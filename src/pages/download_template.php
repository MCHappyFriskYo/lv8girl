<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die('请先登录');
}
$exam_id = intval($_GET['exam_id'] ?? 0);
if (!$exam_id) {
    die('缺少考试ID');
}
// 获取题目
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
$stmt = $pdo->prepare("SELECT * FROM gsk_questions WHERE exam_id = ? ORDER BY sort_order, id");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>答题卡模板</title>
<style>
  body { font-family: 'Times New Roman', serif; padding: 2cm; }
  .header { text-align: center; margin-bottom: 2rem; }
  .header h1 { font-size: 24pt; }
  .question { margin-bottom: 1.5rem; }
  .question .q-title { font-weight: bold; }
  .answer-area { border-bottom: 1px solid #333; height: 3rem; margin-top: 0.5rem; }
  .footer { text-align: center; margin-top: 3rem; font-size: 10pt; color: #666; }
  @media print { .no-print { display: none; } }
</style>
</head>
<body>
<div class="header">
  <h1>答题卡</h1>
  <p>考试名称：___________  姓名：___________  学号：___________</p>
</div>
<?php foreach ($questions as $idx => $q): ?>
  <div class="question">
    <div class="q-title"><?= ($idx+1) ?>. <?= strip_tags($q['content']) ?> （<?= $q['score'] ?>分）</div>
    <?php if ($q['type'] === 'single'): ?>
      <div>选项：A ( )  B ( )  C ( )  D ( )</div>
    <?php elseif ($q['type'] === 'multiple'): ?>
      <div>选项：A ( )  B ( )  C ( )  D ( )  E ( )</div>
    <?php elseif ($q['type'] === 'fill'): ?>
      <div>填空：_____________</div>
    <?php else: ?>
      <div>解答区域：</div>
      <div class="answer-area" style="height:6rem;"></div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<div class="footer">—— 请将答案填写在对应区域 ——</div>
<div class="no-print" style="margin-top:2rem;text-align:center;">
  <button onclick="window.print()">打印/另存为PDF</button>
  <a href="exam_take.php?exam_id=<?= $exam_id ?>">返回</a>
</div>
</body>
</html>
