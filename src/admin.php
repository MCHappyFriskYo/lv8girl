<?php
require_once "config.php";
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 权限拦截
if (!isset($_SESSION['uid']) || !isTeacherLogin()) {
    $_SESSION['msg'] = "无教师访问权限，请用教师账号登录首页后再进入后台";
    header("Location: index.php");
    exit;
}
$tid = $_SESSION['uid'];
$tname = $_SESSION['username'];
$msg = '';
$uploadBase = 'upload/question/';
if (!is_dir($uploadBase)) mkdir($uploadBase, 0755, true);

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

// 2、上传题目图片
if (isset($_FILES['qimg']) && $_FILES['qimg']['error'] === 0) {
    $allow = ['image/jpeg','image/jpg','image/png','image/gif'];
    $mime = mime_content_type($_FILES['qimg']['tmp_name']);
    if (!in_array($mime, $allow)) {
        echo json_encode(['code'=>0,'msg'=>'仅支持jpg/png/gif图片']);exit;
    }
    $ext = pathinfo($_FILES['qimg']['name'], PATHINFO_EXTENSION);
    $saveName = md5(time().rand(1000,9999)).".".$ext;
    $savePath = $uploadBase.$saveName;
    move_uploaded_file($_FILES['qimg']['tmp_name'], $savePath);
    echo json_encode(['code'=>1,'path'=>$savePath]);
    exit;
}

// 3、保存整套试卷+多道题目
if (isset($_POST['save_full_paper'])) {
    $pid = isset($_POST['pid']) ? intval($_POST['pid']) : 0;
    $title = trim($_POST['title']);
    $type = intval($_POST['paper_type']);
    $timeLimit = intval($_POST['time_limit']);
    $publish = isset($_POST['is_publish']) ? 1 : 0;
    $qNos = $_POST['q_no'] ?? [];
    $qTypes = $_POST['q_type'] ?? [];
    $qScores = $_POST['q_score'] ?? [];
    $qContents = $_POST['q_content'] ?? [];
    $qImgs = $_POST['q_img'] ?? [];
    $qAnswers = $_POST['q_answer'] ?? [];

    if (empty($title) || empty($qNos)) {
        $msg = "试卷名称、至少一道题目必填";
    } else {
        $totalScore = array_sum($qScores);
        if ($pid > 0) {
            // 更新试卷主表
            $upPaper = $pdo->prepare("UPDATE paper SET title=?,paper_type=?,time_limit=?,full_score=?,is_publish=? WHERE id=? AND create_tid=?");
            $upPaper->execute([$title, $type, $timeLimit, $totalScore, $publish, $pid, $tid]);
            // 删除旧题目
            $pdo->prepare("DELETE FROM question WHERE paper_id=?")->execute([$pid]);
        } else {
            // 新建试卷
            $addPaper = $pdo->prepare("INSERT INTO paper(title,paper_type,time_limit,full_score,is_publish,create_tid) VALUES (?,?,?,?,?,?)");
            $addPaper->execute([$title, $type, $timeLimit, $totalScore, $publishing, $tid]);
            $pid = $pdo->lastInsertId();
        }
        // 批量插入题目
        $insertQ = $pdo->prepare("INSERT INTO question(paper_id,q_no,q_type,score,content,img_path,answer) VALUES (?,?,?,?,?,?,?)");
        for ($i=0;$i<count($qNos);$i++) {
            $insertQ->execute([
                $pid,
                trim($qNos[$i]),
                intval($qTypes[$i]),
                intval($qScores[$i]),
                trim($qContents[$i]),
                trim($qImgs[$i]),
                trim($qAnswers[$i])
            ]);
        }
        $msg = "试卷保存成功，总分：".$totalScore."分";
    }
}

// 4、批改学生作答
if (isset($_POST['score_submit'])) {
    $arid = intval($_POST['arid']);
    $score = trim($_POST['score']) === '' ? null : intval($_POST['score']);
    $comment = trim($_POST['comment']);
    $up = $pdo->prepare("UPDATE answer_record SET score=?,comment=? WHERE id=?");
    $up->execute([$score, $comment, $arid]);
    $msg = "批改保存成功";
}

// 获取教师所有试卷
$paperListStmt = $pdo->prepare("SELECT * FROM paper WHERE create_tid=? ORDER BY create_time DESC");
$paperListStmt->execute([$tid]);
$myPaper = $paperListStmt->fetchAll();

// 编辑试卷读取数据
$editPaper = null;
$editQuestions = [];
if (isset($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $eStmt = $pdo->prepare("SELECT * FROM paper WHERE id=? AND create_tid=?");
    $eStmt->execute([$eid, $tid]);
    $editPaper = $eStmt->fetch();
    if ($editPaper) {
        $qStmt = $pdo->prepare("SELECT * FROM question WHERE paper_id=? ORDER BY q_no");
        $qStmt->execute([$eid]);
        $editQuestions = $qStmt->fetchAll();
    }
}

// 查看试卷作答
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
.btn{padding:8px 14px;border-radius:6px;font-size:14px;cursor:pointer}
.btn-primary{background:#0d9488;color:#fff;border:0}
.btn-gray{background:#eee;border:0}
.btn-red{background:#ef4444;color:#fff;border:0}
.question-block{border:1px dashed #ccc;padding:16px;border-radius:6px;margin-bottom:12px}
</style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
<div class="max-w-7xl mx-auto">
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

    <div class="flex gap-2 mb-6 border-b border-borderColor pb-2">
        <a href="#notice" class="px-3 py-2 hover:text-primary">发布公告</a>
        <a href="#paper_add" class="px-3 py-2 hover:text-primary"><?php echo $editPaper ? '编辑整套试卷' : '新建整套试卷'; ?></a>
        <a href="#paper_list" class="px-3 py-2 hover:text-primary">我的全部试卷</a>
    </div>

    <!-- 1、发布公告 -->
    <section id="notice" class="card mb-6">
        <h2 class="text-lg font-semibold mb-4">发布平台公告</h2>
        <form method="post">
            <textarea name="content" rows="4" class="w-full border border-borderColor rounded p-3" placeholder="输入公告内容，所有学生首页可见"></textarea>
            <button name="save_notice" type="submit" class="btn btn-primary mt-3">发布公告</button>
        </form>
    </section>

    <!-- 2、新建/编辑试卷（多题目动态表单） -->
    <section id="paper_add" class="card mb-6">
        <h2 class="text-lg font-semibold mb-4"><?php echo $editPaper ? '编辑已有试卷（可修改所有题目）' : '创建新试卷（支持多题型+图片）'; ?></h2>
        <form method="post" id="paperForm">
            <?php if ($editPaper): ?>
                <input type="hidden" name="pid" value="<?php echo $editPaper['id']; ?>">
            <?php endif; ?>
            <!-- 试卷基础信息 -->
            <div class="grid md:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm mb-1 font-medium">试卷标题</label>
                    <input type="text" name="title" required class="w-full border border-borderColor rounded p-2" value="<?php echo $editPaper ? htmlspecialchars($editPaper['title']) : ''; ?>">
                </div>
                <div>
                    <label class="block text-sm mb-1 font-medium">试卷分类</label>
                    <select name="paper_type" class="w-full border border-borderColor rounded p-2">
                        <option value="1" <?php echo ($editPaper && $editPaper['paper_type']==1) ? 'selected' : ''; ?>>联考大考</option>
                        <option value="2" <?php echo ($editPaper && $editPaper['paper_type']==2) ? 'selected' : ''; ?>>周常小测</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1 font-medium">考试限时（分钟）</label>
                    <input type="number" name="time_limit" class="w-full border border-borderColor rounded p-2" value="<?php echo $editPaper ? $editPaper['time_limit'] : 60; ?>">
                </div>
            </div>
            <div class="mb-6">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_publish" <?php echo ($editPaper && $editPaper['is_publish']) ? 'checked' : ''; ?>>
                    立即发布（勾选学生可见，不勾选仅草稿）
                </label>
            </div>

            <hr class="my-6 border-borderColor">
            <h3 class="font-bold text-lg mb-4">题目列表（可自定义题号、上传配图、多题型）</h3>
            <div id="questionWrap">
                <?php if (!empty($editQuestions)): ?>
                    <?php foreach ($editQuestions as $q): ?>
                    <div class="question-block">
                        <div class="flex justify-between mb-3">
                            <span class="font-medium">题目</span>
                            <button type="button" onclick="delBlock(this)" class="btn btn-red text-xs">删除本题</button>
                        </div>
                        <div class="grid md:grid-cols-3 gap-3 mb-3">
                            <div>
                                <label class="text-sm">自定义题号</label>
                                <input type="text" name="q_no[]" value="<?php echo htmlspecialchars($q['q_no']); ?>" required class="w-full border rounded p-2">
                            </div>
                            <div>
                                <label class="text-sm">题型</label>
                                <select name="q_type[]" class="w-full border rounded p-2">
                                    <option value="1" <?php echo $q['q_type']==1?'selected':''; ?>>填空题</option>
                                    <option value="2" <?php echo $q['q_type']==2?'selected':''; ?>>主观问答</option>
                                    <option value="3" <?php echo $q['q_type']==3?'selected':''; ?>>计算题</option>
                                    <option value="4" <?php echo $q['q_type']==4?'selected':''; ?>>简答题</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-sm">本题分值</label>
                                <input type="number" name="q_score[]" value="<?php echo $q['score']; ?>" required class="w-full border rounded p-2">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="text-sm">题干内容</label>
                            <textarea name="q_content[]" rows="3" required class="w-full border rounded p-2"><?php echo htmlspecialchars($q['content']); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="text-sm">题目配图（选填）</label>
                            <input type="hidden" name="q_img[]" value="<?php echo htmlspecialchars($q['img_path']); ?>" class="imgInput">
                            <div class="flex gap-3 items-center mt-1">
                                <input type="file" class="uploadImg" accept="image/*">
                                <span class="text-xs text-textSub">点击上传图片自动填充</span>
                                <?php if (!empty($q['img_path'])): ?>
                                    <img src="<?php echo $q['img_path']; ?>" class="h-16 border">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <label class="text-sm">本题参考答案（教师后台可见）</label>
                            <textarea name="q_answer[]" rows="2" class="w-full border rounded p-2"><?php echo htmlspecialchars($q['answer']); ?></textarea>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <button type="button" onclick="addQuestionBlock()" class="btn btn-gray mb-4">+ 添加一道新题目</button>
            <hr class="my-6 border-borderColor">
            <div class="flex gap-3">
                <button name="save_full_paper" type="submit" class="btn btn-primary">保存整套试卷（自动计算总分）</button>
                <?php if ($editPaper): ?>
                    <a href="admin.php#paper_add" class="btn btn-gray">取消编辑</a>
                <?php endif; ?>
            </div>
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
                        <th class="p-3 text-left">总分</th>
                        <th class="p-3 text-left">状态</th>
                        <th class="p-3 text-left">操作</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($myPaper as $p): ?>
                <tr class="border-t border-borderColor">
                    <td class="p-3"><?php echo htmlspecialchars($p['title']); ?></td>
                    <td class="p-3"><?php echo $p['paper_type']==1 ? '联考' : '周常'; ?></td>
                    <td class="p-3"><?php echo $p['full_score']; ?> 分</td>
                    <td class="p-3"><?php echo $p['is_publish'] ? '<span class="text-green-600">已发布</span>' : '<span class="text-orange-500">草稿</span>'; ?></td>
                    <td class="p-3 flex gap-2">
                        <a href="?edit=<?php echo $p['id']; ?>#paper_add" class="btn btn-gray text-xs">编辑整套</a>
                        <a href="?view=<?php echo $p['id']; ?>#record_view" class="btn btn-primary text-xs">查看学生作答</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <!-- 4、学生作答批改 -->
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

<script>
// 动态新增题目区块
function addQuestionBlock(){
    const wrap = document.getElementById('questionWrap');
    const html = `
    <div class="question-block">
        <div class="flex justify-between mb-3">
            <span class="font-medium">题目</span>
            <button type="button" onclick="delBlock(this)" class="btn btn-red text-xs">删除本题</button>
        </div>
        <div class="grid md:grid-cols-3 gap-3 mb-3">
            <div>
                <label class="text-sm">自定义题号</label>
                <input type="text" name="q_no[]" required class="w-full border rounded p-2" placeholder="如：1、2(1)、计算题一">
            </div>
            <div>
                <label class="text-sm">题型</label>
                <select name="q_type[]" class="w-full border rounded p-2">
                    <option value="1">填空题</option>
                    <option value="2">主观问答</option>
                    <option value="3">计算题</option>
                    <option value="4">简答题</option>
                </select>
            </div>
            <div>
                <label class="text-sm">本题分值</label>
                <input type="number" name="q_score[]" required class="w-full border rounded p-2" value="10">
            </div>
        </div>
        <div class="mb-3">
            <label class="text-sm">题干内容</label>
            <textarea name="q_content[]" rows="3" required class="w-full border rounded p-2" placeholder="输入题干"></textarea>
        </div>
        <div class="mb-3">
            <label class="text-sm">题目配图（选填）</label>
            <input type="hidden" name="q_img[]" value="" class="imgInput">
            <div class="flex gap-3 items-center mt-1">
                <input type="file" class="uploadImg" accept="image/*">
                <span class="text-xs text-textSub">点击上传图片自动填充</span>
            </div>
        </div>
        <div>
            <label class="text-sm">本题参考答案（教师后台可见）</label>
            <textarea name="q_answer[]" rows="2" class="w-full border rounded p-2"></textarea>
        </div>
    </div>
    `;
    wrap.insertAdjacentHTML('beforeend', html);
    bindUpload();
}
// 删除题目区块
function delBlock(btn){
    btn.closest('.question-block').remove();
}
// 图片上传AJAX绑定
function bindUpload(){
    const uploadInputs = document.querySelectorAll('.uploadImg');
    uploadInputs.forEach(input=>{
        input.onchange = async function(){
            const file = this.files[0];
            if(!file) return;
            const formData = new FormData();
            formData.append('qimg', file);
            const res = await fetch('admin.php', {method:'POST',body:formData});
            const data = await res.json();
            if(data.code === 1){
                this.parentElement.querySelector('.imgInput').value = data.path;
                alert('图片上传成功');
            }else{
                alert(data.msg);
            }
        }
    })
}
bindUpload();
</script>
</body>
</html>
