<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['code' => 40100, 'message' => '未登录']);
    exit;
}
require_once 'gsk_config.php';
$stmt = $pdo->prepare("SELECT id, email, real_name, qq, role FROM gsk_users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(404);
    echo json_encode(['code' => 40400, 'message' => '用户不存在']);
    exit;
}
echo json_encode(['code' => 0, 'data' => $user]);
?>