<?php
// 后台身份验证辅助文件
// 在所有后台页面开头 include 此文件以检查登录状态

session_start();

// 检查管理员登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // 如果没有管理员登录，重定向到登录页面（带admin参数）
    header("Location: customer_login.php?admin=1&redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

