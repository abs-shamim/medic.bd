<?php
require_once __DIR__ . '/../includes/functions.php';

if (is_admin()) {
    redirect('dashboard.php');
}

$error = '';

if (empty($_SESSION['admin_login_csrf'])) {
    $_SESSION['admin_login_csrf'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['admin_login_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['csrf_token'] ?? '');
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!hash_equals($csrf_token, $posted_token)) {
        $error = 'Security token mismatch. Please try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $logged_in = false;

        /*
        |--------------------------------------------------------------------------
        | New Moderator/Admin Login From Database
        |--------------------------------------------------------------------------
        */
        if (function_exists('table_exists') && table_exists('admin_users')) {
            try {
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM admin_users
                    WHERE email = :email
                    AND status = 'active'
                    LIMIT 1
                ");

                $stmt->execute([':email' => $email]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, (string)$admin['password'])) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_id'] = (int)$admin['id'];
                    $_SESSION['admin_name'] = (string)$admin['name'];
                    $_SESSION['admin_email'] = (string)$admin['email'];
                    $_SESSION['admin_role'] = (string)($admin['role'] ?? 'super_admin');

                    $permissions = [];

                    if (!empty($admin['permissions'])) {
                        $decoded = json_decode((string)$admin['permissions'], true);
                        $permissions = is_array($decoded) ? $decoded : [];
                    }

                    $_SESSION['admin_permissions'] = $permissions;

                    try {
                        $update = $pdo->prepare("
                            UPDATE admin_users
                            SET last_login_at = NOW()
                            WHERE id = :id
                            LIMIT 1
                        ");

                        $update->execute([':id' => (int)$admin['id']]);
                    } catch (Throwable $e) {
                        // Login should not fail because of last_login update.
                    }

                    if (function_exists('admin_log_activity')) {
                        admin_log_activity('admin.login', 'Admin logged in: ' . $admin['email']);
                    }

                    $logged_in = true;
                }
            } catch (Throwable $e) {
                $logged_in = false;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Old Constant Based Login Fallback
        |--------------------------------------------------------------------------
        | This keeps your old ADMIN_EMAIL / ADMIN_PASSWORD login working.
        |--------------------------------------------------------------------------
        */
        if (!$logged_in && defined('ADMIN_EMAIL') && defined('ADMIN_PASSWORD')) {
            if ($email === ADMIN_EMAIL && $password === ADMIN_PASSWORD) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = 1;
                $_SESSION['admin_name'] = 'Administrator';
                $_SESSION['admin_email'] = ADMIN_EMAIL;
                $_SESSION['admin_role'] = 'super_admin';
                $_SESSION['admin_permissions'] = [];

                $logged_in = true;
            }
        }

        if ($logged_in) {
            redirect('dashboard.php');
        }

        $error = 'Invalid admin login or account is blocked.';
    }
}

$site_name = defined('APP_NAME') ? APP_NAME : 'Admin Panel';

if (function_exists('get_site_setting')) {
    $site_name = get_site_setting('site_name', $site_name);
}
?>
<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">

  <title>Admin Login | <?= e($site_name) ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Bengali:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --login-bg: #f6f8fa;
      --login-card: #ffffff;
      --login-border: #d0d7de;
      --login-border-soft: #d8dee4;
      --login-text: #24292f;
      --login-muted: #57606a;
      --login-blue: #0969da;
      --login-green: #2da44e;
      --login-green-dark: #1f883d;
      --login-red: #cf222e;
      --login-red-bg: #ffebe9;
      --login-shadow: 0 8px 24px rgba(140, 149, 159, 0.20);
    }

    * {
      box-sizing: border-box;
    }

    body.admin-login-body {
      margin: 0;
      min-height: 100vh;
      color: var(--login-text);
      background:
        radial-gradient(circle at top left, rgba(9, 105, 218, 0.10), transparent 32%),
        radial-gradient(circle at bottom right, rgba(45, 164, 78, 0.12), transparent 30%),
        var(--login-bg);
      font-family: "Inter", "Noto Sans Bengali", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }

    .login-shell {
      width: 100%;
      max-width: 1080px;
      display: grid;
      grid-template-columns: 1.1fr 430px;
      gap: 24px;
      align-items: center;
    }

    .login-intro {
      padding: 28px;
    }

    .login-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 30px;
      padding: 5px 11px;
      border-radius: 999px;
      background: #dafbe1;
      color: #1a7f37;
      border: 1px solid rgba(26, 127, 55, 0.25);
      font-size: 13px;
      font-weight: 700;
      margin-bottom: 18px;
    }

    .login-badge::before {
      content: "";
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #1a7f37;
    }

    .login-intro h1 {
      margin: 0 0 14px;
      color: var(--login-text);
      font-size: clamp(34px, 5vw, 58px);
      line-height: 1.02;
      letter-spacing: -0.06em;
      font-weight: 800;
    }

    .login-intro p {
      max-width: 560px;
      margin: 0;
      color: var(--login-muted);
      font-size: 16px;
      line-height: 1.75;
      font-weight: 500;
    }

    .login-feature-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(180px, 1fr));
      gap: 12px;
      margin-top: 28px;
      max-width: 620px;
    }

    .login-feature {
      padding: 14px;
      border-radius: 10px;
      border: 1px solid var(--login-border);
      background: rgba(255, 255, 255, 0.72);
      box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
      backdrop-filter: blur(10px);
    }

    .login-feature strong {
      display: block;
      color: var(--login-text);
      font-size: 14px;
      margin-bottom: 5px;
      font-weight: 700;
    }

    .login-feature span {
      display: block;
      color: var(--login-muted);
      font-size: 13px;
      line-height: 1.5;
    }

    .login-card {
      width: 100%;
      background: var(--login-card);
      border: 1px solid var(--login-border);
      border-radius: 12px;
      box-shadow: var(--login-shadow);
      overflow: hidden;
    }

    .login-card-head {
      padding: 22px;
      border-bottom: 1px solid var(--login-border);
      background: #f6f8fa;
      text-align: center;
    }

    .admin-login-logo {
      width: 58px;
      height: 58px;
      margin: 0 auto 14px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      color: #ffffff;
      background: linear-gradient(135deg, #2da44e, #0969da);
      font-size: 24px;
      font-weight: 800;
      letter-spacing: -0.04em;
      box-shadow: 0 8px 24px rgba(9, 105, 218, 0.18);
    }

    .login-card h2 {
      margin: 0 0 7px;
      color: var(--login-text);
      font-size: 22px;
      font-weight: 800;
      letter-spacing: -0.03em;
    }

    .login-subtitle {
      margin: 0;
      color: var(--login-muted);
      font-size: 14px;
      line-height: 1.6;
    }

    .login-card-body {
      padding: 22px;
    }

    .alert-error {
      padding: 12px 13px;
      border-radius: 8px;
      background: var(--login-red-bg);
      border: 1px solid rgba(207, 34, 46, 0.25);
      color: var(--login-red);
      font-size: 14px;
      line-height: 1.5;
      margin-bottom: 14px;
      font-weight: 600;
    }

    .form-box {
      display: grid;
      gap: 13px;
    }

    .login-field {
      display: grid;
      gap: 7px;
    }

    .login-field label {
      color: var(--login-text);
      font-size: 13px;
      font-weight: 700;
    }

    .login-input-wrap {
      position: relative;
    }

    .login-input-wrap input {
      width: 100%;
      min-height: 44px;
      padding: 10px 12px;
      border: 1px solid var(--login-border);
      border-radius: 8px;
      outline: none;
      color: var(--login-text);
      background: #ffffff;
      font-size: 15px;
      font-family: inherit;
      transition: 0.14s ease;
    }

    .login-input-wrap input:focus {
      border-color: var(--login-blue);
      box-shadow: 0 0 0 3px rgba(9, 105, 218, 0.15);
    }

    .login-password-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
    }

    .toggle-password {
      border: 0;
      background: transparent;
      color: var(--login-blue);
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      padding: 0;
    }

    .btn-login {
      width: 100%;
      min-height: 44px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: 1px solid rgba(27, 31, 36, 0.15);
      border-radius: 8px;
      background: var(--login-green);
      color: #ffffff;
      font-size: 15px;
      font-weight: 800;
      font-family: inherit;
      cursor: pointer;
      box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04);
      transition: 0.14s ease;
    }

    .btn-login:hover {
      background: var(--login-green-dark);
    }

    .login-footer-note {
      margin-top: 16px;
      padding: 12px;
      border-radius: 8px;
      border: 1px solid var(--login-border-soft);
      background: #f6f8fa;
      color: var(--login-muted);
      font-size: 13px;
      line-height: 1.6;
    }

    .login-footer-note strong {
      color: var(--login-text);
    }

    .login-card-foot {
      padding: 14px 22px;
      border-top: 1px solid var(--login-border);
      background: #ffffff;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }

    .login-card-foot span,
    .login-card-foot a {
      color: var(--login-muted);
      font-size: 13px;
      text-decoration: none;
      font-weight: 600;
    }

    .login-card-foot a:hover {
      color: var(--login-blue);
      text-decoration: underline;
    }

    @media(max-width: 960px) {
      .login-shell {
        max-width: 470px;
        grid-template-columns: 1fr;
      }

      .login-intro {
        display: none;
      }
    }

    @media(max-width: 520px) {
      body.admin-login-body {
        padding: 14px;
        align-items: flex-start;
      }

      .login-card-head,
      .login-card-body {
        padding: 18px;
      }

      .login-card-foot {
        padding: 14px 18px;
        flex-direction: column;
        align-items: flex-start;
      }
    }
  </style>
</head>

<body class="admin-login-body">

  <div class="login-shell">

    <section class="login-intro">
      <div class="login-badge">Secure admin workspace</div>

      <h1>Manage your healthcare directory with confidence.</h1>

      <p>
        Sign in to control doctors, hospitals, specialties, locations, reviews, appointments, users, claims, site settings and moderator permissions from one protected admin area.
      </p>

      <div class="login-feature-grid">
        <div class="login-feature">
          <strong>Directory Control</strong>
          <span>Manage doctors, hospitals, locations and specialties.</span>
        </div>

        <div class="login-feature">
          <strong>User Management</strong>
          <span>Handle users, profile claims and update requests.</span>
        </div>

        <div class="login-feature">
          <strong>Site Settings</strong>
          <span>Update website identity, SEO, contact and system settings.</span>
        </div>

        <div class="login-feature">
          <strong>Moderator Access</strong>
          <span>Use role based permissions for team members.</span>
        </div>
      </div>
    </section>

    <main class="login-card">
      <div class="login-card-head">
        <div class="admin-login-logo"><?= e(strtoupper(substr((string)$site_name, 0, 1))) ?></div>

        <h2>Admin Login</h2>
        <p class="login-subtitle">Sign in to continue to <?= e($site_name) ?> control panel.</p>
      </div>

      <div class="login-card-body">
        <?php if ($error): ?>
          <div class="alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="form-box" method="POST" autocomplete="off">
          <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

          <div class="login-field">
            <label for="email">Email address</label>
            <div class="login-input-wrap">
              <input
                id="email"
                type="email"
                name="email"
                placeholder="admin@example.com"
                value="<?= e((string)($_POST['email'] ?? '')) ?>"
                required
              >
            </div>
          </div>

          <div class="login-field">
            <div class="login-password-row">
              <label for="password">Password</label>
              <button type="button" class="toggle-password" id="togglePassword">Show</button>
            </div>

            <div class="login-input-wrap">
              <input
                id="password"
                type="password"
                name="password"
                placeholder="Enter your password"
                required
              >
            </div>
          </div>

          <button class="btn-login" type="submit">Login</button>
        </form>

        <div class="login-footer-note">
          <strong>Security note:</strong> Use your assigned admin or moderator account. Blocked accounts cannot access the admin panel.
        </div>
      </div>

      <div class="login-card-foot">
        <span>© <?= date('Y') ?> <?= e($site_name) ?></span>
        <a href="../index.php" target="_blank" rel="noopener">Back to website</a>
      </div>
    </main>

  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const toggleButton = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('password');

      if (!toggleButton || !passwordInput) {
        return;
      }

      toggleButton.addEventListener('click', function () {
        const isPassword = passwordInput.type === 'password';

        passwordInput.type = isPassword ? 'text' : 'password';
        toggleButton.textContent = isPassword ? 'Hide' : 'Show';
      });
    });
  </script>

</body>
</html>