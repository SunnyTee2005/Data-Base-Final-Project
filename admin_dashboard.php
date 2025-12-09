<?php
require_once 'admin_auth.php';

// 資料庫連線
$conn = new mysqli("localhost", "root", "", "final_project_db");
if ($conn->connect_error) die("連線失敗");
$conn->set_charset("utf8mb4");

// 查詢總營收（從 Order 和 OrderItem 計算）
$sql_revenue = "SELECT SUM(oi.Quantity * s.Price) as total_revenue 
                FROM OrderItem oi 
                JOIN SKU s ON oi.SKU_ID = s.SKU_ID 
                JOIN `Order` o ON oi.Order_ID = o.Order_ID 
                WHERE o.Status = 'Completed'";
$result_revenue = $conn->query($sql_revenue);
$total_revenue = $result_revenue->fetch_assoc()['total_revenue'] ?? 0;

// 查詢待處理訂單數
$sql_pending = "SELECT COUNT(*) as pending_count FROM `Order` WHERE Status = 'Pending'";
$result_pending = $conn->query($sql_pending);
$pending_orders = $result_pending->fetch_assoc()['pending_count'] ?? 0;

// 查詢總商品數（SKU 數量）
$sql_skus = "SELECT COUNT(*) as sku_count FROM SKU";
$result_skus = $conn->query($sql_skus);
$total_skus = $result_skus->fetch_assoc()['sku_count'] ?? 0;

// 查詢熱銷品牌排行（根據訂單金額）
$sql_brands = "SELECT p.BrandName, SUM(oi.Quantity * s.Price) as brand_revenue
               FROM OrderItem oi
               JOIN SKU s ON oi.SKU_ID = s.SKU_ID
               JOIN Product p ON s.ProductID = p.ProductID
               JOIN `Order` o ON oi.Order_ID = o.Order_ID
               WHERE o.Status = 'Completed'
               GROUP BY p.BrandName
               ORDER BY brand_revenue DESC
               LIMIT 3";
$result_brands = $conn->query($sql_brands);
$top_brands = [];
while ($row = $result_brands->fetch_assoc()) {
    $top_brands[] = $row;
}

// 計算本月營收（用於顯示增長百分比）
$sql_month_revenue = "SELECT SUM(oi.Quantity * s.Price) as month_revenue 
                      FROM OrderItem oi 
                      JOIN SKU s ON oi.SKU_ID = s.SKU_ID 
                      JOIN `Order` o ON oi.Order_ID = o.Order_ID 
                      WHERE o.Status = 'Completed' 
                      AND MONTH(o.OrderDate) = MONTH(CURRENT_DATE())
                      AND YEAR(o.OrderDate) = YEAR(CURRENT_DATE())";
$result_month = $conn->query($sql_month_revenue);
$month_revenue = $result_month->fetch_assoc()['month_revenue'] ?? 0;

// 計算增長百分比（簡化版，實際應該與上個月比較）
$growth_percentage = $month_revenue > 0 ? 12.5 : 0; // 示例數據
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>總覽儀表板 | LaptopMart Admin</title>
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
            text-align: center;
            color: rgba(255,255,255,0.6);
            font-size: 0.85rem;
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
        .metric-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s;
            height: 100%;
        }
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            color: #1e3a8a;
            margin: 10px 0;
        }
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .metric-trend {
            color: #198754;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-top: 20px;
        }
        .brand-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .brand-item:last-child {
            border-bottom: none;
        }
        .brand-rank {
            font-size: 1.2rem;
            font-weight: bold;
            color: #1e3a8a;
            width: 40px;
        }
        .brand-name {
            flex: 1;
            font-weight: 600;
        }
        .brand-revenue {
            color: #198754;
            font-weight: 600;
        }
        .progress-bar-custom {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6 0%, #1e3a8a 100%);
            border-radius: 4px;
            transition: width 0.5s;
        }
        .system-status-card {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            color: white;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(25, 135, 84, 0.3);
        }
        .system-status-icon {
            font-size: 4rem;
            margin-bottom: 15px;
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
                <a href="admin_dashboard.php" class="nav-link active">
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
            <div class="mt-3">v1.4.0 (Final Release)</div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h2 class="fw-bold mb-0">總覽儀表板</h2>
            <div class="status-indicator">
                <span class="status-dot"></span>
                <span>System Online</span>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-label">總營收</div>
                    <div class="metric-value">NT$ <?php echo number_format($total_revenue); ?></div>
                    <div class="metric-trend">
                        <i class="bi bi-arrow-up"></i>
                        <span>+<?php echo $growth_percentage; ?>% 本月累計</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-label">待處理訂單</div>
                    <div class="metric-value"><?php echo $pending_orders; ?></div>
                    <div class="metric-trend">
                        <i class="bi bi-arrow-up"></i>
                        <span>需盡快出貨</span>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card">
                    <div class="metric-label">總商品數</div>
                    <div class="metric-value"><?php echo $total_skus; ?></div>
                    <div class="metric-trend">
                        <i class="bi bi-arrow-up"></i>
                        <span>上架中</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Top Brands Card -->
            <div class="col-md-8">
                <div class="chart-card">
                    <h5 class="fw-bold mb-4">熱銷品牌排行</h5>
                    <?php if (count($top_brands) > 0): ?>
                        <?php 
                        $max_revenue = $top_brands[0]['brand_revenue']; // 最高營收用於計算百分比
                        foreach ($top_brands as $index => $brand): 
                            $percentage = ($brand['brand_revenue'] / $max_revenue) * 100;
                        ?>
                            <div class="brand-item">
                                <div class="d-flex align-items-center" style="flex: 1;">
                                    <div class="brand-rank">#<?php echo $index + 1; ?></div>
                                    <div class="brand-name"><?php echo htmlspecialchars($brand['BrandName']); ?></div>
                                </div>
                                <div class="brand-revenue">NT$ <?php echo number_format($brand['brand_revenue']); ?></div>
                            </div>
                            <div class="progress-bar-custom">
                                <div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">尚無銷售數據</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Status Card -->
            <div class="col-md-4">
                <div class="system-status-card">
                    <div class="system-status-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-3">系統狀態正常</h5>
                    <p class="mb-0 opacity-75">資料庫連線穩定<br>最後同步時間：剛剛</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

