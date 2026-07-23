<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";

// 核心：未登录禁止访问本页面
if (!isset($_SESSION['uid'])) {
    $_SESSION['msg'] = "请先登录账号后查看联考内容";
    header("Location: index.php");
    exit;
}
$uid = $_SESSION['uid'];

// 查询所有已发布联考
$sql = "SELECT id, title, time_limit, full_score, download_paper, download_card 
        FROM paper 
        WHERE paper_type = 1 AND is_publish = 1 
        ORDER BY create_time DESC";
$examList = $pdo->query($sql)->fetchAll();

// 查询当前用户自己所有联考答题卡提交记录
$mySubmitRec = $pdo->prepare("
    SELECT ar.id, ar.submit_time, ar.card_img, ar.score, ar.comment, p.title
    FROM answer_record ar
    LEFT JOIN paper p ON ar.paper_id = p.id
    WHERE ar.uid = ? AND p.paper_type = 1
    ORDER BY ar.submit_time DESC
");
$mySubmitRec->execute([$uid]);
$myRecords = $mySubmitRec->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>联考试卷下载 | LunaticChO</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css">
<style>
.box{background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:20px;box-shadow:0 1px 4px #eee;}
.btn{padding:8px 14px;border-radius:6px;font-size:14px;text-decoration:none;display:inline-block;margin:4px 0;}
.btn-blue{background:#0ea5e9;color:#fff;}
.btn-gray{border:1px solid #ddd;background:#fff;color:#333;}
.btn-orange{background:#f97316;color:#fff;}
body{background:#f4f6f8;}
.record-done{border-left:4px solid #0d9488;background:#f0fdfa;padding:12px;margin-top:10px;border-radius:0 6px 6px 0;}
.record-wait{border-left:4px solid #f59e0b;background:#fffbeb;padding:12px;margin-top:10px;border-radius:0 6px 6px 0;}
</style>
</head>
<body class="min-h-screen py-8 px-4">
<div class="container max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-2xl font-bold text-[#0d9488]">
            <i class="fa fa-file-text-o mr-2"></i>联考试卷下载中心
        </h1>
        <a href="index.php" class="btn btn-gray">← 返回首页</a>
    </div>

    <div class="box mb-6">
        <p class="text-gray-600 text-sm">
            使用流程：下载试卷PDF打印做题 → 下载答题卡PDF手写填写 → 点击【上传答题卡】提交照片等待教师批改
        </p>
    </div>

    <?php if(empty($examList)): ?>
    <div class="box text-center text-gray-500 py-10">
        <i class="fa fa-folder-open-o text-4xl mb-3 text-gray-300"></i>
        <p>暂无上架的联考试卷</p>
    </div>
    <?php else: ?>
        <?php foreach ($examList as $item): ?>
        <div class="box mb-4">
            <h3 class="text-lg font-semibold mb-2"><?=htmlspecialchars($item['title'])?></h3>
            <p class="text-sm text-gray-500 mb-4">
                考试时长：<?=$item['time_limit']?> 分钟 &nbsp;|&nbsp; 总分：<?=$item['full_score']?> 分
            </p>
            <div class="flex flex-wrap gap-2">
                <?php 
                $paperFile = trim($item['download_paper']);
                if (!empty($paperFile)): 
                ?>
                    <a href="download.php?file=<?=urlencode($paperFile)?>" class="btn btn-blue">
                        <i class="fa fa-download mr-1"></i>下载试卷PDF
                    </a>
                <?php endif; ?>

                <?php 
                $cardFile = trim($item['download_card']);
                if (!empty($cardFile)): 
                ?>
                    <a href="download.php?file=<?=urlencode($cardFile)?>" class="btn btn-gray">
                        <i class="fa fa-file-text mr-1"></i>下载答题卡PDF
                    </a>
                <?php endif; ?>

                <!-- 上传答题卡按钮，跳转上传页面 -->
                <a href="upload_card.php?pid=<?=$item['id']?>" class="btn btn-orange">
                    <i class="fa fa-upload mr-1"></i>上传答题卡
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- 个人答题卡提交记录区域 -->
    <div class="box mt-8">
        <h2 class="text-lg font-semibold mb-4">我的联考提交记录</h2>
        <?php if(empty($myRecords)): ?>
            <p class="text-gray-500">你还没有上传过任何联考答题卡</p>
        <?php else: ?>
            <?php foreach ($myRecords as $rec): ?>
                <?php $isFinish = $rec['score'] !== null; ?>
                <div class="<?= $isFinish ? 'record-done' : 'record-wait' ?>">
                    <p class="font-medium"><?=htmlspecialchars($rec['title'])?></p>
                    <p class="text-sm text-gray-500">提交时间：<?=$rec['submit_time']?></p>
                    <?php if (!empty($rec['card_img'])): ?>
                        <p class="my-1">答题卡：<a href="<?=htmlspecialchars($rec['card_img'])?>" target="_blank" class="text-blue-600 underline">查看原图</a></p>
                    <?php endif; ?>
                    <?php if ($isFinish): ?>
                        <p class="text-green-600 font-medium">得分：<?=$rec['score']?> 分</p>
                        <?php if (!empty($rec['comment'])): ?>
                            <p>教师评语：<?=htmlspecialchars($rec['comment'])?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-orange-500">等待教师批改中</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <footer class="mt-12 text-center text-sm text-gray-500">
        LunaticChO © 2026 化学竞赛平台
    </footer>
</div>
</body>
</html>
