<?php
$host = 'lv8girl-db';
$dbname = 'lv8girl';
$user = 'lv8girl';
$pass = 'yourpasswd';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    echo "连接成功！";
} catch (PDOException $e) {
    echo "连接失败：<br>";
    echo "错误码：" . $e->getCode() . "<br>";
    echo "错误信息：" . $e->getMessage() . "<br>";
    echo "请检查：<br>";
    echo "1. MySQL 服务是否运行<br>";
    echo "2. 主机、用户名、密码、数据库名是否正确<br>";
    echo "3. 如果端口不是 3306，请使用 127.0.0.1:端口号<br>";
}
?>
