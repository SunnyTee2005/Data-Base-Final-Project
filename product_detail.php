<?php
// 引入圖片輔助函數
require_once 'image_helper.php';

$conn = new mysqli("localhost", "root", "", "final_project_db");
$conn->set_charset("utf8mb4");

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$sku_id = isset($_GET['sku_id']) ? $conn->real_escape_string($_GET['sku_id']) : '';

// 如果有 SKU_ID，优先使用 SKU_ID；否则使用 ProductID
if ($sku_id) {
    $sql = "SELECT p.*, s.* FROM SKU s JOIN Product p ON s.ProductID = p.ProductID WHERE s.SKU_ID = '$sku_id'";
} else {
    $sql = "SELECT p.*, s.* FROM SKU s JOIN Product p ON s.ProductID = p.ProductID WHERE p.ProductID = $id LIMIT 1";
}
$result = $conn->query($sql);

if ($result->num_rows == 0) { header("Location: index.php"); exit; }
$row = $result->fetch_assoc();

// 使用統一的圖片分配函數
$img_src = getProductImage(
    $row["BrandName"], 
    $row["SKU_ID"], 
    $row["ProductName"], 
    $row["Category"],
    800
);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $row['ProductName']; ?> | LaptopMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .spec-box { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px; }
        .spec-title { font-size: 0.85em; color: #666; margin-bottom: 4px; }
        .spec-val { font-weight: 600; color: #333; }
        .feature-icon { color: #198754; margin-right: 8px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold text-primary" href="index.php"><i class="bi bi-laptop"></i> LaptopMart</a>
    <div class="d-flex gap-2">
        <?php
        session_start();
        $cart_count = 0;
        if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
            $cart_count = array_sum($_SESSION['cart']);
        }
        ?>
        <a class="btn btn-outline-primary btn-sm position-relative" href="cart.php">
            <i class="bi bi-cart3"></i>
            <?php if ($cart_count > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem;"><?php echo $cart_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> 回列表</a>
    </div>
  </div>
</nav>

<div class="container py-4">
    <div class="row bg-white p-4 rounded shadow-sm gx-5">
        <div class="col-md-6 mb-4">
            <img id="main-product-image" src="<?php echo $img_src; ?>" class="img-fluid rounded shadow-sm w-100 mb-3" alt="<?php echo htmlspecialchars($row['ProductName']); ?>">
            <div class="row g-2">
                <?php 
                // 生成不同的缩略图（使用不同的后缀来生成不同的图片）
                $thumb1 = getProductImage($row["BrandName"], $row["SKU_ID"] . '_thumb1', $row["ProductName"] . ' view1', $row["Category"], 200);
                $thumb2 = getProductImage($row["BrandName"], $row["SKU_ID"] . '_thumb2', $row["ProductName"] . ' view2', $row["Category"], 200);
                $thumb3 = getProductImage($row["BrandName"], $row["SKU_ID"] . '_thumb3', $row["ProductName"] . ' view3', $row["Category"], 200);
                ?>
                <div class="col-3">
                    <img src="<?php echo $thumb1; ?>" class="img-fluid rounded border border-primary" style="cursor: pointer; opacity: 0.8;" 
                         onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'"
                         onclick="document.getElementById('main-product-image').src=this.src">
                </div>
                <div class="col-3">
                    <img src="<?php echo $thumb2; ?>" class="img-fluid rounded border" style="cursor: pointer; opacity: 0.8;" 
                         onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'"
                         onclick="document.getElementById('main-product-image').src=this.src">
                </div>
                <div class="col-3">
                    <img src="<?php echo $thumb3; ?>" class="img-fluid rounded border" style="cursor: pointer; opacity: 0.8;" 
                         onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'"
                         onclick="document.getElementById('main-product-image').src=this.src">
                </div>
                <div class="col-3">
                    <img src="<?php echo $img_src; ?>" class="img-fluid rounded border border-primary" style="cursor: pointer; opacity: 0.8;" 
                         onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.8'"
                         onclick="document.getElementById('main-product-image').src=this.src">
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="mb-2">
                <span class="badge bg-primary"><?php echo $row['BrandName']; ?></span>
                <span class="badge bg-dark"><?php echo $row['Category']; ?></span>
            </div>
            
            <h2 class="fw-bold mb-2"><?php echo $row['ProductName']; ?></h2>
            
            <div class="mb-3 text-warning small">
                <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star"></i>
                <span class="text-muted ms-1">(156 評論)</span>
            </div>

            <h1 class="text-primary fw-bold mb-4">NT$ <?php echo number_format($row['Price']); ?></h1>

            <p class="text-muted mb-4">
                搭載強大效能的 <?php echo $row['CPU']; ?> 處理器與 <?php echo $row['RAM']; ?>GB 記憶體，
                <?php echo $row['ScreenSize']; ?> 吋清晰螢幕，無論是工作、學習還是娛樂都能輕鬆應對。
                配備 <?php echo $row['StorageCapacity']; ?>GB <?php echo $row['StorageType']; ?>，存取速度飛快。
            </p>

            <div class="row mb-4 small fw-bold">
                <div class="col-6 mb-2"><i class="bi bi-check-circle-fill feature-icon"></i><?php echo $row['GPU']; ?></div>
                <div class="col-6 mb-2"><i class="bi bi-check-circle-fill feature-icon"></i><?php echo $row['ScreenSize']; ?>吋螢幕</div>
                <div class="col-6 mb-2"><i class="bi bi-check-circle-fill feature-icon"></i>原廠保固一年</div>
                <div class="col-6 mb-2"><i class="bi bi-check-circle-fill feature-icon"></i>快速出貨</div>
            </div>

            <div class="card border-0 bg-light mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3 text-secondary">技術規格</h6>
                    <div class="row g-2">
                        <div class="col-6"><div class="spec-box"><div class="spec-title">處理器</div><div class="spec-val"><?php echo $row['CPU']; ?></div></div></div>
                        <div class="col-6"><div class="spec-box"><div class="spec-title">記憶體</div><div class="spec-val"><?php echo $row['RAM']; ?> GB</div></div></div>
                        <div class="col-6"><div class="spec-box"><div class="spec-title">顯示卡</div><div class="spec-val"><?php echo $row['GPU']; ?></div></div></div>
                        <div class="col-6"><div class="spec-box"><div class="spec-title">儲存空間</div><div class="spec-val"><?php echo $row['StorageCapacity']; ?>GB <?php echo $row['StorageType']; ?></div></div></div>
                        <div class="col-6"><div class="spec-box"><div class="spec-title">顯存 (VRAM)</div><div class="spec-val"><?php echo $row['VRAM'] > 0 ? $row['VRAM'].' GB' : 'N/A'; ?></div></div></div>
                        <div class="col-6"><div class="spec-box"><div class="spec-title">重量</div><div class="spec-val"><?php echo $row['Weight']; ?> kg</div></div></div>
                    </div>
                </div>
            </div>

            <form method="GET" action="add_to_cart.php" class="d-flex gap-3">
                <input type="hidden" name="sku_id" value="<?php echo htmlspecialchars($row['SKU_ID']); ?>">
                <div class="input-group" style="width: 140px;">
                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(-1)">-</button>
                    <input type="number" id="quantity" name="quantity" class="form-control text-center" value="1" min="1" max="<?php echo $row['Stock']; ?>">
                    <button class="btn btn-outline-secondary" type="button" onclick="changeQuantity(1)">+</button>
                </div>
                <button type="submit" class="btn btn-primary flex-grow-1 btn-lg shadow-sm">
                    <i class="bi bi-cart-plus me-2"></i>加入購物車
                </button>
            </form>
            
            <?php if ($row['Stock'] <= 0): ?>
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-exclamation-triangle me-2"></i>目前缺貨中
                </div>
            <?php elseif ($row['Stock'] <= 5): ?>
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle me-2"></i>庫存僅剩 <?php echo $row['Stock']; ?> 台
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function changeQuantity(delta) {
        const qtyInput = document.getElementById('quantity');
        let currentQty = parseInt(qtyInput.value) || 1;
        let newQty = currentQty + delta;
        const maxQty = <?php echo $row['Stock']; ?>;
        
        if (newQty < 1) newQty = 1;
        if (newQty > maxQty) newQty = maxQty;
        
        qtyInput.value = newQty;
    }
</script>
</body>
</html>