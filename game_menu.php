<?php
session_start();
$username = $_SESSION['username'] ?? null;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>游戏主菜单 - 校园塔防</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="game-menu.css">
</head>
<body>
    <div class="menu-container">
        <div class="title-card">
            <h1>校园塔防</h1>
            <p>一个激动人心的塔防游戏</p>
        </div>

        <div class="user-info-card">
            <?php if ($username): ?>
                <p class="welcome-message">欢迎回来, <strong><?php echo htmlspecialchars($username); ?></strong>!</p>
            <?php else: ?>
                <p class="welcome-message">您尚未登录</p>
            <?php endif; ?>
        </div>

        <div class="menu-options">
            <?php if ($username): ?>
                <a href="game-index.php?new_game=1" class="menu-button start-game">开始新游戏</a>
                <a href="multiplayer_lobby.php" class="menu-button multiplayer">多人对战</a>
            <?php else: ?>
                <a href="login_page.php" class="menu-button login-prompt">登录以开始游戏</a>
            <?php endif; ?>
            <a href="index.php" class="menu-button back-to-chat">返回雨由Talk</a>
        </div>

        <footer>
            <p>&copy; 2024 雨由Talk. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>