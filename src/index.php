<?php
require_once "config.php";

header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

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

// 公告查询
$noticeSql = "SELECT n.content, t.username, n.create_time FROM notice n LEFT JOIN cho_user t ON n.create_tid = t.id ORDER BY n.create_time DESC LIMIT 3";
$noticeSt = $pdo->query($noticeSql);
$noticeList = $noticeSt->fetchAll();

// 已发布联考 paper_type=1 仅基础信息，不再读取题目
$examStmt = $pdo->prepare("SELECT * FROM paper WHERE paper_type = 1 AND is_publish = 1 ORDER BY create_time DESC");
$examStmt->execute();
$examList = $examStmt->fetchAll();

// 已发布周常 paper_type=2 保留多题目作答逻辑
$weekStmt = $pdo->prepare("SELECT * FROM paper WHERE paper_type = 2 AND is_publish = 1 ORDER BY create_time DESC");
$weekStmt->execute();
$weekList = $weekStmt->fetchAll();

// 预加载周常试卷题目（联考不用题目）
$allWeekPaperIds = [];
foreach ($weekList as $v) $allWeekPaperIds[] = $v['id'];
$paperQuestions = [];
if (!empty($allWeekPaperIds)) {
    $inStr = implode(',', array_map('intval', $allWeekPaperIds));
    $qAllStmt = $pdo->query("SELECT * FROM question WHERE paper_id IN ($inStr) ORDER BY q_no");
    $qAll = $qAllStmt->fetchAll();
    foreach ($qAll as $q) {
        $q['img_path'] = trim($q['img_path'] ?? '');
        $paperQuestions[$q['paper_id']][] = $q;
    }
}

// 答题/答题卡提交记录
$myRecords = array();
if ($isLogin) {
    $recSql = "SELECT ar.score, ar.comment, ar.submit_time, p.title, p.paper_type, p.id as paper_id, ar.card_img
    FROM answer_record ar
    LEFT JOIN paper p ON ar.paper_id = p.id
    WHERE ar.uid = ?
    ORDER BY ar.submit_time DESC";
    $recStmt = $pdo->prepare($recSql);
    $recStmt->execute([$uid]);
    $myRecords = $recStmt->fetchAll();
}

// 组装周常试卷JSON（仅周常有题目）
$allWeekPaperData = [];
foreach ($weekList as $p) {
    $p['questions'] = $paperQuestions[$p['id']] ?? [];
    $allWeekPaperData[] = $p;
}

// 按试卷ID分组记录
$groupRecords = [];
foreach ($myRecords as $item) {
    $pid = $item['paper_id'];
    $groupRecords[$pid][] = $item;
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
.btn-blue {
    background: #0ea5e9;
    color: white;
}
.btn-orange {
    background: #f97316;
    color: white;
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
#pdf-wrap {
    width: 100%;
    height: 100%;
    overflow: auto;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    background: #e5e7eb;
}
#pdf-canvas {
    max-width: 100%;
    height: auto;
}
.question-item {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 12px;
    margin-bottom: 10px;
}
.q-img {
    max-width: 100%;
    border: 1px solid #ccc;
    border-radius: 4px;
    margin: 8px 0;
}
.record-block {
    border-left:4px solid #0d9488;
    padding:10px 14px;
    background:#f0fdfa;
    margin-top:10px;
    border-radius:0 6px 6px 0;
}
.record-pending {
    border-left:4px solid #f59e0b;
    background:#fffbeb;
}
.download-row {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
    align-items:center;
    margin-top:10px;
}
.upload-card-form {
    margin-top:12px;
    padding:12px;
    background:#fef3f2;
    border-radius:6px;
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
                    <a href="admin.php" class="btn btn-primary text-sm">进入教师后台</a>
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
                联考：下载试卷、打印答题卡，手写完成后上传答题卡图片等待批改；
                周常：在线直接作答系统题目，提交等待批改。
            </p>
            <div class="grid md:grid-cols-3 gap-4">
                <div class="p-4 bg-gray-50 rounded border border-[#d1d5db] cursor-pointer" onclick="switchPage('exam')">
                    <i class="fa fa-file-text-o text-[#0d9488] mb-2"></i>
                    <h3 class="font-medium">联考</h3>
                    <p class="text-xs text-[#6b7280]">下载试卷+上传答题卡</p>
                </div>
                <div class="p-4 bg-gray-50 rounded border border-[#d1d5db] cursor-pointer" onclick="switchPage('weekly')">
                    <i class="fa fa-list-alt text-[#0d9488] mb-2"></i>
                    <h3 class="font-medium">周常</h3>
                    <p class="text-xs text-[#6b7280]">在线限时小题训练</p>
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

<!-- 联考页面：下载试卷、下载答题卡、上传答题卡 -->
<section id="page-exam" class="page page-hidden">
    <h2 class="text-2xl font-bold mb-6">联考｜线下作答上传答题卡</h2>
    <div class="plain-card mb-6">
        <p class="text-sm text-[#6b7280] mb-4">
            使用流程：1.下载试卷PDF打印完成作答 2.下载答题卡PDF打印手写 3.拍照答题卡上传提交等待教师批改
        </p>
        <?php if (empty($examList)): ?>
            <p class="text-[#6b7280]">暂无已发布联考</p>
        <?php else: ?>
            <?php foreach ($examList as $ep): ?>
            <div class="border border-[#d1d5db] rounded p-4 mb-4 bg-gray-50">
                <h3 class="font-medium text-lg"><?php echo htmlspecialchars($ep['title']); ?></h3>
                <p class="text-xs text-[#6b7280]">限时<?php echo $ep['time_limit']; ?>分钟｜满分<?php echo $ep['full_score']; ?></p>
                <div class="download-row">
                    <!-- 下载试卷按钮，后台需要填 paper.download_paper 存pdf路径 -->
                    <a href="<?php echo htmlspecialchars($ep['download_paper'] ?? '#'); ?>" target="_blank" class="btn btn-blue" download>
                        <i class="fa fa-download mr-1"></i>下载试卷
                    </a>
                    <a href="<?php echo htmlspecialchars($ep['download_card'] ?? '#'); ?>" target="_blank" class="btn btn-outline" download>
                        <i class="fa fa-file-text-o mr-1"></i>下载答题卡
                    </a>
                </div>
                <?php if ($isLogin): ?>
                <!-- 上传答题卡表单 -->
                <div class="upload-card-form">
                    <form class="uploadCardForm" data-pid="<?php echo $ep['id']; ?>">
                        <p class="text-sm font-medium mb-2 text-orange-600">上传你的答题卡（jpg/png ≤5MB）</p>
                        <div class="flex gap-3 flex-wrap items-center">
                            <input type="file" class="cardFileInput" accept="image/jpeg,image/jpg,image/png" required>
                            <button type="submit" class="btn btn-orange">提交答题卡</button>
                        </div>
                        <p class="uploadTip text-sm mt-2 hidden"></p>
                    </form>
                </div>
                <?php else: ?>
                <p class="text-orange-500 text-sm mt-3">请先登录后上传答题卡</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 联考提交记录（答题卡+批改成绩） -->
    <?php if ($isLogin): ?>
    <div class="plain-card mb-6">
        <h3 class="text-lg font-semibold mb-3">你的联考答题卡提交记录（含批改成绩）</h3>
        <?php
        $showExamRec = array_filter($myRecords, fn($r)=>$r['paper_type'] == 1);
        if(empty($showExamRec)){
            echo '<p class="text-[#6b7280]">你还没有上传任何联考答题卡</p>';
        }else{
            foreach($showExamRec as $rec):
                $isFinish = $rec['score'] !== null;
            ?>
            <div class="record-block <?= $isFinish ? '' : 'record-pending' ?>">
                <p class="font-medium"><?php echo htmlspecialchars($rec['title']) ?></p>
                <p class="text-sm text-[#6b7280]">提交时间：<?php echo $rec['submit_time'] ?></p>
                <?php if(!empty($rec['card_img'])): ?>
                    <p class="text-sm mt-1">答题卡：<a href="<?php echo htmlspecialchars($rec['card_img']) ?>" target="_blank" class="text-blue-600 underline">点击查看原图</a></p>
                <?php endif; ?>
                <?php if($isFinish): ?>
                    <p class="text-green-600 font-medium mt-1">得分：<?php echo $rec['score'] ?> 分</p>
                    <?php if(!empty($rec['comment'])): ?>
                        <p class="mt-1"><span class="font-medium">教师评语：</span><?php echo htmlspecialchars($rec['comment']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-orange-500 mt-1">等待教师批改中</p>
                <?php endif; ?>
            </div>
            <?php endforeach;
        }
        ?>
    </div>
    <?php endif; ?>
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

<!-- 周常页面：保留在线答题逻辑不变 -->
<section id="page-weekly" class="page page-hidden">
    <h2 class="text-2xl font-bold mb-6">周常小测｜每周限时训练</h2>
    <div class="plain-card mb-6">
        <p class="text-sm text-[#6b7280] mb-4">
            周常小测以限时小题为主，在线直接作答提交，等待教师批改查看分数与评语。
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

    <!-- 周常提交+批改成绩展示 -->
    <?php if ($isLogin): ?>
    <div class="plain-card">
        <h3 class="text-lg font-semibold mb-3">你的周测提交记录（含批改成绩）</h3>
        <?php
        $showWeekRec = array_filter($myRecords, fn($r)=>$r['paper_type'] == 2);
        if(empty($showWeekRec)){
            echo '<p class="text-[#6b7280]">你还没有提交任何周常小测</p>';
        }else{
            foreach($showWeekRec as $rec):
                $isFinish = $rec['score'] !== null;
            ?>
            <div class="record-block <?= $isFinish ? '' : 'record-pending' ?>">
                <p class="font-medium"><?php echo htmlspecialchars($rec['title']) ?></p>
                <p class="text-sm text-[#6b7280]">提交时间：<?php echo $rec['submit_time'] ?></p>
                <?php if($isFinish): ?>
                    <p class="text-green-600 font-medium mt-1">得分：<?php echo $rec['score'] ?> 分</p>
                    <?php if(!empty($rec['comment'])): ?>
                        <p class="mt-1"><span class="font-medium">教师评语：</span><?php echo htmlspecialchars($rec['comment']) ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-orange-500 mt-1">等待教师批改中</p>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        }
        ?>
    </div>
    <?php endif; ?>
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

<!-- 周常答题弹窗（联考已移除该弹窗） -->
<div id="exam-modal" class="fixed inset-0 bg-black/60 z-[110] hidden flex items-center justify-center p-4">
    <div class="plain-card w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold" id="exam-title">试卷作答</h3>
            <i class="fa fa-times text-[#6b7280] cursor-pointer" onclick="closeExamModal()"></i>
        </div>
        <form action="submit.php" method="post">
            <input type="hidden" name="paper_id" id="current_pid">
            <div class="mb-4">
                <p class="text-sm text-[#6b7280] mb-2">全部题目：</p>
                <div id="exam-content" class="bg-gray-50 p-3 rounded text-sm"></div>
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
        <div class="flex items-center justify-between px-4 py-3 border-b border-borderColor shrink-0">
            <h4 class="font-bold text-sm" id="pdf-title">PDF阅读</h4>
            <div class="flex gap-3 items-center">
                <button type="button" id="pdf-prev" class="btn btn-outline text-sm">上一页</button>
                <span class="text-sm">第 <span id="pdf-page-num">0</span> / <span id="pdf-total-page">0</span></span>
                <button type="button" id="pdf-next" class="btn btn-outline text-sm">下一页</button>
                <button type="button" id="pdf-close" class="btn btn-outline text-sm">关闭</button>
            </div>
        </div>
        <div id="pdf-wrap" class="flex-grow">
            <canvas id="pdf-canvas"></canvas>
        </div>
    </div>
</div>

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

// 周常答题弹窗逻辑
const examModal = document.getElementById('exam-modal');
const examContentWrap = document.getElementById('exam-content');
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}
function openExamModal(pid, title, questionList) {
    document.getElementById('current_pid').value = pid;
    document.getElementById('exam-title').innerText = title;
    examContentWrap.innerHTML = '';
    const typeMap = {1:'填空题',2:'主观问答',3:'计算题',4:'简答题'};
    if(questionList && questionList.length > 0){
        questionList.forEach(q=>{
            let html = `<div class="question-item"><div class="font-bold mb-1">${escapeHtml(q.q_no)} 【${typeMap[q.q_type]}】（${q.score}分）</div><div class="mb-2 whitespace-pre-wrap">${escapeHtml(q.content)}</div>`;
            if(q.img_path && q.img_path !== '') html += `<img src="${escapeHtml(q.img_path)}" class="q-img" alt="题目配图" onerror="this.style.display='none'">`;
            html += `</div>`;
            examContentWrap.innerHTML += html;
        })
    }else{
        examContentWrap.innerHTML = '<p class="text-red-500">该试卷暂无题目，请等待教师完善试卷内容</p>';
    }
    examModal.classList.remove('hidden');
}
function closeExamModal() { examModal.classList.add('hidden'); }
examModal.onclick = e => { if (e.target === examModal) closeExamModal(); }
const isLogin = <?php echo $isLogin ? 'true' : 'false'; ?>;
const weekPaperData = <?php echo json_encode($allWeekPaperData); ?>;
document.querySelectorAll('.start-exam-btn').forEach(btn => {
    btn.onclick = () => {
        const pid = Number(btn.dataset.pid);
        if (!isLogin) return openModal('login');
        let targetPaper = null;
        for (let i = 0; i < weekPaperData.length; i++) {
            if (weekPaperData[i].id === pid) {
                targetPaper = weekPaperData[i];
                break;
            }
        }
        if (!targetPaper) return alert("试卷不存在");
        openExamModal(pid, targetPaper.title, targetPaper.questions);
    }
})

// 联考答题卡上传AJAX
document.querySelectorAll('.uploadCardForm').forEach(form=>{
    form.onsubmit = async function(e){
        e.preventDefault();
        const pid = this.dataset.pid;
        const fileInput = this.querySelector('.cardFileInput');
        const tip = this.querySelector('.uploadTip');
        const file = fileInput.files[0];
        if(!file) return;
        if(file.size > 5*1024*1024){
            tip.className = 'uploadTip text-sm mt-2 text-red-500';
            tip.innerText = '图片不能超过5MB';
            return;
        }
        const formData = new FormData();
        formData.append('upload_card', file);
        formData.append('paper_id', pid);
        try {
            const res = await fetch('submit.php', {
                method:'POST',
                cache:'no-cache',
                credentials:'same-origin',
                body:formData
            });
            const data = await res.json();
            if(data.code === 1){
                tip.className = 'uploadTip text-sm mt-2 text-green-600';
                tip.innerText = '答题卡上传成功！刷新页面可查看记录';
                fileInput.value = '';
            }else{
                tip.className = 'uploadTip text-sm mt-2 text-red-500';
                tip.innerText = data.msg;
            }
        }catch(err){
            tip.className = 'uploadTip text-sm mt-2 text-red-500';
            tip.innerText = '上传失败，请检查网络';
        }
    }
})

// PDF渲染逻辑
const pdfjsLib = window['pdfjs-dist/build/pdf'];
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
let pdfDoc = null, pageNum = 1, pageRendering = false, pageNumPending = null;
const canvas = document.getElementById('pdf-canvas');
const ctx = canvas.getContext('2d');
const pdfModal = document.getElementById('pdf-modal');
const pdfTitle = document.getElementById('pdf-title');
const pageNumDom = document.getElementById('pdf-page-num');
const totalPageDom = document.getElementById('pdf-total-page');
const prevBtn = document.getElementById('pdf-prev');
const nextBtn = document.getElementById('pdf-next');
const closePdfBtn = document.getElementById('pdf-close');
const wrap = document.getElementById('pdf-wrap');
function getAutoScale(viewportWidth){
    const maxWidth = wrap.clientWidth - 40;
    return maxWidth / viewportWidth;
}
function renderPage(num){
    pageRendering = true;
    pdfDoc.getPage(num).then(page=>{
        const originViewport = page.getViewport({scale:1});
        const autoScale = getAutoScale(originViewport.width);
        const viewport = page.getViewport({scale:autoScale});
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        const renderTask = page.render({canvasContext:ctx, viewport:viewport});
        renderTask.promise.then(()=>{
            pageRendering = false;
            pageNumDom.textContent = num;
            if(pageNumPending !== null){ renderPage(pageNumPending); pageNumPending=null; }
        });
    });
}
function queueRenderPage(num){ if(pageRendering) pageNumPending=num; else renderPage(num); }
function prevPage(){ if(pageNum<=1) return; pageNum--; queueRenderPage(pageNum); }
function nextPage(){ if(pageNum>=pdfDoc.numPages) return; pageNum++; queueRenderPage(pageNum); }
async function openPdfFile(pdfPath, fileName){
    pdfModal.classList.remove('hidden');
    pdfTitle.innerText = fileName;
    pageNum = 1;
    ctx.clearRect(0,0,canvas.width,canvas.height);
    try{
        const loadingTask = pdfjsLib.getDocument(pdfPath);
        pdfDoc = await loadingTask.promise;
        totalPageDom.textContent = pdfDoc.numPages;
        renderPage(pageNum);
    }catch(err){
        alert("PDF加载失败，请检查文件路径");
        pdfModal.classList.add('hidden');
    }
}
function closePdfModal(){
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
window.addEventListener('resize', ()=>{ if(pdfDoc) queueRenderPage(pageNum); })
</script>
</body>
</html>
