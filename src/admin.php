<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";

header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 教师权限校验
if (!isset($_SESSION['uid']) || empty($_SESSION['role']) || $_SESSION['role'] != 1) {
    $_SESSION['msg'] = "无教师权限，请登录教师账号";
    header("Location: index.php");
    exit;
}
$tid = $_SESSION['uid'];
$tname = $_SESSION['username'];
$msg = '';

// 创建文件夹
$imgDir = 'upload/question/';
if (!is_dir($imgDir)) mkdir($imgDir, 0755, true);

// 1、发布公告
if (isset($_POST['save_notice'])) {
    $content = trim($_POST['content']);
    if (empty($content)) {
        $msg = "公告内容不能为空";
    } else {
        $ins = $pdo->prepare("INSERT INTO notice(content, create_tid, create_time) VALUES (?,?,NOW())");
        $ins->execute([$content, $tid]);
        $msg = "公告发布成功";
    }
}

// 2、上传题目图片AJAX接口
if (isset($_FILES['qimg']) && $_FILES['qimg']['error'] === 0) {
    if ($_FILES['qimg']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['code' => 0, 'msg' => '图片最大5MB']);
        exit;
    }
    $finfo = new Finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES['qimg']['tmp_name']);
    $allow = ['image/jpeg','image/png','image/gif'];
    if (!in_array($mime, $allow)) {
        echo json_encode(['code' => 0, 'msg' => '仅支持jpg/png/gif']);
        exit;
    }
    $ext = pathinfo($_FILES['qimg']['name'], PATHINFO_EXTENSION);
    $saveName = md5(time() . rand(100,999)) . '.' . $ext;
    $savePath = $imgDir . $saveName;
    if (move_uploaded_file($_FILES['qimg']['tmp_name'], $savePath)) {
        echo json_encode(['code' => 1, 'path' => $savePath]);
    } else {
        echo json_encode(['code' => 0, 'msg' => '文件夹权限不足']);
    }
    exit;
}

// 3、保存试卷（核心：区分联考/周常表单逻辑）
if (isset($_POST['save_paper'])) {
    $pid = intval($_POST['pid'] ?? 0);
    $title = trim($_POST['title']);
    $paperType = intval($_POST['paper_type']);
    $timeLimit = intval($_POST['time_limit']);
    $isPublish = isset($_POST['is_publish']) ? 1 : 0;

    // 联考专属：pdf文件路径
    $pdfPaper = trim($_POST['pdf_paper'] ?? '');
    $pdfCard = trim($_POST['pdf_card'] ?? '');

    if (empty($title)) {
        $msg = "试卷名称不能为空";
    } else {
        if ($pid > 0) {
            // 更新试卷
            if ($paperType == 1) {
                // 联考：只更新基础信息+pdf地址，清空原有题目
                $up = $pdo->prepare("UPDATE paper SET title=?,paper_type=?,time_limit=?,is_publish=?,download_paper=?,download_card=? WHERE id=? AND create_tid=?");
                $up->execute([$title, $paperType, $timeLimit, $isPublish, $pdfPaper, $pdfCard, $pid, $tid]);
                // 删除该试卷下所有题目（联考不需要题目）
                $pdo->prepare("DELETE FROM question WHERE paper_id=?")->execute([$pid]);
            } else {
                // 周常：原有整套题目保存逻辑
                $qNos = $_POST['q_no'] ?? [];
                $qTypes = $_POST['q_type'] ?? [];
                $qScores = $_POST['q_score'] ?? [];
                $qContents = $_POST['q_content'] ?? [];
                $qImgs = $_POST['q_img'] ?? [];
                $qAnswers = $_POST['q_answer'] ?? [];
                $totalScore = array_sum($qScores);

                $up = $pdo->prepare("UPDATE paper SET title=?,paper_type=?,time_limit=?,full_score=?,is_publish=? WHERE id=? AND create_tid=?");
                $up->execute([$title, $paperType, $timeLimit, $totalScore, $isPublish, $pid, $tid]);
                $pdo->prepare("DELETE FROM question WHERE paper_id=?")->execute([$pid]);

                $insQ = $pdo->prepare("INSERT INTO question(paper_id,q_no,q_type,score,content,img_path,answer) VALUES (?,?,?,?,?,?,?)");
                for ($i = 0; $i < count($qNos); $i++) {
                    $insQ->execute([
                        $pid,
                        trim($qNos[$i]),
                        intval($qTypes[$i]),
                        intval($qScores[$i]),
                        trim($qContents[$i]),
                        trim($qImgs[$i]),
                        trim($qAnswers[$i])
                    ]);
                }
            }
            $msg = "试卷修改保存成功";
        } else {
            // 新建试卷
            if ($paperType == 1) {
                // 联考：总分默认0，无需题目分数汇总
                $add = $pdo->prepare("INSERT INTO paper(title,paper_type,time_limit,full_score,is_publish,download_paper,download_card,create_tid,create_time) VALUES (?,?,?,?,?,?,?, ?,NOW())");
                $add->execute([$title, 1, $timeLimit, 0, $isPublish, $pdfPaper, $pdfCard, $tid]);
            } else {
                // 周常：录入题目计算总分
                $qNos = $_POST['q_no'] ?? [];
                $qTypes = $_POST['q_type'] ?? [];
                $qScores = $_POST['q_score'] ?? [];
                $qContents = $_POST['q_content'] ?? [];
                $qImgs = $_POST['q_img'] ?? [];
                $qAnswers = $_POST['q_answer'] ?? [];
                $totalScore = array_sum($qScores);

                $add = $pdo->prepare("INSERT INTO paper(title,paper_type,time_limit,full_score,is_publish,create_tid,create_time) VALUES (?,?,?,?,?,?,NOW())");
                $add->execute([$title, 2, $timeLimit, $totalScore, $isPublish, $tid]);
                $newPid = $pdo->lastInsertId();

                $insQ = $pdo->prepare("INSERT INTO question(paper_id,q_no,q_type,score,content,img_path,answer) VALUES (?,?,?,?,?,?,?)");
                for ($i = 0; $i < count($qNos); $i++) {
                    $insQ->execute([
                        $newPid,
                        trim($qNos[$i]),
                        intval($qTypes[$i]),
                        intval($qScores[$i]),
                        trim($qContents[$i]),
                        trim($qImgs[$i]),
                        trim($qAnswers[$i])
                    ]);
                }
            }
            $msg = "试卷创建成功";
        }
    }
}

// 4、批改学生作答打分保存
if (isset($_POST['save_score'])) {
    $arid = intval($_POST['arid']);
    $score = $_POST['score'] === '' ? null : intval($_POST['score']);
    $comment = trim($_POST['comment']);
    $edit = $pdo->prepare("UPDATE answer_record SET score=?,comment=? WHERE id=?");
    $edit->execute([$score, $comment, $arid]);
    $msg = "批改记录已保存";
}

// 获取自己创建的所有试卷
$myPaperList = $pdo->prepare("SELECT * FROM paper WHERE create_tid=? ORDER BY create_time DESC");
$myPaperList->execute([$tid]);
$myPaper = $myPaperList->fetchAll();

// 编辑试卷读取数据
$editInfo = null;
$editQuestions = [];
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $getPaper = $pdo->prepare("SELECT * FROM paper WHERE id=? AND create_tid=? LIMIT 1");
    $getPaper->execute([$eid, $tid]);
    $editInfo = $getPaper->fetch();
    // 只有周常才读取题目
    if ($editInfo && $editInfo['paper_type'] == 2) {
        $qList = $pdo->prepare("SELECT * FROM question WHERE paper_id=? ORDER BY q_no");
        $qList->execute([$eid]);
        $editQuestions = $qList->fetchAll();
    }
}

// 查看学生提交作答记录
$viewRec = [];
$viewPaperName = '';
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $vid = intval($_GET['view']);
    $checkOwn = $pdo->prepare("SELECT title FROM paper WHERE id=? AND create_tid=?");
    $checkOwn->execute([$vid, $tid]);
    $paperData = $checkOwn->fetch();
    if ($paperData) {
        $viewPaperName = $paperData['title'];
        // 关联用户查看提交内容
        $recSql = "
            SELECT ar.id,ar.stu_answer,ar.card_img,ar.score,ar.comment,ar.submit_time,u.username
            FROM answer_record ar
            LEFT JOIN cho_user u ON ar.uid = u.id
            WHERE ar.paper_id = ?
            ORDER BY ar.submit_time DESC
        ";
        $recStmt = $pdo->prepare($recSql);
        $recStmt->execute([$vid]);
        $viewRec = $recStmt->fetchAll();
    } else {
        $msg = "无权查看该试卷作答";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>教师后台管理 | LunaticChO</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css">
<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                primary: '#0d9488',
                border: '#d1d5db',
                textGray: '#6b7280'
            }
        }
    }
}
</script>
<style>
.box{background:#fff;border:1px solid #d1d5db;border-radius:8px;padding:20px;box-shadow:0 1px 4px #eee;}
.btn{padding:8px 14px;border-radius:6px;font-size:14px;border:none;cursor:pointer;}
.btn-main{background:#0d9488;color:#fff;}
.btn-gray{background:#eee;color:#333;}
.btn-red{background:#ef4444;color:#fff;}
.question-block{border:1px dashed #ccc;padding:16px;border-radius:6px;margin:12px 0;}
.tab-active{border-b-2px solid #0d9488;color:#0d9488;}
</style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8">
<div class="max-w-6xl mx-auto">
    <!-- 顶部头部 -->
    <div class="flex flex-wrap justify-between items-center mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold">教师管理后台</h1>
            <p class="text-textGray">当前登录：<?=htmlspecialchars($tname)?></p>
        </div>
        <div class="flex gap-3">
            <a href="index.php" class="btn btn-gray">返回首页</a>
            <a href="index.php?logout=1" class="btn btn-gray">退出登录</a>
        </div>
    </div>

    <!-- 提示消息 -->
    <?php if (!empty($msg)): ?>
    <div class="mb-4 p-3 rounded <?=str_contains($msg,'成功') ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'?>">
        <?=htmlspecialchars($msg)?>
    </div>
    <?php endif; ?>

    <!-- 标签切换栏 -->
    <div class="flex gap-6 border-b border-border pb-2 mb-6">
        <a href="#notice" class="py-2 cursor-pointer hover:text-primary">发布平台公告</a>
        <a href="#paper_edit" class="py-2 cursor-pointer hover:text-primary tab-active">新建/编辑试卷</a>
        <a href="#paper_list" class="py-2 cursor-pointer hover:text-primary">我的全部试卷</a>
        <a href="card\_list.php" class="py-2 cursor-pointer hover:text-primary">答题卡管理</a>
    </div>

    <!-- 模块1：发布公告 -->
    <section id="notice" class="box mb-6">
        <h2 class="text-lg font-semibold mb-4">发布全站公告</h2>
        <form method="post">
            <textarea name="content" rows="4" class="w-full border border-border rounded p-3" placeholder="输入公告内容，所有学生首页可见"></textarea>
            <button name="save_notice" type="submit" class="btn btn-main mt-3">发布公告</button>
        </form>
    </section>

    <!-- 模块2：新建/编辑试卷【核心区分联考周常】 -->
    <section id="paper_edit" class="box mb-6">
        <h2 class="text-lg font-semibold mb-4">
            <?= $editInfo ? '编辑试卷' : '创建新试卷' ?>
        </h2>
        <form method="post" id="paperForm">
            <?php if ($editInfo): ?>
                <input type="hidden" name="pid" value="<?=$editInfo['id']?>">
            <?php endif; ?>

            <!-- 基础通用信息 -->
            <div class="grid md:grid-cols-3 gap-4 mb-6">
                <div>
                    <label class="block text-sm mb-1 font-medium">试卷标题</label>
                    <input type="text" name="title" required class="w-full border border-border rounded p-2"
                    value="<?= $editInfo ? htmlspecialchars($editInfo['title']) : '' ?>">
                </div>
                <div>
                    <label class="block text-sm mb-1 font-medium">试卷类型</label>
                    <select name="paper_type" id="typeSelect" class="w-full border border-border rounded p-2">
                        <option value="1" <?= ($editInfo && $editInfo['paper_type'] == 1) ? 'selected' : '' ?>>联考（上传PDF文件）</option>
                        <option value="2" <?= ($editInfo && $editInfo['paper_type'] == 2) ? 'selected' : '' ?>>周常小测（在线出题）</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm mb-1 font-medium">考试限时（分钟）</label>
                    <input type="number" name="time_limit" class="w-full border border-border rounded p-2"
                    value="<?= $editInfo ? $editInfo['time_limit'] : 60 ?>">
                </div>
            </div>

            <div class="mb-6">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="is_publish" <?= ($editInfo && $editInfo['is_publish']) ? 'checked' : '' ?>>
                    立即发布（勾选学生端可见，不勾选仅草稿保存）
                </label>
            </div>

            <!-- ========== 联考区域：仅填写两个PDF文件名 ========== -->
            <div id="examArea" class="hidden border-t pt-6 mt-6">
                <h3 class="font-bold text-lg mb-4 text-orange-600">联考设置（无需录入题目）</h3>
                <p class="text-sm text-textGray mb-4">
                    PDF文件请提前上传到网站 <b>pdf/</b> 文件夹内，只需要填写文件名即可<br>
                    例：试卷文件命名 chem_exam1.pdf 直接填写 chem_exam1.pdf
                </p>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm mb-1">试卷PDF文件名</label>
                        <input type="text" name="pdf_paper" class="w-full border rounded p-2"
                        value="<?= $editInfo ? htmlspecialchars($editInfo['download_paper']) : '' ?>" placeholder="如：高三联考一卷.pdf">
                    </div>
                    <div>
                        <label class="block text-sm mb-1">答题卡PDF文件名</label>
                        <input type="text" name="pdf_card" class="w-full border rounded p-2"
                        value="<?= $editInfo ? htmlspecialchars($editInfo['download_card']) : '' ?>" placeholder="如：答题卡1.pdf">
                    </div>
                </div>
            </div>

            <!-- ========== 周常区域：出题、上传图片整套表单 ========== -->
            <div id="weekArea" class="border-t pt-6 mt-6">
                <h3 class="font-bold text-lg mb-4 text-primary">周常题目编辑区域</h3>
                <div id="questionWrap">
                    <?php if (!empty($editQuestions)): ?>
                        <?php foreach ($editQuestions as $q): ?>
                        <div class="question-block">
                            <div class="flex justify-between mb-3">
                                <span class="font-medium">题目区块</span>
                                <button type="button" onclick="delItem(this)" class="btn btn-red text-xs">删除本题</button>
                            </div>
                            <div class="grid md:grid-cols-3 gap-3 mb-3">
                                <div>
                                    <label class="text-sm">题号</label>
                                    <input type="text" name="q_no[]" required value="<?=htmlspecialchars($q['q_no'])?>" class="w-full border rounded p-2">
                                </div>
                                <div>
                                    <label class="text-sm">题型</label>
                                    <select name="q_type[]" class="w-full border rounded p-2">
                                        <option value="1" <?=$q['q_type']==1?'selected':''?>>填空题</option>
                                        <option value="2" <?=$q['q_type']==2?'selected':''?>>主观问答</option>
                                        <option value="3" <?=$q['q_type']==3?'selected':''?>>计算题</option>
                                        <option value="4" <?=$q['q_type']==4?'selected':''?>>简答题</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm">分值</label>
                                    <input type="number" name="q_score[]" required value="<?=$q['score']?>" class="w-full border rounded p-2">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="text-sm">题干内容</label>
                                <textarea name="q_content[]" rows="3" required class="w-full border rounded p-2"><?=htmlspecialchars($q['content'])?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="text-sm">题目配图（选填）</label>
                                <input type="hidden" name="q_img[]" value="<?=htmlspecialchars($q['img_path'])?>" class="imgVal">
                                <div class="flex gap-3 items-center mt-1">
                                    <input type="file" class="uploadImg" accept="image/jpeg,image/png,image/gif">
                                    <span class="text-xs text-textGray">选择图片自动上传填充</span>
                                    <?php if (!empty($q['img_path'])): ?>
                                        <img src="<?=htmlspecialchars($q['img_path'])?>" class="h-16 border">
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <label class="text-sm">参考答案（教师后台可见）</label>
                                <textarea name="q_answer[]" rows="2" class="w-full border rounded p-2"><?=htmlspecialchars($q['answer'])?></textarea>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="addQuestion()" class="btn btn-gray my-4">+ 添加一道新题目</button>
            </div>

            <hr class="my-6 border-border">
            <div class="flex gap-3">
                <button name="save_paper" type="submit" class="btn btn-main">保存整套试卷</button>
                <?php if ($editInfo): ?>
                    <a href="admin.php#paper_edit" class="btn btn-gray">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- 模块3：我的试卷列表 -->
    <section id="paper_list" class="box">
        <h2 class="text-lg font-semibold mb-4">我创建的所有试卷</h2>
        <?php if (empty($myPaper)): ?>
            <p class="text-textGray">暂无创建过试卷</p>
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
                    <tr class="border-t border-border">
                        <td class="p-3"><?=htmlspecialchars($p['title'])?></td>
                        <td class="p-3"><?=$p['paper_type'] == 1 ? '联考(PDF)' : '周常小测'?></td>
                        <td class="p-3"><?=$p['full_score']?> 分</td>
                        <td class="p-3">
                            <?=$p['is_publish'] == 1 ? '<span class="text-green-600">已发布</span>' : '<span class="text-amber-500">草稿</span>'?>
                        </td>
                        <td class="p-3 flex gap-2">
                            <a href="?edit=<?=$p['id']?>#paper_edit" class="btn btn-gray text-xs">编辑</a>
                            <a href="?view=<?=$p['id']?>#paper_edit" class="btn btn-main text-xs">查看作答记录</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- 查看学生作答批改区域 -->
    <?php if (!empty($viewRec)): ?>
    <section class="box mt-6">
        <h2 class="text-lg font-semibold mb-4">学生作答记录 — <?=htmlspecialchars($viewPaperName)?></h2>
        <?php foreach ($viewRec as $item): ?>
        <div class="border border-border rounded p-4 mb-4">
            <div class="flex justify-between mb-2">
                <span class="font-medium">学生：<?=htmlspecialchars($item['username'])?></span>
                <span class="text-textGray text-sm">提交时间：<?=$item['submit_time']?></span>
            </div>

            <!-- 区分联考答题卡图片 / 周常文字作答 -->
            <?php if (!empty($item['card_img'])): ?>
                <div class="mb-3 p-3 bg-gray-50 rounded">
                    <p class="text-sm mb-2">上传答题卡：</p>
                    <a href="<?=htmlspecialchars($item['card_img'])?>" target="_blank" class="text-blue-600 underline">点击查看答题卡原图</a>
                </div>
            <?php else: ?>
                <div class="mb-3 p-3 bg-gray-50 rounded whitespace-pre-wrap text-sm">
                    <?php echo htmlspecialchars($item['stu_answer']); ?>
                </div>
            <?php endif; ?>

            <!-- 打分评语表单 -->
            <form method="post" class="grid md:grid-cols-12 gap-3 items-end">
                <input type="hidden" name="arid" value="<?=$item['id']?>">
                <div class="md:col-span-2">
                    <label class="text-sm block mb-1">得分</label>
                    <input type="number" name="score" class="w-full border rounded p-2" value="<?=$item['score']?>">
                </div>
                <div class="md:col-span-8">
                    <label class="text-sm block mb-1">教师评语</label>
                    <input type="text" name="comment" class="w-full border rounded p-2" value="<?=htmlspecialchars($item['comment'])?>" placeholder="填写评语">
                </div>
                <div class="md:col-span-2">
                    <button name="save_score" type="submit" class="btn btn-main w-full">保存批改</button>
                </div>
            </form>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</div>

<script>
// 切换试卷类型 显示对应表单区域
const typeSelect = document.getElementById('typeSelect');
const examArea = document.getElementById('examArea');
const weekArea = document.getElementById('weekArea');

function toggleArea(){
    if(typeSelect.value == '1'){
        examArea.classList.remove('hidden');
        weekArea.classList.add('hidden');
    }else{
        examArea.classList.add('hidden');
        weekArea.classList.remove('hidden');
    }
}
toggleArea();
typeSelect.onchange = toggleArea;

// 周常：新增题目区块
function addQuestion(){
    const wrap = document.getElementById('questionWrap');
    const html = `
    <div class="question-block">
        <div class="flex justify-between mb-3">
            <span class="font-medium">题目区块</span>
            <button type="button" onclick="delItem(this)" class="btn btn-red text-xs">删除本题</button>
        </div>
        <div class="grid md:grid-cols-3 gap-3 mb-3">
            <div>
                <label class="text-sm">题号</label>
                <input type="text" name="q_no[]" required class="w-full border rounded p-2" placeholder="1、(1)">
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
                <label class="text-sm">分值</label>
                <input type="number" name="q_score[]" required value="10" class="w-full border rounded p-2">
            </div>
        </div>
        <div class="mb-3">
            <label class="text-sm">题干内容</label>
            <textarea name="q_content[]" rows="3" required class="w-full border rounded p-2"></textarea>
        </div>
        <div class="mb-3">
            <label class="text-sm">题目配图</label>
            <input type="hidden" name="q_img[]" value="" class="imgVal">
            <div class="flex gap-3 items-center mt-1">
                <input type="file" class="uploadImg" accept="image/jpeg,image/png,image/gif">
                <span class="text-xs text-gray-500">选择图片自动上传</span>
            </div>
        </div>
        <div>
            <label class="text-sm">参考答案</label>
            <textarea name="q_answer[]" rows="2" class="w-full border rounded p-2"></textarea>
        </div>
    </div>
    `;
    wrap.insertAdjacentHTML('beforeend', html);
    bindUpload();
}
// 删除题目
function delItem(btn){
    btn.closest('.question-block').remove();
}

// 图片上传绑定
function bindUpload(){
    document.querySelectorAll('.uploadImg').forEach(input=>{
        input.onchange = async function(){
            const file = this.files[0];
            if(!file) return;
            if(file.size > 5*1024*1024){
                alert('图片不能超过5MB');
                this.value = '';
                return;
            }
            const fd = new FormData();
            fd.append('qimg', file);
            try{
                const res = await fetch('admin.php',{
                    method:'POST',
                    credentials:'same-origin',
                    cache:'no-cache',
                    body:fd
                });
                const data = await res.json();
                if(data.code === 1){
                    this.parentElement.querySelector('.imgVal').value = data.path;
                    alert('图片上传成功');
                    // 预览图片
                    let preview = this.parentElement.querySelector('img');
                    if(!preview){
                        preview = document.createElement('img');
                        preview.className = 'h-16 border';
                        this.parentElement.appendChild(preview);
                    }
                    preview.src = data.path;
                }else{
                    alert(data.msg);
                }
            }catch(err){
                alert('上传请求失败');
            }
        }
    })
}
bindUpload();
</script>
</body>
</html>
