<?php
session_start();

// 清除所有 session 變數
$_SESSION = array();

// 銷毀 session
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

session_destroy();

// 重定向到首頁
header("Location: index.php");
exit;
?>

