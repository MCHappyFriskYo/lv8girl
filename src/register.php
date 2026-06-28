<?php
require_once 'gsk_config.php';

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');
$password = trim($input['password'] ?? '');
$realName = trim($input['realName'] ?? '');
$inviteCode = trim($input['tenantInviteCode'] ?? '');
$role = trim($input['role'] ?? 'MARKER');
$qq = trim($input['qq'] ?? '');

// 基本校验
if (!$email || !$code || !$password || !$realName || !$inviteCode) {
    http_response_code(400);
    echo json_encode(['code' => 40001, 'message' => '请完整填写所有必填字段']);
    exit;
}

// 邮箱格式
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['code' => 40001, 'message' => '邮箱格式无效']);
    exit;
}

// 密码强度（至少8位，含大小写/数字/特殊字符中三种）
$strong = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)|(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9])|(?=.*[a-z])(?=.*\d)(?=.*[^a-zA-Z0-9])|(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9])/', $password);
if (strlen($password) < 8 || !$strong) {
    http_response_code(400);
    echo json_encode(['code' => 40001, 'message' => '密码至少8位，且包含大小写字母、数字、特殊字符中的至少三种']);
    exit;
}

// 校验验证码
$stmt = $pdo->prepare("SELECT code, expires_at FROM gsk_codes WHERE email = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$email]);
$row = $stmt->fetch();
if (!$row || $row['code'] !== $code || strtotime($row['expires_at']) < time()) {
    http_response_code(400);
    echo json_encode(['code' => 40001, 'message' => '验证码错误或已过期']);
    exit;
}

// 检查邮箱是否已被注册
$stmt = $pdo->prepare("SELECT id FROM gsk_users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['code' => 40900, 'message' => '该邮箱已注册']);
    exit;
}

// 校验邀请码
$stmt = $pdo->prepare("SELECT * FROM gsk_invite_codes WHERE code = ? AND (expires_at IS NULL OR expires_at > NOW()) AND used_count < max_uses");
$stmt->execute([$inviteCode]);
$invite = $stmt->fetch();
if (!$invite) {
    http_response_code(400);
    echo json_encode(['code' => 40002, 'message' => '邀请码无效或已失效']);
    exit;
}

// 更新邀请码使用次数
$stmt = $pdo->prepare("UPDATE gsk_invite_codes SET used_count = used_count + 1 WHERE id = ?");
$stmt->execute([$invite['id']]);

// 密码哈希
$hashed = password_hash($password, PASSWORD_DEFAULT);

// 插入用户
$stmt = $pdo->prepare("INSERT INTO gsk_users (email, password, real_name, qq, role, tenant_id, status) VALUES (?, ?, ?, ?, ?, ?, 'PENDING')");
$stmt->execute([$email, $hashed, $realName, $qq, $role, $invite['tenant_id']]);
$userId = $pdo->lastInsertId();

// 删除已使用的验证码（可清理）
$stmt = $pdo->prepare("DELETE FROM gsk_codes WHERE email = ?");
$stmt->execute([$email]);

echo json_encode([
    'code' => 0,
    'message' => '注册成功，请等待管理员审核激活账号',
    'data' => ['userId' => $userId]
]);
?>