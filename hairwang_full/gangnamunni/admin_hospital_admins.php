<?php
/**
 * Hospital Admin/Sub-Admin Management
 * hospital_admin can manage their sub-admins
 */
session_start();
$admin_page_title = 'Hospital Staff Accounts';
$admin_current = 'hospital_admins';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB Error");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();

// Hospital admin can only manage their hospital's sub-admins
require_once __DIR__ . '/includes/hospital_filter.php';
$my_hospital_id = get_hospital_filter();
$my_role = $_SESSION['ad_admin_role'] ?? '';
$my_id = $_SESSION['ad_admin_id'] ?? 0;

// Only hospital_admin and super_admin can access
if (!in_array($my_role, ['super_admin', 'admin', 'hospital_admin'])) {
    echo '<div class="alert alert-danger m-5">Access denied.</div>';
    exit;
}

// Process actions
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $role = 'hospital_sub_admin';
        $h_id = $my_hospital_id ?: (int)$_POST['hospital_id'];
        $h_name = '';

        // Get hospital name
        $hr = mysqli_fetch_assoc(mysqli_query($connect, "SELECT name FROM gangnamunni_hospitals WHERE id = {$h_id}"));
        if ($hr) $h_name = $hr['name'];

        if (!$username || !$password) {
            $msg = 'Username and password required.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $connect->prepare("INSERT INTO gangnamunni_admins (username, password, name, email, role, hospital_id, hospital_name, parent_admin_id) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param('sssssis' . 'i', $username, $hash, $name, $email, $role, $h_id, $h_name, $my_id);
            if ($stmt->execute()) {
                $msg = "Sub-admin '{$username}' created.";
            } else {
                $msg = "Error: " . $connect->error;
            }
        }
    } elseif ($action === 'delete') {
        $del_id = (int)$_POST['admin_id'];
        // Only delete sub-admins of own hospital
        $check = mysqli_fetch_assoc(mysqli_query($connect, "SELECT hospital_id, role FROM gangnamunni_admins WHERE id = {$del_id}"));
        if ($check && $check['role'] === 'hospital_sub_admin' && ($my_role === 'super_admin' || $check['hospital_id'] == $my_hospital_id)) {
            $connect->query("DELETE FROM gangnamunni_admins WHERE id = {$del_id}");
            $msg = "Sub-admin deleted.";
        } else {
            $msg = "Cannot delete this account.";
        }
    } elseif ($action === 'toggle') {
        $toggle_id = (int)$_POST['admin_id'];
        $check = mysqli_fetch_assoc(mysqli_query($connect, "SELECT hospital_id, role, is_active FROM gangnamunni_admins WHERE id = {$toggle_id}"));
        if ($check && $check['role'] === 'hospital_sub_admin') {
            $new_status = $check['is_active'] ? 0 : 1;
            $connect->query("UPDATE gangnamunni_admins SET is_active = {$new_status} WHERE id = {$toggle_id}");
            $msg = $new_status ? "Sub-admin activated." : "Sub-admin deactivated.";
        }
    }
}

// Fetch staff
$where = "WHERE role IN ('hospital_admin','hospital_sub_admin')";
if ($my_hospital_id) {
    $where .= " AND hospital_id = {$my_hospital_id}";
}
$admins = mysqli_query($connect, "SELECT * FROM gangnamunni_admins {$where} ORDER BY role, id");

include __DIR__ . '/includes/admin_header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-info alert-dismissible fade show">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Create Sub-Admin Form -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="bi bi-person-plus me-2"></i>Add Sub-Admin</h6>
    </div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <input type="hidden" name="action" value="create">
            <?php if (!$my_hospital_id): ?>
            <div class="col-md-3">
                <label class="form-label">Hospital</label>
                <select name="hospital_id" class="form-select" required>
                    <option value="">Select...</option>
                    <?php
                    $hosp = mysqli_query($connect, "SELECT id, name FROM gangnamunni_hospitals ORDER BY name");
                    while ($h = mysqli_fetch_assoc($hosp)):
                    ?>
                    <option value="<?= $h['id'] ?>"><?= htmlspecialchars($h['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-md-2">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Staff List -->
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
<?php while ($a = mysqli_fetch_assoc($admins)): ?>
    <tr>
        <td><?= $a['id'] ?></td>
        <td><strong><?= htmlspecialchars($a['username']) ?></strong></td>
        <td><?= htmlspecialchars($a['name'] ?? '') ?></td>
        <td>
            <?php if ($a['role'] === 'hospital_admin'): ?>
                <span class="badge bg-primary">Admin</span>
            <?php else: ?>
                <span class="badge bg-info">Sub-Admin</span>
            <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($a['hospital_name'] ?? '') ?> (#<?= $a['hospital_id'] ?>)</td>
        <td>
            <span class="badge bg-<?= ($a['is_active'] ?? 1) ? 'success' : 'secondary' ?>">
                <?= ($a['is_active'] ?? 1) ? 'Active' : 'Inactive' ?>
            </span>
        </td>
        <td>
            <?php if ($a['role'] === 'hospital_sub_admin'): ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                    <button class="btn btn-sm btn-outline-<?= ($a['is_active'] ?? 1) ? 'warning' : 'success' ?>">
                        <?= ($a['is_active'] ?? 1) ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this sub-admin?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="admin_id" value="<?= $a['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
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