<?php
ini_set('upload_max_filesize', '500M');
ini_set('post_max_size', '500M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
// submit_video.php - 视频投稿（自动生成 LB 号）
session_start();
require_once 'config.php';

if (!isset($_SESSION['handle'])) {
    header('Location: login.php');
    exit;
}

$userHandle = $_SESSION['handle'];
$userDisplayName = $_SESSION['display_name'] ?? $userHandle;

define('VIDEO_UPLOAD_DIR', __DIR__ . '/uploads/videos/');
define('THUMB_UPLOAD_DIR', __DIR__ . '/uploads/thumbnails/');
define('MAX_FILE_SIZE', 500 * 1024 * 1024);
$allowedVideoTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'];
$allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];

if (!is_dir(VIDEO_UPLOAD_DIR)) mkdir(VIDEO_UPLOAD_DIR, 0777, true);
if (!is_dir(THUMB_UPLOAD_DIR)) mkdir(THUMB_UPLOAD_DIR, 0777, true);

$success = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '其他');
    $uploadedVideo = $_FILES['video_file'] ?? null;
    $uploadedThumb = $_FILES['thumbnail_file'] ?? null;

    if (empty($title)) {
        $error = '请填写视频标题';
    } elseif (!$uploadedVideo || $uploadedVideo['error'] !== UPLOAD_ERR_OK) {
        $error = '请选择视频文件';
    } elseif ($uploadedVideo['size'] > MAX_FILE_SIZE) {
        $error = '视频文件过大，不能超过500MB';
    } elseif (!in_array($uploadedVideo['type'], $allowedVideoTypes)) {
        $error = '仅支持 MP4、MOV、AVI、WebM 格式的视频';
    } else {
        $videoExt = pathinfo($uploadedVideo['name'], PATHINFO_EXTENSION);
        $videoName = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $videoExt;
        $videoPath = VIDEO_UPLOAD_DIR . $videoName;
        if (!move_uploaded_file($uploadedVideo['tmp_name'], $videoPath)) {
            $error = '视频保存失败，请检查目录权限';
        } else {
            $thumbnailPath = '';
            $coverUrl = '';
            if ($uploadedThumb && $uploadedThumb['error'] === UPLOAD_ERR_OK && in_array($uploadedThumb['type'], $allowedImageTypes)) {
                $thumbExt = pathinfo($uploadedThumb['name'], PATHINFO_EXTENSION);
                $thumbName = uniqid() . '_thumb.' . $thumbExt;
                $thumbPath = THUMB_UPLOAD_DIR . $thumbName;
                if (move_uploaded_file($uploadedThumb['tmp_name'], $thumbPath)) {
                    $thumbnailPath = 'uploads/thumbnails/' . $thumbName;
                    $coverUrl = $thumbnailPath;
                } else {
                    $error = '封面图保存失败';
                }
            } else {
                // 尝试用 FFmpeg 生成缩略图
                $ffmpeg = 'ffmpeg';
                $thumbName = uniqid() . '_frame.jpg';
                $thumbPath = THUMB_UPLOAD_DIR . $thumbName;
                $cmd = "$ffmpeg -i " . escapeshellarg($videoPath) . " -ss 00:00:01 -vframes 1 -f image2 " . escapeshellarg($thumbPath) . " 2>&1";
                exec($cmd, $output, $retval);
                if ($retval === 0 && file_exists($thumbPath)) {
                    $thumbnailPath = 'uploads/thumbnails/' . $thumbName;
                    $coverUrl = $thumbnailPath;
                } else {
                    $coverUrl = 'https://picsum.photos/id/20/300/180';
                }
            }

            if (!$error) {
                // 生成 LB 号
                $lb = generateLB($pdo);
                $stmt = $pdo->prepare("INSERT INTO videos (lb, title, description, category, up_name, file_path, thumbnail_path, cover_url, plays, danmu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([
                    $lb, $title, $description, $category, $userDisplayName,
                    'uploads/videos/' . $videoName, $thumbnailPath, $coverUrl, '0', '0'
                ]);
                if ($result) {
                    $success = '视频投稿成功！✨ 视频 LB 号为 ' . $lb;
                    $_POST = [];
                } else {
                    $error = '数据库写入失败，请稍后重试';
                    @unlink($videoPath);
                    if ($thumbnailPath && strpos($thumbnailPath, 'uploads/') === 0) @unlink(__DIR__ . '/' . $thumbnailPath);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>视频投稿 · lv8girl 少女世界</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(145deg, #eaf7e4 0%, #d3e8cc 100%); font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; color: #1c2c1a; padding: 0 0 40px; }
        .navbar { background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(100, 150, 90, 0.2); padding: 12px 0; position: sticky; top: 0; z-index: 100; }
        .nav-container { max-width: 1000px; margin: 0 auto; padding: 0 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
        .logo-area { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo-icon { background: linear-gradient(135deg, #9be29b, #50b36e); width: 38px; height: 38px; border-radius: 16px; display: flex; align-items: center; justify-content: center; }
        .logo-icon i { font-size: 22px; color: white; }
        .logo-text { font-size: 24px; font-weight: 800; background: linear-gradient(135deg, #5acd73, #2e7d4c); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .back-home { background: rgba(100, 150, 90, 0.15); padding: 8px 18px; border-radius: 40px; color: #3a874b; text-decoration: none; font-weight: 500; }
        .container { max-width: 1000px; margin: 28px auto 0; padding: 0 28px; display: grid; grid-template-columns: 1fr 300px; gap: 32px; }
        .form-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border-radius: 32px; padding: 28px; border: 1px solid rgba(100, 150, 90, 0.25); }
        .form-title { font-size: 24px; font-weight: 700; color: #2a5233; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
        .form-sub { color: #7b9f72; margin-bottom: 28px; font-size: 14px; border-bottom: 1px solid rgba(100,150,90,0.2); padding-bottom: 16px; }
        .form-group { margin-bottom: 24px; }
        label { font-weight: 600; color: #3a6345; display: block; margin-bottom: 8px; font-size: 14px; }
        label i { margin-right: 8px; color: #6fcf97; }
        input, select, textarea { width: 100%; padding: 12px 16px; background: rgba(240,248,235,0.8); border: 1px solid rgba(100,150,90,0.4); border-radius: 28px; font-size: 14px; font-family: inherit; transition: 0.2s; outline: none; }
        input:focus, select:focus, textarea:focus { border-color: #6fcf97; background: white; box-shadow: 0 0 0 3px rgba(111,207,151,0.2); }
        .file-input { padding: 8px; background: rgba(240,248,235,0.6); border: 1px dashed #a3d69e; }
        .btn-submit { background: linear-gradient(95deg, #69d588, #3fa359); border: none; border-radius: 60px; padding: 14px 24px; color: white; font-weight: 700; font-size: 16px; cursor: pointer; width: 100%; transition: 0.2s; margin-top: 8px; }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(70,150,70,0.3); }
        .alert { padding: 12px 20px; border-radius: 40px; margin-bottom: 24px; font-size: 14px; }
        .alert-success { background: #e2f7e0; color: #2e8b57; border-left: 4px solid #62cd82; }
        .alert-error { background: #ffe7e0; color: #c55a3e; border-left: 4px solid #f3a18b; }
        .tips-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border-radius: 32px; padding: 24px; border: 1px solid rgba(100,150,90,0.25); margin-bottom: 24px; }
        .tips-title { font-size: 18px; font-weight: 700; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; border-left: 4px solid #7bc87b; padding-left: 14px; color: #2a5233; }
        .tips-list { list-style: none; margin-top: 12px; }
        .tips-list li { margin-bottom: 12px; font-size: 13px; color: #44633b; display: flex; gap: 10px; }
        @media (max-width: 800px) { .container { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="navbar">
    <div class="nav-container">
        <a href="index.php" class="logo-area">
            <div class="logo-icon"><i class="fas fa-leaf"></i></div>
            <div class="logo-text">lv8girl</div>
        </a>
        <a href="index.php" class="back-home"><i class="fas fa-arrow-left"></i> 返回首页</a>
    </div>
</div>

<div class="container">
    <div class="form-card">
        <div class="form-title"><i class="fas fa-cloud-upload-alt" style="color: #5acd73;"></i> 投稿视频</div>
        <div class="form-sub">分享你喜欢的少女向作品，让更多人发现美好 ✨</div>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label><i class="fas fa-heading"></i> 视频标题 *</label>
                <input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="例如：樱花JK · 漫步校园 春日限定vlog">
            </div>
            <div class="form-group">
                <label><i class="fas fa-align-left"></i> 视频简介</label>
                <textarea name="description" placeholder="简单介绍一下这个视频吧～"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label><i class="fas fa-tags"></i> 分类</label>
                <select name="category">
                    <option value="舞蹈" <?= ($_POST['category'] ?? '') == '舞蹈' ? 'selected' : '' ?>>💃 舞蹈</option>
                    <option value="萌宠" <?= ($_POST['category'] ?? '') == '萌宠' ? 'selected' : '' ?>>🐱 萌宠</option>
                    <option value="洛丽塔" <?= ($_POST['category'] ?? '') == '洛丽塔' ? 'selected' : '' ?>>🎀 洛丽塔</option>
                    <option value="JK日常" <?= ($_POST['category'] ?? '') == 'JK日常' ? 'selected' : '' ?>>👗 JK日常</option>
                    <option value="仿妆" <?= ($_POST['category'] ?? '') == '仿妆' ? 'selected' : '' ?>>💄 仿妆</option>
                    <option value="vlog" <?= ($_POST['category'] ?? '') == 'vlog' ? 'selected' : '' ?>>📹 vlog</option>
                    <option value="其他" <?= (!isset($_POST['category']) || $_POST['category'] == '其他') ? 'selected' : '' ?>>✨ 其他</option>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-video"></i> 视频文件 *</label>
                <input type="file" name="video_file" accept="video/mp4,video/quicktime,video/x-msvideo,video/webm" required class="file-input">
                <div class="file-hint" style="font-size:12px; color:#8bb282; margin-top:6px;">支持 MP4、MOV、AVI、WebM，最大 500MB</div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-image"></i> 封面图片（选填）</label>
                <input type="file" name="thumbnail_file" accept="image/jpeg,image/png,image/webp" class="file-input" id="thumbInput">
                <div class="file-hint" style="font-size:12px; color:#8bb282;">若不提供将自动从视频第一帧提取（需服务器支持 FFmpeg）</div>
                <div id="thumbPreview" style="display: none; margin-top: 12px;"><img id="previewImg" style="max-width: 100%; max-height: 150px; border-radius: 16px;"></div>
            </div>
            <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> 发布视频</button>
        </form>
    </div>
    <div>
        <div class="tips-card">
            <div class="tips-title"><i class="fas fa-info-circle"></i> 投稿须知</div>
            <ul class="tips-list">
                <li><i class="fas fa-check-circle" style="color:#6fcf97;"></i> 请勿上传违规或侵权内容</li>
                <li><i class="fas fa-check-circle" style="color:#6fcf97;"></i> 视频将展示在「为你推荐」和「全站少女榜」</li>
                <li><i class="fas fa-check-circle" style="color:#6fcf97;"></i> 支持常见视频格式，建议使用 H.264 编码的 MP4</li>
                <li><i class="fas fa-check-circle" style="color:#6fcf97;"></i> 每个视频会自动生成唯一的 LB 号（类似 B 站 BV 号）</li>
            </ul>
        </div>
    </div>
</div>
<script>
    document.getElementById('thumbInput')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('previewImg').src = ev.target.result;
                document.getElementById('thumbPreview').style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            document.getElementById('thumbPreview').style.display = 'none';
        }
    });
    document.querySelector('form')?.addEventListener('submit', function(e) {
        const videoFile = document.querySelector('input[name="video_file"]').files[0];
        if (videoFile && videoFile.size > 500 * 1024 * 1024) {
            e.preventDefault();
            alert('视频文件不能超过 500MB');
        }
    });
</script>
</body>
</html>
