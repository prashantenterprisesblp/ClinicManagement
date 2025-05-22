<?php
require_once '../config/database.php';

$error_message = '';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

if ($_POST) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password, role, full_name, is_active FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password']) && $user['is_active']) {
                // Log user activity
                log_user_activity($pdo, $user['id'], get_client_ip(), $_SERVER['HTTP_USER_AGENT'] ?? '');
                
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $error_message = 'Invalid username or password.';
                // Log failed attempt
                error_log("Failed login attempt for username: $username from IP: " . get_client_ip());
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = 'An error occurred during login. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Clinic Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }

        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .login-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            opacity: 0.8;
            font-size: 0.9rem;
        }

        .login-form {
            padding: 2rem;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
            text-align: center;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .input-container {
            position: relative;
        }

        .input-container i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            z-index: 1;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .back-link {
            text-align: center;
            margin-top: 1rem;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s;
        }

        .back-link a:hover {
            color: #764ba2;
        }

        .security-notice {
            background: #e7f3ff;
            color: #004085;
            padding: 1rem;
            border-radius: 5px;
            font-size: 0.85rem;
            text-align: center;
            margin-top: 1rem;
            border: 1px solid #b8daff;
        }

        .security-notice i {
            margin-right: 0.5rem;
        }

        @media (max-width: 480px) {
            .login-container {
                margin: 1rem;
            }
            
            .login-header {
                padding: 1.5rem;
            }
            
            .login-form {
                padding: 1.5rem;
            }
        }

        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: 9999;
        }

        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-shield-alt"></i>
            <h1>Admin Access</h1>
            <p>Clinic Management System</p>
        </div>
        
        <div class="login-form">
            <?php if ($error_message): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-container">
                        <i class="fas fa-user"></i>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                               required 
                               autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-container">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required 
                               autocomplete="current-password">
                    </div>
                </div>

                <button type="submit" class="login-btn">
                    <i class="fas fa-sign-in-alt"></i> Login
                </button>
            </form>

            <div class="security-notice">
                <i class="fas fa-info-circle"></i>
                This is a secure area. All access attempts are logged.
            </div>
        </div>

        <div class="back-link">
            <a href="../index.php">
                <i class="fas fa-arrow-left"></i> Back to Website
            </a>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            // Show loading
            document.getElementById('loading').style.display = 'block';
            
            // Basic validation
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                e.preventDefault();
                document.getElementById('loading').style.display = 'none';
                alert('Please enter both username and password.');
                return false;
            }
        });

        // Hide loading on page load
        window.addEventListener('load', function() {
            document.getElementById('loading').style.display = 'none';
        });

        // Auto-focus username field
        document.getElementById('username').focus();

        // Prevent multiple submissions
        let submitted = false;
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (submitted) {
                e.preventDefault();
                return false;
            }
            submitted = true;
        });
    </script>
</body>
</html>
