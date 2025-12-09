<?php
/**
 * 圖片分配輔助函數
 * 根據品牌、SKU_ID、產品名稱等生成唯一的圖片URL
 */

function getProductImage($brand, $sku_id, $product_name, $category = '', $size = 600) {
    // 根據品牌和SKU_ID生成唯一的圖片索引
    $brand_hash = [
        'Apple' => 1,
        'ASUS' => 2,
        'Dell' => 3,
        'HP' => 4,
        'Lenovo' => 5,
        'Acer' => 6,
        'MSI' => 7,
    ];
    
    // 使用 SKU_ID 的數字部分或產品名稱來生成變化
    $sku_num = preg_replace('/[^0-9]/', '', $sku_id);
    $sku_index = !empty($sku_num) ? intval($sku_num) % 10 : 0;
    
    // 根據產品名稱生成哈希值
    $name_hash = crc32($product_name) % 5;
    
    // 品牌基礎圖片庫（每個品牌多張圖片）
    $brand_image_pools = [
        'Apple' => [
            'https://images.unsplash.com/photo-1517336714731-489689fd1ca4?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1504707748692-419802cf939d?w=' . $size . '&q=80',
        ],
        'ASUS' => [
            'https://images.unsplash.com/photo-1593642702821-c8da6771f0c6?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1504707748692-419802cf939d?w=' . $size . '&q=80',
        ],
        'Dell' => [
            'https://images.unsplash.com/photo-1593642632823-8f78536788c6?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1504707748692-419802cf939d?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=' . $size . '&q=80',
        ],
        'HP' => [
            'https://images.unsplash.com/photo-1589561084283-930aa7b1ce50?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1504707748692-419802cf939d?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=' . $size . '&q=80',
        ],
        'Lenovo' => [
            'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1504707748692-419802cf939d?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=' . $size . '&q=80',
        ],
        'Acer' => [
            'https://images.unsplash.com/photo-1544731612-de7f96afe55f?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1504707748692-419802cf939d?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1541807084-5c52b6b3adef?w=' . $size . '&q=80',
        ],
        'MSI' => [
            'https://images.unsplash.com/photo-1587614382346-4ec70e388b28?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=' . $size . '&q=80',
            'https://images.unsplash.com/photo-1504707748692-419802cf939d?w=' . $size . '&q=80',
        ],
    ];
    
    // 電競筆電專用圖片庫
    $gaming_images = [
        'https://images.unsplash.com/photo-1603302576837-37561b2e2302?w=' . $size . '&q=80',
        'https://images.unsplash.com/photo-1587614382346-4ec70e388b28?w=' . $size . '&q=80',
        'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=' . $size . '&q=80',
        'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?w=' . $size . '&q=80',
        'https://images.unsplash.com/photo-1504707748692-419802cf939d?w=' . $size . '&q=80',
    ];
    
    // 如果是電競筆電，使用電競圖片庫
    if (stripos($category, 'Gaming') !== false) {
        $image_index = ($sku_index + $name_hash) % count($gaming_images);
        return $gaming_images[$image_index];
    }
    
    // 根據品牌選擇圖片庫
    if (isset($brand_image_pools[$brand])) {
        $pool = $brand_image_pools[$brand];
        // 使用 SKU_ID 和產品名稱的組合來選擇圖片
        $image_index = ($sku_index + $name_hash) % count($pool);
        return $pool[$image_index];
    }
    
    // 預設圖片
    return 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=' . $size . '&q=80';
}
?>

