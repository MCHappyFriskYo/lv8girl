<?php
require_once "config.php";
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");
header("Cache-Control: no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// 未登录拦截
if(!isset($_SESSION['uid'])){
    $_SESSION['msg'] = "请先登录后再提交作答";
    header("Location: index.php");
    exit;
}
$uid = $_SESSION['uid'];

// 获取表单数据
$paper_id = intval($_POST['paper_id'] ?? 0);
$stu_answer = trim($_POST['stu_answer'] ?? '');

// 参数校验
if($paper_id <= 0 || empty($stu_answer)){
    $_SESSION['msg'] = "试卷ID或作答内容不能为空";
    header("Location: index.php");
    exit;
}

// 校验试卷：必须存在且已发布
$paperCheck = $pdo->prepare("SELECT id FROM paper WHERE id=? AND is_publish=1");
$paperCheck->execute([$paper_id]);
if($paperCheck->rowCount() === 0){
    $_SESSION['msg'] = "该试卷未发布或不存在";
    header("Location: index.php");
    exit;
}

// 防止重复提交：同一学生同一试卷只能提交一次
$repeatCheck = $pdo->prepare("SELECT id FROM answer_record WHERE paper_id=? AND uid=?");
$repeatCheck->execute([$paper_id, $uid]);
if($repeatCheck->rowCount() > 0){
    $_SESSION['msg'] = "你已经提交过该试卷，不可重复提交";
    header("Location: index.php");
    exit;
}

// 插入答题记录
$insert = $pdo->prepare("INSERT INTO answer_record(paper_id, uid, stu_answer) VALUES (?,?,?)");
$insert->execute([$paper_id, $uid, $stu_answer]);

// 提交成功提示
$_SESSION['msg'] = "作答提交成功！等待教师批改查看分数与评语";
header("Location: index.php");
exit;
?>
