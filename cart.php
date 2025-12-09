<?php
session_start();

// 引入圖片輔助函數
require_once 'image_helper.php';

// 資料庫連線
require_once 'db_connect.php';
if ($conn->connect_error) die("連線失敗");
$conn->set_charset("utf8mb4");

// 處理購物車操作
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $sku_id = isset($_GET['sku_id']) ? $_GET['sku_id'] : '';
    
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = array();
    }
    
    switch($action) {
        case 'remove':
            if (isset($_SESSION['cart'][$sku_id])) {
                unset($_SESSION['cart'][$sku_id]);
            }
            break;
        case 'clear':
            $_SESSION['cart'] = array();
            break;
        case 'update':
            $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1;
            if ($quantity > 0) {
                $_SESSION['cart'][$sku_id] = $quantity;
            } else {
                unset($_SESSION['cart'][$sku_id]);
            }
            break;
    }
    
    header("Location: cart.php");
    exit;
}

// 讀取購物車商品
$cart_items = [];
$total_amount = 0;

if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $sku_id => $quantity) {
        $sql = "SELECT s.SKU_ID, s.Price, s.Stock, p.BrandName, p.ProductName, p.Category
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
        } else {
            // 商品不存在，從購物車移除
            unset($_SESSION['cart'][$sku_id]);
        }
    }
}

// 圖片將使用統一的圖片分配函數
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>購物車 | LaptopMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .cart-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .cart-item-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .quantity-btn {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: 1px solid #dee2e6;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .quantity-btn:hover {
            background: #f8f9fa;
            border-color: #0d6efd;
        }
        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            position: sticky;
            top: 20px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold text-primary" href="index.php"><i class="bi bi-laptop"></i> LaptopMart</a>
    <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 繼續購物</a>
  </div>
</nav>

<div class="container py-4">
    <div class="row">
        <div class="col-lg-8">
            <h3 class="fw-bold mb-4">購物車 (<?php echo count($cart_items); ?>)</h3>
            
            <?php if (count($cart_items) > 0): ?>
                <?php foreach ($cart_items as $item): 
                    $img_src = getProductImage(
                        $item['BrandName'], 
                        $item['SKU_ID'], 
                        $item['ProductName'], 
                        $item['Category'],
                        200
                    );
                ?>
                    <div class="cart-item">
                        <div class="d-flex align-items-center">
                            <img src="<?php echo $img_src; ?>" class="cart-item-img me-3" alt="<?php echo htmlspecialchars($item['ProductName']); ?>">
                            
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1"><?php echo htmlspecialchars($item['BrandName'] . ' ' . $item['ProductName']); ?></h6>
                                <div class="text-muted small mb-2">SKU: <?php echo htmlspecialchars($item['SKU_ID']); ?></div>
                                <div class="text-primary fw-bold fs-5">NT$ <?php echo number_format($item['Price']); ?></div>
                            </div>
                            
                            <div class="quantity-control me-4">
                                <button class="quantity-btn" onclick="updateQuantity('<?php echo $item['SKU_ID']; ?>', <?php echo $item['Quantity'] - 1; ?>)">-</button>
                                <span class="fw-bold" style="min-width: 30px; text-align: center;"><?php echo $item['Quantity']; ?></span>
                                <button class="quantity-btn" onclick="updateQuantity('<?php echo $item['SKU_ID']; ?>', <?php echo $item['Quantity'] + 1; ?>)">+</button>
                            </div>
                            
                            <div class="text-end me-4" style="min-width: 120px;">
                                <div class="fw-bold fs-5 text-primary">NT$ <?php echo number_format($item['Subtotal']); ?></div>
                                <small class="text-muted">小計</small>
                            </div>
                            
                            <button class="btn btn-link text-danger p-0" onclick="removeItem('<?php echo $item['SKU_ID']; ?>')" title="刪除">
                                <i class="bi bi-trash fs-5"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="text-end mb-4">
                    <button class="btn btn-outline-danger" onclick="clearCart()">
                        <i class="bi bi-trash me-2"></i>清空購物車
                    </button>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-cart-x" style="font-size: 4rem; color: #dee2e6;"></i>
                    <h5 class="mt-3 text-muted">購物車是空的</h5>
                    <p class="text-muted">快去選購您喜歡的商品吧！</p>
                    <a href="index.php" class="btn btn-primary mt-3">
                        <i class="bi bi-arrow-left me-2"></i>前往購物
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <div class="summary-card">
                <h5 class="fw-bold mb-4">訂單摘要</h5>
                
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">商品數量</span>
                    <span class="fw-bold"><?php echo count($cart_items); ?> 項</span>
                </div>
                
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">小計</span>
                    <span class="fw-bold">NT$ <?php echo number_format($total_amount); ?></span>
                </div>
                
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">運費</span>
                    <span class="fw-bold">NT$ <?php echo $total_amount > 0 ? '100' : '0'; ?></span>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-between mb-4">
                    <span class="fw-bold fs-5">總計</span>
                    <span class="fw-bold fs-4 text-primary">NT$ <?php echo number_format($total_amount + ($total_amount > 0 ? 100 : 0)); ?></span>
                </div>
                
                <?php if (count($cart_items) > 0): ?>
                    <a href="checkout.php" class="btn btn-primary w-100 btn-lg">
                        <i class="bi bi-credit-card me-2"></i>前往結帳
                    </a>
                <?php else: ?>
                    <button class="btn btn-secondary w-100 btn-lg" disabled>前往結帳</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function updateQuantity(skuId, quantity) {
        if (quantity < 1) {
            removeItem(skuId);
        } else {
            window.location.href = 'cart.php?action=update&sku_id=' + skuId + '&quantity=' + quantity;
        }
    }
    
    function removeItem(skuId) {
        if (confirm('確定要移除此商品嗎？')) {
            window.location.href = 'cart.php?action=remove&sku_id=' + skuId;
        }
    }
    
    function clearCart() {
        if (confirm('確定要清空購物車嗎？')) {
            window.location.href = 'cart.php?action=clear';
        }
    }
</script>
</body>
</html>

