<?php
session_start();
if (!isset($_SESSION['username'])) {
    header('Location: login_page.php');
    exit;
}
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>多人对战大厅</title>
    <link rel="stylesheet" href="lobby-style.css">
</head>
<body>
    <div class="lobby-container">
        <h1>多人对战大厅</h1>
        <p>欢迎, <?php echo htmlspecialchars($username); ?>!</p>
        <div id="matchmaking-status">
            <p>点击下面的按钮开始寻找对手。</p>
        </div>
        <button id="find-match-btn">寻找对战</button>
        <a href="game_menu.php">返回菜单</a>
    </div>

    <script>
        document.getElementById('find-match-btn').addEventListener('click', () => {
            const statusDiv = document.getElementById('matchmaking-status');
            statusDiv.innerHTML = '<p class="searching">正在寻找对手...</p>';
            
            // 使用 long-polling 或 WebSocket 来检查匹配状态
            // 这里我们用一个简单的 fetch 轮询来模拟
            const matchInterval = setInterval(() => {
                fetch('match_api.php?action=check_match')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'matched') {
                            clearInterval(matchInterval);
                            statusDiv.innerHTML = `<p class="matched">找到对手！正在进入游戏...</p>`;
                            window.location.href = `multiplayer_game.php?game_id=${data.game_id}`;
                        }
                    })
                    .catch(err => console.error('匹配检查出错:', err));
            }, 2000);

            // 发送开始匹配的请求
            fetch('match_api.php?action=find_match', { method: 'POST' })
                .catch(err => console.error('开始匹配出错:', err));
        });
    </script>
</body>
</html>