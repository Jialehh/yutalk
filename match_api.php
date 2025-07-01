<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$username = $_SESSION['username'];
$matchesFile = 'data/matches.json';

// 确保数据文件存在
if (!file_exists(dirname($matchesFile))) {
    mkdir(dirname($matchesFile), 0777, true);
}
if (!file_exists($matchesFile)) {
    file_put_contents($matchesFile, json_encode(['waiting' => null, 'games' => []]));
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'find_match':
        find_match($username, $matchesFile);
        break;
    case 'check_match':
        check_match($username, $matchesFile);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function find_match($username, $file) {
    $data = json_decode(file_get_contents($file), true);

    if ($data['waiting'] && $data['waiting'] !== $username) {
        // 找到对手，创建游戏
        $player1 = $data['waiting'];
        $player2 = $username;
        $data['waiting'] = null; // 清空等待玩家

        $game_id = uniqid('game_');
        $roles = ['defender', 'attacker'];
        shuffle($roles);

        $data['games'][$game_id] = [
            'player1' => ['username' => $player1, 'role' => $roles[0]],
            'player2' => ['username' => $player2, 'role' => $roles[1]],
            'status' => 'starting'
        ];

        file_put_contents($file, json_encode($data));
        echo json_encode(['status' => 'matched', 'game_id' => $game_id]);
    } else {
        // 没有等待的玩家，将当前玩家设为等待
        $data['waiting'] = $username;
        file_put_contents($file, json_encode($data));
        echo json_encode(['status' => 'waiting']);
    }
}

function check_match($username, $file) {
    $data = json_decode(file_get_contents($file), true);

    foreach ($data['games'] as $game_id => $game) {
        if ($game['player1']['username'] === $username || $game['player2']['username'] === $username) {
            if ($game['status'] === 'starting') {
                echo json_encode(['status' => 'matched', 'game_id' => $game_id]);
                return;
            }
        }
    }

    echo json_encode(['status' => 'waiting']);
}