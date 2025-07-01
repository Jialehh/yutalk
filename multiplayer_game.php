<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login_page.php');
    exit;
}

$username = $_SESSION['username']; // This username is the logged-in user
$game_id = $_GET['game_id'] ?? '';

if (empty($game_id)) {
    header('Location: multiplayer_lobby.php');
    exit;
}

$matchesFile = 'data/matches.json';
if (!file_exists($matchesFile)) {
    error_log("CRITICAL: matches.json not found in multiplayer_game.php for game_id: " . $game_id);
    echo "错误：找不到游戏数据文件。无法加载游戏。 <a href='multiplayer_lobby.php'>返回大厅</a>";
    exit;
}

$fileData = file_get_contents($matchesFile);
if ($fileData === false) {
    error_log("CRITICAL: Could not read matches.json in multiplayer_game.php for game_id: " . $game_id);
    echo "错误：无法读取游戏数据。 <a href='multiplayer_lobby.php'>返回大厅</a>";
    exit;
}

$data = json_decode($fileData, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("CRITICAL: JSON decode error for matches.json in multiplayer_game.php for game_id: " . $game_id . " - Error: " . json_last_error_msg());
    echo "错误：游戏数据损坏。 <a href='multiplayer_lobby.php'>返回大厅</a>";
    exit;
}

if (!isset($data['games'][$game_id])) {
    header('Location: multiplayer_lobby.php?error=game_not_found');
    exit;
}

$game = $data['games'][$game_id];
$myRole = ''; 
$opponentUsername = '';

if (isset($game['player1']) && $game['player1']['username'] === $username) {
    $myRole = $game['player1']['role'];
    $opponentUsername = $game['player2']['username'] ?? '等待中...';
} elseif (isset($game['player2']) && $game['player2']['username'] === $username) {
    $myRole = $game['player2']['role'];
    $opponentUsername = $game['player1']['username'] ?? '等待中...';
} else {
    error_log("User $username is accessing game $game_id but is not listed as player1 or player2.");
    // Consider redirect or allow spectating (current game.js might limit UI if myRole is empty)
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>对战模式 - 游戏ID: <?php echo htmlspecialchars($game_id); ?></title>
    <link rel="stylesheet" href="game-style.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .game-container { max-width: 900px; margin: auto; background: white; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .game-info-bar { display: flex; justify-content: space-around; padding: 10px; background-color: #eee; margin-bottom:15px; border-radius: 5px;}
        .game-info-bar div { margin: 0 15px; font-size: 1.1em; }
        #game-board { 
            border: 2px solid #333; 
            margin-bottom:15px; 
            background-color: #f0f8ff; /* Light alice blue for the board background */
            /* Sized by JS */
        }
        .grid-row { display: flex; /* Will be overridden by JS to display:grid */ }
        .cell { 
            /* border: 1px solid #ddd;  */ /* Light border for cells */
            box-sizing: border-box; 
            display: flex; 
            align-items: center; 
            justify-content: center;
            position: relative; /* For positioning units inside */
         }
        .protected-column-cell { background-color: #e0ffe0; } /* Light green for defender base area */
        .attacker-spawn-area-cell { background-color: #ffe0e0; } /* Light red for attacker spawn */
        
        .action-panels { display: flex; justify-content: space-around; margin-bottom: 15px; }
        #tower-selection-panel, #attacker-unit-panel { 
            border: 1px solid #ccc; 
            padding: 15px; 
            min-height: 120px; 
            width: 45%; 
            background-color: #f9f9f9;
            border-radius: 5px;
            display: none; /* JS shows the relevant one */
        }
        #tower-selection-panel h3, #attacker-unit-panel h3 { margin-top: 0; text-align: center; color: #333; }

        .tower-option, .unit-option { /* Common style for selectable units */
            border: 1px solid #bbb; 
            padding: 8px; 
            margin: 5px; 
            cursor: pointer; 
            background-color: #fff; 
            border-radius: 4px;
            transition: background-color 0.2s;
            text-align: center;
        }
        .tower-option:hover, .unit-option:hover { background-color: #e9e9e9; }
        .tower-option.selected, .unit-option.selected { background-color: #aadeff; border-color: #77c7ff; }
        .tower-option.insufficient-funds, .unit-option.insufficient-funds { opacity: 0.5; cursor: not-allowed; }
        .tower-option.on-cooldown, .unit-option.on-cooldown { background-color: #ffdddd; }


        .game-over-ui { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border: 3px solid #d9534f; border-radius:8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); z-index: 100; text-align: center;}
        .game-over-ui.show { display: block; }
        .game-over-ui h2 { color: #d9534f; margin-top: 0;}
        .game-over-ui button { padding: 10px 20px; background-color: #5cb85c; color:white; border:none; border-radius: 4px; cursor:pointer; font-size: 1em; margin-top:15px;}
        .game-over-ui button:hover { background-color: #4cae4c;}

        #game-messages-container { position: fixed; bottom: 10px; left: 50%; transform: translateX(-50%); z-index: 200; display: flex; flex-direction: column; align-items: center; pointer-events: none;}
        .temporary-game-message { background: rgba(0,0,0,0.75); color: white; padding: 10px 18px; border-radius: 5px; margin-top: 8px; font-size:0.9em; }
        
        .cooldown-progress-bar { width: 90%; height: 6px; background-color: #e0e0e0; margin: 4px auto 0 auto; border-radius: 3px; overflow: hidden;}
        .cooldown-progress { width: 0%; height: 100%; background-color: #5bc0de; transition: width 0.1s linear; }
        .on-cooldown .cooldown-progress { background-color: #f0ad4e; } /* Orange when cooling down */
        .tower-option.on-cooldown .cooldown-progress, .unit-option.on-cooldown .cooldown-progress {
             /* width is set by JS to show cooldown completion, not remaining */
        }


        /* Styles for units on the board */
        .tower, .enemy { position: absolute; /* Positioned by JS within their cell/row */ width: ${CELL_SIZE*0.8}px; height: ${CELL_SIZE*0.8}px; background-size: contain; background-repeat: no-repeat; background-position: center; /* Centered in cell */ left: 10%; top: 10%; /* Basic centering within cell */}
        .spice-tower { background-color: #FFDEAD; /* Example color */ content: "S"; display:flex; align-items:center; justify-content:center; font-weight:bold; }
        .bomb-tower { background-color: #CD5C5C; content: "B"; }
        .producer-tower { background-color: #90EE90; content: "P"; }

        .scout-enemy { background-color: #ADD8E6; content: "s"; display:flex; align-items:center; justify-content:center; font-weight:bold; border-radius:50%;}
        .brute-enemy { background-color: #F08080; content: "b"; border-radius:50%;}
        .health-bar { position: absolute; bottom: -10px; left: 0; width: 100%; height: 5px; background-color: #ccc; border-radius:2px; }
        .health-fill { height: 100%; background-color: red; width: 100%; border-radius:2px;}

    </style>
</head>
<body>
    <div class="game-container">
        <h1>对战模式</h1>
        <p>游戏ID: <span id="game-id-display"><?php echo htmlspecialchars($game_id); ?></span></p>
        <p>你的身份: <strong id="player-role-display" class="role-<?php echo htmlspecialchars($myRole); ?>"><?php echo htmlspecialchars($myRole === 'attacker' ? '进攻方' : ($myRole === 'defender' ? '防守方' : '观战中')); ?></strong></p>
        <p>你的资源: <span id="player-resources">--</span></p>
        <p>对手: <?php echo htmlspecialchars($opponentUsername); ?></p>
        
        <div class="game-info-bar">
            <div>防守方基地: <span id="defender-health">--</span>%</div>
            <div>游戏计时: <span id="game-timer">--:--</span></div>
        </div>

        <?php if (!empty($myRole)): // Only show start button if user is a player ?>
            <button id="start-multiplayer-game-btn">开始游戏</button> 
        <?php endif; ?>
        <a href="multiplayer_lobby.php" style="margin-left: 10px;">返回大厅</a>

        <div id="game-board" style="position:relative;">
            <!-- Grid and units dynamically generated by game.js -->
        </div>

        <div class="action-panels">
            <div id="tower-selection-panel">
                <h3>防御塔 (点击选择)</h3>
                <!-- Tower options populated by game.js based on role and config -->
            </div>
            <div id="attacker-unit-panel">
                <h3>进攻单位 (点击选择)</h3>
                <!-- Attacker unit options populated by game.js based on role and config -->
            </div>
        </div>
        
        <div id="game-messages-container">
            <!-- Temporary messages from showTemporaryMessage in game.js appear here -->
        </div>

        <div id="game-over-screen" class="game-over-ui"> 
            <h2 id="game-over-message">游戏结束！</h2>
            <button onclick="window.location.href='multiplayer_lobby.php'">返回大厅</button>
        </div>
    </div>

    <script>
        const gameId = '<?php echo $game_id; ?>';
        const myRole = '<?php echo $myRole; ?>'; 
        // const currentUserName = '<?php echo $username; ?>'; // Available if game.js needs it
    </script>
    <script src="game.js"></script> 
</body>
</html>
