<?php
/**
 * LunaticCho 前台
 * 支持用户名注册、头像上传、周常卡片、后台入口
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

// ========== 数据库配置 ==========
function gsk_config() {
    $host = 'db';
    $dbname = 'lv8girl';
    $db_user = 'lv8girl';
    $db_pass = 'yourpasswd';
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
            $login = trim($input['username'] ?? '');
            $password = trim($input['password'] ?? '');
            if (!$login || !$password) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '请填写用户名/邮箱和密码']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT * FROM gsk_users WHERE username = ? OR email = ?");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();
            if (!$user || !password_verify($password, $user['password'])) {
                http_response_code(401);
                echo json_encode(['code' => 40001, 'message' => '用户名/邮箱或密码错误']);
                exit;
            }
            if ($user['status'] !== 'ACTIVE') {
                http_response_code(403);
                echo json_encode(['code' => 40300, 'message' => '账号未激活，请联系管理员']);
                exit;
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
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
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'avatar' => $user['avatar'] ?? null,
                        'tenantId' => $user['tenant_id']
                    ]
                ]
            ]);
            exit;
        }

        // ---------- 注册 ----------
        if ($action === 'register') {
            $input = json_decode(file_get_contents('php://input'), true);
            $username = trim($input['username'] ?? '');
            $email = trim($input['email'] ?? '');
            $password = trim($input['password'] ?? '');
            $qq = trim($input['qq'] ?? '');

            if (!$username || !$email || !$password) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '用户名、邮箱和密码为必填项']);
                exit;
            }
            if (strlen($username) < 2 || strlen($username) > 30) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '用户名长度2-30位']);
                exit;
            }
            $stmt = $pdo->prepare("SELECT id FROM gsk_users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['code' => 40900, 'message' => '该用户名已被使用']);
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

            $check = $pdo->query("SHOW COLUMNS FROM gsk_users LIKE 'real_name'");
            $hasRealName = $check->rowCount() > 0;

            if ($hasRealName) {
                $stmt = $pdo->prepare("INSERT INTO gsk_users (username, email, password, qq, role, tenant_id, status, real_name) VALUES (?, ?, ?, ?, 'MARKER', 'school_a', 'ACTIVE', '')");
                $stmt->execute([$username, $email, $hashed, $qq]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO gsk_users (username, email, password, qq, role, tenant_id, status) VALUES (?, ?, ?, ?, 'MARKER', 'school_a', 'ACTIVE')");
                $stmt->execute([$username, $email, $hashed, $qq]);
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
            $stmt = $pdo->prepare("SELECT id, username, email, qq, role, avatar FROM gsk_users WHERE id = ?");
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

        // ---------- 更新头像 ----------
        if ($action === 'update_avatar') {
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode(['code' => 40100, 'message' => '未登录']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            $avatar = trim($input['avatar'] ?? '');
            if (empty($avatar)) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '头像数据不能为空']);
                exit;
            }
            if (!preg_match('/^data:image\/(jpeg|png|gif|webp);base64,/', $avatar)) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '图片格式不支持，请上传 jpg/png/gif/webp']);
                exit;
            }
            $size = strlen($avatar);
            if ($size > 2.5 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['code' => 40001, 'message' => '图片过大，请压缩后上传']);
                exit;
            }
            $stmt = $pdo->prepare("UPDATE gsk_users SET avatar = ? WHERE id = ?");
            $stmt->execute([$avatar, $_SESSION['user_id']]);
            echo json_encode(['code' => 0, 'message' => '头像更新成功']);
            exit;
        }

        // ---------- 登出 ----------
        if ($action === 'logout') {
            session_destroy();
            echo json_encode(['code' => 0, 'message' => '已登出']);
            exit;
        }

        // ---------- 获取周常 ----------
        if ($action === 'get_weekly') {
            $stmt = $pdo->query("SELECT id, title, content, options, answer, teacher, created_at FROM gsk_weekly WHERE status = 1 ORDER BY created_at DESC");
            $items = $stmt->fetchAll();
            foreach ($items as &$item) {
                $item['options'] = json_decode($item['options'], true);
            }
            echo json_encode(['code' => 0, 'data' => $items]);
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
// 没有 action 参数，输出 HTML
// ================================================================
ob_end_clean();
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LunaticCho · 联考平台</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    /* ===== 基础样式 ===== */
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

    /* ===== 博物志 ===== */
    .book-container {
      position: relative;
      max-width: 700px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 16px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.10);
      padding: 0;
      min-height: 400px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      border: 1px solid #e9edf2;
      overflow: hidden;
    }
    .book-pages {
      position: relative;
      width: 100%;
      height: 450px;
      overflow: hidden;
    }
    .book-page {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8fafc;
      opacity: 0;
      transform: translateX(30px) scale(0.95);
      transition: all 0.5s cubic-bezier(0.23, 1, 0.32, 1);
      pointer-events: none;
    }
    .book-page.active {
      opacity: 1;
      transform: translateX(0) scale(1);
      pointer-events: auto;
    }
    .book-page img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      background: #ffffff;
      border-radius: 0;
    }
    .book-controls {
      display: flex;
      gap: 2rem;
      margin: 1rem 0 1.2rem;
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
    .book-controls .page-indicator {
      font-weight: 600;
      color: #0b3b4c;
      font-size: 1rem;
      min-width: 80px;
      text-align: center;
    }

    /* ===== 周常卡片 ===== */
    .weekly-cards {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 1.2rem;
      margin-top: 1rem;
    }
    .weekly-card {
      background: #f8fafc;
      border-radius: 8px;
      padding: 1.2rem 1.5rem;
      border-left: 4px solid #d4a373;
      cursor: pointer;
      transition: background 0.15s;
      box-shadow: 0 2px 6px rgba(0,0,0,0.04);
    }
    .weekly-card:hover { background: #f1f5f9; }
    .weekly-card .meta {
      display: flex;
      justify-content: space-between;
      font-size: 0.85rem;
      color: #64748b;
      margin-top: 0.3rem;
    }
    .weekly-card .title {
      font-size: 1.1rem;
      font-weight: 600;
      color: #0b3b4c;
    }
    .weekly-detail {
      display: none;
      margin-top: 0.8rem;
      padding-top: 0.8rem;
      border-top: 1px solid #e2e8f0;
    }
    .weekly-detail.open { display: block; }
    .weekly-detail .content { color: #1e293b; margin-bottom: 0.6rem; }
    .weekly-detail .options { display: flex; flex-direction: column; gap: 0.3rem; }
    .weekly-detail .options label { display: flex; align-items: center; gap: 0.6rem; font-size: 0.9rem; }
    .weekly-detail .answer-btn {
      margin-top: 0.6rem;
      background: #0b3b4c;
      color: #fff;
      border: none;
      padding: 0.2rem 1.2rem;
      border-radius: 20px;
      font-size: 0.85rem;
      cursor: pointer;
    }
    .weekly-detail .feedback { margin-top: 0.4rem; font-weight: 500; font-size: 0.9rem; }

    /* ===== 账号 ===== */
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
    .user-profile .avatar-wrap {
      position: relative;
      width: 100px;
      height: 100px;
      margin: 0 auto 0.8rem;
      cursor: pointer;
    }
    .user-profile .avatar-wrap img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #d4a373;
      background: #f0f4f8;
    }
    .user-profile .avatar-wrap .avatar-placeholder {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: #dbeafe;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3rem;
      color: #0b3b4c;
      border: 3px solid #d4a373;
    }
    .user-profile .avatar-wrap .upload-hint {
      position: absolute;
      bottom: 0;
      right: 0;
      background: #0b3b4c;
      color: #fff;
      border-radius: 50%;
      width: 28px;
      height: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.8rem;
      border: 2px solid #fff;
    }
    .user-profile .user-email { font-size: 1.1rem; font-weight: 600; color: #0b3b4c; }
    .user-profile .user-qq { color: #64748b; font-size: 0.9rem; }
    .user-profile .user-role {
      display: inline-block;
      background: #dbeafe;
      color: #0b3b4c;
      padding: 0.1rem 0.8rem;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      margin-top: 0.2rem;
    }
    .user-profile .btn-group {
      margin-top: 1rem;
      display: flex;
      gap: 0.8rem;
      justify-content: center;
      flex-wrap: wrap;
    }
    .user-profile .btn-logout {
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
    .user-profile .btn-admin {
      background: #0b3b4c;
      color: #fff;
      border: none;
      padding: 0.4rem 1.8rem;
      border-radius: 20px;
      font-weight: 500;
      cursor: pointer;
      font-size: 0.9rem;
      transition: background 0.15s;
      text-decoration: none;
      display: inline-block;
    }
    .user-profile .btn-admin:hover { background: #0a2f3d; }
    #avatarInput { display: none; }

    /* ===== Toast ===== */
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

    /* ===== 响应式 ===== */
    @media (max-width: 820px) {
      .navbar { padding: 0 1.5rem; }
      .features-grid { grid-template-columns: 1fr 1fr; }
      .hero-logo { width: 80px; height: 80px; line-height: 80px; font-size: 2.2rem; }
      .book-pages { height: 350px; }
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
      .book-container { min-height: 300px; }
      .book-pages { height: 280px; }
      .book-controls button { width: 40px; height: 40px; font-size: 1.2rem; }
      .weekly-cards { grid-template-columns: 1fr; }
    }
    @media (max-width: 400px) {
      .book-pages { height: 220px; }
      .user-profile .avatar-wrap { width: 80px; height: 80px; }
    }
  </style>
</head>
<body>
  <!-- 导航 -->
  <nav class="navbar">
    <div class="brand"><i class="fas fa-flask"></i> LunaticCho</div>
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
        <h1><i class="fas fa-flask"></i>LunaticCho 联考平台</h1>
        <p>化学学科联考 · 答题卡收集 · 数据驱动教学</p>
      </div>
      <div class="card" style="border-top: 4px solid #d4a373;">
        <div class="card-title"><i class="fas fa-flag"></i>平台简介</div>
        <p style="color:#334155;">LunaticCho 为化学联考提供从试卷发布、答题卡扫描上传到成绩统计的全流程支持。考生通过邮箱注册，可随时上传答题卡，教师端统一收集，高效便捷。</p>
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

    <!-- 博物志 -->
    <section class="page" id="page-museum">
      <div class="card">
        <div class="card-title"><i class="fas fa-book-open"></i>燕石博物志</div>
        <div class="book-container">
          <div class="book-pages" id="bookPages">
            <div class="book-page active" data-index="0"><img src="img/01.jpg" alt="博物志 01"></div>
            <div class="book-page" data-index="1"><img src="img/02.jpg" alt="博物志 02"></div>
            <div class="book-page" data-index="2"><img src="img/03.jpg" alt="博物志 03"></div>
            <div class="book-page" data-index="3"><img src="img/04.jpg" alt="博物志 04"></div>
            <div class="book-page" data-index="4"><img src="img/05.jpg" alt="博物志 05"></div>
            <div class="book-page" data-index="5"><img src="img/06.jpg" alt="博物志 06"></div>
            <div class="book-page" data-index="6"><img src="img/07.jpg" alt="博物志 07"></div>
            <div class="book-page" data-index="7"><img src="img/08.jpg" alt="博物志 08"></div>
          </div>
          <div class="book-controls">
            <button id="prevPage" disabled><i class="fas fa-chevron-left"></i></button>
            <span class="page-indicator" id="pageIndicator">1 / 8</span>
            <button id="nextPage"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
        <p style="text-align:center;color:#94a3b8;font-size:0.9rem;margin-top:0.8rem;">
          <i class="fas fa-info-circle"></i> 将您的图片命名为 01.jpg ~ 08.jpg 放在 img/ 文件夹下。
        </p>
      </div>
    </section>

    <!-- 周常 -->
    <section class="page" id="page-weekly">
      <div class="card">
        <div class="card-title"><i class="fas fa-calendar-week"></i>周常 · 化学挑战</div>
        <div id="weeklyContainer">
          <div class="weekly-cards" id="weeklyCards"></div>
        </div>
      </div>
    </section>

    <!-- 账号 -->
    <section class="page" id="page-account">
      <div class="card" style="max-width:560px; margin:0 auto;">
        <div class="card-title" style="justify-content:center;"><i class="fas fa-user-circle"></i>账号中心</div>
        <div class="user-profile" id="userProfile">
          <div class="avatar-wrap" id="avatarWrap">
            <div class="avatar-placeholder" id="avatarPlaceholder"><i class="fas fa-user"></i></div>
            <img id="avatarImg" style="display:none;" alt="头像">
            <div class="upload-hint"><i class="fas fa-camera"></i></div>
          </div>
          <input type="file" id="avatarInput" accept="image/*">
          <div class="user-email" id="profileEmail">user@example.com</div>
          <div class="user-qq" id="profileQQ">QQ: --</div>
          <div class="user-role" id="profileRole">MARKER</div>
          <div class="btn-group">
            <button class="btn-logout" id="logoutBtn"><i class="fas fa-sign-out-alt"></i> 退出</button>
            <a href="admin.php" class="btn-admin" id="adminBtn" style="display:none;"><i class="fas fa-tools"></i> 进入后台</a>
          </div>
        </div>
        <div class="auth-tabs" id="authTabs">
          <button class="active" data-tab="login">登录</button>
          <button data-tab="register">注册</button>
        </div>
        <!-- 登录 -->
        <form class="auth-form active" id="loginForm">
          <div class="form-error" id="loginError"></div>
          <div class="form-success" id="loginSuccess"></div>
          <label>用户名 / 邮箱</label>
          <div class="input-group"><i class="fas fa-user"></i><input type="text" id="loginUsername" placeholder="请输入用户名或邮箱" required /></div>
          <label>密码</label>
          <div class="input-group"><i class="fas fa-lock"></i><input type="password" id="loginPassword" placeholder="请输入密码" required /></div>
          <button type="submit" class="btn-primary">登录</button>
        </form>
        <!-- 注册 -->
        <form class="auth-form" id="registerForm">
          <div class="form-error" id="registerError"></div>
          <div class="form-success" id="registerSuccess"></div>
          <label>用户名（唯一）</label>
          <div class="input-group"><i class="fas fa-user"></i><input type="text" id="regUsername" placeholder="请设置用户名" required /></div>
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

  <footer class="footer"><p>© 2026 <span>LunaticCho</span> · 化学联考平台</p></footer>
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
        async register(username, email, password, qq) {
            return this._request('register', { username, email, password, qq });
        },
        async getCurrentUser() {
            return this._request('get_user');
        },
        async logout() {
            return this._request('logout');
        },
        async getWeekly() {
            return this._request('get_weekly');
        },
        async updateAvatar(avatar) {
            return this._request('update_avatar', { avatar });
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

      // ========== 头像上传 ==========
      function handleAvatarUpload(file) {
        if (!file) return;
        if (!file.type.startsWith('image/')) {
          showToast('请上传图片文件', 'error');
          return;
        }
        const maxSize = 2 * 1024 * 1024;
        if (file.size > maxSize) {
          showToast('图片不能超过2MB', 'error');
          return;
        }
        const reader = new FileReader();
        reader.onload = async function(e) {
          const base64 = e.target.result;
          try {
            const res = await API.updateAvatar(base64);
            if (res.code === 0) {
              showToast('头像更新成功', 'success');
              // 更新显示
              const img = $('#avatarImg');
              const placeholder = $('#avatarPlaceholder');
              img.src = base64;
              img.style.display = 'block';
              placeholder.style.display = 'none';
              if (currentUser) currentUser.avatar = base64;
            } else {
              showToast(res.message || '上传失败', 'error');
            }
          } catch (err) {
            showToast(err.message || '上传失败', 'error');
          }
        };
        reader.readAsDataURL(file);
      }

      // ========== 用户状态更新 ==========
      function updateUIForUser(user) {
        currentUser = user;
        const profile = $('#userProfile');
        const tabs = $('#authTabs');
        const forms = $$('.auth-form');
        const adminBtn = $('#adminBtn');
        const roleLabel = $('#profileRole');

        if (user) {
          profile.classList.add('active');
          tabs.style.display = 'none';
          forms.forEach(f => f.style.display = 'none');

          $('#profileEmail').textContent = user.username + ' (' + user.email + ')';
          $('#profileQQ').textContent = 'QQ: ' + (user.qq || '未设置');

          // 角色显示
          const roleMap = { 'ADMIN': '管理员', 'TEACHER': '教师', 'MARKER': '学生' };
          roleLabel.textContent = roleMap[user.role] || user.role;

          // 头像
          const img = $('#avatarImg');
          const placeholder = $('#avatarPlaceholder');
          if (user.avatar) {
            img.src = user.avatar;
            img.style.display = 'block';
            placeholder.style.display = 'none';
          } else {
            img.style.display = 'none';
            placeholder.style.display = 'flex';
          }

          // 后台按钮：teacher/admin 可见
          if (user.role === 'ADMIN' || user.role === 'TEACHER') {
            adminBtn.style.display = 'inline-block';
          } else {
            adminBtn.style.display = 'none';
          }
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
          adminBtn.style.display = 'none';
        }
        loadWeekly();
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
        const username = $('#loginUsername').value.trim();
        const password = $('#loginPassword').value.trim();
        const err = $('#loginError');
        const suc = $('#loginSuccess');
        err.style.display = 'none';
        suc.style.display = 'none';
        if (!username || !password) { err.textContent = '请填写完整'; err.style.display = 'block'; return; }
        try {
          const res = await API.login(username, password);
          if (res.code === 0) {
            suc.textContent = '✅ 登录成功';
            suc.style.display = 'block';
            showToast('欢迎回来', 'success');
            $('#loginUsername').value = '';
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
        const username = $('#regUsername').value.trim();
        const email = $('#regEmail').value.trim();
        const password = $('#regPassword').value.trim();
        const qq = $('#regQQ').value.trim();
        const err = $('#registerError');
        const suc = $('#registerSuccess');
        err.style.display = 'none';
        suc.style.display = 'none';
        if (!username || !email || !password) {
          err.textContent = '用户名、邮箱和密码为必填项';
          err.style.display = 'block';
          return;
        }
        if (password.length < 6) {
          err.textContent = '密码至少6位';
          err.style.display = 'block';
          return;
        }
        try {
          const res = await API.register(username, email, password, qq);
          if (res.code === 0) {
            suc.textContent = '🎉 ' + res.message;
            suc.style.display = 'block';
            showToast('注册成功，请登录', 'success');
            $('#regUsername').value = '';
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

      // 头像上传点击
      $('#avatarWrap').addEventListener('click', function() {
        if (!currentUser) {
          showToast('请先登录', 'error');
          return;
        }
        $('#avatarInput').click();
      });

      $('#avatarInput').addEventListener('change', function() {
        if (this.files.length > 0) {
          handleAvatarUpload(this.files[0]);
        }
        this.value = '';
      });

      // ========== 周常加载 ==========
      async function loadWeekly() {
        const container = $('#weeklyCards');
        try {
          const res = await API.getWeekly();
          if (res.code === 0 && res.data.length > 0) {
            let html = '';
            res.data.forEach((item) => {
              const optionsHtml = item.options.map((opt, i) => {
                const letter = String.fromCharCode(65 + i);
                return `<label><input type="radio" name="q_${item.id}" value="${letter}"> ${opt}</label>`;
              }).join('');
              html += `
                <div class="weekly-card" data-id="${item.id}">
                  <div class="title">📅 ${item.title}</div>
                  <div class="meta">
                    <span>👩‍🏫 ${item.teacher}</span>
                    <span>📅 ${new Date(item.created_at).toLocaleDateString()}</span>
                  </div>
                  <div class="weekly-detail">
                    <div class="content">${item.content}</div>
                    <div class="options">${optionsHtml}</div>
                    <button class="answer-btn" data-id="${item.id}" data-answer="${item.answer}">查看答案</button>
                    <div class="feedback" id="feedback_${item.id}"></div>
                  </div>
                </div>
              `;
            });
            container.innerHTML = html;

            container.querySelectorAll('.weekly-card').forEach(card => {
              const detail = card.querySelector('.weekly-detail');
              card.addEventListener('click', function(e) {
                if (e.target.closest('.answer-btn') || e.target.closest('input')) return;
                const isOpen = detail.classList.contains('open');
                container.querySelectorAll('.weekly-detail').forEach(d => d.classList.remove('open'));
                if (!isOpen) {
                  detail.classList.add('open');
                }
              });
            });

            container.querySelectorAll('.answer-btn').forEach(btn => {
              btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const id = this.dataset.id;
                const correct = this.dataset.answer;
                const feedback = document.getElementById('feedback_' + id);
                const selected = document.querySelector(`input[name="q_${id}"]:checked`);
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
          } else {
            container.innerHTML = '<p style="color:#94a3b8; text-align:center; padding:1rem 0;">暂无周常题目，请关注后续更新。</p>';
          }
        } catch (e) {
          container.innerHTML = '<p style="color:#b91c1c;">加载失败，请稍后重试。</p>';
        }
      }

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

      // ========== 启动 ==========
      initUser();

      document.addEventListener('click', (e) => {
        if (!e.target.closest('.navbar')) navList.classList.remove('open');
      });

      console.log('LunaticCho 前台启动');
    })();
  </script>
</body>
</html>
