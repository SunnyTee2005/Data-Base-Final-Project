<?php
require_once 'admin_auth.php';

// 資料庫連線
$conn = new mysqli("localhost", "root", "", "final_project_db");
if ($conn->connect_error) die("連線失敗");
$conn->set_charset("utf8mb4");

// 處理訂單狀態更新
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = $conn->real_escape_string($_POST['status']);
    
    $update_sql = "UPDATE `Order` SET Status = '$new_status' WHERE Order_ID = $order_id";
    if ($conn->query($update_sql)) {
        header("Location: admin_orders.php?success=1");
        exit;
    }
}

// 查詢所有訂單
$sql = "SELECT o.Order_ID, o.Customer_ID, o.OrderDate, o.PaymentMethod, o.Status,
               SUM(oi.Quantity * s.Price) as TotalAmount,
               a.ReceiverName, a.Phone, a.Address
        FROM `Order` o
        LEFT JOIN OrderItem oi ON o.Order_ID = oi.Order_ID
        LEFT JOIN SKU s ON oi.SKU_ID = s.SKU_ID
        LEFT JOIN AddressBook a ON o.Address_ID = a.AddressID
        GROUP BY o.Order_ID
        ORDER BY o.OrderDate DESC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>訂單與出貨 | LaptopMart Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
        }
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.25rem;
            font-weight: bold;
        }
        .sidebar-logo i {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .nav-item {
            margin: 5px 15px;
        }
        .nav-link {
            color: rgba(255,255,255,0.7);
            padding: 12px 15px;
            border-radius: 8px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
            font-weight: 600;
        }
        .sidebar-footer {
            position: absolute;
            bottom: 20px;
            left: 0;
            right: 0;
            padding: 0 20px;
        }
        .main-content {
            margin-left: 260px;
            padding: 30px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #198754;
            font-weight: 500;
        }
        .status-dot {
            width: 10px;
            height: 10px;
            background: #198754;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-completed {
            background: #d1e7dd;
            color: #0f5132;
        }
        .badge-processing {
            background: #cfe2ff;
            color: #084298;
        }
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="bi bi-shield-check"></i>
                <span>AdminPanel</span>
            </div>
        </div>
        <nav>
            <div class="nav-item">
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>總覽儀表板</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_inventory.php" class="nav-link">
                    <i class="bi bi-box-seam"></i>
                    <span>商品與庫存</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_orders.php" class="nav-link active">
                    <i class="bi bi-truck"></i>
                    <span>訂單與出貨</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_analytics.php" class="nav-link">
                    <i class="bi bi-bar-chart"></i>
                    <span>銷售分析報表</span>
                </a>
            </div>
        </nav>
        <div class="sidebar-footer">
            <div class="nav-item">
                <a href="admin_logout.php" class="nav-link">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>登出</span>
                </a>
            </div>
            <div class="mt-3 text-center" style="color: rgba(255,255,255,0.6); font-size: 0.85rem;">v1.4.0 (Final Release)</div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h2 class="fw-bold mb-0">訂單與出貨 (Logistics)</h2>
            <div class="status-indicator">
                <span class="status-dot"></span>
                <span>System Online</span>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>訂單狀態已更新成功！
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>訂單 ID</th>
                            <th>客戶 ID</th>
                            <th>金額</th>
                            <th>狀態</th>
                            <th>收件資訊</th>
                            <th>狀態更新</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): 
                                $status_class = '';
                                $status_text = '';
                                switch($row['Status']) {
                                    case 'Completed':
                                        $status_class = 'badge-completed';
                                        $status_text = '已完成';
                                        break;
                                    case 'Processing':
                                        $status_class = 'badge-processing';
                                        $status_text = '處理中';
                                        break;
                                    case 'Pending':
                                    default:
                                        $status_class = 'badge-pending';
                                        $status_text = '待處理';
                                        break;
                                }
                            ?>
                                <tr>
                                    <td><strong>ORD-<?php echo str_pad($row['Order_ID'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td>C<?php echo str_pad($row['Customer_ID'], 3, '0', STR_PAD_LEFT); ?></td>
                                    <td><strong>NT$ <?php echo number_format($row['TotalAmount'] ?? 0); ?></strong></td>
                                    <td>
                                        <span class="badge-status <?php echo $status_class; ?>">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" 
                                                onclick="viewShipping(<?php echo $row['Order_ID']; ?>)"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#shippingModal">
                                            檢視出貨單
                                        </button>
                                        <div id="shipping-info-<?php echo $row['Order_ID']; ?>" style="display:none;">
                                            <small>
                                                <strong>收件人：</strong><?php echo htmlspecialchars($row['ReceiverName'] ?? 'N/A'); ?><br>
                                                <strong>電話：</strong><?php echo htmlspecialchars($row['Phone'] ?? 'N/A'); ?><br>
                                                <strong>地址：</strong><?php echo htmlspecialchars($row['Address'] ?? 'N/A'); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="order_id" value="<?php echo $row['Order_ID']; ?>">
                                            <select name="status" class="form-select form-select-sm d-inline-block" 
                                                    style="width: auto;" onchange="this.form.submit()">
                                                <option value="Pending" <?php echo $row['Status'] == 'Pending' ? 'selected' : ''; ?>>待處理</option>
                                                <option value="Processing" <?php echo $row['Status'] == 'Processing' ? 'selected' : ''; ?>>處理中</option>
                                                <option value="Completed" <?php echo $row['Status'] == 'Completed' ? 'selected' : ''; ?>>已完成</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                    <p class="mt-2 mb-0">目前沒有訂單</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Shipping Modal -->
    <div class="modal fade" id="shippingModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">出貨單資訊</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="shippingModalBody">
                    <!-- 內容將由 JavaScript 動態載入 -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">關閉</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewShipping(orderId) {
            const info = document.getElementById('shipping-info-' + orderId);
            const modalBody = document.getElementById('shippingModalBody');
            if (info) {
                modalBody.innerHTML = info.innerHTML;
            } else {
                modalBody.innerHTML = '<p class="text-muted">載入中...</p>';
            }
        }
    </script>
</body>
</html>

