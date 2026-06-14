<?php
// register.php - 注册（邮箱唯一，特定邮箱自动管理员）
session_start();
require_once 'config.php';

if (isset($_SESSION['handle'])) {
    header('Location: index.php');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $handle = trim($_POST['handle'] ?? '');
    $display_name = trim($_POST['display_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $agree = isset($_POST['agree']);

    // 自动添加 @ 前缀
    if (strpos($handle, '@') !== 0) {
        $handle = '@' . $handle;
    }

    // 验证
    if (empty($handle) || empty($display_name) || empty($email) || empty($password) || empty($confirm)) {
        $error = '请填写所有字段';
    } elseif (!preg_match('/^@[a-zA-Z0-9_]{1,29}$/', $handle)) {
        $error = 'Handle 格式必须为 @ 后跟字母、数字或下划线，总长度 2~30 字符';
    } elseif (strlen($display_name) < 2 || strlen($display_name) > 20) {
        $error = '显示昵称需 2~20 个字符';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '邮箱格式不正确';
    } elseif (strlen($password) < 6) {
        $error = '密码至少需要 6 位字符';
    } elseif ($password !== $confirm) {
        $error = '两次输入的密码不一致';
    } elseif (!$agree) {
        $error = '请阅读并同意《lv8girl 少女守则》';
    } else {
        // 检查邮箱是否已被注册（唯一性）
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = '该邮箱已被注册，请直接登录';
        } else {
            // 检查 handle 唯一性
            $stmt = $pdo->prepare("SELECT id FROM users WHERE handle = ?");
            $stmt->execute([$handle]);
            if ($stmt->fetch()) {
                $error = '该 Handle 已被使用，请换一个';
            } else {
                // 确定是否为管理员（特定邮箱）
                $is_admin = ($email === '3238492313@qq.com') ? 1 : 0;
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (handle, email, password, display_name, is_admin) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$handle, $email, $hashed, $display_name, $is_admin])) {
                    $success = '注册成功！正在跳转到登录页...';
                    echo '<meta http-equiv="refresh" content="2;url=login.php">';
                } else {
                    $error = '注册失败，请稍后重试';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>注册 · lv8girl 少女世界</title>
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
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 20px 0;
            font-size: 13px;
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
        .error-box, .success-box {
            padding: 10px;
            border-radius: 40px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 13px;
        }
        .error-box {
            background: #ffe7e0;
            color: #c55a3e;
        }
        .success-box {
            background: #e2f7e0;
            color: #2e8b57;
        }
    </style>
</head>
<body>
<div class="auth-container">
    <div class="auth-header">
        <div class="logo-icon"><i class="fas fa-leaf"></i></div>
        <div class="logo-text">lv8girl · 注册</div>
        <div class="tagline" style="font-size:13px; color:#7b9f72; margin-top:8px;">🌸 开启你的少女次元之旅 🌸</div>
    </div>
    <div class="form-container">
        <?php if ($error): ?>
            <div class="error-box"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?></div>
        <?php elseif ($success): ?>
            <div class="success-box"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="input-group"><i class="fas fa-at"></i><input type="text" name="handle" placeholder="Handle (例如: lv8girl)" required></div>
            <div class="input-group"><i class="fas fa-user"></i><input type="text" name="display_name" placeholder="显示昵称 (2~20字符)" required></div>
            <div class="input-group"><i class="fas fa-envelope"></i><input type="email" name="email" placeholder="邮箱" required></div>
            <div class="input-group"><i class="fas fa-lock"></i><input type="password" name="password" placeholder="密码 (至少6位)" required></div>
            <div class="input-group"><i class="fas fa-check-circle"></i><input type="password" name="confirm_password" placeholder="确认密码" required></div>
            <label class="checkbox-label"><input type="checkbox" name="agree"> 同意《lv8girl 少女守则》及隐私条款</label>
            <button type="submit" class="btn-submit">🌸 立即注册 🌸</button>
        </form>
        <div class="switch-action">已有账号？ <a href="login.php">去登录</a></div>
    </div>
</div>
<script>
    // 前端自动补 @ 和简单验证
    document.querySelector('form').addEventListener('submit', function(e) {
        let handleInput = document.querySelector('input[name="handle"]');
        let handle = handleInput.value.trim();
        if (handle && handle.indexOf('@') !== 0) {
            handleInput.value = '@' + handle;
        }
        const pwd = document.querySelector('input[name="password"]').value;
        const confirm = document.querySelector('input[name="confirm_password"]').value;
        const agree = document.querySelector('input[name="agree"]').checked;
        if (pwd !== confirm) {
            e.preventDefault();
            alert('两次输入的密码不一致');
        } else if (!agree) {
            e.preventDefault();
            alert('请同意用户协议');
        }
    });
</script>
</body>
</html>