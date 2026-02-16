<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header('Location: index.php');
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

// ==================== 处理新番添加/编辑 ====================
$anime_message = '';
$anime_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['anime_submit'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $title = trim($_POST['title'] ?? '');
    $season_year = $_POST['season_year'] ?? '';
$season_quarter = $_POST['season_quarter'] ?? '';
$season = trim($season_year . $season_quarter);
    $description = trim($_POST['description'] ?? '');
    $broadcast_date = $_POST['broadcast_date'] ?? null;
    $status = $_POST['status'] ?? '播出中';

    if (empty($title)) {
        $anime_error = '新番名称不能为空';
    } else {
        // 处理封面上传
        $cover_path = null;
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/anime/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $ext = pathinfo($_FILES['cover']['name'], PATHINFO_EXTENSION);
            $filename = 'anime_' . time() . '_' . uniqid() . '.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['cover']['tmp_name'], $target)) {
                $cover_path = $target;
            } else {
                $anime_error = '封面上传失败';
            }
        }

        if (empty($anime_error)) {
            if ($id > 0) {
                // 更新
                if ($cover_path) {
                    // 删除旧封面
                    $stmt = $pdo->prepare("SELECT cover FROM anime WHERE id = ?");
                    $stmt->execute([$id]);
                    $old = $stmt->fetchColumn();
                    if ($old && file_exists($old)) unlink($old);
                    $stmt = $pdo->prepare("UPDATE anime SET title=?, season=?, description=?, broadcast_date=?, status=?, cover=? WHERE id=?");
                    $stmt->execute([$title, $season, $description, $broadcast_date, $status, $cover_path, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE anime SET title=?, season=?, description=?, broadcast_date=?, status=? WHERE id=?");
                    $stmt->execute([$title, $season, $description, $broadcast_date, $status, $id]);
                }
                $anime_message = '更新成功';
            } else {
                // 新增
                $stmt = $pdo->prepare("INSERT INTO anime (title, season, description, broadcast_date, status, cover) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $season, $description, $broadcast_date, $status, $cover_path]);
                $anime_message = '添加成功';
            }
        }
    }
}

// ==================== 处理新番删除 ====================
if (isset($_GET['delete_anime'])) {
    $id = (int)$_GET['delete_anime'];
    // 删除封面文件
    $stmt = $pdo->prepare("SELECT cover FROM anime WHERE id = ?");
    $stmt->execute([$id]);
    $cover = $stmt->fetchColumn();
    if ($cover && file_exists($cover)) unlink($cover);
    $stmt = $pdo->prepare("DELETE FROM anime WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: admin.php');
    exit;
}

// ==================== 获取数据 ====================
// 所有用户
$users = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
// 所有帖子（连表获取作者名）
$posts = $pdo->query("
    SELECT d.*, u.username 
    FROM discussions d 
    JOIN users u ON d.user_id = u.id 
    ORDER BY d.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
// 所有新番
$anime_list = $pdo->query("SELECT * FROM anime ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// 获取编辑的新番数据
$edit_anime = null;
if (isset($_GET['edit_anime'])) {
    $id = (int)$_GET['edit_anime'];
    $stmt = $pdo->prepare("SELECT * FROM anime WHERE id = ?");
    $stmt->execute([$id]);
    $edit_anime = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 消息处理
$user_post_message = '';
if (isset($_GET['msg'])) {
    $user_post_message = '<div class="success-message">' . htmlspecialchars($_GET['msg']) . '</div>';
} elseif (isset($_GET['error'])) {
    $user_post_message = '<div class="error-message">' . htmlspecialchars($_GET['error']) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员面板 - lv8girl</title>
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
        }

        body {
            background: var(--bg-body);
            min-height: 100vh;
            transition: background 0.3s;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
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

        h1, h2 {
            font-size: 2rem;
            font-weight: var(--font-weight-black);
            background: linear-gradient(135deg, var(--primary), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }

        h2 {
            font-size: 1.6rem;
            margin-top: 40px;
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

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-surface);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        th {
            background: var(--primary);
            color: white;
            font-weight: var(--font-weight-bold);
            padding: 12px;
            text-align: left;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover {
            background: var(--bg-nav);
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-edit, .btn-delete {
            padding: 6px 12px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: var(--font-weight-bold);
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--primary-light), var(--accent-blue));
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--accent-pink), #ff4d6d);
            color: white;
        }

        .btn-edit:hover, .btn-delete:hover {
            transform: scale(1.05);
            box-shadow: var(--hover-shadow);
        }

        .form-card {
            background: var(--bg-surface);
            border-radius: 30px;
            border: 1px solid var(--border);
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-group input, 
        .form-group textarea, 
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            background: var(--bg-nav);
            border: 1px solid var(--border);
            border-radius: 30px;
            color: var(--text-primary);
        }

        .btn {
            background: linear-gradient(135deg, var(--primary), var(--accent-blue));
            border: none;
            border-radius: 40px;
            padding: 12px 30px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
            display: inline-block;
            text-decoration: none;
        }

        .btn:hover {
            transform: scale(1.02);
            box-shadow: var(--hover-shadow);
        }

        .btn-secondary {
            background: var(--text-hint);
            margin-left: 10px;
        }

        .footer-links {
            margin-top: 40px;
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

        .preview-img {
            max-width: 100px;
            border-radius: 10px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="mini-nav">
            <div class="logo">lv8girl<span>管理员</span></div>
            <div class="theme-toggle" id="themeToggle">🌓</div>
        </div>

        <h1>管理员面板</h1>
        <?php echo $user_post_message; ?>

        <!-- ==================== 用户列表 ==================== -->
        <h2>用户列表</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户名</th>
                    <th>邮箱</th>
                    <th>注册时间</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo $user['created_at']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- ==================== 帖子列表 ==================== -->
        <h2>所有帖子</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>标题</th>
                    <th>作者</th>
                    <th>发布时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                <tr>
                    <td><?php echo $post['id']; ?></td>
                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                    <td><?php echo htmlspecialchars($post['username']); ?></td>
                    <td><?php echo $post['created_at']; ?></td>
                    <td class="action-btns">
                        <a href="edit_post.php?id=<?php echo $post['id']; ?>" class="btn-edit">编辑</a>
                        <a href="delete_post.php?id=<?php echo $post['id']; ?>&from=admin" class="btn-delete" onclick="return confirm('确定删除此帖子吗？')">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- ==================== 新番管理 ==================== -->
        <h2>新番管理</h2>

        <?php if ($anime_message): ?>
            <div class="success-message"><?php echo $anime_message; ?></div>
        <?php endif; ?>
        <?php if ($anime_error): ?>
            <div class="error-message"><?php echo $anime_error; ?></div>
        <?php endif; ?>

        <!-- 添加/编辑新番表单 -->
        <div class="form-card">
            <h3 style="margin-bottom:20px;"><?php echo $edit_anime ? '编辑新番' : '添加新番'; ?></h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="anime_submit" value="1">
                <?php if ($edit_anime): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_anime['id']; ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label>新番名称</label>
                    <input type="text" name="title" value="<?php echo $edit_anime ? htmlspecialchars($edit_anime['title']) : ''; ?>" required>
                </div>
                <div class="form-group">
    <label>季度</label>
    <div style="display: flex; gap: 10px;">
        <select name="season_year" style="width: 120px;">
            <?php
            $current_year = date('Y');
            for ($y = $current_year - 2; $y <= $current_year + 2; $y++):
                $selected = ($edit_anime && substr($edit_anime['season'], 0, 4) == $y) ? 'selected' : '';
            ?>
            <option value="<?php echo $y; ?>" <?php echo $selected; ?>><?php echo $y; ?> 年</option>
            <?php endfor; ?>
        </select>
        <select name="season_quarter" style="width: 150px;">
            <option value="第一季度" <?php echo ($edit_anime && strpos($edit_anime['season'], '第一季度') !== false) ? 'selected' : ''; ?>>第一季度（1-3月）</option>
            <option value="第二季度" <?php echo ($edit_anime && strpos($edit_anime['season'], '第二季度') !== false) ? 'selected' : ''; ?>>第二季度（4-6月）</option>
            <option value="第三季度" <?php echo ($edit_anime && strpos($edit_anime['season'], '第三季度') !== false) ? 'selected' : ''; ?>>第三季度（7-9月）</option>
            <option value="第四季度" <?php echo ($edit_anime && strpos($edit_anime['season'], '第四季度') !== false) ? 'selected' : ''; ?>>第四季度（10-12月）</option>
        </select>
    </div>
</div>
                <div class="form-group">
                    <label>简介</label>
                    <textarea name="description" rows="4"><?php echo $edit_anime ? htmlspecialchars($edit_anime['description']) : ''; ?></textarea>
                </div>
                <div class="form-group">
                    <label>首播日期</label>
                    <input type="date" name="broadcast_date" value="<?php echo $edit_anime ? $edit_anime['broadcast_date'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status">
                        <option value="即将播出" <?php echo $edit_anime && $edit_anime['status']=='即将播出'?'selected':''; ?>>即将播出</option>
                        <option value="播出中" <?php echo $edit_anime && $edit_anime['status']=='播出中'?'selected':''; ?>>播出中</option>
                        <option value="已完结" <?php echo $edit_anime && $edit_anime['status']=='已完结'?'selected':''; ?>>已完结</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>封面图片</label>
                    <input type="file" name="cover" accept="image/*">
                    <?php if ($edit_anime && $edit_anime['cover']): ?>
                        <img class="preview-img" src="<?php echo $edit_anime['cover']; ?>" alt="cover">
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn"><?php echo $edit_anime ? '更新' : '添加'; ?></button>
                <?php if ($edit_anime): ?>
                    <a href="admin.php" class="btn btn-secondary">取消编辑</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- 新番列表 -->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>封面</th>
                    <th>名称</th>
                    <th>季度</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($anime_list as $a): ?>
                <tr>
                    <td><?php echo $a['id']; ?></td>
                    <td>
                        <?php if ($a['cover'] && file_exists($a['cover'])): ?>
                            <img src="<?php echo $a['cover']; ?>" style="width:50px; height:50px; object-fit:cover; border-radius:10px;">
                        <?php else: ?>
                            📷
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($a['title']); ?></td>
                    <td><?php echo htmlspecialchars($a['season']); ?></td>
                    <td><?php echo $a['status']; ?></td>
                    <td class="action-btns">
                        <a href="?edit_anime=<?php echo $a['id']; ?>" class="btn-edit">编辑</a>
                        <a href="?delete_anime=<?php echo $a['id']; ?>" class="btn-delete" onclick="return confirm('确定删除吗？')">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

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