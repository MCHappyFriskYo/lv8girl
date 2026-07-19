<?php
session_start();

// ========== 此处改成你服务器真实数据库信息 ==========
$host = 'lv8girl-db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';
$db_pass = 'yourpasswd';
// =====================================================

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

// 判断教师权限函数
function isTeacherLogin()
{
    if (!isset($_SESSION['uid'])) {
        return false;
    }
    global $pdo;
    $uid = $_SESSION['uid'];
    $sql = "SELECT role FROM cho_user WHERE id = ? LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$uid]);
    $row = $stmt->fetch();
    return $row && $row['role'] == 1;
}
?>
