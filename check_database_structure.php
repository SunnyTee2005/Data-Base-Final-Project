<?php
/**
 * 檢查資料庫結構與程式碼是否匹配
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
    <title>資料庫結構檢查</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0; }
        .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        .mismatch { background: #fff3cd; }
        .missing { background: #f8d7da; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 資料庫結構檢查工具</h1>
        <p>檢查資料庫表結構是否與程式碼中使用的欄位名稱匹配</p>
        
        <?php
        // 程式碼中使用的欄位名稱（從 checkout.php 和其他檔案推斷）
        $expected_fields = [
            'Customer' => ['CustomerID', 'Email', 'Name', 'Phone', 'Password'],
            'AddressBook' => ['AddressID', 'CustomerID', 'ReceiverName', 'Phone', 'Address', 'PaymentMethod'],
            'Order' => ['Order_ID', 'Customer_ID', 'Address_ID', 'OrderDate', 'PaymentMethod', 'Status'],
            'OrderItem' => ['Order_ID', 'SKU_ID', 'Quantity'],
            'SKU' => ['SKU_ID', 'ProductID', 'Price', 'CPU', 'GPU', 'VRAM', 'RAM', 'StorageType', 'StorageCapacity', 'ScreenSize', 'Weight', 'Stock'],
            'Product' => ['ProductID', 'BrandName', 'ProductName', 'Category', 'Status']
        ];
        
        foreach ($expected_fields as $table_name => $fields) {
            echo '<div class="info">';
            echo '<h3>📋 檢查表：' . htmlspecialchars($table_name) . '</h3>';
            
            // 檢查表是否存在
            $table_check = $conn->query("SHOW TABLES LIKE '$table_name'");
            if (!$table_check || $table_check->num_rows == 0) {
                echo '<div class="error">❌ 表 <code>' . $table_name . '</code> 不存在！</div>';
                echo '</div>';
                continue;
            }
            
            // 獲取實際的欄位
            $actual_fields = [];
            $structure = $conn->query("SHOW COLUMNS FROM `$table_name`");
            if ($structure) {
                echo '<table>';
                echo '<tr><th>程式碼期望的欄位</th><th>資料庫實際欄位</th><th>狀態</th><th>類型</th><th>Key</th><th>額外</th></tr>';
                
                while ($row = $structure->fetch_assoc()) {
                    $actual_fields[] = $row['Field'];
                }
                
                // 檢查每個期望的欄位
                foreach ($fields as $expected_field) {
                    $found = in_array($expected_field, $actual_fields);
                    $row_class = '';
                    $status = '';
                    
                    if ($found) {
                        $db_field = null;
                        $structure->data_seek(0);
                        while ($r = $structure->fetch_assoc()) {
                            if ($r['Field'] === $expected_field) {
                                $db_field = $r;
                                break;
                            }
                        }
                        $status = '✅ 匹配';
                        echo '<tr>';
                        echo '<td><strong>' . htmlspecialchars($expected_field) . '</strong></td>';
                        echo '<td>' . htmlspecialchars($db_field['Field']) . '</td>';
                        echo '<td>' . $status . '</td>';
                        echo '<td>' . htmlspecialchars($db_field['Type']) . '</td>';
                        echo '<td>' . htmlspecialchars($db_field['Key']) . '</td>';
                        echo '<td>' . htmlspecialchars($db_field['Extra'] ?? '') . '</td>';
                        echo '</tr>';
                    } else {
                        $status = '❌ 找不到';
                        $row_class = 'class="missing"';
                        echo '<tr ' . $row_class . '>';
                        echo '<td><strong>' . htmlspecialchars($expected_field) . '</strong></td>';
                        echo '<td>-</td>';
                        echo '<td>' . $status . '</td>';
                        echo '<td>-</td>';
                        echo '<td>-</td>';
                        echo '<td>-</td>';
                        echo '</tr>';
                    }
                }
                
                // 檢查資料庫中有但程式碼沒使用的欄位
                $unused = array_diff($actual_fields, $fields);
                if (!empty($unused)) {
                    echo '<tr><td colspan="6" style="background: #e9ecef;"><strong>資料庫中額外的欄位（程式碼未使用）：</strong></td></tr>';
                    foreach ($unused as $unused_field) {
                        $structure->data_seek(0);
                        $db_field = null;
                        while ($r = $structure->fetch_assoc()) {
                            if ($r['Field'] === $unused_field) {
                                $db_field = $r;
                                break;
                            }
                        }
                        echo '<tr style="background: #f8f9fa;">';
                        echo '<td>-</td>';
                        echo '<td>' . htmlspecialchars($unused_field) . '</td>';
                        echo '<td>⚠️ 未使用</td>';
                        echo '<td>' . htmlspecialchars($db_field['Type']) . '</td>';
                        echo '<td>' . htmlspecialchars($db_field['Key']) . '</td>';
                        echo '<td>' . htmlspecialchars($db_field['Extra'] ?? '') . '</td>';
                        echo '</tr>';
                    }
                }
                
                echo '</table>';
                
                // 檢查關鍵欄位
                if ($table_name === 'Order') {
                    $order_id_check = $conn->query("SHOW COLUMNS FROM `Order` WHERE Field = 'Order_ID' AND Extra LIKE '%auto_increment%'");
                    if ($order_id_check && $order_id_check->num_rows > 0) {
                        echo '<div class="success">✅ Order_ID 已開啟 AUTO_INCREMENT</div>';
                    } else {
                        echo '<div class="error">❌ Order_ID 未開啟 AUTO_INCREMENT</div>';
                        echo '<p>修復 SQL：</p>';
                        echo '<pre>ALTER TABLE `Order` MODIFY COLUMN `Order_ID` INT NOT NULL AUTO_INCREMENT;</pre>';
                    }
                }
                
                // 檢查是否有欄位名稱不匹配
                $missing_fields = array_diff($fields, $actual_fields);
                if (!empty($missing_fields)) {
                    echo '<div class="error">';
                    echo '<h4>❌ 缺少以下欄位：</h4>';
                    echo '<ul>';
                    foreach ($missing_fields as $missing) {
                        echo '<li><code>' . htmlspecialchars($missing) . '</code></li>';
                    }
                    echo '</ul>';
                    echo '</div>';
                } else {
                    echo '<div class="success">✅ 所有必要欄位都存在</div>';
                }
                
            } else {
                echo '<div class="error">無法讀取表結構：' . $conn->error . '</div>';
            }
            
            echo '</div>';
        }
        
        // 檢查常見的欄位名稱差異
        echo '<div class="warning">';
        echo '<h3>⚠️ 常見的欄位名稱差異</h3>';
        echo '<p>如果發現欄位名稱不匹配，可能是因為：</p>';
        echo '<ul>';
        echo '<li><code>Order_ID</code> vs <code>OrderID</code></li>';
        echo '<li><code>Customer_ID</code> vs <code>CustomerID</code></li>';
        echo '<li><code>Address_ID</code> vs <code>AddressID</code></li>';
        echo '<li><code>SKU_ID</code> vs <code>SKUID</code></li>';
        echo '<li><code>Product_ID</code> vs <code>ProductID</code></li>';
        echo '</ul>';
        echo '<p>如果發現不匹配，請：</p>';
        echo '<ol>';
        echo '<li>修改資料庫欄位名稱以匹配程式碼，或</li>';
        echo '<li>修改程式碼中的欄位名稱以匹配資料庫</li>';
        echo '</ol>';
        echo '</div>';
        
        // 生成修復 SQL（如果需要的話）
        echo '<div class="info">';
        echo '<h3>🔧 如果發現問題，可以使用以下 SQL 修復</h3>';
        echo '<p><strong>注意：</strong>執行前請先備份資料庫！</p>';
        echo '<pre>';
        echo "-- 修復 Order 表 AUTO_INCREMENT\n";
        echo "ALTER TABLE `Order` MODIFY COLUMN `Order_ID` INT NOT NULL AUTO_INCREMENT;\n\n";
        echo "-- 如果欄位名稱不同，可以使用以下方式重新命名（範例）\n";
        echo "-- ALTER TABLE `Order` CHANGE `OrderID` `Order_ID` INT NOT NULL AUTO_INCREMENT;\n";
        echo "-- ALTER TABLE `Order` CHANGE `CustomerID` `Customer_ID` INT NOT NULL;\n";
        echo "-- ALTER TABLE `Order` CHANGE `AddressID` `Address_ID` INT NOT NULL;\n";
        echo '</pre>';
        echo '</div>';
        
        $conn->close();
        ?>
    </div>
</body>
</html>


