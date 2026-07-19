<?php
require_once "config.php";

// 缓存头
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 登出处理
if (isset($_GET['logout'])) {
    $_SESSION = array();
    session_unset();
    session_destroy();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    header("Location: index.php");
    exit;
}

$msg = isset($_SESSION['msg']) ? $_SESSION['msg'] : '';
unset($_SESSION['msg']);

$isLogin = isset($_SESSION['uid']);
$loginName = isset($_SESSION['username']) ? $_SESSION['username'] : '';
$uid = isset($_SESSION['uid']) ? $_SESSION['uid'] : 0;
$isTeacher = false;

if ($isLogin && isset($_SESSION['role']) && $_SESSION['role'] == 1) {
    $isTeacher = true;
}

// 读取公告 修复字段错误
$noticeSql = "SELECT n.content, t.username, n.create_time FROM notice n LEFT JOIN cho_user t ON n.create_tid = t.id ORDER BY n.create_time DESC LIMIT 3";
$noticeSt = $pdo->query($noticeSql);
$noticeList = $noticeSt->fetchAll();

// 联考试卷
$examStmt = $pdo->prepare("SELECT * FROM paper WHERE paper_type = 1 AND is_publish = 1 ORDER BY create_time DESC");
$examStmt->execute();
$examList = $examStmt->fetchAll();

// 周常试卷
$weekStmt = $pdo->prepare("SELECT * FROM paper WHERE paper_type = 2 AND is_publish = 1 ORDER BY create_time DESC");
$weekStmt->execute();
$weekList = $weekStmt->fetchAll();

// 我的答题记录
$myRecords = array();
if ($isLogin) {
    $recSql = "SELECT ar.score, ar.comment, ar.submit_time, p.title, p.paper_type
    FROM answer_record ar
    LEFT JOIN paper p ON ar.paper_id = p.id
    WHERE ar.uid = ?
    ORDER BY ar.submit_time DESC";
    $recStmt = $pdo->prepare($recSql);
    $recStmt->execute([$uid]);
    $myRecords = $recStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LunaticChO - 化学竞赛平台</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css">
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                bgPage: '#f4f6f8',
                bgCard: '#ffffff',
                textMain: '#1f2937',
                textSub: '#6b7280',
                primary: '#0d9488',
                secondary: '#0ea5e9',
                borderColor: '#d1d5db'
            }
        }
    }
}
</script>
<style>
.plain-card {
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 1px 4px #eee;
}
.btn {
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 14px;
}
.btn-primary {
    background: #0d9488;
    color: white;
}
.btn-outline {
    border: 1px solid #d1d5db;
}
.nav-link-active {
    border-bottom: 2px solid #0d9488;
    color: #0d9488;
}
.page-hidden {
    display: none !important;
}
.table-cell {
    padding: 12px 16px;
    font-size: 14px;
    border-bottom: 1px solid #d1d5db;
}
body {
    background: #f4f6f8;
    color: #1f2937;
}
</style>
</head>
<body class="flex flex-col min-h-screen">
<?php if (!empty($msg)): ?>
<div class="fixed top-4 right-4 z-[200] plain-card">
    <p><?php echo htmlspecialchars($msg); ?></p>
</div>
<script>setTimeout(()=>document.querySelector('.fixed.top-4')?.remove(),2500)</script>
<?php endif; ?>

<header class="sticky top-0 z-50 bg-white border-b border-[#d1d5db]">
    <div class="container mx-auto px-4 lg:px-8">
        <nav class="flex items-center justify-between h-16">
            <a href="#" class="flex items-center gap-2 text-xl font-bold" onclick="switchPage('home')">
                <i class="fa fa-flask text-[#0d9488]"></i>
                <span>LunaticChO</span>
            </a>
            <div class="hidden md:flex items-center gap-6">
                <a class="nav-item py-1 cursor-pointer hover:text-[#0d9488]" data-page="home">主页</a>
                <a class="nav-item py-1 cursor-pointer hover:text-[#0d9488]" data-page="exam">联考（大考）</a>
                <a class="nav-item py-1 cursor-pointer hover:text-[#0d9488]" data-page="magazine">博物志｜燕石博物志</a>
                <a class="nav-item py-1 cursor-pointer hover:text-[#0d9488]" data-page="weekly">周常小测</a>
            </div>
            <?php if (!$isLogin): ?>
            <div class="hidden md:flex items-center gap-3">
                <button class="btn btn-outline" onclick="openModal('login')">登录</button>
                <button class="btn btn-primary" onclick="openModal('register')">注册</button>
            </div>
            <?php else: ?>
            <div class="hidden md:flex items-center gap-3">
                <span class="text-[#0d9488] font-medium"><?php echo htmlspecialchars($loginName); ?></span>
                <?php if ($isTeacher): ?>
                    <a href="admin.php" class="btn btn-primary">进入教师后台</a>
                <?php endif; ?>
                <a href="?logout=1" class="btn btn-outline">退出</a>
            </div>
            <?php endif; ?>
            <button class="md:hidden text-xl" onclick="toggleMobileMenu()">
                <i class="fa fa-bars"></i>
            </button>
        </nav>
        <div id="mobile-menu" class="hidden md:hidden pb-4 flex flex-col gap-3">
            <a class="nav-item py-2 border-b border-[#d1d5db] cursor-pointer" data-page="home">主页</a>
            <a class="nav-item py-2 border-b border-[#d1d5db] cursor-pointer" data-page="exam">联考（大考）</a>
            <a class="nav-item py-2 border-b border-[#d1d5db] cursor-pointer" data-page="magazine">博物志｜燕石博物志</a>
            <a class="nav-item py-2 border-b border-[#d1d5db] cursor-pointer" data-page="weekly">周常小测</a>
            <?php if (!$isLogin): ?>
            <div class="flex gap-3 mt-2">
                <button class="btn btn-outline flex-1" onclick="openModal('login')">登录</button>
                <button class="btn btn-primary flex-1" onclick="openModal('register')">注册</button>
            </div>
            <?php else: ?>
            <div class="flex flex-col gap-2 mt-2">
                <div class="flex justify-between items-center">
                    <span class="text-[#0d9488] font-medium"><?php echo htmlspecialchars($loginName); ?></span>
                    <a href="?logout=1" class="btn btn-outline">退出</a>
                </div>
                <?php if ($isTeacher): ?>
                    <a href="admin.php" class="btn btn-primary text-center">进入教师后台</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="flex-grow container mx-auto px-4 lg:px-8 py-8">
<section id="page-home" class="page">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold mb-2">LunaticChO</h1>
        <p class="text-[#6b7280] mb-6">化学竞赛学习与训练平台</p>
        <div class="plain-card mb-8">
            <h2 class="text-lg font-semibold mb-3">平台说明</h2>
            <p class="text-sm text-[#6b7280] mb-4">
                这里主要用于化学竞赛日常训练、联考模拟和社团刊物发布。
                你可以在这里参加限时小测、完成标准化模考，以及阅读《燕石博物志》。
            </p>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="p-4 bg-gray-50 rounded border border-[#d1d5db] cursor-pointer" onclick="switchPage('exam')">
                    <i class="fa fa-file-text-o text-[#0d9488] mb-2"></i>
                    <h3 class="font-medium">联考</h3>
                    <p class="text-xs text-[#6b7280]">标准化模考与真题训练</p>
                </div>
                <div class="p-4 bg-gray-50 rounded border border-[#d1d5db] cursor-pointer" onclick="switchPage('weekly')">
                    <i class="fa fa-list-alt text-[#0d9488] mb-2"></i>
                    <h3 class="font-medium">周常</h3>
                    <p class="text-xs text-[#6b7280]">每周限时小题训练</p>
                </div>
                <div class="p-4 bg-gray-50 rounded border border-[#d1d5db] cursor-pointer" onclick="switchPage('magazine')">
                    <i class="fa fa-book text-[#0d9488] mb-2"></i>
                    <h3 class="font-medium">燕石博物志</h3>
                    <p class="text-xs text-[#6b7280]">社团化学刊物</p>
                </div>
            </div>
        </div>
        <div class="plain-card">
            <h2 class="text-lg font-semibold mb-3">近期公告</h2>
            <ul class="space-y-3 text-sm">
                <?php if (empty($noticeList)): ?>
                    <li class="text-[#6b7280]">暂无公告</li>
                <?php else: ?>
                    <?php foreach ($noticeList as $nt): ?>
                    <li class="flex gap-3">
                        <span class="text-[#0d9488]">▸</span>
                        <div>
                            <span><?php echo nl2br(htmlspecialchars($nt['content'])); ?></span>
                            <p class="text-xs text-[#6b7280] mt-1">发布人：<?php echo htmlspecialchars($nt['username']); ?> · <?php echo $nt['create_time']; ?></p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</section>

<section id="page-exam" class="page page-hidden">
    <h2 class="text-2xl font-bold mb-6">联考｜标准化大考</h2>
    <div class="plain-card mb-6">
        <p class="text-sm text-[#6b7280] mb-4">
            联考页面用于教师发布完整模拟卷、限时考试。正式考试需登录账号，提交答案后教师可在线批改。
        </p>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="table-cell text-left">试卷名称</th>
                        <th class="table-cell text-left">时长</th>
                        <th class="table-cell text-left">满分</th>
                        <th class="table-cell text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($examList)): ?>
                        <tr><td colspan="4" class="table-cell text-[#6b7280]">暂无已发布联考试卷</td></tr>
                    <?php else: ?>
                        <?php foreach ($examList as $ep): ?>
                        <tr>
                            <td class="table-cell"><?php echo htmlspecialchars($ep['title']); ?></td>
                            <td class="table-cell"><?php echo $ep['time_limit']; ?> 分钟</td>
                            <td class="table-cell"><?php echo $ep['full_score']; ?></td>
                            <td class="table-cell">
                                <button class="btn btn-primary start-exam-btn" data-pid="<?php echo $ep['id']; ?>">开始模考作答</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="plain-card">
        <h3 class="text-lg font-semibold mb-3">我的联考记录</h3>
        <?php if (!$isLogin): ?>
            <p class="text-sm text-[#6b7280]">登录后查看你的考试分数、教师评语与错题。</p>
        <?php else: ?>
            <?php
            $myExamRec = array_filter($myRecords, function($v) {
                return $v['paper_type'] == 1;
            });
            if (empty($myExamRec)):
            ?>
                <p class="text-sm text-[#6b7280]">你还未完成任何联考作答</p>
            <?php else: ?>
                <div class="space-y-3">
                <?php foreach ($myExamRec as $rec): ?>
                    <div class="border border-[#d1d5db] p-3 rounded">
                        <p class="font-medium"><?php echo htmlspecialchars($rec['title']); ?></p>
                        <p class="text-sm text-[#6b7280]">提交时间：<?php echo $rec['submit_time']; ?></p>
                        <p class="text-sm mt-1">得分：<?php echo isset($rec['score']) ? $rec['score'] : '待批改'; ?></p>
                        <?php if (!empty($rec['comment'])): ?>
                            <p class="text-sm mt-1">教师评语：<?php echo htmlspecialchars($rec['comment']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<section id="page-magazine" class="page page-hidden">
    <h2 class="text-2xl font-bold mb-6">燕石博物志｜社团化学刊物</h2>
    <div class="plain-card mb-6">
        <p class="text-sm text-[#6b7280]">
            《燕石博物志》是 LunaticChO 社团内部编写的化学竞赛刊物，文档为PDF格式，点击按钮即可在线阅读。
        </p>
    </div>
    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="plain-card">
            <div class="w-full h-28 bg-gray-100 rounded flex items-center justify-center mb-3">
                <i class="fa fa-file-pdf-o text-3xl text-red-500"></i>
            </div>
            <h3 class="font-medium text-sm mb-1">燕石博物志 第一刊</h3>
            <p class="text-xs text-[#6b7280] mb-3">化学竞赛专题内容合集</p>
            <button class="btn btn-primary w-full text-xs open-pdf" data-pdf="pdf/vol1.pdf">在线阅读PDF</button>
        </div>
    </div>
</section>

<section id="page-weekly" class="page page-hidden">
    <h2 class="text-2xl font-bold mb-6">周常小测｜每周限时训练</h2>
    <div class="plain-card mb-6">
        <p class="text-sm text-[#6b7280] mb-4">
            周常小测以限时小题为主，适合日常查漏补缺。提交作答后等待教师批改查看分数与评语。
        </p>
        <div class="space-y-4">
            <?php if (empty($weekList)): ?>
                <p class="text-[#6b7280]">暂无已发布周常小测</p>
            <?php else: ?>
                <?php foreach ($weekList as $wp): ?>
                <div class="p-4 border border-[#d1d5db] rounded bg-gray-50 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <h3 class="font-medium"><?php echo htmlspecialchars($wp['title']); ?></h3>
                        <p class="text-xs text-[#6b7280]">限时<?php echo $wp['time_limit']; ?>分钟｜满分<?php echo $wp['full_score']; ?></p>
                    </div>
                    <button class="btn btn-primary start-exam-btn" data-pid="<?php echo $wp['id']; ?>">开始作答</button>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="plain-card">
        <h3 class="text-lg font-semibold mb-3">周测历史记录</h3>
        <?php if (!$isLogin): ?>
            <p class="text-sm text-[#6b7280]">登录后保存所有周测成绩与教师评语。</p>
        <?php else: ?>
            <?php
            $myWeekRec = array_filter($myRecords, function($v) {
                return $v['paper_type'] == 2;
            });
            if (empty($myWeekRec)):
            ?>
                <p class="text-sm text-[#6b7280]">你还未完成任何周常作答</p>
            <?php else: ?>
                <div class="space-y-3">
                <?php foreach ($myWeekRec as $rec): ?>
                    <div class="border border-[#d1d5db] p-3 rounded">
                        <p class="font-medium"><?php echo htmlspecialchars($rec['title']); ?></p>
                        <p class="text-sm text-[#6b7280]">提交时间：<?php echo $rec['submit_time']; ?></p>
                        <p class="text-sm mt-1">得分：<?php echo isset($rec['score']) ? $rec['score'] : '待批改'; ?></p>
                        <?php if (!empty($rec['comment'])): ?>
                            <p class="text-sm mt-1">教师评语：<?php echo htmlspecialchars($rec['comment']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
</main>

<footer class="border-t border-[#d1d5db] py-6 mt-12">
    <div class="container mx-auto px-4 text-center text-sm text-[#6b7280]">
        <p>LunaticChO © 2026 | 化学竞赛学习平台 | 《燕石博物志》</p>
    </div>
</footer>

<!-- 登录弹窗 -->
<div id="modal-mask" class="fixed inset-0 bg-black/60 z-[100] hidden flex items-center justify-center p-4">
    <div id="modal-login" class="modal-box plain-card w-full max-w-md hidden">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">账号登录</h3>
            <i class="fa fa-times text-[#6b7280] cursor-pointer" onclick="closeModal()"></i>
        </div>
        <form action="auth.php" method="post" class="space-y-3">
            <div>
                <label class="block text-sm text-[#6b7280] mb-1">用户名</label>
                <input type="text" name="username" required class="w-full border border-[#d1d5db] rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-[#6b7280] mb-1">密码</label>
                <input type="password" name="password" required class="w-full border border-[#d1d5db] rounded px-3 py-2 text-sm">
            </div>
            <button name="login" type="submit" class="btn btn-primary w-full mt-2">登录账号</button>
            <p class="text-center text-xs text-[#6b7280]">
                没有账号？<span class="text-[#0d9488] cursor-pointer" onclick="switchModal('register')">立即注册</span>
            </p>
        </form>
    </div>
    <div id="modal-register" class="modal-box plain-card w-full max-w-md hidden">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">账号注册</h3>
            <i class="fa fa-times text-[#6b7280] cursor-pointer" onclick="closeModal()"></i>
        </div>
        <form action="auth.php" method="post" class="space-y-3">
            <div>
                <label class="block text-sm text-[#6b7280] mb-1">设置用户名</label>
                <input type="text" name="username" required class="w-full border border-[#d1d5db] rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-[#6b7280] mb-1">设置密码</label>
                <input type="password" name="password" required class="w-full border border-[#d1d5db] rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm text-[#6b7280] mb-1">确认密码</label>
                <input type="password" name="password2" required class="w-full border border-[#d1d5db] rounded px-3 py-2 text-sm">
            </div>
            <button name="register" type="submit" class="btn btn-primary w-full mt-2">完成注册</button>
            <p class="text-center text-xs text-[#6b7280]">
                已有账号？<span class="text-[#0d9488] cursor-pointer" onclick="switchModal('login')">去登录</span>
            </p>
        </form>
    </div>
</div>

<!-- 答题弹窗 -->
<div id="exam-modal" class="fixed inset-0 bg-black/60 z-[110] hidden flex items-center justify-center p-4">
    <div class="plain-card w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold" id="exam-title">试卷作答</h3>
            <i class="fa fa-times text-[#6b7280] cursor-pointer" onclick="closeExamModal()"></i>
        </div>
        <form action="submit.php" method="post">
            <input type="hidden" name="paper_id" id="current_pid">
            <div class="mb-4">
                <p class="text-sm text-[#6b7280] mb-2">题目内容：</p>
                <div class="bg-gray-50 p-3 rounded text-sm whitespace-pre-wrap" id="exam-content"></div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">你的全部作答答案</label>
                <textarea name="stu_answer" rows="10" class="w-full border border-[#d1d5db] rounded p-3" placeholder="在此填写你的作答、方程式、推导过程等..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-full">提交答案等待教师批改</button>
        </form>
    </div>
</div>

<!-- PDF阅读器弹窗 -->
<div id="pdf-modal" class="fixed inset-0 bg-black/80 z-[200] hidden flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-5xl h-[92vh] flex flex-col bg-white rounded-lg overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-borderColor">
            <h4 class="font-bold text-sm" id="pdf-title">PDF阅读</h4>
            <div class="flex gap-3 items-center">
                <button type="button" id="pdf-prev" class="btn btn-outline text-sm">上一页</button>
                <span class="text-sm">第 <span id="pdf-page-num">0</span> / <span id="pdf-total-page">0</span></span>
                <button type="button" id="pdf-next" class="btn btn-outline text-sm">下一页</button>
                <button type="button" id="pdf-close" class="btn btn-outline text-sm">关闭</button>
            </div>
        </div>
        <div class="flex-grow overflow-auto flex items-center justify-center bg-gray-200">
            <canvas id="pdf-canvas"></canvas>
        </div>
    </div>
</div>

<!-- PDF.js 核心库 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>

<script>
const navItems = document.querySelectorAll('.nav-item');
const pages = document.querySelectorAll('.page');
let currentPage = 'home';
function switchPage(pageName) {
    currentPage = pageName;
    pages.forEach(p => p.classList.add('page-hidden'));
    document.getElementById(`page-${pageName}`).classList.remove('page-hidden');
    navItems.forEach(item => {
        if (item.dataset.page === pageName) item.classList.add('nav-link-active');
        else item.classList.remove('nav-link-active');
    });
    document.getElementById('mobile-menu').classList.add('hidden');
    window.scrollTo(0, 0);
}
navItems.forEach(item => item.onclick = () => switchPage(item.dataset.page));
function toggleMobileMenu() {
    document.getElementById('mobile-menu').classList.toggle('hidden');
}
const mask = document.getElementById('modal-mask');
function openModal(type) {
    mask.classList.remove('hidden');
    document.querySelectorAll('.modal-box').forEach(m => m.classList.add('hidden'));
    document.getElementById(`modal-${type}`).classList.remove('hidden');
}
function closeModal() { mask.classList.add('hidden'); }
function switchModal(to) {
    document.querySelectorAll('.modal-box').forEach(m => m.classList.add('hidden'));
    document.getElementById(`modal-${to}`).classList.remove('hidden');
}
mask.onclick = e => { if (e.target === mask) closeModal(); }
const examModal = document.getElementById('exam-modal');
function openExamModal(pid, title, content) {
    document.getElementById('current_pid').value = pid;
    document.getElementById('exam-title').innerText = title;
    document.getElementById('exam-content').innerText = content;
    examModal.classList.remove('hidden');
}
function closeExamModal() { examModal.classList.add('hidden'); }
examModal.onclick = e => { if (e.target === examModal) closeExamModal(); }
const isLogin = <?php echo $isLogin ? 'true' : 'false'; ?>;
const paperData = <?php echo json_encode(array_merge($examList, $weekList)); ?>;
document.querySelectorAll('.start-exam-btn').forEach(btn => {
    btn.onclick = () => {
        const pid = btn.dataset.pid;
        if (!isLogin) return openModal('login');
        let targetPaper = null;
        for (let i = 0; i < paperData.length; i++) {
            if (paperData[i].id == pid) {
                targetPaper = paperData[i];
                break;
            }
        }
        if (!targetPaper) return alert("试卷不存在");
        openExamModal(pid, targetPaper.title, targetPaper.content);
    }
})

// PDF阅读器逻辑
const pdfjsLib = window['pdfjs-dist/build/pdf'];
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
let pdfDoc = null;
let pageNum = 1;
let pageRendering = false;
let pageNumPending = null;
const scale = 1.2;
const canvas = document.getElementById('pdf-canvas');
const ctx = canvas.getContext('2d');
const pdfModal = document.getElementById('pdf-modal');
const pdfTitle = document.getElementById('pdf-title');
const pageNumDom = document.getElementById('pdf-page-num');
const totalPageDom = document.getElementById('pdf-total-page');
const prevBtn = document.getElementById('pdf-prev');
const nextBtn = document.getElementById('pdf-next');
const closePdfBtn = document.getElementById('pdf-close');

function renderPage(num) {
    pageRendering = true;
    pdfDoc.getPage(num).then(function(page) {
        const viewport = page.getViewport({scale: scale});
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        const renderTask = page.render({canvasContext: ctx, viewport: viewport});
        renderTask.promise.then(function() {
            pageRendering = false;
            pageNumDom.textContent = num;
            if(pageNumPending !== null) {
                renderPage(pageNumPending);
                pageNumPending = null;
            }
        });
    });
}
function queueRenderPage(num) {
    if(pageRendering) pageNumPending = num;
    else renderPage(num);
}
function prevPage() {
    if(pageNum <= 1) return;
    pageNum--; queueRenderPage(pageNum);
}
function nextPage() {
    if(pageNum >= pdfDoc.numPages) return;
    pageNum++; queueRenderPage(pageNum);
}
async function openPdfFile(pdfPath, fileName) {
    pdfModal.classList.remove('hidden');
    pdfTitle.innerText = fileName;
    pageNum = 1;
    try {
        const loadingTask = pdfjsLib.getDocument(pdfPath);
        pdfDoc = await loadingTask.promise;
        totalPageDom.textContent = pdfDoc.numPages;
        renderPage(pageNum);
    } catch(err) {
        alert("PDF加载失败，请检查 pdf/vol1.pdf 文件是否上传");
        pdfModal.classList.add('hidden');
    }
}
function closePdfModal() {
    pdfModal.classList.add('hidden');
    ctx.clearRect(0,0,canvas.width,canvas.height);
    pdfDoc = null;
}
document.querySelectorAll('.open-pdf').forEach(btn=>{
    btn.onclick = ()=>{
        const path = btn.dataset.pdf;
        const name = btn.parentElement.querySelector('h3').innerText;
        openPdfFile(path, name);
    }
})
prevBtn.onclick = prevPage;
nextBtn.onclick = nextPage;
closePdfBtn.onclick = closePdfModal;
pdfModal.onclick = e=>{ if(e.target === pdfModal) closePdfModal(); }
</script>
</body>
</html>
