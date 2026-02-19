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
$db_pass = 'yourpasswd';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败');
}

// 获取接收者信息
$stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
$stmt->execute([$to_user_id]);
$receiver = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$receiver) {
    die('用户不存在');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    if (empty($content)) {
        $error = '消息内容不能为空';
    } else {
        $stmt = $pdo->prepare("INSERT INTO private_messages (from_user_id, to_user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $to_user_id, $content]);
        $success = '消息已发送！';
        // 可选：重定向到对话页
        // header('Location: conversation.php?user=' . $to_user_id);
        // exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发送私信 - lv8girl</title>
    <style>
        /* 使用与论坛一致的样式变量 */
        <?php include 'style.php'; ?>  // 可抽取公共样式，此处简化，可复制之前的变量
        * { margin:0; padding:0; box-sizing:border-box; }
        :root { /* 复制 index 中的变量 */ }
        body { background: var(--bg); color: var(--text); font-family: -apple-system, ...; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .logo { font-size: 2rem; font-weight: 800; background: var(--gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 25px; }
        h2 { color: var(--accent); margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; color: var(--text-soft); }
        textarea { width: 100%; padding: 12px; background: var(--surface-light); border: 1px solid var(--border); border-radius: 12px; color: var(--text); min-height: 150px; }
        .btn { background: var(--gradient); border: none; border-radius: 40px; padding: 12px 30px; color: white; font-weight: 600; cursor: pointer; }
        .error { background: #ff6b6b; color: white; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
        .success { background: var(--primary); color: white; padding: 10px; border-radius: 8px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div><a href="index.php">返回首页</a></div>
        </div>
        <div class="card">
            <h2>发送私信给 <?php echo htmlspecialchars($receiver['username']); ?></h2>
            <?php if ($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
            <?php if ($success): ?><div class="success"><?php echo $success; ?></div><?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label>内容</label>
                    <textarea name="content" placeholder="请输入私信内容..."></textarea>
                </div>
                <button type="submit" class="btn">发送</button>
            </form>
        </div>
    </div>
</body>
</html>