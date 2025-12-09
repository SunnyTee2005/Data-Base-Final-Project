<?php
require_once 'admin_auth.php';

// 資料庫連線
$conn = new mysqli("localhost", "root", "", "final_project_db");
if ($conn->connect_error) die("連線失敗");
$conn->set_charset("utf8mb4");

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 新增商品
    $brand = $conn->real_escape_string($_POST['brand']);
    $category = $conn->real_escape_string($_POST['category']);
    $product_name = $conn->real_escape_string($_POST['product_name']);
    $sku_id = $conn->real_escape_string($_POST['sku_id']);
    $cpu = $conn->real_escape_string($_POST['cpu']);
    $gpu = isset($_POST['gpu']) ? $conn->real_escape_string($_POST['gpu']) : '';
    $vram = isset($_POST['vram']) ? intval($_POST['vram']) : 0;
    $ram = intval($_POST['ram']);
    $storage_type = $conn->real_escape_string($_POST['storage_type']);
    $storage_capacity = intval($_POST['storage_capacity']);
    $screen_size = isset($_POST['screen_size']) ? $conn->real_escape_string($_POST['screen_size']) : '';
    $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 0;
    $price = intval($_POST['price']);
    $stock = intval($_POST['stock']);
    
    // 先檢查 Product 是否存在，如果不存在則新增
    $check_product_sql = "SELECT ProductID FROM Product WHERE BrandName = '$brand' AND ProductName = '$product_name'";
    $check_result = $conn->query($check_product_sql);
    
    if ($check_result->num_rows > 0) {
        $product_row = $check_result->fetch_assoc();
        $product_id = $product_row['ProductID'];
    } else {
        // 新增 Product
        $insert_product_sql = "INSERT INTO Product (BrandName, ProductName, Category, Status) 
                              VALUES ('$brand', '$product_name', '$category', 'Active')";
        if ($conn->query($insert_product_sql)) {
            $product_id = $conn->insert_id;
        } else {
            header("Location: admin_inventory.php?error=" . urlencode("新增商品失敗：" . $conn->error));
            exit;
        }
    }
    
    // 新增 SKU
    $insert_sku_sql = "INSERT INTO SKU (SKU_ID, ProductID, CPU, GPU, VRAM, RAM, StorageType, StorageCapacity, ScreenSize, Weight, Price, Stock) 
                      VALUES ('$sku_id', $product_id, '$cpu', '$gpu', $vram, $ram, '$storage_type', $storage_capacity, '$screen_size', $weight, $price, $stock)";
    
    if ($conn->query($insert_sku_sql)) {
        header("Location: admin_inventory.php?success=1");
    } else {
        header("Location: admin_inventory.php?error=" . urlencode("新增SKU失敗：" . $conn->error));
    }
    exit;
    
} elseif ($action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 編輯商品
    $sku_id = $conn->real_escape_string($_POST['sku_id']);
    $cpu = $conn->real_escape_string($_POST['cpu']);
    $gpu = isset($_POST['gpu']) ? $conn->real_escape_string($_POST['gpu']) : '';
    $vram = isset($_POST['vram']) ? intval($_POST['vram']) : 0;
    $ram = intval($_POST['ram']);
    $storage_type = $conn->real_escape_string($_POST['storage_type']);
    $storage_capacity = intval($_POST['storage_capacity']);
    $screen_size = isset($_POST['screen_size']) ? $conn->real_escape_string($_POST['screen_size']) : '';
    $weight = isset($_POST['weight']) ? floatval($_POST['weight']) : 0;
    $price = intval($_POST['price']);
    $stock = intval($_POST['stock']);
    
    // 更新 SKU
    $update_sql = "UPDATE SKU SET 
                   CPU = '$cpu',
                   GPU = '$gpu',
                   VRAM = $vram,
                   RAM = $ram,
                   StorageType = '$storage_type',
                   StorageCapacity = $storage_capacity,
                   ScreenSize = '$screen_size',
                   Weight = $weight,
                   Price = $price,
                   Stock = $stock
                   WHERE SKU_ID = '$sku_id'";
    
    if ($conn->query($update_sql)) {
        header("Location: admin_inventory.php?success=1");
    } else {
        header("Location: admin_inventory.php?error=" . urlencode("更新失敗：" . $conn->error));
    }
    exit;
    
} elseif ($action === 'update_stock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // 快速更新庫存
    $sku_id = $conn->real_escape_string($_POST['sku_id']);
    $stock = intval($_POST['stock']);
    
    $update_sql = "UPDATE SKU SET Stock = $stock WHERE SKU_ID = '$sku_id'";
    
    if ($conn->query($update_sql)) {
        header("Location: admin_inventory.php?success=1");
    } else {
        header("Location: admin_inventory.php?error=" . urlencode("更新庫存失敗：" . $conn->error));
    }
    exit;
    
} elseif ($action === 'delete' && isset($_GET['sku_id'])) {
    // 刪除商品
    $sku_id = $conn->real_escape_string($_GET['sku_id']);
    
    // 先檢查是否有訂單使用此 SKU
    $check_order_sql = "SELECT COUNT(*) as count FROM OrderItem WHERE SKU_ID = '$sku_id'";
    $check_result = $conn->query($check_order_sql);
    $order_count = $check_result->fetch_assoc()['count'];
    
    if ($order_count > 0) {
        header("Location: admin_inventory.php?error=" . urlencode("無法刪除：此商品已有訂單記錄"));
        exit;
    }
    
    // 刪除 SKU
    $delete_sql = "DELETE FROM SKU WHERE SKU_ID = '$sku_id'";
    if ($conn->query($delete_sql)) {
        header("Location: admin_inventory.php?success=1");
    } else {
        header("Location: admin_inventory.php?error=" . urlencode("刪除失敗：" . $conn->error));
    }
    exit;
    
} else {
    header("Location: admin_inventory.php");
    exit;
}
?>

