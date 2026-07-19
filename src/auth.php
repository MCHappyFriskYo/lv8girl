<?php
require_once "config.php";

// 登出逻辑（统一处理学生/教师）
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

// 学生注册
if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $pwd = $_POST['password'];
    $pwd2 = $_POST['password2'];

    if (empty($username) || empty($pwd)) {
        $_SESSION['msg'] = "用户名、密码不能为空";
        header("Location: index.php");
        exit;
    }
    if ($pwd !== $pwd2) {
        $_SESSION['msg'] = "两次输入密码不一致";
        header("Location: index.php");
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM cho_user WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        $_SESSION['msg'] = "该用户名已被注册";
        header("Location: index.php");
        exit;
    }

    $hash_pwd = password_hash($pwd, PASSWORD_DEFAULT);
    $ins = $pdo->prepare("INSERT INTO cho_user(username, password) VALUES (?, ?)");
    $ins->execute([$username, $hash_pwd]);

    $_SESSION['uid'] = $pdo->lastInsertId();
    $_SESSION['username'] = $username;
    $_SESSION['msg'] = "注册成功，已自动登录";
    header("Location: index.php");
    exit;
}

// 学生登录
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $pwd = $_POST['password'];

    $stmt = $pdo->prepare("SELECT id, password FROM cho_user WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pwd, $user['password'])) {
        $_SESSION['msg'] = "用户名或密码错误";
        header("Location: index.php");
        exit;
    }

    $_SESSION['uid'] = $user['id'];
    $_SESSION['username'] = $username;
    $_SESSION['msg'] = "登录成功";
    header("Location: index.php");
    exit;
}

// ========== 新增教师登录 ==========
if(isset($_POST['teacher_login'])){
    $tname = trim($_POST['t_name']);
    $tpwd = $_POST['t_pwd'];
    $st = $pdo->prepare("SELECT id,t_pwd,t_realname FROM teacher WHERE t_name=?");
    $st->execute([$tname]);
    $tea = $st->fetch();
    if(!$tea || !password_verify($tpwd,$tea['t_pwd'])){
        $_SESSION['msg'] = "教师账号或密码错误";
        header("Location: admin.php");
        exit;
    }
    $_SESSION['tid'] = $tea['id'];
    $_SESSION['t_name'] = $tea['t_realname'];
    header("Location: admin.php");
    exit;
}
?>
