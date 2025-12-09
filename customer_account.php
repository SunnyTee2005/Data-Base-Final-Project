<?php
session_start();

// 檢查是否已登入
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
    header("Location: customer_login.php");
    exit;
}

// 資料庫連線
require_once 'db_connect.php';
if ($conn->connect_error) die("連線失敗");
$conn->set_charset("utf8mb4");

// 處理個人資料更新
$update_success = false;
$update_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    
    if (empty($name)) {
        $update_error = '姓名不能為空';
    } else {
        $customer_id = $_SESSION['customer_id'];
        $name_escaped = $conn->real_escape_string($name);
        $phone_escaped = $conn->real_escape_string($phone);
        
        $update_sql = "UPDATE Customer SET Name = '$name_escaped', Phone = '$phone_escaped' WHERE CustomerID = $customer_id";
        if ($conn->query($update_sql)) {
            $update_success = true;
            $_SESSION['customer_name'] = $name;
        } else {
            $update_error = '更新失敗：' . $conn->error;
        }
    }
}

// 查詢客戶資訊
$customer_id = isset($_SESSION['customer_id']) ? intval($_SESSION['customer_id']) : 0;
$customer = null;

if ($customer_id > 0) {
    $sql = "SELECT * FROM Customer WHERE CustomerID = $customer_id";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $customer = $result->fetch_assoc();
    }
}

// 如果找不到客戶資料，清除 session 並重定向到登入頁面
if (!$customer) {
    // 清除所有 session 資料，避免重定向循環
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-3600, '/');
    }
    session_destroy();
    // 重定向時添加參數，避免 customer_login.php 再次重定向回來
    header("Location: customer_login.php?logout=1");
    exit;
}

// 查詢訂單
$orders_sql = "SELECT o.*, SUM(oi.Quantity * s.Price) as TotalAmount
               FROM `Order` o
               LEFT JOIN OrderItem oi ON o.Order_ID = oi.Order_ID
               LEFT JOIN SKU s ON oi.SKU_ID = s.SKU_ID
               WHERE o.Customer_ID = $customer_id
               GROUP BY o.Order_ID
               ORDER BY o.OrderDate DESC
               LIMIT 10";
$orders_result = $conn->query($orders_sql);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的帳號 | LaptopMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Microsoft JhengHei', 'Segoe UI', sans-serif;
        }
        .account-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 30px;
        }
        .account-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .nav-pills .nav-link {
            color: #333;
            border-radius: 8px;
            margin-right: 10px;
        }
        .nav-pills .nav-link.active {
            background: #1e3a8a;
        }
        .order-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
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
        .status-completed {
            background: #d1e7dd;
            color: #0f5132;
        }
    </style>
</head>
<body>

<!-- 顶部导航栏 -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php" style="color: #1e3a8a;">
            <i class="bi bi-laptop"></i> LaptopMart
        </a>
        <div class="d-flex gap-3">
            <a href="index.php" class="btn btn-outline-secondary btn-sm">返回購物</a>
            <a href="customer_logout.php" class="btn btn-outline-danger btn-sm">登出</a>
        </div>
    </div>
</nav>

<!-- 帳號標題 -->
<div class="account-header">
    <div class="container">
        <div class="d-flex align-items-center">
            <div class="me-4">
                <i class="bi bi-person-circle" style="font-size: 4rem;"></i>
            </div>
            <div>
                <h2 class="mb-1"><?php echo htmlspecialchars($customer['Name']); ?></h2>
                <p class="mb-0 opacity-75"><?php echo htmlspecialchars($customer['Email']); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="row">
        <!-- 側邊欄選單 -->
        <div class="col-lg-3">
            <div class="account-card">
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="customer_account.php">
                            <i class="bi bi-person me-2"></i>個人資料
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customer_account.php#orders">
                            <i class="bi bi-receipt me-2"></i>我的訂單
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customer_account.php#addresses">
                            <i class="bi bi-geo-alt me-2"></i>收件地址
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="bi bi-cart3 me-2"></i>購物車
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- 主內容區 -->
        <div class="col-lg-9">
            <!-- 個人資料 -->
            <div class="account-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="fw-bold mb-0"><i class="bi bi-person me-2"></i>個人資料</h4>
                    <button class="btn btn-outline-primary btn-sm" onclick="toggleEditMode()" id="editBtn">
                        <i class="bi bi-pencil me-1"></i>編輯
                    </button>
                </div>
                
                <?php if ($update_success): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="bi bi-check-circle-fill me-2"></i>個人資料已更新！
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($update_error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($update_error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" id="profileForm">
                    <input type="hidden" name="update_profile" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">姓名 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo htmlspecialchars($customer['Name']); ?>" 
                                   id="nameInput" readonly required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">電子郵件</label>
                            <input type="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($customer['Email']); ?>" 
                                   readonly>
                            <small class="text-muted">電子郵件無法修改</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">電話</label>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($customer['Phone'] ?? ''); ?>" 
                                   id="phoneInput" readonly>
                        </div>
                    </div>
                    <div class="mt-3" id="saveBtnContainer" style="display: none;">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>儲存變更
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="cancelEdit()">
                            取消
                        </button>
                    </div>
                </form>
            </div>

            <!-- 我的訂單 -->
            <div class="account-card" id="orders">
                <h4 class="fw-bold mb-4"><i class="bi bi-receipt me-2"></i>我的訂單</h4>
                <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>訂單編號</th>
                                    <th>訂單日期</th>
                                    <th>金額</th>
                                    <th>狀態</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($order = $orders_result->fetch_assoc()): 
                                    $status_class = '';
                                    $status_text = '';
                                    switch($order['Status']) {
                                        case 'Completed':
                                            $status_class = 'status-completed';
                                            $status_text = '已完成';
                                            break;
                                        case 'Processing':
                                            $status_class = 'status-processing';
                                            $status_text = '處理中';
                                            break;
                                        case 'Pending':
                                        default:
                                            $status_class = 'status-pending';
                                            $status_text = '待處理';
                                            break;
                                    }
                                ?>
                                    <tr>
                                        <td><strong>ORD-<?php echo str_pad($order['Order_ID'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($order['OrderDate'])); ?></td>
                                        <td><strong>NT$ <?php echo number_format($order['TotalAmount'] ?? 0); ?></strong></td>
                                        <td>
                                            <span class="order-status <?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="order_detail.php?id=<?php echo $order['Order_ID']; ?>" class="btn btn-sm btn-outline-primary">
                                                查看詳情
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p class="text-muted mt-3">目前沒有訂單</p>
                        <a href="index.php" class="btn btn-primary">前往購物</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 收件地址 -->
            <div class="account-card" id="addresses">
                <h4 class="fw-bold mb-4"><i class="bi bi-geo-alt me-2"></i>收件地址</h4>
                <?php
                $addresses_sql = "SELECT * FROM AddressBook WHERE CustomerID = $customer_id";
                $addresses_result = $conn->query($addresses_sql);
                ?>
                <?php if ($addresses_result && $addresses_result->num_rows > 0): ?>
                    <div class="row g-3">
                        <?php while($address = $addresses_result->fetch_assoc()): ?>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title"><?php echo htmlspecialchars($address['ReceiverName']); ?></h6>
                                        <p class="card-text mb-1">
                                            <i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($address['Phone']); ?>
                                        </p>
                                        <p class="card-text mb-1">
                                            <i class="bi bi-geo-alt me-2"></i><?php echo htmlspecialchars($address['Address']); ?>
                                        </p>
                                        <p class="card-text">
                                            <small class="text-muted">付款方式：<?php echo htmlspecialchars($address['PaymentMethod']); ?></small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-geo-alt" style="font-size: 3rem; color: #dee2e6;"></i>
                        <p class="text-muted mt-3">目前沒有收件地址</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function toggleEditMode() {
        var nameInput = document.getElementById('nameInput');
        var phoneInput = document.getElementById('phoneInput');
        var saveBtnContainer = document.getElementById('saveBtnContainer');
        var editBtn = document.getElementById('editBtn');
        
        if (nameInput.readOnly) {
            nameInput.readOnly = false;
            phoneInput.readOnly = false;
            saveBtnContainer.style.display = 'block';
            editBtn.style.display = 'none';
            nameInput.focus();
        }
    }
    
    function cancelEdit() {
        location.reload();
    }
</script>
</body>
</html>

