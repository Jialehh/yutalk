<?php
session_start();
header('Content-Type: application/json');

// 数据文件路径
$usersFile = 'data/users.txt';
$groupFile = 'data/group.txt';
$messagesFile = 'data/messages.txt';

// 确保数据目录存在
if (!file_exists('data')) {
    mkdir('data', 0777, true);
}

// 初始化文件
if (!file_exists($usersFile)) {
    file_put_contents($usersFile, '');
}
if (!file_exists($groupFile)) {
    file_put_contents($groupFile, '');
}
if (!file_exists($messagesFile)) {
    file_put_contents($messagesFile, '');
}

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

switch ($action) {
    case 'register':
        register($input['username'], $input['password']);
        break;
    case 'login':
        login($input['username'], $input['password']);
        break;
    case 'logout':
        logout();
        break;
    default:
        echo json_encode(['success' => false, 'message' => '无效操作']);
}

function register($username, $password) {
    global $usersFile, $groupFile;
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => '用户名和密码不能为空']);
        return;
    }
    
    // 检查用户名是否已存在
    $users = file_exists($usersFile) ? file($usersFile, FILE_IGNORE_NEW_LINES) : [];
    foreach ($users as $user) {
        $userData = explode('|', $user);
        if ($userData[0] === $username) {
            echo json_encode(['success' => false, 'message' => '用户名已存在']);
            return;
        }
    }
    
    // 添加新用户
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $userLine = $username . '|' . $hashedPassword . '|' . date('Y-m-d H:i:s') . "\n";
    file_put_contents($usersFile, $userLine, FILE_APPEND);
    
    // 自动加入群聊
    $groupLine = $username . '|' . date('Y-m-d H:i:s') . "\n";
    file_put_contents($groupFile, $groupLine, FILE_APPEND);
    
    $_SESSION['username'] = $username;
    echo json_encode(['success' => true, 'message' => '注册成功并已加入群聊']);
}

function login($username, $password) {
    global $usersFile;
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => '用户名和密码不能为空']);
        return;
    }
    
    $users = file_exists($usersFile) ? file($usersFile, FILE_IGNORE_NEW_LINES) : [];
    foreach ($users as $user) {
        $userData = explode('|', $user);
        if ($userData[0] === $username) {
            // 假设用户状态存储在第四个字段 (username|password|date|status)
            $status = isset($userData[3]) ? $userData[3] : 'active';

            if ($status === 'banned') {
                echo json_encode(['success' => false, 'message' => '该用户已被封禁']);
                return;
            }

            if (password_verify($password, $userData[1])) {
                $_SESSION['username'] = $username;
                echo json_encode(['success' => true, 'message' => '登录成功']);
                return;
            }
            // 找到用户但密码错误，跳出循环，最后会返回“用户名或密码错误”
            break;
        }
    }
    
    echo json_encode(['success' => false, 'message' => '用户名或密码错误']);
}

function logout() {
    session_destroy();
    echo json_encode(['success' => true, 'message' => '退出成功']);
}
?>