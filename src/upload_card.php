<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";

// 未登录跳转首页
if (!isset($_SESSION['uid'])) {
    $_SESSION['msg'] = "请先登录账号";
    header("Location: index.php");
    exit;
}
$uid = $_SESSION['uid'];
$username = $_SESSION['username'];
$msg = '';

// 创建存储目录
$saveDir = "upload/card/";
if (!is_dir($saveDir)) mkdir($saveDir, 0755, true);

// 处理文件上传提交
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paper_id'], $_FILES['card_file'])) {
    $pid = intval($_POST['paper_id']);
    $file = $_FILES['card_file'];

    // 校验文件
    if ($file['error'] !== 0) {
        $msg = "文件上传出错，请重新选择";
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $msg = "图片大小不能超过5MB";
    } else {
        $mime = mime_content_type($file['tmp_name']);
        $allowMime = ['image/jpeg', 'image/png'];
        if (!in_array($mime, $allowMime)) {
            $msg = "仅支持 JPG、PNG 图片格式";
        } else {
            // 生成唯一文件名
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = md5(time() . $uid . rand(100,999)) . "." . $ext;
            $savePath = $saveDir . $newName;

            move_uploaded_file($file['tmp_name'], $savePath);

            // 插入提交记录
            $ins = $pdo->prepare("
                INSERT INTO answer_record (uid, paper_id, card_img, submit_time)
                VALUES (?, ?, ?, NOW())
            ");
            $ins->execute([$uid, $pid, $savePath]);
            $msg = "答题卡上传成功！等待教师批改打分";
        }
    }
}

// 获取全部已发布联考
$examList = $pdo->query("
    SELECT id,title,time_limit,full_score FROM paper
    WHERE paper_type=1 AND is_publish=1 ORDER BY create_time DESC
")->fetchAll();

// 获取本人所有上传记录
$myRecords = $pdo->prepare("
    SELECT ar.*, p.title
    FROM answer_record ar
    LEFT JOIN paper p ON ar.paper_id = p.id
    WHERE ar.uid = ? AND p.paper_type=1
    ORDER BY ar.submit_time DESC
");
$myRecords->execute([$uid]);
$myList = $myRecords->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>上传联考答题卡</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css">
<style>
.card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:22px;box-shadow:0 1px 5px #eee;}
.btn{padding:9px 16px;border-radius:6px;border:none;cursor:pointer;}
.btn-orange{background:#f97316;color:#fff;}
.btn-gray{background:#eee;color:#333;text-decoration:none;display:inline-block;}
.done{border-left:4px solid #0d9488;background:#f0fdfa; padding:12px; border-radius:0 6px 6px 0; margin:10px 0;}
.wait{border-left:4px solid #f59e0b;background:#fffbeb; padding:12px; border-radius:0 6px 6px 0; margin:10px 0;}
</style>
</head>
<body class="bg-[#f4f6f8] min-h-screen py-8 px-4">
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-[#0d9488]"><i class="fa fa-upload mr-2"></i>联考答题卡上传</h1>
        <a href="index.php" class="btn btn-gray">返回首页</a>
    </div>

    <?php if ($msg): ?>
    <div class="mb-4 p-3 rounded <?=str_contains($msg,'成功')?'bg-green-100 text-green-700':'bg-red-100 text-red-600'?>">
        <?=htmlspecialchars($msg)?>
    </div>
    <?php endif; ?>

    <!-- 上传表单区域 -->
    <div class="card mb-8">
        <h2 class="text-lg font-semibold mb-4">提交新答题卡</h2>
        <?php if(empty($examList)): ?>
            <p class="text-gray-500">暂无开放的联考，暂时无法上传</p>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data">
            <div class="mb-4">
                <label class="block mb-2 text-sm font-medium">选择对应联考</label>
                <select name="paper_id" class="w-full border border-gray-300 rounded p-2" required>
                    <?php foreach ($examList as $item): ?>
                    <option value="<?=$item['id']?>"><?=htmlspecialchars($item['title'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-5">
                <label class="block mb-2 text-sm font-medium">答题卡照片</label>
                <input type="file" name="card_file" accept="image/jpeg,image/png" required class="w-full">
                <p class="text-xs text-gray-500 mt-1">要求：清晰拍摄整张答题卡，大小≤5MB</p>
            </div>
            <button type="submit" class="btn btn-orange">确认上传提交</button>
        </form>
        <?php endif; ?>
    </div>

    <!-- 个人提交记录 -->
    <div class="card">
        <h2 class="text-lg font-semibold mb-4">我的提交记录</h2>
        <?php if(empty($myList)): ?>
            <p class="text-gray-500">你还没有上传过答题卡</p>
        <?php else: ?>
            <?php foreach ($myList as $row): ?>
                <?php $isFinish = $row['score'] !== null; ?>
                <div class="<?= $isFinish ? 'done' : 'wait' ?>">
                    <p class="font-medium"><?=htmlspecialchars($row['title'])?></p>
                    <p class="text-sm text-gray-500">提交时间：<?=$row['submit_time']?></p>
                    <p class="my-1">
                        答题卡：
                        <a href="<?=htmlspecialchars($row['card_img'])?>" target="_blank" class="text-blue-600 underline">点击查看原图</a>
                    </p>
                    <?php if ($isFinish): ?>
                        <p class="text-green-600 font-medium">得分：<?=$row['score']?> 分</p>
                        <?php if(!empty($row['comment'])): ?>
                            <p>教师评语：<?=htmlspecialchars($row['comment'])?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-orange-500">等待教师批改中</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
