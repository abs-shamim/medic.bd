<?php
require_once __DIR__ . '/../includes/functions.php';

require_admin();

function pca_table_exists(string $table): bool
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
        ");
        $stmt->execute([':table' => $table]);

        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function pca_column_exists(string $table, string $column): bool
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table
            AND COLUMN_NAME = :column
        ");
        $stmt->execute([
            ':table' => $table,
            ':column' => $column,
        ]);

        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function pca_add_column_if_missing(string $table, string $column, string $definition): void
{
    global $pdo;

    if (!pca_table_exists($table)) {
        return;
    }

    if (!pca_column_exists($table, $column)) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

function pca_ensure_columns(): void
{
    pca_add_column_if_missing('profile_claims', 'admin_note', "TEXT NULL");
    pca_add_column_if_missing('profile_claims', 'updated_at', "DATETIME NULL");

    pca_add_column_if_missing('users', 'claimed_doctor_id', "INT UNSIGNED DEFAULT NULL");
    pca_add_column_if_missing('users', 'claimed_hospital_id', "INT UNSIGNED DEFAULT NULL");

    if (pca_table_exists('doctors')) {
        pca_add_column_if_missing('doctors', 'is_verified', "TINYINT(1) DEFAULT 0");
    }

    if (pca_table_exists('hospitals')) {
        pca_add_column_if_missing('hospitals', 'is_verified', "TINYINT(1) DEFAULT 0");
    }
}

function pca_redirect(string $message = '', string $type = 'success'): void
{
    $url = 'profile-claims.php';

    if ($message !== '') {
        $url .= '?' . $type . '=' . urlencode($message);
    }

    redirect($url);
}

function pca_mark_claim(int $claim_id, string $status, string $note = ''): void
{
    global $pdo;

    pca_ensure_columns();

    $sets = ["status = :status"];
    $params = [
        ':id' => $claim_id,
        ':status' => $status,
    ];

    if (pca_column_exists('profile_claims', 'admin_note')) {
        $sets[] = "admin_note = :admin_note";
        $params[':admin_note'] = $note;
    }

    if (pca_column_exists('profile_claims', 'updated_at')) {
        $sets[] = "updated_at = NOW()";
    }

    $stmt = $pdo->prepare("
        UPDATE profile_claims
        SET " . implode(', ', $sets) . "
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute($params);
}

function pca_profile_exists(string $type, int $profile_id): bool
{
    global $pdo;

    if ($profile_id <= 0) {
        return false;
    }

    if ($type === 'doctor') {
        if (!pca_table_exists('doctors')) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT id FROM doctors WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $profile_id]);

        return (bool)$stmt->fetchColumn();
    }

    if ($type === 'hospital') {
        if (!pca_table_exists('hospitals')) {
            return false;
        }

        $stmt = $pdo->prepare("SELECT id FROM hospitals WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $profile_id]);

        return (bool)$stmt->fetchColumn();
    }

    return false;
}

function pca_verify_profile_after_claim(string $type, int $profile_id): void
{
    global $pdo;

    if ($profile_id <= 0) {
        return;
    }

    $table = $type === 'hospital' ? 'hospitals' : 'doctors';

    if (!pca_table_exists($table)) {
        return;
    }

    $sets = [];

    if (pca_column_exists($table, 'is_verified')) {
        $sets[] = "is_verified = 1";
    }

    if (pca_column_exists($table, 'status')) {
        $sets[] = "status = 'active'";
    }

    if (pca_column_exists($table, 'updated_at')) {
        $sets[] = "updated_at = NOW()";
    }

    if (!$sets) {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE {$table}
        SET " . implode(', ', $sets) . "
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([':id' => $profile_id]);
}

function pca_approve_claim(array $claim): string
{
    global $pdo;

    pca_ensure_columns();

    $claim_id = (int)$claim['id'];
    $user_id = (int)$claim['user_id'];
    $claim_type = (string)$claim['claim_type'];

    if ($user_id <= 0) {
        throw new RuntimeException('Invalid user ID.');
    }

    if (!pca_table_exists('users')) {
        throw new RuntimeException('Users table not found.');
    }

    if ($claim_type === 'doctor') {
        $doctor_id = (int)($claim['doctor_id'] ?? 0);

        if ($doctor_id <= 0) {
            throw new RuntimeException('Doctor ID not found.');
        }

        if (!pca_profile_exists('doctor', $doctor_id)) {
            throw new RuntimeException('Doctor profile does not exist.');
        }

        if (!pca_column_exists('users', 'claimed_doctor_id')) {
            throw new RuntimeException('users.claimed_doctor_id column not found.');
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET claimed_doctor_id = :doctor_id
            WHERE id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':doctor_id' => $doctor_id,
            ':user_id' => $user_id,
        ]);

        pca_verify_profile_after_claim('doctor', $doctor_id);
        pca_mark_claim($claim_id, 'approved', 'Doctor profile claim approved and doctor verified.');

        return 'Doctor profile claim approved successfully and doctor verified.';
    }

    if ($claim_type === 'hospital') {
        $hospital_id = (int)($claim['hospital_id'] ?? 0);

        if ($hospital_id <= 0) {
            throw new RuntimeException('Hospital ID not found.');
        }

        if (!pca_profile_exists('hospital', $hospital_id)) {
            throw new RuntimeException('Hospital profile does not exist.');
        }

        if (!pca_column_exists('users', 'claimed_hospital_id')) {
            throw new RuntimeException('users.claimed_hospital_id column not found.');
        }

        $stmt = $pdo->prepare("
            UPDATE users
            SET claimed_hospital_id = :hospital_id
            WHERE id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':hospital_id' => $hospital_id,
            ':user_id' => $user_id,
        ]);

        pca_verify_profile_after_claim('hospital', $hospital_id);
        pca_mark_claim($claim_id, 'approved', 'Hospital profile claim approved and hospital verified.');

        return 'Hospital profile claim approved successfully and hospital verified.';
    }

    throw new RuntimeException('Invalid claim type.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('profile-claims.php');
}

$id = (int)($_POST['id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));
$admin_note = trim((string)($_POST['admin_note'] ?? ''));

if ($id <= 0 || !in_array($action, ['approve', 'reject'], true)) {
    redirect('profile-claims.php');
}

try {
    pca_ensure_columns();

    $stmt = $pdo->prepare("
        SELECT *
        FROM profile_claims
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);

    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$claim) {
        pca_redirect('Claim request not found.', 'error');
    }

    if (($claim['status'] ?? '') !== 'pending') {
        pca_redirect('This claim request is already processed.', 'error');
    }

    if ($action === 'reject') {
        if ($admin_note === '') {
            $admin_note = 'Rejected by admin.';
        }

        pca_mark_claim($id, 'rejected', $admin_note);
        pca_redirect('Claim request rejected successfully.', 'success');
    }

    if ($action === 'approve') {
        $pdo->beginTransaction();

        try {
            $message = pca_approve_claim($claim);
            $pdo->commit();

            pca_redirect($message, 'success');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }
} catch (Throwable $e) {
    pca_redirect('Action failed: ' . $e->getMessage(), 'error');
}