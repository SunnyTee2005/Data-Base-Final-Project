<?php
// 開啟錯誤回報
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// 引入圖片輔助函數
require_once 'image_helper.php';

// 資料庫連線
require_once 'db_connect.php';
if ($conn->connect_error) die("連線失敗");
$conn->set_charset("utf8mb4");

// 接收參數
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$brand = isset($_GET['brand']) ? trim($_GET['brand']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'recommend';
$min_price = isset($_GET['min_price']) ? intval($_GET['min_price']) : 0;
$max_price = isset($_GET['max_price']) ? intval($_GET['max_price']) : 0;
// 支持多选的尺寸和容量
$screen_sizes = isset($_GET['screen_size']) ? (is_array($_GET['screen_size']) ? $_GET['screen_size'] : [$_GET['screen_size']]) : [];
$screen_sizes = array_filter($screen_sizes); // 移除空值
$storages = isset($_GET['storage']) ? (is_array($_GET['storage']) ? $_GET['storage'] : [$_GET['storage']]) : [];
$storages = array_filter($storages); // 移除空值
// 新增筛选选项
$cpus = isset($_GET['cpu']) ? (is_array($_GET['cpu']) ? $_GET['cpu'] : [$_GET['cpu']]) : [];
$cpus = array_filter($cpus);
$rams = isset($_GET['ram']) ? (is_array($_GET['ram']) ? $_GET['ram'] : [$_GET['ram']]) : [];
$rams = array_filter($rams);
$gpus = isset($_GET['gpu']) ? (is_array($_GET['gpu']) ? $_GET['gpu'] : [$_GET['gpu']]) : [];
$gpus = array_filter($gpus);
$vrams = isset($_GET['vram']) ? (is_array($_GET['vram']) ? $_GET['vram'] : [$_GET['vram']]) : [];
$vrams = array_filter($vrams);
$weights = isset($_GET['weight']) ? (is_array($_GET['weight']) ? $_GET['weight'] : [$_GET['weight']]) : [];
$weights = array_filter($weights);

// 組合 SQL
$sql = "SELECT p.*, s.* FROM SKU s JOIN Product p ON s.ProductID = p.ProductID WHERE 1=1";

// 搜索功能
if ($search && $search != '') {
    $search_escaped = $conn->real_escape_string($search);
    $sql .= " AND (
        p.ProductName LIKE '%$search_escaped%' 
        OR p.BrandName LIKE '%$search_escaped%' 
        OR s.SKU_ID LIKE '%$search_escaped%'
        OR s.CPU LIKE '%$search_escaped%'
        OR s.GPU LIKE '%$search_escaped%'
        OR CONCAT(p.BrandName, ' ', p.ProductName) LIKE '%$search_escaped%'
    )";
}

// 品牌筛选
if ($brand && $brand != '') {
    $brand_escaped = $conn->real_escape_string($brand);
    $sql .= " AND p.BrandName = '$brand_escaped'";
}

// 分类筛选
if ($category && $category != '') {
    $category_escaped = $conn->real_escape_string($category);
    $sql .= " AND p.Category = '$category_escaped'";
}

// 价格范围筛选
if ($min_price > 0) {
    $sql .= " AND s.Price >= $min_price";
}
if ($max_price > 0) {
    $sql .= " AND s.Price <= $max_price";
}

// 屏幕尺寸筛选（支持多选）
if (!empty($screen_sizes)) {
    $size_conditions = [];
    foreach ($screen_sizes as $size) {
        $size_escaped = $conn->real_escape_string($size);
        $size_conditions[] = "s.ScreenSize LIKE '%$size_escaped%'";
    }
    if (!empty($size_conditions)) {
        $sql .= " AND (" . implode(" OR ", $size_conditions) . ")";
    }
}

// 存储容量筛选（支持多选）
if (!empty($storages)) {
    $storage_conditions = [];
    foreach ($storages as $stor) {
        if ($stor == '512GB') {
            $storage_conditions[] = "(s.StorageCapacity >= 512 AND s.StorageCapacity < 1024)";
        } elseif ($stor == '1TB') {
            $storage_conditions[] = "(s.StorageCapacity >= 1024 AND s.StorageCapacity < 2048)";
        } elseif ($stor == '2TB') {
            $storage_conditions[] = "(s.StorageCapacity >= 2048)";
        }
    }
    if (!empty($storage_conditions)) {
        $sql .= " AND (" . implode(" OR ", $storage_conditions) . ")";
    }
}

// 处理器筛选（支持多选）
if (!empty($cpus)) {
    $cpu_conditions = [];
    foreach ($cpus as $cpu) {
        $cpu_escaped = $conn->real_escape_string($cpu);
        $cpu_conditions[] = "s.CPU LIKE '%$cpu_escaped%'";
    }
    if (!empty($cpu_conditions)) {
        $sql .= " AND (" . implode(" OR ", $cpu_conditions) . ")";
    }
}

// 内存筛选（支持多选）
if (!empty($rams)) {
    $ram_conditions = [];
    foreach ($rams as $ram) {
        $ram_val = intval($ram);
        $ram_conditions[] = "s.RAM = $ram_val";
    }
    if (!empty($ram_conditions)) {
        $sql .= " AND (" . implode(" OR ", $ram_conditions) . ")";
    }
}

// 显卡筛选（支持多选）
if (!empty($gpus)) {
    $gpu_conditions = [];
    foreach ($gpus as $gpu) {
        $gpu_escaped = $conn->real_escape_string($gpu);
        $gpu_conditions[] = "s.GPU LIKE '%$gpu_escaped%'";
    }
    if (!empty($gpu_conditions)) {
        $sql .= " AND (" . implode(" OR ", $gpu_conditions) . ")";
    }
}

// 显存筛选（支持多选）
if (!empty($vrams)) {
    $vram_conditions = [];
    foreach ($vrams as $vram) {
        if ($vram == 'N/A' || $vram == '0') {
            $vram_conditions[] = "(s.VRAM = 0 OR s.VRAM IS NULL)";
        } else {
            $vram_val = intval($vram);
            $vram_conditions[] = "s.VRAM = $vram_val";
        }
    }
    if (!empty($vram_conditions)) {
        $sql .= " AND (" . implode(" OR ", $vram_conditions) . ")";
    }
}

// 重量筛选（支持多选）
if (!empty($weights)) {
    $weight_conditions = [];
    foreach ($weights as $weight) {
        if ($weight == '<1.5') {
            $weight_conditions[] = "s.Weight < 1.5";
        } elseif ($weight == '1.5-2.0') {
            $weight_conditions[] = "(s.Weight >= 1.5 AND s.Weight < 2.0)";
        } elseif ($weight == '2.0-2.5') {
            $weight_conditions[] = "(s.Weight >= 2.0 AND s.Weight < 2.5)";
        } elseif ($weight == '>2.5') {
            $weight_conditions[] = "s.Weight >= 2.5";
        }
    }
    if (!empty($weight_conditions)) {
        $sql .= " AND (" . implode(" OR ", $weight_conditions) . ")";
    }
}

// 排序
switch($sort) {
    case 'price_asc':
        $sql .= " ORDER BY s.Price ASC";
        break;
    case 'price_desc':
        $sql .= " ORDER BY s.Price DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY s.Stock DESC, s.Price ASC";
        break;
    case 'new':
        $sql .= " ORDER BY s.SKU_ID DESC";
        break;
    default:
        $sql .= " ORDER BY p.BrandName, p.ProductName";
        break;
}

// 先获取总数（在添加ORDER BY和LIMIT之前）
$count_sql = "SELECT COUNT(*) as total FROM SKU s JOIN Product p ON s.ProductID = p.ProductID WHERE 1=1";

// 应用相同的筛选条件
if ($search && $search != '') {
    $search_escaped = $conn->real_escape_string($search);
    $count_sql .= " AND (
        p.ProductName LIKE '%$search_escaped%' 
        OR p.BrandName LIKE '%$search_escaped%' 
        OR s.SKU_ID LIKE '%$search_escaped%'
        OR s.CPU LIKE '%$search_escaped%'
        OR s.GPU LIKE '%$search_escaped%'
        OR CONCAT(p.BrandName, ' ', p.ProductName) LIKE '%$search_escaped%'
    )";
}
if ($brand && $brand != '') {
    $brand_escaped = $conn->real_escape_string($brand);
    $count_sql .= " AND p.BrandName = '$brand_escaped'";
}
if ($category && $category != '') {
    $category_escaped = $conn->real_escape_string($category);
    $count_sql .= " AND p.Category = '$category_escaped'";
}
if ($min_price > 0) {
    $count_sql .= " AND s.Price >= $min_price";
}
if ($max_price > 0) {
    $count_sql .= " AND s.Price <= $max_price";
}
if (!empty($screen_sizes)) {
    $size_conditions = [];
    foreach ($screen_sizes as $size) {
        $size_escaped = $conn->real_escape_string($size);
        $size_conditions[] = "s.ScreenSize LIKE '%$size_escaped%'";
    }
    if (!empty($size_conditions)) {
        $count_sql .= " AND (" . implode(" OR ", $size_conditions) . ")";
    }
}
if (!empty($storages)) {
    $storage_conditions = [];
    foreach ($storages as $stor) {
        if ($stor == '512GB') {
            $storage_conditions[] = "(s.StorageCapacity >= 512 AND s.StorageCapacity < 1024)";
        } elseif ($stor == '1TB') {
            $storage_conditions[] = "(s.StorageCapacity >= 1024 AND s.StorageCapacity < 2048)";
        } elseif ($stor == '2TB') {
            $storage_conditions[] = "(s.StorageCapacity >= 2048)";
        }
    }
    if (!empty($storage_conditions)) {
        $count_sql .= " AND (" . implode(" OR ", $storage_conditions) . ")";
    }
}
if (!empty($cpus)) {
    $cpu_conditions = [];
    foreach ($cpus as $cpu) {
        $cpu_escaped = $conn->real_escape_string($cpu);
        $cpu_conditions[] = "s.CPU LIKE '%$cpu_escaped%'";
    }
    if (!empty($cpu_conditions)) {
        $count_sql .= " AND (" . implode(" OR ", $cpu_conditions) . ")";
    }
}
if (!empty($rams)) {
    $ram_conditions = [];
    foreach ($rams as $ram) {
        $ram_val = intval($ram);
        $ram_conditions[] = "s.RAM = $ram_val";
    }
    if (!empty($ram_conditions)) {
        $count_sql .= " AND (" . implode(" OR ", $ram_conditions) . ")";
    }
}
if (!empty($gpus)) {
    $gpu_conditions = [];
    foreach ($gpus as $gpu) {
        $gpu_escaped = $conn->real_escape_string($gpu);
        $gpu_conditions[] = "s.GPU LIKE '%$gpu_escaped%'";
    }
    if (!empty($gpu_conditions)) {
        $count_sql .= " AND (" . implode(" OR ", $gpu_conditions) . ")";
    }
}
if (!empty($vrams)) {
    $vram_conditions = [];
    foreach ($vrams as $vram) {
        if ($vram == 'N/A' || $vram == '0') {
            $vram_conditions[] = "(s.VRAM = 0 OR s.VRAM IS NULL)";
        } else {
            $vram_val = intval($vram);
            $vram_conditions[] = "s.VRAM = $vram_val";
        }
    }
    if (!empty($vram_conditions)) {
        $count_sql .= " AND (" . implode(" OR ", $vram_conditions) . ")";
    }
}
if (!empty($weights)) {
    $weight_conditions = [];
    foreach ($weights as $weight) {
        if ($weight == '<1.5') {
            $weight_conditions[] = "s.Weight < 1.5";
        } elseif ($weight == '1.5-2.0') {
            $weight_conditions[] = "(s.Weight >= 1.5 AND s.Weight < 2.0)";
        } elseif ($weight == '2.0-2.5') {
            $weight_conditions[] = "(s.Weight >= 2.0 AND s.Weight < 2.5)";
        } elseif ($weight == '>2.5') {
            $weight_conditions[] = "s.Weight >= 2.5";
        }
    }
    if (!empty($weight_conditions)) {
        $count_sql .= " AND (" . implode(" OR ", $weight_conditions) . ")";
    }
}

$count_result = $conn->query($count_sql);
$total_products = $count_result->fetch_assoc()['total'];

// 分页设置
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 24; // 每页显示24个商品
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_products / $per_page);

// 添加分页限制
$sql .= " LIMIT $per_page OFFSET $offset";
$result = $conn->query($sql);

// 获取品牌列表和数量
$brands_sql = "SELECT p.BrandName, COUNT(*) as count 
               FROM SKU s 
               JOIN Product p ON s.ProductID = p.ProductID 
               WHERE 1=1";
if ($category) {
    $category_escaped = $conn->real_escape_string($category);
    $brands_sql .= " AND p.Category = '$category_escaped'";
}
$brands_sql .= " GROUP BY p.BrandName ORDER BY p.BrandName";
$brands_result = $conn->query($brands_sql);
$brands_list = [];
while($brand_row = $brands_result->fetch_assoc()) {
    $brands_list[$brand_row['BrandName']] = $brand_row['count'];
}

// 获取分类列表和数量
$categories_sql = "SELECT p.Category, COUNT(*) as count 
                  FROM SKU s 
                  JOIN Product p ON s.ProductID = p.ProductID 
                  GROUP BY p.Category ORDER BY p.Category";
$categories_result = $conn->query($categories_sql);
$categories_list = [];
while($cat_row = $categories_result->fetch_assoc()) {
    $categories_list[$cat_row['Category']] = $cat_row['count'];
}

// 获取尺寸列表和数量（基于当前筛选条件）
$size_base_sql = "SELECT DISTINCT s.ScreenSize, COUNT(*) as count 
                  FROM SKU s 
                  JOIN Product p ON s.ProductID = p.ProductID 
                  WHERE 1=1";
if ($brand) {
    $brand_escaped = $conn->real_escape_string($brand);
    $size_base_sql .= " AND p.BrandName = '$brand_escaped'";
}
if ($category) {
    $category_escaped = $conn->real_escape_string($category);
    $size_base_sql .= " AND p.Category = '$category_escaped'";
}
$size_base_sql .= " GROUP BY s.ScreenSize ORDER BY s.ScreenSize";
$size_result = $conn->query($size_base_sql);
$sizes_list = [];
while($size_row = $size_result->fetch_assoc()) {
    $size_val = $size_row['ScreenSize'];
    // 提取数字部分
    preg_match('/(\d+)/', $size_val, $matches);
    if (isset($matches[1])) {
        $size_key = $matches[1] . '吋';
        if (!isset($sizes_list[$size_key])) {
            $sizes_list[$size_key] = 0;
        }
        $sizes_list[$size_key] += $size_row['count'];
    } else {
        if (!isset($sizes_list['未分類'])) {
            $sizes_list['未分類'] = 0;
        }
        $sizes_list['未分類'] += $size_row['count'];
    }
}

// 获取容量列表和数量
$storage_base_sql = "SELECT s.StorageCapacity, COUNT(*) as count 
                     FROM SKU s 
                     JOIN Product p ON s.ProductID = p.ProductID 
                     WHERE 1=1";
if ($brand) {
    $brand_escaped = $conn->real_escape_string($brand);
    $storage_base_sql .= " AND p.BrandName = '$brand_escaped'";
}
if ($category) {
    $category_escaped = $conn->real_escape_string($category);
    $storage_base_sql .= " AND p.Category = '$category_escaped'";
}
$storage_base_sql .= " GROUP BY s.StorageCapacity ORDER BY s.StorageCapacity";
$storage_result = $conn->query($storage_base_sql);
$storages_list = [
    '512GB' => 0,
    '1TB' => 0,
    '2TB' => 0,
    '未分類' => 0
];
while($stor_row = $storage_result->fetch_assoc()) {
    $capacity = $stor_row['StorageCapacity'];
    if ($capacity >= 512 && $capacity < 1024) {
        $storages_list['512GB'] += $stor_row['count'];
    } elseif ($capacity >= 1024 && $capacity < 2048) {
        $storages_list['1TB'] += $stor_row['count'];
    } elseif ($capacity >= 2048) {
        $storages_list['2TB'] += $stor_row['count'];
    } else {
        $storages_list['未分類'] += $stor_row['count'];
    }
}

// 获取处理器列表和数量
$cpu_base_sql = "SELECT s.CPU, COUNT(*) as count 
                 FROM SKU s 
                 JOIN Product p ON s.ProductID = p.ProductID 
                 WHERE 1=1";
if ($brand) {
    $brand_escaped = $conn->real_escape_string($brand);
    $cpu_base_sql .= " AND p.BrandName = '$brand_escaped'";
}
if ($category) {
    $category_escaped = $conn->real_escape_string($category);
    $cpu_base_sql .= " AND p.Category = '$category_escaped'";
}
$cpu_base_sql .= " GROUP BY s.CPU ORDER BY s.CPU";
$cpu_result = $conn->query($cpu_base_sql);
$cpus_list = [];
while($cpu_row = $cpu_result->fetch_assoc()) {
    $cpu_name = $cpu_row['CPU'];
    // 提取主要品牌（Intel, AMD等）
    if (stripos($cpu_name, 'Intel') !== false) {
        $key = 'Intel';
    } elseif (stripos($cpu_name, 'AMD') !== false) {
        $key = 'AMD';
    } else {
        $key = $cpu_name;
    }
    if (!isset($cpus_list[$key])) {
        $cpus_list[$key] = 0;
    }
    $cpus_list[$key] += $cpu_row['count'];
}

// 获取内存列表和数量
$ram_base_sql = "SELECT s.RAM, COUNT(*) as count 
                 FROM SKU s 
                 JOIN Product p ON s.ProductID = p.ProductID 
                 WHERE 1=1";
if ($brand) {
    $brand_escaped = $conn->real_escape_string($brand);
    $ram_base_sql .= " AND p.BrandName = '$brand_escaped'";
}
if ($category) {
    $category_escaped = $conn->real_escape_string($category);
    $ram_base_sql .= " AND p.Category = '$category_escaped'";
}
$ram_base_sql .= " GROUP BY s.RAM ORDER BY s.RAM";
$ram_result = $conn->query($ram_base_sql);
$rams_list = [];
while($ram_row = $ram_result->fetch_assoc()) {
    $rams_list[$ram_row['RAM']] = $ram_row['count'];
}

// 获取显卡列表和数量
$gpu_base_sql = "SELECT s.GPU, COUNT(*) as count 
                 FROM SKU s 
                 JOIN Product p ON s.ProductID = p.ProductID 
                 WHERE 1=1";
if ($brand) {
    $brand_escaped = $conn->real_escape_string($brand);
    $gpu_base_sql .= " AND p.BrandName = '$brand_escaped'";
}
if ($category) {
    $category_escaped = $conn->real_escape_string($category);
    $gpu_base_sql .= " AND p.Category = '$category_escaped'";
}
$gpu_base_sql .= " GROUP BY s.GPU ORDER BY s.GPU";
$gpu_result = $conn->query($gpu_base_sql);
$gpus_list = [];
while($gpu_row = $gpu_result->fetch_assoc()) {
    $gpu_name = $gpu_row['GPU'];
    // 提取主要品牌（NVIDIA, AMD, Intel等）
    if (stripos($gpu_name, 'NVIDIA') !== false || stripos($gpu_name, 'GeForce') !== false || stripos($gpu_name, 'RTX') !== false || stripos($gpu_name, 'GTX') !== false) {
        $key = 'NVIDIA';
    } elseif (stripos($gpu_name, 'AMD') !== false || stripos($gpu_name, 'Radeon') !== false) {
        $key = 'AMD Radeon';
    } elseif (stripos($gpu_name, 'Intel') !== false) {
        $key = 'Intel';
    } else {
        $key = $gpu_name ?: '內顯';
    }
    if (!isset($gpus_list[$key])) {
        $gpus_list[$key] = 0;
    }
    $gpus_list[$key] += $gpu_row['count'];
}

// 获取显存列表和数量
$vram_base_sql = "SELECT s.VRAM, COUNT(*) as count 
                  FROM SKU s 
                  JOIN Product p ON s.ProductID = p.ProductID 
                  WHERE 1=1";
if ($brand) {
    $brand_escaped = $conn->real_escape_string($brand);
    $vram_base_sql .= " AND p.BrandName = '$brand_escaped'";
}
if ($category) {
    $category_escaped = $conn->real_escape_string($category);
    $vram_base_sql .= " AND p.Category = '$category_escaped'";
}
$vram_base_sql .= " GROUP BY s.VRAM ORDER BY s.VRAM";
$vram_result = $conn->query($vram_base_sql);
$vrams_list = ['N/A' => 0];
while($vram_row = $vram_result->fetch_assoc()) {
    $vram_val = $vram_row['VRAM'];
    if ($vram_val == 0 || $vram_val === null) {
        $vrams_list['N/A'] += $vram_row['count'];
    } else {
        $vrams_list[$vram_val . 'GB'] = $vram_row['count'];
    }
}

// 获取重量列表和数量
$weight_base_sql = "SELECT s.Weight, COUNT(*) as count 
                    FROM SKU s 
                    JOIN Product p ON s.ProductID = p.ProductID 
                    WHERE 1=1";
if ($brand) {
    $brand_escaped = $conn->real_escape_string($brand);
    $weight_base_sql .= " AND p.BrandName = '$brand_escaped'";
}
if ($category) {
    $category_escaped = $conn->real_escape_string($category);
    $weight_base_sql .= " AND p.Category = '$category_escaped'";
}
$weight_base_sql .= " GROUP BY s.Weight ORDER BY s.Weight";
$weight_result = $conn->query($weight_base_sql);
$weights_list = [
    '<1.5' => 0,
    '1.5-2.0' => 0,
    '2.0-2.5' => 0,
    '>2.5' => 0
];
while($weight_row = $weight_result->fetch_assoc()) {
    $weight_val = floatval($weight_row['Weight']);
    if ($weight_val < 1.5) {
        $weights_list['<1.5'] += $weight_row['count'];
    } elseif ($weight_val < 2.0) {
        $weights_list['1.5-2.0'] += $weight_row['count'];
    } elseif ($weight_val < 2.5) {
        $weights_list['2.0-2.5'] += $weight_row['count'];
    } else {
        $weights_list['>2.5'] += $weight_row['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LaptopMart 筆電商城</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Microsoft JhengHei', 'Segoe UI', sans-serif; 
            background-color: #f5f5f5; 
        }
        
        /* 顶部导航栏 */
        .top-nav {
            background: #1e3a8a;
            color: white;
            padding: 8px 0;
            font-size: 0.9rem;
        }
        
        .main-header {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px 0;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: #1e3a8a;
            text-decoration: none;
        }
        
        .search-container {
            position: relative;
        }
        
        .search-box {
            width: 100%;
            padding: 10px 50px 10px 15px;
            border: 2px solid #1e3a8a;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: #1e3a8a;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .search-btn:hover {
            background: #1e40af;
        }
        
        .header-links a {
            color: #333;
            text-decoration: none;
            margin-left: 20px;
            font-size: 0.9rem;
        }
        
        .header-links a:hover {
            color: #e60012;
        }
        
        .cart-icon {
            position: relative;
            font-size: 1.5rem;
            color: #333;
        }
        
        /* 面包屑导航 */
        .breadcrumb-nav {
            background: white;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
            font-size: 0.9rem;
        }
        
        .breadcrumb-nav a {
            color: #666;
            text-decoration: none;
        }
        
        .breadcrumb-nav a:hover {
            color: #1e3a8a;
        }
        
        /* 左侧边栏 */
        .sidebar {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .sidebar-title {
            font-size: 1.1rem;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #1e3a8a;
        }
        
        .category-item {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .category-item:last-child {
            border-bottom: none;
        }
        
        .category-item a {
            color: #333;
            text-decoration: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .category-item a:hover {
            color: #1e3a8a;
        }
        
        .category-count {
            color: #999;
            font-size: 0.9rem;
        }
        
        /* 品牌筛选样式 */
        .brand-filter {
            margin-top: 20px;
        }
        
        .brand-option {
            padding: 6px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .brand-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #1e3a8a;
        }
        
        .brand-option label {
            cursor: pointer;
            margin: 0;
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .brand-option:hover {
            background: #f8f9fa;
            padding-left: 5px;
            margin-left: -5px;
            border-radius: 4px;
        }
        
        /* 筛选区域 */
        .filter-section {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .filter-row:last-child {
            border-bottom: none;
        }
        
        .filter-label {
            font-weight: bold;
            min-width: 80px;
            color: #333;
        }
        
        .filter-options {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            flex: 1;
        }
        
        .filter-checkbox {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .filter-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .filter-checkbox label {
            cursor: pointer;
            margin: 0;
        }
        
        /* 排序区域 */
        .sort-section {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .sort-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .sort-btn {
            padding: 8px 15px;
            border: 1px solid #ddd;
            background: white;
            cursor: pointer;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        
        .sort-btn:hover, .sort-btn.active {
            background: #1e3a8a;
            color: white;
            border-color: #1e3a8a;
        }
        
        .price-range {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .price-input {
            width: 100px;
            padding: 5px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        /* 产品卡片 */
        .product-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .product-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
            display: block;
            border: 1px solid #e0e0e0;
        }
        
        .product-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transform: translateY(-3px);
        }
        
        .product-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #e60012;
            color: white;
            padding: 4px 8px;
            font-size: 0.75rem;
            border-radius: 4px;
            z-index: 1;
        }
        
        .product-image-wrapper {
            position: relative;
            height: 200px;
            overflow: hidden;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 10px;
        }
        
        .product-info {
            padding: 15px;
        }
        
        .product-brand {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .product-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            line-height: 1.4;
            height: 2.8em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .product-spec {
            font-size: 0.8rem;
            color: #999;
            margin-bottom: 10px;
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #1e3a8a;
        }
        
        .product-badge {
            background: #1e3a8a;
        }
        
        .price-range .btn-danger {
            background: #1e3a8a;
            border-color: #1e3a8a;
        }
        
        .price-range .btn-danger:hover {
            background: #1e40af;
            border-color: #1e40af;
        }
        
        .product-rating {
            font-size: 0.85rem;
            color: #ffa500;
            margin-bottom: 8px;
        }
        
        mark {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body>

<!-- 顶部导航栏 -->
<div class="top-nav">
  <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <a href="#" style="color: white; text-decoration: none; margin-right: 20px;">綜合服務</a>
            </div>
            <div class="d-flex gap-3">
                <a href="cart.php" style="color: white; text-decoration: none;">購物車</a>
                <?php if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true): ?>
                    <a href="customer_account.php" style="color: white; text-decoration: none;">我的帳號</a>
                    <a href="customer_logout.php" style="color: white; text-decoration: none;">登出</a>
                <?php else: ?>
                    <a href="customer_login.php" style="color: white; text-decoration: none;">登入</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </div>

<!-- 主头部 -->
<div class="main-header">
<div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <a href="index.php" class="logo">
                <i class="bi bi-laptop"></i> LaptopMart
            </a>
            
            <form class="search-container" action="index.php" method="GET" style="flex: 1; max-width: 600px; margin: 0 30px;">
                <?php if ($brand): ?>
                    <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand); ?>">
                <?php endif; ?>
                <?php if ($category): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <?php endif; ?>
                <input type="search" name="search" class="search-box" 
                       placeholder="搜尋筆電型號、品牌、規格..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="search-btn">
                    <i class="bi bi-search"></i>
                </button>
            </form>
            
            <div class="header-links d-flex align-items-center">
                <?php
                $cart_count = 0;
                if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
                    $cart_count = array_sum($_SESSION['cart']);
                }
                ?>
                <a href="cart.php" class="cart-icon position-relative">
                    <i class="bi bi-cart3"></i>
                    <?php if ($cart_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.7rem;"><?php echo $cart_count; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 面包屑导航 -->
<div class="breadcrumb-nav">
    <div class="container">
        <a href="index.php">首頁</a> > 
        <a href="index.php">全部分類</a>
        <?php if ($category): ?>
            > <span><?php echo htmlspecialchars($category); ?></span>
        <?php endif; ?>
    </div>
</div>

<div class="container" style="margin-top: 20px;">
    <div class="row">
        <!-- 左侧边栏 -->
        <div class="col-lg-3">
            <div class="sidebar">
                <div class="sidebar-title">品牌選擇</div>
                <form method="GET" action="index.php" id="brandForm">
                    <?php if ($search): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <?php endif; ?>
                    <?php if ($category): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <?php endif; ?>
                    <?php if ($min_price): ?>
                        <input type="hidden" name="min_price" value="<?php echo $min_price; ?>">
                    <?php endif; ?>
                    <?php if ($max_price): ?>
                        <input type="hidden" name="max_price" value="<?php echo $max_price; ?>">
                    <?php endif; ?>
                    <?php foreach ($screen_sizes as $sz): ?>
                        <input type="hidden" name="screen_size[]" value="<?php echo htmlspecialchars($sz); ?>">
                    <?php endforeach; ?>
                    <?php foreach ($storages as $st): ?>
                        <input type="hidden" name="storage[]" value="<?php echo htmlspecialchars($st); ?>">
                    <?php endforeach; ?>
                    <?php if ($sort): ?>
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <?php endif; ?>
                    
                    <div class="category-item">
                        <label style="cursor: pointer; width: 100%; display: flex; justify-content: space-between; align-items: center;">
                            <input type="radio" name="brand" value="" 
                                   <?php echo $brand == '' ? 'checked' : ''; ?>
                                   onchange="document.getElementById('brandForm').submit();"
                                   style="margin-right: 8px;">
                            <span style="flex: 1;">全部品牌</span>
                            <span class="category-count">(<?php echo array_sum($brands_list); ?>)</span>
                        </label>
                    </div>
                    <?php foreach ($brands_list as $b_name => $b_count): ?>
                        <div class="category-item">
                            <label style="cursor: pointer; width: 100%; display: flex; justify-content: space-between; align-items: center;">
                                <input type="radio" name="brand" value="<?php echo htmlspecialchars($b_name); ?>" 
                                       <?php echo $brand == $b_name ? 'checked' : ''; ?>
                                       onchange="document.getElementById('brandForm').submit();"
                                       style="margin-right: 8px;">
                                <span style="flex: 1;"><?php echo htmlspecialchars($b_name); ?></span>
                                <span class="category-count">(<?php echo $b_count; ?>)</span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </form>
            </div>
            
            <!-- 分类筛选移到下面 -->
            <div class="sidebar" style="margin-top: 20px;">
                <div class="sidebar-title">分類</div>
                <div class="category-item">
                    <a href="<?php 
                        $all_link = 'index.php';
                        if ($search) $all_link .= '?search=' . urlencode($search);
                        if ($brand) $all_link .= ($search ? '&' : '?') . 'brand=' . urlencode($brand);
                        echo $all_link;
                    ?>">
                        <span>全部</span>
                        <span class="category-count">(<?php echo array_sum($categories_list); ?>)</span>
                    </a>
                </div>
                <?php foreach ($categories_list as $cat_name => $cat_count): ?>
                    <div class="category-item">
                        <a href="<?php 
                            $cat_link = 'index.php?category=' . urlencode($cat_name);
                            if ($search) $cat_link .= '&search=' . urlencode($search);
                            if ($brand) $cat_link .= '&brand=' . urlencode($brand);
                            echo $cat_link;
                        ?>">
                            <span><?php echo htmlspecialchars($cat_name); ?></span>
                            <span class="category-count">(<?php echo $cat_count; ?>)</span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            
        </div>

        <!-- 主内容区 -->
        <div class="col-lg-9">
            <!-- 详细规格筛选（下拉式选单） -->
            <div class="card mb-3">
                <div class="card-header bg-white">
                    <button class="btn btn-link text-decoration-none w-100 text-start p-0" type="button" data-bs-toggle="collapse" data-bs-target="#specFilterCollapse" aria-expanded="false" aria-controls="specFilterCollapse">
                        <i class="bi bi-funnel me-2"></i><strong>詳細規格篩選</strong>
                        <i class="bi bi-chevron-down float-end"></i>
                    </button>
                </div>
                <div class="collapse" id="specFilterCollapse">
                    <div class="card-body">
                        <form method="GET" action="index.php" id="specFilterForm">
                            <?php if ($search): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <?php endif; ?>
                            <?php if ($brand): ?>
                                <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand); ?>">
                            <?php endif; ?>
                            <?php if ($category): ?>
                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                            <?php endif; ?>
                            <!-- 不保留尺寸/容量的隱藏欄位，避免無法取消勾選 -->
                            
                            <div class="row g-3">
                                <!-- 处理器筛选 -->
                                <?php if (!empty($cpus_list)): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <label class="form-label fw-bold">處理器</label>
                                        <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($cpus_list as $cpu_name => $cpu_count): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="cpu[]" value="<?php echo htmlspecialchars($cpu_name); ?>" 
                                                           id="cpu_<?php echo md5($cpu_name); ?>"
                                                           <?php echo in_array($cpu_name, $cpus) ? 'checked' : ''; ?>
                                                           onchange="document.getElementById('specFilterForm').submit();">
                                                    <label class="form-check-label" for="cpu_<?php echo md5($cpu_name); ?>">
                                                        <?php echo htmlspecialchars($cpu_name); ?> <small class="text-muted">(<?php echo $cpu_count; ?>)</small>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- 内存筛选 -->
                                <?php if (!empty($rams_list)): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <label class="form-label fw-bold">記憶體</label>
                                        <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($rams_list as $ram_val => $ram_count): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="ram[]" value="<?php echo $ram_val; ?>" 
                                                           id="ram_<?php echo $ram_val; ?>"
                                                           <?php echo in_array($ram_val, $rams) ? 'checked' : ''; ?>
                                                           onchange="document.getElementById('specFilterForm').submit();">
                                                    <label class="form-check-label" for="ram_<?php echo $ram_val; ?>">
                                                        <?php echo $ram_val; ?> GB <small class="text-muted">(<?php echo $ram_count; ?>)</small>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- 显卡筛选 -->
                                <?php if (!empty($gpus_list)): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <label class="form-label fw-bold">顯示卡</label>
                                        <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($gpus_list as $gpu_name => $gpu_count): ?>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="gpu[]" value="<?php echo htmlspecialchars($gpu_name); ?>" 
                                                           id="gpu_<?php echo md5($gpu_name); ?>"
                                                           <?php echo in_array($gpu_name, $gpus) ? 'checked' : ''; ?>
                                                           onchange="document.getElementById('specFilterForm').submit();">
                                                    <label class="form-check-label" for="gpu_<?php echo md5($gpu_name); ?>">
                                                        <?php echo htmlspecialchars($gpu_name); ?> <small class="text-muted">(<?php echo $gpu_count; ?>)</small>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- 显存筛选 -->
                                <?php if (!empty($vrams_list)): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <label class="form-label fw-bold">顯存 (VRAM)</label>
                                        <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($vrams_list as $vram_name => $vram_count): ?>
                                                <?php if ($vram_count > 0): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="vram[]" value="<?php echo $vram_name == 'N/A' ? 'N/A' : str_replace('GB', '', $vram_name); ?>" 
                                                               id="vram_<?php echo md5($vram_name); ?>"
                                                               <?php echo in_array(($vram_name == 'N/A' ? 'N/A' : str_replace('GB', '', $vram_name)), $vrams) ? 'checked' : ''; ?>
                                                               onchange="document.getElementById('specFilterForm').submit();">
                                                        <label class="form-check-label" for="vram_<?php echo md5($vram_name); ?>">
                                                            <?php echo $vram_name; ?> <small class="text-muted">(<?php echo $vram_count; ?>)</small>
                                                        </label>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- 重量筛选 -->
                                <?php if (!empty($weights_list)): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <label class="form-label fw-bold">重量</label>
                                        <div class="border rounded p-2" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach ($weights_list as $weight_name => $weight_count): ?>
                                                <?php if ($weight_count > 0): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="weight[]" value="<?php echo htmlspecialchars($weight_name); ?>" 
                                                               id="weight_<?php echo md5($weight_name); ?>"
                                                               <?php echo in_array($weight_name, $weights) ? 'checked' : ''; ?>
                                                               onchange="document.getElementById('specFilterForm').submit();">
                                                        <label class="form-check-label" for="weight_<?php echo md5($weight_name); ?>">
                                                            <?php echo htmlspecialchars($weight_name); ?> kg <small class="text-muted">(<?php echo $weight_count; ?>)</small>
                                                        </label>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php foreach ($cpus as $c): ?>
                                <input type="hidden" name="cpu[]" value="<?php echo htmlspecialchars($c); ?>">
                            <?php endforeach; ?>
                            <?php foreach ($rams as $r): ?>
                                <input type="hidden" name="ram[]" value="<?php echo htmlspecialchars($r); ?>">
                            <?php endforeach; ?>
                            <?php foreach ($gpus as $g): ?>
                                <input type="hidden" name="gpu[]" value="<?php echo htmlspecialchars($g); ?>">
                            <?php endforeach; ?>
                            <?php foreach ($vrams as $v): ?>
                                <input type="hidden" name="vram[]" value="<?php echo htmlspecialchars($v); ?>">
                            <?php endforeach; ?>
                            <?php foreach ($weights as $w): ?>
                                <input type="hidden" name="weight[]" value="<?php echo htmlspecialchars($w); ?>">
                            <?php endforeach; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- 筛选区域 -->
            <div class="filter-section">
                <form method="GET" action="index.php" id="filterForm">
                    <?php if ($search): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                    <?php endif; ?>
                    <?php if ($brand): ?>
                        <input type="hidden" name="brand" value="<?php echo htmlspecialchars($brand); ?>">
                    <?php endif; ?>
                    <?php if ($category): ?>
                        <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                    <?php endif; ?>
                    <?php if ($sort): ?>
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <?php endif; ?>
                    <!-- 不保留尺寸/容量的隱藏欄位，避免無法取消勾選 -->
                    
                    <div class="filter-row">
                        <div class="filter-label">價格</div>
                        <div class="filter-options">
                            <input type="number" name="min_price" class="price-input" 
                                   placeholder="最低價" value="<?php echo $min_price > 0 ? $min_price : ''; ?>">
                            <span>-</span>
                            <input type="number" name="max_price" class="price-input" 
                                   placeholder="最高價" value="<?php echo $max_price > 0 ? $max_price : ''; ?>">
                            <button type="submit" class="btn btn-sm" style="background: #1e3a8a; color: white; border: none;">確定</button>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-label">尺寸</div>
                        <div class="filter-options">
                            <?php 
                            $size_options = ['13' => '13吋', '14' => '14吋', '15' => '15吋', '17' => '17吋'];
                            foreach ($size_options as $size_val => $size_label): 
                                $is_checked = in_array($size_val, $screen_sizes);
                                $count = $sizes_list[$size_label] ?? 0;
                            ?>
                                <div class="filter-checkbox">
                                    <input type="checkbox" name="screen_size[]" value="<?php echo $size_val; ?>" 
                                           id="size_<?php echo $size_val; ?>"
                                           <?php echo $is_checked ? 'checked' : ''; ?>
                                           onchange="document.getElementById('filterForm').submit();">
                                    <label for="size_<?php echo $size_val; ?>">
                                        <?php echo $size_label; ?>(<?php echo $count; ?>)
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <?php if (isset($sizes_list['未分類']) && $sizes_list['未分類'] > 0): ?>
                                <div class="filter-checkbox">
                                    <input type="checkbox" name="screen_size[]" value="未分類" id="size_other"
                                           <?php echo in_array('未分類', $screen_sizes) ? 'checked' : ''; ?>
                                           onchange="document.getElementById('filterForm').submit();">
                                    <label for="size_other">未分類(<?php echo $sizes_list['未分類']; ?>)</label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="filter-row">
                        <div class="filter-label">容量</div>
                        <div class="filter-options">
                            <?php 
                            $storage_options = ['512GB' => '512GB SSD', '1TB' => '1TB SSD', '2TB' => '2TB SSD'];
                            foreach ($storage_options as $stor_val => $stor_label): 
                                $is_checked = in_array($stor_val, $storages);
                                $count = $storages_list[$stor_val] ?? 0;
                            ?>
                                <div class="filter-checkbox">
                                    <input type="checkbox" name="storage[]" value="<?php echo $stor_val; ?>" 
                                           id="storage_<?php echo $stor_val; ?>"
                                           <?php echo $is_checked ? 'checked' : ''; ?>
                                           onchange="document.getElementById('filterForm').submit();">
                                    <label for="storage_<?php echo $stor_val; ?>">
                                        <?php echo $stor_label; ?>(<?php echo $count; ?>)
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <?php if (isset($storages_list['未分類']) && $storages_list['未分類'] > 0): ?>
                                <div class="filter-checkbox">
                                    <input type="checkbox" name="storage[]" value="未分類" id="storage_other"
                                           <?php echo in_array('未分類', $storages) ? 'checked' : ''; ?>
                                           onchange="document.getElementById('filterForm').submit();">
                                    <label for="storage_other">未分類(<?php echo $storages_list['未分類']; ?>)</label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 排序区域 -->
            <div class="sort-section">
                <div class="sort-buttons">
                    <?php
                    $sort_params = $_GET;
                    unset($sort_params['sort']);
                    $base_url = 'index.php?' . http_build_query($sort_params);
                    ?>
                    <a href="<?php echo $base_url . '&sort=recommend'; ?>" 
                       class="sort-btn <?php echo $sort == 'recommend' ? 'active' : ''; ?>">推薦排序</a>
                    <a href="<?php echo $base_url . '&sort=popular'; ?>" 
                       class="sort-btn <?php echo $sort == 'popular' ? 'active' : ''; ?>">熱銷度</a>
                    <a href="<?php echo $base_url . '&sort=new'; ?>" 
                       class="sort-btn <?php echo $sort == 'new' ? 'active' : ''; ?>">新上架</a>
                    <a href="<?php echo $base_url . '&sort=price_asc'; ?>" 
                       class="sort-btn <?php echo $sort == 'price_asc' ? 'active' : ''; ?>">
                        <i class="bi bi-arrow-up"></i> 價格
                    </a>
                    <a href="<?php echo $base_url . '&sort=price_desc'; ?>" 
                       class="sort-btn <?php echo $sort == 'price_desc' ? 'active' : ''; ?>">
                        <i class="bi bi-arrow-down"></i> 價格
                    </a>
                </div>
                <div>
                    <span class="text-muted">共 <?php echo $total_products; ?> 台筆電</span>
                    <?php if ($total_pages > 1): ?>
                        <span class="text-muted ms-2">(第 <?php echo $page; ?> / <?php echo $total_pages; ?> 頁)</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 产品列表 -->
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="product-grid">
                    <?php while($row = $result->fetch_assoc()): 
                        $img_src = getProductImage(
                            $row["BrandName"], 
                            $row["SKU_ID"], 
                            $row["ProductName"], 
                            $row["Category"],
                            400
                        );
                    ?>
                        <a href="product_detail.php?id=<?php echo $row['ProductID']; ?>&sku_id=<?php echo htmlspecialchars($row['SKU_ID']); ?>" class="product-card">
                            <?php if ($row['Stock'] <= 5 && $row['Stock'] > 0): ?>
                                <div class="product-badge">庫存僅剩 <?php echo $row['Stock']; ?> 台</div>
                            <?php endif; ?>
                            <div class="product-image-wrapper">
                                <img src="<?php echo $img_src; ?>" class="product-image" alt="<?php echo htmlspecialchars($row["ProductName"]); ?>">
                            </div>
                            <div class="product-info">
                                <div class="product-brand"><?php echo htmlspecialchars($row["BrandName"]); ?></div>
                                <div class="product-name">
                <?php
                                    if ($search) {
                                        $highlighted = preg_replace('/(' . preg_quote($search, '/') . ')/i', '<mark>$1</mark>', htmlspecialchars($row["ProductName"]));
                                        echo $highlighted;
                                    } else {
                                        echo htmlspecialchars($row["ProductName"]);
                                    }
                                    ?>
                                </div>
                                <div class="product-spec">
                                    <?php echo htmlspecialchars($row['CPU']); ?> / <?php echo $row['RAM']; ?>GB / <?php echo $row['StorageCapacity']; ?>GB
                                    </div>
                                <div class="product-rating">
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-fill"></i>
                                        <i class="bi bi-star-half"></i>
                                    <span style="color: #999; font-size: 0.8rem;">(<?php echo rand(10, 500); ?>)</span>
                                </div>
                                <div class="product-price">NT$ <?php echo number_format($row["Price"]); ?></div>
                                </div>
                            </a>
                    <?php endwhile; ?>
                        </div>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="bi bi-search me-2"></i>
                    <?php if ($search): ?>
                        找不到符合「<strong><?php echo htmlspecialchars($search); ?></strong>」的商品。
                    <?php else: ?>
                        目前沒有商品。
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- 分页导航 -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="商品分頁">
                    <ul class="pagination justify-content-center mt-4">
                        <?php
                        // 构建分页URL（保留所有筛选条件）
                        $page_params = [];
                        if ($search) $page_params['search'] = $search;
                        if ($brand) $page_params['brand'] = $brand;
                        if ($category) $page_params['category'] = $category;
                        if ($sort) $page_params['sort'] = $sort;
                        if ($min_price > 0) $page_params['min_price'] = $min_price;
                        if ($max_price > 0) $page_params['max_price'] = $max_price;
                        foreach ($screen_sizes as $sz) {
                            $page_params['screen_size[]'][] = $sz;
                        }
                        foreach ($storages as $st) {
                            $page_params['storage[]'][] = $st;
                        }
                        foreach ($cpus as $c) {
                            $page_params['cpu[]'][] = $c;
                        }
                        foreach ($rams as $r) {
                            $page_params['ram[]'][] = $r;
                        }
                        foreach ($gpus as $g) {
                            $page_params['gpu[]'][] = $g;
                        }
                        foreach ($vrams as $v) {
                            $page_params['vram[]'][] = $v;
                        }
                        foreach ($weights as $w) {
                            $page_params['weight[]'][] = $w;
                        }
                        $base_url = 'index.php';
                        if (!empty($page_params)) {
                            // 手动构建URL以正确处理数组参数
                            $query_parts = [];
                            foreach ($page_params as $key => $value) {
                                if (is_array($value)) {
                                    foreach ($value as $v) {
                                        $query_parts[] = urlencode($key) . '=' . urlencode($v);
                                    }
                                } else {
                                    $query_parts[] = urlencode($key) . '=' . urlencode($value);
                                }
                            }
                            $base_url .= '?' . implode('&', $query_parts);
                        }
                        
                        // 上一页
                        if ($page > 1):
                            $prev_url = $base_url . '&page=' . ($page - 1);
                        ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $prev_url; ?>">
                                    <i class="bi bi-chevron-left"></i> 上一頁
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link"><i class="bi bi-chevron-left"></i> 上一頁</span>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        // 计算显示的页码范围
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        // 显示第一页
                        if ($start_page > 1):
                        ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $base_url . '&page=1'; ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php
                        // 显示当前页附近的页码
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $base_url . '&page=' . $i; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php
                        // 显示最后一页
                        if ($end_page < $total_pages):
                        ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $base_url . '&page=' . $total_pages; ?>">
                                    <?php echo $total_pages; ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        // 下一页
                        if ($page < $total_pages):
                            $next_url = $base_url . '&page=' . ($page + 1);
                        ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $next_url; ?>">
                                    下一頁 <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link">下一頁 <i class="bi bi-chevron-right"></i></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .pagination .page-link {
        color: #1e3a8a;
        border-color: #dee2e6;
    }
    .pagination .page-item.active .page-link {
        background-color: #1e3a8a;
        border-color: #1e3a8a;
    }
    .pagination .page-link:hover {
        background-color: #e9ecef;
        color: #1e3a8a;
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
