<?php
// 启动会话
session_start();

// 销毁所有会话数据
session_destroy();

// 重定向到网站首页
header('Location: index.php');
exit;