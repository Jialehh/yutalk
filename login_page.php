<?php
session_start();

// 如果已经登录，重定向到主页
if (isset($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录/注册 - 雨由Talk</title>
    <link rel="icon" href="aukp-icon.png" type="image/png">
    <style>
/* style.css from user's initial prompt - assuming this is what they want */
body {
    font-family: 'Arial', sans-serif;
    background: linear-gradient(135deg, #6e8efb, #a777e3);
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    margin: 0;
    color: #333;
}

.container {
    background-color: #ffffff;
    padding: 30px 40px;
    border-radius: 10px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 400px;
    text-align: center;
}

h2 {
    color: #333;
    margin-bottom: 25px;
}

.input-group {
    margin-bottom: 20px;
    text-align: left;
}

.input-group input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    box-sizing: border-box;
    font-size: 16px;
}

.input-group input:focus {
    border-color: #6e8efb;
    outline: none;
    box-shadow: 0 0 0 2px rgba(110, 142, 251, 0.2);
}

.btn {
    background-color: #6e8efb;
    color: white;
    padding: 12px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    width: 100%;
    transition: background-color 0.3s ease;
    margin-top: 10px;
}

.btn:hover {
    background-color: #5a78e4;
}

p {
    margin-top: 20px;
    font-size: 14px;
}

a {
    color: #6e8efb;
    text-decoration: none;
}

a:hover {
    text-decoration: underline;
}

.message {
    padding: 10px;
    margin-top: 15px;
    border-radius: 5px;
    font-size: 14px;
    display: none; /* Hidden by default */
}

.message.success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
    display: block;
}

.message.error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
    display: block;
}

/* Responsive adjustments */
@media (max-width: 480px) {
    .container {
        padding: 20px;
    }

    .input-group input {
        padding: 10px;
        font-size: 15px;
    }

    .btn {
        padding: 10px;
        font-size: 15px;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container" id="login-form">
            <h2>登录</h2>
            <form id="loginFormReal">
                <div class="input-group">
                    <input type="text" id="login-username" placeholder="用户名" required>
                </div>
                <div class="input-group">
                    <input type="password" id="login-password" placeholder="密码" required>
                </div>
                <button type="submit" class="btn">登录</button>
            </form>
            <p>还没有账户? <a href="#" id="show-register">立即注册</a></p>
            <div id="login-message" class="message"></div>
        </div>

        <div class="form-container" id="register-form" style="display: none;">
            <h2>注册</h2>
            <form id="registerFormReal">
                <div class="input-group">
                    <input type="text" id="register-username" placeholder="用户名" required>
                </div>
                <div class="input-group">
                    <input type="password" id="register-password" placeholder="密码" required>
                </div>
                <div class="input-group">
                    <input type="password" id="register-confirm-password" placeholder="确认密码" required>
                </div>
                <button type="submit" class="btn">注册</button>
            </form>
            <p>已有账户? <a href="#" id="show-login">立即登录</a></p>
            <div id="register-message" class="message"></div>
        </div>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('login-form'); // This is the div container
    const registerForm = document.getElementById('register-form'); // This is the div container
    const showRegisterLink = document.getElementById('show-register');
    const showLoginLink = document.getElementById('show-login');

    const loginFormReal = document.getElementById('loginFormReal'); // This is the actual form element
    const registerFormReal = document.getElementById('registerFormReal'); // This is the actual form element

    const loginMessage = document.getElementById('login-message');
    const registerMessage = document.getElementById('register-message');

    showRegisterLink.addEventListener('click', (e) => {
        e.preventDefault();
        loginForm.style.display = 'none';
        registerForm.style.display = 'block';
        clearMessages();
    });

    showLoginLink.addEventListener('click', (e) => {
        e.preventDefault();
        registerForm.style.display = 'none';
        loginForm.style.display = 'block';
        clearMessages();
    });

    loginFormReal.addEventListener('submit', (e) => {
        e.preventDefault();
        const username = document.getElementById('login-username').value;
        const password = document.getElementById('login-password').value;
        
        fetch('auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', username, password })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(loginMessage, data.message, 'success');
                setTimeout(() => window.location.href = 'index.php', 1000);
            } else {
                // The server should be updated to send a 'reason' field for specific errors
                if (data.reason === 'banned') {
                    showMessage(loginMessage, '您的账号已被封禁', 'error');
                } else {
                    showMessage(loginMessage, data.message, 'error');
                }
            }
        })
        .catch(() => showMessage(loginMessage, '网络错误，请重试。', 'error'));
    });

    registerFormReal.addEventListener('submit', (e) => {
        e.preventDefault();
        const username = document.getElementById('register-username').value;
        const password = document.getElementById('register-password').value;
        const confirmPassword = document.getElementById('register-confirm-password').value;

        if (password !== confirmPassword) {
            showMessage(registerMessage, '两次输入的密码不一致!', 'error');
            return;
        }

        fetch('auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'register', username, password })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(registerMessage, data.message, 'success');
                setTimeout(() => {
                     window.location.href = 'index.php';
                }, 1000);
            } else {
                showMessage(registerMessage, data.message, 'error');
            }
        })
        .catch(() => showMessage(registerMessage, '网络错误，请重试。', 'error'));
    });

    function showMessage(element, message, type) {
        element.textContent = message;
        element.className = 'message ' + type; 
    }

    function clearMessages() {
        loginMessage.textContent = '';
        loginMessage.className = 'message'; 
        registerMessage.textContent = '';
        registerMessage.className = 'message';
    }
});
    </script>
</body>
</html>