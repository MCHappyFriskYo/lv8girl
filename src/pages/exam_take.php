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
$stmt = $pdo->prepare("SELECT * FROM gsk_exams WHERE id = ? AND status = 'published'");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();
if (!$exam) {
    die('考试不存在或未发布');
}

// 检查时间（管理员可绕过）
$isAdmin = false;
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['ADMIN', 'TEACHER'])) {
    $isAdmin = true;
}
if (!$isAdmin) {
    $now = new DateTime();
    if ($exam['start_time'] && new DateTime($exam['start_time']) > $now) {
        die('考试尚未开始');
    }
    if ($exam['end_time'] && new DateTime($exam['end_time']) < $now) {
        die('考试已结束');
    }
}

// 获取题目
$stmt = $pdo->prepare("SELECT * FROM gsk_questions WHERE exam_id = ? ORDER BY sort_order, id");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll();

// 获取用户已提交的答题卡（用于显示）
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM gsk_exam_submissions WHERE exam_id = ? AND user_id = ?");
$stmt->execute([$exam_id, $user_id]);
$submission = $stmt->fetch();

// 处理答题卡上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['answer_sheet'])) {
    $file = $_FILES['answer_sheet'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!in_array($file['type'], $allowed)) {
            $upload_error = '仅支持 jpg/png/gif/pdf 格式';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $upload_error = '文件不能超过 5MB';
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $path = 'uploads/answer_sheets/' . $filename;
            if (!is_dir('uploads/answer_sheets')) {
                mkdir('uploads/answer_sheets', 0755, true);
            }
            if (move_uploaded_file($file['tmp_name'], $path)) {
                // 存入数据库
                $stmt = $pdo->prepare("INSERT INTO gsk_exam_submissions (exam_id, user_id, file_name, file_path, status) VALUES (?, ?, ?, ?, 'pending')");
                $stmt->execute([$exam_id, $user_id, $file['name'], $path]);
                $upload_success = '答题卡上传成功！';
                // 重新获取提交信息
                $stmt = $pdo->prepare("SELECT * FROM gsk_exam_submissions WHERE exam_id = ? AND user_id = ?");
                $stmt->execute([$exam_id, $user_id]);
                $submission = $stmt->fetch();
            } else {
                $upload_error = '文件保存失败';
            }
        }
    } else {
        $upload_error = '文件上传出错';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($exam['title']) ?> · 答题</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: #f6f8fa;
      color: #1e293b;
      padding: 2rem;
      max-width: 900px;
      margin: 0 auto;
    }
    .exam-header {
      background: #ffffff;
      padding: 1.5rem 2rem;
      border-radius: 12px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
      margin-bottom: 2rem;
      border: 1px solid #e9edf2;
    }
    .exam-header h1 { color: #0b3b4c; }
    .exam-header .meta { color: #64748b; font-size: 0.9rem; }
    .question-item {
      background: #ffffff;
      padding: 1.2rem 1.5rem;
      border-radius: 8px;
      margin-bottom: 1rem;
      border: 1px solid #e9edf2;
    }
    .question-item .q-header {
      display: flex;
      justify-content: space-between;
      font-weight: 600;
      color: #0b3b4c;
    }
    .question-item .q-type {
      font-weight: 400;
      font-size: 0.8rem;
      padding: 0.1rem 0.6rem;
      border-radius: 12px;
      background: #dbeafe;
      color: #1d4ed8;
    }
    .question-item .q-content { margin: 0.5rem 0; }
    .question-item .q-content img { max-width: 100%; }
    .upload-section {
      background: #ffffff;
      padding: 1.5rem 2rem;
      border-radius: 12px;
      border: 2px dashed #cbd5e1;
      margin: 2rem 0;
      text-align: center;
    }
    .upload-section form { display: flex; flex-direction: column; gap: 1rem; align-items: center; }
    .upload-section input[type="file"] { padding: 0.5rem; }
    .upload-section .btn {
      background: #0b3b4c;
      color: #fff;
      border: none;
      padding: 0.5rem 2rem;
      border-radius: 20px;
      cursor: pointer;
      font-size: 1rem;
      transition: background 0.15s;
    }
    .upload-section .btn:hover { background: #0a2f3d; }
    .btn-download {
      background: #d4a373;
      color: #fff;
      padding: 0.5rem 1.5rem;
      border-radius: 20px;
      text-decoration: none;
      display: inline-block;
      margin: 0.5rem 0;
    }
    .btn-download:hover { background: #c08d5e; }
    .status-msg { padding: 0.5rem; border-radius: 6px; margin: 0.5rem 0; }
    .success { background: #d1fae5; color: #0b6b4c; }
    .error { background: #fce4ec; color: #b91c1c; }
    .back-link { display: inline-block; margin-top: 1rem; color: #2563eb; text-decoration: none; }
    .back-link:hover { text-decoration: underline; }
  </style>
</head>
<body>
<div class="exam-header">
  <h1><?= htmlspecialchars($exam['title']) ?></h1>
  <div class="meta">
    <span>出卷老师：<?= htmlspecialchars($exam['teacher']) ?></span>
    <?php if ($exam['start_time']): ?>
      <span style="margin-left:1rem;">开始：<?= date('Y-m-d H:i', strtotime($exam['start_time'])) ?></span>
    <?php endif; ?>
    <?php if ($exam['end_time']): ?>
      <span style="margin-left:1rem;">结束：<?= date('Y-m-d H:i', strtotime($exam['end_time'])) ?></span>
    <?php endif; ?>
  </div>
</div>

<?php if ($questions): ?>
  <h2 style="color:#0b3b4c;">题目列表</h2>
  <?php foreach ($questions as $idx => $q): ?>
    <div class="question-item">
      <div class="q-header">
        <span>第 <?= $idx+1 ?> 题 <span class="q-type"><?= ['single'=>'单选','multiple'=>'多选','fill'=>'填空','essay'=>'解答'][$q['type']] ?></span></span>
        <span><?= $q['score'] ?> 分</span>
      </div>
      <div class="q-content"><?= $q['content'] ?></div>
      <?php if ($q['type'] === 'single' || $q['type'] === 'multiple'): ?>
        <div style="margin-top:0.5rem;">
          <?php
          $opts = json_decode($q['options'], true);
          foreach ($opts as $opt) {
            echo '<div>' . $opt . '</div>';
          }
          ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php else: ?>
  <p style="color:#94a3b8;">该考试暂无题目</p>
<?php endif; ?>

<!-- 答题卡下载与上传 -->
<div class="upload-section">
  <h3><i class="fas fa-download"></i> 下载答题卡模板</h3>
  <p style="color:#64748b; font-size:0.9rem;">点击下方按钮下载答题卡模板（PDF格式），打印后手写答案，拍照或扫描后上传。</p>
  <a href="download_template.php?exam_id=<?= $exam_id ?>" class="btn-download" target="_blank"><i class="fas fa-file-pdf"></i> 下载答题卡模板</a>
</div>

<div class="upload-section">
  <h3><i class="fas fa-upload"></i> 上传答题卡</h3>
  <?php if (isset($upload_success)): ?>
    <div class="status-msg success"><?= $upload_success ?></div>
  <?php elseif (isset($upload_error)): ?>
    <div class="status-msg error"><?= $upload_error ?></div>
  <?php endif; ?>
  <?php if ($submission): ?>
    <div style="background:#f0fdf4; padding:0.5rem; border-radius:6px; margin-bottom:1rem;">
      已上传：<?= htmlspecialchars($submission['file_name']) ?> （<?= $submission['status'] === 'graded' ? '已批改' : '待批改' ?>）
    </div>
  <?php else: ?>
    <form method="POST" enctype="multipart/form-data">
      <input type="file" name="answer_sheet" accept="image/*,.pdf" required>
      <button type="submit" class="btn">提交答题卡</button>
    </form>
  <?php endif; ?>
</div>

<a href="?page=exam" class="back-link">← 返回联考列表</a>

</body>
</html>
