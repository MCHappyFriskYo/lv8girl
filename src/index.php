<?php
/**
 * GSKChem 联考平台 - 博物志翻页书图片版
 * 图片文件：01.jpg ~ 08.jpg，放置于 index.php 同级目录
 */

// 关闭错误显示，但记录到日志
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// 输出缓冲
ob_start();

// ========== 数据库配置 ==========
function gsk_config() {
    $host = 'localhost';
    $dbname = 'lv8girl';
    $db_user = 'lv8girl';
    $db_pass = 'yourpasswd';   // 请修改为实际密码
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['code' => 50000, 'message' => '数据库连接失败：' . $e->getMessage()]);
        exit;
    }
}

// ========== 跨域 ==========
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// ========== 处理 API 请求 ==========
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

        // ---------- 注册（兼容 real_name） ----------
        if ($action === 'register') {
            $input = json_decode(file_get_contents('php://input'), true);
            $email = trim($input['email'] ?? '');
            $password = trim($input['password'] ?? '');
            $qq = trim($input['qq'] ?? '');

            if (!$email || !$password) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '邮箱和密码为必填项']);
                exit;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '邮箱格式无效']);
                exit;
            }
            if (strlen($password) < 6) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '密码至少6位']);
                exit;
            }

            $stmt = $pdo->prepare("SELECT id FROM gsk_users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['code' => 40900, 'message' => '该邮箱已注册']);
                exit;
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // 检查 real_name 列是否存在
            $check = $pdo->query("SHOW COLUMNS FROM gsk_users LIKE 'real_name'");
            $hasRealName = $check->rowCount() > 0;

            if ($hasRealName) {
                $stmt = $pdo->prepare("INSERT INTO gsk_users (email, password, qq, role, tenant_id, status, real_name) VALUES (?, ?, ?, 'MARKER', 'school_a', 'ACTIVE', '')");
                $stmt->execute([$email, $hashed, $qq]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO gsk_users (email, password, qq, role, tenant_id, status) VALUES (?, ?, ?, 'MARKER', 'school_a', 'ACTIVE')");
                $stmt->execute([$email, $hashed, $qq]);
            }

            $userId = $pdo->lastInsertId();

            echo json_encode([
                'code' => 0,
                'message' => '注册成功，请登录',
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
            $stmt = $pdo->prepare("SELECT id, email, qq, role FROM gsk_users WHERE id = ?");
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
        http_response_code(500);
        echo json_encode(['code' => 50000, 'message' => '服务器错误：' . $e->getMessage()]);
        exit;
    }
}

// ================================================================
// 没有 action 参数，输出 HTML 界面
// ================================================================
ob_end_clean();
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
    /* ===== 样式（与之前完全一致，这里简写，实际使用时完整复制） ===== */
    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
      background: #f6f8fa;
      color: #1e293b;
      line-height: 1.5;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background-image: linear-gradient(rgba(11,59,76,0.02) 1px,transparent 1px),linear-gradient(90deg,rgba(11,59,76,0.02) 1px,transparent 1px);
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
    .navbar .brand i { color: #d4a373; font-size: 1.6rem; }
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
    .hamburger span { display: block; width: 26px; height: 2px; background: #cbd5e1; border-radius: 2px; }
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
    .card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.06); }
    .card-title {
      font-size: 1.3rem;
      font-weight: 600;
      margin-bottom: 1rem;
      color: #0b3b4c;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .card-title i { color: #d4a373; width: 1.6rem; text-align: center; }
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
    .hero-logo:hover { transform: scale(1.02); }
    .hero-logo i { color: #0b3b4c; }
    .hero h1 {
      font-size: 2.6rem;
      font-weight: 700;
      color: #0b3b4c;
      letter-spacing: -0.5px;
    }
    .hero h1 i { color: #d4a373; margin-right: 8px; }
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
    .feature-item:hover { transform: translateY(-2px); }
    .feature-item i { font-size: 2rem; color: #0b3b4c; margin-bottom: 0.5rem; display: block; }
    .feature-item h4 { font-weight: 600; color: #0b3b4c; }
    .feature-item p { color: #64748b; font-size: 0.9rem; margin-top: 0.2rem; }
    .exam-empty {
      text-align: center;
      padding: 3rem 0;
      color: #94a3b8;
    }
    .exam-empty i { font-size: 4rem; color: #d4a373; margin-bottom: 1rem; display: block; }
    .exam-empty h3 { font-size: 1.5rem; color: #0b3b4c; margin-bottom: 0.5rem; }
    .book-container {
      position: relative;
      max-width: 700px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.10);
      padding: 2rem 1.5rem;
      min-height: 400px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      border: 1px solid #e9edf2;
    }
    .book-pages {
      position: relative;
      width: 100%;
      height: 350px;
      overflow: hidden;
    }
    .book-page {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      background: #ffffff;
      border-radius: 12px;
      opacity: 0;
      transform: translateX(30px) scale(0.95);
      transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
      pointer-events: none;
      box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .book-page.active {
      opacity: 1;
      transform: translateX(0) scale(1);
      pointer-events: auto;
    }
    .book-page.exit { opacity: 0; transform: translateX(-30px) scale(0.95); }
    .book-page .page-icon { font-size: 3.5rem; color: #0b3b4c; margin-bottom: 0.8rem; }
    .book-page .page-title { font-size: 1.6rem; font-weight: 700; color: #0b3b4c; margin-bottom: 0.3rem; }
    .book-page .page-desc { color: #475569; text-align: center; max-width: 400px; font-size: 0.95rem; }
    .book-page .page-img {
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: #f0f4f8;
      border: 2px solid #d4a373;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 0.8rem;
      font-size: 3rem;
      color: #94a3b8;
      overflow: hidden;
    }
    .book-page .page-img img { width: 100%; height: 100%; object-fit: cover; }
    .book-controls {
      display: flex;
      gap: 2rem;
      margin-top: 1.5rem;
      align-items: center;
    }
    .book-controls button {
      background: #0b3b4c;
      color: #fff;
      border: none;
      width: 48px;
      height: 48px;
      border-radius: 50%;
      font-size: 1.4rem;
      cursor: pointer;
      transition: background 0.2s, transform 0.15s;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .book-controls button:hover { background: #0a2f3d; transform: scale(1.05); }
    .book-controls button:disabled { opacity: 0.3; cursor: not-allowed; transform: none; }
    .book-controls .page-indicator { font-weight: 600; color: #0b3b4c; font-size: 1rem; min-width: 80px; text-align: center; }
    .weekly-list {
      display: flex;
      flex-direction: column;
      gap: 1.2rem;
      max-width: 700px;
      margin: 0 auto;
    }
    .weekly-item {
      background: #f8fafc;
      padding: 1.2rem 1.5rem;
      border-radius: 8px;
      border-left: 4px solid #d4a373;
    }
    .weekly-item h4 { font-size: 1.05rem; color: #0b3b4c; margin-bottom: 0.3rem; }
    .weekly-item p { color: #475569; font-size: 0.9rem; }
    .weekly-item .options { margin-top: 0.5rem; display: flex; flex-direction: column; gap: 0.3rem; }
    .weekly-item .options label { display: flex; align-items: center; gap: 0.6rem; font-size: 0.9rem; color: #1e293b; cursor: pointer; }
    .weekly-item .options input[type="radio"] { accent-color: #0b3b4c; width: 16px; height: 16px; }
    .weekly-item .answer-btn {
      margin-top: 0.6rem;
      background: #0b3b4c;
      color: #fff;
      border: none;
      padding: 0.3rem 1.2rem;
      border-radius: 20px;
      font-size: 0.85rem;
      cursor: pointer;
      transition: background 0.15s;
    }
    .weekly-item .answer-btn:hover { background: #0a2f3d; }
    .weekly-item .feedback { margin-top: 0.5rem; font-weight: 500; font-size: 0.9rem; }
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
    .auth-tabs button.active { color: #0b3b4c; border-bottom-color: #d4a373; }
    .auth-tabs button:hover { color: #0b3b4c; }
    .auth-form {
      display: none;
      flex-direction: column;
      gap: 1rem;
      max-width: 400px;
      margin: 0 auto;
    }
    .auth-form.active { display: flex; }
    .auth-form label { font-weight: 500; font-size: 0.9rem; color: #334155; }
    .auth-form .input-group {
      display: flex;
      align-items: center;
      background: #f1f5f9;
      border-radius: 6px;
      padding: 0 0.8rem;
      border: 1px solid #e2e8f0;
      transition: border-color 0.15s;
    }
    .auth-form .input-group:focus-within { border-color: #0b3b4c; background: #ffffff; }
    .auth-form .input-group i { color: #94a3b8; font-size: 0.95rem; margin-right: 8px; }
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
    .auth-form .form-error { color: #b91c1c; font-size: 0.85rem; background: #fef2f2; padding: 0.4rem 0.8rem; border-radius: 4px; display: none; }
    .auth-form .form-success { color: #0b6b4c; font-size: 0.85rem; background: #f0fdf4; padding: 0.4rem 0.8rem; border-radius: 4px; display: none; }
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
      .book-container { padding: 1.5rem 1rem; min-height: 350px; }
      .book-pages { height: 300px; }
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
      .container { padding: 1rem 0.8rem; }
      .card { padding: 1.2rem; }
      .hero { padding: 1.8rem 1.2rem; }
      .hero h1 { font-size: 2rem; }
      .hero-logo { width: 72px; height: 72px; line-height: 72px; font-size: 2rem; }
      .book-container { padding: 1rem; min-height: 300px; }
      .book-pages { height: 260px; }
      .book-page .page-title { font-size: 1.3rem; }
      .book-page .page-icon { font-size: 2.8rem; }
      .book-controls button { width: 40px; height: 40px; font-size: 1.2rem; }
    }
    @media (max-width: 400px) { .book-page .page-img { width: 80px; height: 80px; } }
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
      <li><a href="#" data-page="weekly">周常</a></li>
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
          <div class="feature-item"><i class="fas fa-envelope"></i><h4>邮箱注册</h4><p>快速注册，即时使用</p></div>
          <div class="feature-item"><i class="fas fa-upload"></i><h4>答题卡上传</h4><p>支持拖拽/点击，图片格式</p></div>
          <div class="feature-item"><i class="fas fa-chart-bar"></i><h4>数据管理</h4><p>自动归档，随时查阅</p></div>
        </div>
      </div>
    </section>

    <!-- 联考（空白） -->
    <section class="page" id="page-exam">
      <div class="card">
        <div class="card-title"><i class="fas fa-pencil-alt"></i>联考管理</div>
        <div class="exam-empty">
          <i class="fas fa-hourglass-half"></i>
          <h3>联考功能开发中</h3>
          <p>敬请期待后续更新，届时将支持考试创建、答题卡上传与阅卷。</p>
        </div>
      </div>
    </section>

    <!-- ===== 博物志翻页书（图片版） ===== -->
    <section class="page" id="page-museum">
      <div class="card">
        <div class="card-title"><i class="fas fa-book-open"></i>燕石博物志</div>
        <div class="book-container">
          <div class="book-pages" id="bookPages">
            <!-- 第1页：01.jpg -->
            <div class="book-page active" data-index="0">
              <div class="page-img"><img src="01.jpg" alt="元素周期表"></div>
              <div class="page-icon"><i class="fas fa-atom"></i></div>
              <div class="page-title">元素周期表</div>
              <div class="page-desc">118种元素的规律与奥秘，从氢到Og。</div>
            </div>
            <!-- 第2页：02.jpg -->
            <div class="book-page" data-index="1">
              <div class="page-img"><img src="02.jpg" alt="分子结构"></div>
              <div class="page-icon"><i class="fas fa-bezier-curve"></i></div>
              <div class="page-title">分子结构</div>
              <div class="page-desc">三维空间中的化学键与分子构型。</div>
            </div>
            <!-- 第3页：03.jpg -->
            <div class="book-page" data-index="2">
              <div class="page-img"><img src="03.jpg" alt="化学反应"></div>
              <div class="page-icon"><i class="fas fa-fire"></i></div>
              <div class="page-title">化学反应</div>
              <div class="page-desc">燃烧、置换、催化……万千变化。</div>
            </div>
            <!-- 第4页：04.jpg -->
            <div class="book-page" data-index="3">
              <div class="page-img"><img src="04.jpg" alt="实验仪器"></div>
              <div class="page-icon"><i class="fas fa-flask"></i></div>
              <div class="page-title">实验仪器</div>
              <div class="page-desc">烧杯、试管、酒精灯——实验室的基石。</div>
            </div>
            <!-- 第5页：05.jpg -->
            <div class="book-page" data-index="4">
              <div class="page-img"><img src="05.jpg" alt="生物化学"></div>
              <div class="page-icon"><i class="fas fa-dna"></i></div>
              <div class="page-title">生物化学</div>
              <div class="page-desc">生命体中的化学反应与代谢途径。</div>
            </div>
            <!-- 第6页：06.jpg -->
            <div class="book-page" data-index="5">
              <div class="page-img"><img src="06.jpg" alt="材料科学"></div>
              <div class="page-icon"><i class="fas fa-microscope"></i></div>
              <div class="page-title">材料科学</div>
              <div class="page-desc">从纳米材料到高分子，化学构筑世界。</div>
            </div>
            <!-- 第7页：07.jpg -->
            <div class="book-page" data-index="6">
              <div class="page-img"><img src="07.jpg" alt="化学史"></div>
              <div class="page-icon"><i class="fas fa-history"></i></div>
              <div class="page-title">化学史</div>
              <div class="page-desc">从炼金术到现代化学的璀璨历程。</div>
            </div>
            <!-- 第8页：08.jpg -->
            <div class="book-page" data-index="7">
              <div class="page-img"><img src="08.jpg" alt="诺贝尔化学奖"></div>
              <div class="page-icon"><i class="fas fa-trophy"></i></div>
              <div class="page-title">诺贝尔化学奖</div>
              <div class="page-desc">那些改变世界的化学发现与人物。</div>
            </div>
          </div>
          <div class="book-controls">
            <button id="prevPage" disabled><i class="fas fa-chevron-left"></i></button>
            <span class="page-indicator" id="pageIndicator">1 / 8</span>
            <button id="nextPage"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
        <p style="text-align:center;color:#94a3b8;font-size:0.9rem;margin-top:0.8rem;">
          <i class="fas fa-info-circle"></i> 图片文件（01.jpg ~ 08.jpg）请放置在网站根目录，与 index.php 同级。
        </p>
      </div>
    </section>

    <!-- 周常 -->
    <section class="page" id="page-weekly">
      <div class="card">
        <div class="card-title"><i class="fas fa-calendar-week"></i>周常 · 化学挑战</div>
        <div class="weekly-list">
          <div class="weekly-item">
            <h4>🧪 第1题 · 元素推断</h4>
            <p>某元素原子核外电子排布为 2, 8, 7，它位于元素周期表的第几周期第几族？</p>
            <div class="options">
              <label><input type="radio" name="q1" value="A"> A. 第三周期 VIIA族</label>
              <label><input type="radio" name="q1" value="B"> B. 第三周期 VIIB族</label>
              <label><input type="radio" name="q1" value="C"> C. 第二周期 VIIA族</label>
              <label><input type="radio" name="q1" value="D"> D. 第三周期 VIA族</label>
            </div>
            <button class="answer-btn" data-question="1" data-answer="A">查看答案</button>
            <div class="feedback" id="feedback1"></div>
          </div>
          <div class="weekly-item">
            <h4>🧪 第2题 · 化学键</h4>
            <p>下列物质中，含有离子键和共价键的是？</p>
            <div class="options">
              <label><input type="radio" name="q2" value="A"> A. NaOH</label>
              <label><input type="radio" name="q2" value="B"> B. H₂O</label>
              <label><input type="radio" name="q2" value="C"> C. NaCl</label>
              <label><input type="radio" name="q2" value="D"> D. CO₂</label>
            </div>
            <button class="answer-btn" data-question="2" data-answer="A">查看答案</button>
            <div class="feedback" id="feedback2"></div>
          </div>
          <div class="weekly-item">
            <h4>🧪 第3题 · 化学平衡</h4>
            <p>对于可逆反应 2SO₂(g) + O₂(g) ⇌ 2SO₃(g)，增大压强，平衡向哪个方向移动？</p>
            <div class="options">
              <label><input type="radio" name="q3" value="A"> A. 正反应方向</label>
              <label><input type="radio" name="q3" value="B"> B. 逆反应方向</label>
              <label><input type="radio" name="q3" value="C"> C. 不移动</label>
              <label><input type="radio" name="q3" value="D"> D. 无法判断</label>
            </div>
            <button class="answer-btn" data-question="3" data-answer="A">查看答案</button>
            <div class="feedback" id="feedback3"></div>
          </div>
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
          <label>密码（至少6位）</label>
          <div class="input-group"><i class="fas fa-lock"></i><input type="password" id="regPassword" placeholder="设置密码" required minlength="6" /></div>
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
            const res = await fetch(url, options);
            const json = await res.json();
            if (!res.ok) throw json;
            return json;
        },

        async login(username, password) {
            return this._request('login', { username, password });
        },
        async register(email, password, qq) {
            return this._request('register', { email, password, qq });
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
        const password = $('#regPassword').value.trim();
        const qq = $('#regQQ').value.trim();
        const err = $('#registerError');
        const suc = $('#registerSuccess');
        err.style.display = 'none';
        suc.style.display = 'none';
        if (!email || !password) {
          err.textContent = '邮箱和密码为必填项';
          err.style.display = 'block';
          return;
        }
        if (password.length < 6) {
          err.textContent = '密码至少6位';
          err.style.display = 'block';
          return;
        }
        try {
          const res = await API.register(email, password, qq);
          if (res.code === 0) {
            suc.textContent = '🎉 ' + res.message;
            suc.style.display = 'block';
            showToast('注册成功，请登录', 'success');
            $('#regEmail').value = '';
            $('#regPassword').value = '';
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

      // ========== 翻页书 ==========
      const pages_book = $$('.book-page');
      const prevBtn = $('#prevPage');
      const nextBtn = $('#nextPage');
      const indicator = $('#pageIndicator');
      let currentPage = 0;
      const totalPages = pages_book.length;

      function updateBook(index) {
        pages_book.forEach((page, i) => {
          page.classList.remove('active', 'exit');
          if (i === index) {
            page.classList.add('active');
          }
        });
        indicator.textContent = (index + 1) + ' / ' + totalPages;
        prevBtn.disabled = index === 0;
        nextBtn.disabled = index === totalPages - 1;
      }

      prevBtn.addEventListener('click', () => {
        if (currentPage > 0) {
          currentPage--;
          updateBook(currentPage);
        }
      });
      nextBtn.addEventListener('click', () => {
        if (currentPage < totalPages - 1) {
          currentPage++;
          updateBook(currentPage);
        }
      });
      updateBook(0);

      // ========== 周常答题 ==========
      $$('.answer-btn').forEach(btn => {
        btn.addEventListener('click', function() {
          const qNum = this.dataset.question;
          const correct = this.dataset.answer;
          const feedback = document.getElementById('feedback' + qNum);
          const selected = document.querySelector(`input[name="q${qNum}"]:checked`);
          if (!selected) {
            feedback.textContent = '请先选择一个选项';
            feedback.style.color = '#d4a373';
            return;
          }
          const userAnswer = selected.value;
          if (userAnswer === correct) {
            feedback.textContent = '✅ 回答正确！';
            feedback.style.color = '#0b6b4c';
          } else {
            feedback.textContent = '❌ 回答错误，正确答案是 ' + correct;
            feedback.style.color = '#b91c1c';
          }
        });
      });

      // ========== 启动 ==========
      initUser();

      document.addEventListener('click', (e) => {
        if (!e.target.closest('.navbar')) navList.classList.remove('open');
      });

      console.log('GSKChem 平台已启动（博物志图片版）');
    })();
  </script>
</body>
</html>
