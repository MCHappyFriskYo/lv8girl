<?php
// login.php - 登录处理（合并页面，支持管理员自动提升）
session_start();
require_once 'config.php';

$error = null;

// 如果已登录则跳转主页
if (isset($_SESSION['handle'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($login) || empty($password)) {
        $error = '请输入邮箱/Handle 和密码';
    } else {
        // 判断是否为邮箱，否则当作 handle
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL);
        if ($isEmail) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$login]);
        } else {
            // 自动补 @ 前缀
            if (strpos($login, '@') !== 0) {
                $login = '@' . $login;
            }
            $stmt = $pdo->prepare("SELECT * FROM users WHERE handle = ?");
            $stmt->execute([$login]);
        }
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // 如果是特定管理员邮箱，强制设为管理员
            if ($user['email'] === '3238492313@qq.com' && $user['is_admin'] != 1) {
                $upd = $pdo->prepare("UPDATE users SET is_admin = 1 WHERE id = ?");
                $upd->execute([$user['id']]);
                $user['is_admin'] = 1;
            }
            // 存储会话
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['handle'] = $user['handle'];
            $_SESSION['display_name'] = $user['display_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['is_admin'] = (bool)$user['is_admin'];

            // 记住我（简化）
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + 86400 * 7, '/', '', false, true);
                // 生产环境应保存 token 到数据库，此处略
            }
            header('Location: index.php');
            exit;
        } else {
            $error = '邮箱/Handle 或密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>登录 · lv8girl 少女世界</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(145deg, #eaf7e4 0%, #d3e8cc 100%);
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .auth-container {
            max-width: 460px;
            width: 100%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            border-radius: 42px;
            box-shadow: 0 25px 45px -12px rgba(46,139,86,0.35);
            overflow: hidden;
            border: 1px solid rgba(100, 150, 90, 0.3);
        }
        .auth-header {
            padding: 32px 32px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(100, 150, 90, 0.2);
        }
        .logo-icon {
            background: linear-gradient(135deg, #9be29b, #50b36e);
            width: 58px;
            height: 58px;
            border-radius: 28px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .logo-icon i { font-size: 32px; color: white; }
        .logo-text {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(135deg, #5acd73, #2e7d4c);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .form-container {
            padding: 28px 32px 36px;
        }
        .input-group {
            margin-bottom: 20px;
            position: relative;
        }
        .input-group i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #8bb282;
        }
        .input-group input {
            width: 100%;
            padding: 14px 16px 14px 48px;
            border: 1px solid rgba(100, 150, 90, 0.4);
            border-radius: 60px;
            background: rgba(240, 248, 235, 0.8);
            outline: none;
            font-size: 15px;
        }
        .input-group input:focus {
            border-color: #6fcf97;
            box-shadow: 0 0 0 3px rgba(111,207,151,0.2);
            background: white;
        }
        .form-options {
            display: flex;
            justify-content: space-between;
            margin-bottom: 24px;
            font-size: 13px;
        }
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #5b8a51;
            cursor: pointer;
        }
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(95deg, #69d588, #3fa359);
            border: none;
            border-radius: 60px;
            font-weight: 700;
            font-size: 16px;
            color: white;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(70, 150, 70, 0.3);
        }
        .switch-action {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
        }
        .switch-action a {
            color: #3e9b57;
            text-decoration: none;
            font-weight: 500;
        }
        .error-box {
            background: #ffe7e0;
            color: #c55a3e;
            padding: 10px;
            border-radius: 40px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-header">
        <div class="logo-icon"><i class="fas fa-leaf"></i></div>
        <div class="logo-text">lv8girl · 登录</div>
        <div class="tagline" style="font-size:13px; color:#7b9f72; margin-top:8px;">✨ 欢迎回到少女世界 ✨</div>
    </div>
    <div class="form-container">
        <?php if ($error): ?>
            <div class="error-box"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="input-group"><i class="fas fa-envelope"></i><input type="text" name="login" placeholder="@handle 或 邮箱" required></div>
            <div class="input-group"><i class="fas fa-lock"></i><input type="password" name="password" placeholder="密码" required></div>
            <div class="form-options">
                <label class="checkbox-label"><input type="checkbox" name="remember"> 记住我</label>
            </div>
            <button type="submit" class="btn-submit">✨ 登录 ✨</button>
        </form>
        <div class="switch-action">还没有账号？ <a href="register.php">立即注册</a></div>
    </div>
</div>
</body>
</html>