<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";

// 只查询已发布联考 paper_type=1
$examList = $pdo->query("
    SELECT id,title,time_limit,full_score,download_paper,download_card
    FROM paper WHERE paper_type=1 AND is_publish=1 ORDER BY create_time DESC
")->fetchAll();
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
.card{background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:20px;box-shadow:0 1px 4px #eee;}
.btn{padding:8px 14px;border-radius:6px;font-size:14px;text-decoration:none;display:inline-block;}
.btn-blue{background:#0ea5e9;color:white;}
.btn-gray{border:1px solid #ddd;background:#fff;color:#333;}
body{background:#f4f6f8;}
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

    <div class="card mb-6">
        <p class="text-gray-600 text-sm">
            使用说明：下载试卷PDF打印做题 → 下载答题卡PDF手写填写，完成后可联系教师提交答题卡照片等待批改
        </p>
    </div>

    <?php if(empty($examList)): ?>
    <div class="card text-center text-gray-500 py-10">
        <i class="fa fa-folder-open-o text-4xl mb-3 text-gray-300"></i>
        <p>暂无上架的联考试卷</p>
    </div>
    <?php else: ?>
        <?php foreach ($examList as $item): ?>
        <div class="card mb-4">
            <h3 class="text-lg font-semibold mb-2"><?=htmlspecialchars($item['title'])?></h3>
            <p class="text-sm text-gray-500 mb-4">
                考试时长：<?=$item['time_limit']?> 分钟 &nbsp;|&nbsp; 总分：<?=$item['full_score']?> 分
            </p>
            <div class="flex flex-wrap gap-3">
                <?php if (!empty($item['download_paper'])): ?>
                    <a href="<?=htmlspecialchars($item['download_paper'])?>" download target="_blank" class="btn btn-blue">
                        <i class="fa fa-download mr-1"></i>下载试卷PDF
                    </a>
                <?php else: ?>
                    <span class="text-gray-400 py-2">试卷文件暂未上传</span>
                <?php endif; ?>

                <?php if (!empty($item['download_card'])): ?>
                    <a href="<?=htmlspecialchars($item['download_card'])?>" download target="_blank" class="btn btn-gray">
                        <i class="fa fa-file-text mr-1"></i>下载答题卡PDF
                    </a>
                <?php else: ?>
                    <span class="text-gray-400 py-2">答题卡暂未上传</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <footer class="mt-12 text-center text-sm text-gray-500">
        LunaticChO © 2026 化学竞赛平台
    </footer>
</div>
</body>
</html>
