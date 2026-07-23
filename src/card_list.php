<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";

// 教师权限校验
if (!isset($_SESSION['uid']) || $_SESSION['role'] != 1) {
    $_SESSION['msg'] = "仅限教师访问";
    header("Location: index.php");
    exit;
}
$msg = '';

// 删除整条答题卡记录
if (isset($_GET['del'])) {
    $delId = intval($_GET['del']);
    // 删除数据库记录
    $pdo->prepare("DELETE FROM answer_record WHERE id=?")->execute([$delId]);
    $msg = "提交记录已彻底删除";
    header("Location: card_list.php");
    exit;
}

// 保存打分评语
if (isset($_POST['save_mark'])) {
    $arid = intval($_POST['arid']);
    $score = $_POST['score'] === '' ? null : intval($_POST['score']);
    $comment = trim($_POST['comment']);
    $pdo->prepare("UPDATE answer_record SET score=?,comment=? WHERE id=?")->execute([$score,$comment,$arid]);
    $msg = "批改已保存";
}

// 试卷筛选
$pid = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
$where = "p.paper_type=1";
$params = [];
if ($pid > 0) {
    $where .= " AND ar.paper_id=?";
    $params[] = $pid;
}

// 查询所有提交记录
$sql = "
SELECT ar.*,u.username,p.title
FROM answer_record ar
LEFT JOIN cho_user u ON ar.uid=u.id
LEFT JOIN paper p ON ar.paper_id=p.id
WHERE {$where} ORDER BY ar.submit_time DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

// 联考下拉筛选
$examList = $pdo->query("SELECT id,title FROM paper WHERE paper_type=1 AND is_publish=1 ORDER BY create_time DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>答题卡批改管理后台</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css">
<style>
.box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:16px;}
.btn{padding:7px 12px;border-radius:5px;font-size:13px;border:none;cursor:pointer;text-decoration:none;}
.btn-green{background:#0d9488;color:#fff;}
.btn-blue{background:#0ea5e9;color:#fff;}
.btn-gray{background:#eee;color:#333;}
.btn-red{background:#ef4444;color:#fff;}
.item-wait{background:#fffbeb;border:1px solid #fed7aa;border-radius:8px;padding:16px;margin:12px 0;}
.item-done{background:#f0fdfa;border:1px solid #bbf7d0;border-radius:8px;padding:16px;margin:12px 0;}
.thumb{width:90px;height:120px;object-fit:cover;border:1px solid #ccc;border-radius:4px;}
</style>
</head>
<body class="bg-gray-100 p-6">
<div class="max-w-5xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">联考答题卡批改管理</h1>
        <a href="admin.php" class="btn btn-gray">返回教师后台</a>
    </div>

    <?php if($msg): ?>
    <div class="p-3 bg-green-100 text-green-700 rounded mb-4"><?=htmlspecialchars($msg)?></div>
    <?php endif; ?>

    <!-- 筛选区域 -->
    <div class="box">
        <form method="get" class="flex flex-wrap gap-4 items-center">
            <span>筛选试卷：</span>
            <select name="pid" class="border rounded p-2">
                <option value="0">全部联考</option>
                <?php foreach($examList as $ex): ?>
                <option value="<?=$ex['id']?>" <?= $pid==$ex['id']?'selected':'' ?>>
                    <?=htmlspecialchars($ex['title'])?>
                </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-green">筛选</button>
            <a href="card_list.php" class="btn btn-gray">重置</a>
        </form>
    </div>

    <?php if(empty($list)): ?>
    <div class="box text-center py-10 text-gray-500">
        <i class="fa fa-file-image-o text-4xl text-gray-300 mb-3"></i>
        <p>暂无学生答题卡提交记录</p>
    </div>
    <?php else: ?>
        <?php foreach($list as $row): ?>
        <?php
        $isMark = $row['score']!==null;
        $uploadCount = 0;
        for($i=1;$i<=5;$i++){
            if(!empty($row['card_img'.$i])) $uploadCount++;
        }
        ?>
        <div class="<?= $isMark ? 'item-done' : 'item-wait' ?>">
            <div class="grid md:grid-cols-2 gap-6">
                <!-- 左侧信息+图片预览 -->
                <div>
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-bold text-lg"><?=htmlspecialchars($row['title'])?></p>
                            <p class="text-sm text-gray-600">学生：<?=htmlspecialchars($row['username'])?></p>
                            <p class="text-sm text-gray-600">提交时间：<?=$row['submit_time']?></p>
                            <p class="text-sm font-medium mt-1">已上传：<?=$uploadCount?> / 5 面</p>
                        </div>
                        <a href="?del=<?=$row['id']?>" onclick="return confirm('确定要删除这条提交记录？删除后无法恢复')" class="btn btn-red">删除记录</a>
                    </div>

                    <!-- 5张图片预览 -->
                    <div class="flex flex-wrap gap-2 mt-4">
                        <?php for($i=1;$i<=5;$i++):
                            $src = $row['card_img'.$i];
                        ?>
                            <?php if(!empty($src)): ?>
                                <div class="text-center">
                                    <a href="<?=$src?>" target="_blank">
                                        <img src="<?=$src?>" class="thumb">
                                    </a>
                                    <p class="text-xs mt-1">第<?=$i?>页</p>
                                </div>
                            <?php else: ?>
                                <div class="w-[90px] h-[120px] bg-gray-200 flex items-center justify-center rounded text-xs text-gray-400">无图片</div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">点击图片可打开大图查看，右键可保存下载</p>
                </div>

                <!-- 右侧打分区域 -->
                <div>
                    <p class="font-medium mb-3">批改打分</p>
                    <form method="post">
                        <input type="hidden" name="arid" value="<?=$row['id']?>">
                        <div class="flex items-center gap-2 mb-3">
                            <label>总分：</label>
                            <input type="number" name="score" value="<?=$row['score']??''?>" class="border w-20 p-1 rounded">
                        </div>
                        <textarea name="comment" rows="3" class="w-full border rounded p-2 mb-3" placeholder="填写评语"><?=htmlspecialchars($row['comment']??'')?></textarea>
                        <button name="save_mark" type="submit" class="btn btn-orange">保存批改</button>
                    </form>
                    <?php if($isMark): ?>
                        <p class="text-green-600 mt-2">✅ 已批改完毕</p>
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
