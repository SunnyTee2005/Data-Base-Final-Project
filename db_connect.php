<?php
// 防止重複開啟 Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$user = "root";
$dbname = "final_project_db";

// 嘗試 1: MAMP 預設 (密碼為 "root")
@$conn = new mysqli($host, $user, "root", $dbname);

// 嘗試 2: 如果失敗，嘗試 XAMPP/Windows 預設 (密碼為空字串)
if ($conn->connect_error) {
    @$conn = new mysqli($host, $user, "", $dbname);
}

// 最終檢查: 如果兩者都失敗，才報錯
if ($conn->connect_error) {
    die("資料庫連線失敗 (已嘗試常見密碼組合): " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
