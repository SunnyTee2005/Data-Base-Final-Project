<?php
// 自動環境偵測與連線設定
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$dbname = "final_project_db";

// 嘗試 1: MAMP (Mac) 預設密碼 "root"
@$conn = new mysqli($host, "root", "root", $dbname);

// 嘗試 2: 若失敗，嘗試 XAMPP (Windows) 預設空密碼
if ($conn->connect_error) {
    @$conn = new mysqli($host, "root", "", $dbname);
}

// 最終檢查
if ($conn->connect_error) {
    die("資料庫連線失敗 (已嘗試跨平台設定): " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
