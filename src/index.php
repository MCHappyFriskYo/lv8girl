<?php
require_once "config.php";
// 禁止页面缓存，解决退出缓存残留
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 首页处理登出逻辑
if(isset($_GET['logout'])){
    $_SESSION = [];
    session_unset();
    session_destroy();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    header("Location: index.php");
    exit;
}

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);
$isLogin = isset($_SESSION['uid']);
$loginName = $_SESSION['username'] ?? '';
$uid = $_SESSION['uid'] ?? 0;
$isTeacher = false;
if($isLogin && isset($_SESSION['role']) && $_SESSION['role'] == 1){
    $isTeacher = true;
}

// 读取首页公告（最新3条）
$noticeSt = $pdo->query("SELECT n.content, t.t_realname, n.create_time 
FROM notice n LEFT JOIN cho_user t ON n.create_tid = t.id 
ORDER BY n.create_time DESC LIMIT 3");
$noticeList = $noticeSt->fetchAll();

// 读取已发布联考试卷 type=1
$examPaperSt = $pdo->prepare("SELECT * FROM paper WHERE paper_type=1 AND is_publish=1 ORDER BY create_time DESC");
$examPaperSt->execute();
$examList = $examPaperSt->fetchAll();

// 读取已发布周常试卷 type=2
$weekPaperSt = $pdo->prepare("SELECT * FROM paper WHERE paper_type=2 AND is_publish=1 ORDER BY create_time DESC");
$weekPaperSt->execute();
$weekList = $weekPaperSt->fetchAll();

// 登录学生：读取本人所有答题记录
$myRecords = [];
if($isLogin){
    $recSql = "SELECT ar.score, ar.comment, ar.submit_time, p.title, p.paper_type 
FROM answer_record ar 
LEFT JOIN paper p ON ar.paper_id = p.id 
WHERE ar.uid = ? ORDER BY ar.submit_time DESC";
    $recSt = $pdo->prepare($recSql);
    $recSt->execute([$uid]);
    $myRecords = $recSt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LunaticChO - 化学竞赛平台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
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
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .nav-link-active {
                @apply border-b-2 border-primary text-primary;
            }
            .plain-card {
                @apply bg-bgCard rounded-lg p-5 border border-borderColor shadow-sm;
            }
            .btn {
                @apply px-4 py-2 rounded-md text-sm font-medium transition-all;
            }
            .btn-primary {
                @apply bg-primary text-white hover:bg-primary/90;
            }
            .btn-outline {
                @apply border border-borderColor text-textMain hover:bg-gray-50;
            }
            .page-hidden {
                display: none !important;
            }
            .table-cell {
                @apply px-4 py-3 text-sm border-b border-borderColor;
            }
        }
        body {
            background-color: #f4f6f8;
            color: #1f2937;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">
<!-- 全局消息提示 -->
<?php if (!empty($msg)): ?>
<div class="fixed top-4 right-4 z-[200] plain-card">
    <p><?= htmlspecialchars($msg) ?></p>
</div>
<script>setTimeout(()=>document.querySelector('.fixed.top-4')?.remove(),2500)</script>
<?php endif; ?>

<!-- 顶部导航 -->
<header class="sticky top-0 z-50 bg-white border-b border-borderColor">
    <div class="container mx-auto px-4 lg:px-8">
        <nav class="flex items-center justify-between h-16">
            <!-- Logo -->
            <a href="#" class="flex items-center gap-2 text-xl font-bold text-textMain" onclick="switchPage('home')">
                <i class="fa fa-flask text-primary"></i>
                <span>LunaticChO</span>
            </a>

            <!-- 桌面端导航 -->
            <div class="hidden md:flex items-center gap-6">
                <a class="nav-item py-1 cursor-pointer text-textMain hover:text-primary" data-page="home">主页</a>
                <a class="nav-item py-1 cursor-pointer text-textMain hover:text-primary" data-page="exam">联考（大考）</a>
                <a class="nav-item py-1 cursor-pointer text-textMain hover:text-primary" data-page="magazine">博物志｜燕石博物志</a>
                <a class="nav-item py-1 cursor-pointer text-textMain hover:text-primary" data-page="weekly">周常小测</a>
            </div>

            <!-- 未登录：登录注册按钮 -->
            <?php if (!$isLogin): ?>
            <div id="auth-area" class="hidden md:flex items-center gap-3">
                <button class="btn btn-outline" onclick="openModal('login')">登录</button>
                <button class="btn btn-primary" onclick="openModal('register')">注册</button>
            </div>
            <?php else: ?>
            <!-- 已登录：用户名 + 教师显示后台入口 + 退出 -->
            <div id="user-area" class="hidden md:flex items-center gap-3">
                <span class="text-primary font-medium"><?= htmlspecialchars($loginName) ?></span>
                <?php if($isTeacher): ?>
                    <a href="admin.php" class="btn btn-primary text-sm">进入教师后台</a>
                <?php endif; ?>
                <a href="?logout=1" class="btn btn-outline text-sm">退出</a>
            </div>
            <?php endif; ?>

            <!-- 移动端汉堡按钮 -->
            <button class="md:hidden text-textMain text-xl" onclick="toggleMobileMenu()">
                <i class="fa fa-bars"></i>
            </button>
        </nav>

        <!-- 移动端菜单 -->
        <div id="mobile-menu" class="md:hidden hidden pb-4 flex flex-col gap-3">
            <a class="nav-item py-2 border-b border-borderColor cursor-pointer" data-page="home">主页</a>
            <a class="nav-item py-2 border-b border-borderColor cursor-pointer" data-page="exam">联考（大考）</a>
            <a class="nav-item py-2 border-b border-borderColor cursor-pointer" data-page="magazine">博物志｜燕石博物志</a>
            <a class="nav-item py-2 border-b border-borderColor cursor-pointer" data-page="weekly">周常小测</a>

            <?php if (!$isLogin): ?>
            <div id="mobile-auth" class="flex gap-3 mt-2">
                <button class="btn btn-outline flex-1" onclick="openModal('login')">登录</button>
                <button class="btn btn-primary flex-1" onclick="openModal('register')">注册</button>
            </div>
            <?php else: ?>
            <div id="mobile-user" class="flex flex-col gap-2 mt-2">
                <div class="flex items-center justify-between">
                    <span class="text-primary font-medium"><?= htmlspecialchars($loginName) ?></span>
                    <a href="?logout=1" class="btn btn-outline text-sm">退出</a>
                </div>
                <?php if($isTeacher): ?>
                    <a href="admin.php" class="btn btn-primary text-sm text-center">进入教师后台</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- 主内容 -->
<main class="flex-grow container mx-auto px-4 lg:px-8 py-8">
    <!-- 主页 -->
    <section id="page-home" class="page">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold mb-2">LunaticChO</h1>
            <p class="text-textSub mb-6">化学竞赛学习与训练平台</p>

            <div class="plain-card mb-8">
                <h2 class="text-lg font-semibold mb-3">平台说明</h2>
                <p class="text-sm text-textSub mb-4">
                    这里主要用于化学竞赛日常训练、联考模拟和社团刊物发布。
                    你可以在这里参加限时小测、完成标准化模考，以及阅读《燕石博物志》。
                </p>
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="p-4 bg-gray-50 rounded border border-borderColor cursor-pointer" onclick="switchPage('exam')">
                        <i class="fa fa-file-text-o text-primary mb-2"></i>
                        <h3 class="font-medium">联考</h3>
                        <p class="text-xs text-textSub">标准化模考与真题训练</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded border border-borderColor cursor-pointer" onclick="switchPage('weekly')">
                        <i class="fa fa-list-alt text-primary mb-2"></i>
                        <h3 class="font-medium">周常</h3>
                        <p class="text-xs text-textSub">每周限时小题训练</p>
                    </div>
                    <div class="p-4 bg-gray-50 rounded border border-borderColor cursor-pointer" onclick="switchPage('magazine')">
                        <i class="fa fa-book text-primary mb-2"></i>
                        <h3 class="font-medium">燕石博物志</h3>
                        <p class="text-xs text-textSub">社团化学刊物</p>
                    </div>
                </div>
            </div>

            <div class="plain-card">
                <h2 class="text-lg font-semibold mb-3">近期公告</h2>
                <ul class="space-y-3 text-sm">
                    <?php if(empty($noticeList)): ?>
                        <li class="text-textSub">暂无公告</li>
                    <?php else: ?>
                        <?php foreach($noticeList as $nt): ?>
                        <li class="flex gap-3">
                            <span class="text-primary">▸</span>
                            <div>
                                <span><?=nl2br(htmlspecialchars($nt['content'])) ?></span>
                                <p class="text-xs text-textSub mt-1">教师 <?=htmlspecialchars($nt['t_realname']) ?> · <?= $nt['create_time'] ?></p>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </section>

    <!-- 联考大考页面 -->
    <section id="page-exam" class="page page-hidden">
        <h2 class="text-2xl font-bold mb-6">联考｜标准化大考</h2>

        <div class="plain-card mb-6">
            <p class="text-sm text-textSub mb-4">
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
                        <?php if(empty($examList)): ?>
                            <tr><td colspan="4" class="table-cell text-textSub">暂无已发布联考试卷</td></tr>
                        <?php else: ?>
                            <?php foreach($examList as $ep): ?>
                            <tr>
                                <td class="table-cell"><?=htmlspecialchars($ep['title']) ?></td>
                                <td class="table-cell"><?= $ep['time_limit'] ?> 分钟</td>
                                <td class="table-cell"><?= $ep['full_score'] ?></td>
                                <td class="table-cell">
                                    <button class="btn btn-primary start-exam-btn" data-pid="<?= $ep['id'] ?>">开始模考作答</button>
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
                <p class="text-sm text-textSub">登录后查看你的考试分数、教师评语与错题。</p>
            <?php else: ?>
                <?php
                $myExamRec = array_filter($myRecords, function($v){
                    return $v['paper_type'] == 1;
                });
                if(empty($myExamRec)):
                ?>
                    <p class="text-sm text-textSub">你还未完成任何联考作答</p>
                <?php else: ?>
                    <div class="space-y-3">
                    <?php foreach($myExamRec as $rec): ?>
                        <div class="border border-borderColor p-3 rounded">
                            <p class="font-medium"><?=htmlspecialchars($rec['title']) ?></p>
                            <p class="text-sm text-textSub">提交时间：<?= $rec['submit_time'] ?></p>
                            <p class="text-sm mt-1">得分：<?= $rec['score'] ?? '待批改' ?></p>
                            <?php if(!empty($rec['comment'])): ?>
                                <p class="text-sm mt-1">教师评语：<?=htmlspecialchars($rec['comment']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- 燕石博物志页面 -->
    <section id="page-magazine" class="page page-hidden">
        <h2 class="text-2xl font-bold mb-6">燕石博物志｜社团化学刊物</h2>

        <div class="plain-card mb-6">
            <p class="text-sm text-textSub">
                《燕石博物志》是 LunaticChO 社团内部编写的化学竞赛刊物，主要收录元素、结构、有机、分析、物化等方向的专题内容。
            </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="plain-card">
                <div class="w-full h-28 bg-gray-100 rounded flex items-center justify-center mb-3">
                    <i class="fa fa-book text-3xl text-primary"></i>
                </div>
                <h3 class="font-medium text-sm mb-1">第七期 · 有机合成进阶</h3>
                <p class="text-xs text-textSub mb-3">逆合成分析、杂环合成、保护基专题</p>
                <button class="btn btn-outline w-full text-xs">在线阅读</button>
            </div>
            <div class="plain-card">
                <div class="w-full h-28 bg-gray-100 rounded flex items-center justify-center mb-3">
                    <i class="fa fa-book text-3xl text-secondary"></i>
                </div>
                <h3 class="font-medium text-sm mb-1">第六期 · 元素博物篇</h3>
                <p class="text-xs text-textSub mb-3">过渡金属、稀土元素特殊反应总结</p>
                <button class="btn btn-outline w-full text-xs">在线阅读</button>
            </div>
            <div class="plain-card">
                <div class="w-full h-28 bg-gray-100 rounded flex items-center justify-center mb-3">
                    <i class="fa fa-book text-3xl text-primary"></i>
                </div>
                <h3 class="font-medium text-sm mb-1">第五期 · 晶体结构专题</h3>
                <p class="text-xs text-textSub mb-3">晶胞计算、点阵、配位化合物结构</p>
                <button class="btn btn-outline w-full text-xs">在线阅读</button>
            </div>
            <div class="plain-card">
                <div class="w-full h-28 bg-gray-100 rounded flex items-center justify-center mb-3">
                    <i class="fa fa-download text-3xl text-secondary"></i>
                </div>
                <h3 class="font-medium text-sm mb-1">往期合订本</h3>
                <p class="text-xs text-textSub mb-3">1-4期完整PDF打包下载</p>
                <button class="btn btn-outline w-full text-xs">下载合集</button>
            </div>
        </div>
    </section>

    <!-- 周常小测页面 -->
    <section id="page-weekly" class="page page-hidden">
        <h2 class="text-2xl font-bold mb-6">周常小测｜每周限时训练</h2>

        <div class="plain-card mb-6">
            <p class="text-sm text-textSub mb-4">
                周常小测以限时小题为主，适合日常查漏补缺。提交作答后等待教师批改查看分数与评语。
            </p>

            <div class="space-y-4">
                <?php if(empty($weekList)): ?>
                    <p class="text-textSub">暂无已发布周常小测</p>
                <?php else: ?>
                    <?php foreach($weekList as $wp): ?>
                    <div class="p-4 border border-borderColor rounded bg-gray-50 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                        <div>
                            <h3 class="font-medium"><?=htmlspecialchars($wp['title']) ?></h3>
                            <p class="text-xs text-textSub">限时<?= $wp['time_limit'] ?>分钟｜满分<?= $wp['full_score'] ?></p>
                        </div>
                        <button class="btn btn-primary start-exam-btn" data-pid="<?= $wp['id'] ?>">开始作答</button>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="plain-card">
            <h3 class="text-lg font-semibold mb-3">周测历史记录</h3>
            <?php if (!$isLogin): ?>
                <p class="text-sm text-textSub">登录后保存所有周测成绩与教师评语。</p>
            <?php else: ?>
                <?php
                $myWeekRec = array_filter($myRecords, function($v){
                    return $v['paper_type'] == 2;
                });
                if(empty($myWeekRec)):
                ?>
                    <p class="text-sm text-textSub">你还未完成任何周常作答</p>
                <?php else: ?>
                    <div class="space-y-3">
                    <?php foreach($myWeekRec as $rec): ?>
                        <div class="border border-borderColor p-3 rounded">
                            <p class="font-medium"><?=htmlspecialchars($rec['title']) ?></p>
                            <p class="text-sm text-textSub">提交时间：<?= $rec['submit_time'] ?></p>
                            <p class="text-sm mt-1">得分：<?= $rec['score'] ?? '待批改' ?></p>
                            <?php if(!empty($rec['comment'])): ?>
                                <p class="text-sm mt-1">教师评语：<?=htmlspecialchars($rec['comment']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- 页脚 -->
<footer class="border-t border-borderColor py-6 mt-12">
    <div class="container mx-auto px-4 text-center text-sm text-textSub">
        <p>LunaticChO © 2026 | 化学竞赛学习平台 | 《燕石博物志》</p>
    </div>
</footer>

<!-- 登录/注册弹窗 -->
<div id="modal-mask" class="fixed inset-0 bg-black/60 z-[100] hidden flex items-center justify-center p-4">
    <!-- 登录表单 -->
    <div id="modal-login" class="modal-box plain-card w-full max-w-md hidden">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">账号登录</h3>
            <i class="fa fa-times text-textSub cursor-pointer" onclick="closeModal()"></i>
        </div>
        <form action="auth.php" method="post" class="space-y-3">
            <div>
                <label class="block text-sm text-textSub mb-1">用户名</label>
                <input type="text" name="username" required class="w-full border border-borderColor rounded px-3 py-2 text-sm focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="block text-sm text-textSub mb-1">密码</label>
                <input type="password" name="password" required class="w-full border border-borderColor rounded px-3 py-2 text-sm focus:outline-none focus:border-primary">
            </div>
            <button name="login" type="submit" class="btn btn-primary w-full mt-2">登录账号</button>
            <p class="text-center text-xs text-textSub">
                没有账号？<span class="text-primary cursor-pointer" onclick="switchModal('register')">立即注册</span>
            </p>
        </form>
    </div>

    <!-- 注册表单 -->
    <div id="modal-register" class="modal-box plain-card w-full max-w-md hidden">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold">账号注册</h3>
            <i class="fa fa-times text-textSub cursor-pointer" onclick="closeModal()"></i>
        </div>
        <form action="auth.php" method="post" class="space-y-3">
            <div>
                <label class="block text-sm text-textSub mb-1">设置用户名</label>
                <input type="text" name="username" required class="w-full border border-borderColor rounded px-3 py-2 text-sm focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="block text-sm text-textSub mb-1">设置密码</label>
                <input type="password" name="password" required class="w-full border border-borderColor rounded px-3 py-2 text-sm focus:outline-none focus:border-primary">
            </div>
            <div>
                <label class="block text-sm text-textSub mb-1">确认密码</label>
                <input type="password" name="password2" required class="w-full border border-borderColor rounded px-3 py-2 text-sm focus:outline-none focus:border-primary">
            </div>
            <button name="register" type="submit" class="btn btn-primary w-full mt-2">完成注册</button>
            <p class="text-center text-xs text-textSub">
                已有账号？<span class="text-primary cursor-pointer" onclick="switchModal('login')">去登录</span>
            </p>
        </form>
    </div>
</div>

<!-- 答题弹窗（点击开始作答弹出） -->
<div id="exam-modal" class="fixed inset-0 bg-black/60 z-[110] hidden flex items-center justify-center p-4">
    <div class="plain-card w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold" id="exam-title">试卷作答</h3>
            <i class="fa fa-times text-textSub cursor-pointer" onclick="closeExamModal()"></i>
        </div>
        <form action="submit.php" method="post">
            <input type="hidden" name="paper_id" id="current_pid">
            <div class="mb-4">
                <p class="text-sm text-textSub mb-2">题目内容：</p>
                <div class="bg-gray-50 p-3 rounded text-sm whitespace-pre-wrap" id="exam-content"></div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1">你的全部作答答案</label>
                <textarea name="stu_answer" rows="10" class="w-full border border-borderColor rounded p-3" placeholder="在此填写你的作答、方程式、推导过程等..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-full">提交答案等待教师批改</button>
        </form>
    </div>
</div>

<script>
const navItems = document.querySelectorAll('.nav-item');
const pages = document.querySelectorAll('.page');
let currentPage = 'home';

function switchPage(pageName) {
    currentPage = pageName;
    pages.forEach(p => p.classList.add('page-hidden'));
    document.getElementById(`page-${pageName}`).classList.remove('page-hidden');

    navItems.forEach(item => {
        if (item.dataset.page === pageName) {
            item.classList.add('nav-link-active');
        } else {
            item.classList.remove('nav-link-active');
        }
    });

    document.getElementById('mobile-menu').classList.add('hidden');
    window.scrollTo(0, 0);
}

navItems.forEach(item => {
    item.onclick = () => switchPage(item.dataset.page);
});

function toggleMobileMenu() {
    document.getElementById('mobile-menu').classList.toggle('hidden');
}

// 登录弹窗控制
const mask = document.getElementById('modal-mask');
function openModal(type) {
    mask.classList.remove('hidden');
    document.querySelectorAll('.modal-box').forEach(m => m.classList.add('hidden'));
    document.getElementById(`modal-${type}`).classList.remove('hidden');
}
function closeModal() {
    mask.classList.add('hidden');
}
function switchModal(to) {
    document.querySelectorAll('.modal-box').forEach(m => m.classList.add('hidden'));
    document.getElementById(`modal-${to}`).classList.remove('hidden');
}
mask.onclick = (e) => {
    if (e.target === mask) closeModal();
}

// 答题弹窗
const examModal = document.getElementById('exam-modal');
function openExamModal(pid, title, content) {
    document.getElementById('current_pid').value = pid;
    document.getElementById('exam-title').innerText = title;
    document.getElementById('exam-content').innerText = content;
    examModal.classList.remove('hidden');
}
function closeExamModal() {
    examModal.classList.add('hidden');
}
examModal.onclick = (e)=>{
    if(e.target === examModal) closeExamModal();
}

// 考试按钮拦截与打开答题框
const isLogin = <?= $isLogin ? 'true' : 'false' ?>;
const paperData = <?= json_encode(array_merge($examList, $weekList)) ?>;
document.querySelectorAll('.start-exam-btn').forEach(btn => {
    btn.onclick = () => {
        const pid = btn.dataset.pid;
        if (!isLogin) {
            openModal('login');
            return;
        }
        let targetPaper = null;
        for(let i=0; i < paperData.length; i++){
            if(paperData[i].id == pid){
                targetPaper = paperData[i];
                break;
            }
        }
        if(!targetPaper){
            alert("试卷不存在");
            return;
        }
        openExamModal(pid, targetPaper.title, targetPaper.content);
    }
})
</script>
</body>
</html>
