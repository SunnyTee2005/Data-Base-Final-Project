<?php
/**
 * 資料庫檢查工具
 * 用於檢查 Order 表的 AUTO_INCREMENT 設定
 * 
 * 使用方法：在瀏覽器中打開 http://localhost/final_project/check_database.php
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>資料庫檢查工具</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #333; }
        .section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #007bff; }
        .success { border-left-color: #28a745; background: #d4edda; }
        .error { border-left-color: #dc3545; background: #f8d7da; }
        .warning { border-left-color: #ffc107; background: #fff3cd; }
        code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #007bff; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 資料庫結構檢查工具</h1>
        
        <?php
        // 檢查 Order 表是否存在
        $table_check = $conn->query("SHOW TABLES LIKE 'Order'");
        if ($table_check && $table_check->num_rows > 0) {
            echo '<div class="section success">';
            echo '<h3>✅ Order 表存在</h3>';
            echo '</div>';
            
            // 檢查 Order 表的結構
            $structure = $conn->query("SHOW COLUMNS FROM `Order`");
            if ($structure) {
                echo '<div class="section">';
                echo '<h3>📋 Order 表結構</h3>';
                echo '<table>';
                echo '<tr><th>欄位名稱</th><th>類型</th><th>Null</th><th>Key</th><th>預設值</th><th>額外</th></tr>';
                
                $order_id_has_auto_increment = false;
                while ($row = $structure->fetch_assoc()) {
                    $extra = $row['Extra'] ?? '';
                    if ($row['Field'] === 'Order_ID' && strpos($extra, 'auto_increment') !== false) {
                        $order_id_has_auto_increment = true;
                    }
                    echo '<tr>';
                    echo '<td><strong>' . htmlspecialchars($row['Field']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($row['Type']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Null']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Key']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['Default'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($extra) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
                echo '</div>';
                
                // 檢查 AUTO_INCREMENT 狀態
                if ($order_id_has_auto_increment) {
                    echo '<div class="section success">';
                    echo '<h3>✅ Order_ID 欄位已開啟 AUTO_INCREMENT</h3>';
                    echo '<p>資料庫設定正確，問題可能出在 PHP 程式碼。</p>';
                    echo '</div>';
                } else {
                    echo '<div class="section error">';
                    echo '<h3>❌ Order_ID 欄位未開啟 AUTO_INCREMENT</h3>';
                    echo '<p><strong>這就是問題所在！</strong></p>';
                    echo '<p>請按照以下步驟修復：</p>';
                    echo '<ol>';
                    echo '<li>打開 phpMyAdmin</li>';
                    echo '<li>選擇 <code>final_project_db</code> 資料庫</li>';
                    echo '<li>點選 <code>Order</code> 表</li>';
                    echo '<li>點選上方的 <strong>[結構] (Structure)</strong> 標籤</li>';
                    echo '<li>找到 <code>Order_ID</code> 欄位，點選右側的 <strong>[更改] (Change)</strong></li>';
                    echo '<li>勾選 <strong>[A_I] (AUTO_INCREMENT)</strong> 核取方塊</li>';
                    echo '<li>點選 <strong>[儲存] (Save)</strong></li>';
                    echo '</ol>';
                    echo '</div>';
                }
                
                // 檢查當前 AUTO_INCREMENT 值
                $auto_increment_info = $conn->query("SHOW TABLE STATUS LIKE 'Order'");
                if ($auto_increment_info) {
                    $status = $auto_increment_info->fetch_assoc();
                    $next_auto_increment = $status['Auto_increment'] ?? 'NULL';
                    echo '<div class="section">';
                    echo '<h3>📊 當前 AUTO_INCREMENT 值</h3>';
                    echo '<p>下一個自動產生的 Order_ID 將是：<code>' . $next_auto_increment . '</code></p>';
                    echo '</div>';
                }
            }
            
            // 檢查是否有 Order_ID = 0 的記錄
            $zero_id_check = $conn->query("SELECT COUNT(*) as count FROM `Order` WHERE Order_ID = 0");
            if ($zero_id_check) {
                $zero_count = $zero_id_check->fetch_assoc()['count'];
                if ($zero_count > 0) {
                    echo '<div class="section warning">';
                    echo '<h3>⚠️ 發現問題記錄</h3>';
                    echo '<p>資料庫中存在 <code>Order_ID = 0</code> 的記錄（共 ' . $zero_count . ' 筆）。</p>';
                    echo '<p>這可能是導致錯誤的原因。建議刪除這些記錄：</p>';
                    echo '<pre>DELETE FROM `Order` WHERE Order_ID = 0;</pre>';
                    echo '</div>';
                }
            }
            
        } else {
            echo '<div class="section error">';
            echo '<h3>❌ Order 表不存在</h3>';
            echo '<p>請檢查資料庫名稱是否正確。</p>';
            echo '</div>';
        }
        
        // 檢查其他相關表
        echo '<div class="section">';
        echo '<h3>📋 相關表檢查</h3>';
        $tables = ['Customer', 'AddressBook', 'OrderItem', 'SKU', 'Product'];
        echo '<ul>';
        foreach ($tables as $table) {
            $check = $conn->query("SHOW TABLES LIKE '$table'");
            if ($check && $check->num_rows > 0) {
                echo '<li>✅ <code>' . $table . '</code> 表存在</li>';
            } else {
                echo '<li>❌ <code>' . $table . '</code> 表不存在</li>';
            }
        }
        echo '</ul>';
        echo '</div>';
        
        $conn->close();
        ?>
        
        <div class="section">
            <h3>💡 下一步</h3>
            <p>如果 Order_ID 已開啟 AUTO_INCREMENT，但問題仍然存在，請：</p>
            <ol>
                <li>檢查 <code>checkout.php</code> 中的 INSERT 語句是否包含 <code>Order_ID</code></li>
                <li>確認使用 <code>$conn->insert_id</code> 獲取新產生的 ID</li>
                <li>查看錯誤訊息中的詳細資訊</li>
            </ol>
        </div>
    </div>
</body>
</html>

