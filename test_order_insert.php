<?php
/**
 * 測試訂單插入功能
 * 用於診斷問題
 */

session_start();

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
    <title>訂單插入測試</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0; }
        .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 訂單插入測試工具</h1>
        
        <?php
        // 檢查 Order 表結構
        echo '<div class="info">';
        echo '<h3>步驟 1: 檢查 Order 表結構</h3>';
        $structure = $conn->query("SHOW COLUMNS FROM `Order`");
        if ($structure) {
            echo '<table>';
            echo '<tr><th>欄位名稱</th><th>類型</th><th>Null</th><th>Key</th><th>預設值</th><th>額外</th></tr>';
            $order_id_info = null;
            while ($row = $structure->fetch_assoc()) {
                if ($row['Field'] === 'Order_ID') {
                    $order_id_info = $row;
                }
                $extra = $row['Extra'] ?? '';
                $highlight = ($row['Field'] === 'Order_ID') ? 'style="background: #fff3cd;"' : '';
                echo '<tr ' . $highlight . '>';
                echo '<td><strong>' . htmlspecialchars($row['Field']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
                echo '<td>' . htmlspecialchars($row['Default'] ?? 'NULL') . '</td>';
                echo '<td>' . htmlspecialchars($extra) . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            
            if ($order_id_info) {
                $has_auto_increment = strpos($order_id_info['Extra'] ?? '', 'auto_increment') !== false;
                if ($has_auto_increment) {
                    echo '<div class="success">✅ Order_ID 欄位已開啟 AUTO_INCREMENT</div>';
                } else {
                    echo '<div class="error">';
                    echo '<h4>❌ 問題發現：Order_ID 欄位未開啟 AUTO_INCREMENT</h4>';
                    echo '<p><strong>這是導致錯誤的主要原因！</strong></p>';
                    echo '<p>請執行以下 SQL 語句來修復：</p>';
                    echo '<pre>ALTER TABLE `Order` MODIFY COLUMN `Order_ID` INT NOT NULL AUTO_INCREMENT;</pre>';
                    echo '<p>或者：</p>';
                    echo '<ol>';
                    echo '<li>打開 phpMyAdmin</li>';
                    echo '<li>選擇 <code>final_project_db</code> 資料庫</li>';
                    echo '<li>點選 <code>Order</code> 表</li>';
                    echo '<li>點選上方的 <strong>[結構] (Structure)</strong></li>';
                    echo '<li>找到 <code>Order_ID</code> 欄位，點選 <strong>[更改] (Change)</strong></li>';
                    echo '<li>勾選 <strong>[A_I] (AUTO_INCREMENT)</strong></li>';
                    echo '<li>點選 <strong>[儲存] (Save)</strong></li>';
                    echo '</ol>';
                    echo '</div>';
                }
            }
        } else {
            echo '<div class="error">無法讀取表結構：' . $conn->error . '</div>';
        }
        echo '</div>';
        
        // 檢查是否有 Order_ID = 0 的記錄
        echo '<div class="info">';
        echo '<h3>步驟 2: 檢查問題記錄</h3>';
        $zero_check = $conn->query("SELECT COUNT(*) as count FROM `Order` WHERE Order_ID = 0");
        if ($zero_check) {
            $zero_count = $zero_check->fetch_assoc()['count'];
            if ($zero_count > 0) {
                echo '<div class="error">';
                echo '<h4>⚠️ 發現 ' . $zero_count . ' 筆 Order_ID = 0 的記錄</h4>';
                echo '<p>這些記錄可能導致主鍵衝突。建議刪除：</p>';
                echo '<pre>DELETE FROM `Order` WHERE Order_ID = 0;</pre>';
                if (isset($_GET['clean']) && $_GET['clean'] === 'yes') {
                    $clean_result = $conn->query("DELETE FROM `Order` WHERE Order_ID = 0");
                    if ($clean_result) {
                        echo '<div class="success">✅ 已刪除問題記錄</div>';
                    } else {
                        echo '<div class="error">❌ 刪除失敗：' . $conn->error . '</div>';
                    }
                } else {
                    echo '<a href="?clean=yes" class="btn btn-danger" onclick="return confirm(\'確定要刪除 Order_ID = 0 的記錄嗎？\')">刪除問題記錄</a>';
                }
                echo '</div>';
            } else {
                echo '<div class="success">✅ 沒有發現 Order_ID = 0 的記錄</div>';
            }
        }
        echo '</div>';
        
        // 測試插入
        if (isset($_GET['test']) && $_GET['test'] === 'insert') {
            echo '<div class="info">';
            echo '<h3>步驟 3: 測試插入訂單</h3>';
            
            // 先獲取一個測試用的 Customer_ID
            $test_customer = $conn->query("SELECT CustomerID FROM Customer LIMIT 1");
            if ($test_customer && $test_customer->num_rows > 0) {
                $test_customer_id = $test_customer->fetch_assoc()['CustomerID'];
                
                // 創建測試地址
                $test_address_sql = "INSERT INTO AddressBook (CustomerID, ReceiverName, Phone, Address, PaymentMethod) VALUES (
                    $test_customer_id,
                    '測試收件人',
                    '0912345678',
                    '測試地址',
                    'Credit Card'
                )";
                
                if ($conn->query($test_address_sql)) {
                    $test_address_id = $conn->insert_id;
                    echo '<div class="success">✅ 測試地址創建成功，Address_ID = ' . $test_address_id . '</div>';
                    
                    // 測試插入訂單
                    $test_order_sql = "INSERT INTO `Order` (Customer_ID, Address_ID, OrderDate, PaymentMethod, Status) VALUES (
                        $test_customer_id,
                        $test_address_id,
                        NOW(),
                        'Credit Card',
                        'Pending'
                    )";
                    
                    echo '<p>執行 SQL：</p>';
                    echo '<pre>' . htmlspecialchars($test_order_sql) . '</pre>';
                    
                    if ($conn->query($test_order_sql)) {
                        $test_order_id = $conn->insert_id;
                        echo '<div class="success">';
                        echo '<h4>✅ 測試插入成功！</h4>';
                        echo '<p>新產生的 Order_ID = <strong>' . $test_order_id . '</strong></p>';
                        echo '</div>';
                        
                        // 清理測試資料
                        $conn->query("DELETE FROM `Order` WHERE Order_ID = $test_order_id");
                        $conn->query("DELETE FROM AddressBook WHERE AddressID = $test_address_id");
                        echo '<div class="info">已清理測試資料</div>';
                    } else {
                        echo '<div class="error">';
                        echo '<h4>❌ 測試插入失敗</h4>';
                        echo '<p>錯誤訊息：' . htmlspecialchars($conn->error) . '</p>';
                        echo '<p>錯誤代碼：' . $conn->errno . '</p>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="error">創建測試地址失敗：' . $conn->error . '</div>';
                }
            } else {
                echo '<div class="error">找不到測試用的客戶資料，請先創建至少一個客戶</div>';
            }
            echo '</div>';
        } else {
            echo '<div class="info">';
            echo '<h3>步驟 3: 測試插入功能</h3>';
            echo '<p>點選下方按鈕來測試訂單插入功能：</p>';
            echo '<a href="?test=insert" class="btn">執行測試插入</a>';
            echo '</div>';
        }
        
        // 顯示當前 AUTO_INCREMENT 值
        $auto_inc_info = $conn->query("SHOW TABLE STATUS LIKE 'Order'");
        if ($auto_inc_info) {
            $status = $auto_inc_info->fetch_assoc();
            $next_id = $status['Auto_increment'] ?? 'NULL';
            echo '<div class="info">';
            echo '<h3>當前狀態</h3>';
            echo '<p>下一個自動產生的 Order_ID 將是：<code>' . $next_id . '</code></p>';
            echo '</div>';
        }
        
        $conn->close();
        ?>
        
        <div class="info">
            <h3>📝 下一步</h3>
            <ol>
                <li>如果 Order_ID 未開啟 AUTO_INCREMENT，請先修復資料庫</li>
                <li>如果有 Order_ID = 0 的記錄，請先刪除</li>
                <li>執行測試插入，確認功能正常</li>
                <li>回到 <a href="checkout.php">checkout.php</a> 再次嘗試提交訂單</li>
            </ol>
        </div>
    </div>
</body>
</html>

