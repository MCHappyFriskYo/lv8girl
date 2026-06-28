<?php
require_once 'gsk_config.php';

// 启动会话（用于存储登录状态）
session_start();

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['username'] ?? '');  // 前端传 username 字段（邮箱）
$password = trim($input['password'] ?? '');

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['code' => 40001, 'message' => '请填写邮箱和密码']);
    exit;
}

// 查询用户
$stmt = $pdo->prepare("SELECT * FROM gsk_users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['code' => 40001, 'message' => '邮箱或密码错误']);
    exit;
}

if ($user['status'] !== 'ACTIVE') {
    http_response_code(403);
    echo json_encode(['code' => 40300, 'message' => '账号未激活，请联系管理员']);
    exit;
}

// 登录成功，生成会话或JWT（此处使用Session）
$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];
$_SESSION['tenant_id'] = $user['tenant_id'];

// 生成模拟的access token（实际可生成JWT，此处简化）
$accessToken = session_id(); // 用session_id作为token

// 返回数据
echo json_encode([
    'code' => 0,
    'data' => [
        'accessToken' => $accessToken,
        'expiresIn' => 900,
        'user' => [
            'id' => $user['id'],
            'username' => $user['email'],
            'role' => $user['role'],
            'tenantId' => $user['tenant_id']
        ]
    ]
]);
?>