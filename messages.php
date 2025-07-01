<?php 
 session_start(); 
 header('Content-Type: application/json'); 
 
 // 检查用户是否已登录 
 if (!isset($_SESSION['username'])) { 
     echo json_encode(['success' => false, 'message' => '请先登录']); 
     exit; 
 } 
 
 $baseDataDir = 'data/chats'; 
 $groupFile = 'data/group.txt'; 
 
 // 确保基础目录存在 
 if (!is_dir($baseDataDir)) { 
     if (!mkdir($baseDataDir, 0777, true)) { 
         echo json_encode(['success' => false, 'message' => '无法创建聊天数据目录']); 
         exit; 
     } 
 } 
 
 // 获取聊天文件路径 
 function getChatMessagesFile($chatId) { 
     global $baseDataDir; 
     $safeChatId = preg_replace('/[^a-zA-Z0-9_\-]/', '', $chatId); 
     if (empty($safeChatId)) { 
         $safeChatId = 'default'; 
     } 
     return $baseDataDir . '/' . $safeChatId . '_messages.txt'; 
 } 
 
 $input = json_decode(file_get_contents('php://input'), true); 
 $action = $input['action'] ?? ''; 
 
 switch ($action) { 
     case 'send': 
         $messageContent = $input['message'] ?? ''; 
         $chatId = $input['chat_id'] ?? 'group'; 
         $messageType = $input['type'] ?? 'text'; 
         $imageData = $input['image_data'] ?? null; 
         $emojiPath = $input['emoji_path'] ?? null; 
         
         sendMessage($chatId, $messageContent, $messageType, $imageData, $emojiPath); 
         break; 
         
     case 'get': 
         $chatId = $input['chat_id'] ?? 'group'; 
         getMessages($chatId, $input['lastId'] ?? '0'); 
         break; 
         
     case 'getGroupMembers': 
         getGroupMembers(); 
         break; 
         
     default: 
         echo json_encode(['success' => false, 'message' => '无效操作']); 
 } 
 
 function sendMessage($chatId, $messageContent, $messageType, $imageData, $emojiPath) { 
     $messagesFile = getChatMessagesFile($chatId); 
     $username = $_SESSION['username']; 
     
     // 验证消息类型和内容 
     if ($messageType === 'text' && empty($messageContent)) { 
         echo json_encode(['success' => false, 'message' => '文本消息不能为空']); 
         return; 
     } 
     
     if ($messageType === 'image' && empty($imageData)) { 
         echo json_encode(['success' => false, 'message' => '图片数据不能为空']); 
         return; 
     } 
     
     if ($messageType === 'emoji' && empty($emojiPath)) { 
         echo json_encode(['success' => false, 'message' => '表情路径不能为空']); 
         return; 
     } 
     
     $timestamp = date('Y-m-d H:i:s'); 
     $messageId = time() . '_' . uniqid(); 
     
     // 如果是图片消息，保存图片文件 
     $imagePath = null; 
     if ($messageType === 'image' && $imageData) { 
         $imageDir = 'uploads/images/'; 
         if (!is_dir($imageDir)) { 
             mkdir($imageDir, 0777, true); 
         } 
         
         $imageName = uniqid() . '.png'; 
         $imagePath = $imageDir . $imageName; 
         
         // 解码base64图片数据 
         $imageData = str_replace('data:image/png;base64,', '', $imageData); 
         $imageData = str_replace(' ', '+', $imageData); 
         $imageBinary = base64_decode($imageData); 
         
         if (file_put_contents($imagePath, $imageBinary) === false) { 
             echo json_encode(['success' => false, 'message' => '图片保存失败']); 
             return; 
         } 
     } 
     
     // 构建消息数据结构 
     $messageData = [ 
         'id' => $messageId, 
         'username' => $username, 
         'message' => $messageContent, 
         'timestamp' => $timestamp, 
         'type' => $messageType, 
         'image_path' => $imagePath, 
         'emoji_path' => $emojiPath, 
         'chat_id' => $chatId 
     ]; 
     
     // 保存消息到文件 
     $messageLine = json_encode($messageData) . "\n"; 
     
     if (file_put_contents($messagesFile, $messageLine, FILE_APPEND) === false) { 
         echo json_encode(['success' => false, 'message' => '消息保存失败']); 
         return; 
     } 
     
     echo json_encode([ 
         'success' => true, 
         'message' => '消息发送成功', 
         'data' => $messageData 
     ]); 
 } 
 
 function getMessages($chatId, $lastKnownId) { 
     $messagesFile = getChatMessagesFile($chatId); 
     $messages = []; 
     
     if (!file_exists($messagesFile)) { 
         echo json_encode(['success' => true, 'messages' => []]); 
         return; 
     } 
     
     $lines = file($messagesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); 
     foreach ($lines as $line) { 
         $msg = json_decode($line, true); 
         if ($msg && strcmp($msg['id'], $lastKnownId) > 0) { 
             $messages[] = $msg; 
         } 
     } 
     
     // 按时间顺序排序 
     usort($messages, function($a, $b) { 
         return strcmp($a['id'], $b['id']); 
     }); 
     
     echo json_encode(['success' => true, 'messages' => $messages]); 
 } 
 
 function getGroupMembers() { 
     global $groupFile; 
     $members = []; 
     
     if (file_exists($groupFile)) { 
         $lines = file($groupFile, FILE_IGNORE_NEW_LINES); 
         foreach ($lines as $line) { 
             if (!empty($line)) { 
                 $parts = explode('|', $line); 
                 if (count($parts) >= 2) { 
                     $members[] = [ 
                         'username' => $parts[0], 
                         'joinTime' => $parts[1] 
                     ]; 
                 } 
             } 
         } 
     } 
     
     echo json_encode(['success' => true, 'members' => $members]); 
 } 
 ?> 