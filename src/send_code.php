<?php
require_once 'gsk_config.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['code' => 40001, 'message' => '邮箱格式无效']);
    exit;
}

// 检查60秒内是否已发送（防刷）
$stmt = $pdo->prepare("SELECT created_at FROM gsk_codes WHERE email = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$email]);
$last = $stmt->fetch();
if ($last) {
    $lastTime = strtotime($last['created_at']);
    if (time() - $lastTime < 60) {
        http_response_code(429);
        echo json_encode(['code' => 42900, 'message' => '请求过于频繁，请稍后再试']);
        exit;
    }
}

// 生成6位数字验证码
$code = sprintf("%06d", mt_rand(0, 999999));
$expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));

// 存入数据库
$stmt = $pdo->prepare("INSERT INTO gsk_codes (email, code, expires_at) VALUES (?, ?, ?)");
$stmt->execute([$email, $code, $expires]);

// 此处可调用邮件发送函数，本例仅打印日志
error_log("验证码 $code 已发送至 $email");

// 返回统一成功消息（不暴露是否注册）
echo json_encode([
    'code' => 0,
    'message' => '若该邮箱有效，验证码已发送，5分钟内有效'
]);
?>