<?php
/**
 * 점검 모드 관리 페이지
 * Bootstrap 5.3 + 주간/야간 모드
 */

session_start();

require_once __DIR__ . '/maintenance_config.php';
require_once __DIR__ . "/../dbconfig.php";
require_once __DIR__ . "/theme_helper.php";
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, defined('G5_MYSQL_PORT') ? G5_MYSQL_PORT : 3306);
if ($connect) { mysqli_set_charset($connect, 'utf8mb4'); loadThemeSettings($connect); }
require_once __DIR__ . "/includes/admin_auth.php";

// 로그인 체크
$is_logged_in = isset($_SESSION['ad_admin_logged_in']) && $_SESSION['ad_admin_logged_in'] === true;
if (!$is_logged_in) { header('Location: dashboard.php'); exit; }
require_permission('maintenance');
if (isset($_GET['logout'])) { session_destroy(); header('Location: dashboard.php'); exit; }

$success_message = '';
$error_message = '';

// 점검 모드 활성화/비활성화 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enable_maintenance'])) {
        $message = $_POST['message'] ?? '시스템 점검 중입니다';
        $start_time = $_POST['start_time'] ?? date('Y-m-d H:i:s');
        $end_time = $_POST['end_time'] ?? null;
        $admin_bypass = isset($_POST['admin_bypass']);
        $auto_end = isset($_POST['auto_end']);

        $options = [
            'admin_bypass' => $admin_bypass,
            'auto_end' => $auto_end,
            'contact_email' => $_POST['contact_email'] ?? 'support@seoulbeauty.app',
        ];

        if (enableMaintenance($message, $start_time, $end_time, $options)) {
            $success_message = '점검 모드가 활성화되었습니다.';
        } else {
            $error_message = '점검 모드 활성화 실패';
        }
    } elseif (isset($_POST['disable_maintenance'])) {
        if (disableMaintenance()) {
            $success_message = '점검 모드가 비활성화되었습니다.';
        } else {
            $error_message = '점검 모드 비활성화 실패';
        }
    }
}

$current_config = getMaintenanceConfig();
$is_maintenance = isMaintenanceMode();

// 공통 헤더 include
$admin_page_title = '점검 모드 관리';
$admin_current = 'maintenance';
include __DIR__ . '/includes/admin_header.php';
?>

<!-- 페이지 헤더 -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-tools"></i> 점검 모드 관리</h5>
    <span class="badge <?php echo $is_maintenance ? 'bg-warning text-dark' : 'bg-success'; ?> fs-6">
        <?php echo $is_maintenance ? '점검 모드 활성화' : '정상 운영'; ?>
    </span>
</div>

<!-- 알림 메시지 -->
<?php if ($success_message): ?>
<div class="alert alert-success alert-dismissible fade show py-2" role="alert">
    <?php echo htmlspecialchars($success_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($error_message): ?>
<div class="alert alert-danger alert-dismissible fade show py-2" role="alert">
    <?php echo htmlspecialchars($error_message); ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- 현재 상태 카드 -->
<div class="card mb-3 <?php echo $is_maintenance ? 'border-warning' : 'border-success'; ?>">
    <div class="card-header <?php echo $is_maintenance ? 'bg-warning bg-opacity-10 text-warning-emphasis' : 'bg-success bg-opacity-10 text-success-emphasis'; ?>">
        <i class="bi <?php echo $is_maintenance ? 'bi-exclamation-triangle' : 'bi-check-circle'; ?>"></i>
        <strong>현재 상태</strong>
    </div>
    <div class="card-body">
        <?php if ($current_config): ?>
        <div class="table-responsive">
            <table class="table table-sm table-borderless mb-0">
                <tbody>
                    <tr>
                        <th class="text-body-secondary" style="width:140px;">점검 메시지</th>
                        <td><?php echo htmlspecialchars($current_config['message']); ?></td>
                    </tr>
                    <?php if (isset($current_config['start_time'])): ?>
                    <tr>
                        <th class="text-body-secondary">시작 시간</th>
                        <td><?php echo $current_config['start_time']; ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (isset($current_config['end_time']) && $current_config['end_time']): ?>
                    <tr>
                        <th class="text-body-secondary">종료 시간</th>
                        <td><?php echo $current_config['end_time']; ?></td>
                    </tr>
                    <tr>
                        <th class="text-body-secondary">진행률</th>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 16px;">
                                    <div class="progress-bar bg-primary" role="progressbar"
                                         style="width: <?php echo getMaintenanceProgress(); ?>%"
                                         aria-valuenow="<?php echo getMaintenanceProgress(); ?>"
                                         aria-valuemin="0" aria-valuemax="100">
                                        <?php echo getMaintenanceProgress(); ?>%
                                    </div>
                                </div>
                                <small class="text-body-secondary"><?php echo getMaintenanceProgress(); ?>%</small>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th class="text-body-secondary">관리자 우회</th>
                        <td>
                            <?php if ($current_config['admin_bypass'] ?? false): ?>
                            <span class="badge bg-success">활성화</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">비활성화</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="text-body-secondary">자동 종료</th>
                        <td>
                            <?php if ($current_config['auto_end'] ?? false): ?>
                            <span class="badge bg-success">활성화</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">비활성화</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if (isset($current_config['contact_email'])): ?>
                    <tr>
                        <th class="text-body-secondary">문의 이메일</th>
                        <td><?php echo htmlspecialchars($current_config['contact_email']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (isset($current_config['created_at'])): ?>
                    <tr>
                        <th class="text-body-secondary">설정 시간</th>
                        <td><?php echo $current_config['created_at']; ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($is_maintenance): ?>
        <form method="POST" class="mt-3">
            <button type="submit" name="disable_maintenance" class="btn btn-success btn-sm"
                    onclick="return confirm('점검 모드를 해제하시겠습니까?')">
                <i class="bi bi-check-circle"></i> 점검 모드 해제
            </button>
        </form>
        <?php endif; ?>

        <?php else: ?>
        <p class="text-body-secondary mb-0">점검 모드가 설정된 적이 없습니다.</p>
        <?php endif; ?>
    </div>
</div>

<!-- 점검 모드 활성화 폼 -->
<?php if (!$is_maintenance): ?>
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-gear"></i> <strong>점검 모드 활성화</strong>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label for="message" class="form-label fw-semibold">점검 메시지 <span class="text-danger">*</span></label>
                <textarea name="message" id="message" class="form-control form-control-sm" rows="3" required
                          placeholder="예: DB 성능 개선 작업 중입니다">시스템 점검 중입니다. 잠시 후 다시 접속해주세요.</textarea>
                <div class="form-text">사용자에게 표시될 메시지입니다.</div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label for="start_time" class="form-label fw-semibold">시작 시간</label>
                    <input type="datetime-local" name="start_time" id="start_time"
                           class="form-control form-control-sm" value="<?php echo date('Y-m-d\TH:i'); ?>">
                    <div class="form-text">비워두면 즉시 시작</div>
                </div>
                <div class="col-md-6">
                    <label for="end_time" class="form-label fw-semibold">종료 시간 (선택)</label>
                    <input type="datetime-local" name="end_time" id="end_time"
                           class="form-control form-control-sm">
                    <div class="form-text">예약 종료 시간</div>
                </div>
            </div>

            <div class="mb-3">
                <label for="contact_email" class="form-label fw-semibold">문의 이메일</label>
                <input type="email" name="contact_email" id="contact_email"
                       class="form-control form-control-sm" value="support@seoulbeauty.app"
                       placeholder="support@seoulbeauty.app">
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="admin_bypass" id="admin_bypass" class="form-check-input" checked>
                    <label class="form-check-label" for="admin_bypass">관리자 우회 허용 (관리자는 계속 접속 가능)</label>
                </div>
            </div>

            <div class="mb-3">
                <div class="form-check">
                    <input type="checkbox" name="auto_end" id="auto_end" class="form-check-input" checked>
                    <label class="form-check-label" for="auto_end">자동 종료 (종료 시간에 자동으로 점검 모드 해제)</label>
                </div>
            </div>

            <button type="submit" name="enable_maintenance" class="btn btn-danger btn-sm"
                    onclick="return confirm('점검 모드를 시작하시겠습니까? 일반 사용자가 차단됩니다.')">
                <i class="bi bi-tools"></i> 점검 모드 시작
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- 빠른 실행 -->
<div class="card mb-3">
    <div class="card-header">
        <i class="bi bi-lightning"></i> <strong>빠른 실행</strong>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-4">
                <form method="POST">
                    <input type="hidden" name="message" value="긴급 점검 중입니다">
                    <input type="hidden" name="admin_bypass" value="1">
                    <button type="submit" name="enable_maintenance" class="btn btn-outline-primary btn-sm w-100"
                            <?php echo $is_maintenance ? 'disabled' : ''; ?>
                            onclick="return confirm('즉시 점검 모드를 시작하시겠습니까?')">
                        <i class="bi bi-lightning"></i> 즉시 점검 (무기한)
                    </button>
                </form>
            </div>
            <div class="col-md-4">
                <form method="POST">
                    <input type="hidden" name="message" value="시스템 업데이트 중입니다 (약 30분 소요)">
                    <input type="hidden" name="end_time" value="<?php echo date('Y-m-d H:i:s', strtotime('+30 minutes')); ?>">
                    <input type="hidden" name="admin_bypass" value="1">
                    <input type="hidden" name="auto_end" value="1">
                    <button type="submit" name="enable_maintenance" class="btn btn-outline-primary btn-sm w-100"
                            <?php echo $is_maintenance ? 'disabled' : ''; ?>
                            onclick="return confirm('30분 점검 모드를 시작하시겠습니까?')">
                        <i class="bi bi-clock"></i> 30분 점검
                    </button>
                </form>
            </div>
            <div class="col-md-4">
                <form method="POST">
                    <input type="hidden" name="message" value="정기 점검 중입니다 (새벽 2시~4시)">
                    <input type="hidden" name="start_time" value="<?php echo date('Y-m-d'); ?> 02:00:00">
                    <input type="hidden" name="end_time" value="<?php echo date('Y-m-d'); ?> 04:00:00">
                    <input type="hidden" name="admin_bypass" value="1">
                    <input type="hidden" name="auto_end" value="1">
                    <button type="submit" name="enable_maintenance" class="btn btn-outline-primary btn-sm w-100"
                            <?php echo $is_maintenance ? 'disabled' : ''; ?>
                            onclick="return confirm('야간 점검 예약을 하시겠습니까?')">
                        <i class="bi bi-moon-stars"></i> 야간 점검 예약
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- 사용 가이드 -->
<div class="card mb-3">
    <div class="card-header bg-info bg-opacity-10 text-info-emphasis">
        <i class="bi bi-info-circle"></i> <strong>사용 가이드</strong>
    </div>
    <div class="card-body">
        <ul class="list-unstyled mb-0 small text-body-secondary">
            <li class="mb-1"><i class="bi bi-dot"></i> 점검 모드 활성화 시 일반 사용자는 점검 페이지로 리다이렉트됩니다</li>
            <li class="mb-1"><i class="bi bi-dot"></i> 관리자 우회가 활성화되면 로그인한 관리자는 계속 접속 가능합니다</li>
            <li class="mb-1"><i class="bi bi-dot"></i> 자동 종료가 활성화되면 종료 시간에 점검 모드가 자동으로 해제됩니다</li>
            <li><i class="bi bi-dot"></i> 긴급 상황 시 "즉시 점검" 버튼으로 빠르게 활성화할 수 있습니다</li>
        </ul>
    </div>
</div>

<?php
include __DIR__ . '/includes/admin_footer.php';
?>
