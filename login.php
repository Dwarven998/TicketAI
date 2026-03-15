<?php
require_once 'config/session.php';
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['user_role']) {
        case 'superadmin':
        case 'admin':
            header('Location: admin/dashboard.php');
            exit;
        case 'department_admin':
            header('Location: department/dashboard.php');
            exit;
        case 'staff':
            header('Location: staff/dashboard.php');
            exit;
        default:
            session_unset();
            session_destroy();
            break;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, password_hash, role, department_id, is_active FROM users WHERE email = ? AND is_active = 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (!in_array($user['role'], ['superadmin', 'admin', 'department_admin', 'staff'], true)) {
                    $error = 'Access denied. This system is only for employees.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['department_id'] = $user['department_id'];

                    $stmt = $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$user['id']]);

                    switch ($user['role']) {
                        case 'superadmin':
                        case 'admin':
                            header('Location: admin/dashboard.php');
                            break;
                        case 'department_admin':
                            header('Location: department/dashboard.php');
                            break;
                        case 'staff':
                            header('Location: staff/dashboard.php');
                            break;
                    }
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ServiceLink</title>
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/green.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --brand-purple: #10b981;
            --brand-purple-light: #34d399;
            --brand-purple-dark: #059669;
            --text-main: #101828;
            --text-muted: #667085;
            --border-color: #eaecf0;
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.7);
            --glass-shadow: 0 12px 40px rgba(0, 0, 0, 0.06);
        }

        body,
        html {
            height: 100%;
            font-family: 'Inter', sans-serif;
            background: #f8f9fc;
            overflow-x: hidden;
            color: var(--text-main);
        }

        .splash-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(135deg, var(--brand-purple) 0%, var(--brand-purple-dark) 100%);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.6s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.6s;
        }

        .splash-screen.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .splash-content {
            text-align: center;
        }

        .splash-icon {
            animation: pulse 2s infinite ease-in-out;
        }

        .splash-loader {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, 0.2);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .split-screen {
            min-height: 100vh;
        }

        .login-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-right: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            padding: 4rem;
            z-index: 10;
        }

        .brand-logo {
            position: absolute;
            top: 3rem;
            left: 4rem;
            font-weight: 700;
            font-size: 1.4rem;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-main);
            text-decoration: none;
            transition: transform 0.2s ease;
        }

        .brand-logo:hover {
            transform: scale(1.02);
            color: var(--text-main);
        }

        .brand-logo i {
            color: var(--brand-purple);
        }

        .login-header h1 {
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            letter-spacing: -1.2px;
            color: #101828;
        }

        .login-header p {
            color: var(--text-muted);
            margin-bottom: 3rem;
            font-size: 1.1rem;
        }

        .form-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #344054;
            margin-bottom: 8px;
        }

        .form-control {
            background: #fff;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            padding: 0.85rem 1.25rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--brand-purple);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            outline: none;
        }

        .custom-input-group {
            display: flex;
            background: #fff;
            border: 1.5px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .custom-input-group:focus-within {
            border-color: var(--brand-purple);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }

        .custom-input-group .form-control {
            border: none;
            flex-grow: 1;
        }

        .custom-input-group .btn-toggle {
            border: none;
            background: transparent;
            padding-right: 1.25rem;
            color: #98A2B3;
        }

        .btn-primary-purple {
            background: linear-gradient(135deg, var(--brand-purple) 0%, var(--brand-purple-dark) 100%);
            border: none;
            color: white;
            font-weight: 700;
            padding: 1rem;
            border-radius: 12px;
            width: 100%;
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-primary-purple:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.4);
            color: #fff;
        }

        .btn-primary-purple:active {
            transform: translateY(0);
        }

        .illustration-panel {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.6;
            mix-blend-mode: screen;
            pointer-events: none;
        }

        .blob-1 {
            top: -15%;
            left: -10%;
            width: 55vw;
            height: 55vw;
            background: #34d399;
        }

        .blob-2 {
            bottom: -20%;
            right: -5%;
            width: 50vw;
            height: 50vw;
            background: #14b8a6;
        }

        .illustration-content {
            text-align: center;
            color: #fff;
            z-index: 2;
            padding: 0 4rem;
        }

        .illustration-content i {
            margin-bottom: 2rem;
            filter: drop-shadow(0 10px 15px rgba(0, 0, 0, 0.2));
        }

        .illustration-content h3 {
            font-weight: 800;
            font-size: 2.25rem;
            letter-spacing: -0.5px;
            margin-bottom: 1rem;
        }

        .illustration-content p {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 400;
            line-height: 1.6;
        }

        .bg-theme {
            background: linear-gradient(135deg, var(--brand-purple) 0%, var(--brand-purple-dark) 100%) !important;
        }

        .btn-theme {
            background: linear-gradient(135deg, var(--brand-purple) 0%, var(--brand-purple-dark) 100%);
            border: none;
            color: white;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-theme:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(16, 185, 129, 0.3);
            color: #fff;
        }

        .btn-outline-theme {
            border: 2px solid var(--brand-purple);
            color: var(--brand-purple);
            background: transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-outline-theme:hover {
            background: rgba(16, 185, 129, 0.05);
            color: var(--brand-purple-dark);
            transform: translateY(-2px);
        }

        @media (max-width: 992px) {
            .brand-logo {
                left: 2rem;
                top: 2rem;
            }

            .login-panel {
                padding: 5rem 2rem 3rem;
            }

            .login-header h1 {
                font-size: 2.2rem;
            }
        }
    </style>
</head>

<body>

    <?php if (empty($error)): ?>
        <div id="splashScreen" class="splash-screen">
            <div class="splash-content">
                <img src="assets/images/white.png" alt="ServiceLink Logo" class="splash-logo mb-3"
                    style="width:180px;height:130px;animation:pulse 2s infinite ease-in-out;">
                <h1 class="text-white fw-bold mb-4 tracking-tight">ServiceLink</h1>
                <div class="splash-loader"></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="container-fluid p-0">
        <div class="row g-0 split-screen">

            <!-- User type modal (only shown on fresh page load, not on error) -->
            <?php if (empty($error)): ?>
                <div class="modal fade" id="userTypeModal" tabindex="-1" aria-labelledby="userTypeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-theme text-white border-0">
                                <h5 class="modal-title fw-bold" id="userTypeModalLabel">
                                    <i class="fas fa-question-circle me-2"></i>Who is using ServiceLink?
                                </h5>
                            </div>
                            <div class="modal-body p-4">
                                <p class="text-muted mb-4">Please select how you would like to access our service ticketing system:</p>
                                <div class="d-grid gap-3">
                                    <button type="button" class="btn btn-outline-theme btn-lg d-flex align-items-center justify-content-center" id="guestBtn">
                                        <i class="fas fa-user-circle me-3 fs-4"></i>
                                        <div class="text-start">
                                            <div class="fw-bold">Client User</div>
                                            <small class="text-muted">Submit a service request without logging in</small>
                                        </div>
                                    </button>
                                    <button type="button" class="btn btn-theme btn-lg d-flex align-items-center justify-content-center" id="registeredBtn">
                                        <i class="fas fa-user-shield me-3 fs-4"></i>
                                        <div class="text-start">
                                            <div class="fw-bold">Registered User</div>
                                            <small class="text-white-50">Login with your university account</small>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Login panel -->
            <div class="col-lg-6 login-panel" id="loginPanel"
                style="<?php echo !empty($error) ? '' : 'display:none;'; ?>">
                <a href="#" class="brand-logo">
                    <img src="assets/images/logo.png" alt="ServiceLink Logo" style="width:auto;height:60px;" id="brandLogoIcon">
                </a>
                <div class="row justify-content-center w-100 m-0">
                    <div class="col-md-10 col-lg-8 px-0">
                        <div class="login-header">
                            <h1>Welcome back</h1>
                            <p>Please enter your credentials to access the portal</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4 d-flex align-items-center">
                                <i class="fas fa-exclamation-circle me-3 fs-5"></i>
                                <div><?php echo htmlspecialchars($error); ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="" id="loginForm">
                            <div class="mb-4">
                                <label for="email" class="form-label text-uppercase ls-wide">Email address</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                    placeholder="name@company.com" required>
                            </div>

                            <div class="mb-4">
                                <label for="password" class="form-label text-uppercase ls-wide">Password</label>
                                <div class="custom-input-group">
                                    <input type="password" class="form-control" id="password" name="password"
                                        placeholder="••••••••" required>
                                    <button class="btn btn-toggle" type="button" id="togglePassword">
                                        <i class="fas fa-eye" id="toggleIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mb-5">
                                <div class="form-check custom-checkbox">
                                    <input class="form-check-input" type="checkbox" id="remember">
                                    <label class="form-check-label text-muted small" for="remember">Keep me signed in</label>
                                </div>
                                <a href="forgot-password.php" class="text-decoration-none small fw-bold"
                                    style="color:var(--brand-purple)">Reset password</a>
                            </div>

                            <button type="submit" class="btn btn-primary-purple py-3">Sign into Portal</button>

                            <div class="text-center mt-5 text-muted small">
                                Need access? <span class="fw-bold text-dark">Contact IT Administration</span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 d-none d-lg-flex illustration-panel">
                <div class="blob blob-1"></div>
                <div class="blob blob-2"></div>
                <div class="illustration-content">
                    <div class="mb-4 d-inline-block p-4 bg-white bg-opacity-10 rounded-5 backdrop-blur">
                        <img src="assets/images/green.png" alt="ServiceLink" style="height:150px;width:auto;">
                    </div>
                    <h3>ServiceLink</h3>
                    <p class="text-dark">Service Ticketing System for the University of Caloocan City (UCC)</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        const loginForm = document.getElementById('loginForm');
        const loginPanel = document.getElementById('loginPanel');
        const brandLogoIcon = document.getElementById('brandLogoIcon');

        <?php if (empty($error)): ?>
            // ── Fresh page load — show splash then modal ──────────────────────────
            const userTypeModal = new bootstrap.Modal(document.getElementById('userTypeModal'));

            document.getElementById('guestBtn').addEventListener('click', function() {
                window.location.href = 'client_submit.php';
            });

            document.getElementById('registeredBtn').addEventListener('click', function() {
                userTypeModal.hide();
                loginPanel.style.display = 'block';
            });

            window.addEventListener('load', function() {
                setTimeout(function() {
                    document.getElementById('splashScreen').classList.add('hidden');
                    setTimeout(() => userTypeModal.show(), 600);
                }, 800);
            });
        <?php else: ?>
            // ── Login error — panel already visible, no splash, no modal ─────────
            // Nothing needed — login panel is rendered visible by PHP directly
        <?php endif; ?>

        // ── Password toggle ───────────────────────────────────────────────────
        toggleBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            toggleIcon.classList.toggle('fa-eye');
            toggleIcon.classList.toggle('fa-eye-slash');
        });

        // ── Spin logo on submit ───────────────────────────────────────────────
        loginForm.addEventListener('submit', function() {
            brandLogoIcon.style.animation = 'spin 1s linear infinite';
        });
    </script>
</body>

</html>