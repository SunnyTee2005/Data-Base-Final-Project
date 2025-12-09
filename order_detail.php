<?php
session_start();

// 檢查是否已登入
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
    header("Location: customer_login.php");
    exit;
}

// 獲取訂單ID
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($order_id == 0) {
    header("Location: customer_account.php");
    exit;
}

// 資料庫連線
require_once 'db_connect.php';
if ($conn->connect_error) die("連線失敗");
$conn->set_charset("utf8mb4");

// 查詢訂單資訊
$customer_id = $_SESSION['customer_id'];
$order_sql = "SELECT o.*, c.Name, c.Email, c.Phone as CustomerPhone,
                     a.ReceiverName, a.Phone, a.Address, a.PaymentMethod
              FROM `Order` o
              JOIN Customer c ON o.Customer_ID = c.CustomerID
              JOIN AddressBook a ON o.Address_ID = a.AddressID
              WHERE o.Order_ID = $order_id AND o.Customer_ID = $customer_id";
$order_result = $conn->query($order_sql);

if (!$order_result || $order_result->num_rows == 0) {
    header("Location: customer_account.php");
    exit;
}

$order = $order_result->fetch_assoc();

// 查詢訂單商品明細
require_once 'image_helper.php';
$items_sql = "SELECT oi.*, s.*, p.BrandName, p.ProductName, p.Category
              FROM OrderItem oi
              JOIN SKU s ON oi.SKU_ID = s.SKU_ID
              JOIN Product p ON s.ProductID = p.ProductID
              WHERE oi.Order_ID = $order_id";
$items_result = $conn->query($items_sql);

// 計算總金額
$total_amount = 0;
$items = [];
while ($item = $items_result->fetch_assoc()) {
    $item_total = $item['Quantity'] * $item['Price'];
    $total_amount += $item_total;
    $items[] = $item;
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>訂單詳情 | LaptopMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Microsoft JhengHei', 'Segoe UI', sans-serif;
        }
        .order-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        .order-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
        .order-status {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-processing {
            background: #cfe2ff;
            color: #084298;
        }
        .status-shipped {
            background: #d1e7dd;
            color: #0f5132;
        }
        .status-completed {
            background: #d1e7dd;
            color: #0f5132;
        }
        .status-cancelled {
            background: #f8d7da;
            color: #842029;
        }
        .item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- 顶部导航 -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-laptop"></i> LaptopMart
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">首頁</a>
                <a class="nav-link" href="customer_account.php">我的帳號</a>
                <a class="nav-link" href="customer_logout.php">登出</a>
            </div>
        </div>
    </nav>

    <div class="order-header">
        <div class="container">
            <h2 class="mb-2">訂單詳情</h2>
            <p class="mb-0 opacity-75">訂單編號：ORD-<?php echo str_pad($order['Order_ID'], 3, '0', STR_PAD_LEFT); ?></p>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-lg-8">
                <!-- 訂單商品 -->
                <div class="order-card">
                    <h5 class="fw-bold mb-4"><i class="bi bi-box-seam me-2"></i>訂單商品</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>商品</th>
                                    <th>規格</th>
                                    <th>數量</th>
                                    <th>單價</th>
                                    <th>小計</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): 
                                    $img_src = getProductImage(
                                        $item['BrandName'],
                                        $item['SKU_ID'],
                                        $item['ProductName'],
                                        $item['Category'],
                                        200
                                    );
                                    $item_total = $item['Quantity'] * $item['Price'];
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo $img_src; ?>" alt="<?php echo htmlspecialchars($item['ProductName']); ?>" class="item-image me-3">
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($item['BrandName']); ?></div>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($item['ProductName']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($item['CPU']); ?> / 
                                                <?php echo $item['RAM']; ?>GB / 
                                                <?php echo $item['StorageCapacity']; ?>GB
                                            </small>
                                        </td>
                                        <td><?php echo $item['Quantity']; ?></td>
                                        <td>NT$ <?php echo number_format($item['Price']); ?></td>
                                        <td class="fw-bold">NT$ <?php echo number_format($item_total); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- 訂單資訊 -->
                <div class="order-card">
                    <h5 class="fw-bold mb-4"><i class="bi bi-info-circle me-2"></i>訂單資訊</h5>
                    <div class="mb-3">
                        <label class="text-muted small">訂單狀態</label>
                        <div>
                            <?php
                            $status_class = '';
                            $status_text = '';
                            switch($order['Status']) {
                                case 'Pending':
                                    $status_class = 'status-pending';
                                    $status_text = '待處理';
                                    break;
                                case 'Processing':
                                    $status_class = 'status-processing';
                                    $status_text = '處理中';
                                    break;
                                case 'Shipped':
                                    $status_class = 'status-shipped';
                                    $status_text = '已出貨';
                                    break;
                                case 'Completed':
                                    $status_class = 'status-completed';
                                    $status_text = '已完成';
                                    break;
                                case 'Cancelled':
                                    $status_class = 'status-cancelled';
                                    $status_text = '已取消';
                                    break;
                                default:
                                    $status_class = 'status-pending';
                                    $status_text = $order['Status'];
                            }
                            ?>
                            <span class="order-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">訂單日期</label>
                        <div class="fw-bold"><?php echo date('Y年m月d日 H:i', strtotime($order['OrderDate'])); ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="text-muted small">付款方式</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($order['PaymentMethod']); ?></div>
                    </div>
                </div>

                <!-- 收貨資訊 -->
                <div class="order-card">
                    <h5 class="fw-bold mb-4"><i class="bi bi-geo-alt me-2"></i>收貨資訊</h5>
                    <div class="mb-2">
                        <label class="text-muted small">收貨人</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($order['ReceiverName']); ?></div>
                    </div>
                    <div class="mb-2">
                        <label class="text-muted small">電話</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($order['Phone']); ?></div>
                    </div>
                    <div class="mb-2">
                        <label class="text-muted small">地址</label>
                        <div class="fw-bold"><?php echo htmlspecialchars($order['Address']); ?></div>
                    </div>
                </div>

                <!-- 金額總計 -->
                <div class="order-card">
                    <h5 class="fw-bold mb-4"><i class="bi bi-calculator me-2"></i>金額總計</h5>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">商品總額</span>
                        <span>NT$ <?php echo number_format($total_amount); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">運費</span>
                        <span>NT$ 0</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold fs-5">總計</span>
                        <span class="fw-bold fs-5 text-primary">NT$ <?php echo number_format($total_amount); ?></span>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="customer_account.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left me-2"></i>返回訂單列表
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

