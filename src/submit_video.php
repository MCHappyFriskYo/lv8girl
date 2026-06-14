<?php
// submit_video.php - 视频上传投稿（面向用户）
session_start();
require_once 'config.php';

// 登录检测
if (!isset($_SESSION['handle'])) {
    header('Location: login.php');
    exit;
}

$userHandle = $_SESSION['handle'];
$userDisplayName = $_SESSION['display_name'] ?? $userHandle;

// 配置上传目录
define('VIDEO_UPLOAD_DIR', __DIR__ . '/uploads/videos/');
define('THUMB_UPLOAD_DIR', __DIR__ . '/uploads/thumbnails/');
define('MAX_FILE_SIZE', 500 * 1024 * 1024); // 500MB
$allowedVideoTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm'];
$allowedImageTypes = ['image/jpeg', 'image/png', 'image/webp'];

// 创建目录（如果不存在）
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

    // 验证标题
    if (empty($title)) {
        $error = '请填写视频标题';
    }
    // 验证视频文件
    elseif (!$uploadedVideo || $uploadedVideo['error'] !== UPLOAD_ERR_OK) {
        $error = '请选择视频文件';
    }
    elseif ($uploadedVideo['size'] > MAX_FILE_SIZE) {
        $error = '视频文件过大，不能超过500MB';
    }
    elseif (!in_array($uploadedVideo['type'], $allowedVideoTypes)) {
        $error = '仅支持 MP4、MOV、AVI、WebM 格式的视频';
    }
    else {
        // 处理视频文件
        $videoExt = pathinfo($uploadedVideo['name'], PATHINFO_EXTENSION);
        $videoName = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $videoExt;
        $videoPath = VIDEO_UPLOAD_DIR . $videoName;
        if (!move_uploaded_file($uploadedVideo['tmp_name'], $videoPath)) {
            $error = '视频保存失败，请检查目录权限';
        }
        else {
            // 处理封面图（用户上传或自动生成）
            $thumbnailPath = '';
            $coverUrl = ''; // 用于 cover_url 字段

            if ($uploadedThumb && $uploadedThumb['error'] === UPLOAD_ERR_OK && in_array($uploadedThumb['type'], $allowedImageTypes)) {
                $thumbExt = pathinfo($uploadedThumb['name'], PATHINFO_EXTENSION);
                $thumbName = uniqid() . '_thumb.' . $thumbExt;
                $thumbPath = THUMB_UPLOAD_DIR . $thumbName;
                if (move_uploaded_file($uploadedThumb['tmp_name'], $thumbPath)) {
                    $thumbnailPath = 'uploads/thumbnails/' . $thumbName;
                    $coverUrl = $thumbnailPath; // cover_url 也可以使用相同图片
                } else {
                    $error = '封面图保存失败';
                }
            } else {
                // 尝试用 FFmpeg 生成缩略图（第一帧）
                $ffmpeg = 'ffmpeg'; // 确保系统安装了 ffmpeg 且可执行
                $thumbName = uniqid() . '_frame.jpg';
                $thumbPath = THUMB_UPLOAD_DIR . $thumbName;
                $cmd = "$ffmpeg -i " . escapeshellarg($videoPath) . " -ss 00:00:01 -vframes 1 -f image2 " . escapeshellarg($thumbPath) . " 2>&1";
                exec($cmd, $output, $retval);
                if ($retval === 0 && file_exists($thumbPath)) {
                    $thumbnailPath = 'uploads/thumbnails/' . $thumbName;
                    $coverUrl = $thumbnailPath;
                } else {
                    // 无法生成缩略图，使用默认封面（外部图片）
                    $coverUrl = 'https://picsum.photos/id/20/300/180';
                    // 也可以留空，但 cover_url 字段需允许 NULL
                }
            }

            // 写入数据库（包含 cover_url 字段）
            $stmt = $pdo->prepare("INSERT INTO videos (title, description, category, up_name, file_path, thumbnail_path, cover_url, plays, danmu) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $result = $stmt->execute([
                $title,
                $description,
                $category,
                $userDisplayName,
                'uploads/videos/' . $videoName,
                $thumbnailPath,
                $coverUrl,
                '0',
                '0'
            ]);

            if ($result) {
                $success = '视频投稿成功！✨ 已提交审核/直接展示';
                // 可选：清空表单
                $_POST = [];
            } else {
                $error = '数据库写入失败，请稍后重试';
                // 清理已上传的文件
                @unlink($videoPath);
                if ($thumbnailPath && strpos($thumbnailPath, 'uploads/') === 0) @unlink(__DIR__ . '/' . $thumbnailPath);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>视频投稿 · lv8girl</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* 样式与之前相同，此处省略以保持简洁 */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(145deg, #e9f7e6, #d4ecd0); font-family: 'Segoe UI', system-ui; padding: 40px 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: #fff; border-radius: 32px; box-shadow: 0 20px 35px -12px rgba(46,139,86,0.25); overflow: hidden; }
        .card-header { padding: 28px 32px 16px; border-bottom: 1px solid #e2f3de; }
        .card-header h1 { font-size: 28px; color: #2e8b57; display: flex; align-items: center; gap: 12px; }
        .card-body { padding: 32px; }
        .form-group { margin-bottom: 24px; }
        label { font-weight: 600; color: #3a6345; display: block; margin-bottom: 8px; }
        label i { margin-right: 8px; color: #6fcf97; }
        input, select, textarea { width: 100%; padding: 12px 16px; border: 1px solid #e2f0df; border-radius: 28px; font-size: 14px; font-family: inherit; background: #fefefc; }
        input:focus, select:focus, textarea:focus { outline: none; border-color: #6fcf97; box-shadow: 0 0 0 3px rgba(111,207,151,0.2); }
        textarea { resize: vertical; min-height: 100px; }
        .file-hint { font-size: 12px; color: #9bc195; margin-top: 6px; margin-left: 12px; }
        .btn-submit { background: linear-gradient(95deg, #62cd82, #3aa35b); border: none; border-radius: 40px; padding: 12px 24px; color: white; font-weight: bold; font-size: 16px; cursor: pointer; width: 100%; transition: 0.2s; }
        .btn-submit:hover { transform: translateY(-2px); background: linear-gradient(95deg, #56bd74, #2e8b57); box-shadow: 0 8px 18px rgba(76,187,107,0.3); }
        .alert { padding: 12px 20px; border-radius: 40px; margin-bottom: 24px; }
        .alert-success { background: #e2f7e0; color: #2e8b57; border-left: 4px solid #62cd82; }
        .alert-error { background: #ffe7e0; color: #c55a3e; border-left: 4px solid #f3a18b; }
        .user-badge { background: #f2faf0; padding: 12px 20px; border-radius: 40px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; color: #3a6345; }
        .back-link { text-align: center; margin-top: 24px; }
        .back-link a { color: #8cbb82; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
        @media (max-width: 600px) { .card-body { padding: 24px; } }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1><i class="fas fa-cloud-upload-alt"></i> 投稿视频</h1>
            <p style="color: #8cb885; margin-top: 8px;">分享你喜欢的少女向视频，让更多人看见 ✨</p>
        </div>
        <div class="card-body">
            <div class="user-badge">
                <i class="fas fa-user-astronaut"></i>
                <span>投稿者：<strong><?= htmlspecialchars($userDisplayName) ?></strong> (<?= htmlspecialchars($userHandle) ?>)</span>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
            <?php elseif ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label><i class="fas fa-heading"></i> 视频标题 *</label>
                    <input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" placeholder="例：樱花JK · 漫步校园 春日限定vlog">
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
                    <label><i class="fas fa-video"></i> 视频文件 * (MP4 / MOV / AVI / WebM)</label>
                    <input type="file" name="video_file" accept="video/mp4,video/quicktime,video/x-msvideo,video/webm" required>
                    <div class="file-hint">最大 500MB，建议使用 H.264 编码的 MP4</div>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-image"></i> 封面图片 (选填)</label>
                    <input type="file" name="thumbnail_file" accept="image/jpeg,image/png,image/webp">
                    <div class="file-hint">若不提供，将从视频第一帧自动提取封面（需服务器支持 FFmpeg）</div>
                </div>
                <button type="submit" class="btn-submit"><i class="fas fa-paper-plane"></i> 发布视频</button>
            </form>
        </div>
    </div>
    <div class="back-link">
        <a href="index.php"><i class="fas fa-arrow-left"></i> 返回首页</a>
    </div>
</div>
</body>
</html>
