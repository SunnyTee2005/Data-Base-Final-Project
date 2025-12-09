<?php
session_start();

// 如果沒有訂單ID，重定向到首頁
if (!isset($_SESSION['order_id'])) {
    header("Location: index.php");
    exit;
}

$order_id = $_SESSION['order_id'];

// 資料庫連線
require_once 'db_connect.php';
if ($conn->connect_error) die("連線失敗");
$conn->set_charset("utf8mb4");

// 查詢訂單資訊
$sql = "SELECT o.Order_ID, o.OrderDate, o.PaymentMethod, o.Status,
               c.Name, c.Email, c.Phone,
               a.Address
        FROM `Order` o
        JOIN Customer c ON o.Customer_ID = c.CustomerID
        JOIN AddressBook a ON o.Address_ID = a.AddressID
        WHERE o.Order_ID = $order_id";
$result = $conn->query($sql);
$order = $result->fetch_assoc();

// 清除訂單ID session
unset($_SESSION['order_id']);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>訂單成功 | LaptopMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .success-card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
            max-width: 600px;
            margin: 0 auto;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #d1e7dd;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .success-icon i {
            font-size: 3rem;
            color: #198754;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold text-primary" href="index.php"><i class="bi bi-laptop"></i> LaptopMart</a>
  </div>
</nav>

<div class="container py-5">
    <div class="success-card">
        <div class="success-icon">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        
        <h2 class="fw-bold mb-3">訂單已成功送出！</h2>
        <p class="text-muted mb-4">感謝您的購買，我們會盡快處理您的訂單。</p>
        
        <div class="card bg-light mb-4">
            <div class="card-body text-start">
                <h6 class="fw-bold mb-3">訂單資訊</h6>
                <div class="row g-2 mb-2">
                    <div class="col-4"><strong>訂單編號：</strong></div>
                    <div class="col-8">ORD-<?php echo str_pad($order['Order_ID'], 3, '0', STR_PAD_LEFT); ?></div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-4"><strong>訂單日期：</strong></div>
                    <div class="col-8"><?php echo date('Y-m-d H:i', strtotime($order['OrderDate'])); ?></div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-4"><strong>付款方式：</strong></div>
                    <div class="col-8"><?php echo htmlspecialchars($order['PaymentMethod']); ?></div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-4"><strong>訂單狀態：</strong></div>
                    <div class="col-8">
                        <span class="badge bg-warning"><?php echo htmlspecialchars($order['Status']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-flex gap-3 justify-content-center">
            <a href="index.php" class="btn btn-primary">
                <i class="bi bi-house me-2"></i>返回首頁
            </a>
            <a href="index.php" class="btn btn-outline-primary">
                <i class="bi bi-cart me-2"></i>繼續購物
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

