<?php
require_once 'admin_auth.php';

// 資料庫連線
$conn = new mysqli("localhost", "root", "", "final_project_db");
if ($conn->connect_error) die("連線失敗");
$conn->set_charset("utf8mb4");

// 處理搜尋
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_sql = "";
if ($search) {
    $search_escaped = $conn->real_escape_string($search);
    $search_sql = " AND (s.SKU_ID LIKE '%$search_escaped%' OR p.BrandName LIKE '%$search_escaped%' OR p.ProductName LIKE '%$search_escaped%')";
}

// 處理編輯請求 - 獲取商品資料
$edit_sku_id = isset($_GET['edit']) ? $conn->real_escape_string($_GET['edit']) : '';
$edit_product = null;
if ($edit_sku_id) {
    $edit_sql = "SELECT s.*, p.BrandName, p.ProductName, p.Category
                 FROM SKU s
                 JOIN Product p ON s.ProductID = p.ProductID
                 WHERE s.SKU_ID = '$edit_sku_id'";
    $edit_result = $conn->query($edit_sql);
    if ($edit_result && $edit_result->num_rows > 0) {
        $edit_product = $edit_result->fetch_assoc();
    }
}

// 查詢所有商品（SKU + Product JOIN）
$sql = "SELECT s.SKU_ID, s.ProductID, p.BrandName, p.ProductName, p.Category, 
               s.CPU, s.GPU, s.VRAM, s.RAM, s.StorageCapacity, s.StorageType, 
               s.ScreenSize, s.Weight, s.Price, s.Stock
        FROM SKU s
        JOIN Product p ON s.ProductID = p.ProductID
        WHERE 1=1 $search_sql
        ORDER BY s.SKU_ID ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品與庫存 | LaptopMart Admin</title>
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
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: #495057;
        }
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        .btn-action {
            padding: 5px 10px;
            margin: 0 2px;
        }
        .stock-low {
            color: #dc3545;
            font-weight: 600;
        }
        .stock-ok {
            color: #198754;
        }
        .search-bar {
            max-width: 400px;
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
                <a href="admin_inventory.php" class="nav-link active">
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
            <div class="mt-3 text-center" style="color: rgba(255,255,255,0.6); font-size: 0.85rem;">v1.4.0 (Final Release)</div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h2 class="fw-bold mb-0">商品資料庫 (Inventory)</h2>
            <div class="status-indicator">
                <span class="status-dot"></span>
                <span>System Online</span>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>操作成功！
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <!-- Search and Add Button -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <form method="GET" action="admin_inventory.php" class="search-bar">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" class="form-control border-0 bg-light" 
                               name="search" placeholder="搜尋 ID, 品牌, 或型號..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </form>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="bi bi-plus-circle me-2"></i>新增型號
                </button>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>品牌</th>
                            <th>型號名稱</th>
                            <th>規格簡述</th>
                            <th>價格</th>
                            <th>庫存</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo htmlspecialchars($row['SKU_ID']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['BrandName']); ?></td>
                                    <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php 
                                            $spec = $row['CPU'] . '/' . $row['RAM'] . 'G/' . 
                                                   ($row['GPU'] ?: '內顯') . '/' . 
                                                   $row['StorageCapacity'] . 'G' . $row['StorageType'];
                                            echo htmlspecialchars($spec);
                                            ?>
                                        </small>
                                    </td>
                                    <td><strong>NT$ <?php echo number_format($row['Price']); ?></strong></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="<?php echo $row['Stock'] <= 5 ? 'stock-low' : 'stock-ok'; ?>">
                                                <?php echo $row['Stock']; ?>
                                            </span>
                                            <button class="btn btn-sm btn-outline-secondary" 
                                                    onclick="quickUpdateStock('<?php echo htmlspecialchars($row['SKU_ID']); ?>', <?php echo $row['Stock']; ?>)"
                                                    title="快速修改庫存">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary btn-action" 
                                                onclick="editProduct('<?php echo htmlspecialchars($row['SKU_ID']); ?>')"
                                                title="編輯">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger btn-action" 
                                                onclick="deleteProduct('<?php echo htmlspecialchars($row['SKU_ID']); ?>')"
                                                title="刪除">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                    <p class="mt-2 mb-0">找不到符合條件的商品</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">新增商品型號</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="admin_product_action.php?action=add">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">品牌 <span class="text-danger">*</span></label>
                                <select class="form-select" name="brand" required>
                                    <option value="">請選擇</option>
                                    <option value="Apple">Apple</option>
                                    <option value="ASUS">ASUS</option>
                                    <option value="Dell">Dell</option>
                                    <option value="HP">HP</option>
                                    <option value="Lenovo">Lenovo</option>
                                    <option value="Acer">Acer</option>
                                    <option value="MSI">MSI</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">分類 <span class="text-danger">*</span></label>
                                <select class="form-select" name="category" required>
                                    <option value="">請選擇</option>
                                    <option value="Laptop">一般筆電</option>
                                    <option value="Gaming Laptop">電競筆電</option>
                                    <option value="Ultrabook">輕薄筆電</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">型號名稱 <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="product_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SKU_ID <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="sku_id" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">處理器 (CPU) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="cpu" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">顯示卡 (GPU)</label>
                                <input type="text" class="form-control" name="gpu">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">顯存 (VRAM) GB</label>
                                <input type="number" class="form-control" name="vram" value="0" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">記憶體 (RAM) GB <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="ram" required min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">儲存類型</label>
                                <select class="form-select" name="storage_type">
                                    <option value="SSD">SSD</option>
                                    <option value="HDD">HDD</option>
                                    <option value="NVMe SSD">NVMe SSD</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">儲存容量 GB <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="storage_capacity" required min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">螢幕尺寸 吋</label>
                                <input type="text" class="form-control" name="screen_size">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">重量 kg</label>
                                <input type="number" step="0.1" class="form-control" name="weight">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">價格 NT$ <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="price" required min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">庫存數量 <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="stock" required min="0" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">新增商品</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <?php if ($edit_product): ?>
    <div class="modal fade show" id="editProductModal" tabindex="-1" style="display: block;" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">編輯商品型號</h5>
                    <a href="admin_inventory.php" class="btn-close"></a>
                </div>
                <form method="POST" action="admin_product_action.php?action=edit">
                    <input type="hidden" name="sku_id" value="<?php echo htmlspecialchars($edit_product['SKU_ID']); ?>">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">品牌</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_product['BrandName']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">分類</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_product['Category']); ?>" readonly>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">型號名稱</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_product['ProductName']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SKU_ID</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($edit_product['SKU_ID']); ?>" readonly>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">處理器 (CPU) <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="cpu" value="<?php echo htmlspecialchars($edit_product['CPU']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">顯示卡 (GPU)</label>
                                <input type="text" class="form-control" name="gpu" value="<?php echo htmlspecialchars($edit_product['GPU'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">顯存 (VRAM) GB</label>
                                <input type="number" class="form-control" name="vram" value="<?php echo $edit_product['VRAM'] ?? 0; ?>" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">記憶體 (RAM) GB <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="ram" value="<?php echo $edit_product['RAM']; ?>" required min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">儲存類型</label>
                                <select class="form-select" name="storage_type">
                                    <option value="SSD" <?php echo ($edit_product['StorageType'] ?? 'SSD') == 'SSD' ? 'selected' : ''; ?>>SSD</option>
                                    <option value="HDD" <?php echo ($edit_product['StorageType'] ?? '') == 'HDD' ? 'selected' : ''; ?>>HDD</option>
                                    <option value="NVMe SSD" <?php echo ($edit_product['StorageType'] ?? '') == 'NVMe SSD' ? 'selected' : ''; ?>>NVMe SSD</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">儲存容量 GB <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="storage_capacity" value="<?php echo $edit_product['StorageCapacity']; ?>" required min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">螢幕尺寸 吋</label>
                                <input type="text" class="form-control" name="screen_size" value="<?php echo htmlspecialchars($edit_product['ScreenSize'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">重量 kg</label>
                                <input type="number" step="0.1" class="form-control" name="weight" value="<?php echo $edit_product['Weight'] ?? 0; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">價格 NT$ <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="price" value="<?php echo $edit_product['Price']; ?>" required min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">庫存數量 <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="stock" value="<?php echo $edit_product['Stock']; ?>" required min="0">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="admin_inventory.php" class="btn btn-secondary">取消</a>
                        <button type="submit" class="btn btn-primary">儲存變更</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <!-- Quick Update Stock Modal -->
    <div class="modal fade" id="quickStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">快速修改庫存</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="admin_product_action.php?action=update_stock" id="quickStockForm">
                    <input type="hidden" name="sku_id" id="stock_sku_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">庫存數量</label>
                            <input type="number" class="form-control" name="stock" id="stock_quantity" required min="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                        <button type="submit" class="btn btn-primary">更新</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editProduct(skuId) {
            window.location.href = 'admin_inventory.php?edit=' + skuId;
        }
        
        function quickUpdateStock(skuId, currentStock) {
            document.getElementById('stock_sku_id').value = skuId;
            document.getElementById('stock_quantity').value = currentStock;
            var modal = new bootstrap.Modal(document.getElementById('quickStockModal'));
            modal.show();
        }
        
        function deleteProduct(skuId) {
            if (confirm('確定要刪除此商品嗎？此操作無法復原。')) {
                window.location.href = 'admin_product_action.php?action=delete&sku_id=' + skuId;
            }
        }
    </script>
</body>
</html>

