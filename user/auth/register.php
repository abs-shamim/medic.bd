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

<link rel="stylesheet" href="<?php echo BASE_URL; ?>/user/assets/css/user-register.css">

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
                        <select id="user_type" name="user_type" required>
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
                            autocomplete="name"
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
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="premium-field">
                        <label for="phone">Phone Number <span>*</span></label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            placeholder="Enter your phone number"
                            value="<?= e($_POST['phone'] ?? '') ?>"
                            autocomplete="tel"
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
                                autocomplete="new-password"
                                required
                            >

                            <button type="button" class="toggle-password" data-toggle-target="password">
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
                                autocomplete="new-password"
                                required
                            >

                            <button type="button" class="toggle-password" data-toggle-target="confirm_password">
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

<script src="<?php echo BASE_URL; ?>/user/assets/js/register.js" defer></script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>