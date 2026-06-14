<?php
$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';
$db_pass = 'yourpasswd';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('数据库连接失败：' . $e->getMessage());
}

function isAdmin($pdo) {
    if (!isset($_SESSION['handle'])) return false;
    if (isset($_SESSION['is_admin'])) return (bool)$_SESSION['is_admin'];
    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE handle = ?");
    $stmt->execute([$_SESSION['handle']]);
    $user = $stmt->fetch();
    $isAdmin = $user ? (bool)$user['is_admin'] : false;
    $_SESSION['is_admin'] = $isAdmin;
    return $isAdmin;
}

function generateLB($pdo) {
    $prefix = 'LB';
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $length = 8;
    do {
        $suffix = '';
        for ($i = 0; $i < $length; $i++) {
            $suffix .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $lb = $prefix . $suffix;
        $stmt = $pdo->prepare("SELECT id FROM videos WHERE lb = ?");
        $stmt->execute([$lb]);
        $exists = $stmt->fetch();
    } while ($exists);
    return $lb;
}
?>
