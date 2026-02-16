<?php
// 设置响应头为JSON
header('Content-Type: application/json');

// 数据库配置
$host = 'db';
$dbname = 'lv8girl';
$username = 'lv8girl';               // 数据库用户名
$password = 'yourpasswd';        // 数据库密码（已修改）

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    exit;
}

// 只接受POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '请求方式错误']);
    exit;
}

// 获取POST数据
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// 验证输入
$errors = [];
if (empty($username)) {
    $errors[] = '用户名不能为空';
} elseif (strlen($username) < 3 || strlen($username) > 20) {
    $errors[] = '用户名长度必须在3-20个字符之间';
}

if (empty($email)) {
    $errors[] = '邮箱不能为空';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '邮箱格式不正确';
}

if (empty($password)) {
    $errors[] = '密码不能为空';
} elseif (strlen($password) < 6) {
    $errors[] = '密码长度至少为6位';
}

if ($password !== $confirm_password) {
    $errors[] = '两次输入的密码不一致';
}

// 如果验证失败，返回错误
if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('；', $errors)]);
    exit;
}

// 检查用户名或邮箱是否已存在
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '用户名或邮箱已被注册']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '查询失败，请稍后重试']);
    exit;
}

// 加密密码
$password_hash = password_hash($password, PASSWORD_DEFAULT);

// 插入用户
try {
    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $password_hash]);
    echo json_encode(['success' => true, 'message' => '注册成功！请登录']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '注册失败，请稍后重试']);
}