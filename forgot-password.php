<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
           
            $stmt = $pdo->prepare("SELECT id, first_name, email FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // Generic success message
            $success = 'If that email is registered, a verification code has been sent.';

            if ($user) {
              
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS password_resets (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        email VARCHAR(255) NOT NULL,
                        code VARCHAR(10) NOT NULL,
                        created_at DATETIME NOT NULL,
                        expires_at DATETIME NOT NULL,
                        used TINYINT(1) NOT NULL DEFAULT 0
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");

                // Invalidate previous codes
                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE email = ? AND used = 0");
                $stmt->execute([$email]);

                // Generate 6-digit code and expiry
                $code = random_int(100000, 999999);
                $expires_at = date('Y-m-d H:i:s', time() + 15 * 60);

                // Insert new reset code
                $stmt = $pdo->prepare("
                    INSERT INTO password_resets (user_id, email, code, created_at, expires_at)
                    VALUES (?, ?, ?, NOW(), ?)
                ");
                $stmt->execute([$user['id'], $user['email'], $code, $expires_at]);

                // Prepare email
                $subject = 'ServiceLink Password Reset Code';
                $message = "
                    <p>Hi " . htmlspecialchars($user['first_name']) . ",</p>
                    <p>You requested to reset your ServiceLink password.</p>
                    <p><strong>Your verification code is: {$code}</strong></p>
                    <p>This code will expire in 15 minutes.</p>
                    <p>If you did not request this, ignore this email.</p>
                ";

                // Send email using your existing function
                sendEmailNotification($user['email'], $subject, $message);
            }
        } catch (PDOException $e) {
            if (!$success) {
                $error = 'Unable to process your request right now. Please try again later.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body class="auth-container">
    <div class="brand-logo-auth" style="position: absolute; top: 1.5rem; left: 2rem; font-weight: 700; font-size: 1.25rem; color: #101828; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-cubes fa-lg" style="color: #5a4ad1;"></i>
        <a href="login.php" style="color: inherit; text-decoration: none;">ServiceLink</a>
    </div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card auth-card">
                    <div class="auth-header">
                        <h3 class="mb-0"><i class="fas fa-unlock-alt me-2"></i>Forgot Password</h3>
                        <p class="mb-0 opacity-75">Enter your email to receive a verification code.</p>
                    </div>
                    <div class="auth-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required
                                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                </div>
                            </div>
                            <button type="submit" class="btn btn-success w-100 mb-3">
                                <i class="fas fa-paper-plane me-2"></i>Send Code
                            </button>
                        </form>
                        <div class="text-center mb-2">
                            <a href="reset-password.php" class="text-decoration-none">Already have a code? Reset password</a>
                        </div>
                        <div class="text-center">
                            <a href="login.php" class="text-muted text-decoration-none"><i class="fas fa-arrow-left me-1"></i>Back to Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('email').focus();
    </script>
</body>

</html>