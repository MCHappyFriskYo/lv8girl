<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    // 仅管理员可编辑
    header('Location: index.php');
    exit;
}

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$post_id) {
    header('Location: admin.php?error=无效的帖子ID');
    exit;
}

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

// 获取帖子信息
$stmt = $pdo->prepare("SELECT * FROM discussions WHERE id = ?");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    header('Location: admin.php?error=帖子不存在');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        $error = '标题和内容不能为空';
    } else {
        // 更新帖子
        $stmt = $pdo->prepare("UPDATE discussions SET title = ?, content = ? WHERE id = ?");
        $stmt->execute([$title, $content, $post_id]);
        $success = '帖子已更新';
        // 重新获取数据
        $post['title'] = $title;
        $post['content'] = $content;
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑帖子 - lv8girl</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        /* 复用与 post_discussion.php 相同的样式，此处省略以节省篇幅，但实际需保留 */
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Noto Sans SC',...; }
        :root { ... }
        body.dark-mode { ... }
        body { background:var(--bg-body); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .post-wrapper { max-width:800px; width:100%; }
        .mini-nav { display:flex; align-items:center; justify-content:space-between; margin-bottom:30px; }
        .logo { font-size:1.8rem; font-weight:var(--font-weight-black); background:linear-gradient(135deg,var(--primary),var(--accent-blue)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
        .logo span { font-size:0.8rem; background:var(--secondary); color:var(--primary-dark); padding:4px 10px; border-radius:30px; margin-left:10px; font-weight:var(--font-weight-bold); -webkit-text-fill-color:var(--primary-dark); }
        .theme-toggle { background:var(--bg-surface); border:1px solid var(--border); color:var(--text-primary); font-size:1.3rem; width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:all 0.2s; }
        .theme-toggle:hover { background:linear-gradient(135deg,var(--secondary),var(--accent-pink)); color:var(--primary-dark); transform:rotate(15deg) scale(1.1); }
        .post-card { background:var(--bg-surface); backdrop-filter:blur(10px); border-radius:32px; border:1px solid var(--border); box-shadow:var(--shadow); padding:32px; }
        .mascot { text-align:center; margin-bottom:20px; font-size:3rem; filter:drop-shadow(0 8px 0 var(--primary-dark)); animation:float 3s ease-in-out infinite; }
        @keyframes float { 0%{transform:translateY(0);}50%{transform:translateY(-8px);}100%{transform:translateY(0);} }
        h2 { font-size:1.8rem; font-weight:var(--font-weight-black); text-align:center; margin-bottom:30px; background:linear-gradient(135deg,var(--primary),var(--accent-purple)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
        .post-form { display:flex; flex-direction:column; gap:20px; }
        .form-group { display:flex; flex-direction:column; gap:8px; }
        .form-group label { font-size:1rem; font-weight:var(--font-weight-bold); color:var(--text-primary); }
        .form-group input[type="text"], .form-group textarea { background:var(--input-bg); border:1px solid var(--border); border-radius:30px; padding:12px 20px; font-size:1rem; color:var(--text-primary); transition:all 0.2s; outline:none; width:100%; }
        .form-group textarea { border-radius:20px; resize:vertical; min-height:150px; }
        .form-group input:focus, .form-group textarea:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(61,158,74,0.2); }
        .btn-primary { background:linear-gradient(135deg,var(--primary),var(--accent-blue)); border:none; border-radius:40px; padding:14px; color:white; font-weight:var(--font-weight-bold); font-size:1rem; cursor:pointer; transition:all 0.2s; margin-top:10px; }
        .btn-primary:hover { background:linear-gradient(135deg,var(--primary-light),var(--accent-purple)); transform:scale(1.02); box-shadow:var(--hover-shadow); }
        .auth-footer { text-align:center; margin-top:20px; color:var(--text-hint); }
        .auth-footer a { color:var(--primary); text-decoration:none; font-weight:var(--font-weight-bold); }
        .auth-footer a:hover { text-decoration:underline; }
        .error-message { background:var(--accent-pink); color:white; padding:12px; border-radius:30px; text-align:center; margin-bottom:20px; }
        .success-message { background:var(--primary-light); color:var(--primary-dark); padding:12px; border-radius:30px; text-align:center; margin-bottom:20px; }
        @media screen and (max-width:768px) { .post-wrapper { padding:10px; } h2 { font-size:1.5rem; } }
    </style>
</head>
<body>
    <div class="post-wrapper">
        <div class="mini-nav">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div class="theme-toggle" id="themeToggle">🌓</div>
        </div>

        <div class="post-card">
            <div class="mascot">🍀</div>
            <h2>编辑帖子</h2>
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="post" class="post-form">
                <div class="form-group">
                    <label>标题</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label>内容</label>
                    <textarea name="content" required><?php echo htmlspecialchars($post['content']); ?></textarea>
                </div>
                <button type="submit" class="btn-primary">保存修改</button>
            </form>
            <div class="auth-footer">
                <a href="admin.php">返回管理面板</a>
            </div>
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