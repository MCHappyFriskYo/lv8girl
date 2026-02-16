<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 连接数据库
$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';               // 数据库用户名
$db_pass = 'yourpasswd';        // 数据库密码（已修改）

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败：' . $e->getMessage());
}

// 获取当前用户的所有帖子
$stmt = $pdo->prepare("SELECT * FROM discussions WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 处理删除后的消息
$message = '';
if (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = '<div class="success-message">帖子已成功删除。</div>';
} elseif (isset($_GET['error'])) {
    $message = '<div class="error-message">' . htmlspecialchars($_GET['error']) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的帖子 - lv8girl</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Noto Sans SC', 'PingFang SC', 'Microsoft YaHei', 'Helvetica Neue', Arial, sans-serif;
        }

        :root {
            --primary: #3d9e4a;
            --primary-dark: #2e7d32;
            --primary-light: #6abf6e;
            --secondary: #ffb347;
            --accent-blue: #5a9cff;
            --accent-purple: #b47aff;
            --accent-pink: #ff7b9c;
            --bg-body: linear-gradient(145deg, #f0f7f0 0%, #e6f0e6 100%);
            --bg-surface: #ffffff;
            --bg-nav: rgba(255,255,255,0.9);
            --text-primary: #2c3e2c;
            --text-secondary: #5f6b5f;
            --text-hint: #8f9f8f;
            --border-light: #e6ede6;
            --border: #d0e0d0;
            --shadow: 0 8px 20px rgba(0,0,0,0.06);
            --hover-shadow: 0 12px 28px rgba(61, 158, 74, 0.2);
            --input-bg: #f5faf5;
            
            --font-weight-light: 400;
            --font-weight-regular: 500;
            --font-weight-bold: 700;
            --font-weight-black: 900;
        }

        body.dark-mode {
            --primary: #6b8e6b;
            --primary-dark: #4a6b4a;
            --primary-light: #8aad8a;
            --secondary: #d9a066;
            --accent-blue: #6688aa;
            --accent-purple: #8a7a9c;
            --accent-pink: #b57a8a;
            --bg-body: linear-gradient(145deg, #1e261e 0%, #232d23 100%);
            --bg-surface: #2c342c;
            --bg-nav: #2c342ccc;
            --text-primary: #e0e8e0;
            --text-secondary: #b0bcb0;
            --text-hint: #8a958a;
            --border-light: #3a453a;
            --border: #4d5a4d;
            --shadow: 0 8px 20px rgba(0,0,0,0.5);
            --hover-shadow: 0 12px 28px rgba(107, 142, 107, 0.3);
            --input-bg: #3a453a;
        }

        body {
            background: var(--bg-body);
            min-height: 100vh;
            transition: background 0.3s;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .mini-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: var(--font-weight-black);
            background: linear-gradient(135deg, var(--primary), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
        }

        .logo span {
            font-size: 0.8rem;
            background: var(--secondary);
            color: var(--primary-dark);
            padding: 4px 10px;
            border-radius: 30px;
            margin-left: 10px;
            font-weight: var(--font-weight-bold);
            -webkit-text-fill-color: var(--primary-dark);
        }

        .theme-toggle {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            color: var(--text-primary);
            font-size: 1.3rem;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .theme-toggle:hover {
            background: linear-gradient(135deg, var(--secondary), var(--accent-pink));
            color: var(--primary-dark);
            transform: rotate(15deg) scale(1.1);
        }

        h1 {
            font-size: 2rem;
            font-weight: var(--font-weight-black);
            background: linear-gradient(135deg, var(--primary), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 30px;
        }

        .message {
            margin-bottom: 20px;
        }
        .success-message {
            background: var(--primary-light);
            color: var(--primary-dark);
            padding: 12px 20px;
            border-radius: 30px;
            text-align: center;
            font-weight: var(--font-weight-bold);
        }
        .error-message {
            background: var(--accent-pink);
            color: white;
            padding: 12px 20px;
            border-radius: 30px;
            text-align: center;
            font-weight: var(--font-weight-bold);
        }

        .post-list {
            list-style: none;
        }

        .post-item {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: all 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .post-item:hover {
            box-shadow: var(--hover-shadow);
            border-color: var(--primary);
        }

        .post-info {
            flex: 1;
        }

        .post-title {
            font-size: 1.2rem;
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .post-date {
            font-size: 0.8rem;
            color: var(--text-hint);
            font-weight: var(--font-weight-light);
        }

        .post-preview {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 8px;
        }

        .post-actions {
            display: flex;
            gap: 10px;
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--accent-pink), #ff4d6d);
            border: none;
            border-radius: 30px;
            padding: 8px 20px;
            color: white;
            font-weight: var(--font-weight-bold);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-delete:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(255, 75, 100, 0.3);
        }

        .btn-view {
            background: linear-gradient(135deg, var(--primary-light), var(--accent-blue));
            border: none;
            border-radius: 30px;
            padding: 8px 20px;
            color: white;
            font-weight: var(--font-weight-bold);
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-view:hover {
            transform: scale(1.05);
            box-shadow: var(--hover-shadow);
        }

        .no-posts {
            text-align: center;
            color: var(--text-hint);
            padding: 50px 0;
            background: var(--bg-surface);
            border-radius: 20px;
            border: 1px dashed var(--border);
        }

        .no-posts a {
            color: var(--primary);
            text-decoration: none;
            font-weight: var(--font-weight-bold);
        }

        .footer-links {
            margin-top: 30px;
            text-align: center;
        }

        .footer-links a {
            color: var(--text-hint);
            text-decoration: none;
            margin: 0 10px;
        }

        .footer-links a:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="mini-nav">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div class="theme-toggle" id="themeToggle">🌓</div>
        </div>

        <h1><?php echo htmlspecialchars($username); ?> 的帖子</h1>

        <?php echo $message; ?>

        <?php if (empty($posts)): ?>
            <div class="no-posts">
                <p>你还没有发表过帖子，<a href="post_discussion.php">快去发表第一篇吧！</a></p>
            </div>
        <?php else: ?>
            <ul class="post-list">
                <?php foreach ($posts as $post): ?>
                    <li class="post-item">
                        <div class="post-info">
                            <div class="post-title">
                                <?php echo htmlspecialchars($post['title']); ?>
                                <?php if ($post['image_path']): ?>
                                    <span style="font-size: 1.2rem;">📷</span>
                                <?php endif; ?>
                            </div>
                            <div class="post-date"><?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></div>
                            <div class="post-preview">
                                <?php echo htmlspecialchars(mb_substr(strip_tags($post['content']), 0, 100)) . (mb_strlen($post['content']) > 100 ? '...' : ''); ?>
                            </div>
                        </div>
                        <div class="post-actions">
                            <a href="delete_post.php?id=<?php echo $post['id']; ?>" class="btn-delete" onclick="return confirm('确定要删除这篇帖子吗？此操作不可撤销。')">删除</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="footer-links">
            <a href="index.php">返回首页</a>
            <a href="post_discussion.php">发表新帖</a>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            themeToggle.textContent = body.classList.contains('dark-mode') ? '☀️' : '🌓';
        });
    </script>
</body>
</html>