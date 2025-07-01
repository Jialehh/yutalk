<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login_page.php');
    exit;
}

$username = $_SESSION['username'];
$game_id = $_GET['game_id'] ?? '';

if (empty($game_id)) {
    header('Location: multiplayer_lobby.php');
    exit;
}

$matchesFile = 'data/matches.json';
$data = json_decode(file_get_contents($matchesFile), true);

if (!isset($data['games'][$game_id])) {
    // 游戏不存在
    header('Location: multiplayer_lobby.php');
    exit;
}

$game = $data['games'][$game_id];
$myRole = '';
$opponent = '';

if ($game['player1']['username'] === $username) {
    $myRole = $game['player1']['role'];
    $opponent = $game['player2']['username'];
} elseif ($game['player2']['username'] === $username) {
    $myRole = $game['player2']['role'];
    $opponent = $game['player1']['username'];
} else {
    // 玩家不属于这个游戏
    header('Location: multiplayer_lobby.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>对战 - <?php echo htmlspecialchars($myRole); ?></title>
    <link rel="stylesheet" href="game-style.css">
    <link rel="stylesheet" href="mobile.css">
</head>
<body>
    <div class="game-container">
        <h1>对战模式</h1>
        <p>你的身份: <span class="role-<?php echo $myRole; ?>"><?php echo $myRole === 'attacker' ? '进攻方' : '防守方'; ?></span></p>
        <p>对手: <?php echo htmlspecialchars($opponent); ?></p>
        
        <!-- 游戏棋盘 -->
        <div id="game-board">
            <!-- 棋盘格子将由JS生成 -->
        </div>

        <!-- 卡牌选择 -->
        <div id="card-selection">
            <!-- 卡牌将由JS加载 -->
        </div>
    </div>

    <script>
        const myRole = '<?php echo $myRole; ?>';
        const gameId = '<?php echo $game_id; ?>';
        // 接下来是游戏逻辑的JS，它会处理单位的放置、移动和战斗
        // 这部分会与 api.php 交互来更新游戏状态
    </script>
</body>
</html>
</html>
