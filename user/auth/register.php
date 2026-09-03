<?php
require_once __DIR__ . '/../includes/auth.php';

if (user_is_logged_in()) {
    redirect('dashboard.php');
}

/*
|--------------------------------------------------------------------------
| Auto Database Update for User Registration Fields
|--------------------------------------------------------------------------
| This will automatically add missing columns to users table.
|--------------------------------------------------------------------------
*/
function user_column_exists(PDO $pdo, string $column_name): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE :column_name");
        $stmt->execute([
            ':column_name' => $column_name
        ]);

        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return false;
    }
}

function ensure_user_registration_columns(PDO $pdo): void
{
    try {
        if (!user_column_exists($pdo, 'bmdc_number')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN bmdc_number VARCHAR(100) NULL AFTER status");
        }

        if (!user_column_exists($pdo, 'doctor_specialty')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN doctor_specialty VARCHAR(190) NULL AFTER bmdc_number");
        }

        if (!user_column_exists($pdo, 'authorized_person_name')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN authorized_person_name VARCHAR(190) NULL AFTER doctor_specialty");
        }

        if (!user_column_exists($pdo, 'hospital_type')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN hospital_type VARCHAR(150) NULL AFTER authorized_person_name");
        }

        if (!user_column_exists($pdo, 'hospital_address')) {
            $pdo->exec("ALTER TABLE users ADD COLUMN hospital_address TEXT NULL AFTER hospital_type");
        }

        $pdo->exec("
            ALTER TABLE users 
            MODIFY status ENUM('active','pending_verification','blocked') 
            NOT NULL DEFAULT 'pending_verification'
        ");
    } catch (PDOException $e) {
        /*
         * If hosting database user has no ALTER permission,
         * the page will continue loading.
         */
    }
}

ensure_user_registration_columns($pdo);

$error = '';
$success = '';

/*
|--------------------------------------------------------------------------
| Dynamic Specialty Options
|--------------------------------------------------------------------------
| Specialty comes from admin specialties table.
|--------------------------------------------------------------------------
*/
$specialty_options = [];

try {
    $stmt = $pdo->query("
        SELECT id, name, name_bn 
        FROM specialties 
        WHERE status = 'active' 
        ORDER BY name ASC
    ");

    $specialty_options = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $specialty_options = [];
}

/*
|--------------------------------------------------------------------------
| Fixed Hospital Type Options
|--------------------------------------------------------------------------
| Only these hospital types will show in registration page.
|--------------------------------------------------------------------------
*/
$hospital_type_options = [
    'Private Hospital',
    'Government Hospital',
    'Medical College Hospital',
    'Specialized Hospital',
    'General Hospital',
    'Diagnostic Center',
    'Clinic',
    'Eye Hospital',
    'Dental Hospital',
    'Cardiac Hospital',
    'Cancer Hospital',
    'Mother and Child Hospital',
    'Rehabilitation Center',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $user_type = trim($_POST['user_type'] ?? '');

    $bmdc_number = trim($_POST['bmdc_number'] ?? '');
    $doctor_specialty = trim($_POST['doctor_specialty'] ?? '');

    $authorized_person_name = trim($_POST['authorized_person_name'] ?? '');
    $hospital_type = trim($_POST['hospital_type'] ?? '');
    $hospital_address = trim($_POST['hospital_address'] ?? '');

    $password = (string)($_POST['password'] ?? '');
    $confirm_password = (string)($_POST['confirm_password'] ?? '');

    if (!verify_csrf_token()) {
        $error = 'Security token mismatch. Please try again.';
    } elseif (
        $name === '' ||
        $email === '' ||
        $phone === '' ||
        $user_type === '' ||
        $password === '' ||
        $confirm_password === ''
    ) {
        $error = 'All required fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($user_type, ['doctor', 'hospital_owner'], true)) {
        $error = 'Please select a valid account type.';
    } elseif ($user_type === 'doctor' && ($bmdc_number === '' || $doctor_specialty === '')) {
        $error = 'BMDC registration number and specialty are required for doctor account.';
    } elseif ($user_type === 'hospital_owner' && ($authorized_person_name === '' || $hospital_type === '' || $hospital_address === '')) {
        $error = 'Authorized person name, hospital type, and full address are required for hospital account.';
    } elseif ($user_type === 'hospital_owner' && !in_array($hospital_type, $hospital_type_options, true)) {
        $error = 'Please select a valid hospital type.';
    } elseif ($password !== $confirm_password) {
        $error = 'Password and confirm password do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([
            ':email' => $email
        ]);

        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            $error = 'This email is already registered.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO users 
                (
                    name,
                    email,
                    phone,
                    password,
                    user_type,
                    status,
                    bmdc_number,
                    doctor_specialty,
                    authorized_person_name,
                    hospital_type,
                    hospital_address,
                    created_at
                )
                VALUES 
                (
                    :name,
                    :email,
                    :phone,
                    :password,
                    :user_type,
                    'pending_verification',
                    :bmdc_number,
                    :doctor_specialty,
                    :authorized_person_name,
                    :hospital_type,
                    :hospital_address,
                    NOW()
                )
            ");

            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':password' => $password_hash,
                ':user_type' => $user_type,
                ':bmdc_number' => ($user_type === 'doctor') ? $bmdc_number : null,
                ':doctor_specialty' => ($user_type === 'doctor') ? $doctor_specialty : null,
                ':authorized_person_name' => ($user_type === 'hospital_owner') ? $authorized_person_name : null,
                ':hospital_type' => ($user_type === 'hospital_owner') ? $hospital_type : null,
                ':hospital_address' => ($user_type === 'hospital_owner') ? $hospital_address : null
            ]);

            $_SESSION['user_id'] = (int)$pdo->lastInsertId();
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_name'] = $name;

            redirect('dashboard.php');
        }
    }
}

$page_title = 'Create Account';
$user = null;

require_once __DIR__ . '/../includes/header.php';
?>

<style>
    :root {
        --register-bg: #f6f8fa;
        --register-card: #ffffff;
        --register-soft: #f6f8fa;
        --register-border: #d0d7de;
        --register-text: #24292f;
        --register-muted: #57606a;
        --register-blue: #0969da;
        --register-green: #1f883d;
        --register-green-hover: #1a7f37;
        --register-red-bg: rgba(207, 34, 46, 0.10);
        --register-red-border: rgba(207, 34, 46, 0.28);
        --register-red-text: #cf222e;
        --register-success-bg: rgba(31, 136, 61, 0.10);
        --register-success-border: rgba(31, 136, 61, 0.28);
        --register-success-text: #1a7f37;
        --register-shadow: 0 24px 70px rgba(27, 31, 36, 0.10);
        --register-field-bg: #ffffff;
    }

    html[data-theme="dark"] {
        --register-bg: #0d1117;
        --register-card: #161b22;
        --register-soft: #21262d;
        --register-border: #30363d;
        --register-text: #f0f6fc;
        --register-muted: #8b949e;
        --register-blue: #2f81f7;
        --register-green: #238636;
        --register-green-hover: #2ea043;
        --register-red-bg: rgba(248, 81, 73, 0.12);
        --register-red-border: rgba(248, 81, 73, 0.35);
        --register-red-text: #ff7b72;
        --register-success-bg: rgba(46, 160, 67, 0.12);
        --register-success-border: rgba(46, 160, 67, 0.35);
        --register-success-text: #7ee787;
        --register-shadow: 0 24px 70px rgba(0, 0, 0, 0.45);
        --register-field-bg: #0d1117;
    }

    body {
        background:
            radial-gradient(circle at top left, rgba(9, 105, 218, 0.12), transparent 34%),
            radial-gradient(circle at bottom right, rgba(31, 136, 61, 0.10), transparent 32%),
            var(--register-bg) !important;
        color: var(--register-text);
    }

    html[data-theme="dark"] body {
        background:
            radial-gradient(circle at top left, rgba(47, 129, 247, 0.22), transparent 34%),
            radial-gradient(circle at bottom right, rgba(35, 134, 54, 0.16), transparent 32%),
            var(--register-bg) !important;
    }

    .premium-register-wrapper {
        min-height: calc(100vh - 160px);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 60px 16px;
        color: var(--register-text);
    }

    .premium-register-shell {
        width: 100%;
        max-width: 1180px;
        display: grid;
        grid-template-columns: 0.95fr 1.05fr;
        gap: 28px;
        align-items: stretch;
    }

    .register-info-panel,
    .register-card-panel {
        border: 1px solid var(--register-border);
        background: color-mix(in srgb, var(--register-card) 92%, transparent);
        box-shadow: var(--register-shadow);
        backdrop-filter: blur(18px);
        border-radius: 22px;
        overflow: hidden;
    }

    .register-info-panel {
        padding: 38px;
    }

    .register-badge {
        display: inline-flex;
        align-items: center;
        gap: 9px;
        padding: 8px 13px;
        border: 1px solid var(--register-border);
        background: var(--register-soft);
        border-radius: 999px;
        color: var(--register-muted);
        font-size: 13px;
        margin-bottom: 24px;
    }

    .register-badge span {
        width: 9px;
        height: 9px;
        background: var(--register-green);
        border-radius: 50%;
        box-shadow: 0 0 0 5px rgba(35, 134, 54, 0.15);
    }

    .register-info-panel h1 {
        color: var(--register-text);
        font-size: clamp(32px, 4vw, 52px);
        line-height: 1.08;
        letter-spacing: -1.5px;
        margin: 0 0 16px;
        font-weight: 800;
    }

    .register-info-panel p {
        color: var(--register-muted);
        font-size: 16px;
        line-height: 1.75;
        max-width: 560px;
        margin: 0 0 26px;
    }

    .register-stat-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 14px;
        margin: 28px 0;
    }

    .register-stat {
        padding: 16px;
        border: 1px solid var(--register-border);
        background: color-mix(in srgb, var(--register-soft) 72%, transparent);
        border-radius: 16px;
    }

    .register-stat strong {
        display: block;
        color: var(--register-text);
        font-size: 22px;
        margin-bottom: 5px;
    }

    .register-stat small {
        color: var(--register-muted);
        font-size: 13px;
        line-height: 1.5;
    }

    .register-feature-list {
        display: grid;
        gap: 14px;
        margin-top: 26px;
    }

    .register-feature {
        display: flex;
        gap: 13px;
        align-items: flex-start;
        padding: 15px;
        border: 1px solid var(--register-border);
        background: color-mix(in srgb, var(--register-soft) 72%, transparent);
        border-radius: 16px;
    }

    .register-feature-icon {
        width: 36px;
        height: 36px;
        flex: 0 0 36px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: rgba(47, 129, 247, 0.15);
        color: var(--register-blue);
        font-size: 17px;
    }

    .register-feature strong {
        display: block;
        color: var(--register-text);
        font-size: 14px;
        margin-bottom: 4px;
    }

    .register-feature small {
        color: var(--register-muted);
        font-size: 13px;
        line-height: 1.55;
    }

    .register-card-panel {
        padding: 34px;
    }

    .register-card-header {
        text-align: center;
        margin-bottom: 26px;
    }

    .register-avatar {
        width: 66px;
        height: 66px;
        display: grid;
        place-items: center;
        margin: 0 auto 16px;
        border-radius: 50%;
        background: linear-gradient(145deg, var(--register-soft), var(--register-card));
        border: 1px solid var(--register-border);
        color: var(--register-blue);
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.06);
    }

    .register-avatar svg {
        width: 32px;
        height: 32px;
    }

    .register-card-header h2 {
        color: var(--register-text);
        font-size: 26px;
        margin: 0 0 8px;
        letter-spacing: -0.5px;
        font-weight: 750;
    }

    .register-card-header p {
        color: var(--register-muted);
        margin: 0;
        font-size: 14px;
        line-height: 1.6;
    }

    .premium-alert {
        padding: 13px 14px;
        border-radius: 12px;
        margin-bottom: 18px;
        font-size: 14px;
        line-height: 1.5;
    }

    .premium-alert.error {
        background: var(--register-red-bg);
        border: 1px solid var(--register-red-border);
        color: var(--register-red-text);
    }

    .premium-alert.success {
        background: var(--register-success-bg);
        border: 1px solid var(--register-success-border);
        color: var(--register-success-text);
    }

    .premium-register-form {
        display: grid;
        gap: 16px;
    }

    .field-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }

    .premium-field label {
        display: block;
        color: var(--register-text);
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
    }

    .premium-field label span {
        color: var(--register-red-text);
        margin-left: 3px;
    }

    .premium-field input,
    .premium-field select,
    .premium-field textarea {
        width: 100%;
        border: 1px solid var(--register-border);
        border-radius: 12px;
        background: var(--register-field-bg);
        color: var(--register-text);
        outline: none;
        padding: 0 14px;
        font-size: 15px;
        transition: 0.2s ease;
        box-sizing: border-box;
    }

    .premium-field input,
    .premium-field select {
        height: 48px;
    }

    .premium-field textarea {
        min-height: 96px;
        padding-top: 12px;
        resize: vertical;
        line-height: 1.5;
    }

    .premium-field select {
        cursor: pointer;
    }

    .premium-field input::placeholder,
    .premium-field textarea::placeholder {
        color: var(--register-muted);
        opacity: 0.8;
    }

    .premium-field input:focus,
    .premium-field select:focus,
    .premium-field textarea:focus {
        border-color: var(--register-blue);
        box-shadow: 0 0 0 4px rgba(47, 129, 247, 0.18);
    }

    .conditional-fields {
        display: none;
        padding: 16px;
        border: 1px solid rgba(47, 129, 247, 0.22);
        background: color-mix(in srgb, var(--register-soft) 58%, transparent);
        border-radius: 16px;
    }

    .conditional-fields.active {
        display: grid;
        gap: 16px;
    }

    .conditional-title {
        color: var(--register-blue);
        font-size: 14px;
        font-weight: 700;
        margin-bottom: -2px;
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
        color: var(--register-blue);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        padding: 8px 10px;
        border-radius: 9px;
    }

    .toggle-password:hover {
        background: rgba(47, 129, 247, 0.12);
    }

    .account-type-help {
        margin-top: 8px;
        color: var(--register-muted);
        font-size: 12.5px;
        line-height: 1.5;
    }

    .register-rules {
        padding: 14px;
        border: 1px solid rgba(47, 129, 247, 0.24);
        background: rgba(47, 129, 247, 0.08);
        color: var(--register-muted);
        border-radius: 14px;
        font-size: 13px;
        line-height: 1.65;
    }

    .register-rules strong {
        color: var(--register-blue);
    }

    .premium-register-btn {
        width: 100%;
        height: 48px;
        border: 0;
        border-radius: 12px;
        background: var(--register-green);
        color: #ffffff;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        transition: 0.2s ease;
        margin-top: 2px;
    }

    .premium-register-btn:hover {
        background: var(--register-green-hover);
        transform: translateY(-1px);
    }

    .premium-register-btn:active {
        transform: translateY(0);
    }

    .register-divider {
        display: flex;
        align-items: center;
        gap: 12px;
        color: var(--register-muted);
        font-size: 13px;
        margin: 8px 0;
    }

    .register-divider::before,
    .register-divider::after {
        content: "";
        height: 1px;
        flex: 1;
        background: var(--register-border);
    }

    .login-link-btn {
        width: 100%;
        min-height: 46px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border: 1px solid var(--register-border);
        border-radius: 12px;
        background: var(--register-soft);
        color: var(--register-text);
        font-weight: 650;
        font-size: 15px;
        text-decoration: none;
        transition: 0.2s ease;
        box-sizing: border-box;
    }

    .login-link-btn:hover {
        border-color: var(--register-muted);
        background: color-mix(in srgb, var(--register-soft) 86%, var(--register-blue));
        color: var(--register-text);
        text-decoration: none;
    }

    .register-bottom-note {
        margin-top: 18px;
        padding: 13px 14px;
        border: 1px solid rgba(46, 160, 67, 0.22);
        background: rgba(46, 160, 67, 0.08);
        color: var(--register-muted);
        border-radius: 13px;
        font-size: 13px;
        line-height: 1.6;
    }

    .register-bottom-note strong {
        color: var(--register-success-text);
    }

    @media (max-width: 980px) {
        .premium-register-shell {
            grid-template-columns: 1fr;
            max-width: 640px;
        }

        .register-info-panel {
            padding: 28px;
        }

        .register-card-panel {
            padding: 26px;
        }
    }

    @media (max-width: 560px) {
        .premium-register-wrapper {
            padding: 36px 12px;
        }

        .register-info-panel,
        .register-card-panel {
            border-radius: 18px;
        }

        .register-info-panel,
        .register-card-panel {
            padding: 22px;
        }

        .field-grid,
        .register-stat-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="premium-register-wrapper">
    <div class="premium-register-shell">

        <section class="register-info-panel">
            <div class="register-badge">
                <span></span>
                Professional account registration
            </div>

            <h1>Create your trusted profile.</h1>

            <p>
                Register as a doctor or hospital owner to manage your public profile,
                submit information updates, and keep your healthcare listing accurate.
            </p>

            <div class="register-stat-grid">
                <div class="register-stat">
                    <strong>Doctor</strong>
                    <small>Manage doctor profile, chamber details, specialty, and contact information.</small>
                </div>

                <div class="register-stat">
                    <strong>Hospital</strong>
                    <small>Manage hospital profile, department details, service information, and location.</small>
                </div>
            </div>

            <div class="register-feature-list">
                <div class="register-feature">
                    <div class="register-feature-icon">✓</div>
                    <div>
                        <strong>Profile update requests</strong>
                        <small>Submit your profile changes for admin review and approval.</small>
                    </div>
                </div>

                <div class="register-feature">
                    <div class="register-feature-icon">↗</div>
                    <div>
                        <strong>Better visitor trust</strong>
                        <small>Accurate contact, location, and service details help visitors make better decisions.</small>
                    </div>
                </div>

                <div class="register-feature">
                    <div class="register-feature-icon">🔒</div>
                    <div>
                        <strong>Secure dashboard access</strong>
                        <small>Your account area is protected with email and password based authentication.</small>
                    </div>
                </div>
            </div>
        </section>

        <section class="register-card-panel">
            <div class="register-card-header">
                <div class="register-avatar" aria-hidden="true">
                    <svg viewBox="0 0 16 16" fill="currentColor">
                        <path d="M7.5 2a5.5 5.5 0 0 0-4.764 8.258c.085.147.077.331-.024.468L1.58 12.268a.75.75 0 0 0 .884 1.103l1.877-.704a.75.75 0 0 1 .55.012A5.5 5.5 0 1 0 7.5 2Zm0 1.5a4 4 0 1 1 0 8 4 4 0 0 1-1.886-.47.75.75 0 0 0-.635-.04l-.66.248.376-.512a.75.75 0 0 0 .056-.805A4 4 0 0 1 7.5 3.5Z"></path>
                        <path d="M10.25 7.25H8.25V5.25a.75.75 0 0 0-1.5 0v2H4.75a.75.75 0 0 0 0 1.5h2v2a.75.75 0 0 0 1.5 0v-2h2a.75.75 0 0 0 0-1.5Z"></path>
                    </svg>
                </div>

                <h2>Create Account</h2>
                <p>Fill in your details carefully. You can manage your profile after registration.</p>
            </div>

            <?php if ($error): ?>
                <div class="premium-alert error">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="premium-alert success">
                    <?= e($success) ?>
                </div>
            <?php endif; ?>

            <form method="post" class="premium-register-form" autocomplete="on">
                <?= csrf_field() ?>

                <div class="field-grid">
                    <div class="premium-field">
                        <label for="user_type">Account Type <span>*</span></label>
                        <select id="user_type" name="user_type" required onchange="handleAccountType()">
                            <option value="">Select Account Type</option>
                            <option value="doctor" <?= (($_POST['user_type'] ?? '') === 'doctor') ? 'selected' : '' ?>>
                                Doctor
                            </option>
                            <option value="hospital_owner" <?= (($_POST['user_type'] ?? '') === 'hospital_owner') ? 'selected' : '' ?>>
                                Hospital Owner / Authority
                            </option>
                        </select>

                        <div class="account-type-help">
                            Choose Doctor for personal doctor profile, or Hospital Owner for hospital profile management.
                        </div>
                    </div>

                    <div class="premium-field">
                        <label for="name" id="nameLabel">Full Name <span>*</span></label>
                        <input 
                            type="text" 
                            id="name"
                            name="name" 
                            placeholder="Enter your full name or hospital name"
                            value="<?= e($_POST['name'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <div class="field-grid">
                    <div class="premium-field">
                        <label for="email">Email Address <span>*</span></label>
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
                        <label for="phone">Phone Number <span>*</span></label>
                        <input 
                            type="text" 
                            id="phone"
                            name="phone" 
                            placeholder="Enter your phone number"
                            value="<?= e($_POST['phone'] ?? '') ?>"
                            required
                        >
                    </div>
                </div>

                <div id="doctorFields" class="conditional-fields">
                    <div class="conditional-title">Doctor account information</div>

                    <div class="field-grid">
                        <div class="premium-field">
                            <label for="bmdc_number">BMDC Registration Number <span>*</span></label>
                            <input 
                                type="text" 
                                id="bmdc_number"
                                name="bmdc_number" 
                                placeholder="Enter BMDC registration number"
                                value="<?= e($_POST['bmdc_number'] ?? '') ?>"
                            >
                        </div>

                        <div class="premium-field">
                            <label for="doctor_specialty">Specialty <span>*</span></label>
                            <select id="doctor_specialty" name="doctor_specialty">
                                <option value="">Select Specialty</option>

                                <?php foreach ($specialty_options as $specialty): ?>
                                    <?php
                                        $specialty_name = $specialty['name'] ?? '';
                                        $specialty_bn = $specialty['name_bn'] ?? '';
                                        $label = $specialty_name;

                                        if ($specialty_bn !== '') {
                                            $label .= ' - ' . $specialty_bn;
                                        }
                                    ?>

                                    <option 
                                        value="<?= e($specialty_name) ?>" 
                                        <?= (($_POST['doctor_specialty'] ?? '') === $specialty_name) ? 'selected' : '' ?>
                                    >
                                        <?= e($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php if (empty($specialty_options)): ?>
                                <div class="account-type-help">
                                    No active specialty found. Please add specialties from admin panel.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="hospitalFields" class="conditional-fields">
                    <div class="conditional-title">Hospital account information</div>

                    <div class="field-grid">
                        <div class="premium-field">
                            <label for="authorized_person_name">Authorized Person Name <span>*</span></label>
                            <input 
                                type="text" 
                                id="authorized_person_name"
                                name="authorized_person_name" 
                                placeholder="Owner / Manager / Authorized admin"
                                value="<?= e($_POST['authorized_person_name'] ?? '') ?>"
                            >
                        </div>

                        <div class="premium-field">
                            <label for="hospital_type">Hospital Type <span>*</span></label>
                            <select id="hospital_type" name="hospital_type">
                                <option value="">Select Hospital Type</option>

                                <?php foreach ($hospital_type_options as $type): ?>
                                    <option 
                                        value="<?= e($type) ?>" 
                                        <?= (($_POST['hospital_type'] ?? '') === $type) ? 'selected' : '' ?>
                                    >
                                        <?= e($type) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="premium-field">
                        <label for="hospital_address">Full Address <span>*</span></label>
                        <textarea 
                            id="hospital_address"
                            name="hospital_address" 
                            placeholder="Enter full hospital address"
                        ><?= e($_POST['hospital_address'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="field-grid">
                    <div class="premium-field">
                        <label for="password">Password <span>*</span></label>

                        <div class="password-wrap">
                            <input 
                                type="password" 
                                id="password"
                                name="password" 
                                placeholder="Minimum 6 characters"
                                required
                            >

                            <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                                Show
                            </button>
                        </div>
                    </div>

                    <div class="premium-field">
                        <label for="confirm_password">Confirm Password <span>*</span></label>

                        <div class="password-wrap">
                            <input 
                                type="password" 
                                id="confirm_password"
                                name="confirm_password" 
                                placeholder="Re-enter your password"
                                required
                            >

                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)">
                                Show
                            </button>
                        </div>
                    </div>
                </div>

                <div class="register-rules">
                    <strong>Account guideline:</strong>
                    Doctor account requires BMDC number and specialty. Hospital account requires authorized person, hospital type, and full address. New accounts will stay pending until verification.
                </div>

                <button type="submit" class="premium-register-btn">
                    Create Account
                </button>

                <div class="register-divider">
                    Already have an account?
                </div>

                <a class="login-link-btn" href="login.php">
                    Login to Existing Account
                </a>
            </form>

            <div class="register-bottom-note">
                <strong>Security note:</strong>
                Your password is stored securely using password hashing. Never share your login details with anyone.
            </div>
        </section>

    </div>
</div>

<script>
    function togglePassword(inputId, button) {
        const passwordInput = document.getElementById(inputId);

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            button.textContent = 'Hide';
        } else {
            passwordInput.type = 'password';
            button.textContent = 'Show';
        }
    }

    function setRequiredState(containerId, isRequired) {
        const fields = document.querySelectorAll('#' + containerId + ' input, #' + containerId + ' select, #' + containerId + ' textarea');

        fields.forEach(function(field) {
            if (isRequired) {
                field.setAttribute('required', 'required');
            } else {
                field.removeAttribute('required');
            }
        });
    }

    function handleAccountType() {
        const accountType = document.getElementById('user_type').value;
        const doctorFields = document.getElementById('doctorFields');
        const hospitalFields = document.getElementById('hospitalFields');
        const nameLabel = document.getElementById('nameLabel');
        const nameInput = document.getElementById('name');

        doctorFields.classList.remove('active');
        hospitalFields.classList.remove('active');

        setRequiredState('doctorFields', false);
        setRequiredState('hospitalFields', false);

        if (accountType === 'doctor') {
            doctorFields.classList.add('active');
            setRequiredState('doctorFields', true);
            nameLabel.innerHTML = 'Doctor Full Name <span>*</span>';
            nameInput.placeholder = 'Enter doctor full name';
        } else if (accountType === 'hospital_owner') {
            hospitalFields.classList.add('active');
            setRequiredState('hospitalFields', true);
            nameLabel.innerHTML = 'Hospital / Clinic Name <span>*</span>';
            nameInput.placeholder = 'Enter hospital or clinic name';
        } else {
            nameLabel.innerHTML = 'Full Name <span>*</span>';
            nameInput.placeholder = 'Enter your full name or hospital name';
        }
    }

    document.addEventListener('DOMContentLoaded', handleAccountType);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>