<?php
session_start();

// 检查用户是否已登录
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
    <title>设置 - 雨由Talk</title>
    <link rel="icon" href="aukp-icon.png" type="image/png">
    <link rel="stylesheet" href="settings.css">
</head>
<body>
    <div class="settings-container">
        <header class="settings-header">
            <a href="mobile_index.php" class="back-button">
                <img src="hide-icon.svg" alt="返回">
            </a>
            <h1>设置</h1>
        </header>

        <main class="settings-content">
            <div class="settings-group">
                <h2 class="settings-title">聊天设置</h2>
                <div class="setting-item">
                    <label for="backgroundImageInput">自定义聊天背景</label>
                    <input type="file" id="backgroundImageInput" accept="image/*" class="file-input">
                    <button onclick="document.getElementById('backgroundImageInput').click()" class="btn">选择图片</button>
                </div>
                 <div class="setting-item">
                    <label>恢复默认背景</label>
                    <button id="resetBackgroundButton" class="btn btn-secondary">恢复默认</button>
                </div>
                <div class="setting-item">
                    <label for="bubbleStyleSelect">聊天气泡样式</label>
                    <select id="bubbleStyleSelect">
                        <option value="default">默认</option>
                        <option value="rounded">圆角</option>
                        <option value="minimal">简约</option>
                    </select>
                </div>
            </div>

            <div class="settings-group">
                <h2 class="settings-title">应用设置</h2>
                <div class="setting-item">
                    <label for="theme-toggle">夜间模式</label>
                    <label class="switch">
                        <input type="checkbox" id="theme-toggle">
                        <span class="slider round"></span>
                    </label>
                </div>
                 <div class="setting-item">
                    <label>清除本地缓存</label>
                    <button id="clearCacheButton" class="btn btn-danger">立即清除</button>
                </div>
            </div>

             <div class="settings-group profile-section">
                <h2 class="settings-title">个人资料</h2>
                <a href="profile.php" class="btn btn-full-width">编辑我的资料</a>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const backgroundImageInput = document.getElementById('backgroundImageInput');
            const resetBackgroundButton = document.getElementById('resetBackgroundButton');
            const bubbleStyleSelect = document.getElementById('bubbleStyleSelect');
            const themeToggle = document.getElementById('theme-toggle');
            const clearCacheButton = document.getElementById('clearCacheButton');

            // 1. 加载并应用保存的设置
            const savedBubbleStyle = localStorage.getItem('chatBubbleStyle') || 'default';
            bubbleStyleSelect.value = savedBubbleStyle;

            const savedTheme = localStorage.getItem('appTheme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
            if (savedTheme === 'dark') {
                themeToggle.checked = true;
            }

            // 2. 事件监听器
            backgroundImageInput.addEventListener('change', (event) => {
                const file = event.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        localStorage.setItem('chatBackgroundImage', e.target.result);
                        alert('背景图片已更新，返回聊天界面后生效。');
                    };
                    reader.readAsDataURL(file);
                }
            });

            resetBackgroundButton.addEventListener('click', () => {
                localStorage.removeItem('chatBackgroundImage');
                alert('已恢复默认背景，返回聊天界面后生效。');
            });

            bubbleStyleSelect.addEventListener('change', (event) => {
                localStorage.setItem('chatBubbleStyle', event.target.value);
                alert('气泡样式已更新，返回聊天界面后生效。');
            });

            themeToggle.addEventListener('change', () => {
                if (themeToggle.checked) {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('appTheme', 'dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                    localStorage.setItem('appTheme', 'light');
                }
            });

            clearCacheButton.addEventListener('click', () => {
                if(confirm('确定要清除所有本地设置和缓存吗？这将恢复所有自定义设置。')) {
                    localStorage.clear();
                    alert('缓存已清除。');
                    window.location.reload();
                }
            });
        });
    </script>
</body>
</html>