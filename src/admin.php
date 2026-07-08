<?php
/**
 * LunaticChO 管理后台 - 包含报名管理
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (!in_array($_SESSION['role'], ['ADMIN', 'TEACHER'])) {
    die('您没有权限访问管理后台。');
}

// 创建上传目录
if (!is_dir('uploads')) {
    mkdir('uploads', 0755, true);
}

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
        die('数据库连接失败：' . $e->getMessage());
    }
}
$pdo = gsk_config();

// 处理 POST 请求（与之前相同，略）

// ========== 获取数据 ==========
$exam_id = $_GET['exam_id'] ?? 0;

// 考试列表、题目、阅卷等（与之前相同，略）

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LunaticChO 管理后台</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* 样式与之前相同，略 */
    </style>
</head>
<body>
<div class="container">
    <h1>
        <i class="fas fa-tools"></i> LunaticChO 管理后台
        <span style="font-size:0.9rem; font-weight:400; margin-left:auto;">
            欢迎，<?= htmlspecialchars($_SESSION['username']) ?>
            <a href="index.php?action=logout" onclick="return confirm('确认退出？')">退出</a>
        </span>
    </h1>

    <?php if ($msg): ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- 考试列表、出题、阅卷等（与之前相同，略） -->

    <!-- ===== 报名管理 ===== -->
    <div class="section">
        <h2><i class="fas fa-clipboard-list"></i> 报名管理</h2>
        <div id="signupList">
            <p style="color:#94a3b8;">加载中...</p>
        </div>
    </div>

</div>

<script>
    // 加载报名列表
    async function loadSignups() {
        const container = document.getElementById('signupList');
        try {
            const res = await fetch('?action=admin_get_signups');
            const data = await res.json();
            if (data.code !== 0) {
                container.innerHTML = `<p style="color:#b91c1c;">加载失败：${data.message}</p>`;
                return;
            }
            const signups = data.data;
            if (!signups || signups.length === 0) {
                container.innerHTML = '<p style="color:#94a3b8;">暂无报名记录</p>';
                return;
            }
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>考试</th>
                            <th>学生</th>
                            <th>姓名</th>
                            <th>学号</th>
                            <th>班级</th>
                            <th>手机</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            for (const s of signups) {
                const statusMap = {
                    'pending': '<span class="badge badge-grading">待审核</span>',
                    'approved': '<span class="badge badge-published">已通过</span>',
                    'rejected': '<span class="badge badge-draft">已拒绝</span>'
                };
                html += `
                    <tr>
                        <td>${s.id}</td>
                        <td>${s.exam_title}</td>
                        <td>${s.username}</td>
                        <td>${s.student_name}</td>
                        <td>${s.student_id}</td>
                        <td>${s.class}</td>
                        <td>${s.phone}</td>
                        <td>${statusMap[s.status] || s.status}</td>
                        <td>
                            ${s.status === 'pending' ? `
                                <button class="btn btn-success btn-sm" onclick="reviewSignup(${s.id}, 'approved')">通过</button>
                                <button class="btn btn-danger btn-sm" onclick="reviewSignup(${s.id}, 'rejected')">拒绝</button>
                            ` : '-'}
                        </td>
                    </tr>
                `;
            }
            html += '</tbody></table>';
            container.innerHTML = html;
        } catch (e) {
            container.innerHTML = '<p style="color:#b91c1c;">加载失败，请刷新</p>';
            console.error(e);
        }
    }

    async function reviewSignup(id, status) {
        if (!confirm('确认将此报名状态改为 ' + (status === 'approved' ? '通过' : '拒绝') + ' 吗？')) return;
        try {
            const res = await fetch('?action=admin_review_signup', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ signup_id: id, status: status })
            });
            const data = await res.json();
            if (data.code === 0) {
                alert('审核成功');
                loadSignups();
            } else {
                alert('审核失败：' + data.message);
            }
        } catch (e) {
            alert('网络错误');
        }
    }

    // 页面加载后加载报名列表
    document.addEventListener('DOMContentLoaded', loadSignups);
</script>
</body>
</html>
