<?php
session_start();

// 检查是否是后台登录请求
$is_admin_login = isset($_GET['admin']) || isset($_POST['admin']);

// 如果已经登录，根据登录类型重定向
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && $is_admin_login) {
    header("Location: admin_dashboard.php");
    exit;
}
// 如果已登入且不是登出請求，重定向到帳號頁面
if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true && !$is_admin_login && !isset($_GET['logout'])) {
    header("Location: customer_account.php");
    exit;
}

// 处理登录表单提交
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    if (empty($email) || empty($password)) {
        $error = '請輸入帳號和密碼';
    } else {
        // 資料庫連線
        require_once 'db_connect.php';
        if ($conn->connect_error) die("連線失敗");
        $conn->set_charset("utf8mb4");
        
        // 查詢客戶
        $email_escaped = $conn->real_escape_string($email);
        $sql = "SELECT * FROM Customer WHERE Email = '$email_escaped'";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $customer = $result->fetch_assoc();
            // 密碼驗證
            if ($customer['Password'] === $password || password_verify($password, $customer['Password'])) {
                $_SESSION['customer_logged_in'] = true;
                $_SESSION['customer_id'] = $customer['CustomerID'];
                $_SESSION['customer_name'] = $customer['Name'];
                $_SESSION['customer_email'] = $customer['Email'];
                
                // 檢查是否為管理員
                $admin_emails = ['admin@laptopmart.com', 'admin@admin.com'];
                $is_admin = in_array(strtolower($email), $admin_emails) || 
                           (isset($customer['IsAdmin']) && $customer['IsAdmin'] == 1);
                
                if ($is_admin) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $customer['Email'];
                }
                
                // 檢查是否有重定向參數或後台登入參數
                $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 
                           (isset($_GET['redirect']) ? $_GET['redirect'] : 
                           (($is_admin_login || $is_admin) ? 'admin_dashboard.php' : 'customer_account.php'));
                
                header("Location: $redirect");
                exit;
            } else {
                $error = '密碼錯誤';
            }
        } else {
            // 檢查是否是硬編碼的管理員帳號（向後兼容）
            if (($email === 'admin' || strtolower($email) === 'admin@laptopmart.com') && $password === 'admin123') {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = 'admin';
                $_SESSION['customer_logged_in'] = true; // 同時設置用戶登入
                $_SESSION['customer_id'] = 0; // 管理員可能沒有CustomerID
                $_SESSION['customer_name'] = '管理員';
                $_SESSION['customer_email'] = 'admin@laptopmart.com';
                
                $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : 
                           (isset($_GET['redirect']) ? $_GET['redirect'] : 'admin_dashboard.php');
                header("Location: $redirect");
                exit;
            }
            $error = '帳號不存在';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>會員登入 | LaptopMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Microsoft JhengHei', 'Segoe UI', sans-serif;
        }
        .login-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-control:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 0.2rem rgba(30, 58, 138, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(30, 58, 138, 0.4);
        }
        .input-group-text {
            background: #f8f9fa;
            border-right: none;
        }
        .form-control {
            border-left: none;
        }
        .form-control:focus {
            border-left: 1px solid #ced4da;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-<?php echo $is_admin_login ? 'shield-lock' : 'person-circle'; ?>" style="font-size: 3rem; margin-bottom: 15px;"></i>
            <h3 class="mb-0 fw-bold"><?php echo $is_admin_login ? '管理員登入' : '會員登入'; ?></h3>
            <p class="mb-0 mt-2 opacity-75"><?php echo $is_admin_login ? '後台管理系統' : 'LaptopMart'; ?></p>
        </div>
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <?php if ($is_admin_login): ?>
                    <input type="hidden" name="admin" value="1">
                <?php endif; ?>
                <?php if (isset($_GET['redirect'])): ?>
                    <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect']); ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label for="email" class="form-label fw-semibold"><?php echo $is_admin_login ? '管理員帳號' : '電子郵件'; ?></label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-<?php echo $is_admin_login ? 'person' : 'envelope'; ?>"></i></span>
                        <input type="<?php echo $is_admin_login ? 'text' : 'email'; ?>" class="form-control" id="email" name="email" 
                               placeholder="<?php echo $is_admin_login ? '請輸入管理員帳號（email或admin）' : '請輸入電子郵件'; ?>" required autofocus>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">密碼</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="請輸入密碼" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100 text-white">
                    <i class="bi bi-box-arrow-in-right me-2"></i>登入
                </button>
            </form>
            
            <?php if (!$is_admin_login): ?>
                <div class="mt-4 text-center">
                    <a href="customer_register.php" class="text-decoration-none">
                        還沒有帳號？<strong>立即註冊</strong>
                    </a>
                </div>
                
                <div class="mt-3 text-center">
                    <a href="customer_login.php?admin=1" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-shield-lock me-1"></i>管理員登入
                    </a>
                </div>
            <?php else: ?>
                <div class="mt-3 text-center">
                    <small class="text-muted">
                        <i class="bi bi-info-circle me-1"></i>
                        管理員帳號：admin / admin123 或 admin@laptopmart.com / admin123
                    </small>
                </div>
                
                <div class="mt-3 text-center">
                    <a href="customer_login.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-person-circle me-1"></i>一般會員登入
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="mt-3 text-center">
                <a href="index.php" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left me-1"></i>返回首頁
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

