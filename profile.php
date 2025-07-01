<?php
session_start();

// 检查用户是否已登录
if (!isset($_SESSION['username'])) {
    header('Location: login_page.php');
    exit;
}

$username = $_SESSION['username'];
$avatarPath = 'avatars/' . $username . '.jpg';
$hasAvatar = file_exists($avatarPath);

// 处理头像上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $targetDir = "avatars/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    $targetFile = $targetDir . $username . '.jpg';
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
    
    // 检查是否为图片
    $check = getimagesize($_FILES['avatar']['tmp_name']);
    if ($check === false) {
        $error = "文件不是图片。";
        $uploadOk = 0;
    }
    
    // 检查文件大小 (2MB)
    if ($_FILES['avatar']['size'] > 2000000) {
        $error = "图片太大，请选择小于2MB的图片。";
        $uploadOk = 0;
    }
    
    // 允许的格式
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
        $error = "只支持 JPG, JPEG, PNG 格式。";
        $uploadOk = 0;
    }
    
    // 尝试上传
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES['avatar']['tmp_name'], $targetFile)) {
            $success = "头像上传成功！";
            $hasAvatar = true;
        } else {
            $error = "上传过程中出错。";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的资料 - 雨由Talk</title>
    <link rel="icon" href="aukp-icon.png" type="image/png">
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .avatar-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .avatar-preview {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            margin: 0 auto 20px;
            overflow: hidden;
            position: relative;
            background-color: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 60px;
            color: #58AFFF;
        }
        
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .upload-btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #58AFFF;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .upload-btn:hover {
            background-color: #409ae0;
        }
        
        .upload-btn input[type="file"] {
            display: none;
        }
        
        .user-info-section {
            padding: 20px;
            border-top: 1px solid #eee;
        }
        
        .info-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f5f5f5;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
        }
        
        .message {
            padding: 10px;
            margin: 15px 0;
            border-radius: 5px;
            text-align: center;
        }
        
        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <header>
            <img src="Logot.svg" alt="Logo" class="logo-img">
            <h1>我的资料</h1>
            <div class="user-info">
                <div class="avatar-container">
                    <?php if ($hasAvatar): ?>
                        <img src="<?php echo $avatarPath; ?>" alt="用户头像" class="user-avatar">
                    <?php else: ?>
                        <div class="default-avatar"><?php echo substr($username, 0, 1); ?></div>
                    <?php endif; ?>
                </div>
                <span><?php echo htmlspecialchars($username); ?></span>
                <a href="index.php" style="margin-left: 10px; padding: 5px 10px; background: #58AFFF; color: white; border: none; border-radius: 3px; cursor: pointer; text-decoration: none;">返回</a>
            </div>
        </header>
        <div class="main-content">
            <div class="profile-container">
                <?php if (isset($error)): ?>
                    <div class="message error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (isset($success)): ?>
                    <div class="message success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="avatar-section">
                    <div class="avatar-preview">
                        <?php if ($hasAvatar): ?>
                            <img src="<?php echo $avatarPath; ?>" alt="用户头像">
                        <?php else: ?>
                            <?php echo substr($username, 0, 1); ?>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <label class="upload-btn">
                            <img src="upq-icon.svg" alt="上传" style="width: 20px; height: 20px; vertical-align: middle; margin-right: 5px;">
                            更换头像
                            <input type="file" name="avatar" id="avatar" accept="image/*">
                        </label>
                        <button type="submit" style="margin-left: 10px; padding: 10px 20px; background-color: #58AFFF; color: white; border: none; border-radius: 5px; cursor: pointer;">保存</button>
                    </form>
                </div>
                
                <div class="user-info-section">
                    <div class="info-item">
                        <div class="info-label">用户名</div>
                        <div class="info-value"><?php echo htmlspecialchars($username); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">注册时间</div>
                        <div class="info-value"><?php echo date('Y-m-d H:i:s', filemtime('data/users.txt')); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">最近活跃</div>
                        <div class="info-value"><?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>