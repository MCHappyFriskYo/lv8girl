<?php
require_once "config.php";
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 拦截：未登录 或 登录账号不是教师角色
if(!isset($_SESSION['uid']) || !isTeacherLogin()){
    $_SESSION['msg'] = "无教师权限，请使用教师账号登录前台后再进入后台";
    header("Location: index.php");
    exit;
}

$tid = $_SESSION['uid'];
$tname = $_SESSION['username'];

    
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<title>教师登录</title>
<link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
tailwind.config={theme:{extend:{colors:{primary:'#0d9488',borderColor:'#d1d5db',bgPage:'#f4f6f8'}}}}
</script>
</head>
<body class="bg-bgPage flex items-center justify-center h-screen">
<div class="plain-card w-full max-w-md p-6 bg-white rounded-lg border border-borderColor shadow-sm">
    <h2 class="text-xl font-bold mb-4 text-center">教师后台登录</h2>
    <?php if($msg): ?>
    <div class="bg-red-100 text-red-600 p-2 rounded mb-4 text-sm"><?=htmlspecialchars($msg)?></div>
    <?php endif; ?>
    <form action="auth.php" method="post" class="space-y-4">
        <div>
            <label class="text-sm block mb-1">教师账号</label>
            <input type="text" name="t_name" required class="w-full border border-borderColor rounded px-3 py-2">
        </div>
        <div>
            <label class="text-sm block mb-1">密码</label>
            <input type="password" name="t_pwd" required class="w-full border border-borderColor rounded px-3 py-2">
        </div>
        <button name="teacher_login" type="submit" class="w-full bg-primary text-white py-2 rounded">登录后台</button>
    </form>
    <div class="mt-4 text-center text-sm">
        <a href="index.php" class="text-primary">返回学生首页</a>
    </div>
</div>
</body>
</html>
<?php
exit;
}

// 教师已登录，获取教师ID
$tid = $_SESSION['tid'];
$tname = $_SESSION['t_name'];

// 1. 发布公告
if(isset($_POST['add_notice'])){
    $content = trim($_POST['notice_content']);
    $ins = $pdo->prepare("INSERT INTO notice(content,create_tid) VALUES (?,?)");
    $ins->execute([$content,$tid]);
    $_SESSION['msg'] = "公告发布成功";
    header("Location: admin.php");exit;
}

// 2. 创建试卷草稿（联考/周常）
if(isset($_POST['add_paper'])){
    $type = $_POST['paper_type'];
    $title = trim($_POST['title']);
    $limit = intval($_POST['time_limit']);
    $score = intval($_POST['full_score']);
    $content = $_POST['paper_content'];
    $ans = $_POST['answer_key'];
    $ins = $pdo->prepare("INSERT INTO paper(paper_type,title,time_limit,full_score,content,answer_key,create_tid) VALUES (?,?,?,?,?,?,?)");
    $ins->execute([$type,$title,$limit,$score,$content,$ans,$tid]);
    $_SESSION['msg'] = "试卷草稿创建完成，可编辑发布";
    header("Location: admin.php#paper");exit;
}

// 3. 发布试卷（改为已发布）
if(isset($_GET['publish'])){
    $pid = intval($_GET['publish']);
    $up = $pdo->prepare("UPDATE paper SET is_publish=1 WHERE id=? AND create_tid=?");
    $up->execute([$pid,$tid]);
    $_SESSION['msg'] = "试卷已对外发布";
    header("Location: admin.php#paper");exit;
}

// 4. 批改学生作答、打分写评语
if(isset($_POST['mark_score'])){
    $rec_id = intval($_POST['record_id']);
    $score = intval($_POST['stu_score']);
    $comment = trim($_POST['tea_comment']);
    $up = $pdo->prepare("UPDATE answer_record SET score=?,comment=? WHERE id=?");
    $up->execute([$score,$comment,$rec_id]);
    $_SESSION['msg'] = "批改保存完成";
    header("Location: admin.php#mark");exit;
}

$msg = $_SESSION['msg'] ?? '';
unset($_SESSION['msg']);

// 查询数据
// 自己创建的所有试卷
$paperList = $pdo->prepare("SELECT * FROM paper WHERE create_tid=? ORDER BY create_time DESC");
$paperList->execute([$tid]);
$paperAll = $paperList->fetchAll();

// 待批改答题记录（关联试卷+学生）
$recordSql = "SELECT ar.*,p.title,u.username FROM answer_record ar
LEFT JOIN paper p ON ar.paper_id=p.id
LEFT JOIN cho_user u ON ar.uid=u.id
WHERE p.create_tid=? ORDER BY submit_time DESC";
$recSt = $pdo->prepare($recordSql);
$recSt->execute([$tid]);
$recordList = $recSt->fetchAll();

// 全部公告
$noticeAll = $pdo->query("SELECT n.*,t.t_realname FROM notice n LEFT JOIN teacher t ON n.create_tid=t.id ORDER BY create_time DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LunaticChO 教师后台</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
<script>
tailwind.config={theme:{extend:{colors:{bgPage:'#f4f6f8',bgCard:'#fff',textMain:'#1f2937',textSub:'#6b7280',primary:'#0d9488',borderColor:'#d1d5db'}}}}
</script>
<style>
.plain-card{background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:16px;box-shadow:0 1px 3px #eee;}
.btn{padding:6px 12px;border-radius:4px;font-size:14px;}
.btn-primary{background:#0d9488;color:#fff;}
.btn-outline{border:1px solid #d1d5db;}
.tab-nav a.active{border-bottom:2px solid #0d9488;color:#0d9488;font-weight:bold;}
</style>
</head>
<body class="bg-bgPage text-textMain min-h-screen">
<header class="bg-white border-b border-borderColor py-4">
    <div class="container mx-auto px-4 flex justify-between items-center">
        <h1 class="text-xl font-bold"><i class="fa fa-flask text-primary"></i> LunaticChO 教师管理后台</h1>
        <div>
            <span class="mr-4">欢迎 <?=htmlspecialchars($tname) ?></span>
            <a href="auth.php?logout=1" class="btn btn-outline">退出登录</a>
            <a href="index.php" class="btn btn-primary ml-2">学生前台首页</a>
        </div>
    </div>
</header>
<div class="container mx-auto px-4 py-6">
    <?php if($msg): ?>
    <div class="bg-green-100 text-green-700 p-3 rounded mb-4"><?=htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <!-- 顶部标签切换 -->
    <div class="tab-nav flex gap-6 border-b border-borderColor mb-6 text-lg">
        <a href="#notice" class="py-2 active">发布公告</a>
        <a href="#paper" class="py-2">出题/试卷管理</a>
        <a href="#mark" class="py-2">学生作答批改</a>
    </div>

    <!-- 1. 发布公告模块 -->
    <section id="notice" class="block">
        <div class="plain-card mb-6">
            <h3 class="text-lg font-semibold mb-3">发布新平台公告</h3>
            <form method="post" action="admin.php">
                <textarea name="notice_content" rows="4" class="w-full border border-borderColor rounded p-3 mb-3" placeholder="输入公告内容..." required></textarea>
                <button name="add_notice" type="submit" class="btn btn-primary">发布公告至首页</button>
            </form>
        </div>
        <div class="plain-card">
            <h3 class="text-lg font-semibold mb-3">全部公告列表</h3>
            <?php foreach($noticeAll as $n): ?>
            <div class="border-b border-borderColor py-3">
                <p><?=nl2br(htmlspecialchars($n['content'])) ?></p>
                <p class="text-xs text-textSub mt-2">发布教师：<?=htmlspecialchars($n['t_realname']) ?> | <?= $n['create_time'] ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- 2. 出题、试卷管理 -->
    <section id="paper" class="hidden mt-10">
        <div class="plain-card mb-6">
            <h3 class="text-lg font-semibold mb-3">新建试卷（联考 / 周常）</h3>
            <form method="post" action="admin.php" class="space-y-3">
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm block mb-1">试卷分类</label>
                        <select name="paper_type" class="w-full border border-borderColor rounded p-2">
                            <option value="1">联考（大考）</option>
                            <option value="2">周常小测</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-sm block mb-1">试卷标题</label>
                        <input type="text" name="title" class="w-full border border-borderColor rounded p-2" required>
                    </div>
                    <div>
                        <label class="text-sm block mb-1">限时(分钟)</label>
                        <input type="number" name="time_limit" class="w-full border border-borderColor rounded p-2" value="40">
                    </div>
                    <div>
                        <label class="text-sm block mb-1">满分</label>
                        <input type="number" name="full_score" class="w-full border border-borderColor rounded p-2" value="100">
                    </div>
                </div>
                <div>
                    <label class="text-sm block mb-1">题目内容</label>
                    <textarea name="paper_content" rows="6" class="w-full border border-borderColor rounded p-2" placeholder="输入全部试题..." required></textarea>
                </div>
                <div>
                    <label class="text-sm block mb-1">标准答案</label>
                    <textarea name="answer_key" rows="4" class="w-full border border-borderColor rounded p-2" placeholder="填写参考答案"></textarea>
                </div>
                <button name="add_paper" type="submit" class="btn btn-primary">保存为草稿试卷</button>
            </form>
        </div>

        <div class="plain-card">
            <h3 class="text-lg font-semibold mb-3">我的全部试卷</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="border p-2">ID</th>
                            <th class="border p-2">类型</th>
                            <th class="border p-2">试卷名称</th>
                            <th class="border p-2">限时/满分</th>
                            <th class="border p-2">状态</th>
                            <th class="border p-2">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($paperAll as $p): ?>
                    <tr>
                        <td class="border p-2"><?= $p['id'] ?></td>
                        <td class="border p-2"><?= $p['paper_type']==1 ? '联考' : '周常' ?></td>
                        <td class="border p-2"><?=htmlspecialchars($p['title']) ?></td>
                        <td class="border p-2"><?= $p['time_limit'] ?>min / <?= $p['full_score'] ?>分</td>
                        <td class="border p-2"><?= $p['is_publish']==1 ? '<span class="text-green-600">已发布</span>' : '<span class="text-orange-500">草稿</span>' ?></td>
                        <td class="border p-2">
                            <?php if($p['is_publish']==0): ?>
                            <a href="admin.php?publishing=<?= $p['id'] ?>" class="btn btn-primary">发布试卷</a>
                            <?php else: ?>
                            <span class="text-textSub">已对外展示</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- 3. 学生作答批改 -->
    <section id="mark" class="hidden mt-10">
        <div class="plain-card">
            <h3 class="text-lg font-semibold mb-3">学生答题记录批改</h3>
            <?php if(empty($recordList)): ?>
                <p class="text-textSub">暂无学生提交作答</p>
            <?php else: ?>
            <div class="space-y-6">
                <?php foreach($recordList as $r): ?>
                <div class="border border-borderColor rounded p-4">
                    <div class="flex justify-between mb-2">
                        <span>试卷：<?=htmlspecialchars($r['title']) ?></span>
                        <span>学生：<?=htmlspecialchars($r['username']) ?> | 提交时间：<?= $r['submit_time'] ?></span>
                    </div>
                    <div class="bg-gray-50 p-3 rounded mb-3">
                        <p class="text-sm font-medium mb-1">学生作答内容：</p>
                        <p class="text-sm whitespace-pre-wrap"><?=htmlspecialchars($r['stu_answer']) ?></p>
                    </div>
                    <form action="admin.php" method="post" class="grid md:grid-cols-3 gap-3 items-end">
                        <input type="hidden" name="record_id" value="<?= $r['id'] ?>">
                        <div>
                            <label class="text-sm block">打分</label>
                            <input type="number" name="stu_score" class="w-full border border-borderColor rounded p-2" value="<?= $r['score']??0 ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm block">教师评语</label>
                            <input type="text" name="tea_comment" class="w-full border border-borderColor rounded p-2" value="<?=htmlspecialchars($r['comment']??'') ?>">
                        </div>
                        <button name="mark_score" type="submit" class="btn btn-primary">保存批改结果</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
// Tab切换
const tabs = document.querySelectorAll('.tab-nav a');
const blocks = document.querySelectorAll('section');
tabs.forEach(t=>{
    t.onclick = (e)=>{
        e.preventDefault();
        let targetId = t.getAttribute('href').replace('#','');
        tabs.forEach(x=>x.classList.remove('active'));
        t.classList.add('active');
        blocks.forEach(b=>b.style.display='none');
        document.getElementById(targetId).style.display='block';
    }
})
// 页面锚点定位自动切换tab
window.onload = function(){
    let hash = location.hash.replace('#','');
    if(hash){
        tabs.forEach(t=>{
            if(t.getAttribute('href') == '#'+hash) t.click();
        })
    }
}
</script>
</body>
</html>
