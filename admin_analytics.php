<?php
require_once 'admin_auth.php';

// 資料庫連線
require_once 'db_connect.php';
if ($conn->connect_error) die("連線失敗");
$conn->set_charset("utf8mb4");

// 查詢品牌營收排行
$sql_brands = "SELECT p.BrandName, SUM(oi.Quantity * s.Price) as brand_revenue
               FROM OrderItem oi
               JOIN SKU s ON oi.SKU_ID = s.SKU_ID
               JOIN Product p ON s.ProductID = p.ProductID
               JOIN `Order` o ON oi.Order_ID = o.Order_ID
               WHERE o.Status = 'Completed'
               GROUP BY p.BrandName
               ORDER BY brand_revenue DESC";
$result_brands = $conn->query($sql_brands);
$brands_data = [];
while ($row = $result_brands->fetch_assoc()) {
    $brands_data[] = $row;
}

// 計算最大營收（用於百分比計算）
$max_revenue = count($brands_data) > 0 ? $brands_data[0]['brand_revenue'] : 1;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>銷售分析報表 | LaptopMart Admin</title>
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
        .analytics-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        .data-source-card {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .brand-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .brand-item:last-child {
            border-bottom: none;
        }
        .brand-name {
            min-width: 100px;
            font-weight: 600;
            color: #495057;
        }
        .brand-bar-container {
            flex: 1;
            height: 30px;
            background: #e9ecef;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        .brand-bar {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6 0%, #1e3a8a 100%);
            border-radius: 15px;
            transition: width 0.8s ease;
            display: flex;
            align-items: center;
            padding: 0 15px;
        }
        .brand-revenue {
            min-width: 120px;
            text-align: right;
            font-weight: 600;
            color: #1e3a8a;
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
                <a href="admin_orders.php" class="nav-link">
                    <i class="bi bi-truck"></i>
                    <span>訂單與出貨</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="admin_analytics.php" class="nav-link active">
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
            <h2 class="fw-bold mb-0">銷售數據分析 (Analytics)</h2>
            <div class="status-indicator">
                <i class="bi bi-clock"></i>
                <span class="status-dot"></span>
                <span>System Online</span>
            </div>
        </div>

        <!-- Data Source Card -->
        <div class="data-source-card">
            <h4 class="fw-bold mb-2">銷售分析中心</h4>
            <p class="mb-0 opacity-75">數據來源：整合商品(Product)、型號(Model)與訂單品項(OrderItems) 資料表</p>
        </div>

        <!-- Revenue by Brand Chart -->
        <div class="analytics-card">
            <h5 class="fw-bold mb-4">
                <i class="bi bi-bar-chart me-2"></i>品牌營收排行 (Revenue by Brand)
            </h5>
            
            <?php if (count($brands_data) > 0): ?>
                <?php foreach ($brands_data as $brand): 
                    $percentage = ($brand['brand_revenue'] / $max_revenue) * 100;
                ?>
                    <div class="brand-item">
                        <div class="brand-name"><?php echo htmlspecialchars($brand['BrandName']); ?></div>
                        <div class="brand-bar-container">
                            <div class="brand-bar" style="width: <?php echo $percentage; ?>%;">
                                <span style="color: white; font-size: 0.85rem; font-weight: 600;">
                                    <?php echo number_format($percentage, 1); ?>%
                                </span>
                            </div>
                        </div>
                        <div class="brand-revenue">NT$ <?php echo number_format($brand['brand_revenue']); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                    <p class="mt-2 mb-0">尚無銷售數據</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 動畫效果：頁面載入時讓進度條動畫顯示
        window.addEventListener('load', function() {
            const bars = document.querySelectorAll('.brand-bar');
            bars.forEach((bar, index) => {
                setTimeout(() => {
                    bar.style.width = bar.style.width;
                }, index * 100);
            });
        });
    </script>
</body>
</html>

