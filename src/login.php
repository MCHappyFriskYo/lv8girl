<?php
session_start();
// 如果已经登录，直接跳转到主页
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? ''); // 用户名或邮箱
    $password = $_POST['password'] ?? '';

    if (empty($login) || empty($password)) {
        $error = '请输入用户名/邮箱和密码';
    } else {
        // 连接数据库
        $host = 'db';
        $dbname = 'lv8girl';
        $username_db = 'lv8girl';               // 数据库用户名
        $password_db = 'yourpasswd';        // 数据库密码（已修改）

        try {
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // 查询用户（通过用户名或邮箱）
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                // 登录成功
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                header('Location: index.php');
                exit;
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>lv8girl · 登录</title>
    <!-- 引入字体 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Noto Sans SC', 'PingFang SC', 'Microsoft YaHei', 'Helvetica Neue', Arial, sans-serif;
        }

        :root {
            --primary: #3d9e4a;
            --primary-dark: #2e7d32;
            --primary-light: #6abf6e;
            --secondary: #ffb347;
            --accent-blue: #5a9cff;
            --accent-purple: #b47aff;
            --accent-pink: #ff7b9c;
            --bg-body: linear-gradient(145deg, #f0f7f0 0%, #e6f0e6 100%);
            --bg-surface: #ffffff;
            --bg-nav: rgba(255,255,255,0.9);
            --text-primary: #2c3e2c;
            --text-secondary: #5f6b5f;
            --text-hint: #8f9f8f;
            --border-light: #e6ede6;
            --border: #d0e0d0;
            --shadow: 0 8px 20px rgba(0,0,0,0.06);
            --hover-shadow: 0 12px 28px rgba(61, 158, 74, 0.2);
            --input-bg: #f5faf5;
            
            --font-weight-light: 400;
            --font-weight-regular: 500;
            --font-weight-bold: 700;
            --font-weight-black: 900;
        }

        body.dark-mode {
            --primary: #6b8e6b;
            --primary-dark: #4a6b4a;
            --primary-light: #8aad8a;
            --secondary: #d9a066;
            --accent-blue: #6688aa;
            --accent-purple: #8a7a9c;
            --accent-pink: #b57a8a;
            --bg-body: linear-gradient(145deg, #1e261e 0%, #232d23 100%);
            --bg-surface: #2c342c;
            --bg-nav: #2c342ccc;
            --text-primary: #e0e8e0;
            --text-secondary: #b0bcb0;
            --text-hint: #8a958a;
            --border-light: #3a453a;
            --border: #4d5a4d;
            --shadow: 0 8px 20px rgba(0,0,0,0.5);
            --hover-shadow: 0 12px 28px rgba(107, 142, 107, 0.3);
            --input-bg: #3a453a;
        }

        body {
            background: var(--bg-body);
            min-height: 100vh;
            transition: background 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
        }

        .mini-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: var(--font-weight-black);
            background: linear-gradient(135deg, var(--primary), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
        }

        .logo span {
            font-size: 0.8rem;
            background: var(--secondary);
            color: var(--primary-dark);
            padding: 4px 10px;
            border-radius: 30px;
            margin-left: 10px;
            font-weight: var(--font-weight-bold);
            -webkit-text-fill-color: var(--primary-dark);
        }

        .theme-toggle {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            color: var(--text-primary);
            font-size: 1.3rem;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .theme-toggle:hover {
            background: linear-gradient(135deg, var(--secondary), var(--accent-pink));
            color: var(--primary-dark);
            transform: rotate(15deg) scale(1.1);
        }

        .auth-card {
            background: var(--bg-surface);
            backdrop-filter: blur(10px);
            border-radius: 32px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 32px;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .mascot {
            text-align: center;
            margin-bottom: 20px;
            font-size: 3rem;
            filter: drop-shadow(0 8px 0 var(--primary-dark));
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
            100% { transform: translateY(0); }
        }

        .message {
            padding: 12px 20px;
            border-radius: 30px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: var(--font-weight-regular);
        }

        .message.error {
            background: var(--accent-pink);
            color: white;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 0.9rem;
            font-weight: var(--font-weight-regular);
            color: var(--text-secondary);
        }

        .form-group input {
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 12px 20px;
            font-size: 1rem;
            color: var(--text-primary);
            transition: all 0.2s;
            outline: none;
        }

        .form-group input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(61, 158, 74, 0.2);
        }

        .form-group input::placeholder {
            color: var(--text-hint);
            font-weight: var(--font-weight-light);
        }

        .forgot-password {
            text-align: right;
            margin-top: -10px;
        }

        .forgot-password a {
            color: var(--text-hint);
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
        }

        .forgot-password a:hover {
            color: var(--primary);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent-blue));
            border: none;
            border-radius: 40px;
            padding: 14px;
            color: white;
            font-weight: var(--font-weight-bold);
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 10px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-light), var(--accent-purple));
            transform: scale(1.02);
            box-shadow: var(--hover-shadow);
        }

        .auth-footer {
            text-align: center;
            margin-top: 20px;
            color: var(--text-hint);
            font-size: 0.9rem;
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: var(--font-weight-bold);
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        @media screen and (max-width: 480px) {
            .auth-card {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="mini-nav">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div class="theme-toggle" id="themeToggle">🌓</div>
        </div>

        <div class="auth-card">
            <div class="mascot">🍀</div>
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post" class="auth-form">
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
                <button type="submit" class="btn-primary">登 录</button>
            </form>
            <div class="auth-footer">
                还没有账号？ <a href="register.html">立即注册</a>
            </div>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            themeToggle.textContent = body.classList.contains('dark-mode') ? '☀️' : '🌓';
        });
    </script>
</body>
</html>