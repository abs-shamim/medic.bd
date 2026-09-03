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

<style>
    :root {
        --login-bg: #f6f8fa;
        --login-card: #ffffff;
        --login-card-soft: #f6f8fa;
        --login-border: #d0d7de;
        --login-text: #24292f;
        --login-muted: #57606a;
        --login-blue: #0969da;
        --login-blue-hover: #0550ae;
        --login-green: #1f883d;
        --login-green-hover: #1a7f37;
        --login-red-bg: rgba(207, 34, 46, 0.10);
        --login-red-border: rgba(207, 34, 46, 0.28);
        --login-red-text: #cf222e;
        --login-shadow: 0 24px 70px rgba(27, 31, 36, 0.10);
        --login-field-bg: #ffffff;
    }

    html[data-theme="dark"] {
        --login-bg: #0d1117;
        --login-card: #161b22;
        --login-card-soft: #21262d;
        --login-border: #30363d;
        --login-text: #f0f6fc;
        --login-muted: #8b949e;
        --login-blue: #2f81f7;
        --login-blue-hover: #1f6feb;
        --login-green: #238636;
        --login-green-hover: #2ea043;
        --login-red-bg: rgba(248, 81, 73, 0.12);
        --login-red-border: rgba(248, 81, 73, 0.35);
        --login-red-text: #ff7b72;
        --login-shadow: 0 24px 70px rgba(0, 0, 0, 0.45);
        --login-field-bg: #0d1117;
    }

    body {
        background:
            radial-gradient(circle at top left, rgba(47, 129, 247, 0.18), transparent 34%),
            radial-gradient(circle at bottom right, rgba(35, 134, 54, 0.12), transparent 32%),
            var(--login-bg) !important;
        color: var(--login-text);
    }

    html[data-theme="light"] body {
        background:
            radial-gradient(circle at top left, rgba(9, 105, 218, 0.12), transparent 34%),
            radial-gradient(circle at bottom right, rgba(31, 136, 61, 0.10), transparent 32%),
            var(--login-bg) !important;
    }

    .premium-login-wrapper {
        min-height: calc(100vh - 160px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 60px 16px;
        color: var(--login-text);
    }

    .premium-login-shell {
        width: 100%;
        max-width: 1100px;
        display: grid;
        grid-template-columns: 1.05fr 0.95fr;
        gap: 28px;
        align-items: stretch;
    }

    .login-info-panel,
    .login-card-panel {
        border: 1px solid var(--login-border);
        background: color-mix(in srgb, var(--login-card) 92%, transparent);
        box-shadow: var(--login-shadow);
        backdrop-filter: blur(18px);
        border-radius: 22px;
        overflow: hidden;
    }

    .login-info-panel {
        padding: 38px;
        position: relative;
    }

    .login-brand-badge {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 8px 13px;
        border: 1px solid var(--login-border);
        background: var(--login-card-soft);
        border-radius: 999px;
        color: var(--login-muted);
        font-size: 13px;
        margin-bottom: 24px;
    }

    .login-brand-badge span {
        width: 9px;
        height: 9px;
        background: var(--login-green);
        border-radius: 50%;
        box-shadow: 0 0 0 5px rgba(35, 134, 54, 0.15);
    }

    .login-info-panel h1 {
        color: var(--login-text);
        font-size: clamp(32px, 4vw, 52px);
        line-height: 1.08;
        letter-spacing: -1.5px;
        margin: 0 0 16px;
        font-weight: 800;
    }

    .login-info-panel p {
        color: var(--login-muted);
        font-size: 16px;
        line-height: 1.75;
        max-width: 560px;
        margin: 0 0 26px;
    }

    .login-feature-list {
        display: grid;
        gap: 14px;
        margin-top: 30px;
    }

    .login-feature {
        display: flex;
        gap: 13px;
        align-items: flex-start;
        padding: 15px;
        border: 1px solid var(--login-border);
        background: color-mix(in srgb, var(--login-card-soft) 72%, transparent);
        border-radius: 16px;
    }

    .login-feature-icon {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: rgba(47, 129, 247, 0.15);
        color: var(--login-blue);
        font-size: 17px;
    }

    .login-feature strong {
        display: block;
        color: var(--login-text);
        font-size: 14px;
        margin-bottom: 4px;
    }

    .login-feature small {
        color: var(--login-muted);
        font-size: 13px;
        line-height: 1.55;
    }

    .login-card-panel {
        padding: 34px;
    }

    .login-card-header {
        text-align: center;
        margin-bottom: 26px;
    }

    .login-avatar {
        width: 66px;
        height: 66px;
        display: grid;
        place-items: center;
        margin: 0 auto 16px;
        border-radius: 50%;
        background: linear-gradient(145deg, var(--login-card-soft), var(--login-card));
        border: 1px solid var(--login-border);
        color: var(--login-blue);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);
    }

    .login-avatar svg {
        width: 32px;
        height: 32px;
    }

    .login-card-header h2 {
        color: var(--login-text);
        font-size: 26px;
        margin: 0 0 8px;
        letter-spacing: -0.5px;
        font-weight: 750;
    }

    .login-card-header p {
        color: var(--login-muted);
        margin: 0;
        font-size: 14px;
        line-height: 1.6;
    }

    .premium-alert {
        padding: 13px 14px;
        border-radius: 12px;
        margin-bottom: 18px;
        background: var(--login-red-bg);
        border: 1px solid var(--login-red-border);
        color: var(--login-red-text);
        font-size: 14px;
        line-height: 1.5;
    }

    .premium-login-form {
        display: grid;
        gap: 16px;
    }

    .premium-field label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--login-text);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .premium-field input {
        width: 100%;
        height: 48px;
        border: 1px solid var(--login-border);
        border-radius: 12px;
        background: var(--login-field-bg);
        color: var(--login-text);
        outline: none;
        padding: 0 14px;
        font-size: 15px;
        transition: 0.2s ease;
        box-sizing: border-box;
    }

    .premium-field input::placeholder {
        color: var(--login-muted);
        opacity: 0.8;
    }

    .premium-field input:focus {
        border-color: var(--login-blue);
        box-shadow: 0 0 0 4px rgba(47, 129, 247, 0.18);
    }

    .password-wrap {
        position: relative;
    }

    .password-wrap input {
        padding-right: 86px;
    }

    .toggle-password {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        border: 0;
        background: transparent;
        color: var(--login-blue);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        padding: 8px 10px;
        border-radius: 9px;
    }

    .toggle-password:hover {
        background: rgba(47, 129, 247, 0.12);
    }

    .login-options {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-top: 2px;
        color: var(--login-muted);
        font-size: 13px;
    }

    .remember-box {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        user-select: none;
    }

    .remember-box input {
        width: 16px;
        height: 16px;
        accent-color: var(--login-blue);
    }

    .premium-login-btn {
        width: 100%;
        height: 48px;
        border: 0;
        border-radius: 12px;
        background: var(--login-green);
        color: #ffffff;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        transition: 0.2s ease;
        margin-top: 4px;
    }

    .premium-login-btn:hover {
        background: var(--login-green-hover);
        transform: translateY(-1px);
    }

    .premium-login-btn:active {
        transform: translateY(0);
    }

    .login-divider {
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--login-muted);
        font-size: 13px;
        margin: 8px 0;
    }

    .login-divider::before,
    .login-divider::after {
        content: "";
        height: 1px;
        flex: 1;
        background: var(--login-border);
    }

    .create-account-btn {
        width: 100%;
        min-height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--login-border);
        border-radius: 12px;
        background: var(--login-card-soft);
        color: var(--login-text);
        font-weight: 650;
        font-size: 15px;
        text-decoration: none;
        transition: 0.2s ease;
        box-sizing: border-box;
    }

    .create-account-btn:hover {
        border-color: var(--login-muted);
        background: color-mix(in srgb, var(--login-card-soft) 86%, var(--login-blue));
        color: var(--login-text);
        text-decoration: none;
    }

    .login-security-note {
        margin-top: 18px;
        padding: 13px 14px;
        border: 1px solid rgba(47, 129, 247, 0.26);
        background: rgba(47, 129, 247, 0.08);
        color: var(--login-muted);
        border-radius: 13px;
        font-size: 13px;
        line-height: 1.6;
    }

    .login-security-note strong {
        color: var(--login-blue);
    }

    @media (max-width: 900px) {
        .premium-login-shell {
            grid-template-columns: 1fr;
            max-width: 560px;
        }

        .login-info-panel {
            padding: 28px;
        }

        .login-card-panel {
            padding: 26px;
        }
    }

    @media (max-width: 480px) {
        .premium-login-wrapper {
            padding: 36px 12px;
        }

        .login-info-panel,
        .login-card-panel {
            border-radius: 18px;
        }

        .login-info-panel {
            padding: 24px;
        }

        .login-card-panel {
            padding: 22px;
        }

        .login-options {
            align-items: flex-start;
            flex-direction: column;
        }
    }
</style>

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
                            required
                        >

                        <button type="button" class="toggle-password" onclick="togglePassword()">
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

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.querySelector('.toggle-password');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleBtn.textContent = 'Hide';
        } else {
            passwordInput.type = 'password';
            toggleBtn.textContent = 'Show';
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>