<?php
/**
 * 完整診斷和修復工具
 * 檢查並修復所有可能的問題
 */

// 資料庫連線
require_once 'db_connect.php';
if ($conn->connect_error) {
    die("連線失敗：" . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>完整修復工具</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0; }
        .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-success { background: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 完整診斷和修復工具</h1>
        
        <?php
        $all_fixed = true;
        $issues_found = [];
        
        // ===== 檢查 1: Order 表是否存在 =====
        echo '<div class="info">';
        echo '<h3>1. 檢查 Order 表</h3>';
        $table_check = $conn->query("SHOW TABLES LIKE 'Order'");
        if (!$table_check || $table_check->num_rows == 0) {
            echo '<div class="error">❌ Order 表不存在！</div>';
            $all_fixed = false;
            $issues_found[] = 'Order 表不存在';
        } else {
            echo '<div class="success">✅ Order 表存在</div>';
            
            // 檢查欄位
            $columns = $conn->query("SHOW COLUMNS FROM `Order`");
            $field_names = [];
            $order_id_info = null;
            
            while ($col = $columns->fetch_assoc()) {
                $field_names[] = $col['Field'];
                if ($col['Field'] === 'Order_ID' || $col['Field'] === 'OrderID') {
                    $order_id_info = $col;
                }
            }
            
            echo '<p>表欄位：' . implode(', ', $field_names) . '</p>';
            
            // 檢查 Order_ID 欄位名稱
            $has_order_id = in_array('Order_ID', $field_names);
            $has_orderid = in_array('OrderID', $field_names);
            
            if (!$has_order_id && !$has_orderid) {
                echo '<div class="error">❌ 找不到 Order_ID 或 OrderID 欄位！</div>';
                $all_fixed = false;
                $issues_found[] = '找不到主鍵欄位';
            } else {
                $pk_field = $has_order_id ? 'Order_ID' : 'OrderID';
                echo '<div class="success">✅ 主鍵欄位：' . $pk_field . '</div>';
                
                // 檢查 AUTO_INCREMENT
                $auto_check = $conn->query("SHOW COLUMNS FROM `Order` WHERE Field = '$pk_field' AND Extra LIKE '%auto_increment%'");
                if ($auto_check && $auto_check->num_rows > 0) {
                    echo '<div class="success">✅ ' . $pk_field . ' 已開啟 AUTO_INCREMENT</div>';
                } else {
                    echo '<div class="error">❌ ' . $pk_field . ' 未開啟 AUTO_INCREMENT</div>';
                    $all_fixed = false;
                    $issues_found[] = 'AUTO_INCREMENT 未開啟';
                    
                    // 如果用戶點擊修復
                    if (isset($_GET['fix']) && $_GET['fix'] === 'autoincrement') {
                        $fix_sql = "ALTER TABLE `Order` MODIFY COLUMN `$pk_field` INT NOT NULL AUTO_INCREMENT";
                        if ($conn->query($fix_sql)) {
                            echo '<div class="success">✅ 已成功開啟 AUTO_INCREMENT</div>';
                            $all_fixed = true;
                        } else {
                            echo '<div class="error">❌ 修復失敗：' . $conn->error . '</div>';
                        }
                    } else {
                        echo '<p>修復 SQL：</p>';
                        echo '<pre>ALTER TABLE `Order` MODIFY COLUMN `' . $pk_field . '` INT NOT NULL AUTO_INCREMENT;</pre>';
                        echo '<a href="?fix=autoincrement" class="btn btn-danger">執行修復</a>';
                    }
                }
                
                // 檢查是否有 Order_ID = 0 的記錄
                $zero_check = $conn->query("SELECT COUNT(*) as cnt FROM `Order` WHERE `$pk_field` = 0");
                if ($zero_check) {
                    $zero_count = $zero_check->fetch_assoc()['cnt'];
                    if ($zero_count > 0) {
                        echo '<div class="warning">⚠️ 發現 ' . $zero_count . ' 筆 ' . $pk_field . ' = 0 的記錄</div>';
                        if (isset($_GET['fix']) && $_GET['fix'] === 'cleanzero') {
                            $clean_sql = "DELETE FROM `Order` WHERE `$pk_field` = 0";
                            if ($conn->query($clean_sql)) {
                                echo '<div class="success">✅ 已刪除問題記錄</div>';
                            } else {
                                echo '<div class="error">❌ 刪除失敗：' . $conn->error . '</div>';
                            }
                        } else {
                            echo '<a href="?fix=cleanzero" class="btn btn-danger">刪除問題記錄</a>';
                        }
                    }
                }
            }
            
            // 檢查其他必要欄位
            $required_fields = ['Customer_ID', 'Address_ID', 'OrderDate', 'PaymentMethod', 'Status'];
            $missing_fields = [];
            foreach ($required_fields as $req_field) {
                // 檢查可能的變體
                $variants = [
                    $req_field,
                    str_replace('_', '', $req_field), // Customer_ID -> CustomerID
                    strtolower($req_field),
                    strtolower(str_replace('_', '', $req_field))
                ];
                
                $found = false;
                foreach ($variants as $variant) {
                    if (in_array($variant, $field_names)) {
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    $missing_fields[] = $req_field;
                }
            }
            
            if (!empty($missing_fields)) {
                echo '<div class="error">❌ 缺少欄位：' . implode(', ', $missing_fields) . '</div>';
                $all_fixed = false;
                $issues_found[] = '缺少必要欄位';
            } else {
                echo '<div class="success">✅ 所有必要欄位都存在</div>';
            }
        }
        echo '</div>';
        
        // ===== 檢查 2: 測試插入 =====
        if (isset($_GET['test']) && $_GET['test'] === 'insert') {
            echo '<div class="info">';
            echo '<h3>2. 測試插入功能</h3>';
            
            try {
                // 獲取測試客戶
                $test_customer = $conn->query("SELECT CustomerID FROM Customer LIMIT 1");
                if (!$test_customer || $test_customer->num_rows == 0) {
                    throw new Exception('找不到測試客戶');
                }
                $test_customer_id = intval($test_customer->fetch_assoc()['CustomerID']);
                
                // 創建測試地址
                $stmt = $conn->prepare("INSERT INTO AddressBook (CustomerID, ReceiverName, Phone, Address, PaymentMethod) VALUES (?, '測試', '0912345678', '測試地址', 'Credit Card')");
                $stmt->bind_param("i", $test_customer_id);
                $stmt->execute();
                $test_address_id = intval($conn->insert_id);
                
                // 測試插入訂單（不包含 Order_ID）
                $stmt = $conn->prepare("INSERT INTO `Order` (Customer_ID, Address_ID, OrderDate, PaymentMethod, Status) VALUES (?, ?, NOW(), 'Credit Card', 'Pending')");
                $stmt->bind_param("ii", $test_customer_id, $test_address_id);
                
                if ($stmt->execute()) {
                    $test_order_id = intval($conn->insert_id);
                    echo '<div class="success">';
                    echo '<h4>✅ 測試插入成功！</h4>';
                    echo '<p>新訂單 ID: <strong>' . $test_order_id . '</strong></p>';
                    echo '</div>';
                    
                    // 清理
                    $conn->query("DELETE FROM `Order` WHERE Order_ID = $test_order_id");
                    $conn->query("DELETE FROM AddressBook WHERE AddressID = $test_address_id");
                } else {
                    throw new Exception('插入失敗：' . $stmt->error);
                }
                $stmt->close();
                
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h4>❌ 測試失敗</h4>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
                $all_fixed = false;
            }
            echo '</div>';
        } else {
            echo '<div class="info">';
            echo '<h3>2. 測試插入功能</h3>';
            echo '<a href="?test=insert" class="btn">執行測試</a>';
            echo '</div>';
        }
        
        // ===== 總結 =====
        echo '<div class="info">';
        echo '<h3>📋 問題總結</h3>';
        if (empty($issues_found)) {
            echo '<div class="success">✅ 沒有發現問題！所有檢查都通過了。</div>';
            echo '<p>如果還是無法提交訂單，請：</p>';
            echo '<ol>';
            echo '<li>查看 <a href="checkout.php">checkout.php</a> 頁面顯示的具體錯誤訊息</li>';
            echo '<li>檢查瀏覽器控制台是否有 JavaScript 錯誤</li>';
            echo '<li>確認購物車中有商品</li>';
            echo '</ol>';
        } else {
            echo '<div class="error">';
            echo '<h4>發現以下問題：</h4>';
            echo '<ul>';
            foreach ($issues_found as $issue) {
                echo '<li>' . htmlspecialchars($issue) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        echo '</div>';
        
        $conn->close();
        ?>
        
        <div class="info">
            <h3>📝 下一步</h3>
            <ol>
                <li>執行上述所有檢查</li>
                <li>點擊修復按鈕修復發現的問題</li>
                <li>執行測試插入確認功能正常</li>
                <li>回到 <a href="checkout.php">checkout.php</a> 嘗試提交訂單</li>
            </ol>
        </div>
    </div>
</body>
</html>


