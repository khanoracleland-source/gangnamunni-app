<?php
session_start();
$admin_page_title = '쿠폰 관리';
$admin_current = 'coupons';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
require_once __DIR__ . '/includes/dev_mode_helper.php';
if (!isset($connect) || !$connect) {
    $connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
    if ($connect) mysqli_set_charset($connect, 'utf8mb4');
}
if (!$connect) die('DB 연결 실패');
loadThemeSettings($connect);
require_admin_login();
require_permission('coupons');

// 테이블 확인
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_coupons (
    id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(200) NOT NULL,
    description TEXT, discount_type ENUM('percent','fixed') DEFAULT 'fixed', discount_value DECIMAL(10,2) DEFAULT 0,
    min_order_amount DECIMAL(10,2) DEFAULT 0, max_discount DECIMAL(10,2) DEFAULT 0,
    total_quantity INT DEFAULT -1, claimed_count INT DEFAULT 0, hospital_id INT DEFAULT NULL,
    starts_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL, is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// POST 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        if (!$code) $code = strtoupper(substr(md5(time()), 0, 8));
        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_coupons (code,name,description,discount_type,discount_value,total_quantity,starts_at,expires_at) VALUES (?,?,?,?,?,?,?,?)");
        $desc = $_POST['description'] ?? '';
        $dtype = $_POST['discount_type'] ?? 'fixed';
        $dval = floatval($_POST['discount_value'] ?? 0);
        $qty = intval($_POST['total_quantity'] ?? -1);
        $starts = $_POST['starts_at'] ?: null;
        $expires = $_POST['expires_at'] ?: null;
        mysqli_stmt_bind_param($stmt, 'ssssdiss', $code, $name, $desc, $dtype, $dval, $qty, $starts, $expires);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        header('Location: admin_coupons.php?msg=created'); exit;
    } elseif ($act === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) mysqli_query($connect, "DELETE FROM gangnamunni_coupons WHERE id=$id");
        header('Location: admin_coupons.php?msg=deleted'); exit;
    } elseif ($act === 'generate_demo' && isDevMode($connect)) {
        // 데모 쿠폰 5종 생성
        $demo_coupons = [
            ['name' => '신규 가입 축하 쿠폰', 'desc' => '신규 가입 회원에게 지급되는 환영 쿠폰', 'type' => 'fixed', 'val' => 10000, 'qty' => 100],
            ['name' => '봄맞이 20% 할인', 'desc' => '2026년 봄 시즌 한정 할인 쿠폰', 'type' => 'percent', 'val' => 20, 'qty' => 50],
            ['name' => '후기 작성 감사 쿠폰', 'desc' => '후기 작성 시 자동 지급', 'type' => 'fixed', 'val' => 5000, 'qty' => -1],
            ['name' => 'VIP 전용 30% 할인', 'desc' => 'VIP 등급 이상 회원 전용', 'type' => 'percent', 'val' => 30, 'qty' => 30],
            ['name' => '첫 예약 할인 쿠폰', 'desc' => '첫 예약 시 사용 가능', 'type' => 'fixed', 'val' => 20000, 'qty' => 200],
        ];
        $cnt = 0;
        foreach ($demo_coupons as $dc) {
            $code = 'DEMO' . strtoupper(substr(md5(time() . $cnt), 0, 6));
            $starts = date('Y-m-d H:i:s');
            $expires = date('Y-m-d H:i:s', strtotime('+90 days'));
            $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_coupons (code,name,description,discount_type,discount_value,total_quantity,starts_at,expires_at) VALUES (?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'ssssdiss', $code, $dc['name'], $dc['desc'], $dc['type'], $dc['val'], $dc['qty'], $starts, $expires);
            if (mysqli_stmt_execute($stmt)) $cnt++;
            mysqli_stmt_close($stmt);
        }
        header('Location: admin_coupons.php?msg=demo_created&cnt=' . $cnt); exit;
    } elseif ($act === 'toggle') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) mysqli_query($connect, "UPDATE gangnamunni_coupons SET is_active = NOT is_active WHERE id=$id");
        header('Location: admin_coupons.php'); exit;
    }
}

// 목록 조회
$coupons = [];
$res = mysqli_query($connect, "SELECT c.*, (SELECT COUNT(*) FROM gangnamunni_coupon_claims WHERE coupon_id=c.id) as claim_count FROM gangnamunni_coupons c ORDER BY c.created_at DESC");
if ($res) while ($r = mysqli_fetch_assoc($res)) $coupons[] = $r;

require_once __DIR__ . '/includes/admin_header.php';
?>
<?php if (isDevMode($connect)): ?>
<div class="dev-mode-bar mb-3 p-2 rounded" style="background:linear-gradient(135deg,#ff6b35,#f7931e);border:2px dashed #fff;">
<div class="d-flex align-items-center justify-content-between">
<span class="text-white fw-bold"><i class="bi bi-gear-fill me-1"></i> DEV MODE</span>
<form method="post" class="d-inline"><input type="hidden" name="action" value="generate_demo">
<button type="submit" class="btn btn-light btn-sm fw-bold"><i class="bi bi-lightning-fill text-warning me-1"></i>테스트 쿠폰 5종 생성</button></form>
</div></div>
<?php endif; ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">쿠폰 관리 <span class="badge bg-primary"><?php echo count($coupons); ?></span></h4>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal"><i class="bi bi-plus-lg"></i> 쿠폰 생성</button>
</div>
<?php if (!empty($_GET['msg'])): ?><div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> 처리 완료<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<div class="table-responsive">
<table class="table table-hover align-middle">
<thead><tr><th>ID</th><th>코드</th><th>이름</th><th>할인</th><th>수량</th><th>사용</th><th>기간</th><th>상태</th><th>관리</th></tr></thead>
<tbody>
<?php foreach ($coupons as $c): ?>
<tr>
    <td><?php echo $c['id']; ?></td>
    <td><code><?php echo htmlspecialchars($c['code']); ?></code></td>
    <td><?php echo htmlspecialchars($c['name']); ?></td>
    <td><?php echo $c['discount_type']==='percent' ? $c['discount_value'].'%' : number_format($c['discount_value']).'원'; ?></td>
    <td><?php echo $c['total_quantity'] < 0 ? '무제한' : $c['total_quantity']; ?></td>
    <td><?php echo $c['claim_count']; ?></td>
    <td style="font-size:12px;"><?php echo ($c['starts_at'] ?? '-').' ~ '.($c['expires_at'] ?? '-'); ?></td>
    <td>
        <form method="post" style="display:inline;"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo $c['id']; ?>">
        <button type="submit" class="badge <?php echo $c['is_active'] ? 'bg-success' : 'bg-secondary'; ?>" style="border:none;cursor:pointer;"><?php echo $c['is_active'] ? '활성' : '비활성'; ?></button></form>
    </td>
    <td><form method="post" style="display:inline;" onsubmit="return confirm('삭제?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo $c['id']; ?>"><button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>

<!-- 생성 모달 -->
<div class="modal fade" id="createModal"><div class="modal-dialog"><div class="modal-content"><form method="post">
<input type="hidden" name="action" value="create">
<div class="modal-header"><h5 class="modal-title">쿠폰 생성</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label class="form-label">쿠폰명 *</label><input type="text" name="name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">코드 (미입력 시 자동생성)</label><input type="text" name="code" class="form-control" placeholder="AUTO"></div>
    <div class="mb-3"><label class="form-label">설명</label><textarea name="description" class="form-control" rows="2"></textarea></div>
    <div class="row mb-3"><div class="col-6"><label class="form-label">할인 유형</label><select name="discount_type" class="form-select"><option value="fixed">금액</option><option value="percent">퍼센트</option></select></div>
    <div class="col-6"><label class="form-label">할인 값</label><input type="number" name="discount_value" class="form-control" step="0.01" value="0"></div></div>
    <div class="mb-3"><label class="form-label">수량 (-1=무제한)</label><input type="number" name="total_quantity" class="form-control" value="-1"></div>
    <div class="row mb-3"><div class="col-6"><label class="form-label">시작일</label><input type="datetime-local" name="starts_at" class="form-control"></div>
    <div class="col-6"><label class="form-label">종료일</label><input type="datetime-local" name="expires_at" class="form-control"></div></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button><button type="submit" class="btn btn-primary">생성</button></div>
</form></div></div></div>
<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
