<?php
/**
 * 병원 입점 안내 — 관리자 래퍼
 * 오른쪽 콘텐츠 영역에 hospital_landing.php를 iframe으로 미리보기
 * + 새 창 열기 버튼
 * @created 2026-04-03
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$admin_page_title = '병원 입점 안내';
$admin_current = 'hospital_landing';

require_once __DIR__ . '/dbconfig.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("MySQL 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

require_admin_login();

$landing_url = SITE_FULL_URL . 'hospital_landing.php';

require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-info-circle me-2"></i>병원 입점 안내 페이지</h4>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($landing_url) ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-up-right me-1"></i>새 창에서 보기
            </a>
            <button class="btn btn-outline-secondary btn-sm" onclick="document.getElementById('previewFrame').src = document.getElementById('previewFrame').src;">
                <i class="bi bi-arrow-clockwise me-1"></i>새로고침
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <iframe id="previewFrame"
                    src="<?= htmlspecialchars($landing_url) ?>"
                    style="width: 100%; height: calc(100vh - 180px); border: none; border-radius: 0 0 8px 8px;">
            </iframe>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/admin_footer.php';
mysqli_close($connect);
?>
