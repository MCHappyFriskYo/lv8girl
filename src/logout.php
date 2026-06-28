<?php
session_start();
session_destroy();
echo json_encode(['code' => 0, 'message' => '已登出']);
?>