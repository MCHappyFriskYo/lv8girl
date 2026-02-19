<?php
session_start();

// 检查登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 数据库配置
$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';
$db_pass = 'yourpasswd';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败');
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

// 获取所有对话对方（发送者和接收者中涉及的用户）
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        CASE 
            WHEN from_user_id = ? THEN to_user_id
            ELSE from_user_id
        END AS other_user_id
    FROM private_messages
    WHERE from_user_id = ? OR to_user_id = ?
");
$stmt->execute([$user_id, $user_id, $user_id]);
$other_users = $stmt->fetchAll(PDO::FETCH_COLUMN);

// 对每个对方，获取最新一条消息和未读数
$conversations = [];
foreach ($other_users as $other_id) {
    // 获取对方信息
    $stmt = $pdo->prepare("SELECT id, username, avatar FROM users WHERE id = ?");
    $stmt->execute([$other_id]);
    $other = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$other) continue;

    // 获取最新一条消息
    $stmt = $pdo->prepare("
        SELECT * FROM private_messages 
        WHERE (from_user_id = ? AND to_user_id = ?) OR (from_user_id = ? AND to_user_id = ?)
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$user_id, $other_id, $other_id, $user_id]);
    $last_msg = $stmt->fetch(PDO::FETCH_ASSOC);

    // 获取未读消息数（来自对方且未读）
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM private_messages 
        WHERE from_user_id = ? AND to_user_id = ? AND is_read = 0
    ");
    $stmt->execute([$other_id, $user_id]);
    $unread = $stmt->fetchColumn();

    $conversations[] = [
        'user' => $other,
        'last_msg' => $last_msg,
        'unread' => $unread
    ];
}

// 按最新消息时间排序
usort($conversations, function($a, $b) {
    return strtotime($b['last_msg']['created_at']) - strtotime($a['last_msg']['created_at']);
});
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>私信列表 - lv8girl</title>
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
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        /* 头部导航 */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 20px;
        }
        .logo {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logo span {
            font-size: 0.9rem;
            background: var(--accent);
            color: var(--surface);
            padding: 4px 12px;
            border-radius: 30px;
            margin-left: 10px;
            -webkit-text-fill-color: var(--surface);
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .nav-right a {
            color: var(--text-soft);
            text-decoration: none;
            padding: 6px 16px;
            border-radius: 30px;
            background: var(--surface-light);
            transition: 0.2s;
        }
        .nav-right a:hover {
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

        /* 页面标题 */
        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 20px;
        }

        /* 对话列表 */
        .conversation-list {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        .conversation-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-light);
            text-decoration: none;
            color: var(--text);
            transition: 0.2s;
        }
        .conversation-item:hover {
            background: var(--surface-light);
        }
        .conversation-item:last-child {
            border-bottom: none;
        }
        .avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient);
            margin-right: 15px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            flex-shrink: 0;
        }
        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .conversation-info {
            flex: 1;
            min-width: 0;
        }
        .username {
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 4px;
        }
        .last-msg {
            color: var(--text-soft);
            font-size: 0.9rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 300px;
        }
        .time {
            color: var(--text-hint);
            font-size: 0.8rem;
            margin-left: 10px;
            white-space: nowrap;
        }
        .unread-badge {
            background: #ff6b6b;
            color: white;
            border-radius: 50%;
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            margin-left: 10px;
            flex-shrink: 0;
        }
        .empty-message {
            padding: 40px;
            text-align: center;
            color: var(--text-hint);
        }

        /* 页脚 */
        .footer {
            margin-top: 40px;
            padding: 20px 0;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--text-hint);
            font-size: 0.9rem;
        }
        .footer a {
            color: var(--text-hint);
            text-decoration: none;
            margin: 0 10px;
        }
        .footer a:hover {
            color: var(--accent);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 头部导航 -->
        <div class="header">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div class="nav-right">
                <a href="index.php">首页</a>
                <div class="theme-toggle" id="themeToggle">🌓</div>
            </div>
        </div>

        <h1 class="page-title">私信列表</h1>

        <div class="conversation-list">
            <?php if (empty($conversations)): ?>
                <div class="empty-message">暂无对话，快去给感兴趣的用户发送私信吧～</div>
            <?php else: ?>
                <?php foreach ($conversations as $conv): 
                    $other = $conv['user'];
                    $last = $conv['last_msg'];
                    $is_from_me = $last['from_user_id'] == $user_id;
                ?>
                <a href="send_message.php?user=<?php echo $other['id']; ?>" class="conversation-item">
                    <div class="avatar">
                        <?php if ($other['avatar'] && file_exists($other['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($other['avatar']); ?>" alt="">
                        <?php else: ?>
                            <?php echo strtoupper(mb_substr($other['username'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="conversation-info">
                        <div class="username"><?php echo htmlspecialchars($other['username']); ?></div>
                        <div class="last-msg">
                            <?php if ($is_from_me): ?>我: <?php endif; ?>
                            <?php echo htmlspecialchars(mb_substr($last['content'], 0, 50)) . (mb_strlen($last['content']) > 50 ? '...' : ''); ?>
                        </div>
                    </div>
                    <div class="time"><?php echo date('m-d H:i', strtotime($last['created_at'])); ?></div>
                    <?php if ($conv['unread'] > 0): ?>
                        <div class="unread-badge"><?php echo $conv['unread']; ?></div>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 页脚 -->
        <div class="footer">
            <div>© 2025 lv8girl · 绿坝娘二次元论坛</div>
            <div>
                <a href="#">关于</a>
                <a href="#">帮助</a>
                <a href="#">隐私</a>
                <a href="#">投稿</a>
                <a href="https://icp.gov.moe/?keyword=20260911" target="_blank">萌ICP备20260911号</a>
            </div>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            themeToggle.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌓';
        });
    </script>
</body>

</html>
