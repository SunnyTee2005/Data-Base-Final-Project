<?php
/**
 * 自動修復 Order 表的 AUTO_INCREMENT 問題
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
    <title>修復 Order 表</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { background: #d4edda; padding: 15px; border-left: 4px solid #28a745; margin: 10px 0; }
        .error { background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 10px 0; }
        .info { background: #d1ecf1; padding: 15px; border-left: 4px solid #17a2b8; margin: 10px 0; }
        .warning { background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin: 5px; }
        .btn:hover { background: #0056b3; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 自動修復 Order 表</h1>
        
        <?php
        if (isset($_GET['fix']) && $_GET['fix'] === 'yes') {
            echo '<div class="info">';
            echo '<h3>開始修復...</h3>';
            
            // 步驟 1: 刪除 Order_ID = 0 的記錄
            echo '<h4>步驟 1: 清理問題記錄</h4>';
            $clean_result = $conn->query("DELETE FROM `Order` WHERE Order_ID = 0");
            if ($clean_result) {
                $affected = $conn->affected_rows;
                echo '<div class="success">✅ 已刪除 ' . $affected . ' 筆 Order_ID = 0 的記錄</div>';
            } else {
                echo '<div class="warning">⚠️ 刪除記錄時發生錯誤（可能沒有問題記錄）：' . $conn->error . '</div>';
            }
            
            // 步驟 2: 檢查當前 AUTO_INCREMENT 狀態
            echo '<h4>步驟 2: 檢查 AUTO_INCREMENT 狀態</h4>';
            $structure = $conn->query("SHOW COLUMNS FROM `Order` WHERE Field = 'Order_ID'");
            if ($structure && $structure->num_rows > 0) {
                $col = $structure->fetch_assoc();
                $has_auto_increment = strpos($col['Extra'] ?? '', 'auto_increment') !== false;
                
                if ($has_auto_increment) {
                    echo '<div class="success">✅ Order_ID 已開啟 AUTO_INCREMENT</div>';
                } else {
                    echo '<div class="warning">⚠️ Order_ID 未開啟 AUTO_INCREMENT，正在修復...</div>';
                    
                    // 獲取當前最大 Order_ID
                    $max_id_result = $conn->query("SELECT MAX(Order_ID) as max_id FROM `Order`");
                    $max_id = 0;
                    if ($max_id_result && $max_id_result->num_rows > 0) {
                        $max_row = $max_id_result->fetch_assoc();
                        $max_id = intval($max_row['max_id'] ?? 0);
                    }
                    $next_id = max(1, $max_id + 1);
                    
                    // 嘗試修復 AUTO_INCREMENT
                    $fix_sql = "ALTER TABLE `Order` MODIFY COLUMN `Order_ID` INT NOT NULL AUTO_INCREMENT";
                    if ($conn->query($fix_sql)) {
                        echo '<div class="success">✅ 已成功開啟 AUTO_INCREMENT</div>';
                        
                        // 設置 AUTO_INCREMENT 起始值
                        if ($next_id > 1) {
                            $set_auto_sql = "ALTER TABLE `Order` AUTO_INCREMENT = $next_id";
                            if ($conn->query($set_auto_sql)) {
                                echo '<div class="success">✅ 已設置 AUTO_INCREMENT 起始值為 ' . $next_id . '</div>';
                            }
                        }
                    } else {
                        echo '<div class="error">❌ 修復失敗：' . $conn->error . '</div>';
                        echo '<p>請手動執行以下 SQL：</p>';
                        echo '<pre>' . htmlspecialchars($fix_sql) . ';</pre>';
                    }
                }
            }
            
            // 步驟 3: 驗證修復結果
            echo '<h4>步驟 3: 驗證修復結果</h4>';
            $verify = $conn->query("SHOW COLUMNS FROM `Order` WHERE Field = 'Order_ID'");
            if ($verify && $verify->num_rows > 0) {
                $col = $verify->fetch_assoc();
                $has_auto_increment = strpos($col['Extra'] ?? '', 'auto_increment') !== false;
                
                if ($has_auto_increment) {
                    $auto_inc_info = $conn->query("SHOW TABLE STATUS LIKE 'Order'");
                    if ($auto_inc_info) {
                        $status = $auto_inc_info->fetch_assoc();
                        $next_id = $status['Auto_increment'] ?? 'NULL';
                        echo '<div class="success">';
                        echo '<h4>✅ 修復完成！</h4>';
                        echo '<p>Order_ID 已成功開啟 AUTO_INCREMENT</p>';
                        echo '<p>下一個自動產生的 Order_ID 將是：<code>' . $next_id . '</code></p>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="error">❌ 修復未成功，請檢查資料庫權限或手動修復</div>';
                }
            }
            
            echo '</div>';
            echo '<div class="info">';
            echo '<h3>📝 下一步</h3>';
            echo '<p>修復完成後，請：</p>';
            echo '<ol>';
            echo '<li>回到 <a href="checkout.php">checkout.php</a> 嘗試提交訂單</li>';
            echo '<li>如果還有問題，請查看錯誤訊息中的詳細資訊</li>';
            echo '<li>也可以使用 <a href="test_order_insert.php">test_order_insert.php</a> 進行測試</li>';
            echo '</ol>';
            echo '</div>';
        } else {
            echo '<div class="info">';
            echo '<h3>這個工具會自動：</h3>';
            echo '<ol>';
            echo '<li>刪除 Order_ID = 0 的問題記錄</li>';
            echo '<li>檢查並修復 Order_ID 的 AUTO_INCREMENT 設定</li>';
            echo '<li>設置正確的 AUTO_INCREMENT 起始值</li>';
            echo '</ol>';
            echo '<p><strong>注意：</strong>此操作會修改資料庫結構，請確保已備份資料庫。</p>';
            echo '<a href="?fix=yes" class="btn btn-danger" onclick="return confirm(\'確定要執行修復嗎？建議先備份資料庫。\')">執行自動修復</a>';
            echo '</div>';
            
            // 顯示當前狀態
            echo '<div class="info">';
            echo '<h3>當前狀態</h3>';
            
            $structure = $conn->query("SHOW COLUMNS FROM `Order` WHERE Field = 'Order_ID'");
            if ($structure && $structure->num_rows > 0) {
                $col = $structure->fetch_assoc();
                $has_auto_increment = strpos($col['Extra'] ?? '', 'auto_increment') !== false;
                
                if ($has_auto_increment) {
                    echo '<p>✅ Order_ID 已開啟 AUTO_INCREMENT</p>';
                } else {
                    echo '<p>❌ Order_ID 未開啟 AUTO_INCREMENT（需要修復）</p>';
                }
            }
            
            $zero_check = $conn->query("SELECT COUNT(*) as count FROM `Order` WHERE Order_ID = 0");
            if ($zero_check) {
                $zero_count = $zero_check->fetch_assoc()['count'];
                if ($zero_count > 0) {
                    echo '<p>⚠️ 發現 ' . $zero_count . ' 筆 Order_ID = 0 的記錄（需要清理）</p>';
                } else {
                    echo '<p>✅ 沒有 Order_ID = 0 的問題記錄</p>';
                }
            }
            
            echo '</div>';
        }
        
        $conn->close();
        ?>
    </div>
</body>
</html>

