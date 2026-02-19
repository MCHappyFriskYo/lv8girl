<?php
session_start();

// 仅允许管理员访问（角色为 'admin'）
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: index.php');
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

$current_user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';

// 获取当前页面参数，默认为仪表盘
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// 处理审核操作（通过/拒绝）
if (isset($_GET['action']) && isset($_GET['id']) && $page === 'pending_posts') {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE discussions SET status = 'approved' WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: admin.php?page=pending_posts&msg=帖子已通过审核');
        exit;
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE discussions SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: admin.php?page=pending_posts&msg=帖子已拒绝');
        exit;
    }
}

// 处理删除操作（帖子、用户、评论）
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = (int)$_GET['id'];
    if ($action === 'delete_post') {
        $stmt = $pdo->prepare("DELETE FROM discussions WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: admin.php?page=posts&msg=帖子已删除');
        exit;
    } elseif ($action === 'delete_user') {
        if ($id == $current_user_id) {
            header('Location: admin.php?page=users&msg=不能删除自己');
            exit;
        }
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: admin.php?page=users&msg=用户已删除');
        exit;
    } elseif ($action === 'delete_comment') {
        $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: admin.php?page=comments&msg=评论已删除');
        exit;
    }
}

// 处理角色修改
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['new_role'])) {
    $target_user_id = (int)$_POST['user_id'];
    $new_role = $_POST['new_role'];
    if ($target_user_id != $current_user_id) {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $target_user_id]);
        header('Location: admin.php?page=users&msg=用户角色已更新');
        exit;
    } else {
        header('Location: admin.php?page=users&msg=不能修改自己的角色');
        exit;
    }
}

// 获取统计数据
$stats = [];
if ($pdo) {
    $stats['posts'] = $pdo->query("SELECT COUNT(*) FROM discussions")->fetchColumn();
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['comments'] = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
    $stats['likes'] = $pdo->query("SELECT COUNT(*) FROM likes")->fetchColumn();
    $stats['online'] = $pdo->query("SELECT COUNT(*) FROM users WHERE last_active > DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->fetchColumn();
    $stats['approved'] = $pdo->query("SELECT COUNT(*) FROM discussions WHERE status = 'approved'")->fetchColumn();
    $stats['rejected'] = $pdo->query("SELECT COUNT(*) FROM discussions WHERE status = 'rejected'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理面板 · lv8girl</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        /* 高级配色：深蓝灰背景，金色点缀，柔和文本 */
        :root {
            --bg: #0f0f1a;              /* 深蓝黑背景 */
            --surface: #1a1a2f;          /* 卡片背景 */
            --surface-light: #252540;     /* 浅色表面 */
            --border: #2d2d4a;            /* 边框 */
            --border-light: #3a3a5a;       /* 浅边框 */
            --text: #e0e0f0;              /* 主文本 */
            --text-soft: #b0b0d0;          /* 次要文本 */
            --text-hint: #8080a0;          /* 提示文本 */
            --primary: #c5a572;            /* 金色主色 */
            --primary-light: #d4b78c;       /* 浅金色 */
            --accent: #a58e6d;              /* 深金色 */
            --accent-dark: #7a684c;          /* 暗金色 */
            --gradient: linear-gradient(135deg, #c5a572, #9a7e5a); /* 金色渐变 */
            --sidebar-width: 220px;
        }
        body.dark-mode {
            /* 深色模式可保持相近，或稍作变化，这里沿用同一套即可 */
            --bg: #0f0f1a;
            --surface: #1a1a2f;
            --surface-light: #252540;
            --border: #2d2d4a;
            --border-light: #3a3a5a;
            --text: #e0e0f0;
            --text-soft: #b0b0d0;
            --text-hint: #8080a0;
            --primary: #c5a572;
            --primary-light: #d4b78c;
            --accent: #a58e6d;
            --accent-dark: #7a684c;
            --gradient: linear-gradient(135deg, #c5a572, #9a7e5a);
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            line-height: 1.6;
            transition: background 0.3s, color 0.3s;
        }
        .admin-wrapper {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: var(--sidebar-width);
            background: var(--surface);
            border-right: 1px solid var(--border);
            padding: 20px 0;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }
        .sidebar-header .logo {
            font-size: 1.6rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 5px;
        }
        .sidebar-header p {
            color: var(--text-soft);
            font-size: 0.85rem;
        }
        .sidebar-menu {
            list-style: none;
        }
        .sidebar-menu li {
            margin: 5px 0;
        }
        .sidebar-menu a {
            display: block;
            padding: 10px 20px;
            color: var(--text-soft);
            text-decoration: none;
            transition: 0.2s;
            border-left: 4px solid transparent;
        }
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--surface-light);
            border-left-color: var(--primary);
            color: var(--primary);
        }
        .sidebar-menu .separator {
            height: 1px;
            background: var(--border);
            margin: 15px 20px;
        }

        .main-content {
            flex: 1;
            padding: 20px 30px;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--primary);
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-info span {
            background: var(--surface-light);
            padding: 6px 16px;
            border-radius: 30px;
            color: var(--text);
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
            background: var(--primary);
            color: var(--bg);
        }

        /* 统计卡片网格 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        /* 卡片样式：使用flex布局确保内容居中 */
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.2;
            margin-bottom: 4px;
        }
        .stat-label {
            color: var(--text-hint);
            font-size: 0.95rem;
        }

        /* 帖子总数卡片内嵌统计 */
        .post-stat-detail {
            margin-top: 15px;
            border-top: 1px solid var(--border-light);
            padding-top: 15px;
            display: flex;
            justify-content: space-around;
            width: 100%;
        }
        .post-stat-item {
            text-align: center;
        }
        .post-stat-number {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary);
        }
        .post-stat-number.reject {
            color: #ff6b6b;
        }
        .post-stat-label {
            font-size: 0.8rem;
            color: var(--text-hint);
        }

        .table-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: var(--surface-light);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-light);
            color: var(--text-soft);
        }
        tr:last-child td {
            border-bottom: none;
        }
        tr:hover {
            background: var(--surface-light);
        }
        .actions a {
            margin-right: 10px;
            color: var(--text-hint);
            text-decoration: none;
        }
        .actions a:hover {
            color: var(--primary);
        }
        .actions .delete {
            color: #ff6b6b;
        }
        .actions .delete:hover {
            color: #ff4d4d;
        }
        .actions .approve {
            color: var(--primary);
        }
        .actions .approve:hover {
            color: var(--primary-light);
        }

        .message {
            background: var(--surface-light);
            border-left: 4px solid var(--primary);
            padding: 12px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            color: var(--text);
        }

        .settings-form {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 25px;
            max-width: 600px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-soft);
        }
        input[type="text"],
        input[type="email"],
        textarea {
            width: 100%;
            padding: 10px 15px;
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 30px;
            color: var(--text);
        }
        button {
            background: var(--gradient);
            border: none;
            border-radius: 40px;
            padding: 10px 30px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        button:hover {
            transform: scale(1.02);
        }

        @media (max-width: 768px) {
            .admin-wrapper {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
            }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- 左侧导航 -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">lv8girl</div>
                <p>管理面板</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="admin.php?page=dashboard" class="<?php echo $page === 'dashboard' ? 'active' : ''; ?>">📊 仪表盘</a></li>
                <li><a href="admin.php?page=pending_posts" class="<?php echo $page === 'pending_posts' ? 'active' : ''; ?>">⏳ 待审核帖子</a></li>
                <li><a href="admin.php?page=posts" class="<?php echo $page === 'posts' ? 'active' : ''; ?>">📝 帖子管理</a></li>
                <li><a href="admin.php?page=users" class="<?php echo $page === 'users' ? 'active' : ''; ?>">👥 用户管理</a></li>
                <li><a href="admin.php?page=comments" class="<?php echo $page === 'comments' ? 'active' : ''; ?>">💬 评论管理</a></li>
                <li class="separator"></li>
                <li><a href="admin.php?page=settings" class="<?php echo $page === 'settings' ? 'active' : ''; ?>">⚙️ 设置</a></li>
                <li><a href="index.php">🏠 返回首页</a></li>
            </ul>
        </aside>

        <!-- 右侧主内容 -->
        <main class="main-content">
            <div class="top-bar">
                <h1 class="page-title">
                    <?php
                    $titles = [
                        'dashboard' => '仪表盘',
                        'pending_posts' => '待审核帖子',
                        'posts' => '帖子管理',
                        'users' => '用户管理',
                        'comments' => '评论管理',
                        'settings' => '站点设置',
                    ];
                    echo $titles[$page] ?? '仪表盘';
                    ?>
                </h1>
                <div class="user-info">
                    <span><?php echo htmlspecialchars($username); ?></span>
                    <div class="theme-toggle" id="themeToggle">🌓</div>
                </div>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="message"><?php echo htmlspecialchars($_GET['msg']); ?></div>
            <?php endif; ?>

            <?php if ($page === 'dashboard'): ?>
                <!-- 仪表盘 -->
                <div class="stats-grid">
                    <!-- 帖子总数卡片（内含通过/拒绝明细） -->
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['posts']); ?></div>
                        <div class="stat-label">帖子总数</div>
                        <div class="post-stat-detail">
                            <div class="post-stat-item">
                                <div class="post-stat-number"><?php echo number_format($stats['approved']); ?></div>
                                <div class="post-stat-label">通过数</div>
                            </div>
                            <div class="post-stat-item">
                                <div class="post-stat-number reject"><?php echo number_format($stats['rejected']); ?></div>
                                <div class="post-stat-label">拒绝数</div>
                            </div>
                        </div>
                    </div>
                    <!-- 其他统计卡片 -->
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['users']); ?></div>
                        <div class="stat-label">注册用户</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['comments']); ?></div>
                        <div class="stat-label">评论总数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo number_format($stats['likes']); ?></div>
                        <div class="stat-label">点赞总数</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['online']; ?></div>
                        <div class="stat-label">实时在线</div>
                    </div>
                </div>

            <?php elseif ($page === 'pending_posts'): ?>
                <!-- 待审核帖子 -->
                <div class="table-card">
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
                            <?php
                            $stmt = $pdo->query("
                                SELECT d.*, u.username
                                FROM discussions d
                                JOIN users u ON d.user_id = u.id
                                WHERE d.status = 'pending'
                                ORDER BY d.created_at DESC
                            ");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><a href="post.php?id=<?php echo $row['id']; ?>" target="_blank"><?php echo htmlspecialchars($row['title']); ?></a></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="admin.php?page=pending_posts&action=approve&id=<?php echo $row['id']; ?>" class="approve" onclick="return confirm('通过审核？')">通过</a>
                                    <a href="admin.php?page=pending_posts&action=reject&id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('拒绝审核？')">拒绝</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($page === 'posts'): ?>
                <!-- 帖子管理（所有帖子） -->
                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>标题</th>
                                <th>作者</th>
                                <th>状态</th>
                                <th>发布时间</th>
                                <th>阅读数</th>
                                <th>点赞数</th>
                                <th>评论数</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("
                                SELECT d.*, u.username,
                                    (SELECT COUNT(*) FROM likes WHERE post_id = d.id) AS like_count,
                                    (SELECT COUNT(*) FROM comments WHERE post_id = d.id) AS comment_count
                                FROM discussions d
                                JOIN users u ON d.user_id = u.id
                                ORDER BY d.created_at DESC
                            ");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><a href="post.php?id=<?php echo $row['id']; ?>" target="_blank"><?php echo htmlspecialchars($row['title']); ?></a></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td>
                                    <?php
                                    $status_text = [
                                        'pending' => '待审核',
                                        'approved' => '已通过',
                                        'rejected' => '已拒绝'
                                    ];
                                    echo $status_text[$row['status']] ?? $row['status'];
                                    ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                <td><?php echo number_format($row['views']); ?></td>
                                <td><?php echo number_format($row['like_count']); ?></td>
                                <td><?php echo number_format($row['comment_count']); ?></td>
                                <td class="actions">
                                    <a href="edit_post.php?id=<?php echo $row['id']; ?>">编辑</a>
                                    <a href="admin.php?page=posts&action=delete_post&id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('确定删除此帖子吗？')">删除</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($page === 'users'): ?>
                <!-- 用户管理 -->
                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>用户名</th>
                                <th>邮箱</th>
                                <th>角色</th>
                                <th>注册时间</th>
                                <th>最后活动</th>
                                <th>帖子数</th>
                                <th>评论数</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("
                                SELECT u.*,
                                    (SELECT COUNT(*) FROM discussions WHERE user_id = u.id) AS post_count,
                                    (SELECT COUNT(*) FROM comments WHERE user_id = u.id) AS comment_count
                                FROM users u
                                ORDER BY u.id
                            ");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td>
                                    <?php if ($row['id'] == $current_user_id): ?>
                                        <?php 
                                        $role_text = '';
                                        if ($row['role'] === 'admin') $role_text = '管理员';
                                        elseif ($row['role'] === 'banned') $role_text = '封禁用户';
                                        else $role_text = '正常用户';
                                        echo $role_text;
                                        ?>
                                    <?php else: ?>
                                        <form method="post" action="admin.php?page=users" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                            <select name="new_role" onchange="this.form.submit()">
                                                <option value="user" <?php echo $row['role'] === 'user' ? 'selected' : ''; ?>>正常用户</option>
                                                <option value="admin" <?php echo $row['role'] === 'admin' ? 'selected' : ''; ?>>管理员</option>
                                                <option value="banned" <?php echo $row['role'] === 'banned' ? 'selected' : ''; ?>>封禁用户</option>
                                            </select>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                <td><?php echo $row['last_active'] ? date('Y-m-d H:i', strtotime($row['last_active'])) : '从未'; ?></td>
                                <td><?php echo number_format($row['post_count']); ?></td>
                                <td><?php echo number_format($row['comment_count']); ?></td>
                                <td class="actions">
                                    <?php if ($row['id'] != $current_user_id): ?>
                                        <a href="admin.php?page=users&action=delete_user&id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('确定删除此用户吗？所有关联内容将被删除。')">删除</a>
                                    <?php else: ?>
                                        <span>当前用户</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($page === 'comments'): ?>
                <!-- 评论管理 -->
                <div class="table-card">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>帖子标题</th>
                                <th>评论者</th>
                                <th>内容</th>
                                <th>发布时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $stmt = $pdo->query("
                                SELECT c.*, d.title AS post_title, u.username
                                FROM comments c
                                JOIN discussions d ON c.post_id = d.id
                                JOIN users u ON c.user_id = u.id
                                ORDER BY c.created_at DESC
                            ");
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)):
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><a href="post.php?id=<?php echo $row['post_id']; ?>" target="_blank"><?php echo htmlspecialchars($row['post_title']); ?></a></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars(mb_substr($row['content'], 0, 50)) . (mb_strlen($row['content']) > 50 ? '...' : ''); ?></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                                <td class="actions">
                                    <a href="admin.php?page=comments&action=delete_comment&id=<?php echo $row['id']; ?>" class="delete" onclick="return confirm('确定删除此评论吗？')">删除</a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            <?php elseif ($page === 'settings'): ?>
                <!-- 站点设置 -->
                <div class="settings-form">
                    <form method="post" action="admin.php?page=settings">
                        <div class="form-group">
                            <label>站点名称</label>
                            <input type="text" name="site_name" value="lv8girl 论坛">
                        </div>
                        <div class="form-group">
                            <label>站点描述</label>
                            <textarea name="site_description" rows="3">一个 ACG 爱好者的聚集地</textarea>
                        </div>
                        <button type="submit">保存设置</button>
                    </form>
                    <?php
                    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                        echo '<div class="message" style="margin-top:20px;">设置已保存（演示功能）</div>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </main>
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