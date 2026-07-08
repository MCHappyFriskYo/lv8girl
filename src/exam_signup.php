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

$stmt = $pdo->prepare("SELECT title FROM gsk_exams WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();
if (!$exam) {
    die('考试不存在');
}

$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LunaticChO · 报名</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: #f6f8fa;
      color: #1e293b;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .container {
      max-width: 520px;
      width: 100%;
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.06);
      padding: 2rem;
      border: 1px solid #e9edf2;
    }
    .container h1 {
      font-size: 1.6rem;
      color: #0b3b4c;
      margin-bottom: 0.2rem;
    }
    .container .subtitle {
      color: #64748b;
      margin-bottom: 1.5rem;
      font-size: 0.95rem;
    }
    .form-group {
      margin-bottom: 1rem;
    }
    .form-group label {
      display: block;
      font-weight: 500;
      font-size: 0.9rem;
      color: #334155;
      margin-bottom: 0.2rem;
    }
    .form-group input {
      width: 100%;
      padding: 0.6rem 0.8rem;
      border: 1px solid #d1d9e6;
      border-radius: 6px;
      font-size: 0.95rem;
      background: #f1f5f9;
      transition: border-color 0.15s;
    }
    .form-group input:focus {
      outline: none;
      border-color: #0b3b4c;
      box-shadow: 0 0 0 3px rgba(11,59,76,0.1);
    }
    .form-group input:read-only {
      background: #f1f5f9;
      color: #1e293b;
      cursor: default;
    }
    .btn-submit {
      width: 100%;
      padding: 0.7rem;
      background: #0b3b4c;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s;
    }
    .btn-submit:hover { background: #0a2f3d; }
    .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
    .message {
      margin-top: 1rem;
      padding: 0.6rem 1rem;
      border-radius: 6px;
      display: none;
    }
    .message.success {
      background: #d1fae5;
      color: #0b6b4c;
      display: block;
    }
    .message.error {
      background: #fce4ec;
      color: #b91c1c;
      display: block;
    }
    .back-link {
      display: inline-block;
      margin-top: 1rem;
      color: #2563eb;
      text-decoration: none;
    }
    .back-link:hover { text-decoration: underline; }
    .info-text {
      color: #64748b;
      font-size: 0.9rem;
      margin-bottom: 1rem;
    }
  </style>
</head>
<body>
<div class="container">
  <h1><i class="fas fa-pencil-alt" style="color:#d4a373;"></i> 联考报名</h1>
  <div class="subtitle"><?= htmlspecialchars($exam['title']) ?></div>
  <div class="info-text">报名信息将使用您的账户信息，无需额外填写。</div>
  <form id="signupForm">
    <div class="form-group">
      <label>用户名（报名人）</label>
      <input type="text" id="username" value="<?= htmlspecialchars($username) ?>" readonly>
    </div>
    <button type="submit" class="btn-submit">提交报名</button>
    <div id="message" class="message"></div>
    <a href="?page=exam" class="back-link">← 返回联考列表</a>
  </form>
</div>

<script>
  const form = document.getElementById('signupForm');
  const msgDiv = document.getElementById('message');
  const submitBtn = form.querySelector('.btn-submit');

  form.addEventListener('submit', async function(e) {
    e.preventDefault();

    submitBtn.disabled = true;
    submitBtn.textContent = '提交中...';

    try {
      const res = await fetch('?action=submit_signup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          exam_id: <?= $exam_id ?>
        })
      });
      const data = await res.json();
      if (data.code === 0) {
        msgDiv.className = 'message success';
        msgDiv.textContent = '✅ ' + data.message + '，请等待审核。';
        submitBtn.disabled = true;
        submitBtn.textContent = '已提交';
      } else {
        msgDiv.className = 'message error';
        msgDiv.textContent = '❌ ' + (data.message || '提交失败');
        submitBtn.disabled = false;
        submitBtn.textContent = '提交报名';
      }
    } catch (e) {
      msgDiv.className = 'message error';
      msgDiv.textContent = '网络错误，请重试';
      submitBtn.disabled = false;
      submitBtn.textContent = '提交报名';
    }
  });
</script>
</body>
</html>
