<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';               // 数据库用户名
$db_pass = 'yourpasswd';        // 数据库密码（已修改）

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败：' . $e->getMessage());
}

// 获取所有新番列表用于下拉
$stmt = $pdo->query("SELECT id, title FROM anime ORDER BY created_at DESC");
$anime_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 如果从新番详情页跳转过来，预选该新番
$selected_anime = isset($_GET['anime_id']) ? (int)$_GET['anime_id'] : 0;
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发表新讨论 - lv8girl</title>
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

        .post-wrapper {
            width: 100%;
            max-width: 700px;
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

        .post-card {
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

        h2 {
            font-size: 1.8rem;
            font-weight: var(--font-weight-black);
            text-align: center;
            margin-bottom: 30px;
            background: linear-gradient(135deg, var(--primary), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .post-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 1rem;
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
        }

        .form-group input[type="text"],
        .form-group textarea,
        .form-group select,
        .form-group input[type="file"] {
            background: var(--input-bg);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 12px 20px;
            font-size: 1rem;
            color: var(--text-primary);
            transition: all 0.2s;
            outline: none;
            width: 100%;
        }

        .form-group textarea {
            border-radius: 20px;
            resize: vertical;
            min-height: 150px;
        }

        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%232c3e2c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 16px;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(61, 158, 74, 0.2);
        }

        .form-group input[type="file"] {
            padding: 10px;
            background: var(--bg-surface);
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
        }

        .auth-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: var(--font-weight-bold);
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .error-message {
            background: var(--accent-pink);
            color: white;
            padding: 12px;
            border-radius: 30px;
            text-align: center;
            margin-bottom: 20px;
        }

        @media screen and (max-width: 768px) {
            .post-wrapper {
                padding: 10px;
            }
            h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="post-wrapper">
        <div class="mini-nav">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div class="theme-toggle" id="themeToggle">🌓</div>
        </div>

        <div class="post-card">
            <div class="mascot">🍀</div>
            <h2>发表新讨论</h2>
            <?php
            if (isset($_GET['error'])) {
                echo '<div class="error-message">' . htmlspecialchars($_GET['error']) . '</div>';
            }
            ?>
            <form action="submit_discussion.php" method="post" enctype="multipart/form-data" class="post-form">
                <div class="form-group">
                    <label>标题</label>
                    <input type="text" name="title" placeholder="请输入标题" required>
                </div>
                <div class="form-group">
                    <label>内容</label>
                    <textarea name="content" placeholder="请输入讨论内容..." required></textarea>
                </div>
                <div class="form-group">
                    <label>关联新番（可选）</label>
                    <select name="anime_id">
                        <option value="">-- 不关联新番 --</option>
                        <?php foreach ($anime_list as $anime): ?>
                        <option value="<?php echo $anime['id']; ?>" <?php echo $selected_anime == $anime['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($anime['title']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>上传图片（可选）</label>
                    <input type="file" name="image" accept="image/*">
                </div>
                <button type="submit" class="btn-primary">发表讨论</button>
            </form>
            <div class="auth-footer">
                <a href="index.php">返回首页</a>
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