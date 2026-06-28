<?php

// 关闭错误显示，但记录到日志
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 启用输出缓冲，防止意外输出
ob_start();

// ========== 数据库配置 ==========
function gsk_config() {
    $host = 'db';
    $dbname = 'lv8girl';
    $db_user = 'lv8girl';
    $db_pass = 'yourpasswd';   // 请修改为实际密码
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        // 返回 JSON 错误，并终止
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 50000, 'message' => '数据库连接失败：' . $e->getMessage()]);
        exit;
    }
}

// ========== 跨域与响应头 ==========
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ========== 如果请求包含 action 参数，则处理 API ==========
if (isset($_REQUEST['action'])) {
    $action = $_REQUEST['action'];
    try {
        $pdo = gsk_config();
        session_start();

        // ---------- 登录 ----------
        if ($action === 'login') {
            $input = json_decode(file_get_contents('php://input'), true);
            $email = trim($input['username'] ?? '');
            $password = trim($input['password'] ?? '');
            if (!$email || !$password) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '请填写邮箱和密码']);
                exit;
            }
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
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['tenant_id'] = $user['tenant_id'];
            echo json_encode([
                'code' => 0,
                'data' => [
                    'accessToken' => session_id(),
                    'expiresIn' => 900,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['email'],
                        'role' => $user['role'],
                        'tenantId' => $user['tenant_id']
                    ]
                ]
            ]);
            exit;
        }

        // ---------- 发送验证码 ----------
        if ($action === 'send_code') {
            $input = json_decode(file_get_contents('php://input'), true);
            $email = trim($input['email'] ?? '');
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '邮箱格式无效']);
                exit;
            }
            // 60秒限制
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
            $code = sprintf("%06d", mt_rand(0, 999999));
            $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            $stmt = $pdo->prepare("INSERT INTO gsk_codes (email, code, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $code, $expires]);
            // 记录日志（可替换为邮件发送）
            error_log("验证码 $code 发送至 $email");
            echo json_encode(['code' => 0, 'message' => '若该邮箱有效，验证码已发送，5分钟内有效']);
            exit;
        }

        // ---------- 注册 ----------
        if ($action === 'register') {
            $input = json_decode(file_get_contents('php://input'), true);
            $email = trim($input['email'] ?? '');
            $code = trim($input['code'] ?? '');
            $password = trim($input['password'] ?? '');
            $realName = trim($input['realName'] ?? '');
            $inviteCode = trim($input['tenantInviteCode'] ?? '');
            $role = trim($input['role'] ?? 'MARKER');
            $qq = trim($input['qq'] ?? '');
            if (!$email || !$code || !$password || !$realName || !$inviteCode) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '请完整填写所有必填字段']);
                exit;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '邮箱格式无效']);
                exit;
            }
            $strong = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)|(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9])|(?=.*[a-z])(?=.*\d)(?=.*[^a-zA-Z0-9])|(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9])/', $password);
            if (strlen($password) < 8 || !$strong) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '密码至少8位，且包含大小写字母、数字、特殊字符中的至少三种']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT code, expires_at FROM gsk_codes WHERE email = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            if (!$row || $row['code'] !== $code || strtotime($row['expires_at']) < time()) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '验证码错误或已过期']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id FROM gsk_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['code' => 40900, 'message' => '该邮箱已注册']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT * FROM gsk_invite_codes WHERE code = ? AND (expires_at IS NULL OR expires_at > NOW()) AND used_count < max_uses");
            $stmt->execute([$inviteCode]);
            $invite = $stmt->fetch();
            if (!$invite) {
                http_response_code(400);
                echo json_encode(['code' => 40002, 'message' => '邀请码无效或已失效']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE gsk_invite_codes SET used_count = used_count + 1 WHERE id = ?");
            $stmt->execute([$invite['id']]);
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO gsk_users (email, password, real_name, qq, role, tenant_id, status) VALUES (?, ?, ?, ?, ?, ?, 'PENDING')");
            $stmt->execute([$email, $hashed, $realName, $qq, $role, $invite['tenant_id']]);
            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare("DELETE FROM gsk_codes WHERE email = ?");
            $stmt->execute([$email]);
            echo json_encode([
                'code' => 0,
                'message' => '注册成功，请等待管理员审核激活账号',
                'data' => ['userId' => $userId]
            ]);
            exit;
        }

        // ---------- 获取当前用户 ----------
        if ($action === 'get_user') {
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['code' => 40100, 'message' => '未登录']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id, email, real_name, qq, role FROM gsk_users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if (!$user) {
                http_response_code(404);
                echo json_encode(['code' => 40400, 'message' => '用户不存在']);
                exit;
            }
            echo json_encode(['code' => 0, 'data' => $user]);
            exit;
        }

        // ---------- 登出 ----------
        if ($action === 'logout') {
            session_destroy();
            echo json_encode(['code' => 0, 'message' => '已登出']);
            exit;
        }

        // 未知 action
        http_response_code(400);
        echo json_encode(['code' => 40001, 'message' => '无效的操作']);
        exit;

    } catch (Exception $e) {
        // 捕获所有异常，返回 JSON 错误
        http_response_code(500);
        echo json_encode(['code' => 50000, 'message' => '服务器内部错误：' . $e->getMessage()]);
        exit;
    }
}

// =================================================================
// 没有 action 参数，输出 HTML 界面
// =================================================================

// 清空之前的输出缓冲，确保只输出 HTML
ob_end_clean();

// 重置响应头为 HTML
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>GSKChem · 联考平台</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    /* ===== 样式与之前版本相同，请复制您的样式 ===== */
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: #f6f8fa;
      color: #1e293b;
      line-height: 1.5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background-image: 
        linear-gradient(rgba(11, 59, 76, 0.02) 1px, transparent 1px),
        linear-gradient(90deg, rgba(11, 59, 76, 0.02) 1px, transparent 1px);
      background-size: 40px 40px;
    }
    a { text-decoration: none; color: inherit; }

    .navbar {
      background: #0b3b4c;
      padding: 0 2.5rem;
      height: 64px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 2px solid #d4a373;
      position: sticky;
      top: 0;
      z-index: 100;
    }
    .navbar .brand {
      font-size: 1.4rem;
      font-weight: 700;
      color: #f0e6d3;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .navbar .brand i {
      color: #d4a373;
      font-size: 1.6rem;
    }
    .nav-links {
      display: flex;
      list-style: none;
      gap: 2rem;
      align-items: center;
    }
    .nav-links li a {
      color: #cbd5e1;
      font-weight: 500;
      font-size: 0.95rem;
      padding: 0.3rem 0;
      border-bottom: 2px solid transparent;
      transition: border-color 0.2s, color 0.2s;
    }
    .nav-links li a:hover,
    .nav-links li a.active {
      color: #ffffff;
      border-bottom-color: #d4a373;
    }
    .hamburger {
      display: none;
      flex-direction: column;
      gap: 4px;
      cursor: pointer;
      background: none;
      border: none;
      padding: 4px;
    }
    .hamburger span {
      display: block;
      width: 26px;
      height: 2px;
      background: #cbd5e1;
      border-radius: 2px;
    }

    .container {
      max-width: 1140px;
      margin: 0 auto;
      padding: 2rem 1.5rem;
      flex: 1;
      width: 100%;
    }
    .page { display: none; }
    .page.active { display: block; }

    .card {
      background: #ffffff;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.04);
      padding: 1.8rem 2rem;
      margin-bottom: 2rem;
      border: 1px solid #e9edf2;
      transition: box-shadow 0.2s;
    }
    .card:hover {
      box-shadow: 0 8px 24px rgba(0,0,0,0.06);
    }
    .card-title {
      font-size: 1.3rem;
      font-weight: 600;
      margin-bottom: 1rem;
      color: #0b3b4c;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .card-title i {
      color: #d4a373;
      width: 1.6rem;
      text-align: center;
    }

    .hero {
      background: linear-gradient(145deg, #ffffff, #f9fafb);
      border-radius: 12px;
      padding: 2.5rem 2.5rem 2rem;
      margin-bottom: 2rem;
      border: 1px solid #e9edf2;
      text-align: center;
    }
    .hero-logo {
      display: inline-block;
      width: 100px;
      height: 100px;
      border-radius: 50%;
      background: #f0f4f8;
      border: 3px solid #d4a373;
      box-shadow: 0 4px 12px rgba(0,0,0,0.06);
      margin-bottom: 1rem;
      line-height: 100px;
      text-align: center;
      font-size: 2.8rem;
      color: #0b3b4c;
      transition: transform 0.2s;
    }
    .hero-logo:hover {
      transform: scale(1.02);
    }
    .hero-logo i {
      color: #0b3b4c;
    }
    .hero-logo img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
      display: block;
    }
    .hero h1 {
      font-size: 2.6rem;
      font-weight: 700;
      color: #0b3b4c;
      letter-spacing: -0.5px;
    }
    .hero h1 i {
      color: #d4a373;
      margin-right: 8px;
    }
    .hero p {
      font-size: 1.15rem;
      color: #475569;
      max-width: 600px;
      margin: 0.4rem auto 0;
    }
    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 1.5rem;
      margin-top: 1.8rem;
    }
    .feature-item {
      background: #f8fafc;
      padding: 1.5rem 0.8rem;
      border-radius: 8px;
      text-align: center;
      border: 1px solid #e9edf2;
      transition: transform 0.15s;
    }
    .feature-item:hover {
      transform: translateY(-2px);
    }
    .feature-item i {
      font-size: 2rem;
      color: #0b3b4c;
      margin-bottom: 0.5rem;
      display: block;
    }
    .feature-item h4 {
      font-weight: 600;
      color: #0b3b4c;
    }
    .feature-item p {
      color: #64748b;
      font-size: 0.9rem;
      margin-top: 0.2rem;
    }

    .exam-list {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 1rem;
      margin: 1rem 0 1.5rem;
    }
    .exam-card {
      background: #f8fafc;
      padding: 1rem 1.2rem;
      border-radius: 6px;
      border-left: 4px solid #94a3b8;
      cursor: pointer;
      transition: background 0.15s, border-color 0.15s;
    }
    .exam-card:hover { background: #f1f5f9; }
    .exam-card.selected {
      border-left-color: #d4a373;
      background: #fefcf7;
    }
    .exam-card h4 { font-weight: 600; font-size: 1rem; color: #0b3b4c; }
    .exam-card .meta { color: #64748b; font-size: 0.85rem; margin-top: 0.2rem; }

    .upload-area {
      border: 2px dashed #cbd5e1;
      border-radius: 8px;
      padding: 1.8rem 1rem;
      text-align: center;
      background: #fafcff;
      cursor: pointer;
      margin: 0.8rem 0 1rem;
      transition: border-color 0.2s, background 0.2s;
    }
    .upload-area:hover { border-color: #0b3b4c; background: #f0f4f8; }
    .upload-area.dragover { border-color: #d4a373; background: #fefcf7; }
    .upload-area i { font-size: 2.4rem; color: #94a3b8; }
    .upload-area p { font-weight: 500; color: #1e293b; }
    .upload-area .hint { font-size: 0.85rem; color: #94a3b8; }

    .auth-tabs {
      display: flex;
      border-bottom: 2px solid #e9edf2;
      margin-bottom: 1.5rem;
    }
    .auth-tabs button {
      flex: 1;
      padding: 0.6rem 0;
      border: none;
      background: transparent;
      font-size: 1rem;
      font-weight: 600;
      color: #64748b;
      cursor: pointer;
      border-bottom: 2px solid transparent;
      transition: 0.15s;
    }
    .auth-tabs button.active {
      color: #0b3b4c;
      border-bottom-color: #d4a373;
    }
    .auth-tabs button:hover { color: #0b3b4c; }

    .auth-form {
      display: none;
      flex-direction: column;
      gap: 1rem;
      max-width: 400px;
      margin: 0 auto;
    }
    .auth-form.active { display: flex; }
    .auth-form label {
      font-weight: 500;
      font-size: 0.9rem;
      color: #334155;
    }
    .auth-form .input-group {
      display: flex;
      align-items: center;
      background: #f1f5f9;
      border-radius: 6px;
      padding: 0 0.8rem;
      border: 1px solid #e2e8f0;
      transition: border-color 0.15s;
    }
    .auth-form .input-group:focus-within {
      border-color: #0b3b4c;
      background: #ffffff;
    }
    .auth-form .input-group i {
      color: #94a3b8;
      font-size: 0.95rem;
      margin-right: 8px;
    }
    .auth-form .input-group input {
      width: 100%;
      padding: 0.6rem 0;
      border: none;
      background: transparent;
      font-size: 0.95rem;
      outline: none;
      color: #0f172a;
    }
    .auth-form .btn-primary {
      background: #0b3b4c;
      color: white;
      border: none;
      padding: 0.7rem 0;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s;
    }
    .auth-form .btn-primary:hover { background: #0a2f3d; }
    .auth-form .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
    .auth-form .form-error {
      color: #b91c1c;
      font-size: 0.85rem;
      background: #fef2f2;
      padding: 0.4rem 0.8rem;
      border-radius: 4px;
      display: none;
    }
    .auth-form .form-success {
      color: #0b6b4c;
      font-size: 0.85rem;
      background: #f0fdf4;
      padding: 0.4rem 0.8rem;
      border-radius: 4px;
      display: none;
    }
    .code-row {
      display: flex;
      gap: 0.6rem;
      align-items: center;
    }
    .code-row .input-group { flex: 1; }
    .code-row .btn-code {
      padding: 0.6rem 1.2rem;
      border: none;
      border-radius: 6px;
      background: #d4a373;
      color: #fff;
      font-weight: 500;
      cursor: pointer;
      white-space: nowrap;
      font-size: 0.9rem;
      transition: background 0.15s;
    }
    .code-row .btn-code:hover { background: #c08d5e; }

    .user-profile {
      display: none;
      text-align: center;
      padding: 1rem 0;
    }
    .user-profile.active { display: block; }
    .user-profile .avatar {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      background: #dbeafe;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 0.6rem;
      font-size: 2.2rem;
      color: #0b3b4c;
    }
    .user-profile .user-email { font-size: 1.1rem; font-weight: 600; color: #0b3b4c; }
    .user-profile .user-qq { color: #64748b; font-size: 0.9rem; }
    .user-profile .btn-logout {
      margin-top: 0.8rem;
      background: #e2e8f0;
      color: #1e293b;
      border: none;
      padding: 0.4rem 1.8rem;
      border-radius: 20px;
      font-weight: 500;
      cursor: pointer;
      font-size: 0.9rem;
      transition: background 0.15s;
    }
    .user-profile .btn-logout:hover { background: #cbd5e1; }

    .museum-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 1rem;
      margin: 1rem 0;
    }
    .museum-item {
      background: #f8fafc;
      padding: 1.2rem 0.6rem;
      border-radius: 6px;
      text-align: center;
      border: 1px solid #e9edf2;
      transition: background 0.15s;
    }
    .museum-item:hover { background: #f1f5f9; }
    .museum-item i { font-size: 2rem; color: #0b3b4c; margin-bottom: 0.3rem; }
    .museum-item h4 { font-weight: 600; font-size: 0.95rem; color: #0b3b4c; }
    .museum-item p { color: #64748b; font-size: 0.8rem; }

    .footer {
      background: #0b3b4c;
      color: #94a3b8;
      text-align: center;
      padding: 1.2rem 1rem;
      font-size: 0.85rem;
      border-top: 2px solid #d4a373;
      margin-top: 1.5rem;
    }
    .footer span { color: #d4a373; }

    .toast {
      position: fixed;
      bottom: 24px;
      right: 24px;
      padding: 0.8rem 1.6rem;
      border-radius: 8px;
      background: #0b3b4c;
      color: #fff;
      font-weight: 500;
      font-size: 0.9rem;
      box-shadow: 0 4px 16px rgba(0,0,0,0.12);
      opacity: 0;
      transform: translateY(16px);
      transition: 0.25s ease;
      z-index: 9999;
      max-width: 360px;
    }
    .toast.show { opacity: 1; transform: translateY(0); }
    .toast.success { background: #0b6b4c; }
    .toast.error { background: #b91c1c; }

    @media (max-width: 820px) {
      .navbar { padding: 0 1.5rem; }
      .features-grid { grid-template-columns: 1fr 1fr; }
      .hero-logo { width: 80px; height: 80px; line-height: 80px; font-size: 2.2rem; }
    }
    @media (max-width: 640px) {
      .hamburger { display: flex; }
      .nav-links {
        position: absolute;
        top: 64px;
        left: 0;
        right: 0;
        background: #0b3b4c;
        flex-direction: column;
        padding: 0.8rem 0;
        gap: 0;
        display: none;
        border-top: 1px solid #1e4c5e;
      }
      .nav-links.open { display: flex; }
      .nav-links li { width: 100%; text-align: center; }
      .nav-links li a { padding: 0.6rem 0; border-bottom: none; }
      .nav-links li a:hover { background: #1e4c5e; color: #fff; }
      .features-grid { grid-template-columns: 1fr; }
      .exam-list { grid-template-columns: 1fr; }
      .museum-grid { grid-template-columns: 1fr 1fr; }
      .container { padding: 1rem 0.8rem; }
      .card { padding: 1.2rem; }
      .hero { padding: 1.8rem 1.2rem; }
      .hero h1 { font-size: 2rem; }
      .hero-logo { width: 72px; height: 72px; line-height: 72px; font-size: 2rem; }
    }
    @media (max-width: 400px) {
      .museum-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <!-- 导航 -->
  <nav class="navbar">
    <div class="brand"><i class="fas fa-flask"></i> GSKChem</div>
    <button class="hamburger" id="hamburger"><span></span><span></span><span></span></button>
    <ul class="nav-links" id="navLinks">
      <li><a href="#" class="active" data-page="home">主页</a></li>
      <li><a href="#" data-page="exam">联考</a></li>
      <li><a href="#" data-page="museum">博物志</a></li>
      <li><a href="#" data-page="account">账号</a></li>
    </ul>
  </nav>

  <div class="container">
    <!-- 主页 -->
    <section class="page active" id="page-home">
      <div class="hero">
        <div class="hero-logo"><i class="fas fa-flask"></i></div>
        <h1><i class="fas fa-flask"></i>GSKChem 联考平台</h1>
        <p>化学学科联考 · 答题卡收集 · 数据驱动教学</p>
      </div>
      <div class="card" style="border-top: 4px solid #d4a373;">
        <div class="card-title"><i class="fas fa-flag"></i>平台简介</div>
        <p style="color:#334155;">GSKChem 为化学联考提供从试卷发布、答题卡扫描上传到成绩统计的全流程支持。考生通过邮箱注册，可随时上传答题卡，教师端统一收集，高效便捷。</p>
      </div>
      <div class="card">
        <div class="card-title"><i class="fas fa-star"></i>核心功能</div>
        <div class="features-grid">
          <div class="feature-item"><i class="fas fa-envelope"></i><h4>邮箱注册</h4><p>验证码注册，快速安全</p></div>
          <div class="feature-item"><i class="fas fa-upload"></i><h4>答题卡上传</h4><p>支持拖拽/点击，图片格式</p></div>
          <div class="feature-item"><i class="fas fa-chart-bar"></i><h4>数据管理</h4><p>自动归档，随时查阅</p></div>
        </div>
      </div>
    </section>

    <!-- 联考 -->
    <section class="page" id="page-exam">
      <div class="card">
        <div class="card-title"><i class="fas fa-pencil-alt"></i>进行中的联考</div>
        <div id="examListContainer">
          <div class="exam-list" id="examList"></div>
        </div>
        <div style="margin-top:1.5rem; border-top:1px solid #e9edf2; padding-top:1.2rem;">
          <h4 style="font-weight:600; margin-bottom:0.5rem; color:#0b3b4c;">
            <i class="fas fa-cloud-upload-alt" style="color:#d4a373;"></i> 上传答题卡
          </h4>
          <p style="color:#475569; font-size:0.9rem; margin-bottom:0.5rem;">选择上方考试后，上传图片（jpg/png）</p>
          <div class="upload-area" id="uploadArea">
            <i class="fas fa-file-image"></i>
            <p><strong>点击或拖拽上传</strong></p>
            <span class="hint">最大 5MB</span>
            <input type="file" id="fileInput" accept="image/*" style="display:none;" />
          </div>
          <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
            <button class="btn-primary" id="uploadBtn" style="padding:0.5rem 2rem; border:none; border-radius:6px; background:#0b3b4c; color:#fff; font-weight:600; cursor:pointer;">提交</button>
            <span id="uploadStatus" style="font-size:0.9rem; color:#64748b;"></span>
          </div>
        </div>
        <div style="margin-top:2rem; border-top:1px solid #e9edf2; padding-top:1.2rem;">
          <h4 style="font-weight:600; margin-bottom:0.6rem; color:#0b3b4c;"><i class="fas fa-list-ul"></i> 我的上传记录</h4>
          <div id="submissionList"><p style="color:#94a3b8; font-size:0.9rem;">暂无记录</p></div>
        </div>
      </div>
    </section>

    <!-- 博物志 -->
    <section class="page" id="page-museum">
      <div class="card">
        <div class="card-title"><i class="fas fa-book-open"></i>燕石博物志</div>
        <p style="color:#475569; margin-bottom:1rem;">化学知识库，持续收录中。</p>
        <div class="museum-grid">
          <div class="museum-item"><i class="fas fa-atom"></i><h4>元素周期表</h4><p>118种元素</p></div>
          <div class="museum-item"><i class="fas fa-bezier-curve"></i><h4>分子结构</h4><p>三维模型</p></div>
          <div class="museum-item"><i class="fas fa-fire"></i><h4>化学反应</h4><p>燃烧与催化</p></div>
          <div class="museum-item"><i class="fas fa-flask"></i><h4>实验仪器</h4><p>常用工具</p></div>
          <div class="museum-item"><i class="fas fa-dna"></i><h4>生物化学</h4><p>生命化学</p></div>
          <div class="museum-item"><i class="fas fa-microscope"></i><h4>材料科学</h4><p>纳米到宏观</p></div>
        </div>
      </div>
    </section>

    <!-- 账号 -->
    <section class="page" id="page-account">
      <div class="card" style="max-width:560px; margin:0 auto;">
        <div class="card-title" style="justify-content:center;"><i class="fas fa-user-circle"></i>账号中心</div>
        <div class="user-profile" id="userProfile">
          <div class="avatar"><i class="fas fa-user"></i></div>
          <div class="user-email" id="profileEmail">user@example.com</div>
          <div class="user-qq" id="profileQQ">QQ: --</div>
          <button class="btn-logout" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> 退出</button>
        </div>
        <div class="auth-tabs" id="authTabs">
          <button class="active" data-tab="login">登录</button>
          <button data-tab="register">注册</button>
        </div>
        <!-- 登录 -->
        <form class="auth-form active" id="loginForm">
          <div class="form-error" id="loginError"></div>
          <div class="form-success" id="loginSuccess"></div>
          <label>邮箱</label>
          <div class="input-group"><i class="fas fa-envelope"></i><input type="email" id="loginEmail" placeholder="请输入邮箱" required /></div>
          <label>密码</label>
          <div class="input-group"><i class="fas fa-lock"></i><input type="password" id="loginPassword" placeholder="请输入密码" required /></div>
          <button type="submit" class="btn-primary">登录</button>
        </form>
        <!-- 注册 -->
        <form class="auth-form" id="registerForm">
          <div class="form-error" id="registerError"></div>
          <div class="form-success" id="registerSuccess"></div>
          <label>邮箱</label>
          <div class="input-group"><i class="fas fa-envelope"></i><input type="email" id="regEmail" placeholder="请输入邮箱" required /></div>
          <label>验证码</label>
          <div class="code-row">
            <div class="input-group"><i class="fas fa-shield-alt"></i><input type="text" id="regCode" placeholder="6位数字" maxlength="6" required /></div>
            <button type="button" class="btn-code" id="sendCodeBtn">发送</button>
          </div>
          <label>密码（至少8位，含大小写/数字/特殊字符中三种）</label>
          <div class="input-group"><i class="fas fa-lock"></i><input type="password" id="regPassword" placeholder="设置密码" required minlength="8" /></div>
          <label>真实姓名</label>
          <div class="input-group"><i class="fas fa-user"></i><input type="text" id="regRealName" placeholder="请输入真实姓名" required /></div>
          <label>租户邀请码</label>
          <div class="input-group"><i class="fas fa-key"></i><input type="text" id="regInviteCode" placeholder="例如 SCH001-XJ3K" required /></div>
          <label>QQ号（选填）</label>
          <div class="input-group"><i class="fab fa-qq"></i><input type="text" id="regQQ" placeholder="请输入QQ号" /></div>
          <button type="submit" class="btn-primary">注册</button>
        </form>
      </div>
    </section>
  </div>

  <footer class="footer"><p>© 2026 <span>GSKChem</span> · 化学联考平台</p></footer>
  <div class="toast" id="toast"></div>

  <script>
    (function() {
      'use strict';

      // ========== API 调用层 ==========
      const API = {
        baseURL: window.location.pathname,

        async _request(action, data = null) {
            const url = this.baseURL + '?action=' + action;
            const options = {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: data ? JSON.stringify(data) : undefined
            };
            try {
                const res = await fetch(url, options);
                const json = await res.json();
                if (!res.ok) throw json;
                return json;
            } catch (e) {
                // 如果响应不是 JSON，或者网络错误，构造一个标准错误
                if (e instanceof SyntaxError) {
                    throw { code: 50000, message: '服务器返回了非 JSON 格式的响应，请检查 PHP 错误日志' };
                }
                throw e;
            }
        },

        async login(username, password) {
            return this._request('login', { username, password });
        },
        async sendCode(email) {
            return this._request('send_code', { email });
        },
        async register(params) {
            return this._request('register', params);
        },
        async getCurrentUser() {
            return this._request('get_user');
        },
        async logout() {
            return this._request('logout');
        }
      };

      // ========== UI 控制 ==========
      const $ = (s) => document.querySelector(s);
      const $$ = (s) => document.querySelectorAll(s);

      const pageLinks = $$('.nav-links a');
      const pages = $$('.page');
      const hamburger = $('#hamburger');
      const navList = $('#navLinks');

      function setActivePage(pageId) {
        pages.forEach(p => p.classList.remove('active'));
        const target = document.getElementById('page-' + pageId);
        if (target) target.classList.add('active');
        pageLinks.forEach(a => a.classList.toggle('active', a.dataset.page === pageId));
        navList.classList.remove('open');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
      pageLinks.forEach(a => {
        a.addEventListener('click', (e) => {
          e.preventDefault();
          if (a.dataset.page) setActivePage(a.dataset.page);
        });
      });
      hamburger.addEventListener('click', () => navList.classList.toggle('open'));

      function showToast(msg, type = 'info') {
        const t = $('#toast');
        t.textContent = msg;
        t.className = 'toast show ' + type;
        clearTimeout(t._timer);
        t._timer = setTimeout(() => t.classList.remove('show'), 3000);
      }

      let currentUser = null;

      function updateUIForUser(user) {
        currentUser = user;
        const profile = $('#userProfile');
        const tabs = $('#authTabs');
        const forms = $$('.auth-form');
        if (user) {
          profile.classList.add('active');
          tabs.style.display = 'none';
          forms.forEach(f => f.style.display = 'none');
          $('#profileEmail').textContent = user.email;
          $('#profileQQ').textContent = 'QQ: ' + (user.qq || '未设置');
        } else {
          profile.classList.remove('active');
          tabs.style.display = 'flex';
          forms.forEach(f => f.style.display = '');
          $$('.auth-tabs button').forEach(b => b.classList.remove('active'));
          document.querySelector('[data-tab="login"]').classList.add('active');
          $('#loginForm').classList.add('active');
          $('#registerForm').classList.remove('active');
          $('#loginError').style.display = 'none';
          $('#loginSuccess').style.display = 'none';
          $('#registerError').style.display = 'none';
          $('#registerSuccess').style.display = 'none';
        }
        renderExams();
        renderSubmissions();
      }

      async function initUser() {
        try {
          const res = await API.getCurrentUser();
          if (res.code === 0) {
            updateUIForUser(res.data);
          } else {
            updateUIForUser(null);
          }
        } catch (e) {
          console.warn('获取用户信息失败', e);
          updateUIForUser(null);
        }
      }

      // 认证切换
      $$('.auth-tabs button').forEach(tab => {
        tab.addEventListener('click', function() {
          $$('.auth-tabs button').forEach(t => t.classList.remove('active'));
          this.classList.add('active');
          const target = this.dataset.tab;
          $$('.auth-form').forEach(f => f.classList.remove('active'));
          if (target === 'login') {
            $('#loginForm').classList.add('active');
            $('#loginError').style.display = 'none';
            $('#loginSuccess').style.display = 'none';
          } else {
            $('#registerForm').classList.add('active');
            $('#registerError').style.display = 'none';
            $('#registerSuccess').style.display = 'none';
          }
        });
      });

      // 登录
      $('#loginForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const email = $('#loginEmail').value.trim();
        const password = $('#loginPassword').value.trim();
        const err = $('#loginError');
        const suc = $('#loginSuccess');
        err.style.display = 'none';
        suc.style.display = 'none';
        if (!email || !password) { err.textContent = '请填写完整'; err.style.display = 'block'; return; }
        try {
          const res = await API.login(email, password);
          if (res.code === 0) {
            suc.textContent = '✅ 登录成功';
            suc.style.display = 'block';
            showToast('欢迎回来', 'success');
            $('#loginEmail').value = '';
            $('#loginPassword').value = '';
            await initUser();
          } else {
            err.textContent = res.message || '登录失败';
            err.style.display = 'block';
          }
        } catch (ex) {
          err.textContent = ex.message || '网络错误';
          err.style.display = 'block';
        }
      });

      // 注册
      $('#registerForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const email = $('#regEmail').value.trim();
        const code = $('#regCode').value.trim();
        const password = $('#regPassword').value.trim();
        const realName = $('#regRealName').value.trim();
        const inviteCode = $('#regInviteCode').value.trim();
        const qq = $('#regQQ').value.trim();
        const err = $('#registerError');
        const suc = $('#registerSuccess');
        err.style.display = 'none';
        suc.style.display = 'none';
        if (!email || !code || !password || !realName || !inviteCode) {
          err.textContent = '请完整填写所有必填字段';
          err.style.display = 'block';
          return;
        }
        try {
          const res = await API.register({ email, code, password, realName, tenantInviteCode: inviteCode, role: 'MARKER', qq });
          if (res.code === 0) {
            suc.textContent = '🎉 ' + res.message;
            suc.style.display = 'block';
            showToast('注册成功，等待审核', 'success');
            $('#regEmail').value = '';
            $('#regCode').value = '';
            $('#regPassword').value = '';
            $('#regRealName').value = '';
            $('#regInviteCode').value = '';
            $('#regQQ').value = '';
            document.querySelector('[data-tab="login"]').click();
          } else {
            err.textContent = res.message || '注册失败';
            err.style.display = 'block';
          }
        } catch (ex) {
          err.textContent = ex.message || '网络错误';
          err.style.display = 'block';
        }
      });

      // 发送验证码
      $('#sendCodeBtn').addEventListener('click', async function() {
        const email = $('#regEmail').value.trim();
        if (!email || !email.includes('@')) { showToast('请输入有效邮箱', 'error'); return; }
        this.disabled = true;
        this.textContent = '发送中...';
        try {
          const res = await API.sendCode(email);
          showToast(res.message, 'info');
        } catch (ex) {
          showToast(ex.message || '发送失败', 'error');
        } finally {
          this.disabled = false;
          this.textContent = '发送';
        }
      });

      // 退出
      $('#logoutBtn').addEventListener('click', async function() {
        try {
          await API.logout();
          await initUser();
          showToast('已退出', 'info');
          setActivePage('home');
        } catch (e) {
          showToast('退出失败', 'error');
        }
      });

      // ========== 模拟考试和上传记录（临时） ==========
      function renderExams() {
        let exams = JSON.parse(localStorage.getItem('gskchem_exams') || '[]');
        if (exams.length === 0) {
          exams = [
            { id: 'exam1', name: '2026年春季化学联考', subject: 'CHEMISTRY', startTime: '2026-04-15T09:00:00Z', endTime: '2026-04-15T11:00:00Z', status: 'IN_PROGRESS' },
            { id: 'exam2', name: '2026年夏季化学联考', subject: 'CHEMISTRY', startTime: '2026-07-20T09:00:00Z', endTime: '2026-07-20T11:00:00Z', status: 'PENDING' },
            { id: 'exam3', name: '2025年秋季化学联考', subject: 'CHEMISTRY', startTime: '2025-10-10T09:00:00Z', endTime: '2025-10-10T11:00:00Z', status: 'COMPLETED' }
          ];
          localStorage.setItem('gskchem_exams', JSON.stringify(exams));
        }
        const inProgress = exams.filter(e => e.status === 'IN_PROGRESS');
        const container = $('#examList');
        if (inProgress.length === 0) {
          container.innerHTML = '<p style="color:#94a3b8;">暂无进行中的考试</p>';
          return;
        }
        container.innerHTML = inProgress.map(exam => `
          <div class="exam-card" data-exam-id="${exam.id}">
            <h4>🧪 ${exam.name}</h4>
            <div class="meta">${new Date(exam.startTime).toLocaleString()}</div>
          </div>
        `).join('');
        container.querySelectorAll('.exam-card').forEach(el => {
          el.addEventListener('click', function() {
            container.querySelectorAll('.exam-card').forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
          });
        });
        const first = container.querySelector('.exam-card');
        if (first) first.classList.add('selected');
      }

      function renderSubmissions() {
        const container = $('#submissionList');
        if (!currentUser) {
          container.innerHTML = '<p style="color:#94a3b8;">请登录查看记录</p>';
          return;
        }
        const all = JSON.parse(localStorage.getItem('gskchem_submissions') || '[]');
        const my = all.filter(s => s.userId === currentUser.id);
        if (my.length === 0) {
          container.innerHTML = '<p style="color:#94a3b8;">暂无上传记录</p>';
          return;
        }
        let html = '<div style="display:flex;flex-direction:column;gap:0.4rem;">';
        my.slice().reverse().forEach(s => {
          html += `
            <div style="background:#f8fafc;padding:0.5rem 1rem;border-radius:4px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;border-left:3px solid #d4a373;">
              <span><strong>${s.fileName}</strong> <span style="color:#64748b;font-size:0.85rem;">(${(s.fileSize/1024).toFixed(1)} KB)</span></span>
              <span style="color:#64748b;font-size:0.85rem;">${new Date(s.uploadTime).toLocaleString()}</span>
            </div>
          `;
        });
        html += '</div>';
        container.innerHTML = html;
      }

      // ========== 上传功能（模拟） ==========
      let selectedFile = null;
      const uploadArea = $('#uploadArea');
      const fileInput = $('#fileInput');

      uploadArea.addEventListener('click', () => {
        if (!currentUser) { showToast('请先登录', 'error'); return; }
        fileInput.click();
      });
      uploadArea.addEventListener('dragover', (e) => { e.preventDefault(); uploadArea.classList.add('dragover'); });
      uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
      uploadArea.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadArea.classList.remove('dragover');
        if (!currentUser) { showToast('请先登录', 'error'); return; }
        if (e.dataTransfer.files.length > 0) handleFile(e.dataTransfer.files[0]);
      });
      fileInput.addEventListener('change', function() {
        if (this.files.length > 0) handleFile(this.files[0]);
        this.value = '';
      });

      function handleFile(file) {
        if (!file.type.startsWith('image/')) { showToast('请上传图片文件', 'error'); return; }
        if (file.size > 5 * 1024 * 1024) { showToast('文件不能超过5MB', 'error'); return; }
        selectedFile = file;
        $('#uploadStatus').textContent = '已选择: ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        $('#uploadStatus').style.color = '#0b3b4c';
        showToast('已选择文件', 'info');
      }

      $('#uploadBtn').addEventListener('click', async function() {
        if (!currentUser) { showToast('请先登录', 'error'); return; }
        const selected = document.querySelector('.exam-card.selected');
        if (!selected) { showToast('请先选择一场考试', 'error'); return; }
        const examId = selected.dataset.examId;
        if (!selectedFile) { showToast('请选择一张图片', 'error'); return; }

        this.disabled = true;
        this.innerHTML = '上传中...';
        const status = $('#uploadStatus');
        status.textContent = '上传中...';
        status.style.color = '#d4a373';
        // 模拟上传
        setTimeout(() => {
          const subs = JSON.parse(localStorage.getItem('gskchem_submissions') || '[]');
          const entry = {
            id: 'sub_' + Date.now(),
            examId,
            userId: currentUser.id,
            fileName: selectedFile.name,
            fileSize: selectedFile.size,
            uploadTime: new Date().toISOString(),
            status: '已收集'
          };
          subs.push(entry);
          localStorage.setItem('gskchem_submissions', JSON.stringify(subs));
          status.textContent = '✅ 上传成功';
          status.style.color = '#0b6b4c';
          showToast('上传成功', 'success');
          selectedFile = null;
          renderSubmissions();
          this.disabled = false;
          this.innerHTML = '提交';
        }, 1200);
      });

      // ========== 启动 ==========
      initUser();

      document.addEventListener('click', (e) => {
        if (!e.target.closest('.navbar')) navList.classList.remove('open');
      });

      console.log('GSKChem 平台已启动（修复版）');
    })();
  </script>
</body>
</html>
