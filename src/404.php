<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 · 页面不见了 - lv8girl</title>
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

        .container {
            max-width: 600px;
            width: 100%;
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

        .error-card {
            background: var(--bg-surface);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 50px 40px;
            text-align: center;
        }

        .error-code {
            font-size: 8rem;
            font-weight: var(--font-weight-black);
            background: linear-gradient(135deg, var(--primary), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 10px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0); }
        }

        .mascot {
            font-size: 5rem;
            margin-bottom: 20px;
            filter: drop-shadow(0 8px 0 var(--primary-dark));
        }

        .error-title {
            font-size: 2rem;
            font-weight: var(--font-weight-black);
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .error-message {
            color: var(--text-secondary);
            font-size: 1.1rem;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .action-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            background: linear-gradient(135deg, var(--primary), var(--accent-blue));
            border: none;
            border-radius: 50px;
            padding: 14px 35px;
            color: white;
            font-weight: var(--font-weight-bold);
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 0 var(--primary-dark);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 0 var(--primary-dark);
            background: linear-gradient(135deg, var(--primary-light), var(--accent-purple));
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
            box-shadow: none;
        }

        .btn-outline:hover {
            background: var(--primary-light);
            color: white;
            border-color: var(--primary-light);
        }

        .footer-links {
            margin-top: 30px;
            text-align: center;
        }

        .footer-links a {
            color: var(--text-hint);
            text-decoration: none;
            margin: 0 15px;
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        @media screen and (max-width: 480px) {
            .error-card {
                padding: 30px 20px;
            }
            .error-code {
                font-size: 6rem;
            }
            .mascot {
                font-size: 4rem;
            }
            .error-title {
                font-size: 1.6rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="mini-nav">
            <div class="logo">lv8girl<span>404</span></div>
            <div class="theme-toggle" id="themeToggle">🌓</div>
        </div>

        <div class="error-card">
            <div class="mascot">🍀</div>
            <div class="error-code">404</div>
            <div class="error-title">哎呀！页面走丢了</div>
            <div class="error-message">
                绿坝娘找遍了每一个角落，也没找到你要的页面。<br>
                可能是地址输错了，或者页面已经被移走了。
            </div>
            <div class="action-buttons">
                <a href="index.php" class="btn">返回首页</a>
                <a href="javascript:history.back()" class="btn btn-outline">返回上一页</a>
            </div>
        </div>

        <div class="footer-links">
            <a href="index.php">首页</a>
            <a href="#">番剧</a>
            <a href="#">漫画</a>
            <a href="#">游戏</a>
            <a href="#">帮助</a>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;

        // 检查本地存储主题
        if (localStorage.getItem('dark-mode') === 'true') {
            body.classList.add('dark-mode');
            themeToggle.textContent = '☀️';
        }

        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            const isDark = body.classList.contains('dark-mode');
            themeToggle.textContent = isDark ? '☀️' : '🌓';
            localStorage.setItem('dark-mode', isDark);
        });
    </script>
</body>
</html>