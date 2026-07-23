<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";

// 教师权限校验
if (!isset($_SESSION['uid']) || $_SESSION['role'] != 1) {
    $_SESSION['msg'] = "仅限教师账号访问";
    header("Location: index.php");
    exit;
}
$msg = '';

// 保存批改分数评语
if (isset($_POST['save_mark'])) {
    $arid = intval($_POST['arid']);
    $score = $_POST['score'] === '' ? null : intval($_POST['score']);
    $comment = trim($_POST['comment']);
    $up = $pdo->prepare("UPDATE answer_record SET score=?, comment=? WHERE id=?");
    $up->execute([$score, $comment, $arid]);
    $msg = "批改信息保存成功";
}

// 筛选条件
$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
$whereSql = "p.paper_type = 1";
$params = [];
if ($pid > 0) {
    $whereSql .= " AND ar.paper_id = ?";
    $params[] = $pid;
}

// 查询所有答题卡提交记录
$sql = "
SELECT ar.id, ar.submit_time, ar.card_img, ar.score, ar.comment,
u.username, p.title, p.id as pid
FROM answer_record ar
LEFT JOIN cho_user u ON ar.uid = u.id
LEFT JOIN paper p ON ar.paper_id = p.id
WHERE {$whereSql}
ORDER BY ar.submit_time DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allCards = $stmt->fetchAll();

// 获取所有联考下拉筛选
$examList = $pdo->query("SELECT id,title FROM paper WHERE paper_type=1 AND is_publish=1 ORDER BY create_time DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>答题卡管理后台</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css">
<style>
.box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;}
.btn{padding:7px 12px;border-radius:5px;font-size:13px;border:none;cursor:pointer;}
.btn-green{background:#0d9488;color:#fff;}
.btn-blue{background:#0ea5e9;color:#fff;}
.btn-gray{background:#eee;color:#333;}
.btn-orange{background:#f97316;color:#fff;}
.row-item{border:1px solid #eee; border-radius:6px; padding:16px; margin:12px 0;}
.wait-box{bg:#fffbeb;}
.done-box{bg:#f0fdfa;}
</style>
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">联考答题卡统一管理</h1>
        <div class="flex gap-3">
            <a href="admin.php" class="btn btn-gray">返回教师后台首页</a>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="p-3 bg-green-100 text-green-700 rounded mb-4"><?=htmlspecialchars($msg)?></div>
    <?php endif; ?>

    <!-- 筛选栏 -->
    <div class="box">
        <form method="get" class="flex flex-wrap gap-4 items-center">
            <span>筛选试卷：</span>
            <select name="pid" class="border rounded p-2">
                <option value="0">全部联考</option>
                <?php foreach ($examList as $ex): ?>
                <option value="<?=$ex['id']?>" <?= $pid==$ex['id']?'selected':'' ?>>
                    <?=htmlspecialchars($ex['title'])?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-green">筛选查看</button>
            <a href="card_list.php" class="btn btn-gray">重置筛选</a>
        </form>
    </div>

    <!-- 答题卡列表 -->
    <?php if(empty($allCards)): ?>
    <div class="box text-center py-10 text-gray-500">
        <i class="fa fa-file-image-o text-4xl text-gray-300 mb-3"></i>
        <p>暂无学生上传答题卡记录</p>
    </div>
    <?php else: ?>
        <?php foreach ($allCards as $item): ?>
        <?php $isMarked = $item['score'] !== null; ?>
        <div class="row-item <?= $isMarked ? 'done-box' : 'wait-box' ?>">
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <p class="font-medium text-lg"><?=htmlspecialchars($item['title'])?></p>
                    <p class="text-sm text-gray-600">学生：<?=htmlspecialchars($item['username'])?></p>
                    <p class="text-sm text-gray-600">提交时间：<?=$item['submit_time']?></p>
                    <div class="flex gap-2 mt-3">
                        <a href="<?=htmlspecialchars($item['card_img'])?>" target="_blank" class="btn btn-blue">
                            <i class="fa fa-eye"></i> 预览原图
                        </a>
                        <a href="<?=htmlspecialchars($item['card_img'])?>" download class="btn btn-gray">
                            <i class="fa fa-download"></i> 下载答题卡图片
                        </a>
                    </div>
                </div>
                <div>
                    <p class="font-medium mb-2">批改打分区域</p>
                    <form method="post">
                        <input type="hidden" name="arid" value="<?=$item['id']?>">
                        <div class="flex gap-2 items-center mb-2">
                            <label>得分：</label>
                            <input type="number" name="score" value="<?=$item['score'] ?? ''?>" class="border w-20 p-1 rounded">
                        </div>
                        <div class="mb-3">
                            <label class="text-sm">教师评语：</label>
                            <textarea name="comment" rows="2" class="w-full border rounded p-2"><?=htmlspecialchars($item['comment'] ?? '')?></textarea>
                        </div>
                        <button name="save_mark" type="submit" class="btn btn-orange">保存批改结果</button>
                    </form>
                    <?php if ($isMarked): ?>
                        <p class="text-green-600 mt-2">✅ 已完成批改</p>
                    <?php else: ?>
                        <p class="text-orange-500 mt-2">⏳ 待批改</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>
