<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";

// 未登录拦截
if (!isset($_SESSION['uid'])) {
    $_SESSION['msg'] = "请先登录账号";
    header("Location: index.php");
    exit;
}
$uid = $_SESSION['uid'];
$defaultPaperId = isset($_GET['pid']) ? intval($_GET['pid']) : 0;
$msg = '';

// 目录创建
$saveDir = "upload/card/";
if (!is_dir($saveDir)) mkdir($saveDir, 0755, true);

// 编辑已有记录id
$editId = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$editData = null;

// 如果是修改：读取原有5张图片数据
if ($editId > 0) {
    $check = $pdo->prepare("SELECT * FROM answer_record WHERE id=? AND uid=?");
    $check->execute([$editId, $uid]);
    $editData = $check->fetch();
}

// 提交保存（新增/修改）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = intval($_POST['paper_id']);
    $imgArr = [];
    $maxSize = 5 * 1024 * 1024;
    $allowMime = ['image/jpeg','image/png'];

    // 循环处理5个上传文件
    for ($i = 1; $i <= 5; $i++) {
        $key = "img".$i;
        $oldImg = trim($_POST['old_img'.$i] ?? '');
        // 没有新文件上传，沿用旧图片
        if ($_FILES[$key]['error'] === 4) {
            $imgArr[$i] = $oldImg;
            continue;
        }
        // 有新文件上传，校验并保存
        $file = $_FILES[$key];
        if ($file['size'] > $maxSize) {
            $msg = "第{$i}页图片超过5MB限制";
            break;
        }
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowMime)) {
            $msg = "第{$i}页仅支持jpg/png图片";
            break;
        }
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newName = md5(time().$uid.$i.rand()).".".$ext;
        $path = $saveDir.$newName;
        move_uploaded_file($file['tmp_name'], $path);
        $imgArr[$i] = $path;
    }

    if ($msg === '') {
        if ($editId > 0) {
            // 修改原有记录
            $up = $pdo->prepare("
                UPDATE answer_record SET paper_id=?,card_img1=?,card_img2=?,card_img3=?,card_img4=?,card_img5=?
                WHERE id=? AND uid=?
            ");
            $up->execute([
                $pid,$imgArr[1],$imgArr[2],$imgArr[3],$imgArr[4],$imgArr[5],
                $editId,$uid
            ]);
            $msg = "答题卡图片修改保存成功";
        } else {
            // 新建提交记录
            $ins = $pdo->prepare("
                INSERT INTO answer_record (uid,paper_id,card_img1,card_img2,card_img3,card_img4,card_img5,submit_time)
                VALUES (?,?,?,?,?,?,?,NOW())
            ");
            $ins->execute([
                $uid,$pid,$imgArr[1],$imgArr[2],$imgArr[3],$imgArr[4],$imgArr[5]
            ]);
            $msg = "答题卡5面图片上传完成，等待批改";
        }
    }
}

// 获取联考列表
$examList = $pdo->query("SELECT id,title FROM paper WHERE paper_type=1 AND is_publish=1 ORDER BY create_time DESC")->fetchAll();

// 我的全部提交记录
$myRecStmt = $pdo->prepare("
    SELECT ar.*,p.title FROM answer_record ar
    LEFT JOIN paper p ON ar.paper_id=p.id
    WHERE ar.uid=? AND p.paper_type=1 ORDER BY ar.submit_time DESC
");
$myRecStmt->execute([$uid]);
$myList = $myRecStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>答题卡上传与修改</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css">
<style>
.box{background:#fff;border:1px solid #ddd;border-radius:8px;padding:22px;box-shadow:0 1px 5px #eee;margin-bottom:20px;}
.btn{padding:9px 16px;border-radius:6px;border:none;cursor:pointer;text-decoration:inline-block;font-size:14px;}
.btn-orange{background:#f97316;color:#fff;}
.btn-gray{background:#eee;color:#333;}
.btn-green{background:#0d9488;color:#fff;}
.done-item{border-left:4px solid #0d9488;background:#f0fdfa;padding:12px;border-radius:0 6px 6px 0;margin:12px 0;}
.wait-item{border-left:4px solid #f59e0b;background:#fffbeb;padding:12px;border-radius:0 6px 6px 0;margin:12px 0;}
.img-preview{max-height:120px;border:1px solid #ccc;border-radius:4px;margin-top:6px;}
</style>
</head>
<body class="bg-[#f4f6f8] min-h-screen py-8 px-4">
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-[#0d9488]"><i class="fa fa-upload mr-2"></i>答题卡上传（共5面）</h1>
        <a href="exam_download.php" class="btn btn-gray">← 返回联考页面</a>
    </div>

    <?php if($msg): ?>
    <div class="p-3 rounded mb-4 <?=str_contains($msg,'成功')?'bg-green-100 text-green-700':'bg-red-100 text-red-600'?>">
        <?=htmlspecialchars($msg)?>
    </div>
    <?php endif; ?>

    <!-- 上传表单 -->
    <div class="box">
        <h2 class="text-lg font-semibold mb-4">
            <?= $editId>0 ? '修改已上传答题卡图片' : '上传答题卡5页图片' ?>
        </h2>
        <?php if(empty($examList)): ?>
            <p class="text-gray-500">暂无开放联考</p>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data">
            <div class="mb-5">
                <label class="block mb-2 font-medium">选择联考</label>
                <select name="paper_id" class="w-full border rounded p-2" required>
                    <?php foreach($examList as $ex): ?>
                    <option value="<?=$ex['id']?>"
                        <?= ($editData?$editData['paper_id']:$defaultPaperId)==$ex['id']?'selected':'' ?>>
                        <?=htmlspecialchars($ex['title'])?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <p class="text-sm text-orange-600 mb-4">答题卡总共5面，请逐页拍照上传；可重复选择文件替换原有图片</p>

            <?php for($i=1;$i<=5;$i++): ?>
            <?php $oldVal = $editData ? $editData['card_img'.$i] : ''; ?>
            <div class="mb-4 border-b pb-4">
                <label class="font-medium">第<?=$i?>面答题卡</label>
                <input type="hidden" name="old_img<?=$i?>" value="<?=htmlspecialchars($oldVal)?>">
                <input type="file" name="img<?=$i?>" accept="image/jpeg,image/png" class="w-full mt-2">
                <?php if(!empty($oldVal)): ?>
                    <p class="text-xs text-green-600 mt-1">已上传此页</p>
                    <img src="<?=$oldVal?>" class="img-preview">
                <?php else: ?>
                    <p class="text-xs text-gray-400 mt-1">暂未上传该页面</p>
                <?php endif; ?>
            </div>
            <?php endfor; ?>

            <button type="submit" class="btn btn-orange mt-2">保存全部图片</button>
            <?php if($editId>0): ?>
                <a href="upload_card.php" class="btn btn-gray ml-2">取消修改</a>
            <?php endif; ?>
        </form>
        <?php endif; ?>
    </div>

    <!-- 我的提交记录 -->
    <div class="box">
        <h2 class="text-lg font-semibold mb-4">我的提交记录（可点击修改）</h2>
        <?php if(empty($myList)): ?>
            <p class="text-gray-500">暂无上传记录</p>
        <?php else: ?>
            <?php foreach($myList as $row): ?>
            <?php $isDone = $row['score']!==null;
            $uploadNum = 0;
            for($i=1;$i<=5;$i++){
                if(!empty($row['card_img'.$i])) $uploadNum++;
            }
            ?>
            <div class="<?= $isDone ? 'done-item' : 'wait-item' ?>">
                <div class="flex justify-between">
                    <p class="font-medium"><?=htmlspecialchars($row['title'])?></p>
                    <a href="upload_card.php?edit=<?=$row['id']?>" class="btn btn-green text-xs">修改图片</a>
                </div>
                <p class="text-sm text-gray-500">提交时间：<?=$row['submit_time']?> | 已上传：<?=$uploadNum?>/5 页</p>

                <div class="flex flex-wrap gap-2 mt-3">
                    <?php for($i=1;$i<=5;$i++):
                        $src = $row['card_img'.$i];
                    ?>
                        <?php if(!empty($src)): ?>
                            <a href="<?=$src?>" target="_blank">
                                <img src="<?=$src?>" class="w-16 h-20 object-cover border rounded">
                            </a>
                        <?php else: ?>
                            <div class="w-16 h-20 bg-gray-200 flex items-center justify-center text-xs text-gray-400 rounded">空<?=$i?></div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>

                <?php if($isDone): ?>
                    <p class="text-green-600 font-medium mt-3">得分：<?=$row['score']?> 分</p>
                    <?php if(!empty($row['comment'])): ?>
                        <p>教师评语：<?=htmlspecialchars($row['comment'])?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-orange-500 mt-3">等待教师批改</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
