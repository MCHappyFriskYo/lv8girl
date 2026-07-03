<?php
/**
 * LunaticChO 管理后台 - 修复学生列表重复
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (!in_array($_SESSION['role'], ['ADMIN', 'TEACHER'])) {
    die('您没有权限访问管理后台。');
}

// 创建上传目录
if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);
}

function gsk_config() {
    $host = 'lv8girl-db';
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

// ========== 处理 POST 请求 ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---- 图片上传 ----
    if ($action === 'upload_image') {
        $file = $_FILES['image'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['code' => 40001, 'message' => '文件上传失败']);
            exit;
        }
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            echo json_encode(['code' => 40001, 'message' => '仅支持 jpg/png/gif/webp 格式']);
            exit;
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            echo json_encode(['code' => 40001, 'message' => '图片不能超过 2MB']);
            exit;
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $ext;
        $path = 'uploads/' . $filename;
        if (move_uploaded_file($file['tmp_name'], $path)) {
            echo json_encode(['code' => 0, 'data' => ['url' => $path]]);
        } else {
            echo json_encode(['code' => 50000, 'message' => '文件保存失败']);
        }
        exit;
    }

    // ---- 创建考试 ----
    if ($action === 'create_exam') {
        $title = $_POST['title'];
        $description = $_POST['description'] ?? '';
        $teacher = $_SESSION['username'];
        $status = isset($_POST['publish']) ? 'published' : 'draft';
        $stmt = $pdo->prepare("INSERT INTO gsk_exams (title, description, teacher, status, published_at) VALUES (?, ?, ?, ?, ?)");
        $published_at = $status === 'published' ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$title, $description, $teacher, $status, $published_at]);
        $examId = $pdo->lastInsertId();
        $msg = '考试创建成功！ID: ' . $examId;
        header('Location: admin.php?exam_id=' . $examId . '&msg=' . urlencode($msg));
        exit;
    }

    // ---- 添加题目 ----
    if ($action === 'add_question') {
        $exam_id = $_POST['exam_id'];
        $type = $_POST['type'];
        $content = $_POST['content'];
        $score = intval($_POST['score']);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        
        if ($type === 'single' || $type === 'multiple') {
            $options = $_POST['options'];
            $optionsArray = array_filter(array_map('trim', explode("\n", $options)));
            $optionsJson = json_encode($optionsArray);
            $answer = $_POST['answer'];
        } elseif ($type === 'fill') {
            $optionsJson = null;
            $answer = $_POST['fill_answer'];
        } else { // essay
            $optionsJson = null;
            $answer = null;
        }

        $stmt = $pdo->prepare("INSERT INTO gsk_questions (exam_id, type, content, options, answer, score, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$exam_id, $type, $content, $optionsJson, $answer, $score, $sort_order]);
        $msg = '题目添加成功！';
        header('Location: admin.php?exam_id=' . $exam_id . '&msg=' . urlencode($msg));
        exit;
    }

    // ---- 更新题目 ----
    if ($action === 'edit_question') {
        $qid = $_POST['qid'];
        $content = $_POST['content'];
        $score = intval($_POST['score']);
        $sort_order = intval($_POST['sort_order'] ?? 0);
        $exam_id = $_POST['exam_id'];
        
        $stmt = $pdo->prepare("SELECT type FROM gsk_questions WHERE id = ?");
        $stmt->execute([$qid]);
        $q = $stmt->fetch();
        $type = $q['type'];
        
        if ($type === 'single' || $type === 'multiple') {
            $options = $_POST['options'];
            $optionsArray = array_filter(array_map('trim', explode("\n", $options)));
            $optionsJson = json_encode($optionsArray);
            $answer = $_POST['answer'];
            $stmt = $pdo->prepare("UPDATE gsk_questions SET content=?, options=?, answer=?, score=?, sort_order=? WHERE id=?");
            $stmt->execute([$content, $optionsJson, $answer, $score, $sort_order, $qid]);
        } elseif ($type === 'fill') {
            $answer = $_POST['fill_answer'];
            $stmt = $pdo->prepare("UPDATE gsk_questions SET content=?, answer=?, score=?, sort_order=? WHERE id=?");
            $stmt->execute([$content, $answer, $score, $sort_order, $qid]);
        } else {
            $stmt = $pdo->prepare("UPDATE gsk_questions SET content=?, score=?, sort_order=? WHERE id=?");
            $stmt->execute([$content, $score, $sort_order, $qid]);
        }
        $msg = '题目更新成功！';
        header('Location: admin.php?exam_id=' . $exam_id . '&msg=' . urlencode($msg));
        exit;
    }

    // ---- 删除题目 ----
    if ($action === 'delete_question') {
        $qid = $_POST['qid'];
        $exam_id = $_POST['exam_id'];
        $stmt = $pdo->prepare("DELETE FROM gsk_questions WHERE id = ?");
        $stmt->execute([$qid]);
        $msg = '题目已删除';
        header('Location: admin.php?exam_id=' . $exam_id . '&msg=' . urlencode($msg));
        exit;
    }

    // ---- 发布考试 ----
    if ($action === 'publish_exam') {
        $exam_id = $_POST['exam_id'];
        $stmt = $pdo->prepare("UPDATE gsk_exams SET status = 'published', published_at = NOW() WHERE id = ?");
        $stmt->execute([$exam_id]);
        $msg = '考试已发布！';
        header('Location: admin.php?exam_id=' . $exam_id . '&msg=' . urlencode($msg));
        exit;
    }

    // ---- 删除考试 ----
    if ($action === 'delete_exam') {
        $exam_id = $_POST['exam_id'];
        $pdo->prepare("DELETE FROM gsk_answers WHERE exam_id = ?")->execute([$exam_id]);
        $pdo->prepare("DELETE FROM gsk_results WHERE exam_id = ?")->execute([$exam_id]);
        $pdo->prepare("DELETE FROM gsk_questions WHERE exam_id = ?")->execute([$exam_id]);
        $pdo->prepare("DELETE FROM gsk_exams WHERE id = ?")->execute([$exam_id]);
        $msg = '考试已删除';
        header('Location: admin.php?msg=' . urlencode($msg));
        exit;
    }

    // ---- 提交评分（支持二批） ----
    if ($action === 'grade_answer') {
        $answer_id = $_POST['answer_id'];
        $score = intval($_POST['score']);
        $exam_id = $_POST['exam_id'];
        $user_id = $_POST['user_id'];
        
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE gsk_answers SET score = ?, status = 'graded' WHERE id = ?");
            $stmt->execute([$score, $answer_id]);
            
            $stmt = $pdo->prepare("SELECT SUM(score) as total FROM gsk_answers WHERE exam_id = ? AND user_id = ? AND status = 'graded'");
            $stmt->execute([$exam_id, $user_id]);
            $total = $stmt->fetch();
            $totalScore = $total['total'] ?? 0;
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM gsk_answers WHERE exam_id = ? AND user_id = ? AND status = 'pending'");
            $stmt->execute([$exam_id, $user_id]);
            $pending = $stmt->fetch();
            $status = ($pending['pending'] == 0) ? 'graded' : 'pending';
            
            $stmt = $pdo->prepare("INSERT INTO gsk_results (exam_id, user_id, total_score, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE total_score = VALUES(total_score), status = VALUES(status), updated_at = CURRENT_TIMESTAMP");
            $stmt->execute([$exam_id, $user_id, $totalScore, $status]);
            
            $pdo->commit();
            $msg = '评分提交成功！';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = '评分失败：' . $e->getMessage();
        }
        header('Location: admin.php?exam_id=' . $exam_id . '&msg=' . urlencode($msg));
        exit;
    }

    // ---- 自动批改客观题 ----
    if ($action === 'auto_grade') {
        $exam_id = $_POST['exam_id'];
        $stmt = $pdo->prepare("SELECT id, type, answer, score FROM gsk_questions WHERE exam_id = ? AND type IN ('single', 'multiple', 'fill')");
        $stmt->execute([$exam_id]);
        $questions = $stmt->fetchAll();
        
        $pdo->beginTransaction();
        try {
            foreach ($questions as $q) {
                $stmt2 = $pdo->prepare("SELECT id, user_id, answer FROM gsk_answers WHERE question_id = ?");
                $stmt2->execute([$q['id']]);
                $answers = $stmt2->fetchAll();
                
                foreach ($answers as $ans) {
                    $isCorrect = false;
                    if ($q['type'] === 'single') {
                        $isCorrect = trim($ans['answer']) === trim($q['answer']);
                    } elseif ($q['type'] === 'multiple') {
                        $userAnswers = array_map('trim', explode(',', $ans['answer']));
                        $correctAnswers = array_map('trim', explode(',', $q['answer']));
                        sort($userAnswers);
                        sort($correctAnswers);
                        $isCorrect = $userAnswers === $correctAnswers;
                    } elseif ($q['type'] === 'fill') {
                        $isCorrect = trim($ans['answer']) === trim($q['answer']);
                    }
                    $score = $isCorrect ? $q['score'] : 0;
                    $stmt3 = $pdo->prepare("UPDATE gsk_answers SET score = ?, status = 'graded' WHERE id = ?");
                    $stmt3->execute([$score, $ans['id']]);
                }
            }
            
            $stmt = $pdo->prepare("SELECT DISTINCT user_id FROM gsk_answers WHERE exam_id = ?");
            $stmt->execute([$exam_id]);
            $users = $stmt->fetchAll();
            
            foreach ($users as $u) {
                $stmt2 = $pdo->prepare("SELECT SUM(score) as total FROM gsk_answers WHERE exam_id = ? AND user_id = ? AND status = 'graded'");
                $stmt2->execute([$exam_id, $u['user_id']]);
                $total = $stmt2->fetch();
                
                $stmt3 = $pdo->prepare("SELECT COUNT(*) as pending FROM gsk_answers WHERE exam_id = ? AND user_id = ? AND status = 'pending'");
                $stmt3->execute([$exam_id, $u['user_id']]);
                $pending = $stmt3->fetch();
                
                $status = ($pending['pending'] == 0) ? 'graded' : 'pending';
                $stmt4 = $pdo->prepare("INSERT INTO gsk_results (exam_id, user_id, total_score, status) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE total_score = VALUES(total_score), status = VALUES(status), updated_at = CURRENT_TIMESTAMP");
                $stmt4->execute([$exam_id, $u['user_id'], $total['total'] ?? 0, $status]);
            }
            
            $pdo->commit();
            $msg = '自动批改完成！';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = '自动批改失败：' . $e->getMessage();
        }
        header('Location: admin.php?exam_id=' . $exam_id . '&msg=' . urlencode($msg));
        exit;
    }
}

// ========== 获取数据 ==========
$exam_id = $_GET['exam_id'] ?? 0;

$stmt = $pdo->query("SELECT * FROM gsk_exams ORDER BY created_at DESC");
$exams = $stmt->fetchAll();

$questions = [];
$students = [];
$exam = null;
if ($exam_id) {
    $stmt = $pdo->prepare("SELECT * FROM gsk_exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
    
    if ($exam) {
        $stmt = $pdo->prepare("SELECT * FROM gsk_questions WHERE exam_id = ? ORDER BY sort_order, id");
        $stmt->execute([$exam_id]);
        $questions = $stmt->fetchAll();
        
        // ===== 修复：使用 GROUP BY 确保学生唯一 =====
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.email, r.total_score, r.status as result_status 
                               FROM gsk_users u 
                               LEFT JOIN gsk_results r ON r.exam_id = ? AND r.user_id = u.id
                               WHERE u.id IN (SELECT user_id FROM gsk_answers WHERE exam_id = ? GROUP BY user_id)
                               GROUP BY u.id");
        $stmt->execute([$exam_id, $exam_id]);
        $students = $stmt->fetchAll();
        
        // 二次去重（保险）
        $uniqueStudents = [];
        $seen = [];
        foreach ($students as $stu) {
            if (!in_array($stu['id'], $seen)) {
                $seen[] = $stu['id'];
                $uniqueStudents[] = $stu;
            }
        }
        $students = $uniqueStudents;
        
        // 获取每个学生的答题详情
        foreach ($students as &$stu) {
            $stmt = $pdo->prepare("SELECT q.id, q.type, q.content, q.options, q.answer as correct_answer, q.score as max_score, 
                                   a.id as answer_id, a.answer as user_answer, a.score, a.status 
                                   FROM gsk_questions q 
                                   LEFT JOIN gsk_answers a ON a.question_id = q.id AND a.user_id = ? 
                                   WHERE q.exam_id = ? 
                                   ORDER BY q.sort_order, q.id");
            $stmt->execute([$stu['id'], $exam_id]);
            $stu['answers'] = $stmt->fetchAll();
            
            // 如果该学生还没有结果记录，补默认值
            if (!isset($stu['total_score']) && !isset($stu['result_status'])) {
                $stu['total_score'] = null;
                $stu['result_status'] = 'pending';
            }
        }
    }
}

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LunaticChO 管理后台</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ===== 样式与之前相同 ===== */
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            padding: 2rem;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 {
            color: #0b3b4c;
            border-bottom: 2px solid #d4a373;
            padding-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
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
        .section h3 { color: #0b3b4c; margin: 0.5rem 0; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 0.5rem 0.8rem; text-align: left; border-bottom: 1px solid #e9edf2; }
        th { background: #f8fafc; font-weight: 600; }
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
        .btn-warning { background: #d4a373; }
        .btn-warning:hover { background: #c08d5e; }
        .btn-sm { padding: 0.1rem 0.6rem; font-size: 0.8rem; }
        form.inline { display: inline; }
        .form-group { margin-bottom: 0.8rem; }
        .form-group label { display: block; font-weight: 500; margin-bottom: 0.2rem; font-size: 0.9rem; }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 0.4rem 0.6rem;
            border: 1px solid #d1d9e6;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        .form-group textarea { min-height: 50px; }
        .form-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .form-row .form-group { flex: 1; min-width: 120px; }
        .badge {
            display: inline-block;
            padding: 0.1rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-draft { background: #e2e8f0; color: #475569; }
        .badge-published { background: #d1fae5; color: #0b6b4c; }
        .badge-grading { background: #fef3c7; color: #b45309; }
        .badge-completed { background: #dbeafe; color: #1d4ed8; }
        .toggle-form { cursor: pointer; color: #2563eb; font-size: 0.85rem; }
        .toggle-form:hover { text-decoration: underline; }
        .hidden { display: none; }
        .edit-form {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            margin: 0.5rem 0 1rem;
            border: 1px solid #e2e8f0;
        }
        .action-btns { display: flex; gap: 0.4rem; flex-wrap: wrap; }
        .question-type {
            display: inline-block;
            padding: 0.05rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .type-single { background: #dbeafe; color: #1d4ed8; }
        .type-multiple { background: #fef3c7; color: #b45309; }
        .type-fill { background: #d1fae5; color: #0b6b4c; }
        .type-essay { background: #fce4ec; color: #b91c1c; }
        .score-input { width: 60px; padding: 0.2rem 0.3rem; border: 1px solid #d1d9e6; border-radius: 4px; }
        .student-answer {
            background: #f8fafc;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            max-width: 200px;
            font-size: 0.85rem;
        }
        .correct { color: #0b6b4c; font-weight: 600; }
        .wrong { color: #b91c1c; font-weight: 600; }
        .exam-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        .exam-card {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #d4a373;
            cursor: pointer;
            transition: background 0.15s;
        }
        .exam-card:hover { background: #f1f5f9; }
        .exam-card .title { font-weight: 600; font-size: 1rem; }
        .exam-card .meta { font-size: 0.8rem; color: #64748b; }
        .exam-card .status { margin-top: 0.3rem; }
        .upload-img-btn {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 0.2rem 0.8rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            margin-bottom: 0.3rem;
        }
        .upload-img-btn:hover { background: #1d4ed8; }
    </style>
</head>
<body>
<div class="container">
    <h1>
        <i class="fas fa-tools"></i> LunaticChO 管理后台
        <span style="font-size:0.9rem; font-weight:400; margin-left:auto;">
            欢迎，<?= htmlspecialchars($_SESSION['username']) ?>
            <a href="index.php?action=logout" onclick="return confirm('确认退出？')">退出</a>
        </span>
    </h1>

    <?php if ($msg): ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- ===== 考试列表 ===== -->
    <div class="section">
        <h2><i class="fas fa-list"></i> 考试列表</h2>
        <details>
            <summary style="cursor:pointer; font-weight:600; color:#0b3b4c;">➕ 创建新考试</summary>
            <form method="POST" style="margin-top:1rem;">
                <input type="hidden" name="action" value="create_exam">
                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>考试标题</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label><input type="checkbox" name="publish"> 立即发布</label>
                    </div>
                </div>
                <div class="form-group">
                    <label>考试说明</label>
                    <textarea name="description" rows="2"></textarea>
                </div>
                <button type="submit" class="btn btn-success">创建考试</button>
            </form>
        </details>

        <div class="exam-list">
            <?php foreach ($exams as $e): ?>
                <div class="exam-card" onclick="window.location.href='?exam_id=<?= $e['id'] ?>'">
                    <div class="title"><?= htmlspecialchars($e['title']) ?></div>
                    <div class="meta">👩‍🏫 <?= htmlspecialchars($e['teacher']) ?> · <?= date('Y-m-d', strtotime($e['created_at'])) ?></div>
                    <div class="status">
                        <span class="badge badge-<?= $e['status'] ?>">
                            <?= ['draft'=>'草稿', 'published'=>'已发布', 'grading'=>'批改中', 'completed'=>'已完成'][$e['status']] ?? $e['status'] ?>
                        </span>
                        <?php if ($e['status'] === 'draft'): ?>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="publish_exam">
                                <input type="hidden" name="exam_id" value="<?= $e['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm">发布</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($e['status'] !== 'draft'): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('确认删除此考试及所有数据？')">
                                <input type="hidden" name="action" value="delete_exam">
                                <input type="hidden" name="exam_id" value="<?= $e['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">删除</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($exam): ?>
    <!-- ===== 当前考试详情 ===== -->
    <div class="section">
        <h2><i class="fas fa-pencil-alt"></i> <?= htmlspecialchars($exam['title']) ?></h2>
        <p style="color:#64748b;"><?= htmlspecialchars($exam['description'] ?? '') ?></p>
        <p style="font-size:0.85rem; color:#94a3b8;">
            状态：<span class="badge badge-<?= $exam['status'] ?>">
                <?= ['draft'=>'草稿', 'published'=>'已发布', 'grading'=>'批改中', 'completed'=>'已完成'][$exam['status']] ?? $exam['status'] ?>
            </span>
            <?php if ($exam['status'] === 'draft'): ?>
                <form method="POST" class="inline">
                    <input type="hidden" name="action" value="publish_exam">
                    <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                    <button type="submit" class="btn btn-success btn-sm">发布考试</button>
                </form>
            <?php endif; ?>
        </p>
    </div>

    <!-- ===== 添加题目 ===== -->
    <div class="section">
        <h2><i class="fas fa-plus-circle"></i> 添加题目</h2>
        <details open>
            <summary style="cursor:pointer; font-weight:600; color:#0b3b4c;">➕ 添加新题目</summary>
            <form method="POST" style="margin-top:1rem;" id="questionForm">
                <input type="hidden" name="action" value="add_question">
                <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>题型</label>
                        <select name="type" id="qType" onchange="toggleOptions()">
                            <option value="single">单选题</option>
                            <option value="multiple">多选题</option>
                            <option value="fill">填空题</option>
                            <option value="essay">解答题</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>分值</label>
                        <input type="number" name="score" value="5" min="1">
                    </div>
                    <div class="form-group">
                        <label>排序</label>
                        <input type="number" name="sort_order" value="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>题目内容（支持HTML图片，点击下方按钮上传图片）</label>
                    <textarea name="content" id="contentInput" rows="4" required></textarea>
                    <button type="button" class="upload-img-btn" onclick="uploadImage()"><i class="fas fa-image"></i> 上传图片</button>
                    <span style="font-size:0.8rem; color:#94a3b8;">点击后选择图片，自动插入 &lt;img&gt; 标签到内容末尾</span>
                </div>
                
                <div class="form-group" id="optionsGroup">
                    <label>选项（每行一个）</label>
                    <textarea name="options" rows="4" placeholder="A. 选项1&#10;B. 选项2&#10;C. 选项3&#10;D. 选项4"></textarea>
                </div>
                
                <div class="form-group" id="answerGroup">
                    <label>正确答案</label>
                    <input type="text" name="answer" placeholder="单选题填 A/B/C/D，多选题用英文逗号分隔">
                </div>
                
                <div class="form-group hidden" id="fillGroup">
                    <label>填空答案</label>
                    <input type="text" name="fill_answer" placeholder="填空答案">
                </div>
                
                <button type="submit" class="btn btn-success">添加题目</button>
            </form>
        </details>
        
        <?php if (count($questions) > 0): ?>
        <h3 style="margin-top:1.5rem;">已有题目 (<?= count($questions) ?> 题，总分 <?= array_sum(array_column($questions, 'score')) ?> 分)</h3>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>题型</th>
                    <th>内容</th>
                    <th>分值</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($questions as $q): ?>
                <tr>
                    <td><?= $q['id'] ?></td>
                    <td><span class="question-type type-<?= $q['type'] ?>"><?= ['single'=>'单选','multiple'=>'多选','fill'=>'填空','essay'=>'解答'][$q['type']] ?></span></td>
                    <td><?= htmlspecialchars(mb_substr(strip_tags($q['content']), 0, 30)) ?>...</td>
                    <td><?= $q['score'] ?></td>
                    <td class="action-btns">
                        <span class="toggle-form" onclick="toggleEdit(<?= $q['id'] ?>)">编辑</span>
                        <form method="POST" class="inline" onsubmit="return confirm('确认删除？')">
                            <input type="hidden" name="action" value="delete_question">
                            <input type="hidden" name="qid" value="<?= $q['id'] ?>">
                            <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">删除</button>
                        </form>
                    </td>
                </tr>
                <tr id="edit-row-<?= $q['id'] ?>" class="hidden">
                    <td colspan="5">
                        <div class="edit-form">
                            <form method="POST">
                                <input type="hidden" name="action" value="edit_question">
                                <input type="hidden" name="qid" value="<?= $q['id'] ?>">
                                <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                <div class="form-row">
                                    <div class="form-group"><label>分值</label><input type="number" name="score" value="<?= $q['score'] ?>" min="1"></div>
                                    <div class="form-group"><label>排序</label><input type="number" name="sort_order" value="<?= $q['sort_order'] ?>"></div>
                                </div>
                                <div class="form-group">
                                    <label>题目内容</label>
                                    <textarea name="content" rows="4" required><?= htmlspecialchars($q['content']) ?></textarea>
                                    <button type="button" class="upload-img-btn" onclick="uploadImageForEdit('edit_content_<?= $q['id'] ?>')"><i class="fas fa-image"></i> 上传图片</button>
                                </div>
                                <?php if ($q['type'] === 'single' || $q['type'] === 'multiple'): ?>
                                    <div class="form-group"><label>选项</label><textarea name="options" rows="4" required><?php 
                                        $opts = json_decode($q['options'], true);
                                        echo htmlspecialchars(implode("\n", $opts));
                                    ?></textarea></div>
                                    <div class="form-group"><label>正确答案</label><input type="text" name="answer" value="<?= htmlspecialchars($q['answer']) ?>"></div>
                                <?php elseif ($q['type'] === 'fill'): ?>
                                    <div class="form-group"><label>填空答案</label><input type="text" name="fill_answer" value="<?= htmlspecialchars($q['answer']) ?>"></div>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-success btn-sm">更新</button>
                                <span class="toggle-form" onclick="toggleEdit(<?= $q['id'] ?>)">取消</span>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <!-- ===== 阅卷管理（修复重复） ===== -->
    <div class="section">
        <h2><i class="fas fa-check-double"></i> 阅卷管理</h2>
        
        <?php if (count($students) > 0): ?>
            <div style="margin-bottom:1rem;">
                <form method="POST" class="inline" onsubmit="return confirm('确定自动批改所有客观题？')">
                    <input type="hidden" name="action" value="auto_grade">
                    <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                    <button type="submit" class="btn btn-warning">🤖 自动批改客观题</button>
                </form>
            </div>
            
            <?php foreach ($students as $stu): ?>
                <details style="margin-bottom:0.8rem;">
                    <summary style="cursor:pointer; font-weight:600; color:#0b3b4c; padding:0.5rem; background:#f8fafc; border-radius:6px;">
                        <?= htmlspecialchars($stu['username']) ?> 
                        (<?= $stu['email'] ?>) 
                        - 总分: <?= $stu['total_score'] ?? '未批改' ?>
                        <span class="badge badge-<?= ($stu['result_status'] ?? 'pending') === 'graded' ? 'completed' : 'grading' ?>">
                            <?= ($stu['result_status'] ?? 'pending') === 'graded' ? '已批改' : '待批改' ?>
                        </span>
                    </summary>
                    <div style="padding:1rem 0.5rem;">
                        <table>
                            <thead>
                                <tr>
                                    <th>题号</th>
                                    <th>题型</th>
                                    <th>题目</th>
                                    <th>学生答案</th>
                                    <th>得分</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($stu['answers'] as $ans): ?>
                                <tr>
                                    <td><?= $ans['id'] ?></td>
                                    <td><span class="question-type type-<?= $ans['type'] ?>"><?= ['single'=>'单选','multiple'=>'多选','fill'=>'填空','essay'=>'解答'][$ans['type']] ?></span></td>
                                    <td><?= htmlspecialchars(mb_substr(strip_tags($ans['content']), 0, 25)) ?>...</td>
                                    <td>
                                        <?php if ($ans['type'] === 'essay'): ?>
                                            <div class="student-answer" style="max-width:300px; white-space:pre-wrap;"><?= htmlspecialchars($ans['user_answer'] ?? '未作答') ?></div>
                                        <?php elseif ($ans['type'] === 'multiple'): ?>
                                            <?= htmlspecialchars($ans['user_answer'] ?? '未作答') ?>
                                        <?php else: ?>
                                            <?= htmlspecialchars($ans['user_answer'] ?? '未作答') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ans['score'] !== null): ?>
                                            <span class="<?= $ans['score'] == $ans['max_score'] ? 'correct' : 'wrong' ?>">
                                                <?= $ans['score'] ?>/<?= $ans['max_score'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;">待批改</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ans['user_answer']): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('确认评分？')">
                                                <input type="hidden" name="action" value="grade_answer">
                                                <input type="hidden" name="answer_id" value="<?= $ans['answer_id'] ?>">
                                                <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                                <input type="hidden" name="user_id" value="<?= $stu['id'] ?>">
                                                <input type="number" name="score" min="0" max="<?= $ans['max_score'] ?>" style="width:50px;" 
                                                       value="<?= $ans['score'] !== null ? $ans['score'] : '' ?>" required>
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <?= $ans['status'] === 'graded' ? '修改' : '评分' ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color:#94a3b8;">未作答</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color:#94a3b8;">暂无学生提交答案。</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
    // 切换题型
    function toggleOptions() {
        const type = document.getElementById('qType').value;
        const optionsGroup = document.getElementById('optionsGroup');
        const answerGroup = document.getElementById('answerGroup');
        const fillGroup = document.getElementById('fillGroup');
        
        if (type === 'single' || type === 'multiple') {
            optionsGroup.style.display = 'block';
            answerGroup.style.display = 'block';
            fillGroup.style.display = 'none';
        } else if (type === 'fill') {
            optionsGroup.style.display = 'none';
            answerGroup.style.display = 'none';
            fillGroup.style.display = 'block';
        } else { // essay
            optionsGroup.style.display = 'none';
            answerGroup.style.display = 'none';
            fillGroup.style.display = 'none';
        }
    }
    toggleOptions();

    function toggleEdit(id) {
        const row = document.getElementById('edit-row-' + id);
        if (row) row.classList.toggle('hidden');
    }

    // 上传图片（添加）
    function uploadImage() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = function(e) {
            const file = this.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('action', 'upload_image');
            formData.append('image', file);
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.code === 0) {
                    const url = data.data.url;
                    const textarea = document.getElementById('contentInput');
                    textarea.value += '\n<img src="' + url + '" alt="题目图片" style="max-width:100%;">\n';
                } else {
                    alert('上传失败：' + data.message);
                }
            })
            .catch(err => alert('上传出错：' + err.message));
        };
        input.click();
    }

    // 上传图片（编辑）
    function uploadImageForEdit(textareaId) {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = function(e) {
            const file = this.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('action', 'upload_image');
            formData.append('image', file);
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.code === 0) {
                    const url = data.data.url;
                    const textarea = document.getElementById(textareaId);
                    textarea.value += '\n<img src="' + url + '" alt="题目图片" style="max-width:100%;">\n';
                } else {
                    alert('上传失败：' + data.message);
                }
            })
            .catch(err => alert('上传出错：' + err.message));
        };
        input.click();
    }
</script>
</body>
</html>
