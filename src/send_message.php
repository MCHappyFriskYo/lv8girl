<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

// 获取当前选中的对话用户ID，默认为0（表示未选择）
$selected_user_id = isset($_GET['to']) ? (int)$_GET['to'] : 0;

// 获取所有有过私信交流的用户（会话列表）
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        CASE 
            WHEN from_user_id = ? THEN to_user_id
            ELSE from_user_id
        END AS other_user_id
    FROM private_messages
    WHERE from_user_id = ? OR to_user_id = ?
    ORDER BY created_at DESC
");
$stmt->execute([$user_id, $user_id, $user_id]);
$other_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 构建会话列表数据
$conversations = [];
foreach ($other_ids as $other_id) {
    // 获取对方用户信息
    $stmt = $pdo->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
    $stmt->execute([$other_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) continue;

    // 获取最后一条消息
    $stmt = $pdo->prepare("
        SELECT * FROM private_messages 
        WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$user_id, $other_id, $other_id, $user_id]);
    $last_msg = $stmt->fetch(PDO::FETCH_ASSOC);

    // 获取未读消息数（对方发来的未读）
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM private_messages 
        WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0
    ");
    $stmt->execute([$other_id, $user_id]);
    $unread = $stmt->fetchColumn();

    $conversations[] = [
        'user' => $user,
        'last_msg' => $last_msg,
        'unread' => $unread
    ];
}

// 按最后消息时间排序
usort($conversations, function($a, $b) {
    return strtotime($b['last_msg']['created_at']) - strtotime($a['last_msg']['created_at']);
});

// 如果未指定选中用户，且存在会话，则自动选择第一个
if ($selected_user_id == 0 && !empty($conversations)) {
    $selected_user_id = $conversations[0]['user']['id'];
    // 重定向到带to参数的URL，避免刷新后丢失
    header("Location: send_message.php?to=$selected_user_id");
    exit;
}

// 获取选中用户的详细信息
$receiver = null;
$messages = [];
if ($selected_user_id > 0) {
    $stmt = $pdo->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
    $stmt->execute([$selected_user_id]);
    $receiver = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$receiver) {
        // 如果用户不存在，重定向回无参数的页面
        header('Location: send_message.php');
        exit;
    }

    // 获取与选中用户的聊天记录
    $stmt = $pdo->prepare("
        SELECT * FROM private_messages 
        WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)
        ORDER BY created_at ASC
    ");
    $stmt->execute([$user_id, $selected_user_id, $selected_user_id, $user_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 将对方发来的未读消息标记为已读
    $stmt = $pdo->prepare("UPDATE private_messages SET is_read = 1 WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0");
    $stmt->execute([$selected_user_id, $user_id]);
}

// 处理发送新消息
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_user_id > 0) {
    $content = trim($_POST['content'] ?? '');
    if (empty($content)) {
        $error = '消息内容不能为空';
    } else {
        $stmt = $pdo->prepare("INSERT INTO private_messages (from_user_id, to_user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $selected_user_id, $content]);
        // 刷新当前页面以显示新消息
        header("Location: send_message.php?to=$selected_user_id&sent=1");
        exit;
    }
}

// 获取当前登录用户信息（用于右侧头像）
$stmt = $pdo->prepare("SELECT username, avatar FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>私信 · lv8girl</title>
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
            display: inline-block;
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
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
            margin-left: 10px;
        }
        .theme-toggle:hover {
            background: var(--accent);
            color: var(--surface);
        }

        /* 两栏布局 */
        .main-container {
            flex: 1;
            display: flex;
            overflow: hidden;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            background: var(--surface);
            border-left: 1px solid var(--border);
            border-right: 1px solid var(--border);
        }
        /* 左侧会话列表 */
        .conversation-list {
            width: 280px;
            border-right: 1px solid var(--border);
            overflow-y: auto;
            background: var(--surface);
        }
        .conversation-item {
            display: flex;
            align-items: center;
            padding: 15px 15px;
            border-bottom: 1px solid var(--border-light);
            text-decoration: none;
            color: var(--text);
            transition: 0.2s;
        }
        .conversation-item:hover {
            background: var(--surface-light);
        }
        .conversation-item.active {
            background: var(--surface-light);
            border-left: 4px solid var(--accent);
        }
        .conversation-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--gradient);
            margin-right: 12px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            flex-shrink: 0;
        }
        .conversation-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        .conversation-name {
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }
        .conversation-lastmsg {
            color: var(--text-soft);
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .conversation-time {
            color: var(--text-hint);
            font-size: 0.7rem;
            margin-left: 5px;
        }
        .unread-badge {
            background: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            margin-left: 5px;
        }
        .empty-conversations {
            padding: 30px;
            text-align: center;
            color: var(--text-hint);
        }

        /* 右侧聊天区域 */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--surface);
        }
        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border);
            background: var(--surface-light);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .chat-header .avatar {
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
        .chat-header .avatar img {
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
        .no-receiver {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-hint);
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <!-- 头部 -->
    <div class="header">
        <div class="logo">lv8girl<span>绿坝娘</span></div>
        <div>
            <a href="index.php" class="back-link">返回首页</a>
            <div class="theme-toggle" id="themeToggle" style="display: inline-block;">🌓</div>
        </div>
    </div>

    <!-- 两栏主体 -->
    <div class="main-container">
        <!-- 左侧会话列表 -->
        <div class="conversation-list">
            <?php if (empty($conversations)): ?>
                <div class="empty-conversations">暂无私信对话</div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): 
                    $other = $conv['user'];
                    $last = $conv['last_msg'];
                    $is_from_me = $last['from_user_id'] == $user_id;
                    $active = ($selected_user_id == $other['id']) ? 'active' : '';
                ?>
                <a href="send_message.php?to=<?php echo $other['id']; ?>" class="conversation-item <?php echo $active; ?>">
                    <div class="conversation-avatar">
                        <?php if ($other['avatar'] && file_exists($other['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($other['avatar']); ?>" alt="">
                        <?php else: ?>
                            <?php echo strtoupper(mb_substr($other['username'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="conversation-info">
                        <div class="conversation-name">
                            <?php echo htmlspecialchars($other['username']); ?>
                            <span class="conversation-time"><?php echo date('m-d', strtotime($last['created_at'])); ?></span>
                        </div>
                        <div class="conversation-lastmsg">
                            <?php if ($is_from_me): ?>我: <?php endif; ?>
                            <?php echo htmlspecialchars(mb_substr($last['content'], 0, 30)) . (mb_strlen($last['content']) > 30 ? '…' : ''); ?>
                        </div>
                    </div>
                    <?php if ($conv['unread'] > 0): ?>
                        <div class="unread-badge"><?php echo $conv['unread']; ?></div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 右侧聊天区域 -->
        <div class="chat-area">
            <?php if ($selected_user_id > 0 && $receiver): ?>
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
            <?php else: ?>
                <!-- 未选择用户或无用户 -->
                <div class="no-receiver">
                    <?php if (empty($conversations)): ?>
                        暂无私信对话，快去给感兴趣的用户发送私信吧～
                    <?php else: ?>
                        请从左侧选择一个对话
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
        if (messageList) {
            messageList.scrollTop = messageList.scrollHeight;
        }
    </script>
</body>
</html>
