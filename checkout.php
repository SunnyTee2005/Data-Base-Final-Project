<?php
session_start();

// 如果購物車為空，重定向到購物車頁面
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit;
}

// 資料庫連線
$conn = new mysqli("localhost", "root", "", "final_project_db");
if ($conn->connect_error) die("連線失敗");
$conn->set_charset("utf8mb4");

// 處理結帳表單提交
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 驗證表單資料
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $payment_method = isset($_POST['payment_method']) ? trim($_POST['payment_method']) : '';
    
    // 基本驗證
    if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($payment_method)) {
        $error = '請填寫所有必填欄位';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '電子郵件格式不正確';
    } else {
        // 開始資料庫交易
        $conn->begin_transaction();
        
        try {
            // ===== 步驟 1: 取得或創建客戶 =====
            if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
                // 已登入：使用現有客戶ID
                $customer_id = intval($_SESSION['customer_id'] ?? 0);
                
                // 如果 customer_id 無效，嘗試從資料庫查詢
                if ($customer_id <= 0) {
                    // 如果有 email，從資料庫查詢
                    if (!empty($email)) {
                        $stmt = $conn->prepare("SELECT CustomerID FROM Customer WHERE Email = ?");
                        if ($stmt) {
                            $stmt->bind_param("s", $email);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0) {
                                $row = $result->fetch_assoc();
                                $customer_id = intval($row['CustomerID']);
                                // 更新 session
                                $_SESSION['customer_id'] = $customer_id;
                            }
                            $stmt->close();
                        }
                    }
                    
                    // 如果還是無效，當作未登入處理
                    if ($customer_id <= 0) {
                        // 當作未登入處理，使用下面的邏輯創建或查找客戶
                        $customer_id = null;
                    }
                }
            } else {
                $customer_id = null;
            }
            
            // 如果沒有有效的 customer_id，需要創建或查找
            if (!isset($customer_id) || $customer_id <= 0) {
                // 檢查或創建客戶
                $stmt = $conn->prepare("SELECT CustomerID FROM Customer WHERE Email = ?");
                if (!$stmt) {
                    throw new Exception('準備查詢失敗：' . $conn->error);
                }
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // 客戶已存在
                    $row = $result->fetch_assoc();
                    $customer_id = intval($row['CustomerID']);
                    // 如果已登入但 customer_id 不同，更新 session
                    if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
                        $_SESSION['customer_id'] = $customer_id;
                    }
                } else {
                    // 創建新客戶
                    $stmt->close();
                    $stmt = $conn->prepare("INSERT INTO Customer (Email, Name, Phone) VALUES (?, ?, ?)");
                    if (!$stmt) {
                        throw new Exception('準備插入客戶失敗：' . $conn->error);
                    }
                    $stmt->bind_param("sss", $email, $name, $phone);
                    if (!$stmt->execute()) {
                        throw new Exception('創建客戶失敗：' . $stmt->error);
                    }
                    $customer_id = intval($conn->insert_id);
                    if ($customer_id <= 0) {
                        throw new Exception('無法取得新客戶ID。請檢查 Customer 表的 CustomerID 欄位是否已開啟 AUTO_INCREMENT');
                    }
                    // 如果已登入，更新 session
                    if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
                        $_SESSION['customer_id'] = $customer_id;
                    }
                }
                $stmt->close();
            }
            
            // 最終驗證 customer_id
            if (!isset($customer_id) || $customer_id <= 0) {
                throw new Exception('無法取得有效的客戶ID。請確認已登入或填寫正確的電子郵件');
            }
            
            // ===== 步驟 2: 創建地址簿記錄 =====
            $stmt = $conn->prepare("INSERT INTO AddressBook (CustomerID, ReceiverName, Phone, Address, PaymentMethod) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception('準備插入地址失敗：' . $conn->error);
            }
            $stmt->bind_param("issss", $customer_id, $name, $phone, $address, $payment_method);
            if (!$stmt->execute()) {
                throw new Exception('創建地址簿記錄失敗：' . $stmt->error);
            }
            $address_id = intval($conn->insert_id);
            if ($address_id <= 0) {
                throw new Exception('無法取得地址ID');
            }
            $stmt->close();
            
            // ===== 步驟 3: 創建訂單主表 =====
            // 注意：不包含 Order_ID，讓資料庫自動遞增
            $stmt = $conn->prepare("INSERT INTO `Order` (Customer_ID, Address_ID, OrderDate, PaymentMethod, Status) VALUES (?, ?, NOW(), ?, 'Pending')");
            if (!$stmt) {
                throw new Exception('準備插入訂單失敗：' . $conn->error);
            }
            $stmt->bind_param("iis", $customer_id, $address_id, $payment_method);
            if (!$stmt->execute()) {
                throw new Exception('創建訂單失敗：' . $stmt->error);
            }
            $order_id = intval($conn->insert_id);
            if ($order_id <= 0) {
                throw new Exception('無法取得訂單ID。請檢查 Order 表的 Order_ID 欄位是否已開啟 AUTO_INCREMENT');
            }
            $stmt->close();
            
            // ===== 步驟 4: 創建訂單明細 =====
            if (empty($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
                throw new Exception('購物車為空');
            }
            
            $stmt = $conn->prepare("INSERT INTO OrderItem (Order_ID, SKU_ID, Quantity) VALUES (?, ?, ?)");
            if (!$stmt) {
                throw new Exception('準備插入訂單明細失敗：' . $conn->error);
            }
            
            foreach ($_SESSION['cart'] as $sku_id => $quantity) {
                // 驗證數量
                $quantity = intval($quantity);
                if ($quantity <= 0) {
                    continue; // 跳過無效數量
                }
                
                // 驗證 SKU_ID 是否存在
                $sku_id = trim($sku_id);
                if (empty($sku_id)) {
                    continue; // 跳過空 SKU_ID
                }
                
                $stmt->bind_param("isi", $order_id, $sku_id, $quantity);
                if (!$stmt->execute()) {
                    throw new Exception('創建訂單明細失敗（SKU: ' . $sku_id . '）：' . $stmt->error);
                }
            }
            $stmt->close();
            
            // ===== 提交交易 =====
            if (!$conn->commit()) {
                throw new Exception('提交交易失敗：' . $conn->error);
            }
            
            // ===== 成功：清空購物車並跳轉 =====
            $_SESSION['order_id'] = $order_id;
            $_SESSION['cart'] = array();
            
            header("Location: checkout_success.php");
            exit;
            
        } catch (Exception $e) {
            // 回滾交易
            $conn->rollback();
            // 顯示詳細錯誤（開發時使用，正式環境應隱藏詳細錯誤）
            $error = '訂單處理失敗：' . $e->getMessage();
            $error .= '<br><small>錯誤詳情：' . htmlspecialchars($e->getFile() . ':' . $e->getLine()) . '</small>';
        } catch (mysqli_sql_exception $e) {
            // MySQL 特定錯誤
            $conn->rollback();
            $error = '資料庫錯誤：' . $e->getMessage();
            $error .= '<br><small>錯誤代碼：' . $e->getCode() . '</small>';
        }
    }
}

// 如果已登入，獲取客戶資訊
$customer_info = null;
if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
    $customer_id = $_SESSION['customer_id'];
    $customer_sql = "SELECT * FROM Customer WHERE CustomerID = $customer_id";
    $customer_result = $conn->query($customer_sql);
    if ($customer_result && $customer_result->num_rows > 0) {
        $customer_info = $customer_result->fetch_assoc();
    }
}

// 讀取購物車商品
$cart_items = [];
$total_amount = 0;

foreach ($_SESSION['cart'] as $sku_id => $quantity) {
    $sql = "SELECT s.SKU_ID, s.Price, p.BrandName, p.ProductName
            FROM SKU s
            JOIN Product p ON s.ProductID = p.ProductID
            WHERE s.SKU_ID = '" . $conn->real_escape_string($sku_id) . "'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $row['Quantity'] = $quantity;
        $row['Subtotal'] = $row['Price'] * $quantity;
        $cart_items[] = $row;
        $total_amount += $row['Subtotal'];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>結帳 | LaptopMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .checkout-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold text-primary" href="index.php"><i class="bi bi-laptop"></i> LaptopMart</a>
    <a href="cart.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 返回購物車</a>
  </div>
</nav>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <h3 class="fw-bold mb-4">結帳資訊</h3>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="checkout-card">
                <h5 class="fw-bold mb-4">收件資訊</h5>
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">姓名 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" 
                                   value="<?php echo $customer_info ? htmlspecialchars($customer_info['Name']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">電子郵件 <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo $customer_info ? htmlspecialchars($customer_info['Email']) : ''; ?>" 
                                   <?php echo $customer_info ? 'readonly' : ''; ?> required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">電話 <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo $customer_info ? htmlspecialchars($customer_info['Phone'] ?? '') : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">付款方式 <span class="text-danger">*</span></label>
                            <select class="form-select" name="payment_method" required>
                                <option value="">請選擇</option>
                                <option value="Credit Card">信用卡</option>
                                <option value="Bank Transfer">銀行轉帳</option>
                                <option value="Cash on Delivery">貨到付款</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">收件地址 <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="address" rows="3" required></textarea>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-flex justify-content-between">
                        <a href="cart.php" class="btn btn-outline-secondary">返回購物車</a>
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-credit-card me-2"></i>確認訂單
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="checkout-card">
                <h5 class="fw-bold mb-4">訂單摘要</h5>
                
                <?php foreach ($cart_items as $item): ?>
                    <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                        <div>
                            <div class="fw-bold small"><?php echo htmlspecialchars($item['BrandName'] . ' ' . $item['ProductName']); ?></div>
                            <div class="text-muted small">數量：<?php echo $item['Quantity']; ?></div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">NT$ <?php echo number_format($item['Subtotal']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">小計</span>
                    <span class="fw-bold">NT$ <?php echo number_format($total_amount); ?></span>
                </div>
                
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">運費</span>
                    <span class="fw-bold">NT$ 100</span>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between">
                    <span class="fw-bold fs-5">總計</span>
                    <span class="fw-bold fs-4 text-primary">NT$ <?php echo number_format($total_amount + 100); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

