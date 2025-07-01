<?php
// The central API for the game. All game logic resides here.
header('Content-Type: application/json');
session_start();

// --- GAME CONFIGURATION ---
const TOWER_INFO = [
    'spice'      => ['cost' => 4, 'cooldown' => 2, 'damage' => 35, 'range' => 8, 'attackInterval' => 1.2],
    'bomb'       => ['cost' => 10, 'cooldown' => 6, 'damage' => 120, 'range' => 1, 'attackInterval' => 3],
    'producer'   => ['cost' => 2, 'cooldown' => 2, 'productionInterval' => 7],
    'cherryBomb' => ['cost' => 20, 'cooldown' => 6, 'damage' => 250],
    'shield'     => ['cost' => 6, 'cooldown' => 20],
];

// Define enemies the Attacker can spawn
const ATTACKER_ENEMY_INFO = [
    'normal_girl' => ['cost' => 5, 'cooldown' => 2, 'health' => 100, 'maxHealth' => 100, 'baseSpeed' => 0.012, 'type_display' => '普通女生'],
    'fast_girl'   => ['cost' => 8, 'cooldown' => 3, 'health' => 70, 'maxHealth' => 70, 'baseSpeed' => 0.020, 'type_display' => '快速女生'],
    'strong_girl' => ['cost' => 12, 'cooldown' => 5, 'health' => 200, 'maxHealth' => 200, 'baseSpeed' => 0.009, 'type_display' => '强壮女生'],
    // Add more types of "girls" (enemies) here for the attacker
];

const CELL_SIZE = 95;
const GRID_ROWS = 6;
const GRID_COLS = 12;
const PROTECTED_COLUMN = 0;

// --- UTILITY FUNCTIONS ---
function get_initial_state() {
    // Determine player role
    $playerRole = ($_SESSION['last_role_assigned'] ?? 'defender') === 'attacker' ? 'defender' : 'attacker';
    $_SESSION['last_role_assigned'] = $playerRole;

    return [
        'player_role' => $playerRole,
        'defender_vegetables' => 10,
        'attacker_vegetables' => 50,
        'defender_health' => 100,
        'towers' => [],
        'enemies' => [],
        'events' => [],
        'gameActive' => false,
        'gameOver' => false,
        'winner' => null,
        'game_start_time' => 0,
        'game_duration_seconds' => 600, // 10 minutes
        'time_remaining' => 600,
        'lastUpdate' => microtime(true),
        'towerCooldowns' => array_fill_keys(array_keys(TOWER_INFO), 0),
        'enemyCooldowns' => array_fill_keys(array_keys(ATTACKER_ENEMY_INFO), 0),
        'enemySpawnInterval' => 2.8, // For PVE if defender is playing
        'nextEnemySpawn' => 0,
        'difficultyIncreaseInterval' => 30,
        'nextDifficultyIncreaseTime' => 0,
        'canSpawnProtectedEnemy' => false,
        'bossSpawnInterval' => 90,
        'nextBossSpawnTime' => 0,
        'bossesPerWave' => 1,
        'leaderSpawnInterval' => 120,
        'nextLeaderSpawnTime' => 0,
        'bomberSpawnInterval' => 45,
        'nextBomberSpawnTime' => 0,
        'boosterColumn' => 1, // Defender's booster
        'boostMultiplier' => 0.6,
        'attacker_spawn_columns' => [GRID_COLS -1, GRID_COLS -2],
    ];
}

// --- MAIN GAME LOOP (RUNS ON EVERY REQUEST) ---
function game_loop(&$state) {
    if (!$state['gameActive'] || $state['gameOver']) {
        return;
    }

    $now = microtime(true);
    $deltaTime = $now - $state['lastUpdate'];
    $state['lastUpdate'] = $now;
    $state['events'] = [];

    // Update game timer
    if ($state['gameActive'] && !$state['gameOver']) {
        $elapsedTime = $now - $state['game_start_time'];
        $state['time_remaining'] = max(0, (int)round($state['game_duration_seconds'] - $elapsedTime));
    }

    // --- Enemy Updates and Special Actions ---
    foreach ($state['enemies'] as $id => &$enemy) {
        if ($enemy['attacking']) continue;

        $enemy['position'] -= $enemy['baseSpeed'] * $deltaTime * 25;

        // --- Handle Special Actions (existing enemy types) ---
        switch ($enemy['type']) {
            case 'healer': // This is a PVE enemy type, adjust if attacker can spawn them
                if ($now >= ($enemy['lastHeal'] ?? 0) + ($enemy['healInterval'] ?? 8)) {
                    $enemy['lastHeal'] = $now;
                    $state['events'][] = ['type' => 'heal_pulse', 'id' => $id];
                    foreach ($state['enemies'] as $otherId => &$otherEnemy) {
                        if ($id !== $otherId && $otherEnemy['health'] > 0) {
                            $otherEnemy['health'] = min($otherEnemy['maxHealth'], $otherEnemy['health'] + 30);
                            $state['events'][] = ['type' => 'heal_receive', 'target_id' => $otherId];
                        }
                    }
                    unset($otherEnemy);
                }
                break;
            case 'rope': // PVE enemy type
                if ($now >= ($enemy['lastPull'] ?? 0) + ($enemy['pullInterval'] ?? 6)) {
                    $targetTowerId = null;
                    $bestCol = -1;
                    foreach ($state['towers'] as $towerId => $t) {
                        if ($t['row'] == $enemy['row'] && $t['col'] < $enemy['position'] && ($enemy['position'] - $t['col']) <= ($enemy['pullRange'] ?? 5) && $t['col'] > $bestCol) {
                            $targetTowerId = $towerId;
                            $bestCol = $t['col'];
                        }
                    }
                    if ($targetTowerId) {
                        $newCol = $state['towers'][$targetTowerId]['col'] + 1;
                        $isOccupied = false;
                        foreach ($state['towers'] as $t) {
                            if ($t['row'] == $enemy['row'] && $t['col'] == $newCol) $isOccupied = true;
                        }
                        if (!$isOccupied && $newCol < GRID_COLS) {
                            $enemy['lastPull'] = $now;
                            $state['towers'][$targetTowerId]['col'] = $newCol;
                            $state['events'][] = ['type' => 'rope_pull', 'from_id' => $id, 'to_id' => $targetTowerId];
                        }
                    }
                }
                break;
            case 'leader': // PVE enemy type
                if ($now >= ($enemy['lastSummon'] ?? 0) + ($enemy['summonInterval'] ?? 12)) {
                    $enemy['lastSummon'] = $now;
                    $minionId = uniqid('enemy_');
                    // Ensure minion type is defined or default
                    $state['enemies'][$minionId] = ['id' => $minionId, 'row' => $enemy['row'], 'position' => $enemy['position'] + 1, 'health' => 100, 'maxHealth' => 100, 'type' => 'normal', 'baseSpeed' => 0.012, 'attacking' => false];
                }
                if ($now >= ($enemy['lastAttack'] ?? 0) + ($enemy['attackInterval'] ?? 7)) {
                     $targetTowerId = null;
                     $bestCol = -1;
                     foreach($state['towers'] as $towerId => $t) {
                         if ($t['row'] == $enemy['row'] && $t['col'] < $enemy['position'] && ($enemy['position'] - $t['col']) <= ($enemy['attackRange'] ?? 6) && $t['col'] > $bestCol) {
                            $targetTowerId = $towerId;
                            $bestCol = $t['col'];
                         }
                     }
                     if($targetTowerId) {
                         $enemy['lastAttack'] = $now;
                         $state['events'][] = ['type' => 'leader_attack', 'from_id' => $id, 'to_id' => $targetTowerId];
                         unset($state['towers'][$targetTowerId]);
                     }
                }
                break;
             case 'bomber': // PVE enemy type
                if ($now >= ($enemy['lastBomb'] ?? 0) + ($enemy['bombInterval'] ?? 20)) {
                    $enemy['lastBomb'] = $now;
                    $targetRow = rand(0, GRID_ROWS - 2);
                    $targetCol = rand(PROTECTED_COLUMN + 1, GRID_COLS - 2);
                    $state['events'][] = ['type' => 'bomber_attack', 'targetRow' => $targetRow, 'targetCol' => $targetCol, 'triggerTime' => $now + 2];
                }
                break;
        }

        // --- Collision and Damage Logic ---
        $currentPosCol = floor($enemy['position']);
        foreach($state['towers'] as $towerId => &$tower) {
            if ($tower['row'] == $enemy['row'] && $tower['col'] == $currentPosCol && abs($currentPosCol - $enemy['position']) < 0.5) {
                $enemy['attacking'] = true;
                $eatTime = isset($tower['shielded']) && $tower['shielded'] ? 4 : 2;
                $enemy['attackFinishTime'] = $now + $eatTime;
                $enemy['targetTowerId'] = $towerId;
            }
        }
        unset($tower); // Release reference

        if ($enemy['position'] <= PROTECTED_COLUMN + 0.5) {
            $damage = 20; // Default damage
            // Check for specific PVE types that might do more damage
            if ($enemy['type'] === 'boss') $damage = 50;
            if ($enemy['type'] === 'leader') $damage = 80;
            // Attacker-spawned units might have their own damage values if they reach the end
            // For now, all units reaching end do 'damage' to defender_health
            $state['defender_health'] -= $damage;
            unset($state['enemies'][$id]);
            continue;
        }
    }
    unset($enemy);

    // Process finished attacks
    foreach ($state['enemies'] as $id => &$enemy) {
        if ($enemy['attacking'] && $now >= ($enemy['attackFinishTime'] ?? 0)) {
             if (isset($enemy['targetTowerId']) && isset($state['towers'][$enemy['targetTowerId']])) {
                $targetTower = &$state['towers'][$enemy['targetTowerId']];
                if ($targetTower['shielded']) {
                    $targetTower['shielded'] = false;
                } else {
                    unset($state['towers'][$enemy['targetTowerId']]);
                }
             }
             $enemy['attacking'] = false;
        }
    }
    unset($enemy);

    // Update Towers
    foreach ($state['towers'] as $towerId => &$tower) {
        $towerInfo = TOWER_INFO[$tower['type']];
        if ($tower['type'] === 'producer') {
            if ($now >= ($tower['lastProduction'] ?? 0) + $towerInfo['productionInterval']) {
                $tower['lastProduction'] = $now;
                $state['defender_vegetables']++;
                $state['events'][] = ['type' => 'producer_veg', 'row' => $tower['row'], 'col' => $tower['col']];
            }
        } else { // Attacking towers
            $currentAttackInterval = $towerInfo['attackInterval'] * ($tower['col'] == $state['boosterColumn'] ? $state['boostMultiplier'] : 1);
            if ($now >= ($tower['lastAttack'] ?? 0) + $currentAttackInterval) {
                $target = null;
                $bestPos = 1000; // Find closest enemy in range
                foreach($state['enemies'] as $enemyId => $e) {
                    if ($e['row'] == $tower['row'] && $e['position'] > $tower['col'] && ($e['position'] - $tower['col']) < $towerInfo['range'] && $e['position'] < $bestPos) {
                        $target = $enemyId;
                        $bestPos = $e['position'];
                    }
                }
                if ($target !== null && isset($state['enemies'][$target])) {
                    $tower['lastAttack'] = $now;
                    if ($tower['type'] === 'spice') {
                        $state['enemies'][$target]['health'] -= $towerInfo['damage'];
                        $state['events'][] = ['type' => 'attack', 'from' => ['row' => $tower['row'], 'col' => $tower['col']], 'to_id' => $target];
                    } else if ($tower['type'] === 'bomb') {
                        $state['events'][] = ['type' => 'explosion', 'row' => $tower['row'], 'col' => $tower['col']];
                        foreach($state['enemies'] as $enemyIdExplode => &$e_explode) {
                           if (abs($e_explode['row'] - $tower['row']) <= 1 && abs($e_explode['position'] - ($tower['col'] + 0.5)) <= 1.5) {
                               $e_explode['health'] -= $towerInfo['damage'];
                           }
                        }
                        unset($e_explode);
                    }
                }
            }
        }
    }
    unset($tower);

    // Remove dead enemies
    foreach ($state['enemies'] as $id => $enemy) {
        if ($enemy['health'] <= 0) {
            unset($state['enemies'][$id]);
        }
    }

    // --- PVE Spawning Logic (Only if current player is defender) ---
    if ($state['player_role'] === 'defender') {
        // Normal Spawner
        if ($now >= $state['nextEnemySpawn']) {
            $state['nextEnemySpawn'] = $now + $state['enemySpawnInterval'];
            $type = 'normal'; $health = 100; $speed = 0.012; $extra = [];
            if ($state['canSpawnProtectedEnemy']) {
                $roll = rand(0, 100);
                if ($roll < 15) { $type = 'healer'; $health = 150; $speed = 0.013; $extra = ['lastHeal' => $now, 'healInterval' => 8]; }
                else if ($roll < 35) { $type = 'protected'; $health = 250; $speed = 0.011; }
                else if ($roll < 55) { $type = 'rope'; $health = 180; $speed = 0.009; $extra = ['lastPull' => $now, 'pullInterval' => 6, 'pullRange' => 5]; }
                else if ($roll < 70) { $type = 'fast'; $health = 80; $speed = 0.020; }
            }
            $id = uniqid('enemy_');
            $state['enemies'][$id] = array_merge([
                'id' => $id, 'row' => rand(0, GRID_ROWS - 1), 'position' => GRID_COLS,
                'health' => $health, 'maxHealth' => $health, 'type' => $type, 'baseSpeed' => $speed,
                'attacking' => false, 'attackFinishTime' => 0
            ], $extra);
        }
        
        // Bomber Spawner
        if ($now >= $state['nextBomberSpawnTime']) {
            $state['nextBomberSpawnTime'] = $now + $state['bomberSpawnInterval'];
            $id = uniqid('bomber_');
            $state['enemies'][$id] = ['id' => $id, 'row' => rand(0, GRID_ROWS - 1), 'position' => GRID_COLS, 'health' => 1200, 'maxHealth' => 1200, 'type' => 'bomber', 'baseSpeed' => 0.007, 'attacking' => false, 'lastBomb' => $now, 'bombInterval' => 20];
            $state['events'][] = ['type' => 'message', 'text' => "炸弹女生来袭！注意躲避！"];
        }

        // Boss Spawner
        if ($now >= $state['nextBossSpawnTime']) {
            $state['nextBossSpawnTime'] = $now + $state['bossSpawnInterval'];
            $state['events'][] = ['type' => 'message', 'text' => "BOSS WAVE INCOMING!"];
            for($i=0; $i < $state['bossesPerWave']; $i++) {
                $id = uniqid('boss_');
                $state['enemies'][$id] = ['id' => $id, 'row' => rand(0, GRID_ROWS-1), 'position' => GRID_COLS + ($i*2), 'health' => 1000, 'maxHealth' => 1000, 'type' => 'boss', 'baseSpeed' => 0.008, 'attacking' => false];
            }
            $state['bossesPerWave']++;
        }

        // Leader Spawner
        if ($now >= $state['nextLeaderSpawnTime']) {
            $state['nextLeaderSpawnTime'] = $now + $state['leaderSpawnInterval'];
            $id = uniqid('leader_');
            $state['enemies'][$id] = ['id' => $id, 'row' => rand(0, GRID_ROWS - 1), 'position' => GRID_COLS, 'health' => 1800, 'maxHealth' => 1800, 'type' => 'leader', 'baseSpeed' => 0.006, 'attacking' => false, 'lastSummon' => $now, 'summonInterval' => 12, 'lastAttack' => $now, 'attackInterval' => 7, 'attackRange' => 6];
            $state['events'][] = ['type' => 'message', 'text' => "领导来袭！小心你的防御塔！"];
        }

        // Difficulty (Only applies if PVE spawners are active for the defender)
        if ($now >= $state['nextDifficultyIncreaseTime']) {
            $state['nextDifficultyIncreaseTime'] = $now + $state['difficultyIncreaseInterval'];
            $state['enemySpawnInterval'] = max(0.6, $state['enemySpawnInterval'] - 0.4);
            if (!$state['canSpawnProtectedEnemy']) {
                $state['canSpawnProtectedEnemy'] = true;
                $state['events'][] = ['type' => 'message', 'text' => "敌人增强：新类型出现 & 速度加快!"];
            } else {
                $state['events'][] = ['type' => 'message', 'text' => "敌人来得更快了!"];
            }
        }
    }

    // Process triggered bomber attacks
    $kept_events = [];
    $new_events = [];
    foreach ($state['events'] as $event) {
        if ($event['type'] === 'bomber_attack' && $now >= ($event['triggerTime'] ?? $now + 1000)) { // Ensure triggerTime exists
            $new_events[] = ['type' => 'explosion', 'row' => $event['targetRow'], 'col' => $event['targetCol']];
            
            foreach ($state['enemies'] as $enemyId => &$e) {
                if (abs($e['row'] - $event['targetRow']) <= 1 && abs($e['position'] - ($event['targetCol'] + 0.5)) <= 1.5) {
                    $e['health'] -= 150; 
                }
            }
            unset($e);

            for ($r_offset = 0; $r_offset < 2; $r_offset++) {
                for ($c_offset = 0; $c_offset < 2; $c_offset++) {
                    $checkRow = $event['targetRow'] + $r_offset;
                    $checkCol = $event['targetCol'] + $c_offset;
                    foreach ($state['towers'] as $towerId => &$tower_check) {
                        if ($tower_check['row'] == $checkRow && $tower_check['col'] == $checkCol) {
                            if ($tower_check['shielded']) {
                                $tower_check['shielded'] = false;
                            } else {
                                unset($state['towers'][$towerId]);
                            }
                        }
                    }
                    unset($tower_check);
                }
            }
        } else {
            $kept_events[] = $event;
        }
    }
    $state['events'] = array_merge($kept_events, $new_events);


    // Check game over conditions
    if ($state['defender_health'] <= 0) {
        $state['defender_health'] = 0;
        $state['gameOver'] = true;
        $state['gameActive'] = false;
        $state['winner'] = 'attacker'; // Attacker wins
    } elseif ($state['time_remaining'] <= 0 && $state['defender_health'] > 0) {
        $state['gameOver'] = true;
        $state['gameActive'] = false;
        $state['winner'] = 'defender'; // Defender wins by surviving
    }
}


// --- ACTION HANDLERS ---
$action = $_POST['action'] ?? 'get_state';

// Initialize or reset state for a new game
if (!isset($_SESSION['game_state']) || $action === 'start_game') {
    $_SESSION['game_state'] = get_initial_state(); // This also handles role alternation
}

$state = &$_SESSION['game_state']; // Get a reference to the current game state

// If action is start_game, we need to re-initialize specific parts for a fresh game,
// even after get_initial_state might have run (e.g. if user clicks start_game multiple times)
if ($action === 'start_game') {
    $now = microtime(true);
    $state['gameActive'] = true;
    $state['gameOver'] = false;
    $state['winner'] = null;
    $state['game_start_time'] = $now;
    $state['lastUpdate'] = $now;
    $state['time_remaining'] = $state['game_duration_seconds'];
    $state['defender_health'] = 100; // Reset health
    $state['defender_vegetables'] = 10; // Reset resources
    $state['attacker_vegetables'] = 50; // Reset resources

    $state['towerCooldowns'] = array_fill_keys(array_keys(TOWER_INFO), 0);
    $state['enemyCooldowns'] = array_fill_keys(array_keys(ATTACKER_ENEMY_INFO), 0);

    $state['nextEnemySpawn'] = $now + $state['enemySpawnInterval'];
    $state['nextDifficultyIncreaseTime'] = $now + $state['difficultyIncreaseInterval'];
    $state['nextBomberSpawnTime'] = $now + $state['bomberSpawnInterval'];
    $state['nextBossSpawnTime'] = $now + $state['bossSpawnInterval'];
    $state['nextLeaderSpawnTime'] = $now + $state['leaderSpawnInterval'];
    
    $state['towers'] = [];
    $state['enemies'] = [];
    $state['events'] = [];
    $state['bossesPerWave'] = 1;
    $state['canSpawnProtectedEnemy'] = false;
}


switch ($action) {
    // start_game case is now largely handled by the block above to ensure proper re-initialization
    case 'start_game':
        // Most logic moved above to ensure it runs even if get_initial_state() just ran
        break;

    case 'place_tower': // Defender's action
        if ($state['player_role'] !== 'defender' || !$state['gameActive'] || $state['gameOver']) break;

        $type = $_POST['type'] ?? '';
        $row = (int)($_POST['row'] ?? -1);
        $col = (int)($_POST['col'] ?? -1);
        $towerInfo = TOWER_INFO[$type] ?? null;

        if ($towerInfo && $row > -1 && $col > -1 && $col !== PROTECTED_COLUMN) {
            $isOccupied = false;
            foreach ($state['towers'] as $t) {
                if ($t['row'] == $row && $t['col'] == $col) $isOccupied = true;
            }

            if (!$isOccupied && $state['defender_vegetables'] >= $towerInfo['cost'] && microtime(true) >= ($state['towerCooldowns'][$type] ?? 0)) {
                if ($type === 'cherryBomb') {
                    $state['defender_vegetables'] -= $towerInfo['cost'];
                    $state['towerCooldowns'][$type] = microtime(true) + $towerInfo['cooldown'];
                    $state['events'][] = ['type' => 'explosion', 'row' => $row, 'col' => $col];
                    foreach($state['enemies'] as $enemyId => &$e) {
                       if (abs($e['row'] - $row) <= 1 && abs($e['position'] - ($col + 0.5)) <= 1.5) {
                           $e['health'] -= $towerInfo['damage'];
                       }
                    }
                    unset($e);
                } else if ($type === 'shield') {
                    $targetTowerId = null;
                     foreach ($state['towers'] as $id => $t) {
                        if ($t['row'] == $row && $t['col'] == $col) $targetTowerId = $id;
                    }
                    if ($targetTowerId && empty($state['towers'][$targetTowerId]['shielded'])) {
                        $state['defender_vegetables'] -= $towerInfo['cost'];
                        $state['towerCooldowns'][$type] = microtime(true) + $towerInfo['cooldown'];
                        $state['towers'][$targetTowerId]['shielded'] = true;
                    }
                } else {
                    $state['defender_vegetables'] -= $towerInfo['cost'];
                    $state['towerCooldowns'][$type] = microtime(true) + $towerInfo['cooldown'];
                    $id = uniqid('tower_');
                    $state['towers'][$id] = [
                        'id' => $id, 'type' => $type, 'row' => $row, 'col' => $col,
                        'lastAttack' => 0, 'lastProduction' => microtime(true), 'shielded' => false
                    ];
                }
            }
        }
        break;

    case 'place_enemy': // Attacker's action
        if ($state['player_role'] !== 'attacker' || !$state['gameActive'] || $state['gameOver']) break;

        $enemyTypeToPlace = $_POST['type'] ?? ''; // e.g. 'normal_girl'
        $row = (int)($_POST['row'] ?? -1);
        $col = (int)($_POST['col'] ?? -1); 

        $enemyInfo = ATTACKER_ENEMY_INFO[$enemyTypeToPlace] ?? null;

        if ($enemyInfo && $row >= 0 && $row < GRID_ROWS && in_array($col, $state['attacker_spawn_columns'])) {
            if ($state['attacker_vegetables'] >= $enemyInfo['cost'] && microtime(true) >= ($state['enemyCooldowns'][$enemyTypeToPlace] ?? 0) ) {
                $state['attacker_vegetables'] -= $enemyInfo['cost'];
                $state['enemyCooldowns'][$enemyTypeToPlace] = microtime(true) + $enemyInfo['cooldown'];

                $id = uniqid('enemy_'); // Ensure unique ID
                $state['enemies'][$id] = [
                    'id' => $id,
                    'row' => $row,
                    'position' => $col + 0.5, 
                    'health' => $enemyInfo['health'],
                    'maxHealth' => $enemyInfo['maxHealth'],
                    'type' => $enemyTypeToPlace, // Use the key from ATTACKER_ENEMY_INFO as the type
                    'baseSpeed' => $enemyInfo['baseSpeed'],
                    'attacking' => false,
                    'attackFinishTime' => 0,
                    // Ensure all necessary fields expected by game_loop's enemy processing are present
                ];
            }
        }
        break;
    
    case 'move_booster': // Defender's booster
        if ($state['player_role'] !== 'defender' || !$state['gameActive'] || $state['gameOver']) break;
        $direction = $_POST['direction'] ?? '';
        if ($direction === 'left') $state['boosterColumn']--;
        if ($direction === 'right') $state['boosterColumn']++;
        $state['boosterColumn'] = max(PROTECTED_COLUMN + 1, min(GRID_COLS - 1, $state['boosterColumn']));
        break;

    case 'get_state':
    default:
        // game_loop is called below, outside the switch
        break;
}

game_loop($state);

echo json_encode($state);
?>
