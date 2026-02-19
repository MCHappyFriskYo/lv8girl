<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$to_user_id = isset($_GET['to']) ? (int)$_GET['to'] : 0;
if (!$to_user_id) {
    header('Location: index.php');
    exit;
}

$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';
$db_pass = 'yourpasswd'; // 请修改为实际密码

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败');
}

$user_id = $_SESSION['user_id'];

// 获取接收者信息
$stmt = $pdo->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
$stmt->execute([$to_user_id]);
$receiver = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$receiver) {
    die('用户不存在');
}

// 处理发送消息
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    if (empty($content)) {
        $error = '消息内容不能为空';
    } else {
        $stmt = $pdo->prepare("INSERT INTO private_messages (from_user_id, to_user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $to_user_id, $content]);
        // 发送成功后重定向到对话页面（如果有的话）或刷新当前页
        // 这里简单刷新页面，实际可跳转到对话详情页 conversation.php?user=...
        header("Location: send_message.php?to=$to_user_id&sent=1");
        exit;
    }
}

// 获取与该用户的最近几条消息（可选，用于预览）
$stmt = $pdo->prepare("
    SELECT * FROM private_messages 
    WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)
    ORDER BY created_at DESC LIMIT 10
");
$stmt->execute([$user_id, $to_user_id, $to_user_id, $user_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$messages = array_reverse($messages); // 按时间正序显示

// 获取当前登录用户信息
$stmt = $pdo->prepare("SELECT username, avatar FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发送私信给 <?php echo htmlspecialchars($receiver['username']); ?> - lv8girl</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #f0f7f0;
            --surface: #ffffff;
            --surface-light: #e8f0e8;
            --border: #d0e0d0;
            --border-light: #e0e8e0;
            --text: #2c3e2c;
            --text-soft: #5f6b5f;
            --text-hint: #8f9f8f;
            --primary: #3d9e4a;
            --primary-light: #6abf6e;
            --accent: #ffb347;
            --accent-dark: #d9a066;
            --gradient: linear-gradient(135deg, #3d9e4a, #5a9cff);
        }
        body.dark-mode {
            --bg: #1a1e1a;
            --surface: #1e261e;
            --surface-light: #2a3a2a;
            --border: #2a3a2a;
            --border-light: #3a4a3a;
            --text: #e0e8e0;
            --text-soft: #b0bcb0;
            --text-hint: #8a958a;
            --primary: #6b8e6b;
            --primary-light: #8aad8a;
            --accent: #ffb347;
            --accent-dark: #d9a066;
            --gradient: linear-gradient(135deg, #6b8e6b, #5a9cff);
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            line-height: 1.6;
            transition: background 0.3s, color 0.3s;
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* 头部 */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }
        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .back-link {
            color: var(--text-soft);
            text-decoration: none;
            padding: 6px 16px;
            border-radius: 30px;
            background: var(--surface-light);
            transition: 0.2s;
        }
        .back-link:hover {
            background: var(--primary);
            color: white;
        }
        .theme-toggle {
            background: var(--surface-light);
            border: none;
            color: var(--text);
            font-size: 1.3rem;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .theme-toggle:hover {
            background: var(--accent);
            color: var(--surface);
        }

        /* 聊天主区域 */
        .chat-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
            background: var(--surface);
            border-left: 1px solid var(--border);
            border-right: 1px solid var(--border);
        }
        /* 聊天对象信息 */
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--surface-light);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--gradient);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.2rem;
        }
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .chat-header-info h3 {
            font-size: 1.2rem;
            color: var(--accent);
            margin-bottom: 4px;
        }
        .chat-header-info p {
            color: var(--text-hint);
            font-size: 0.85rem;
        }

        /* 消息列表区域 */
        .message-list {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .message-item {
            display: flex;
            gap: 10px;
            max-width: 80%;
        }
        .message-item.me {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        .message-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--gradient);
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .message-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .message-bubble {
            background: var(--surface-light);
            padding: 10px 15px;
            border-radius: 18px;
            position: relative;
            word-break: break-word;
        }
        .message-item.me .message-bubble {
            background: var(--primary);
            color: white;
        }
        .message-time {
            font-size: 0.7rem;
            color: var(--text-hint);
            margin-top: 4px;
            text-align: right;
        }
        .message-item.me .message-time {
            color: rgba(255,255,255,0.7);
        }

        /* 底部输入区域 */
        .chat-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--border);
            background: var(--surface);
        }
        .error-message {
            background: #ff6b6b;
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            margin-bottom: 10px;
            font-size: 0.9rem;
            text-align: center;
        }
        .success-message {
            background: var(--primary);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            margin-bottom: 10px;
            font-size: 0.9rem;
            text-align: center;
        }
        .input-form {
            display: flex;
            gap: 10px;
        }
        .input-form textarea {
            flex: 1;
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 12px 18px;
            color: var(--text);
            font-size: 1rem;
            resize: none;
            outline: none;
            transition: border 0.2s;
            min-height: 60px;
        }
        .input-form textarea:focus {
            border-color: var(--accent);
        }
        .send-btn {
            background: var(--gradient);
            border: none;
            border-radius: 40px;
            padding: 0 30px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            white-space: nowrap;
        }
        .send-btn:hover {
            transform: scale(1.02);
        }
    </style>
</head>
<body>
    <!-- 头部 -->
    <div class="header">
        <div class="logo">lv8girl<span>绿坝娘</span></div>
        <div>
            <a href="messages.php" class="back-link">← 返回私信列表</a>
            <div class="theme-toggle" id="themeToggle" style="display: inline-block; margin-left: 10px;">🌓</div>
        </div>
    </div>

    <!-- 聊天主区域 -->
    <div class="chat-container">
        <!-- 聊天对象信息 -->
        <div class="chat-header">
            <div class="avatar">
                <?php if ($receiver['avatar'] && file_exists($receiver['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($receiver['avatar']); ?>" alt="">
                <?php else: ?>
                    <?php echo strtoupper(mb_substr($receiver['username'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="chat-header-info">
                <h3><?php echo htmlspecialchars($receiver['username']); ?></h3>
                <p>UID: <?php echo $receiver['id']; ?></p>
            </div>
        </div>

        <!-- 消息列表 -->
        <div class="message-list" id="messageList">
            <?php if (empty($messages)): ?>
                <div style="text-align: center; color: var(--text-hint); padding: 40px 0;">暂无聊天记录，发送第一条消息吧～</div>
            <?php else: ?>
                <?php foreach ($messages as $msg): 
                    $is_me = $msg['from_user_id'] == $user_id;
                    $sender = $is_me ? $current_user : $receiver;
                ?>
                <div class="message-item <?php echo $is_me ? 'me' : ''; ?>">
                    <div class="message-avatar">
                        <?php if ($sender['avatar'] && file_exists($sender['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($sender['avatar']); ?>" alt="">
                        <?php else: ?>
                            <?php echo strtoupper(mb_substr($sender['username'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="message-content">
                        <div class="message-bubble"><?php echo nl2br(htmlspecialchars($msg['content'])); ?></div>
                        <div class="message-time"><?php echo date('H:i', strtotime($msg['created_at'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 底部输入区域 -->
        <div class="chat-footer">
            <?php if (isset($_GET['sent']) && $_GET['sent'] == 1): ?>
                <div class="success-message">消息已发送</div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" class="input-form">
                <textarea name="content" placeholder="输入私信内容..." required></textarea>
                <button type="submit" class="send-btn">发送</button>
            </form>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            themeToggle.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌓';
        });

        // 自动滚动到底部
        const messageList = document.getElementById('messageList');
        messageList.scrollTop = messageList.scrollHeight;
    </script>
</body>
</html>
