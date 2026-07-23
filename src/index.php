<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";

// 退出登录
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit;
}

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);

$isLogin = isset($_SESSION['uid']);
$uid = $_SESSION['uid'] ?? 0;
$loginName = $_SESSION['username'] ?? '';
$isTeacher = ($isLogin && $_SESSION['role'] == 1);

// 【修复】去掉 username，notice表无此字段
$noticeList = $pdo->query("SELECT content,create_time FROM notice ORDER BY create_time DESC LIMIT 3")->fetchAll();

// 只读取周常试卷 paper_type=2
$weekList = $pdo->query("SELECT id,title,time_limit,full_score FROM paper WHERE paper_type=2 AND is_publish=1 ORDER BY create_time DESC")->fetchAll();

// 读取周常对应题目
$weekQMap = [];
if (!empty($weekList)) {
    $ids = [];
    foreach ($weekList as $item) $ids[] = $item['id'];
    $inSql = implode(',', array_map('intval', $ids));
    $qList = $pdo->query("SELECT * FROM question WHERE paper_id IN($inSql) ORDER BY q_no")->fetchAll();
    foreach ($qList as $q) {
        $q['img_path'] = trim($q['img_path'] ?? '');
        $weekQMap[$q['paper_id']][] = $q;
    }
}

// 个人作答批改记录
$myRecords = [];
if ($isLogin) {
    $recStmt = $pdo->prepare("
        SELECT ar.score,ar.comment,ar.submit_time,p.title,p.paper_type
        FROM answer_record ar
        LEFT JOIN paper p ON ar.paper_id = p.id
        WHERE ar.uid = ? ORDER BY ar.submit_time DESC
    ");
    $recStmt->execute([$uid]);
    $myRecords = $recStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LunaticChO 化学竞赛平台</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css">
<style>
.plain-card{background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:20px;box-shadow:0 1px 4px #eee;}
.btn{padding:8px 14px;border-radius:6px;font-size:14px;border:0;cursor:pointer;}
.btn-primary{background:#0d9488;color:#fff;}
.btn-outline{border:1px solid #d1d5db;background:#fff;}
.page-hidden{display:none !important;}
.record-done{border-left:4px solid #0d9488;background:#f0fdfa;padding:10px 14px;margin-top:10px;border-radius:0 6px 6px 0;}
.record-wait{border-left:4px solid #f59e0b;background:#fffbeb;padding:10px 14px;margin-top:10px;border-radius:0 6px 6px 0;}
.question-item{border:1px solid #ddd;padding:12px;border-radius:6px;margin-bottom:10px;}
.q-img{max-width:100%;margin:8px 0;border-radius:4px;}
.nav-active{color:#0d9488;border-bottom:2px solid #0d9488;}
</style>
</head>
<body class="bg-[#f4f6f8]">
<?php if($msg): ?>
<div class="fixed top-4 right-4 plain-card z-[200]">
    <p><?=htmlspecialchars($msg)?></p>
</div>
<script>setTimeout(()=>document.querySelector('.fixed.top-4')?.remove(),2500)</script>
<?php endif; ?>

<header class="sticky top-0 z-50 bg-white border-b border-[#d1d5db]">
<div class="container mx-auto px-4 py-4 flex items-center justify-between">
<a href="#" onclick="switchTab('home')" class="text-xl font-bold text-[#0d9488] flex items-center gap-2">
    <i class="fa fa-flask"></i>LunaticChO
</a>
<!-- 导航栏：联考跳转独立下载页面 -->
<div class="hidden md:flex gap-6 items-center">
    <a class="nav-item py-1 cursor-pointer" data-tab="home">主页</a>
    <a href="exam_download.php" class="py-1 text-gray-700 hover:text-[#0d9488]">联考下载</a>
    <a class="nav-item py-1 cursor-pointer" data-tab="magazine">燕石博物志</a>
    <a class="nav-item py-1 cursor-pointer" data-tab="weekly">周常小测</a>
</div>
<div class="hidden md:flex gap-3 items-center">
<?php if(!$isLogin): ?>
    <button class="btn btn-outline" onclick="openModal('login')">登录</button>
    <button class="btn btn-primary" onclick="openModal('register')">注册</button>
<?php else: ?>
    <span class="text-[#0d9488] font-medium"><?=htmlspecialchars($loginName)?></span>
    <?php if($isTeacher): ?>
        <a href="admin.php" class="btn btn-primary text-sm">教师后台</a>
    <?php endif; ?>
    <a href="?logout=1" class="btn btn-outline">退出</a>
<?php endif; ?>
</div>
<button class="md:hidden text-xl" onclick="toggleMobileMenu()">
    <i class="fa fa-bars"></i>
</button>
</div>
<!-- 移动端菜单 -->
<div id="mobileMenu" class="hidden md:hidden px-4 pb-4 flex flex-col gap-3 border-t">
    <a class="nav-item py-2 border-b cursor-pointer" data-tab="home">主页</a>
    <a href="exam_download.php" class="py-2 border-b">联考下载</a>
    <a class="nav-item py-2 border-b cursor-pointer" data-tab="magazine">燕石博物志</a>
    <a class="nav-item py-2 border-b cursor-pointer" data-tab="weekly">周常小测</a>
    <?php if(!$isLogin): ?>
        <div class="flex gap-3 mt-2">
            <button class="btn btn-outline flex-1" onclick="openModal('login')">登录</button>
            <button class="btn btn-primary flex-1" onclick="openModal('register')">注册</button>
        </div>
    <?php endif; ?>
</div>
</header>

<main class="container mx-auto px-4 py-8">
<!-- 主页 -->
<section id="tab-home" class="tab-content">
<div class="max-w-4xl mx-auto">
<h1 class="text-3xl font-bold mb-2">LunaticChO 化学竞赛平台</h1>
<p class="text-gray-500 mb-6">周常在线答题 | 联考文件单独下载 | 竞赛刊物阅读</p>
<div class="plain-card mb-8">
<h2 class="text-lg font-semibold mb-3">功能入口</h2>
<div class="grid md:grid-cols-3 gap-4">
    <div class="p-4 bg-gray-50 rounded border cursor-pointer" onclick="location.href='exam_download.php'">
        <i class="fa fa-download text-[#0d9488] text-xl mb-2"></i>
        <h3 class="font-medium">联考下载</h3>
        <p class="text-xs text-gray-500">下载试卷&答题卡PDF</p>
    </div>
    <div class="p-4 bg-gray-50 rounded border cursor-pointer" onclick="switchTab('weekly')">
        <i class="fa fa-list-alt text-[#0d9488] text-xl mb-2"></i>
        <h3 class="font-medium">周常小测</h3>
        <p class="text-xs text-gray-500">在线作答提交批改</p>
    </div>
    <div class="p-4 bg-gray-50 rounded border cursor-pointer" onclick="switchTab('magazine')">
        <i class="fa fa-book text-[#0d9488] text-xl mb-2"></i>
        <h3 class="font-medium">燕石博物志</h3>
        <p class="text-xs text-gray-500">在线阅读化学刊物</p>
    </div>
</div>
</div>
<div class="plain-card">
<h2 class="text-lg font-semibold mb-3">平台公告</h2>
<?php if(empty($noticeList)): ?>
<p class="text-gray-500">暂无公告</p>
<?php else: foreach($noticeList as $n): ?>
<div class="mb-3 flex gap-3">
    <span class="text-[#0d9488]">▸</span>
    <div>
        <p><?=nl2br(htmlspecialchars($n['content']))?></p>
        <p class="text-xs text-gray-500 mt-1">发布时间：<?= $n['create_time'] ?></p>
    </div>
</div>
<?php endforeach; endif; ?>
</div>
</div>
</section>

<!-- 燕石博物志 -->
<section id="tab-magazine" class="tab-content page-hidden">
<h2 class="text-2xl font-bold mb-6">燕石博物志｜社团化学刊物</h2>
<div class="plain-card mb-6">
<p class="text-gray-500">内部化学竞赛专题刊物，PDF格式在线翻阅</p>
</div>
<div class="grid md:grid-cols-4 gap-4">
<div class="plain-card">
    <div class="h-28 bg-gray-100 rounded flex items-center justify-center mb-3">
        <i class="fa fa-file-pdf-o text-3xl text-red-500"></i>
    </div>
    <h3 class="font-medium text-sm mb-1">燕石博物志 第一刊</h3>
    <p class="text-xs text-gray-500 mb-3">竞赛知识点合集</p>
    <button class="btn btn-primary w-full text-xs openPdf" data-path="pdf/vol1.pdf">在线阅读</button>
</div>
</div>
</section>

<!-- 周常小测（保留在线答题+图片+批改记录） -->
<section id="tab-weekly" class="tab-content page-hidden">
<h2 class="text-2xl font-bold mb-6">周常小测 在线训练</h2>
<div class="plain-card mb-6">
<p class="text-gray-500 mb-4">在线完成题目作答，提交后等待教师批改查看分数与评语</p>
<?php if(empty($weekList)): ?>
<p class="text-gray-500">暂无已发布周常试卷</p>
<?php else: foreach($weekList as $w): ?>
<div class="border rounded p-4 mb-4 bg-gray-50 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
        <h3 class="font-medium"><?=htmlspecialchars($w['title'])?></h3>
        <p class="text-xs text-gray-500">限时<?=$w['time_limit']?>分钟 | 满分<?=$w['full_score']?></p>
    </div>
    <button class="btn btn-primary startWeekBtn" data-pid="<?=$w['id']?>">开始作答</button>
</div>
<?php endforeach; endif; ?>
</div>

<?php if($isLogin): ?>
<div class="plain-card">
<h3 class="text-lg font-semibold mb-3">我的周常作答记录</h3>
<?php
$hasRec = false;
foreach ($myRecords as $r) {
    if ($r['paper_type'] != 2) continue;
    $hasRec = true;
    $isDone = $r['score'] !== null;
?>
<div class="<?= $isDone ? 'record-done' : 'record-wait' ?>">
    <p class="font-medium"><?=htmlspecialchars($r['title'])?></p>
    <p class="text-xs text-gray-500">提交时间：<?=$r['submit_time']?></p>
    <?php if($isDone): ?>
        <p class="text-green-600 font-medium mt-1">得分：<?=$r['score']?> 分</p>
        <?php if(!empty($r['comment'])): ?>
            <p class="mt-1"><span>教师评语：</span><?=htmlspecialchars($r['comment'])?></p>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-orange-500 mt-1">等待教师批改中</p>
    <?php endif; ?>
</div>
<?php }
if (!$hasRec) echo '<p class="text-gray-500">暂无提交记录</p>';
?>
</div>
<?php endif; ?>
</section>
</main>

<footer class="border-t py-6 mt-12 text-center text-sm text-gray-500">
LunaticChO © 2026 化学竞赛学习平台
</footer>

<!-- 登录注册弹窗 -->
<div id="mask" class="fixed inset-0 bg-black/60 z-[100] hidden flex items-center justify-center p-4">
<div id="box-login" class="plain-card w-full max-w-md hidden">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold">账号登录</h3>
        <i class="fa fa-times cursor-pointer" onclick="closeMask()"></i>
    </div>
    <form action="auth.php" method="post" class="space-y-3">
        <div>
            <label class="text-sm block mb-1">用户名</label>
            <input type="text" name="username" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="text-sm block mb-1">密码</label>
            <input type="password" name="password" required class="w-full border rounded px-3 py-2">
        </div>
        <button name="login" type="submit" class="btn btn-primary w-full">登录</button>
        <p class="text-center text-xs text-gray-500">没有账号？<span class="text-[#0d9488] cursor-pointer" onclick="switchBox('register')">去注册</span></p>
    </form>
</div>
<div id="box-register" class="plain-card w-full max-w-md hidden">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold">账号注册</h3>
        <i class="fa fa-times cursor-pointer" onclick="closeMask()"></i>
    </div>
    <form action="auth.php" method="post" class="space-y-3">
        <div>
            <label class="text-sm block mb-1">用户名</label>
            <input type="text" name="username" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="text-sm block mb-1">密码</label>
            <input type="password" name="password" required class="w-full border rounded px-3 py-2">
        </div>
        <div>
            <label class="text-sm block mb-1">确认密码</label>
            <input type="password" name="password2" required class="w-full border rounded px-3 py-2">
        </div>
        <button name="register" type="submit" class="btn btn-primary w-full">注册账号</button>
        <p class="text-center text-xs text-gray-500">已有账号？<span class="text-[#0d9488] cursor-pointer" onclick="switchBox('login')">去登录</span></p>
    </form>
</div>
</div>

<!-- 周常答题弹窗 -->
<div id="weekModal" class="fixed inset-0 bg-black/60 z-[110] hidden flex items-center justify-center p-4">
<div class="plain-card w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold" id="modalTitle"></h3>
        <i class="fa fa-times cursor-pointer" onclick="closeWeekModal()"></i>
    </div>
    <form action="submit.php" method="post">
        <input type="hidden" name="paper_id" id="pidInput">
        <div class="mb-4">
            <p class="text-sm text-gray-500 mb-2">试题内容：</p>
            <div id="questionBox" class="bg-gray-50 p-3 rounded text-sm"></div>
        </div>
        <div class="mb-4">
            <label class="text-sm font-medium block mb-1">你的作答内容</label>
            <textarea name="stu_answer" rows="10" class="w-full border rounded p-3" placeholder="写下解答过程、化学方程式等..." required></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-full">提交作答</button>
    </form>
</div>
</div>

<!-- PDF阅读器 -->
<div id="pdfModal" class="fixed inset-0 bg-black/80 z-[200] hidden flex flex-col items-center justify-center p-4">
<div class="w-full max-w-5xl h-[92vh] flex flex-col bg-white rounded-lg overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b">
        <h4 class="font-bold text-sm" id="pdfTitle"></h4>
        <div class="flex gap-3 items-center">
            <button id="prevPage" class="btn btn-outline text-sm">上一页</button>
            <span>第 <span id="curPage">0</span> / <span id="totalPage">0</span></span>
            <button id="nextPage" class="btn btn-outline text-sm">下一页</button>
            <button id="closePdf" class="btn btn-outline text-sm">关闭</button>
        </div>
    </div>
    <div id="pdfWrap" class="flex-grow flex items-start justify-center overflow-auto p-4"></div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
<script>
// 页面切换
function switchTab(name){
    document.querySelectorAll('.tab-content').forEach(s=>s.classList.add('page-hidden'));
    document.getElementById('tab-'+name).classList.remove('page-hidden');
    document.getElementById('mobileMenu').classList.add('hidden');
}
document.querySelectorAll('.nav-item').forEach(item=>{
    item.onclick = ()=>switchTab(item.dataset.tab);
})
function toggleMobileMenu(){
    document.getElementById('mobileMenu').classList.toggle('hidden');
}

// 登录弹窗控制
const mask = document.getElementById('mask');
function openModal(type){
    mask.classList.remove('hidden');
    document.querySelectorAll('#mask > div').forEach(box=>box.classList.add('hidden'));
    document.getElementById('box-'+type).classList.remove('hidden');
}
function switchBox(type){
    document.querySelectorAll('#mask > div').forEach(box=>box.classList.add('hidden'));
    document.getElementById('box-'+type).classList.remove('hidden');
}
function closeMask(){ mask.classList.add('hidden'); }
mask.onclick = e=>{ if(e.target === mask) closeMask(); }

// 周常答题弹窗
const weekModal = document.getElementById('weekModal');
const questionBox = document.getElementById('questionBox');
const modalTitle = document.getElementById('modalTitle');
const pidInput = document.getElementById('pidInput');
function closeWeekModal(){ weekModal.classList.add('hidden'); }
function escapeHtml(str){
    if(!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
const qTypeMap = {1:"填空题",2:"主观问答",3:"计算题",4:"简答题"};
const qMap = <?=json_encode($weekQMap)?>;
document.querySelectorAll('.startWeekBtn').forEach(btn=>{
    btn.onclick = ()=>{
        <?php if(!$isLogin): ?>openModal('login');return;<?php endif; ?>
        const pid = btn.dataset.pid;
        let title = '';
        <?php foreach($weekList as $w): ?>
            if(<?=$w['id']?> == pid) title = "<?=htmlspecialchars($w['title'])?>";
        <?php endforeach; ?>
        modalTitle.innerText = title;
        pidInput.value = pid;
        questionBox.innerHTML = '';
        const list = qMap[pid] || [];
        list.forEach(q=>{
            let html = `<div class="question-item">
                <div class="font-bold mb-1">${escapeHtml(q.q_no)}【${qTypeMap[q.q_type]}】(${q.score}分)</div>
                <div class="whitespace-pre-wrap mb-2">${escapeHtml(q.content)}</div>`;
            if(q.img_path){
                html += `<img src="${escapeHtml(q.img_path)}" class="q-img" onerror="this.style.display='none'">`;
            }
            html += `</div>`;
            questionBox.innerHTML += html;
        })
        weekModal.classList.remove('hidden');
    }
})
weekModal.onclick = e=>{ if(e.target === weekModal) closeWeekModal(); }

// PDF阅读逻辑
const pdfjsLib = window['pdfjs-dist/build/pdf'];
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
let pdfDoc = null, pageNum = 1, rendering = false, pendingPage = null;
const canvas = document.createElement('canvas');
const ctx = canvas.getContext('2d');
const pdfWrap = document.getElementById('pdfWrap');
pdfWrap.appendChild(canvas);
const pdfModal = document.getElementById('pdfModal');
const curPageSpan = document.getElementById('curPage');
const totalPageSpan = document.getElementById('totalPage');
const pdfTitleText = document.getElementById('pdfTitle');

function renderPage(num){
    rendering = true;
    pdfDoc.getPage(num).then(page=>{
        const viewport = page.getViewport({scale:1});
        const maxW = pdfWrap.clientWidth - 30;
        const scale = maxW / viewport.width;
        const finalView = page.getViewport({scale:scale});
        canvas.width = finalView.width;
        canvas.height = finalView.height;
        page.render({canvasContext:ctx, viewport:finalView}).promise.then(()=>{
            rendering = false;
            curPageSpan.innerText = num;
            if(pendingPage){ renderPage(pendingPage); pendingPage=null; }
        })
    })
}
document.getElementById('prevPage').onclick = ()=>{
    if(pageNum <= 1) return; pageNum--; renderPage(pageNum);
}
document.getElementById('nextPage').onclick = ()=>{
    if(pageNum >= pdfDoc.numPages) return; pageNum++; renderPage(pageNum);
}
document.getElementById('closePdf').onclick = ()=>{
    pdfModal.classList.add('hidden');
    ctx.clearRect(0,0,canvas.width,canvas.height);
    pdfDoc = null;
}
document.querySelectorAll('.openPdf').forEach(btn=>{
    btn.onclick = async ()=>{
        const path = btn.dataset.path;
        pdfTitleText.innerText = btn.parentElement.querySelector('h3').innerText;
        pdfModal.classList.remove('hidden');
        pageNum = 1;
        ctx.clearRect(0,0,canvas.width,canvas.height);
        try{
            const task = pdfjsLib.getDocument(path);
            pdfDoc = await task.promise;
            totalPageSpan.innerText = pdfDoc.numPages;
            renderPage(pageNum);
        }catch(e){
            alert('PDF文件加载失败，请检查路径');
            pdfModal.classList.add('hidden');
        }
    }
})
window.addEventListener('resize', ()=>{ if(pdfDoc) renderPage(pageNum); })
</script>
</body>
</html>
