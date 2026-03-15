<?php
require_once 'config/session.php'; // Include session config FIRST
require_once 'config/database.php';
require_once 'includes/functions.php';

$error = '';
$success = '';

$prefill_email = trim($_GET['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($code) || empty($password) || empty($confirm)) {
        $error = 'Please fill in all fields.';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT pr.*, u.id AS user_id
                FROM password_resets pr
                JOIN users u ON pr.user_id = u.id
                WHERE pr.email = ? 
                  AND pr.code = ? 
                  AND pr.used = 0 
                  AND pr.expires_at > NOW()
                ORDER BY pr.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$email, $code]);
            $reset = $stmt->fetch();

            if (!$reset) {
                $error = 'Invalid or expired verification code.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$password_hash, $reset['user_id']]);

                $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
                $stmt->execute([$reset['id']]);

                $pdo->commit();

                $success = 'Password reset successful. You can now sign in.';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Unable to reset password right now. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - ServiceLink</title>
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
                        <h3 class="mb-0">
                            <i class="fas fa-key me-2"></i>
                            Reset Password
                        </h3>
                        <p class="mb-0 opacity-75">Enter your email, verification code, and new password.</p>
                    </div>

                    <div class="auth-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-envelope"></i>
                                    </span>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="email"
                                        name="email"
                                        required
                                        value="<?php echo htmlspecialchars($_POST['email'] ?? $prefill_email); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="code" class="form-label">Verification Code</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-hashtag"></i>
                                    </span>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="code"
                                        name="code"
                                        required
                                        maxlength="6"
                                        value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>">
                                </div>
                                <div class="form-text">Enter the 6-digit code sent to your email.</div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="password"
                                        name="password"
                                        required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="fas fa-lock"></i>
                                    </span>
                                    <input
                                        type="password"
                                        class="form-control"
                                        id="confirm_password"
                                        name="confirm_password"
                                        required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success w-100 mb-3">
                                <i class="fas fa-key me-2"></i>
                                Reset Password
                            </button>
                        </form>

                        <div class="text-center mb-2">
                            <a href="forgot-password.php" class="text-decoration-none">
                                Didn’t get a code? Resend
                            </a>
                        </div>

                        <div class="text-center">
                            <a href="login.php" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>
                                Back to Login
                            </a>
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