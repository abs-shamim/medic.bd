<?php 
require_once __DIR__ . '/../includes/auth.php';

// If user already logged in, redirect to dashboard
if (user_is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!verify_csrf_token()) {
        $error = 'Security token mismatch. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([
            ':email' => $email
        ]);

        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $account &&
            password_verify($password, $account['password']) &&
            ($account['status'] ?? '') !== 'blocked'
        ) {
            $_SESSION['user_id'] = (int)$account['id'];
            $_SESSION['user_type'] = $account['user_type'] ?? '';
            $_SESSION['user_name'] = $account['name'] ?? '';

            redirect('dashboard.php');
        }

        $error = 'Invalid login details.';
    }
}

$page_title = 'User Login';
$user = null;

require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/user/assets/css/user-login.css">

<div class="premium-login-wrapper">
    <div class="premium-login-shell">

        <section class="login-info-panel">
            <div class="login-brand-badge">
                <span></span>
                Secure user access portal
            </div>

            <h1>Welcome back to your account.</h1>

            <p>
                Login to manage your profile, update your professional information, submit requests,
                and keep your doctor or hospital details accurate for visitors.
            </p>

            <div class="login-feature-list">
                <div class="login-feature">
                    <div class="login-feature-icon">✓</div>
                    <div>
                        <strong>Profile management</strong>
                        <small>Update your personal, hospital, chamber, and contact information from your dashboard.</small>
                    </div>
                </div>

                <div class="login-feature">
                    <div class="login-feature-icon">↗</div>
                    <div>
                        <strong>Request approval system</strong>
                        <small>Submit changes safely. Admin review keeps the platform clean and trusted.</small>
                    </div>
                </div>

                <div class="login-feature">
                    <div class="login-feature-icon">🔒</div>
                    <div>
                        <strong>Protected account area</strong>
                        <small>Your login session is secured and only verified users can access the dashboard.</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="login-card-panel">
            <div class="login-card-header">
                <div class="login-avatar" aria-hidden="true">
                    <svg viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"></path>
                        <path d="M14 13.5c0 1.25-2.69 2.5-6 2.5s-6-1.25-6-2.5S4.69 10 8 10s6 2.25 6 3.5Z"></path>
                    </svg>
                </div>

                <h2>User Login</h2>
                <p>Enter your account email and password to continue.</p>
            </div>

            <?php if ($error): ?>
                <div class="premium-alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="premium-login-form" autocomplete="on">
                <?= csrf_field() ?>

                <div class="premium-field">
                    <label for="email">Email address</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                        value="<?= e($_POST['email'] ?? '') ?>"
                        autocomplete="email"
                        required
                    >
                </div>

                <div class="premium-field">
                    <label for="password">Password</label>

                    <div class="password-wrap">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >

                        <button type="button" class="toggle-password">
                            Show
                        </button>
                    </div>
                </div>

                <div class="login-options">
                    <label class="remember-box">
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>
                </div>

                <button type="submit" class="premium-login-btn">
                    Login to Dashboard
                </button>

                <div class="login-divider">
                    New here?
                </div>

                <a class="create-account-btn" href="register.php">
                    Create New Account
                </a>
            </form>

            <div class="login-security-note">
                <strong>Security note:</strong>
                Use your registered email only. If your account is blocked or pending review, contact the site administrator.
            </div>
        </section>

    </div>
</div>

<script src="<?php echo BASE_URL; ?>/user/assets/js/login.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>