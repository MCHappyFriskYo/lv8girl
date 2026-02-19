<?php
session_start();

// 如果已经登录，直接跳转
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';
$db_pass = 'yourpasswd';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $agree = isset($_POST['agree']);

    $errors = [];
    // ... 原有的输入验证代码保持不变 ...
    // 验证用户名长度、邮箱格式、密码长度、一致性、同意协议等

    if (empty($errors)) {
        // 检查用户名或邮箱是否已存在
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = '用户名或邮箱已被注册';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            // 插入用户，状态默认为 'pending'
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$username, $email, $password_hash]);
            $success = '注册成功！您的账号正在等待管理员审核，请耐心等待。';
        }
    }
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>lv8girl · 注册</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* 默认浅色模式变量 */
        :root {
            --bg: #f0f7f0;
            --surface: #ffffff;
            --surface-light: #e8f0e8;
            --border: #d0e0d0;
            --border-light: #e0e8e0;
            --text: #2c3e2c;
            --text-soft: #5f6b5f;
            --text-hint: #8f9f8f;
            --primary: #3d9e4a;
            --primary-light: #6abf6e;
            --accent: #ffb347;
            --accent-dark: #d9a066;
            --gradient: linear-gradient(135deg, #3d9e4a, #5a9cff);
        }

        /* 深色模式变量 */
        body.dark-mode {
            --bg: #1a1e1a;
            --surface: #1e261e;
            --surface-light: #2a3a2a;
            --border: #2a3a2a;
            --border-light: #3a4a3a;
            --text: #e0e8e0;
            --text-soft: #b0bcb0;
            --text-hint: #8a958a;
            --primary: #6b8e6b;
            --primary-light: #8aad8a;
            --accent: #ffb347;
            --accent-dark: #d9a066;
            --gradient: linear-gradient(135deg, #6b8e6b, #5a9cff);
        }

        body {
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            transition: background 0.3s, color 0.3s;
        }

        .register-wrapper {
            max-width: 420px;
            width: 100%;
        }

        /* 头部 */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo span {
            font-size: 0.9rem;
            background: var(--accent);
            color: var(--surface);
            padding: 4px 12px;
            border-radius: 30px;
            margin-left: 10px;
            -webkit-text-fill-color: var(--surface);
        }

        .theme-toggle {
            background: var(--surface-light);
            border: none;
            color: var(--text);
            font-size: 1.3rem;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }

        .theme-toggle:hover {
            background: var(--accent);
            color: var(--surface);
        }

        /* 卡片 */
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 30px 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }

        .card h2 {
            text-align: center;
            margin-bottom: 25px;
            color: var(--accent);
            font-weight: 600;
        }

        .error-message {
            background: var(--surface-light);
            border-left: 4px solid #ff6b6b;
            color: var(--text-soft);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .success-message {
            background: var(--surface-light);
            border-left: 4px solid var(--primary);
            color: var(--text-soft);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-soft);
            font-size: 0.9rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 30px;
            color: var(--text);
            font-size: 1rem;
            outline: none;
            transition: border 0.2s;
        }

        input:focus {
            border-color: var(--accent);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }

        .checkbox-group label {
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        .checkbox-group a {
            color: var(--accent);
            text-decoration: none;
        }

        .checkbox-group a:hover {
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: var(--gradient);
            border: none;
            border-radius: 40px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
            margin-top: 10px;
        }

        .btn:hover {
            transform: scale(1.02);
        }

        .footer-text {
            text-align: center;
            margin-top: 25px;
            color: var(--text-hint);
        }

        .footer-text a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-wrapper">
        <!-- 头部：logo + 主题切换 -->
        <div class="header">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div class="theme-toggle" id="themeToggle">🌓</div>
        </div>

        <!-- 注册卡片 -->
        <div class="card">
            <h2>注册</h2>

            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php else: ?>
                <form method="post">
                    <div class="form-group">
                        <label>用户名</label>
                        <input type="text" name="username" placeholder="3-20个字符，支持中文、字母、数字、下划线" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>邮箱</label>
                        <input type="email" name="email" placeholder="请输入有效邮箱" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label>密码</label>
                        <input type="password" name="password" placeholder="至少6位" required>
                    </div>

                    <div class="form-group">
                        <label>确认密码</label>
                        <input type="password" name="confirm_password" placeholder="请再次输入密码" required>
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" name="agree" id="agree" required>
                        <label for="agree">我已阅读并同意 <a href="#">用户协议</a> 和 <a href="#">隐私政策</a></label>
                    </div>

                    <button type="submit" class="btn">注 册</button>
                </form>

                <div class="footer-text">
                    已有账号？ <a href="login.php">去登录</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            if (document.body.classList.contains('dark-mode')) {
                themeToggle.textContent = '☀️';
            } else {
                themeToggle.textContent = '🌓';
            }
        });
    </script>
</body>

</html>
