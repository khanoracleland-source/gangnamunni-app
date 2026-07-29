<?php
/**
 * Hospital Suspension Management
 * Super admin only - suspend/reactivate hospitals for non-payment
 */
session_start();
$admin_page_title = 'Hospital Suspension';
$admin_current = 'hospital_suspension';

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB Error");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
if (($_SESSION['ad_admin_role'] ?? '') !== 'super_admin') {
    http_response_code(403);
    exit('Access denied');
}

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $hid = (int)$_POST['hospital_id'];

    if ($action === 'suspend') {
        $reason = mysqli_real_escape_string($connect, $_POST['reason'] ?? 'Non-payment');
        $stmt = $connect->prepare("INSERT INTO gangnamunni_hospital_suspension (hospital_id, hospital_name, status, suspension_reason, suspended_at, suspended_by)
            VALUES (?, (SELECT name FROM gangnamunni_hospitals WHERE id = ?), 'suspended', ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE status='suspended', suspension_reason=?, suspended_at=NOW(), suspended_by=?");
        $admin_id = $_SESSION['ad_admin_id'];
        $stmt->bind_param('iisisi', $hid, $hid, $reason, $admin_id, $reason, $admin_id);
        $stmt->execute();
        $msg = "Hospital #{$hid} suspended.";
    } elseif ($action === 'reactivate') {
        $stmt = $connect->prepare("UPDATE gangnamunni_hospital_suspension SET status='active', reactivated_at=NOW() WHERE hospital_id=?");
        $stmt->bind_param('i', $hid);
        $stmt->execute();
        $msg = "Hospital #{$hid} reactivated.";
    } elseif ($action === 'warning') {
        $stmt = $connect->prepare("UPDATE gangnamunni_hospital_suspension SET status='warning', warning_sent_at=NOW() WHERE hospital_id=?");
        $stmt->bind_param('i', $hid);
        $stmt->execute();
        $msg = "Warning sent to Hospital #{$hid}.";
    } elseif ($action === 'update_fee') {
        $fee = (float)$_POST['monthly_fee'];
        $due = $_POST['payment_due_date'] ?? null;
        $stmt = $connect->prepare("UPDATE gangnamunni_hospital_suspension SET monthly_fee=?, payment_due_date=? WHERE hospital_id=?");
        $stmt->bind_param('dsi', $fee, $due, $hid);
        $stmt->execute();
        $msg = "Fee updated for Hospital #{$hid}.";
    }
}

// Fetch data
$filter_status = $_GET['status'] ?? 'all';
$where = '';
if ($filter_status !== 'all') {
    $where = " WHERE s.status = '" . mysqli_real_escape_string($connect, $filter_status) . "'";
}
$hospitals = mysqli_query($connect, "
    SELECT h.id, h.name,
           COALESCE(s.status, 'active') as suspension_status,
           s.suspension_reason, s.suspended_at, s.warning_sent_at, s.reactivated_at,
           s.monthly_fee, s.payment_due_date, s.last_payment_date,
           (SELECT COUNT(*) FROM gangnamunni_admins WHERE hospital_id = h.id) as admin_count
    FROM gangnamunni_hospitals h
    LEFT JOIN gangnamunni_hospital_suspension s ON h.id = s.hospital_id
    {$where}
    ORDER BY s.status DESC, h.id
");

$stats = [
    'total' => 0, 'active' => 0, 'suspended' => 0, 'warning' => 0
];
$rows = [];
while ($row = mysqli_fetch_assoc($hospitals)) {
    $rows[] = $row;
    $stats['total']++;
    $st = $row['suspension_status'];
    if (isset($stats[$st])) $stats[$st]++;
}

include __DIR__ . '/includes/admin_header.php';
?>

<?php if (!empty($msg)): ?>
<div class="alert alert-success alert-dismissible fade show">
    <?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card text-center">
            <div class="card-body">
                <div class="fs-3 fw-bold"><?= $stats['total'] ?></div>
                <small class="text-muted">Total</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-center border-success">
            <div class="card-body">
                <div class="fs-3 fw-bold text-success"><?= $stats['active'] ?></div>
                <small class="text-muted">Active</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-center border-warning">
            <div class="card-body">
                <div class="fs-3 fw-bold text-warning"><?= $stats['warning'] ?></div>
                <small class="text-muted">Warning</small>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card text-center border-danger">
            <div class="card-body">
                <div class="fs-3 fw-bold text-danger"><?= $stats['suspended'] ?></div>
                <small class="text-muted">Suspended</small>
            </div>
        </div>
    </div>
</div>

<!-- Filter tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $filter_status==='all'?'active':'' ?>" href="?status=all">All</a></li>
    <li class="nav-item"><a class="nav-link <?= $filter_status==='active'?'active':'' ?>" href="?status=active">Active</a></li>
    <li class="nav-item"><a class="nav-link <?= $filter_status==='warning'?'active':'' ?>" href="?status=warning">Warning</a></li>
    <li class="nav-item"><a class="nav-link <?= $filter_status==='suspended'?'active':'' ?>" href="?status=suspended">Suspended</a></li>
</ul>

<!-- Hospital list -->
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead>
    <tr>
        <th>ID</th>
        <th>Hospital</th>
        <th>Status</th>
        <th>Monthly Fee</th>
        <th>Due Date</th>
        <th>Admins</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
<?php foreach ($rows as $row): ?>
    <tr>
        <td><?= $row['id'] ?></td>
        <td>
            <strong><?= htmlspecialchars($row['name']) ?></strong>
            <?php if ($row['suspension_reason']): ?>
                <br><small class="text-danger"><?= htmlspecialchars($row['suspension_reason']) ?></small>
            <?php endif; ?>
        </td>
        <td>
            <?php
            $badges = ['active' => 'success', 'warning' => 'warning', 'suspended' => 'danger'];
            $badge = $badges[$row['suspension_status']] ?? 'secondary';
            ?>
            <span class="badge bg-<?= $badge ?>"><?= ucfirst($row['suspension_status']) ?></span>
            <?php if ($row['suspended_at'] && $row['suspension_status'] === 'suspended'): ?>
                <br><small class="text-muted"><?= date('Y-m-d', strtotime($row['suspended_at'])) ?></small>
            <?php endif; ?>
        </td>
        <td>
            <form method="post" class="d-flex gap-1">
                <input type="hidden" name="action" value="update_fee">
                <input type="hidden" name="hospital_id" value="<?= $row['id'] ?>">
                <input type="number" name="monthly_fee" value="<?= $row['monthly_fee'] ?>" class="form-control form-control-sm" style="width:100px" step="0.01">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check"></i></button>
            </form>
        </td>
        <td>
            <form method="post" class="d-flex gap-1">
                <input type="hidden" name="action" value="update_fee">
                <input type="hidden" name="hospital_id" value="<?= $row['id'] ?>">
                <input type="hidden" name="monthly_fee" value="<?= $row['monthly_fee'] ?>">
                <input type="date" name="payment_due_date" value="<?= $row['payment_due_date'] ?>" class="form-control form-control-sm" style="width:140px">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-check"></i></button>
            </form>
        </td>
        <td><span class="badge bg-secondary"><?= $row['admin_count'] ?></span></td>
        <td>
            <?php if ($row['suspension_status'] === 'active'): ?>
                <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#suspendModal<?= $row['id'] ?>">
                    <i class="bi bi-pause-circle"></i> Warn
                </button>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#suspendModal<?= $row['id'] ?>">
                    <i class="bi bi-x-circle"></i> Suspend
                </button>
            <?php elseif ($row['suspension_status'] === 'warning'): ?>
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#suspendModal<?= $row['id'] ?>">
                    <i class="bi bi-x-circle"></i> Suspend
                </button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="reactivate">
                    <input type="hidden" name="hospital_id" value="<?= $row['id'] ?>">
                    <button class="btn btn-sm btn-success"><i class="bi bi-play-circle"></i> Clear</button>
                </form>
            <?php else: ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="reactivate">
                    <input type="hidden" name="hospital_id" value="<?= $row['id'] ?>">
                    <button class="btn btn-sm btn-success"><i class="bi bi-play-circle"></i> Reactivate</button>
                </form>
            <?php endif; ?>
        </td>
    </tr>

    <!-- Suspend Modal -->
    <div class="modal fade" id="suspendModal<?= $row['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Suspend Hospital: <?= htmlspecialchars($row['name']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="hospital_id" value="<?= $row['id'] ?>">
                        <div class="mb-3">
                            <label class="form-label">Action</label>
                            <select name="action" class="form-select">
                                <option value="warning">Send Warning</option>
                                <option value="suspend">Suspend Immediately</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="e.g., Non-payment for 2 months"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</tbody>
</table>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
