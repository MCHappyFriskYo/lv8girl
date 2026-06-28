<?php
/**
 * LunaticCho 管理后台
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (!in_array($_SESSION['role'], ['ADMIN', 'TEACHER'])) {
    die('您没有权限访问管理后台。');
}

function gsk_config() {
    $host = 'db';
    $dbname = 'lv8girl';
    $db_user = 'lv8girl';
    $db_pass = 'yourpasswd';
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die('数据库连接失败：' . $e->getMessage());
    }
}
$pdo = gsk_config();

// 处理 POST 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add_weekly') {
        $title = $_POST['title'];
        $content = $_POST['content'];
        $options = $_POST['options'];
        $answer = $_POST['answer'];
        $status = isset($_POST['status']) ? 1 : 0;
        $teacher = $_SESSION['username'];
        $optionsArray = array_filter(array_map('trim', explode("\n", $options)));
        $optionsJson = json_encode($optionsArray);
        $stmt = $pdo->prepare("INSERT INTO gsk_weekly (title, content, options, answer, teacher, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $content, $optionsJson, $answer, $teacher, $status]);
        $msg = '题目添加成功';
    } elseif ($action === 'edit_weekly') {
        $id = $_POST['id'];
        $title = $_POST['title'];
        $content = $_POST['content'];
        $options = $_POST['options'];
        $answer = $_POST['answer'];
        $status = isset($_POST['status']) ? 1 : 0;
        $optionsArray = array_filter(array_map('trim', explode("\n", $options)));
        $optionsJson = json_encode($optionsArray);
        $stmt = $pdo->prepare("UPDATE gsk_weekly SET title=?, content=?, options=?, answer=?, status=? WHERE id=?");
        $stmt->execute([$title, $content, $optionsJson, $answer, $status, $id]);
        $msg = '题目更新成功';
    } elseif ($action === 'delete_weekly') {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM gsk_weekly WHERE id=?");
        $stmt->execute([$id]);
        $msg = '题目已删除';
    } elseif ($action === 'score_submission') {
        $subId = $_POST['sub_id'];
        $score = intval($_POST['score']);
        $stmt = $pdo->prepare("UPDATE gsk_submissions SET score=?, status='已批改' WHERE id=?");
        $stmt->execute([$score, $subId]);
        $msg = '评分已提交';
    }
    header('Location: admin.php?msg=' . urlencode($msg));
    exit;
}

$weeklyStmt = $pdo->query("SELECT * FROM gsk_weekly ORDER BY created_at DESC");
$weeklies = $weeklyStmt->fetchAll();

$subStmt = $pdo->query("SELECT s.*, u.username FROM gsk_submissions s LEFT JOIN gsk_users u ON s.user_id = u.id ORDER BY s.upload_time DESC");
$submissions = $subStmt->fetchAll();

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LunaticCho 管理后台</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            margin: 0;
            padding: 2rem;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 {
            color: #0b3b4c;
            border-bottom: 2px solid #d4a373;
            padding-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        h1 a {
            font-size: 0.9rem;
            background: #0b3b4c;
            color: #fff;
            padding: 0.2rem 1rem;
            border-radius: 20px;
            text-decoration: none;
        }
        h1 a:hover { background: #0a2f3d; }
        .msg {
            background: #d1fae5;
            color: #0b6b4c;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            margin: 0.5rem 0;
        }
        .section {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .section h2 {
            margin-top: 0;
            color: #0b3b4c;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.6rem 0.8rem;
            text-align: left;
            border-bottom: 1px solid #e9edf2;
        }
        th {
            background: #f8fafc;
            font-weight: 600;
        }
        .btn {
            background: #0b3b4c;
            color: #fff;
            border: none;
            padding: 0.3rem 1rem;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: background 0.15s;
            text-decoration: none;
        }
        .btn:hover { background: #0a2f3d; }
        .btn-danger { background: #b91c1c; }
        .btn-danger:hover { background: #991b1b; }
        .btn-success { background: #0b6b4c; }
        .btn-success:hover { background: #0a5a3e; }
        form.inline { display: inline; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.2rem; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.4rem 0.6rem;
            border: 1px solid #d1d9e6;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        .form-group textarea { min-height: 60px; }
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .form-row .form-group {
            flex: 1;
            min-width: 150px;
        }
        .badge {
            display: inline-block;
            padding: 0.1rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-draft { background: #e2e8f0; color: #475569; }
        .badge-published { background: #d1fae5; color: #0b6b4c; }
        .toggle-form {
            cursor: pointer;
            color: #2563eb;
            font-size: 0.9rem;
        }
        .toggle-form:hover { text-decoration: underline; }
        .hidden { display: none; }
        .edit-form {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin: 0.5rem 0 1rem;
            border: 1px solid #e2e8f0;
        }
        .action-btns {
            display: flex;
            gap: 0.4rem;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>
        <i class="fas fa-tools"></i> LunaticCho 管理后台
        <span style="font-size:0.9rem; font-weight:400; margin-left:auto;">
            欢迎，<?= htmlspecialchars($_SESSION['username']) ?>
            <a href="index.php?action=logout" onclick="return confirm('确认退出？')">退出</a>
        </span>
    </h1>

    <?php if ($msg): ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- ===== 出题管理 ===== -->
    <div class="section">
        <h2><i class="fas fa-pencil-alt"></i> 出题管理</h2>

        <details>
            <summary style="cursor:pointer; font-weight:600; color:#0b3b4c;">➕ 添加新周常题目</summary>
            <form method="POST" style="margin-top:1rem;">
                <input type="hidden" name="action" value="add_weekly">
                <div class="form-row">
                    <div class="form-group">
                        <label>标题</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group">
                        <label>正确答案 (A/B/C/D)</label>
                        <input type="text" name="answer" maxlength="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>题目内容</label>
                    <textarea name="content" rows="2" required></textarea>
                </div>
                <div class="form-group">
                    <label>选项（每行一个）</label>
                    <textarea name="options" rows="4" required placeholder="A. 选项1&#10;B. 选项2&#10;C. 选项3&#10;D. 选项4"></textarea>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="status" checked> 立即发布</label>
                </div>
                <button type="submit" class="btn btn-success">发布题目</button>
            </form>
        </details>

        <hr style="margin:1.5rem 0;">

        <h3>已有题目</h3>
        <?php if (count($weeklies) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>标题</th>
                        <th>出卷老师</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($weeklies as $w): ?>
                    <tr>
                        <td><?= $w['id'] ?></td>
                        <td><?= htmlspecialchars($w['title']) ?></td>
                        <td><?= htmlspecialchars($w['teacher']) ?></td>
                        <td>
                            <span class="badge <?= $w['status'] ? 'badge-published' : 'badge-draft' ?>">
                                <?= $w['status'] ? '已发布' : '草稿' ?>
                            </span>
                        </td>
                        <td class="action-btns">
                            <span class="toggle-form" onclick="toggleEdit(<?= $w['id'] ?>)">编辑</span>
                            <form method="POST" class="inline" onsubmit="return confirm('确认删除？')">
                                <input type="hidden" name="action" value="delete_weekly">
                                <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                <button type="submit" class="btn btn-danger" style="padding:0.1rem 0.8rem; font-size:0.8rem;">删除</button>
                            </form>
                        </td>
                    </tr>
                    <tr id="edit-row-<?= $w['id'] ?>" class="hidden">
                        <td colspan="5">
                            <div class="edit-form">
                                <form method="POST">
                                    <input type="hidden" name="action" value="edit_weekly">
                                    <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>标题</label>
                                            <input type="text" name="title" value="<?= htmlspecialchars($w['title']) ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>正确答案</label>
                                            <input type="text" name="answer" value="<?= htmlspecialchars($w['answer']) ?>" maxlength="1" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>题目内容</label>
                                        <textarea name="content" rows="2" required><?= htmlspecialchars($w['content']) ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>选项</label>
                                        <textarea name="options" rows="4" required><?php
                                            $opts = json_decode($w['options'], true);
                                            echo htmlspecialchars(implode("\n", $opts));
                                        ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label><input type="checkbox" name="status" <?= $w['status'] ? 'checked' : '' ?>> 发布</label>
                                    </div>
                                    <button type="submit" class="btn btn-success">更新</button>
                                    <span class="toggle-form" onclick="toggleEdit(<?= $w['id'] ?>)">取消</span>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#94a3b8;">暂无题目</p>
        <?php endif; ?>
    </div>

    <!-- ===== 阅卷管理 ===== -->
    <div class="section">
        <h2><i class="fas fa-check-double"></i> 阅卷管理</h2>
        <p style="color:#64748b;">对已上传的答题卡进行评分。</p>
        <?php if (count($submissions) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>学生</th>
                        <th>文件名</th>
                        <th>上传时间</th>
                        <th>状态</th>
                        <th>分数</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($submissions as $sub): ?>
                    <tr>
                        <td><?= $sub['id'] ?></td>
                        <td><?= htmlspecialchars($sub['username'] ?? '未知') ?></td>
                        <td><?= htmlspecialchars($sub['file_name']) ?></td>
                        <td><?= date('Y-m-d H:i', strtotime($sub['upload_time'])) ?></td>
                        <td><?= $sub['status'] ?></td>
                        <td><?= $sub['score'] !== null ? $sub['score'] : '-' ?></td>
                        <td>
                            <?php if ($sub['status'] === '待批改'): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('确认评分？')">
                                    <input type="hidden" name="action" value="score_submission">
                                    <input type="hidden" name="sub_id" value="<?= $sub['id'] ?>">
                                    <input type="number" name="score" min="0" max="100" required style="width:60px;">
                                    <button type="submit" class="btn btn-success">提交</button>
                                </form>
                            <?php else: ?>
                                <span style="color:#94a3b8;">已评分</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="color:#94a3b8;">暂无答题卡上传记录。</p>
        <?php endif; ?>
    </div>
</div>

<script>
    function toggleEdit(id) {
        const row = document.getElementById('edit-row-' + id);
        if (row) {
            row.classList.toggle('hidden');
        }
    }
</script>
</body>
</html>
