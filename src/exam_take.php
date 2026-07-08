<?php
/**
 * 联考答题页面 - 显示题目并允许上传答题卡
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=account');
    exit;
}

$exam_id = intval($_GET['exam_id'] ?? 0);
if (!$exam_id) {
    die('缺少考试ID');
}

// 数据库连接
$host = 'lv8girl-db';
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

// 获取考试信息
$stmt = $pdo->prepare("SELECT * FROM gsk_exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();
if (!$exam) {
    die('考试不存在');
}

// 检查考试类型必须是联考
if ($exam['type'] !== 'exam') {
    die('此考试不是联考');
}

// 检查报名状态
$stmt = $pdo->prepare("SELECT status FROM gsk_exam_signups WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$signup = $stmt->fetch();

$can_enter = false;
$message = '';
if (!$signup) {
    $message = '您尚未报名此联考，请先报名。';
} elseif ($signup['status'] === 'pending') {
    $message = '您的报名正在审核中，请等待通过。';
} elseif ($signup['status'] === 'rejected') {
    $message = '您的报名已被拒绝，请联系管理员。';
} elseif ($signup['status'] === 'approved') {
    $can_enter = true;
}

// 检查时间限制（非管理员/老师可限制）
$is_admin = in_array($_SESSION['role'], ['ADMIN', 'TEACHER']);
if ($can_enter && !$is_admin) {
    $now = new DateTime();
    $start = $exam['start_time'] ? new DateTime($exam['start_time']) : null;
    $end = $exam['end_time'] ? new DateTime($exam['end_time']) : null;
    if ($start && $now < $start) {
        $can_enter = false;
        $message = '考试尚未开始，开始时间：' . $start->format('Y-m-d H:i');
    } elseif ($end && $now > $end) {
        $can_enter = false;
        $message = '考试已结束，结束时间：' . $end->format('Y-m-d H:i');
    } elseif ($exam['status'] === 'ended') {
        $can_enter = false;
        $message = '考试已结束（收卷）';
    }
}

// 获取题目
$stmt = $pdo->prepare("SELECT * FROM gsk_questions WHERE exam_id = ? ORDER BY sort_order, id");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll();

// 获取该用户已提交的答案（用于显示已答）
$stmt = $pdo->prepare("SELECT * FROM gsk_answers WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$user_answers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$answer_map = [];
foreach ($user_answers as $a) {
    $answer_map[$a['question_id']] = $a;
}

// 处理上传答题卡（模拟上传图片）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['answer_image'])) {
    $file = $_FILES['answer_image'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowed)) {
            $upload_error = '仅支持 jpg/png/gif/webp 格式';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $upload_error = '图片不能超过 5MB';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'answer_' . $exam_id . '_' . $user_id . '_' . time() . '.' . $ext;
            $path = 'uploads/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $path)) {
                // 保存到数据库（可以保存到 gsk_answers 或单独的表，这里简单记录）
                // 假设我们为每个题目保存答案，但答题卡上传是整体上传，所以我们可以存到一个字段或新表
                // 暂时只保存到 gsk_answers 的 answer 字段（表示已上传图片）
                // 也可以插入一条特殊记录，但为了简单，我们更新所有题目的 answer 为图片路径（如果还没有答案）
                // 更合理：创建一个新表 gsk_exam_uploads 存储答题卡图片
                // 这里简化：直接存储到 gsk_answers 的 answer 字段，标记为 "image:" . $path
                $stmt = $pdo->prepare("INSERT INTO gsk_answers (exam_id, question_id, user_id, answer, status) 
                                       VALUES (?, 0, ?, ?, 'pending') 
                                       ON DUPLICATE KEY UPDATE answer = VALUES(answer), status = 'pending'");
                // 使用 question_id=0 表示整体答题卡
                $stmt->execute([$exam_id, $user_id, 'image:' . $path]);
                $upload_success = '答题卡上传成功！';
            } else {
                $upload_error = '文件保存失败';
            }
        }
    } else {
        $upload_error = '上传失败，错误码：' . $file['error'];
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>联考 - <?= htmlspecialchars($exam['title']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f6f8fa;
            color: #1e293b;
            padding: 2rem;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
        }
        h1 { font-size: 1.8rem; color: #0b3b4c; margin-bottom: 0.2rem; }
        .exam-meta { color: #64748b; margin-bottom: 1.5rem; }
        .question-item {
            background: #f8fafc;
            padding: 1.2rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #e9edf2;
        }
        .q-header { display: flex; justify-content: space-between; font-weight: 600; color: #0b3b4c; margin-bottom: 0.5rem; }
        .q-content { margin-bottom: 0.5rem; }
        .q-content img { max-width: 100%; height: auto; }
        .q-answer { background: #f1f5f9; padding: 0.3rem 0.8rem; border-radius: 4px; display: inline-block; }
        .btn {
            display: inline-block;
            background: #0b3b4c;
            color: #fff;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            transition: background 0.15s;
        }
        .btn:hover { background: #0a2f3d; }
        .btn-success { background: #0b6b4c; }
        .btn-success:hover { background: #0a5a3e; }
        .btn-warning { background: #d4a373; }
        .btn-warning:hover { background: #c08d5e; }
        .btn-danger { background: #b91c1c; }
        .btn-danger:hover { background: #991b1b; }
        .upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            margin: 1.5rem 0;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .upload-area:hover { border-color: #0b3b4c; background: #f8fafc; }
        .upload-area i { font-size: 3rem; color: #94a3b8; }
        .upload-area p { margin-top: 0.5rem; color: #64748b; }
        .message { padding: 0.8rem 1.2rem; border-radius: 6px; margin: 1rem 0; }
        .message.success { background: #d1fae5; color: #0b6b4c; }
        .message.error { background: #fce4ec; color: #b91c1c; }
        .message.info { background: #dbeafe; color: #1d4ed8; }
        .back-link { margin-top: 1.5rem; display: inline-block; color: #2563eb; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-pencil-alt" style="color:#d4a373;"></i> <?= htmlspecialchars($exam['title']) ?></h1>
    <div class="exam-meta">
        <span>👩‍🏫 <?= htmlspecialchars($exam['teacher']) ?></span>
        <?php if ($exam['start_time']): ?>
            <span style="margin-left:1rem;">⏰ 开始：<?= date('Y-m-d H:i', strtotime($exam['start_time'])) ?></span>
        <?php endif; ?>
        <?php if ($exam['end_time']): ?>
            <span style="margin-left:1rem;">⏰ 结束：<?= date('Y-m-d H:i', strtotime($exam['end_time'])) ?></span>
        <?php endif; ?>
        <span style="margin-left:1rem;">状态：<?= $can_enter ? '✅ 可答题' : '⛔ 不可答题' ?></span>
    </div>

    <?php if (!$can_enter): ?>
        <div class="message error"><?= $message ?></div>
        <a href="?page=exam" class="back-link">← 返回联考列表</a>
        <?php exit; ?>
    <?php endif; ?>

    <!-- 题目展示 -->
    <h2 style="margin-top:1.5rem;">📋 题目列表</h2>
    <?php if (count($questions) > 0): ?>
        <?php foreach ($questions as $idx => $q): ?>
            <div class="question-item">
                <div class="q-header">
                    <span>第 <?= $idx+1 ?> 题 <span class="q-type" style="font-weight:400;font-size:0.8rem;background:#dbeafe;padding:0.1rem 0.6rem;border-radius:12px;color:#1d4ed8;"><?= ['single'=>'单选','multiple'=>'多选','fill'=>'填空','essay'=>'解答'][$q['type']] ?></span></span>
                    <span><?= $q['score'] ?> 分</span>
                </div>
                <div class="q-content"><?= $q['content'] ?></div>
                <?php if (isset($answer_map[$q['id']])): ?>
                    <div class="q-answer">
                        <strong>你的答案：</strong> <?= htmlspecialchars($answer_map[$q['id']]['answer']) ?>
                        <?php if ($answer_map[$q['id']]['score'] !== null): ?>
                            (得分：<?= $answer_map[$q['id']]['score'] ?>)
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="color:#94a3b8;">暂无题目</p>
    <?php endif; ?>

    <!-- 上传答题卡 -->
    <hr style="margin:2rem 0;">
    <h2><i class="fas fa-upload"></i> 上传答题卡</h2>
    <p style="color:#64748b;">请将您的答题卡扫描或拍照后上传（支持 jpg/png/gif/webp，最大 5MB）</p>

    <?php if (isset($upload_success)): ?>
        <div class="message success">✅ <?= $upload_success ?></div>
    <?php endif; ?>
    <?php if (isset($upload_error)): ?>
        <div class="message error">❌ <?= $upload_error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" style="margin-top:1rem;">
        <div class="upload-area" onclick="document.getElementById('fileInput').click();">
            <i class="fas fa-cloud-upload-alt"></i>
            <p>点击选择图片或拖拽上传</p>
            <input type="file" name="answer_image" id="fileInput" accept="image/*" style="display:none;" onchange="this.form.submit();">
        </div>
        <button type="submit" class="btn btn-success" style="margin-top:0.5rem;">提交答题卡</button>
    </form>

    <a href="?page=exam" class="back-link">← 返回联考列表</a>
</div>

<script>
    // 拖拽上传支持
    const area = document.querySelector('.upload-area');
    area.addEventListener('dragover', (e) => {
        e.preventDefault();
        area.style.borderColor = '#0b3b4c';
        area.style.background = '#f0f4f8';
    });
    area.addEventListener('dragleave', () => {
        area.style.borderColor = '#cbd5e1';
        area.style.background = 'transparent';
    });
    area.addEventListener('drop', (e) => {
        e.preventDefault();
        area.style.borderColor = '#cbd5e1';
        area.style.background = 'transparent';
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            const input = document.getElementById('fileInput');
            input.files = files;
            input.form.submit();
        }
    });
</script>
</body>
</html>
