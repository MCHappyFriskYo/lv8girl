<?php
require_once "config.php";

header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

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

// 公告
$notice = $pdo->query("SELECT content,username,create_time FROM notice ORDER BY create_time DESC LIMIT 3")->fetchAll();

// 联考 paper_type=1
$examList = $pdo->query("SELECT id,title,time_limit,full_score,download_paper,download_card FROM paper WHERE paper_type=1 AND is_publish=1 ORDER BY create_time DESC")->fetchAll();

// 周常 paper_type=2
$weekList = $pdo->query("SELECT id,title,time_limit,full_score FROM paper WHERE paper_type=2 AND is_publish=1 ORDER BY create_time DESC")->fetchAll();

// 周常题目
$weekIds = [];
foreach ($weekList as $w) $weekIds[] = $w['id'];
$weekQ = [];
if (!empty($weekIds)) {
    $in = implode(',', array_map('intval', $weekIds));
    $qAll = $pdo->query("SELECT * FROM question WHERE paper_id IN($in) ORDER BY q_no")->fetchAll();
    foreach ($qAll as $q) {
        $q['img_path'] = trim($q['img_path'] ?? '');
        $weekQ[$q['paper_id']][] = $q;
    }
}

// 提交记录
$myRec = [];
if ($isLogin) {
    $myRec = $pdo->prepare("
        SELECT ar.score,ar.comment,ar.submit_time,p.title,p.paper_type,ar.paper_id,ar.card_img
        FROM answer_record ar
        LEFT JOIN paper p ON ar.paper_id=p.id
        WHERE ar.uid=? ORDER BY ar.submit_time DESC
    ");
    $myRec->execute([$uid]);
    $myRec = $myRec->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LunaticChO</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css">
<style>
.plain-card{background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;box-shadow:0 1px 4px #eee;}
.btn{padding:8px 14px;border-radius:6px;font-size:14px;border:0;cursor:pointer;}
.btn-primary{background:#0d9488;color:#fff;}
.btn-blue{background:#0ea5e9;color:#fff;}
.btn-orange{background:#f97316;color:#fff;}
.btn-outline{border:1px solid #ddd;background:#fff;}
.page-hidden{display:none !important;}
.record-done{border-left:4px solid #0d9488;background:#f0fdfa;padding:10px 14px;margin-top:10px;border-radius:0 6px 6px 0;}
.record-wait{border-left:4px solid #f59e0b;background:#fffbeb;padding:10px 14px;margin-top:10px;border-radius:0 6px 6px 0;}
.question-item{border:1px solid #ddd;padding:12px;border-radius:6px;margin-bottom:10px;}
.q-img{max-width:100%;margin:8px 0;border-radius:4px;}
.upload-box{background:#fef3f2;padding:12px;border-radius:6px;margin-top:12px;}
</style>
</head>
<body class="bg-[#f4f6f8]">
<?php if($msg): ?>
<div class="fixed top-4 right-4 plain-card z-[200]">
    <p><?=htmlspecialchars($msg)?></p>
</div>
<script>setTimeout(()=>document.querySelector('.fixed.top-4')?.remove(),2500)</script>
<?php endif; ?>

<header class="sticky top-0 z-50 bg-white border-b border-[#ddd]">
<div class="container mx-auto px-4 py-4 flex items-center justify-between">
<a href="#" onclick="switchTab('home')" class="text-xl font-bold text-[#0d9488] flex items-center gap-2">
    <i class="fa fa-flask"></i>LunaticChO
</a>
<div class="hidden md:flex gap-6">
    <a class="nav-tab py-1 cursor-pointer" data-tab="home">主页</a>
    <a class="nav-tab py-1 cursor-pointer" data-tab="exam">联考</a>
    <a class="nav-tab py-1 cursor-pointer" data-tab="magazine">燕石博物志</a>
    <a class="nav-tab py-1 cursor-pointer" data-tab="weekly">周常</a>
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
<div id="mobileMenu" class="hidden md:hidden px-4 pb-4 flex flex-col gap-3">
    <a class="nav-tab py-2 border-b border-[#ddd]" data-tab="home">主页</a>
    <a class="nav-tab py-2 border-b border-[#ddd]" data-tab="exam">联考</a>
    <a class="nav-tab py-2 border-b border-[#ddd]" data-tab="magazine">燕石博物志</a>
    <a class="nav-tab py-2 border-b border-[#ddd]" data-tab="weekly">周常</a>
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
<p class="text-[#666] mb-6">联考：下载试卷+上传答题卡；周常：在线答题</p>
<div class="plain-card mb-8">
<h2 class="text-lg font-semibold mb-3">快速入口</h2>
<div class="grid md:grid-cols-3 gap-4">
    <div class="p-4 bg-gray-50 rounded border border-[#ddd] cursor-pointer" onclick="switchTab('exam')">
        <i class="fa fa-file-text-o text-[#0d9488] text-xl mb-2"></i>
        <h3 class="font-medium">联考</h3>
        <p class="text-xs text-[#666]">线下作答上传答题卡</p>
    </div>
    <div class="p-4 bg-gray-50 rounded border border-[#ddd] cursor-pointer" onclick="switchTab('weekly')">
        <i class="fa fa-list-alt text-[#0d9488] text-xl mb-2"></i>
        <h3 class="font-medium">周常</h3>
        <p class="text-xs text-[#666]">在线答题</p>
    </div>
    <div class="p-4 bg-gray-50 rounded border border-[#ddd] cursor-pointer" onclick="switchTab('magazine')">
        <i class="fa fa-book text-[#0d9488] text-xl mb-2"></i>
        <h3 class="font-medium">燕石博物志</h3>
        <p class="text-xs text-[#666]">阅读刊物PDF</p>
    </div>
</div>
</div>
<div class="plain-card">
<h2 class="text-lg font-semibold mb-3">公告</h2>
<?php if(empty($notice)): ?>
<p class="text-[#666]">暂无公告</p>
<?php else: foreach($notice as $n): ?>
<div class="mb-3 flex gap-3">
    <span class="text-[#0d9488]">▸</span>
    <div>
        <p><?=nl2br(htmlspecialchars($n['content']))?></p>
        <p class="text-xs text-[#666] mt-1">发布：<?=htmlspecialchars($n['username'])?> <?=$n['create_time']?></p>
    </div>
</div>
<?php endforeach; endif; ?>
</div>
</div>
</section>

<!-- 联考 -->
<section id="tab-exam" class="tab-content page-hidden">
<h2 class="text-2xl font-bold mb-6">联考</h2>
<div class="plain-card mb-6">
<p class="text-[#666] mb-4">流程：下载试卷→打印答题卡手写→上传答题卡等待批改</p>
<?php if(empty($examList)): ?>
<p class="text-[#666]">暂无联考</p>
<?php else: foreach($examList as $e): ?>
<div class="border border-[#ddd] rounded p-4 mb-4 bg-gray-50">
    <h3 class="font-medium text-lg"><?=htmlspecialchars($e['title'])?></h3>
    <p class="text-xs text-[#666]">限时<?=$e['time_limit']?>分钟 满分<?=$e['full_score']?></p>
    <div class="flex flex-wrap gap-3 mt-3">
        <?php if($e['download_paper']): ?>
        <a href="<?=htmlspecialchars($e['download_paper'])?>" target="_blank" class="btn btn-blue" download>
            <i class="fa fa-download mr-1"></i>下载试卷
        </a>
        <?php endif; ?>
        <?php if($e['download_card']): ?>
        <a href="<?=htmlspecialchars($e['download_card'])?>" target="_blank" class="btn btn-outline" download>
            <i class="fa fa-file-text-o mr-1"></i>下载答题卡
        </a>
        <?php endif; ?>
    </div>
    <?php if($isLogin): ?>
    <div class="upload-box">
        <form class="uploadCardForm" data-pid="<?=$e['id']?>">
            <p class="text-orange-600 text-sm font-medium mb-2">上传答题卡图片（jpg/png ≤5MB）</p>
            <div class="flex flex-wrap gap-3 items-center">
                <input type="file" class="cardFile" accept="image/jpeg,image/png" required>
                <button type="submit" class="btn btn-orange">提交答题卡</button>
            </div>
            <p class="tip text-sm mt-2"></p>
        </form>
    </div>
    <?php else: ?>
    <p class="text-orange-500 text-sm mt-3">登录后上传答题卡</p>
    <?php endif; ?>
</div>
<?php endforeach; endif; ?>
</div>

<?php if($isLogin): ?>
<div class="plain-card">
<h3 class="text-lg font-semibold mb-3">你的联考提交记录</h3>
<?php
$has = false;
foreach($myRec as $r){
    if($r['paper_type'] != 1) continue;
    $has = true;
    $done = $r['score'] !== null;
?>
<div class="<?= $done ? 'record-done' : 'record-wait' ?>">
    <p class="font-medium"><?=htmlspecialchars($r['title'])?></p>
    <p class="text-xs text-[#666]">提交：<?=$r['submit_time']?></p>
    <?php if($r['card_img']): ?>
    <p class="text-sm mt-1">答题卡：<a href="<?=htmlspecialchars($r['card_img'])?>" target="_blank" class="text-blue-600 underline">查看原图</a></p>
    <?php endif; ?>
    <?php if($done): ?>
    <p class="text-green-600 font-medium mt-1">得分：<?=$r['score']?></p>
    <?php if($r['comment']): ?>
    <p class="mt-1"><span class="font-medium">评语：</span><?=htmlspecialchars($r['comment'])?></p>
    <?php endif; ?>
    <?php else: ?>
    <p class="text-orange-500 mt-1">等待批改</p>
    <?php endif; ?>
</div>
<?php }
if(!$has) echo '<p class="text-[#666]">暂无提交记录</p>';
?>
</div>
<?php endif; ?>
</section>

<!-- 燕石博物志 -->
<section id="tab-magazine" class="tab-content page-hidden">
<h2 class="text-2xl font-bold mb-6">燕石博物志</h2>
<div class="plain-card mb-6">
<p class="text-[#666] mb-4">社团化学竞赛电子刊物，PDF在线阅读</p>
</div>
<div class="grid md:grid-cols-4 gap-4">
<div class="plain-card">
    <div class="h-28 bg-gray-100 rounded flex items-center justify-center mb-3">
        <i class="fa fa-file-pdf-o text-3xl text-red-500"></i>
    </div>
    <h3 class="font-medium text-sm mb-1">燕石博物志 第一刊</h3>
    <p class="text-xs text-[#666] mb-3">竞赛专题合集</p>
    <button class="btn btn-primary w-full text-xs openPdf" data-path="pdf/vol1.pdf">在线阅读</button>
</div>
</div>
</section>

<!-- 周常 -->
<section id="tab-weekly" class="tab-content page-hidden">
<h2 class="text-2xl font-bold mb-6">周常小测</h2>
<div class="plain-card mb-6">
<p class="text-[#666] mb-4">在线完成题目，提交等待批改</p>
<?php if(empty($weekList)): ?>
<p class="text-[#666]">暂无周常</p>
<?php else: foreach($weekList as $w): ?>
<div class="border border-[#ddd] rounded p-4 mb-4 bg-gray-50 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
    <div>
        <h3 class="font-medium"><?=htmlspecialchars($w['title'])?></h3>
        <p class="text-xs text-[#666]">限时<?=$w['time_limit']?>分钟 满分<?=$w['full_score']?></p>
    </div>
    <button class="btn btn-primary startWeekBtn" data-pid="<?=$w['id']?>">开始作答</button>
</div>
<?php endforeach; endif; ?>
</div>

<?php if($isLogin): ?>
<div class="plain-card">
<h3 class="text-lg font-semibold mb-3">你的周常提交记录</h3>
<?php
$hasWeek = false;
foreach($myRec as $r){
    if($r['paper_type'] != 2) continue;
    $hasWeek = true;
    $done = $r['score'] !== null;
?>
<div class="<?= $done ? 'record-done' : 'record-wait' ?>">
    <p class="font-medium"><?=htmlspecialchars($r['title'])?></p>
    <p class="text-xs text-[#666]">提交：<?=$r['submit_time']?></p>
    <?php if($done): ?>
    <p class="text-green-600 font-medium mt-1">得分：<?=$r['score']?></p>
    <?php if($r['comment']): ?>
    <p class="mt-1"><span class="font-medium">评语：</span><?=htmlspecialchars($r['comment'])?></p>
    <?php endif; ?>
    <?php else: ?>
    <p class="text-orange-500 mt-1">等待批改</p>
    <?php endif; ?>
</div>
<?php }
if(!$hasWeek) echo '<p class="text-[#666]">暂无提交记录</p>';
?>
</div>
<?php endif; ?>
</section>
</main>

<!-- 登录/注册弹窗 -->
<div id="mask" class="fixed inset-0 bg-black/60 z-[100] hidden flex items-center justify-center p-4">
<div id="box-login" class="plain-card w-full max-w-md hidden">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold">登录</h3>
        <i class="fa fa-times cursor-pointer" onclick="closeMask()"></i>
    </div>
    <form action="auth.php" method="post" class="space-y-3">
        <div>
            <label class="text-sm block mb-1">用户名</label>
            <input type="text" name="username" required class="w-full border border-[#ddd] rounded px-3 py-2">
        </div>
        <div>
            <label class="text-sm block mb-1">密码</label>
            <input type="password" name="password" required class="w-full border border-[#ddd] rounded px-3 py-2">
        </div>
        <button name="login" type="submit" class="btn btn-primary w-full">登录</button>
        <p class="text-center text-xs text-[#666]">没有账号？<span class="text-[#0d9488] cursor-pointer" onclick="switchBox('register')">去注册</span></p>
    </form>
</div>
<div id="box-register" class="plain-card w-full max-w-md hidden">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold">注册</h3>
        <i class="fa fa-times cursor-pointer" onclick="closeMask()"></i>
    </div>
    <form action="auth.php" method="post" class="space-y-3">
        <div>
            <label class="text-sm block mb-1">用户名</label>
            <input type="text" name="username" required class="w-full border border-[#ddd] rounded px-3 py-2">
        </div>
        <div>
            <label class="text-sm block mb-1">密码</label>
            <input type="password" name="password" required class="w-full border border-[#ddd] rounded px-3 py-2">
        </div>
        <div>
            <label class="text-sm block mb-1">确认密码</label>
            <input type="password" name="password2" required class="w-full border border-[#ddd] rounded px-3 py-2">
        </div>
        <button name="register" type="submit" class="btn btn-primary w-full">注册</button>
        <p class="text-center text-xs text-[#666]">已有账号？<span class="text-[#0d9488] cursor-pointer" onclick="switchBox('login')">去登录</span></p>
    </form>
</div>
</div>

<!-- 周常答题弹窗 -->
<div id="weekModal" class="fixed inset-0 bg-black/60 z-[110] hidden flex items-center justify-center p-4">
<div class="plain-card w-full max-w-2xl max-h-[90vh] overflow-y-auto">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-lg font-bold" id="weekTitle"></h3>
        <i class="fa fa-times cursor-pointer" onclick="closeWeekModal()"></i>
    </div>
    <form action="submit.php" method="post">
        <input type="hidden" name="paper_id" id="weekPid">
        <div class="mb-4">
            <p class="text-sm text-[#666] mb-2">全部题目：</p>
            <div id="weekQBox" class="bg-gray-50 p-3 rounded text-sm"></div>
        </div>
        <div class="mb-4">
            <label class="text-sm font-medium block mb-1">你的作答</label>
            <textarea name="stu_answer" rows="10" class="w-full border border-[#ddd] rounded p-3" placeholder="写下全部解答过程" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-full">提交</button>
    </form>
</div>
</div>

<!-- PDF弹窗 -->
<div id="pdfModal" class="fixed inset-0 bg-black/80 z-[200] hidden flex flex-col items-center justify-center p-4">
<div class="w-full max-w-5xl h-[92vh] flex flex-col bg-white rounded-lg overflow-hidden">
    <div class="flex items-center justify-between px-4 py-3 border-b border-[#ddd]">
        <h4 class="font-bold text-sm" id="pdfTitle"></h4>
        <div class="flex gap-3 items-center">
            <button type="button" id="pdfPrev" class="btn btn-outline text-sm">上一页</button>
            <span class="text-sm">第 <span id="pdfCur">0</span> / <span id="pdfTotal">0</span></span>
            <button type="button" id="pdfNext" class="btn btn-outline text-sm">下一页</button>
            <button type="button" id="pdfClose" class="btn btn-outline text-sm">关闭</button>
        </div>
    </div>
    <div id="pdfWrap" class="flex-grow">
        <canvas id="pdfCanvas"></canvas>
    </div>
</div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
<script>
// 标签切换
function switchTab(name){
    document.querySelectorAll('.tab-content').forEach(s=>s.classList.add('page-hidden'));
    document.getElementById('tab-'+name).classList.remove('page-hidden');
    document.getElementById('mobileMenu').classList.add('hidden');
}
document.querySelectorAll('.nav-tab').forEach(t=>{
    t.onclick = ()=>switchTab(t.dataset.tab);
})
function toggleMobileMenu(){
    document.getElementById('mobileMenu').classList.toggle('hidden');
}

// 登录弹窗
const mask = document.getElementById('mask');
function openModal(type){
    mask.classList.remove('hidden');
    document.querySelectorAll('#mask > div').forEach(b=>b.classList.add('hidden'));
    document.getElementById('box-'+type).classList.remove('hidden');
}
function switchBox(type){
    document.querySelectorAll('#mask > div').forEach(b=>b.classList.add('hidden'));
    document.getElementById('box-'+type).classList.remove('hidden');
}
function closeMask(){ mask.classList.add('hidden'); }
mask.onclick = e=>{ if(e.target === mask) closeMask(); }

// 周常答题弹窗
const weekModal = document.getElementById('weekModal');
const weekQBox = document.getElementById('weekQBox');
const weekTitle = document.getElementById('weekTitle');
const weekPid = document.getElementById('weekPid');
function closeWeekModal(){ weekModal.classList.add('hidden'); }
function escapeStr(s){
    if(!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
const weekPaperData = <?=json_encode($weekList)?>;
const weekQMap = <?=json_encode($weekQ)?>;
document.querySelectorAll('.startWeekBtn').forEach(btn=>{
    btn.onclick = ()=>{
        const pid = Number(btn.dataset.pid);
        <?php if(!$isLogin): ?>openModal('login');return;<?php endif; ?>
        let title = '';
        let qlist = weekQMap[pid] || [];
        for(let p of weekPaperData){
            if(p.id === pid){ title = p.title; break; }
        }
        weekTitle.innerText = title;
        weekPid.value = pid;
        weekQBox.innerHTML = '';
        const tmap = {1:'填空题',2:'主观问答',3:'计算题',4:'简答题'};
        qlist.forEach(q=>{
            let h = `<div class="question-item">
                <div class="font-bold mb-1">${escapeStr(q.q_no)}【${tmap[q.q_type]}】(${q.score}分)</div>
                <div class="mb-2 whitespace-pre-wrap">${escapeStr(q.content)}</div>`;
            if(q.img_path && q.img_path !== ''){
                h += `<img src="${escapeStr(q.img_path)}" class="q-img" alt="图" onerror="this.style.display='none'">`;
            }
            h += `</div>`;
            weekQBox.innerHTML += h;
        })
        weekModal.classList.remove('hidden');
    }
})
weekModal.onclick = e=>{ if(e.target === weekModal) closeWeekModal(); }

// 联考答题卡上传
document.querySelectorAll('.uploadCardForm').forEach(form=>{
    form.onsubmit = async e=>{
        e.preventDefault();
        const pid = form.dataset.pid;
        const file = form.querySelector('.cardFile').files[0];
        const tip = form.querySelector('.tip');
        if(!file) return;
        if(file.size > 5*1024*1024){
            tip.className = 'tip text-sm mt-2 text-red-500';
            tip.innerText = '图片不能超过5MB';
            return;
        }
        const fd = new FormData();
        fd.append('upload_card', file);
        fd.append('paper_id', pid);
        try{
            const res = await fetch('submit.php',{
                method:'POST',
                cache:'no-cache',
                credentials:'same-origin',
                body:fd
            });
            const d = await res.json();
            if(d.code === 1){
                tip.className = 'tip text-sm mt-2 text-green-600';
                tip.innerText = '上传成功，刷新页面查看记录';
                form.querySelector('.cardFile').value = '';
            }else{
                tip.className = 'tip text-sm mt-2 text-red-500';
                tip.innerText = d.msg;
            }
        }catch(err){
            tip.className = 'tip text-sm mt-2 text-red-500';
            tip.innerText = '上传失败';
        }
    }
})

// PDF
const pdfjsLib = window['pdfjs-dist/build/pdf'];
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.worker.min.js';
let pdfDoc = null, pageNum = 1, pageRendering = false, pagePending = null;
const canvas = document.getElementById('pdfCanvas');
const ctx = canvas.getContext('2d');
const pdfModal = document.getElementById('pdfModal');
const pdfTitle = document.getElementById('pdfTitle');
const curSpan = document.getElementById('pdfCur');
const totalSpan = document.getElementById('pdfTotal');
const wrap = document.getElementById('pdfWrap');
function getScale(w){
    const max = wrap.clientWidth - 40;
    return max / w;
}
function render(num){
    pageRendering = true;
    pdfDoc.getPage(num).then(p=>{
        const v = p.getViewport({scale:1});
        const s = getScale(v.width);
        const view = p.getViewport({scale:s});
        canvas.height = view.height;
        canvas.width = view.width;
        const task = p.render({canvasContext:ctx, viewport:view});
        task.promise.then(()=>{
            pageRendering = false;
            curSpan.innerText = num;
            if(pagePending !== null){ render(pagePending); pagePending=null; }
        })
    })
}
function queue(num){
    if(pageRendering) pagePending = num;
    else render(num);
}
document.getElementById('pdfPrev').onclick = ()=>{ if(pageNum<=1) return; pageNum--; queue(pageNum); }
document.getElementById('pdfNext').onclick = ()=>{ if(pageNum>=pdfDoc.numPages) return; pageNum++; queue(pageNum); }
document.getElementById('pdfClose').onclick = ()=>{
    pdfModal.classList.add('hidden');
    ctx.clearRect(0,0,canvas.width,canvas.height);
    pdfDoc = null;
}
document.querySelectorAll('.openPdf').forEach(btn=>{
    btn.onclick = async ()=>{
        const path = btn.dataset.path;
        pdfTitle.innerText = btn.parentElement.querySelector('h3').innerText;
        pdfModal.classList.remove('hidden');
        pageNum = 1;
        ctx.clearRect(0,0,canvas.width,canvas.height);
        try{
            const task = pdfjsLib.getDocument(path);
            pdfDoc = await task.promise;
            totalSpan.innerText = pdfDoc.numPages;
            render(pageNum);
        }catch(e){
            alert('PDF加载失败');
            pdfModal.classList.add('hidden');
        }
    }
})
pdfModal.onclick = e=>{ if(e.target === pdfModal) document.getElementById('pdfClose').click(); }
window.addEventListener('resize', ()=>{ if(pdfDoc) queue(pageNum); })
</script>
</body>
</html>
