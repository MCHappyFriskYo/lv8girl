<?php
require_once "config.php";
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 权限拦截：必须登录+教师角色
if (!isset($_SESSION['uid']) || !isTeacherLogin()) {
    $_SESSION['msg'] = "无教师访问权限，请用教师账号登录首页后再进入后台";
    header("Location: index.php");
    exit;
}
$tid = $_SESSION['uid'];
$tname = $_SESSION['username'];
$msg = '';

// 1、发布公告
if (isset($_POST['save_notice'])) {
    $content = trim($_POST['content']);
    if (empty($content)) {
        $msg = "公告内容不能为空";
    } else {
        $stmt = $pdo->prepare("INSERT INTO notice(content,create_tid) VALUES (?,?)");
        $stmt->execute([$content, $tid]);
        $msg = "公告发布成功";
    }
}

// 2、新建/保存试卷（草稿/发布）
if (isset($_POST['save_paper'])) {
    $pid = isset($_POST['pid']) ? intval($_POST['pid']) : 0;
    $title = trim($_POST['title']);
    $type = intval($_POST['paper_type']);
    $time = intval($_POST['time_limit']);
    $score = intval($_POST['full_score']);
    $content = trim($_POST['content']);
    $key = trim($_POST['answer_key']);
    $publish = isset($_POST['is_publish']) ? 1 : 0;

    if (empty($title) || empty($content)) {
        $msg = "试卷名称和题目内容必填";
    } else {
        if ($pid > 0) {
            // 更新已有试卷
            $up = $pdo->prepare("UPDATE paper SET title=?,paper_type=?,time_limit=?,full_score=?,content=?,answer_key=?,is_publish=? WHERE id=? AND create_tid=?");
            $up->execute([$title, $type, $time, $score, $content, $key, $publish, $pid, $tid]);
            $msg = "试卷更新完成";
        } else {
            // 新建试卷
            $add = $pdo->prepare("INSERT INTO paper(title,paper_type,time_limit,full_score,content,answer_key,is_publish,create_tid) VALUES (?,?,?,?,?,?,?,?)");
            $add->execute([$title, $type, $time, $score, $content, $key, $publish, $tid]);
            $msg = "试卷创建成功";
        }
    }
}

// 3、批改作答（打分+评语）
if (isset($_POST['score_submit'])) {
    $arid = intval($_POST['arid']);
    $score = trim($_POST['score']) === '' ? null : intval($_POST['score']);
    $comment = trim($_POST['comment']);
    $up = $pdo->prepare("UPDATE answer_record SET score=?,comment=? WHERE id=?");
    $up->execute([$score, $comment, $arid]);
    $msg = "批改保存成功";
}

// 获取当前教师所有试卷
$paperListStmt = $pdo->prepare("SELECT * FROM paper WHERE create_tid=? ORDER BY create_time DESC");
$paperListStmt->execute([$tid]);
$myPaper = $paperListStmt->fetchAll();

// 编辑试卷读取
$editPaper = null;
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $eStmt = $pdo->prepare("SELECT * FROM paper WHERE id=? AND create_tid=?");
    $eStmt->execute([$eid, $tid]);
    $editPaper = $eStmt->fetch();
}

// 查看某试卷学生作答
$viewRecList = [];
$viewPaperTitle = '';
if (isset($_GET['view'])) {
    $vid = intval($_GET['view']);
    $vPaper = $pdo->prepare("SELECT title FROM paper WHERE id=? AND create_tid=?");
    $vPaper->execute([$vid, $tid]);
    $pInfo = $vPaper->fetch();
    if ($pInfo) {
        $viewPaperTitle = $pInfo['title'];
        $recStmt = $pdo->prepare("
            SELECT ar.id,ar.stu_answer,ar.score,ar.comment,ar.submit_time,u.username
            FROM answer_record ar
            LEFT JOIN cho_user u ON ar.uid=u.id
            WHERE ar.paper_id=?
            ORDER BY ar.submit_time DESC
        ");
        $recStmt->execute([$vid]);
        $viewRecList = $recStmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>教师后台 - LunaticChO</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css">
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                primary: '#0d9488',
                borderColor: '#d1d5db',
                textSub: '#6b7280'
            }
        }
    }
}
</script>
<style>
.card{background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:20px;box-shadow:0 1px 3px #eee;}
.btn{px-4 py-2 rounded text-sm}
.btn-primary{bg:#0d9488;color:#fff}
.btn-gray{bg:#eee}
</style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold">教师管理后台</h1>
            <p class="text-textSub">登录账号：<?php echo htmlspecialchars($tname); ?></p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="btn btn-gray">返回首页</a>
            <a href="?logout=1" class="btn btn-gray">退出登录</a>
        </div>
    </div>

    <?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded <?=str_contains($msg,'成功')?'bg-green-100 text-green-700':'bg-red-100 text-red-700'?>">
        <?php echo htmlspecialchars($msg); ?>
    </div>
    <?php endif; ?>

    <!-- 导航切换 -->
    <div class="flex gap-2 mb-6 border-b border-borderColor pb-2">
        <a href="#notice" class="px-3 py-2 hover:text-primary">发布公告</a>
        <a href="#paper_add" class="px-3 py-2 hover:text-primary"><?php echo $editPaper ? '编辑试卷' : '新建试卷'; ?></a>
        <a href="#paper_list" class="px-3 py-2 hover:text-primary">我的全部试卷</a>
    </div>

    <!-- 1、发布公告区域 -->
    <section id="notice" class="card mb-6">
        <h2 class="text-lg font-semibold mb-4">发布平台公告</h2>
        <form method="post">
            <textarea name="content" rows="4" class="w-full border border-borderColor rounded p-3" placeholder="输入公告内容，所有学生首页可见"></textarea>
            <button name="save_notice" type="submit" class="btn btn-primary mt-3">发布公告</button>
        </form>
    </section>

    <!-- 2、新建/编辑试卷 -->
    <section id="paper_add" class="card mb-6">
        <h2 class="text-lg font-semibold mb-4"><?php echo $editPaper ? '编辑已有试卷' : '创建新试卷'; ?></h2>
        <form method="post">
            <?php if ($editPaper): ?>
                <input type="hidden" name="pid" value="<?php echo $editPaper['id']; ?>">
            <?php endif; ?>
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm mb-1">试卷标题</label>
                    <input type="text" name="title" required class="w-full border border-borderColor rounded p-2" value="<?php echo $editPaper ? htmlspecialchars($editPaper['title']) : ''; ?>">
                </div>
                <div>
                    <label class="block text-sm mb-1">试卷类型</label>
                    <select name="paper_type" class="w-full border border-borderColor rounded p-2">
                        <option value="1" <?php echo ($editPaper && $editPaper['paper_type']==1) ? 'selected' : ''; ?>>联考大考</option>
                        <option value="2" <?php echo ($editPaper && $editPaper['paper_type']==2) ? 'selected' : ''; ?>>周常小测</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1">限时（分钟）</label>
                    <input type="number" name="time_limit" class="w-full border border-borderColor rounded p-2" value="<?php echo $editPaper ? $editPaper['time_limit'] : 60; ?>">
                </div>
                <div>
                    <label class="block text-sm mb-1">试卷满分</label>
                    <input type="number" name="full_score" class="w-full border border-borderColor rounded p-2" value="<?php echo $editPaper ? $editPaper['full_score'] : 100; ?>">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm mb-1">题目内容</label>
                <textarea name="content" rows="6" required class="w-full border border-borderColor rounded p-3"><?php echo $editPaper ? htmlspecialchars($editPaper['content']) : ''; ?></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm mb-1">参考答案（仅教师可见）</label>
                <textarea name="answer_key" rows="4" class="w-full border border-borderColor rounded p-3"><?php echo $editPaper ? htmlspecialchars($editPaper['answer_key']) : ''; ?></textarea>
            </div>
            <div class="mb-4">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_publish" <?php echo ($editPaper && $editPaper['is_publish']) ? 'checked' : ''; ?>>
                    立即发布（勾选后学生首页可见，不勾选仅保存草稿）
                </label>
            </div>
            <button name="save_paper" type="submit" class="btn btn-primary">保存试卷</button>
            <?php if ($editPaper): ?>
                <a href="admin.php#paper_add" class="btn btn-gray ml-2">取消编辑</a>
            <?php endif; ?>
        </form>
    </section>

    <!-- 3、我的试卷列表 -->
    <section id="paper_list" class="card mb-6">
        <h2 class="text-lg font-semibold mb-4">我创建的全部试卷</h2>
        <?php if (empty($myPaper)): ?>
            <p class="text-textSub">暂无创建任何试卷</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="p-3 text-left">试卷名称</th>
                        <th class="p-3 text-left">类型</th>
                        <th class="p-3 text-left">状态</th>
                        <th class="p-3 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($myPaper as $p): ?>
                <tr class="border-t border-borderColor">
                    <td class="p-3"><?php echo htmlspecialchars($p['title']); ?></td>
                    <td class="p-3"><?php echo $p['paper_type']==1 ? '联考' : '周常'; ?></td>
                    <td class="p-3"><?php echo $p['is_publish'] ? '<span class="text-green-600">已发布</span>' : '<span class="text-orange-500">草稿</span>'; ?></td>
                    <td class="p-3 flex gap-2">
                        <a href="?edit=<?php echo $p['id']; ?>#paper_add" class="btn btn-gray text-xs">编辑</a>
                        <a href="?view=<?php echo $p['id']; ?>#record_view" class="btn btn-primary text-xs">查看学生作答</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- 4、学生作答批改区域 -->
    <?php if (isset($_GET['view']) && !empty($viewRecList)): ?>
    <section id="record_view" class="card">
        <h2 class="text-lg font-semibold mb-4">作答列表 - <?php echo htmlspecialchars($viewPaperTitle); ?></h2>
        <?php foreach ($viewRecList as $rec): ?>
        <div class="border border-borderColor rounded p-4 mb-4">
            <div class="flex justify-between mb-2">
                <span class="font-medium">学生：<?php echo htmlspecialchars($rec['username']); ?></span>
                <span class="text-textSub">提交时间：<?php echo $rec['submit_time']; ?></span>
            </div>
            <div class="bg-gray-50 p-3 rounded mb-3 whitespace-pre-wrap text-sm">
                <?php echo htmlspecialchars($rec['stu_answer']); ?>
            </div>
            <form method="post" class="grid md:grid-cols-12 gap-3 items-end">
                <input type="hidden" name="arid" value="<?php echo $rec['id']; ?>">
                <div class="md:col-span-2">
                    <label class="text-sm block">得分</label>
                    <input type="number" name="score" class="w-full border border-borderColor rounded p-2" value="<?php echo $rec['score']; ?>">
                </div>
                <div class="md:col-span-8">
                    <label class="text-sm block">教师评语</label>
                    <input type="text" name="comment" class="w-full border border-borderColor rounded p-2" value="<?php echo htmlspecialchars($rec['comment']); ?>" placeholder="填写评语">
                </div>
                <div class="md:col-span-2">
                    <button name="score_submit" type="submit" class="btn btn-primary w-full">保存批改</button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</div>
</body>
</html>
