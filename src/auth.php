<?php
require_once "config.php";

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // 必须查询role字段！少了就失效
    $stmt = $pdo->prepare("SELECT id, username, password, role FROM cho_user WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $_SESSION['msg'] = "用户名或密码错误";
        header("Location: index.php");
        exit;
    }

    // 登录成功 存入角色SESSION（关键代码）
    $_SESSION['uid'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role']; // 缺失这一行就看不到后台按钮
    $_SESSION['msg'] = "登录成功";
    header("Location: index.php");
    exit;
}

// 注册逻辑
if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $pwd1 = $_POST['password'];
    $pwd2 = $_POST['password2'];

    if ($pwd1 !== $pwd2) {
        $_SESSION['msg'] = "两次密码不一致";
        header("Location: index.php");
        exit;
    }

    // 检查用户名是否重复
    $check = $pdo->prepare("SELECT id FROM cho_user WHERE username = ?");
    $check->execute([$username]);
    if ($check->rowCount() > 0) {
        $_SESSION['msg'] = "用户名已被占用";
        header("Location: index.php");
        exit;
    }

    $hashPwd = password_hash($pwd1, PASSWORD_DEFAULT);
    $add = $pdo->prepare("INSERT INTO cho_user (username, password, role) VALUES (?, ?, 0)");
    $add->execute([$username, $hashPwd]);

    $_SESSION['msg'] = "注册成功，请登录";
    header("Location: index.php");
    exit;
}

header("Location: index.php");
exit;
?>
