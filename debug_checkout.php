<?php
/**
 * 訂單提交調試工具
 * 用於診斷訂單提交問題
 */

session_start();

// 資料庫連線
$conn = new mysqli("localhost", "root", "", "final_project_db");
if ($conn->connect_error) {
    die("連線失敗：" . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 啟用錯誤報告
error_reporting(E_ALL);
ini_set('display_errors', 1);

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>訂單提交調試</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0; }
        .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 訂單提交調試工具</h1>
        
        <?php
        // 檢查購物車
        echo '<div class="info">';
        echo '<h3>1. 檢查購物車</h3>';
        if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
            echo '<div class="error">❌ 購物車為空</div>';
            echo '<p>請先到商品頁面將商品加入購物車</p>';
        } else {
            echo '<div class="success">✅ 購物車有 ' . count($_SESSION['cart']) . ' 項商品</div>';
            echo '<pre>' . print_r($_SESSION['cart'], true) . '</pre>';
        }
        echo '</div>';
        
        // 檢查資料庫表結構
        echo '<div class="info">';
        echo '<h3>2. 檢查 Order 表結構</h3>';
        $structure = $conn->query("SHOW COLUMNS FROM `Order`");
        if ($structure) {
            echo '<table>';
            echo '<tr><th>欄位</th><th>類型</th><th>Null</th><th>Key</th><th>預設值</th><th>額外</th></tr>';
            while ($row = $structure->fetch_assoc()) {
                $highlight = ($row['Field'] === 'Order_ID' && strpos($row['Extra'] ?? '', 'auto_increment') === false) ? 'style="background: #fff3cd;"' : '';
                echo '<tr ' . $highlight . '>';
                echo '<td><strong>' . htmlspecialchars($row['Field']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Default'] ?? 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($row['Extra'] ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            // 檢查 AUTO_INCREMENT
            $auto_check = $conn->query("SHOW COLUMNS FROM `Order` WHERE Field = 'Order_ID' AND Extra LIKE '%auto_increment%'");
            if ($auto_check && $auto_check->num_rows > 0) {
                echo '<div class="success">✅ Order_ID 已開啟 AUTO_INCREMENT</div>';
            } else {
                echo '<div class="error">❌ Order_ID 未開啟 AUTO_INCREMENT</div>';
                echo '<p>請執行：<code>ALTER TABLE `Order` MODIFY COLUMN `Order_ID` INT NOT NULL AUTO_INCREMENT;</code></p>';
            }
        }
        echo '</div>';
        
        // 測試插入
        if (isset($_GET['test']) && $_GET['test'] === 'insert') {
            echo '<div class="info">';
            echo '<h3>3. 測試插入訂單</h3>';
            
            try {
                // 獲取測試客戶
                $test_customer = $conn->query("SELECT CustomerID FROM Customer LIMIT 1");
                if (!$test_customer || $test_customer->num_rows == 0) {
                    throw new Exception('找不到測試客戶，請先創建至少一個客戶');
                }
                $test_customer_id = intval($test_customer->fetch_assoc()['CustomerID']);
                echo '<p>使用測試客戶 ID: ' . $test_customer_id . '</p>';
                
                // 創建測試地址
                $stmt = $conn->prepare("INSERT INTO AddressBook (CustomerID, ReceiverName, Phone, Address, PaymentMethod) VALUES (?, '測試', '0912345678', '測試地址', 'Credit Card')");
                if (!$stmt) {
                    throw new Exception('準備插入地址失敗：' . $conn->error);
                }
                $stmt->bind_param("i", $test_customer_id);
                if (!$stmt->execute()) {
                    throw new Exception('插入地址失敗：' . $stmt->error);
                }
                $test_address_id = intval($conn->insert_id);
                echo '<p>✅ 創建測試地址，ID: ' . $test_address_id . '</p>';
                $stmt->close();
                
                // 測試插入訂單
                echo '<p>嘗試插入訂單...</p>';
                $stmt = $conn->prepare("INSERT INTO `Order` (Customer_ID, Address_ID, OrderDate, PaymentMethod, Status) VALUES (?, ?, NOW(), 'Credit Card', 'Pending')");
                if (!$stmt) {
                    throw new Exception('準備插入訂單失敗：' . $conn->error . ' (錯誤代碼: ' . $conn->errno . ')');
                }
                $stmt->bind_param("ii", $test_customer_id, $test_address_id);
                
                if (!$stmt->execute()) {
                    throw new Exception('插入訂單失敗：' . $stmt->error . ' (錯誤代碼: ' . $stmt->errno . ')');
                }
                
                $test_order_id = intval($conn->insert_id);
                echo '<div class="success">';
                echo '<h4>✅ 測試插入成功！</h4>';
                echo '<p>新訂單 ID: <strong>' . $test_order_id . '</strong></p>';
                echo '</div>';
                
                // 清理測試資料
                $conn->query("DELETE FROM `Order` WHERE Order_ID = $test_order_id");
                $conn->query("DELETE FROM AddressBook WHERE AddressID = $test_address_id");
                echo '<p>已清理測試資料</p>';
                
                $stmt->close();
                
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h4>❌ 測試失敗</h4>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<div class="info">';
            echo '<h3>3. 執行測試</h3>';
            echo '<a href="?test=insert" class="btn">執行插入測試</a>';
            echo '</div>';
        }
        
        // 檢查相關表
        echo '<div class="info">';
        echo '<h3>4. 檢查相關表</h3>';
        $tables = ['Customer', 'AddressBook', 'OrderItem', 'SKU', 'Product'];
        echo '<ul>';
        foreach ($tables as $table) {
            $check = $conn->query("SHOW TABLES LIKE '$table'");
            if ($check && $check->num_rows > 0) {
                $count = $conn->query("SELECT COUNT(*) as cnt FROM `$table`")->fetch_assoc()['cnt'];
                echo '<li>✅ <code>' . $table . '</code> 表存在（' . $count . ' 筆記錄）</li>';
            } else {
                echo '<li>❌ <code>' . $table . '</code> 表不存在</li>';
            }
        }
        echo '</ul>';
        echo '</div>';
        
        // 模擬訂單提交
        if (isset($_GET['simulate']) && $_GET['simulate'] === 'yes' && !empty($_SESSION['cart'])) {
            echo '<div class="info">';
            echo '<h3>5. 模擬訂單提交</h3>';
            
            // 模擬表單資料
            $name = '測試用戶';
            $email = 'test@example.com';
            $phone = '0912345678';
            $address = '測試地址';
            $payment_method = 'Credit Card';
            
            echo '<p>模擬資料：</p>';
            echo '<pre>姓名: ' . $name . '
郵件: ' . $email . '
電話: ' . $phone . '
地址: ' . $address . '
付款: ' . $payment_method . '</pre>';
            
            try {
                $conn->begin_transaction();
                
                // 步驟 1: 客戶
                $stmt = $conn->prepare("SELECT CustomerID FROM Customer WHERE Email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $customer_id = intval($result->fetch_assoc()['CustomerID']);
                    echo '<p>✅ 找到客戶 ID: ' . $customer_id . '</p>';
                } else {
                    $stmt->close();
                    $stmt = $conn->prepare("INSERT INTO Customer (Email, Name, Phone) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $email, $name, $phone);
                    $stmt->execute();
                    $customer_id = intval($conn->insert_id);
                    echo '<p>✅ 創建客戶 ID: ' . $customer_id . '</p>';
                }
                $stmt->close();
                
                // 步驟 2: 地址
                $stmt = $conn->prepare("INSERT INTO AddressBook (CustomerID, ReceiverName, Phone, Address, PaymentMethod) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $customer_id, $name, $phone, $address, $payment_method);
                $stmt->execute();
                $address_id = intval($conn->insert_id);
                echo '<p>✅ 創建地址 ID: ' . $address_id . '</p>';
                $stmt->close();
                
                // 步驟 3: 訂單
                $stmt = $conn->prepare("INSERT INTO `Order` (Customer_ID, Address_ID, OrderDate, PaymentMethod, Status) VALUES (?, ?, NOW(), ?, 'Pending')");
                $stmt->bind_param("iis", $customer_id, $address_id, $payment_method);
                $stmt->execute();
                $order_id = intval($conn->insert_id);
                echo '<p>✅ 創建訂單 ID: ' . $order_id . '</p>';
                $stmt->close();
                
                // 步驟 4: 訂單明細
                $stmt = $conn->prepare("INSERT INTO OrderItem (Order_ID, SKU_ID, Quantity) VALUES (?, ?, ?)");
                foreach ($_SESSION['cart'] as $sku_id => $quantity) {
                    $qty = intval($quantity);
                    if ($qty > 0) {
                        $stmt->bind_param("isi", $order_id, $sku_id, $qty);
                        $stmt->execute();
                        echo '<p>✅ 插入明細：SKU=' . $sku_id . ', 數量=' . $qty . '</p>';
                    }
                }
                $stmt->close();
                
                $conn->rollback(); // 模擬測試，不實際提交
                echo '<div class="success"><h4>✅ 模擬提交成功！所有步驟都正常</h4></div>';
                
            } catch (Exception $e) {
                $conn->rollback();
                echo '<div class="error">';
                echo '<h4>❌ 模擬提交失敗</h4>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            if (!empty($_SESSION['cart'])) {
                echo '<div class="info">';
                echo '<h3>5. 模擬訂單提交</h3>';
                echo '<a href="?simulate=yes" class="btn">執行模擬提交</a>';
                echo '</div>';
            }
        }
        
        $conn->close();
        ?>
        
        <div class="info">
            <h3>📝 下一步</h3>
            <ol>
                <li>檢查上述所有項目</li>
                <li>如果測試插入失敗，請修復資料庫問題</li>
                <li>如果模擬提交失敗，請查看具體錯誤</li>
                <li>修復後回到 <a href="checkout.php">checkout.php</a> 再次嘗試</li>
            </ol>
        </div>
    </div>
</body>
</html>


