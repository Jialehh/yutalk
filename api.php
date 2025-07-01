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
const CELL_SIZE = 95;
const GRID_ROWS = 6;
const GRID_COLS = 12;
const PROTECTED_COLUMN = 0;

// --- UTILITY FUNCTIONS ---
function get_initial_state() {
    return [
        'vegetables' => 10,
        'health' => 100,
        'towers' => [],
        'enemies' => [],
        'events' => [],
        'gameActive' => false,
        'gameOver' => false,
        'lastUpdate' => microtime(true),
        'towerCooldowns' => ['spice' => 0, 'bomb' => 0, 'producer' => 0, 'cherryBomb' => 0, 'shield' => 0],
        'enemySpawnInterval' => 2.8,
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
        'boosterColumn' => 1,
        'boostMultiplier' => 0.6,
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

    // --- Enemy Updates and Special Actions ---
    foreach ($state['enemies'] as $id => &$enemy) {
        if ($enemy['attacking']) continue;

        $enemy['position'] -= $enemy['baseSpeed'] * $deltaTime * 25;

        // --- Handle Special Actions ---
        switch ($enemy['type']) {
            case 'healer':
                if ($now >= $enemy['lastHeal'] + $enemy['healInterval']) {
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
            case 'rope':
                if ($now >= $enemy['lastPull'] + $enemy['pullInterval']) {
                    $targetTowerId = null;
                    $bestCol = -1;
                    foreach ($state['towers'] as $towerId => $t) {
                        if ($t['row'] == $enemy['row'] && $t['col'] < $enemy['position'] && ($enemy['position'] - $t['col']) <= $enemy['pullRange'] && $t['col'] > $bestCol) {
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
            case 'leader':
                if ($now >= $enemy['lastSummon'] + $enemy['summonInterval']) {
                    $enemy['lastSummon'] = $now;
                    $minionId = uniqid('enemy_');
                    $state['enemies'][$minionId] = ['id' => $minionId, 'row' => $enemy['row'], 'position' => $enemy['position'] + 1, 'health' => 100, 'maxHealth' => 100, 'type' => 'normal', 'baseSpeed' => 0.012, 'attacking' => false];
                }
                if ($now >= $enemy['lastAttack'] + $enemy['attackInterval']) {
                     $targetTowerId = null;
                     $bestCol = -1;
                     foreach($state['towers'] as $towerId => $t) {
                         if ($t['row'] == $enemy['row'] && $t['col'] < $enemy['position'] && ($enemy['position'] - $t['col']) <= $enemy['attackRange'] && $t['col'] > $bestCol) {
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
             case 'bomber':
                if ($now >= $enemy['lastBomb'] + $enemy['bombInterval']) {
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
        if ($enemy['position'] <= PROTECTED_COLUMN + 0.5) {
            $damage = 20;
            if ($enemy['type'] === 'boss') $damage = 50;
            if ($enemy['type'] === 'leader') $damage = 80;
            $state['health'] -= $damage;
            unset($state['enemies'][$id]);
            continue;
        }
    }
    unset($enemy); unset($tower);

    // Process finished attacks
    foreach ($state['enemies'] as $id => &$enemy) {
        if ($enemy['attacking'] && $now >= $enemy['attackFinishTime']) {
             if (isset($state['towers'][$enemy['targetTowerId']])) {
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
            if ($now >= $tower['lastProduction'] + $towerInfo['productionInterval']) {
                $tower['lastProduction'] = $now;
                $state['vegetables']++;
                $state['events'][] = ['type' => 'producer_veg', 'row' => $tower['row'], 'col' => $tower['col']];
            }
        } else {
            $currentAttackInterval = $towerInfo['attackInterval'] * ($tower['col'] == $state['boosterColumn'] ? $state['boostMultiplier'] : 1);
            if ($now >= $tower['lastAttack'] + $currentAttackInterval) {
                $target = null;
                $bestPos = 1000;
                foreach($state['enemies'] as $enemyId => $e) {
                    if ($e['row'] == $tower['row'] && $e['position'] > $tower['col'] && $e['position'] < $tower['col'] + $towerInfo['range'] && $e['position'] < $bestPos) {
                        $target = $enemyId;
                        $bestPos = $e['position'];
                    }
                }
                if ($target !== null) {
                    $tower['lastAttack'] = $now;
                    if ($tower['type'] === 'spice') {
                        $state['enemies'][$target]['health'] -= $towerInfo['damage'];
                        $state['events'][] = ['type' => 'attack', 'from' => ['row' => $tower['row'], 'col' => $tower['col']], 'to_id' => $target];
                    } else if ($tower['type'] === 'bomb') {
                        $state['events'][] = ['type' => 'explosion', 'row' => $tower['row'], 'col' => $tower['col']];
                        foreach($state['enemies'] as $enemyId => &$e) {
                           if (abs($e['row'] - $tower['row']) <= 1 && abs($e['position'] - ($tower['col'] + 0.5)) <= 1.5) {
                               $e['health'] -= $towerInfo['damage'];
                           }
                        }
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

    // --- Spawning Logic ---
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

    // Process triggered bomber attacks
    $kept_events = [];
    $new_events = [];
    foreach ($state['events'] as $event) {
        if ($event['type'] === 'bomber_attack' && $now >= $event['triggerTime']) {
            $new_events[] = ['type' => 'explosion', 'row' => $event['targetRow'], 'col' => $event['targetCol']];
            
            // Damage enemies in a 2x2 area
            foreach ($state['enemies'] as $enemyId => &$e) {
                if (abs($e['row'] - $event['targetRow']) <= 1 && abs($e['position'] - ($event['targetCol'] + 0.5)) <= 1.5) {
                    $e['health'] -= 150; // Bomber damage to enemies
                }
            }
            unset($e);

            // Damage towers in a 2x2 area
            for ($r = 0; $r < 2; $r++) {
                for ($c = 0; $c < 2; $c++) {
                    $checkRow = $event['targetRow'] + $r;
                    $checkCol = $event['targetCol'] + $c;
                    foreach ($state['towers'] as $towerId => &$tower) {
                        if ($tower['row'] == $checkRow && $tower['col'] == $checkCol) {
                            if ($tower['shielded']) {
                                $tower['shielded'] = false;
                            } else {
                                unset($state['towers'][$towerId]);
                            }
                        }
                    }
                    unset($tower);
                }
            }
        } else {
            $kept_events[] = $event;
        }
    }
    $state['events'] = array_merge($kept_events, $new_events);

    // Difficulty
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

    // Check game over
    if ($state['health'] <= 0) {
        $state['health'] = 0;
        $state['gameOver'] = true;
        $state['gameActive'] = false;
    }
}

// --- ACTION HANDLERS ---
$action = $_POST['action'] ?? 'get_state';

if (!isset($_SESSION['game_state'])) {
    $_SESSION['game_state'] = get_initial_state();
}

$state = &$_SESSION['game_state'];

switch ($action) {
    case 'start_game':
        if (!$state['gameActive']) {
            $now = microtime(true);
            $state['gameActive'] = true;
            $state['lastUpdate'] = $now;
            $state['nextEnemySpawn'] = $now + $state['enemySpawnInterval'];
            $state['nextDifficultyIncreaseTime'] = $now + $state['difficultyIncreaseInterval'];
            $state['nextBomberSpawnTime'] = $now + $state['bomberSpawnInterval'];
            $state['nextBossSpawnTime'] = $now + $state['bossSpawnInterval'];
            $state['nextLeaderSpawnTime'] = $now + $state['leaderSpawnInterval'];
        }
        break;

    case 'place_tower':
        $type = $_POST['type'] ?? '';
        $row = (int)($_POST['row'] ?? -1);
        $col = (int)($_POST['col'] ?? -1);
        $towerInfo = TOWER_INFO[$type] ?? null;

        if ($state['gameActive'] && $towerInfo && $row > -1 && $col > -1 && $col !== PROTECTED_COLUMN) {
            $isOccupied = false;
            foreach ($state['towers'] as $t) {
                if ($t['row'] == $row && $t['col'] == $col) $isOccupied = true;
            }

            if (!$isOccupied && $state['vegetables'] >= $towerInfo['cost'] && microtime(as_float: true) >= $state['towerCooldowns'][$type]) {
                if ($type === 'cherryBomb') {
                    $state['vegetables'] -= $towerInfo['cost'];
                    $state['towerCooldowns'][$type] = microtime(true) + $towerInfo['cooldown'];
                    $state['events'][] = ['type' => 'explosion', 'row' => $row, 'col' => $col];
                    foreach($state['enemies'] as $enemyId => &$e) {
                       if (abs($e['row'] - $row) <= 1 && abs($e['position'] - ($col + 0.5)) <= 1.5) {
                           $e['health'] -= $towerInfo['damage'];
                       }
                    }
                } else if ($type === 'shield') {
                    $targetTowerId = null;
                     foreach ($state['towers'] as $id => $t) {
                        if ($t['row'] == $row && $t['col'] == $col) $targetTowerId = $id;
                    }
                    if ($targetTowerId && empty($state['towers'][$targetTowerId]['shielded'])) {
                        $state['vegetables'] -= $towerInfo['cost'];
                        $state['towerCooldowns'][$type] = microtime(true) + $towerInfo['cooldown'];
                        $state['towers'][$targetTowerId]['shielded'] = true;
                    }
                } else {
                    $state['vegetables'] -= $towerInfo['cost'];
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
    
    case 'move_booster':
        $direction = $_POST['direction'] ?? '';
        if ($direction === 'left') $state['boosterColumn']--;
        if ($direction === 'right') $state['boosterColumn']++;
        $state['boosterColumn'] = max(PROTECTED_COLUMN + 1, min(GRID_COLS - 1, $state['boosterColumn']));
        break;

    case 'get_state':
    default:
        game_loop($state);
        break;
}
