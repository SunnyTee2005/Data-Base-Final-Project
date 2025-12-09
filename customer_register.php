<?php
session_start();

// 如果已经登录，重定向到个人中心
if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
    header("Location: customer_account.php");
    exit;
}

// 处理注册表单提交
$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    $password_confirm = isset($_POST['password_confirm']) ? trim($_POST['password_confirm']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    
    // 验证输入
    if (empty($name) || empty($email) || empty($password) || empty($phone)) {
        $error = '請填寫所有必填欄位';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '請輸入有效的電子郵件地址';
    } elseif (strlen($password) < 6) {
        $error = '密碼長度至少需要6個字元';
    } elseif ($password !== $password_confirm) {
        $error = '兩次輸入的密碼不一致';
    } else {
        // 資料庫連線
        require_once 'db_connect.php';
        if ($conn->connect_error) die("連線失敗");
        $conn->set_charset("utf8mb4");
        
        // 檢查電子郵件是否已存在
        $email_escaped = $conn->real_escape_string($email);
        $check_sql = "SELECT CustomerID FROM Customer WHERE Email = '$email_escaped'";
        $check_result = $conn->query($check_sql);
        
        if ($check_result && $check_result->num_rows > 0) {
            $error = '此電子郵件已被註冊，請使用其他郵件或直接登入';
        } else {
            // 註冊新用戶
            $name_escaped = $conn->real_escape_string($name);
            $phone_escaped = $conn->real_escape_string($phone);
            // 使用 password_hash 加密密碼
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // 先獲取下一個可用的 CustomerID（如果沒有 AUTO_INCREMENT）
            $max_id_sql = "SELECT MAX(CustomerID) as max_id FROM Customer";
            $max_id_result = $conn->query($max_id_sql);
            $max_id_row = $max_id_result->fetch_assoc();
            $next_id = ($max_id_row['max_id'] ?? 0) + 1;
            
            // 確保 ID 不為 0
            if ($next_id == 0) $next_id = 1;
            
            // 插入新用戶（明確指定 CustomerID 以避免主鍵衝突）
            $insert_sql = "INSERT INTO Customer (CustomerID, Email, Password, Name, Phone) VALUES (
                $next_id,
                '$email_escaped',
                '$password_hash',
                '$name_escaped',
                '$phone_escaped'
            )";
            
            if ($conn->query($insert_sql)) {
                $success = true;
                // 自動登入
                $_SESSION['customer_logged_in'] = true;
                $_SESSION['customer_id'] = $next_id;
                $_SESSION['customer_name'] = $name;
                $_SESSION['customer_email'] = $email;
                
                // 延遲跳轉，讓用戶看到成功訊息
                header("refresh:2;url=customer_account.php");
            } else {
                $error = '註冊失敗：' . $conn->error . ' (錯誤代碼: ' . $conn->errno . ')';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>會員註冊 | LaptopMart</title>
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
            padding: 20px;
        }
        .register-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .register-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .register-body {
            padding: 40px 30px;
        }
        .form-control:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 0.2rem rgba(30, 58, 138, 0.25);
        }
        .btn-register {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .btn-register:hover {
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
        .password-strength {
            font-size: 0.85rem;
            margin-top: 5px;
        }
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #198754; }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="register-header">
            <i class="bi bi-person-plus" style="font-size: 3rem; margin-bottom: 15px;"></i>
            <h3 class="mb-0 fw-bold">會員註冊</h3>
            <p class="mb-0 mt-2 opacity-75">加入 LaptopMart，享受購物樂趣</p>
        </div>
        <div class="register-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>註冊成功！</strong> 正在為您跳轉到個人中心...
                </div>
            <?php else: ?>
                <form method="POST" action="" id="registerForm">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">姓名 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="name" name="name" 
                                   placeholder="請輸入您的姓名" required 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label fw-semibold">電子郵件 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="請輸入電子郵件" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-semibold">電話 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   placeholder="請輸入電話號碼" required
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label fw-semibold">密碼 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="請輸入密碼（至少6個字元）" required minlength="6">
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password_confirm" class="form-label fw-semibold">確認密碼 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                                   placeholder="請再次輸入密碼" required minlength="6">
                        </div>
                        <div class="text-danger small mt-1" id="passwordMatch"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-register w-100 text-white">
                        <i class="bi bi-person-plus me-2"></i>立即註冊
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="mt-4 text-center">
                <a href="customer_login.php" class="text-decoration-none">
                    已有帳號？<strong>立即登入</strong>
                </a>
            </div>
            
            <div class="mt-3 text-center">
                <a href="index.php" class="text-muted text-decoration-none small">
                    <i class="bi bi-arrow-left me-1"></i>返回首頁
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 密碼強度檢查
        const passwordInput = document.getElementById('password');
        const passwordConfirmInput = document.getElementById('password_confirm');
        const passwordStrength = document.getElementById('passwordStrength');
        const passwordMatch = document.getElementById('passwordMatch');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = '';
            let strengthClass = '';
            
            if (password.length === 0) {
                strength = '';
            } else if (password.length < 6) {
                strength = '密碼長度不足（至少6個字元）';
                strengthClass = 'strength-weak';
            } else if (password.length < 8) {
                strength = '密碼強度：弱';
                strengthClass = 'strength-weak';
            } else if (!/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
                strength = '密碼強度：中（建議包含字母和數字）';
                strengthClass = 'strength-medium';
            } else {
                strength = '密碼強度：強';
                strengthClass = 'strength-strong';
            }
            
            passwordStrength.textContent = strength;
            passwordStrength.className = 'password-strength ' + strengthClass;
        });
        
        // 密碼確認檢查
        passwordConfirmInput.addEventListener('input', function() {
            if (this.value !== passwordInput.value) {
                passwordMatch.textContent = '密碼不一致';
            } else {
                passwordMatch.textContent = '';
            }
        });
        
        // 表單驗證
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if (passwordInput.value !== passwordConfirmInput.value) {
                e.preventDefault();
                passwordMatch.textContent = '密碼不一致，請重新輸入';
                passwordConfirmInput.focus();
            }
        });
    </script>
</body>
</html>

