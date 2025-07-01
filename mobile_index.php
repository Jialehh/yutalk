<?php
session_start();

function is_mobile_device() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $mobile_agents = [
        'Mobile', 'Android', 'Silk/', 'Kindle', 'BlackBerry', 'Opera Mini', 'Opera Mobi',
        'iPhone', 'iPad', 'iPod', 'Windows Phone', 'IEMobile', 'webOS'
    ];
    
    foreach ($mobile_agents as $device) {
        if (stripos($user_agent, $device) !== false) {
            return true;
        }
    }
    return false;
}

// 如果是移动设备且不在移动版页面，则重定向
if (is_mobile_device() && basename($_SERVER['PHP_SELF']) !== 'mobile_index.php') {
    header('Location: mobile_index.php');
    exit;
}

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
    <title>雨由Talk</title>
    <link rel="icon" href="aukp-icon.png" type="image/png">
    <link rel="stylesheet" href="mobile.css">
</head>
<body>
    <div class="app-container">
        <header>
            <img src="Logot.svg" alt="Logo" class="logo-img">
            <h1>雨由Talk</h1>
            <div class="user-info">
                <!-- 添加头像显示 -->
                <div class="avatar-container">
                    <?php
                    $avatarPath = 'avatars/' . $username . '.jpg';
                    if (file_exists($avatarPath)) {
                        echo '<img src="' . $avatarPath . '" alt="用户头像" class="header-avatar">';
                    } else {
                        echo '<div class="header-avatar">' . substr($username, 0, 1) . '</div>';
                    }
                    ?>
                </div>
                <span>欢迎, <?php echo htmlspecialchars($username); ?></span>
                <button onclick="logout()" style="margin-left: 10px; padding: 5px 10px; background: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer;">退出</button>
            </div>
        </header>
        <div class="main-content" style="padding-bottom: 0; /* Removed space for bottom-nav */ flex-grow: 1; display: flex; flex-direction: column; margin-left: 0; /* Initially no margin */ transition: margin-left 0.3s ease;">
            <div class="chat-list-panel" style="display: flex; flex-direction: column; height: 100%;">
                <div class="search-add-bar">
                    <div class="search-input-container">
                        <img src="se-icon.svg" alt="Search" class="search-icon">
                        <input type="text" id="searchInput" placeholder="搜索">
                    </div>
                    <button id="addChatButton" class="add-button" title="添加聊天">
                        <img src="add-icon.svg" alt="Add">
                    </button>
                </div>
                <ul id="chatList" class="chat-list" style="flex-grow: 1; overflow-y: auto;">
                    <!-- Chat items will be populated by JS -->
                </ul>
            </div>
            <div class="message-display-area" style="display: none; flex-direction: column; height: 100%;">
                <div class="chat-header-bar" id="chatHeaderBar">
                    <button id="backToChatListButton" class="chat-header-button" title="返回列表" style="margin-right: 10px; background: none; border: none; padding: 5px; cursor: pointer;">
                        <img src="hide-icon.svg" alt="Back" style="width: 20px; height: 20px;"> <!-- Placeholder for a back icon, you might need to add one -->
                    </button>
                    <span id="currentChatName">群聊大厅</span>
                    <div id="groupAnnouncementContainer" style="display: none; margin-left: auto; align-items: center;">
                        <span id="groupAnnouncementText" style="font-size: 0.9em; color: #555; margin-right: 10px;"></span>
                    </div>
                    <button id="chatSettingsButton" class="chat-header-button" title="聊天设置" style="display: none; margin-left: 10px;">
                        <img src="settings-icon.svg" alt="Chat Settings" style="width: 20px; height: 20px;">
                    </button>
                </div>
                <div class="messages" id="messagesContainer" style="flex-grow: 1; overflow-y: auto;">
                   <div id="loading-indicator" style="display: none; text-align: center; padding: 20px;">
                     <div class="spinner"></div>
                     <p>加载消息中...</p>
                 </div>
                 <p class="placeholder-text" style="text-align: center; color: #888; padding: 20px;">
                     没有消息，开始聊天吧！
                </p>
                 </div>

                <div class="message-input-area" id="messageInputArea">
                    <input type="file" id="imageUploadInput" accept="image/*" style="display: none;">
                    <button id="attachImageButton" class="attach-image-btn" title="上传图片">
                        <img src="photo-icon.svg" alt="Upload Image">
                    </button>
                    <button id="emojiButton" class="emoji-btn" title="选择表情">
                        <img src="bia-icon.svg" alt="Emoji">
                    </button>
                    <div id="emojiPicker" class="emoji-picker" style="display: none;">
                        <img src="assets/zei.png" alt="zei emoji" data-emoji-name="zei">
                        <img src="assets/zayu.png" alt="zayu emoji" data-emoji-name="zayu">
                        <img src="assets/hello.png" alt="hello emoji" data-emoji-name="hello">
                    </div>
                    <input type="text" id="messageInput" placeholder="在这里输入">
                    <button id="sendMessageButton">发送</button>
                </div>
            </div>
        </div>
        <button id="toggleNavButton" style="position: fixed; top: 15px; left: 15px; z-index: 1001; background: #f0f0f0; border: 1px solid #ccc; padding: 8px; border-radius: 5px; cursor: pointer; font-size: 18px;">☰</button>
        <nav class="side-nav" id="sideNav" style="position: fixed; top: 0; left: -200px; width: 200px; height: 100%; background-color: #fff; display: flex; flex-direction: column; align-items: center; border-right: 1px solid #eee; padding-top: 60px; box-shadow: 2px 0 5px rgba(0,0,0,0.1); transition: left 0.3s ease;">
            <button id="chatNavButtonMobile" class="nav-button active" title="聊天" style="background: none; border: none; cursor: pointer; display: flex; flex-direction: column; align-items: center; font-size: 0.9em; padding: 15px 0; width: 100%; text-align: center;">
                <img src="chat-icon.svg" alt="Chat" style="width: 24px; height: 24px; margin-bottom: 2px;">
                聊天
            </button>
            <a href="profile.php" id="meNavButtonMobile" class="nav-button" title="我" style="background: none; border: none; cursor: pointer; display: flex; flex-direction: column; align-items: center; font-size: 0.9em; color: inherit; text-decoration: none; padding: 15px 0; width: 100%; text-align: center;">
                <img src="me-icon.svg" alt="Me" style="width: 24px; height: 24px; margin-bottom: 2px;">
                我
            </a>
            <a href="settings.php" id="settingsNavButtonMobile" class="nav-button" title="设置" style="background: none; border: none; cursor: pointer; display: flex; flex-direction: column; align-items: center; font-size: 0.9em; color: inherit; text-decoration: none; padding: 15px 0; width: 100%; text-align: center;">
                <img src="settings-icon.svg" alt="Settings" style="width: 24px; height: 24px; margin-bottom: 2px;">
                设置
            </a>
        </nav>
    </div>

    <script>
        let currentUser = '<?php echo $username; ?>';

        // 退出登录
        function logout() {
            fetch('auth.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'logout'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'login_page.php';
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
    // --- 元素引用 ---
    const messagesContainer = document.getElementById('messagesContainer');
    const messageInput = document.getElementById('messageInput');
    const sendMessageButton = document.getElementById('sendMessageButton');
    const attachImageButton = document.getElementById('attachImageButton');
    const imageUploadInput = document.getElementById('imageUploadInput');
    const emojiButton = document.getElementById('emojiButton');
    const emojiPicker = document.getElementById('emojiPicker');
    // const settingsNavButton = document.getElementById('settingsNavButton'); // Original, now settingsNavButtonMobile

    // const messageDisplayArea = document.querySelector('.message-display-area'); // Now direct child
    // const chatNavButton = document.getElementById('chatNavButton'); // Original, now chatNavButtonMobile
    // const meNavButton = document.getElementById('meNavButton'); // Original, now meNavButtonMobile
    const searchInput = document.getElementById('searchInput');
    const addChatButton = document.getElementById('addChatButton');
    const currentChatNameDisplay = document.getElementById('currentChatName');
    const messageInputArea = document.getElementById('messageInputArea');
    const placeholderText = document.querySelector('.placeholder-text'); 
    const groupAnnouncementContainer = document.getElementById('groupAnnouncementContainer');
    const groupAnnouncementText = document.getElementById('groupAnnouncementText');
    const chatSettingsButton = document.getElementById('chatSettingsButton');

    // New/updated element references for mobile layout
    const chatListPanelEl = document.querySelector('.chat-list-panel');
    const messageDisplayAreaEl = document.querySelector('.message-display-area');
    const backToChatListButtonEl = document.getElementById('backToChatListButton');
    const chatNavButtonMobile = document.getElementById('chatNavButtonMobile');
    const appContainer = document.querySelector('.app-container'); // For potential style adjustments
    const mainContentEl = document.querySelector('.main-content');
    const sideNavEl = document.getElementById('sideNav');
    const toggleNavButtonEl = document.getElementById('toggleNavButton');

    // --- 全局变量 ---
    let lastMessageId = '0'; // For fetching messages from server, initialized as string for comparison
    let currentBubbleStyle = localStorage.getItem('chatBubbleStyle') || 'default';
    let currentChatId = 'group'; // Default to the main group chat
    let nextChatIdSuffix = 1; // For generating new chat IDs client-side if needed

    // Simulate chat data structure similar to ef.js, but we'll primarily use the 'group' chat from PHP
    // We can extend this if we want to support client-side creation of other (non-persistent) chats
    let chats = [
        { id: 'group', type: 'group', name: '群聊大厅', messages: [], announcement: '欢迎来到群聊大厅！', avatarColor: '#FFB300', unread: 0, pinned: false }
        // Other chats could be loaded or created here
    ];

    const emojis = [
        { name: 'zei', path: 'assets/zei.png' }, 
        { name: 'zayu', path: 'assets/zayu.png' },
        { name: 'hello', path: 'assets/hello.png' }
    ];

    // --- 核心函数 ---

    function getRandomColor() {
        const letters = '0123456789ABCDEF';
        let color = '#';
        for (let i = 0; i < 6; i++) {
            color += letters[Math.floor(Math.random() * 16)];
        }
        return color;
    }

    function renderChatList() {
        const chatListElement = document.getElementById('chatList');
        chatListElement.innerHTML = '';
        const searchTerm = searchInput.value.toLowerCase();

        const sortedChats = [...chats]
            .filter(chat => chat.name.toLowerCase().includes(searchTerm))
            .sort((a, b) => (b.pinned ? 1 : 0) - (a.pinned ? 1 : 0));

        // Ensure chatListPanel is visible when rendering chat list, if no chat is selected
        if (currentChatId === null || !chats.find(c => c.id === currentChatId)) {
            showChatList(); 
        }

        sortedChats.forEach(chat => {
            const listItem = document.createElement('li');
            listItem.classList.add('chat-list-item'); // Use class from style.css
            listItem.dataset.chatId = chat.id;

            const avatar = document.createElement('div');
            avatar.classList.add('avatar');
            avatar.style.backgroundColor = chat.avatarColor || getRandomColor();
            avatar.textContent = chat.name.substring(0, 1);

            const nameSpan = document.createElement('span');
            nameSpan.classList.add('chat-name'); // Use class from style.css
            nameSpan.textContent = chat.name;

            if (chat.unread > 0) {
                const unreadBadge = document.createElement('span');
                unreadBadge.classList.add('unread-badge'); 
                unreadBadge.textContent = chat.unread;
                unreadBadge.style.backgroundColor = 'red';
                unreadBadge.style.color = 'white';
                unreadBadge.style.padding = '2px 6px';
                unreadBadge.style.borderRadius = '10px';
                unreadBadge.style.marginLeft = '8px';
                unreadBadge.style.fontSize = '0.8em';
                nameSpan.appendChild(unreadBadge);
            }

            listItem.appendChild(avatar);
            listItem.appendChild(nameSpan);

            if (chat.id === currentChatId) {
                listItem.classList.add('active');
            }

            if (chat.pinned) {
                listItem.classList.add('pinned');
                const pinIcon = document.createElement('span');
                pinIcon.classList.add('pin-icon');
                listItem.appendChild(pinIcon);
            }

            listItem.addEventListener('click', () => selectChat(chat.id));
            // listItem.addEventListener('contextmenu', (e) => showChatListContextMenu(e, chat.id)); // Context menu can be added later
            chatListElement.appendChild(listItem);
        });
        // If no chats, display a message (optional)
        if (sortedChats.length === 0 && chatListElement.children.length === 0) {
            const noChatsMessage = document.createElement('li');
            noChatsMessage.textContent = '没有找到聊天。';
            noChatsMessage.style.textAlign = 'center';
            noChatsMessage.style.padding = '10px';
            noChatsMessage.style.color = '#888';
            chatListElement.appendChild(noChatsMessage);
        }
    }

    function selectChat(chatId) {
        currentChatId = chatId;
        const chat = chats.find(c => c.id === chatId);
        if (chat) {
            chat.unread = 0;
            currentChatNameDisplay.textContent = chat.name;
            messagesContainer.innerHTML = ''; 
            lastMessageId = 0; 
            loadMessages(); 

            if (chat.type === 'group' && groupAnnouncementContainer && groupAnnouncementText) {
                groupAnnouncementContainer.style.display = 'flex';
                groupAnnouncementText.textContent = chat.announcement || '暂无公告';
            } else if (groupAnnouncementContainer) {
                groupAnnouncementContainer.style.display = 'none';
            }
            if (chatSettingsButton) chatSettingsButton.style.display = chat.type === 'group' ? 'flex' : 'none';
            if (placeholderText) placeholderText.style.display = 'none';
            messageInputArea.style.display = 'flex';

            // Switch views for mobile
            showChatMessages();

        } else {
            currentChatNameDisplay.textContent = '选择一个聊天';
            if (groupAnnouncementContainer) groupAnnouncementContainer.style.display = 'none';
            if (chatSettingsButton) chatSettingsButton.style.display = 'none';
            if (placeholderText) placeholderText.style.display = 'block';
            messageInputArea.style.display = 'none';
            showChatList(); // Show list if chat is not found or deselected
        }
        renderChatList(); 
        messageInput.focus();
        if (emojiPicker) emojiPicker.style.display = 'none';
    }

    // --- View Switching Functions for Mobile ---
    function showChatList() {
        if (chatListPanelEl) chatListPanelEl.style.display = 'flex'; // or 'block' depending on its CSS
        if (messageDisplayAreaEl) messageDisplayAreaEl.style.display = 'none';
        if (backToChatListButtonEl) backToChatListButtonEl.style.display = 'none';
        // Update active state for bottom nav
        if (chatNavButtonMobile) chatNavButtonMobile.classList.add('active');

        if (mainContentEl && sideNavEl && sideNavEl.classList.contains('open')) mainContentEl.style.marginLeft = '200px'; else if (mainContentEl) mainContentEl.style.marginLeft = '0';
        if (mainContentEl) mainContentEl.style.marginLeft = '0'; // Adjust content margin when nav is hidden or list is shown
    }

    function showChatMessages() {
        if (chatListPanelEl) chatListPanelEl.style.display = 'none';
        if (messageDisplayAreaEl) messageDisplayAreaEl.style.display = 'flex'; // or 'block'
        if (backToChatListButtonEl) backToChatListButtonEl.style.display = 'flex'; // Show back button
        // Update active state for bottom nav (chat is still the active context)
        if (chatNavButtonMobile) chatNavButtonMobile.classList.add('active');

        if (mainContentEl && sideNavEl && sideNavEl.classList.contains('open')) mainContentEl.style.marginLeft = '200px'; else if (mainContentEl) mainContentEl.style.marginLeft = '0';
        if (mainContentEl) mainContentEl.style.marginLeft = '0'; // Adjust content margin when nav is hidden or list is shown
    }

    // --- Event Listeners for new mobile navigation ---
    if (backToChatListButtonEl) {
        backToChatListButtonEl.addEventListener('click', () => {
            showChatList();
            currentChatId = null; // Deselect chat when going back to list
            currentChatNameDisplay.textContent = '群聊大厅'; // Reset header
            renderChatList(); // To remove active state from chat item
        });
    }

    if (chatNavButtonMobile) {
        chatNavButtonMobile.addEventListener('click', () => {
            showChatList();
            // Potentially deselect current chat or keep it to allow quick return
            // For now, just shows the list. If a chat was active, selectChat would take over if an item is clicked.
        });
    }


    
    // --- Event Listener for Side Navigation Toggle ---
    if (toggleNavButtonEl && sideNavEl) {
        toggleNavButtonEl.addEventListener('click', () => {
            sideNavEl.classList.toggle('open');
            if (sideNavEl.classList.contains('open')) {
                sideNavEl.style.left = '0';
                if (mainContentEl && messageDisplayAreaEl.style.display !== 'none') {
                     mainContentEl.style.marginLeft = '200px'; // Only adjust if message area is visible
                } else if (mainContentEl) {
                    mainContentEl.style.marginLeft = '0'; // Keep margin 0 if chat list is visible
                }
                toggleNavButtonEl.style.left = '215px'; // Move button with nav
            } else {
                sideNavEl.style.left = '-200px';
                if (mainContentEl) mainContentEl.style.marginLeft = '0';
                toggleNavButtonEl.style.left = '15px'; // Reset button position
            }
        });
    }

    // Initial setup: Show chat list by default on mobile
    showChatList();
    if (mainContentEl) mainContentEl.style.transition = 'margin-left 0.3s ease'; // Add transition for content margin;
    renderChatList(); // Initial render of chat list
    selectChat('group'); // Automatically select the group chat or the first chat

    // --- Existing Core Functions (sendMessage, getAiResponse, etc.) ---
    // (Make sure these functions correctly reference elements if their context changes)
    async function sendMessage() {
        const text = messageInput.value.trim();
        if (text === '' || !currentChatId) return;

        const chat = chats.find(c => c.id === currentChatId);
        if (!chat) return;

        // AI Interaction: Check if message starts with @AI (case-insensitive) in a group chat
        if (chat.type === 'group' && text.toLowerCase().startsWith('@ai ')) {
            const userMessageToAI = text.substring(4); // Remove "@AI "
            // Display user's @AI message immediately
            displayMessage(currentUser, text, new Date().toISOString(), 'sent'); 
            messageInput.value = '';
            messageInput.focus();
            await getAiResponse(userMessageToAI, chat); 
        } else {
            // Regular message to server (messages.php)
            sendMessageToServer(text);
        }
    }

    async function getAiResponse(userMessage, chatContext) {
        const apiKey = 'sk-ecozvmqvpaemrvsvwskgzbbluxnotuklyddgeawbikglhmdh'; // IMPORTANT: Secure this key properly in a real application
        const apiUrl = 'https://api.siliconflow.cn/v1/chat/completions';

        const requestBody = {
            model: "Qwen/Qwen2.5-VL-72B-Instruct",
            messages: [{ role: "user", content: userMessage }],
            stream: false,
        };

        const options = {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${apiKey}`,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestBody)
        };

        try {
            const response = await fetch(apiUrl, options);
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({ message: response.statusText }));
                displayMessage('System', `抱歉，AI 服务出错: ${errorData.error?.message || errorData.message || '未知错误'}`, new Date().toISOString(), 'system');
                return;
            }
            const data = await response.json();
            
            if (data.choices && data.choices.length > 0 && data.choices[0].message && data.choices[0].message.content) {
                const aiReply = data.choices[0].message.content;
                displayMessage('AI', aiReply, new Date().toISOString(), 'received-ai-message'); 
            } else {
                displayMessage('System', '抱歉，AI 返回了无效的回复。', new Date().toISOString(), 'system');
            }
        } catch (error) {
            console.error("AI API Error:", error);
            displayMessage('System', `请求AI失败: ${error.message}`, new Date().toISOString(), 'system');
        }
    }

    // 发送消息到服务器 
    function sendMessageToServer(messageContent, type = 'text', contentData = null) { 
        if (!messageContent.trim() && !contentData) return; 
        
        let messageData = { 
            action: 'send', 
            message: messageContent, 
            chat_id: currentChatId, 
            type: type 
        }; 
        
        // 添加特定类型的数据 
        if (type === 'image') { 
            messageData.image_data = contentData; // 直接传递base64数据 
        } else if (type === 'emoji') { 
            messageData.emoji_path = contentData; 
        } 
        
        fetch('messages.php', { 
            method: 'POST', 
            headers: { 
                'Content-Type': 'application/json', 
            }, 
            body: JSON.stringify(messageData) 
        }) 
        .then(response => { 
            if (!response.ok) { 
                throw new Error(`服务器响应错误: ${response.status}`); 
            } 
            return response.json(); 
        }) 
        .then(data => { 
            if (data.success && data.data) { 
                messageInput.value = ''; 
                // 显示发送的消息 
                displayMessage( 
                    data.data.username, 
                    data.data.message, 
                    data.data.timestamp, 
                    data.data.username === currentUser ? 'sent' : 'received', 
                    data.data.image_path, 
                    data.data.emoji_path 
                ); 
                
                // 更新最后消息ID 
                if (data.data.id && data.data.id > lastMessageId) { 
                    lastMessageId = data.data.id; 
                } 
            } else { 
                displaySystemMessage(data.message || '发送失败'); 
            } 
        }) 
        .catch(error => { 
            console.error('发送消息错误:', error); 
            displaySystemMessage(`发送失败: ${error.message}`); 
        }); 
    } 

    // 图片上传处理 
    imageUploadInput.addEventListener('change', (event) => { 
        const file = event.target.files[0]; 
        if (!file) return; 
        
        // 验证文件类型 
        if (!file.type.match('image.*')) { 
            displaySystemMessage('请选择有效的图片文件'); 
            return; 
        } 
        
        const reader = new FileReader(); 
        reader.onload = (e) => { 
            // 直接发送base64编码的图片数据 
            sendMessageToServer('[图片]', 'image', e.target.result); 
        }; 
        reader.readAsDataURL(file); 
        
        imageUploadInput.value = ''; 
    }); 

    // 表情发送处理 
    function sendEmojiAsMessage(emojiPath) { 
        sendMessageToServer(`[表情]`, 'emoji', emojiPath); 
    } 

    // 显示系统消息 
    function displaySystemMessage(text) { 
        const timestamp = new Date().toISOString(); 
        displayMessage('System', text, timestamp, 'system'); 
    }

    // 加载消息 (Adjusted for currentChatId)
    function loadMessages() {
    if (!currentChatId) return;
    
    fetch('messages.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'get',
            lastId: lastMessageId, 
            chat_id: currentChatId
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Received messages:', data); // 调试日志
        
        if (data.success && data.messages) {
            const newMessages = data.messages;
            
            if (newMessages.length > 0) {
                // 更新最后消息ID为最新消息的ID
                lastMessageId = newMessages[newMessages.length - 1].id;
                
                newMessages.forEach(msg => {
                    // 确定消息类型
                    let messageType = 'received';
                    if (msg.username === currentUser) messageType = 'sent';
                    if (msg.username === 'AI') messageType = 'received-ai-message';
                    if (msg.username === 'System') messageType = 'system';
                    
                    // 显示消息
                    displayMessage(
                        msg.username,
                        msg.message,
                        msg.timestamp,
                        messageType,
                        msg.image_path,
                        msg.emoji_path
                    );
                });
                
                // 隐藏占位文本
                if (placeholderText) placeholderText.style.display = 'none';
                
                // 滚动到底部
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            } else if (messagesContainer.children.length === 0 && placeholderText) {
                // 没有消息时显示占位文本
                placeholderText.style.display = 'block';
            }
        } else {
            console.error('Failed to load messages:', data.message);
            displaySystemMessage(`加载消息失败: ${data.message || '未知错误'}`);
        }
    })
    .catch(error => {
        console.error('加载消息错误:', error);
        displaySystemMessage(`加载消息失败: ${error.message}`);
    });
}

// 显示消息 (修复版本)
function displayMessage(sender, text, timestamp, type, imagePath = null, emojiPath = null) {
    const messageDiv = document.createElement('div');
    messageDiv.classList.add('message', type);
    
    // 应用气泡样式
    if (currentBubbleStyle !== 'default' && type !== 'system') {
        messageDiv.classList.add(`bubble-${currentBubbleStyle}`);
    }
    
    // 消息头部 (发送者和时间)
    const messageHeader = document.createElement('div');
    messageHeader.classList.add('message-header');
    
    const senderSpan = document.createElement('span');
    senderSpan.classList.add('sender-name');
    senderSpan.textContent = sender;
    
    const timeSpan = document.createElement('span');
    timeSpan.classList.add('message-time');
    timeSpan.textContent = new Date(timestamp).toLocaleTimeString([], { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
    
    messageHeader.appendChild(senderSpan);
    messageHeader.appendChild(timeSpan);
    messageDiv.appendChild(messageHeader);
    
    // 消息内容
    const contentDiv = document.createElement('div');
    contentDiv.classList.add('message-content');
    
    if (imagePath) {
        // 图片消息
        const imgElement = document.createElement('img');
        imgElement.src = imagePath;
        imgElement.classList.add('message-image');
        contentDiv.appendChild(imgElement);
        
        if (text && text !== '[图片]') {
            const textSpan = document.createElement('span');
            textSpan.classList.add('message-text');
            textSpan.textContent = text;
            contentDiv.appendChild(textSpan);
        }
    } else if (emojiPath) {
        // 表情消息
        const emojiImg = document.createElement('img');
        emojiImg.src = emojiPath;
        emojiImg.classList.add('message-emoji');
        contentDiv.appendChild(emojiImg);
    } else {
        // 文本消息
        const textSpan = document.createElement('span');
        textSpan.classList.add('message-text');
        textSpan.textContent = text;
        contentDiv.appendChild(textSpan);
    }
    
    messageDiv.appendChild(contentDiv);
    messagesContainer.appendChild(messageDiv);
    
    // 隐藏占位文本
    if (placeholderText) placeholderText.style.display = 'none';
    
    // 滚动到底部
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

    // 发送表情包消息 (modified to send type and path)
    function sendEmojiAsMessage(emoji) { // emoji is an object {name, path}
        // Display locally first for responsiveness (optional, but good UX)
        // displayMessage(currentUser, '', new Date().toISOString(), 'sent', null, emoji.path, emoji.path); 
        sendMessageToServer(`[表情:${emoji.name}]`, 'emoji', emoji.path);
    }

    // 填充表情包选择器
    function populateEmojiPicker() {
        if (!emojiPicker) return;
        emojiPicker.innerHTML = '';
        emojis.forEach(emoji => {
            const img = document.createElement('img');
            img.src = emoji.path;
            img.alt = emoji.name;
            img.title = emoji.name;
            img.addEventListener('click', () => {
                sendEmojiAsMessage(emoji);
                emojiPicker.style.display = 'none';
            });
            emojiPicker.appendChild(img);
        });
    }

    // --- 事件监听器 ---
    // 发送消息按钮
    if (sendMessageButton) {
        sendMessageButton.addEventListener('click', sendMessage); // sendMessage now handles AI and regular
    }

    // 回车发送消息
    if (messageInput) {
        messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
    }

    // 图片上传 (modified to send type and path/base64)
    if (attachImageButton && imageUploadInput) {
        attachImageButton.addEventListener('click', () => {
            imageUploadInput.click();
        });

        imageUploadInput.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const base64Image = e.target.result;
                    // Display locally first (optional)
                    // displayMessage(currentUser, '', new Date().toISOString(), 'sent', base64Image);
                    sendMessageToServer(`[图片]`, 'image', base64Image); // Send type and base64 data
                };
                reader.readAsDataURL(file);
            }
            imageUploadInput.value = '';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', renderChatList);
    }

    if (addChatButton) {
        addChatButton.addEventListener('click', () => {
            const chatName = prompt('输入新聊天的名称:');
            if (chatName && chatName.trim() !== '') {
                const newChatId = `client_chat_${nextChatIdSuffix++}`;
                const isGroupChat = confirm('这是一个群聊吗？');
                const newChat = {
                    id: newChatId,
                    type: isGroupChat ? 'group' : 'direct',
                    name: chatName.trim(),
                    messages: [], 
                    announcement: isGroupChat ? '欢迎来到本群！' : undefined,
                    avatarColor: getRandomColor(),
                    unread: 0,
                    pinned: false
                };
                chats.unshift(newChat); 
                // Note: This new chat is client-side only. For persistence, it would need to be saved to the server.
                // For now, selecting it will show an empty message list as loadMessages() won't find server messages for it.
                renderChatList();
                selectChat(newChatId);
            }
        });
    }

    // 表情包选择器
    if (emojiButton && emojiPicker) {
        populateEmojiPicker();

        emojiButton.addEventListener('click', (event) => {
            event.stopPropagation();
            if (emojiPicker.style.display === 'none' || !emojiPicker.style.display) {
                emojiPicker.style.display = 'flex';
            } else {
                emojiPicker.style.display = 'none';
            }
        });

        // 点击其他地方隐藏表情包选择器
        document.addEventListener('click', (event) => {
            if (!emojiButton.contains(event.target) && !emojiPicker.contains(event.target)) {
                emojiPicker.style.display = 'none';
            }
        });
    }

    // 导航按钮
    if (chatNavButton) {
        chatNavButton.addEventListener('click', () => {
            chatNavButton.classList.add('active');
            if (meNavButton) meNavButton.classList.remove('active');
            if (settingsNavButton) settingsNavButton.classList.remove('active');
    
            
            // 显示聊天界面
            document.querySelector('.chat-list-panel').style.display = 'flex';
            document.querySelector('.message-display-area').style.display = 'flex';
            document.querySelector('.message-input-area').style.display = 'flex';
        });
    }

    if (meNavButton) {
        meNavButton.addEventListener('click', () => {
            meNavButton.classList.add('active');
            if (chatNavButton) chatNavButton.classList.remove('active');
            if (settingsNavButton) settingsNavButton.classList.remove('active');
    
            
            // 显示个人信息页面
            messagesContainer.innerHTML = '<p class="placeholder-text">这里是"我"的页面内容。</p>';
            document.querySelector('.message-input-area').style.display = 'none';
        });
    }

    if (settingsNavButton) {
        settingsNavButton.addEventListener('click', () => {
            settingsNavButton.classList.add('active');
            if (chatNavButton) chatNavButton.classList.remove('active');
            if (meNavButton) meNavButton.classList.remove('active');
        });
    }


 


    // 加载保存的背景图片
    function loadBackgroundImage() {
        const savedImage = localStorage.getItem('chatBackgroundImage');
        if (savedImage && messageDisplayArea) {
            messageDisplayArea.style.backgroundImage = `url(${savedImage})`;
        }
    }

    // --- 初始化 ---
    loadBackgroundImage();
    renderChatList(); // Render the initial chat list (which includes the default 'group' chat)
    selectChat(currentChatId); // Select the default chat ('group') and load its messages
    
    // 定期加载新消息 for the currently selected chat
    setInterval(loadMessages, 3000); 

    // 确保聊天界面默认激活 (selectChat handles showing the correct panel)
    // if (chatNavButton) {
    //     chatNavButton.click(); // This might be redundant if selectChat is called
    // }
  });

    // Helper to display message (adapting existing displayMessage or creating new)
    function displayMessage(sender, text, timestamp, type, imagePath = null, emojiPath = null, pureEmojiContent = null) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message');

        let senderTypeClass = type; 
        messageDiv.classList.add(senderTypeClass);

        // Determine if this message is primarily an emoji to adjust styling
        const isPureEmojiMessage = type === 'emoji' && emojiPath && (!text || text.startsWith('[表情'));

        if (currentBubbleStyle !== 'default' && senderTypeClass !== 'system' && !isPureEmojiMessage) {
            applyCurrentBubbleStyle(messageDiv);
        }

        const messageHeader = document.createElement('div');
        messageHeader.classList.add('message-header');
        const senderSpan = document.createElement('span');
        senderSpan.classList.add('sender-name');
        senderSpan.textContent = sender;
        const timeSpan = document.createElement('span');
        timeSpan.classList.add('message-time');
        timeSpan.textContent = new Date(timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        messageHeader.appendChild(senderSpan);
        messageHeader.appendChild(timeSpan);
        
        if (senderTypeClass !== 'system' && !isPureEmojiMessage) { 
            messageDiv.appendChild(messageHeader);
        }

        if (isPureEmojiMessage) { 
            const emojiImgElement = document.createElement('img');
            emojiImgElement.src = emojiPath; // Use emojiPath for pure emoji display
            emojiImgElement.classList.add('message-pure-emoji');
            if (sender === currentUser) {
                messageDiv.style.alignSelf = 'flex-end';
            } else {
                messageDiv.style.alignSelf = 'flex-start';
            }
            messageDiv.appendChild(emojiImgElement);
            messageDiv.style.background = 'none';
            messageDiv.style.padding = '0';
            messageDiv.style.borderRadius = '0';
            messageDiv.classList.add('contains-pure-emoji');
        } else if (imagePath) {
            const imgElement = document.createElement('img');
            imgElement.src = imagePath;
            imgElement.classList.add('message-image');
            messageDiv.appendChild(imgElement);
            if (text && text !== '[图片]') { 
                const textSpan = document.createElement('span');
                textSpan.classList.add('message-image-caption');
                textSpan.textContent = text;
                messageDiv.appendChild(textSpan);
            }
        } else if (emojiPath) {
            const emojiImgElement = document.createElement('img');
            emojiImgElement.src = emojiPath;
            emojiImgElement.classList.add('message-emoji-img'); 
            messageDiv.appendChild(emojiImgElement);
             if (text && text !== `[表情:${emojiPath.split('/').pop().split('.')[0]}]`) { // Display text if it's not just the placeholder
                const textSpan = document.createElement('span');
                textSpan.classList.add('message-text'); // Or a specific class for emoji captions
                textSpan.textContent = text;
                messageDiv.appendChild(textSpan);
            }
        } else if (text) {
            const textSpan = document.createElement('span');
            textSpan.classList.add('message-text');
            textSpan.textContent = text;
            messageDiv.appendChild(textSpan);
        }
        
        messagesContainer.appendChild(messageDiv);
        if (placeholderText) placeholderText.style.display = 'none';
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function applyCurrentBubbleStyle(messageDiv) {
        messageDiv.classList.remove('bubble-cute', 'bubble-premium');
        if (currentBubbleStyle === 'cute') {
            messageDiv.classList.add('bubble-cute');
        } else if (currentBubbleStyle === 'premium') {
            messageDiv.classList.add('bubble-premium');
        }
    }

    function scrollToBottom() {
    // 使用平滑滚动
    messagesContainer.scrollTo({
        top: messagesContainer.scrollHeight,
        behavior: 'smooth'
    });
    
    // 设置标志防止重复滚动
    messagesContainer.dataset.lastScroll = messagesContainer.scrollHeight;
}

    // 在添加消息后调用
    function displayMessage(...args) {
    // ... 现有代码 ...
    
    // 只有用户靠近底部时才自动滚动
       const isNearBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop <= messagesContainer.clientHeight + 200;
    
    if (isNearBottom) {
        scrollToBottom();
    }
}

function displayMessage(sender, text, timestamp, type, imagePath = null, emojiPath = null) {
    // 创建消息容器
    const messageContainer = document.createElement('div');
    messageContainer.classList.add('message-container', type);
    
    // 创建头像元素
    const avatarElement = document.createElement('div');
    avatarElement.classList.add('message-avatar');
    
    // 检查是否有该用户的头像
    const avatarImgPath = `avatars/${sender}.jpg`;
    const avatarImg = document.createElement('img');
    avatarImg.src = avatarImgPath;
    avatarImg.style.display = 'none'; // 先隐藏，如果加载成功再显示
    
    // 头像加载失败时显示首字母
    avatarImg.onerror = function() {
        avatarImg.style.display = 'none';
        avatarElement.textContent = sender ? sender.charAt(0).toUpperCase() : '?';
    };
    
    // 头像加载成功时显示图片
    avatarImg.onload = function() {
        avatarImg.style.display = 'block';
        avatarElement.textContent = '';
    };
    
    avatarElement.appendChild(avatarImg);
    avatarElement.appendChild(document.createTextNode(sender ? sender.charAt(0).toUpperCase() : '?'));
    
    // 创建消息内容容器
    const messageContentContainer = document.createElement('div');
    messageContentContainer.classList.add('message-content-container');
    
    // 创建消息气泡
    const messageDiv = document.createElement('div');
    messageDiv.classList.add('message', type);
    
    // ... 原有的消息内容创建代码（头部、内容等）...
    
    // 将气泡添加到消息内容容器
    messageContentContainer.appendChild(messageDiv);
    
    // 将头像和消息内容添加到消息容器
    messageContainer.appendChild(avatarElement);
    messageContainer.appendChild(messageContentContainer);
    
    // 添加到消息容器
    messagesContainer.appendChild(messageContainer);
    
    // 隐藏占位文本
    if (placeholderText) placeholderText.style.display = 'none';
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// 在滚动事件中更新标志
    messagesContainer.addEventListener('scroll', () => {
         const isNearBottom = messagesContainer.scrollHeight - messagesContainer.scrollTop <= messagesContainer.clientHeight + 200;
         messagesContainer.dataset.shouldScroll = isNearBottom ? 'true' : 'false';
});

    </script>

</body>
</html>