<?php
session_start();

// 檢查有沒有傳送 SKU_ID 過來
if (isset($_GET['sku_id'])) {
    $sku_id = $_GET['sku_id'];
    $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1;
    
    // 如果購物車 session 還不存在，就建立一個空的陣列
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = array();
    }

    // 邏輯：如果商品已在車內，數量增加；如果沒有，就設為指定數量
    if (isset($_SESSION['cart'][$sku_id])) {
        $_SESSION['cart'][$sku_id] += $quantity;
    } else {
        $_SESSION['cart'][$sku_id] = $quantity;
    }
}

// 處理完後，跳回上一頁 (或是首頁)
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php';
header("Location: $referer");
exit;
?>