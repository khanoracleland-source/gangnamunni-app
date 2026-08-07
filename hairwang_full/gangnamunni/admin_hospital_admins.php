<?php
/**
 * Hospital sub-admin management.
 *
 * Platform super admins can manage every hospital. Hospital owners can manage
 * sub-admins belonging to their own hospital only.
 */
session_start();

$admin_page_title = 'Hospital Staff Accounts';
$admin_current = 'hospital_admins';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/hospital_filter.php';
require_once __DIR__ . '/includes/functions.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) {
    http_response_code(500);
    exit('Database connection failed.');
}
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();
require_permission('hospital_admins');

$current_admin = get_current_admin();
$current_role = $current_admin['role'] ?? '';
$current_admin_id = (int)($current_admin['id'] ?? 0);
$is_super_admin = $current_role === 'super_admin';
$is_hospital_owner = in_array($current_role, ['hospital_super_admin', 'hospital_admin'], true);
$current_hospital_id = $is_hospital_owner ? (int)get_hospital_filter() : 0;

if (!$is_super_admin && (!$is_hospital_owner || $current_hospital_id <= 0)) {
    http_response_code(403);
    exit('Access denied.');
}

$message = '';
$error = '';
$csrf_token = generateCsrfToken();

/** Fetch a hospital that the current administrator is allowed to manage. */
function find_manageable_hospital(mysqli $connect, int $hospital_id, bool $is_super_admin, int $current_hospital_id): ?array
{
    if ($hospital_id <= 0 || (!$is_super_admin && $hospital_id !== $current_hospital_id)) {
        return null;
    }

    $stmt = mysqli_prepare($connect, 'SELECT id, name FROM gangnamunni_hospitals WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $hospital_id);
    mysqli_stmt_execute($stmt);
    $hospital = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    mysqli_stmt_close($stmt);

    return $hospital;
}

/** Prevent ambiguous login identifiers (login accepts both username and email). */
function hospital_admin_identity_taken(mysqli $connect, string $username, string $email): bool
{
    $stmt = mysqli_prepare(
        $connect,
        'SELECT id FROM gangnamunni_admins
         WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?)
            OR (? <> \'\' AND LOWER(username) = LOWER(?))
            OR (? <> \'\' AND LOWER(email) = LOWER(?))
         LIMIT 1'
    );
    if (!$stmt) {
        return true;
    }
    mysqli_stmt_bind_param($stmt, 'ssssss', $username, $username, $email, $email, $email, $email);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $taken = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);

    return $taken;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'The request has expired. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create') {
            $username = trim((string)($_POST['username'] ?? ''));
            $password = (string)($_POST['password'] ?? '');
            $name = trim((string)($_POST['name'] ?? ''));
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            $hospital_id = $is_super_admin
                ? (int)($_POST['hospital_id'] ?? 0)
                : $current_hospital_id;
            $hospital = find_manageable_hospital($connect, $hospital_id, $is_super_admin, $current_hospital_id);

            if (!$hospital) {
                $error = 'Please select a valid hospital.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
                $error = 'Username must contain 3-30 letters, numbers, or underscores.';
            } elseif (strlen($password) < 8) {
                $error = 'Password must be at least 8 characters.';
            } elseif ($name === '' || mb_strlen($name) > 100) {
                $error = 'Name is required and must not exceed 100 characters.';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } elseif (hospital_admin_identity_taken($connect, $username, $email)) {
                $error = 'Username or email is already in use.';
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'hospital_sub_admin';
                $hospital_name = $hospital['name'];
                $stmt = mysqli_prepare(
                    $connect,
                    'INSERT INTO gangnamunni_admins
                     (username, password, name, email, role, hospital_id, hospital_name, parent_admin_id, is_active, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
                );

                if ($stmt) {
                    mysqli_stmt_bind_param(
                        $stmt,
                        'sssssisi',
                        $username,
                        $password_hash,
                        $name,
                        $email,
                        $role,
                        $hospital_id,
                        $hospital_name,
                        $current_admin_id
                    );
                }

                if ($stmt && mysqli_stmt_execute($stmt)) {
                    $message = "Sub-admin '{$username}' created.";
                } else {
                    $error = 'Unable to create the sub-admin.';
                    error_log('[hospital_admins] create failed: ' . mysqli_error($connect));
                }
                if ($stmt) {
                    mysqli_stmt_close($stmt);
                }
            }
        } elseif (in_array($action, ['toggle', 'delete'], true)) {
            $admin_id = (int)($_POST['admin_id'] ?? 0);
            $stmt = mysqli_prepare(
                $connect,
                "SELECT id, hospital_id, is_active FROM gangnamunni_admins
                 WHERE id = ? AND role = 'hospital_sub_admin' LIMIT 1"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $admin_id);
                mysqli_stmt_execute($stmt);
                $target = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
                mysqli_stmt_close($stmt);
            } else {
                $target = null;
            }

            if (!$target || (!$is_super_admin && (int)$target['hospital_id'] !== $current_hospital_id)) {
                $error = 'Sub-admin not found or access denied.';
            } elseif ($action === 'toggle') {
                $new_status = (int)!((bool)$target['is_active']);
                $stmt = mysqli_prepare($connect, 'UPDATE gangnamunni_admins SET is_active = ?, updated_at = NOW() WHERE id = ?');
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ii', $new_status, $admin_id);
                }
                if ($stmt && mysqli_stmt_execute($stmt)) {
                    $message = $new_status ? 'Sub-admin activated.' : 'Sub-admin deactivated.';
                } else {
                    $error = 'Unable to update the sub-admin.';
                }
                if ($stmt) {
                    mysqli_stmt_close($stmt);
                }
            } else {
                $stmt = mysqli_prepare($connect, "DELETE FROM gangnamunni_admins WHERE id = ? AND role = 'hospital_sub_admin'");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'i', $admin_id);
                }
                if ($stmt && mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
                    $message = 'Sub-admin deleted.';
                } else {
                    $error = 'Unable to delete the sub-admin.';
                }
                if ($stmt) {
                    mysqli_stmt_close($stmt);
                }
            }
        } else {
            $error = 'Invalid action.';
        }
    }
}

if ($is_super_admin) {
    $admins = mysqli_query(
        $connect,
        "SELECT id, username, name, role, hospital_id, hospital_name, is_active
         FROM gangnamunni_admins
         WHERE role IN ('hospital_admin', 'hospital_sub_admin')
         ORDER BY hospital_name, role, id"
    );
} else {
    $stmt = mysqli_prepare(
        $connect,
        "SELECT id, username, name, role, hospital_id, hospital_name, is_active
         FROM gangnamunni_admins
         WHERE role IN ('hospital_admin', 'hospital_sub_admin') AND hospital_id = ?
         ORDER BY role, id"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $current_hospital_id);
        mysqli_stmt_execute($stmt);
        $admins = mysqli_stmt_get_result($stmt);
        mysqli_stmt_close($stmt);
    } else {
        $admins = false;
        $error = $error ?: 'Unable to load hospital administrators.';
    }
}

include __DIR__ . '/includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-person-plus me-2"></i>Add Sub-Admin</h6>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3" autocomplete="off">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <?php if ($is_super_admin): ?>
            <div class="col-md-3">
                <label class="form-label">Hospital</label>
                <select name="hospital_id" class="form-select" required>
                    <option value="">Select...</option>
                    <?php
                    $hospitals = mysqli_query($connect, 'SELECT id, name FROM gangnamunni_hospitals ORDER BY name');
                    while ($hospital = mysqli_fetch_assoc($hospitals)):
                    ?>
                    <option value="<?= (int)$hospital['id'] ?>"><?= htmlspecialchars($hospital['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" minlength="3" maxlength="30"
                       pattern="[a-zA-Z0-9_]+" required autocomplete="off">
            </div>
            <div class="col-md-2">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password">
            </div>
            <div class="col-md-2">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" maxlength="100" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" maxlength="255">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Create</button>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
    <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Name</th>
        <th>Role</th>
        <th>Hospital</th>
        <th>Status</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
<?php while ($admins && ($admin = mysqli_fetch_assoc($admins))): ?>
    <tr>
        <td><?= (int)$admin['id'] ?></td>
        <td><strong><?= htmlspecialchars($admin['username'], ENT_QUOTES, 'UTF-8') ?></strong></td>
        <td><?= htmlspecialchars($admin['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        <td>
            <span class="badge bg-<?= $admin['role'] === 'hospital_admin' ? 'primary' : 'info' ?>">
                <?= $admin['role'] === 'hospital_admin' ? 'Admin' : 'Sub-Admin' ?>
            </span>
        </td>
        <td><?= htmlspecialchars($admin['hospital_name'] ?? '', ENT_QUOTES, 'UTF-8') ?> (#<?= (int)$admin['hospital_id'] ?>)</td>
        <td>
            <span class="badge bg-<?= !empty($admin['is_active']) ? 'success' : 'secondary' ?>">
                <?= !empty($admin['is_active']) ? 'Active' : 'Inactive' ?>
            </span>
        </td>
        <td>
            <?php if ($admin['role'] === 'hospital_sub_admin'): ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="admin_id" value="<?= (int)$admin['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-<?= !empty($admin['is_active']) ? 'warning' : 'success' ?>">
                        <?= !empty($admin['is_active']) ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this sub-admin?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="admin_id" value="<?= (int)$admin['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            <?php else: ?>
                <span class="text-muted">-</span>
            <?php endif; ?>
        </td>
    </tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
