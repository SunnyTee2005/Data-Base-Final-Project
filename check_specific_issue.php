<?php
/**
 * 檢查具體問題
 * 根據你的資料庫結構進行詳細檢查
 */

// 資料庫連線
$conn = new mysqli("localhost", "root", "", "final_project_db");
if ($conn->connect_error) {
    die("連線失敗：" . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>具體問題檢查</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0; }
        .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn-danger { background: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 具體問題檢查</h1>
        
        <?php
        // 檢查 1: Order_ID = 0 的記錄
        echo '<div class="info">';
        echo '<h3>1. 檢查是否有 Order_ID = 0 的記錄</h3>';
        $zero_check = $conn->query("SELECT Order_ID, Customer_ID, OrderDate FROM `Order` WHERE Order_ID = 0");
        if ($zero_check && $zero_check->num_rows > 0) {
            echo '<div class="error">';
            echo '<h4>❌ 發現 ' . $zero_check->num_rows . ' 筆 Order_ID = 0 的記錄！</h4>';
            echo '<p>這就是導致 "Duplicate entry \'0\' for key \'PRIMARY\'" 錯誤的原因！</p>';
            echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
            echo '<tr><th>Order_ID</th><th>Customer_ID</th><th>OrderDate</th></tr>';
            while ($row = $zero_check->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . $row['Order_ID'] . '</td>';
                echo '<td>' . ($row['Customer_ID'] ?? 'NULL') . '</td>';
                echo '<td>' . ($row['OrderDate'] ?? 'NULL') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            if (isset($_GET['delete_zero']) && $_GET['delete_zero'] === 'yes') {
                // 先刪除相關的 OrderItem
                $conn->query("DELETE FROM OrderItem WHERE Order_ID = 0");
                // 再刪除 Order
                $result = $conn->query("DELETE FROM `Order` WHERE Order_ID = 0");
                if ($result) {
                    echo '<div class="success">✅ 已刪除所有 Order_ID = 0 的記錄</div>';
                } else {
                    echo '<div class="error">❌ 刪除失敗：' . $conn->error . '</div>';
                }
            } else {
                echo '<p><strong>解決方法：</strong></p>';
                echo '<p>這些記錄必須刪除。點擊下方按鈕刪除：</p>';
                echo '<a href="?delete_zero=yes" class="btn btn-danger" onclick="return confirm(\'確定要刪除所有 Order_ID = 0 的記錄嗎？這會同時刪除相關的訂單明細。\')">刪除 Order_ID = 0 的記錄</a>';
            }
            echo '</div>';
        } else {
            echo '<div class="success">✅ 沒有發現 Order_ID = 0 的記錄</div>';
        }
        echo '</div>';
        
        // 檢查 2: AUTO_INCREMENT 當前值
        echo '<div class="info">';
        echo '<h3>2. 檢查 AUTO_INCREMENT 當前值</h3>';
        $auto_inc = $conn->query("SHOW TABLE STATUS LIKE 'Order'");
        if ($auto_inc) {
            $status = $auto_inc->fetch_assoc();
            $next_id = $status['Auto_increment'] ?? 'NULL';
            echo '<p>下一個自動產生的 Order_ID 將是：<strong>' . $next_id . '</strong></p>';
            
            if ($next_id == 1 || $next_id == 'NULL') {
                // 檢查最大 Order_ID
                $max_id = $conn->query("SELECT MAX(Order_ID) as max_id FROM `Order`");
                if ($max_id) {
                    $max = $max_id->fetch_assoc()['max_id'];
                    if ($max && $max > 0) {
                        $suggested = intval($max) + 1;
                        echo '<div class="warning">';
                        echo '<p>⚠️ AUTO_INCREMENT 值可能不正確。建議設置為：' . $suggested . '</p>';
                        if (isset($_GET['fix_auto']) && $_GET['fix_auto'] === 'yes') {
                            $fix_sql = "ALTER TABLE `Order` AUTO_INCREMENT = $suggested";
                            if ($conn->query($fix_sql)) {
                                echo '<div class="success">✅ 已修復 AUTO_INCREMENT 值</div>';
                            } else {
                                echo '<div class="error">❌ 修復失敗：' . $conn->error . '</div>';
                            }
                        } else {
                            echo '<a href="?fix_auto=yes" class="btn">修復 AUTO_INCREMENT 值</a>';
                        }
                        echo '</div>';
                    }
                }
            }
        }
        echo '</div>';
        
        // 檢查 3: 測試實際插入
        echo '<div class="info">';
        echo '<h3>3. 測試實際插入（模擬 checkout.php 的流程）</h3>';
        
        if (isset($_GET['test_insert']) && $_GET['test_insert'] === 'yes') {
            try {
                $conn->begin_transaction();
                
                // 獲取測試客戶
                $test_customer = $conn->query("SELECT CustomerID FROM Customer LIMIT 1");
                if (!$test_customer || $test_customer->num_rows == 0) {
                    throw new Exception('找不到測試客戶，請先創建至少一個客戶');
                }
                $test_customer_id = intval($test_customer->fetch_assoc()['CustomerID']);
                echo '<p>✅ 使用客戶 ID: ' . $test_customer_id . '</p>';
                
                // 創建測試地址
                $stmt = $conn->prepare("INSERT INTO AddressBook (CustomerID, ReceiverName, Phone, Address, PaymentMethod) VALUES (?, '測試', '0912345678', '測試地址', 'Credit Card')");
                $stmt->bind_param("i", $test_customer_id);
                $stmt->execute();
                $test_address_id = intval($conn->insert_id);
                echo '<p>✅ 創建地址 ID: ' . $test_address_id . '</p>';
                $stmt->close();
                
                // 插入訂單（完全按照 checkout.php 的方式）
                echo '<p>嘗試插入訂單（不包含 Order_ID）...</p>';
                $stmt = $conn->prepare("INSERT INTO `Order` (Customer_ID, Address_ID, OrderDate, PaymentMethod, Status) VALUES (?, ?, NOW(), 'Credit Card', 'Pending')");
                $stmt->bind_param("ii", $test_customer_id, $test_address_id);
                
                if ($stmt->execute()) {
                    $test_order_id = intval($conn->insert_id);
                    echo '<div class="success">';
                    echo '<h4>✅ 插入成功！</h4>';
                    echo '<p>新訂單 ID: <strong>' . $test_order_id . '</strong></p>';
                    echo '<p>insert_id 值: ' . var_export($conn->insert_id, true) . '</p>';
                    echo '</div>';
                    
                    // 測試插入訂單明細
                    echo '<p>測試插入訂單明細...</p>';
                    $test_sku = $conn->query("SELECT SKU_ID FROM SKU LIMIT 1");
                    if ($test_sku && $test_sku->num_rows > 0) {
                        $test_sku_id = $test_sku->fetch_assoc()['SKU_ID'];
                        $stmt_item = $conn->prepare("INSERT INTO OrderItem (Order_ID, SKU_ID, Quantity) VALUES (?, ?, 1)");
                        $stmt_item->bind_param("is", $test_order_id, $test_sku_id);
                        if ($stmt_item->execute()) {
                            echo '<div class="success">✅ 訂單明細插入成功</div>';
                        } else {
                            throw new Exception('訂單明細插入失敗：' . $stmt_item->error);
                        }
                        $stmt_item->close();
                    }
                    
                    // 回滾（測試用）
                    $conn->rollback();
                    echo '<p>（已回滾測試資料）</p>';
                } else {
                    throw new Exception('插入訂單失敗：' . $stmt->error . ' (錯誤代碼: ' . $stmt->errno . ')');
                }
                $stmt->close();
                
            } catch (Exception $e) {
                $conn->rollback();
                echo '<div class="error">';
                echo '<h4>❌ 測試失敗</h4>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        } else {
            echo '<a href="?test_insert=yes" class="btn">執行完整測試</a>';
        }
        echo '</div>';
        
        // 檢查 4: 檢查程式碼中的 SQL
        echo '<div class="info">';
        echo '<h3>4. 檢查 checkout.php 中的 SQL 語句</h3>';
        $checkout_file = file_get_contents('checkout.php');
        
        // 檢查是否有硬編碼 Order_ID
        if (preg_match('/INSERT INTO.*`Order`.*Order_ID/i', $checkout_file)) {
            echo '<div class="error">❌ 發現問題：INSERT 語句中包含 Order_ID</div>';
            echo '<p>請檢查 checkout.php 第 93 行附近的 INSERT 語句</p>';
        } else {
            echo '<div class="success">✅ INSERT 語句中沒有包含 Order_ID（正確）</div>';
        }
        
        // 檢查是否有使用 insert_id
        if (preg_match('/\$order_id\s*=\s*\$conn->insert_id/i', $checkout_file)) {
            echo '<div class="success">✅ 有使用 $conn->insert_id 獲取訂單 ID（正確）</div>';
        } else {
            echo '<div class="error">❌ 沒有使用 $conn->insert_id 獲取訂單 ID</div>';
        }
        
        // 檢查 OrderItem 是否使用正確的 order_id
        if (preg_match('/INSERT INTO OrderItem.*Order_ID.*\$order_id/i', $checkout_file)) {
            echo '<div class="success">✅ OrderItem 使用正確的 $order_id 變數（正確）</div>';
        } else {
            echo '<div class="warning">⚠️ 請確認 OrderItem 的 INSERT 使用正確的 $order_id</div>';
        }
        echo '</div>';
        
        $conn->close();
        ?>
        
        <div class="info">
            <h3>📝 總結</h3>
            <p>根據你的資料庫結構（從圖片看到）：</p>
            <ul>
                <li>✅ Order_ID 已開啟 AUTO_INCREMENT</li>
                <li>✅ 字段名稱正確（Order_ID, Customer_ID, Address_ID）</li>
                <li>✅ 所有必要字段都存在</li>
            </ul>
            <p><strong>最可能的原因是：</strong></p>
            <ol>
                <li>資料庫中存在 Order_ID = 0 的記錄（最常見）</li>
                <li>AUTO_INCREMENT 的起始值設置不正確</li>
                <li>程式碼中某處還在硬編碼 ID 值</li>
            </ol>
            <p>請執行上述檢查，特別是「刪除 Order_ID = 0 的記錄」和「執行完整測試」。</p>
        </div>
    </div>
</body>
</html>


