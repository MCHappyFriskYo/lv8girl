<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

// 数据库配置，上传服务器务必修改为你服务器真实数据库信息
$host = 'lv8girl-db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';
$db_pass = 'yourpasswd';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("数据库连接失败：" . $e->getMessage());
}

// 判断当前登录用户是否为教师角色
function isTeacherLogin(){
    if(!isset($_SESSION['uid'])) return false;
    global $pdo;
    $uid = $_SESSION['uid'];
    $st = $pdo->prepare("SELECT role FROM cho_user WHERE id = ?");
    $st->execute([$uid]);
    $row = $st->fetch();
    return $row && $row['role'] == 1;
}
?>
