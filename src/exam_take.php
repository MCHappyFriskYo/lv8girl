<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=account');
    exit;
}

$exam_id = intval($_GET['exam_id'] ?? 0);
if (!$exam_id) {
    die('缺少考试ID');
}

$user_id = $_SESSION['user_id'];

// 数据库连接
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

// 获取考试信息
$stmt = $pdo->prepare("SELECT * FROM gsk_exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();
if (!$exam) {
    die('考试不存在');
}

// 检查是否为联考类型
if ($exam['type'] !== 'exam') {
    die('此考试不是联考');
}

// 检查报名状态
$stmt = $pdo->prepare("SELECT status FROM gsk_exam_signups WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$signup = $stmt->fetch();
if (!$signup || $signup['status'] !== 'approved') {
    die('您未通过报名审核，无法参加此联考');
}

// 检查时间限制
$now = new DateTime();
$start = $exam['start_time'] ? new DateTime($exam['start_time']) : null;
$end = $exam['end_time'] ? new DateTime($exam['end_time']) : null;

if ($start && $now < $start) {
    die('考试尚未开始，请于 ' . $start->format('Y-m-d H:i') . ' 后进入');
}
if ($end && $now > $end) {
    die('考试已结束，不可进入');
}
if ($exam['status'] === 'ended') {
    die('考试已结束（收卷）');
}

// 获取题目
$stmt = $pdo->prepare("SELECT * FROM gsk_questions WHERE exam_id = ? ORDER BY sort_order, id");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll();

// 获取已上传的答题卡（用于显示已上传）
$stmt = $pdo->prepare("SELECT * FROM gsk_exam_submissions WHERE exam_id = ? AND user_id = ? ORDER BY upload_time DESC");
$stmt->execute([$exam_id, $user_id]);
$submissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>联考 · <?= htmlspecialchars($exam['title']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f6f8fa;
            color: #1e293b;
            padding: 2rem 1.5rem;
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            border: 1px solid #e9edf2;
        }
        .header h1 {
            font-size: 1.8rem;
            color: #0b3b4c;
            margin-bottom: 0.3rem;
        }
        .header .meta {
            color: #64748b;
            font-size: 0.9rem;
        }
        .header .meta span {
            margin-right: 1.5rem;
        }
        .question-item {
            background: #fff;
            border-radius: 8px;
            padding: 1.2rem 1.5rem;
            margin-bottom: 1.2rem;
            border: 1px solid #e9edf2;
        }
        .question-item .q-header {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            color: #0b3b4c;
            margin-bottom: 0.5rem;
        }
        .question-item .q-type {
            font-weight: 400;
            font-size: 0.8rem;
            padding: 0.1rem 0.6rem;
            border-radius: 12px;
            background: #dbeafe;
            color: #1d4ed8;
        }
        .question-item .q-content {
            margin: 0.5rem 0;
            line-height: 1.6;
        }
        .question-item .q-content img {
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }
        .question-item .q-options {
            margin-top: 0.5rem;
        }
        .question-item .q-options div {
            padding: 0.2rem 0;
        }
        .btn {
            background: #0b3b4c;
            color: #fff;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.15s;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { background: #0a2f3d; }
        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-danger {
            background: #b91c1c;
        }
        .btn-danger:hover { background: #991b1b; }
        .upload-section {
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 2rem 0;
            border: 2px dashed #d4a373;
            text-align: center;
        }
        .upload-section input[type="file"] {
            margin: 0.5rem 0;
        }
        .submission-list {
            background: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid #e9edf2;
        }
        .submission-list .item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9edf2;
        }
        .submission-list .item:last-child { border-bottom: none; }
        .back-link {
            display: inline-block;
            margin-top: 1rem;
            color: #2563eb;
            text-decoration: none;
        }
        .back-link:hover { text-decoration: underline; }
        .print-btn {
            background: #d4a373;
            color: #fff;
        }
        .print-btn:hover { background: #c08d5e; }
    </style>
</head>
<body>

<div class="header">
    <h1><?= htmlspecialchars($exam['title']) ?></h1>
    <div class="meta">
        <span><i class="far fa-clock"></i> 开始：<?= $exam['start_time'] ? date('Y-m-d H:i', strtotime($exam['start_time'])) : '未设置' ?></span>
        <span><i class="far fa-clock"></i> 结束：<?= $exam['end_time'] ? date('Y-m-d H:i', strtotime($exam['end_time'])) : '未设置' ?></span>
        <span><i class="fas fa-user-check"></i> 已报名并通过审核</span>
    </div>
    <p style="color:#64748b; margin-top:0.5rem;"><?= htmlspecialchars($exam['description'] ?? '') ?></p>
</div>

<h2><i class="fas fa-list"></i> 题目列表（共 <?= count($questions) ?> 题）</h2>

<?php if (empty($questions)): ?>
    <p style="color:#94a3b8;">本考试暂无题目，请等待老师出题。</p>
<?php else: ?>
    <?php foreach ($questions as $idx => $q): ?>
        <div class="question-item">
            <div class="q-header">
                <span>第 <?= $idx + 1 ?> 题 <span class="q-type"><?= ['single'=>'单选','multiple'=>'多选','fill'=>'填空','essay'=>'解答'][$q['type']] ?? $q['type'] ?></span></span>
                <span><?= $q['score'] ?> 分</span>
            </div>
            <div class="q-content"><?= $q['content'] ?></div>
            <?php if ($q['type'] === 'single' || $q['type'] === 'multiple'): ?>
                <div class="q-options">
                    <?php
                    $opts = json_decode($q['options'], true);
                    if ($opts) {
                        foreach ($opts as $opt) {
                            echo '<div>· ' . htmlspecialchars($opt) . '</div>';
                        }
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- 下载答题卡 -->
<div style="margin: 2rem 0;">
    <button class="btn print-btn" onclick="window.print()"><i class="fas fa-print"></i> 下载/打印答题卡</button>
    <span style="font-size:0.85rem; color:#64748b; margin-left:0.5rem;">点击后选择“另存为 PDF”或打印</span>
</div>

<!-- 上传答题卡 -->
<div class="upload-section">
    <h3><i class="fas fa-cloud-upload-alt"></i> 上传答题卡</h3>
    <p style="color:#64748b; font-size:0.9rem;">请将手写答案拍照或扫描后上传（支持 jpg/png/pdf）</p>
    <form id="uploadForm" enctype="multipart/form-data">
        <input type="file" name="answer_file" accept="image/*,.pdf" required>
        <br>
        <button type="submit" class="btn">上传</button>
    </form>
    <div id="uploadMsg" style="margin-top:0.5rem; font-weight:500;"></div>
</div>

<!-- 已上传的答题卡 -->
<div class="submission-list">
    <h3><i class="fas fa-history"></i> 已上传的答题卡</h3>
    <?php if (empty($submissions)): ?>
        <p style="color:#94a3b8;">暂无上传记录</p>
    <?php else: ?>
        <?php foreach ($submissions as $sub): ?>
            <div class="item">
                <span><i class="fas fa-file"></i> <?= htmlspecialchars($sub['file_name']) ?></span>
                <span>
                    <span style="color:#64748b; font-size:0.85rem;"><?= date('Y-m-d H:i', strtotime($sub['upload_time'])) ?></span>
                    <a href="<?= htmlspecialchars($sub['file_path']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding:0.1rem 0.8rem; font-size:0.8rem;">查看</a>
                </span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<a href="?page=exam" class="back-link">← 返回联考列表</a>

<script>
    const uploadForm = document.getElementById('uploadForm');
    const uploadMsg = document.getElementById('uploadMsg');

    uploadForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('action', 'upload_exam_answer');
        formData.append('exam_id', <?= $exam_id ?>);

        uploadMsg.textContent = '上传中...';
        uploadMsg.style.color = '#f59e0b';

        try {
            const res = await fetch('?action=upload_exam_answer', {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (data.code === 0) {
                uploadMsg.textContent = '✅ ' + data.message;
                uploadMsg.style.color = '#0b6b4c';
                // 刷新页面显示新上传的文件
                setTimeout(() => location.reload(), 1500);
            } else {
                uploadMsg.textContent = '❌ ' + (data.message || '上传失败');
                uploadMsg.style.color = '#b91c1c';
            }
        } catch (e) {
            uploadMsg.textContent = '网络错误，请重试';
            uploadMsg.style.color = '#b91c1c';
        }
    });
</script>

</body>
</html>
