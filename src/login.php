<?php
session_start();

// 如果已经登录，直接跳转到主页
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// 数据库配置
$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';
$db_pass = 'yourpasswd'; // 请修改为实际密码

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? ''); // 用户名或邮箱
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = '请输入用户名/邮箱和密码';
    } else {
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 查询用户（通过用户名或邮箱），同时获取角色和状态
            $stmt = $pdo->prepare("SELECT id, username, password_hash, role, status FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // 检查用户状态
                if ($user['status'] === 'pending') {
                    $error = '您的账号正在等待管理员审核，请耐心等待。';
                } elseif ($user['status'] === 'rejected') {
                    $error = '您的账号审核未通过，无法登录。如有疑问，请联系管理员。';
                } elseif ($user['status'] === 'approved') {
                    // 检查是否被封禁
                    if ($user['role'] === 'banned') {
                        $error = '您的账号已被封禁，请联系管理员';
                    } else {
                        // 登录成功，设置 Session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];
                        header('Location: index.php');
                        exit;
                    }
                } else {
                    $error = '账号状态异常，请联系管理员';
                }
            } else {
                $error = '用户名/邮箱或密码错误';
            }
        } catch (PDOException $e) {
            $error = '数据库连接失败，请稍后重试';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>lv8girl · 登录</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
        .login-wrapper {
            max-width: 400px;
            width: 100%;
        }
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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-soft);
            font-size: 0.9rem;
        }
        input {
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
        .forgot-password {
            text-align: right;
            margin-top: 5px;
        }
        .forgot-password a {
            color: var(--text-hint);
            text-decoration: none;
            font-size: 0.85rem;
        }
        .forgot-password a:hover {
            color: var(--accent);
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="header">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div class="theme-toggle" id="themeToggle">🌓</div>
        </div>

        <div class="card">
            <h2>登录</h2>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="form-group">
                    <label>用户名 / 邮箱</label>
                    <input type="text" name="login" placeholder="请输入用户名或邮箱" required value="<?php echo isset($_POST['login']) ? htmlspecialchars($_POST['login']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" placeholder="请输入密码" required>
                </div>

                <div class="forgot-password">
                    <a href="#">忘记密码？</a>
                </div>

                <button type="submit" class="btn">登 录</button>
            </form>

            <div class="footer-text">
                还没有账号？ <a href="register.php">立即注册</a>
            </div>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            themeToggle.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌓';
        });
    </script>
</body>
</html>
