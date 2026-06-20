<?php
/**
 * 메뉴 안내 페이지
 * 모든 관리 메뉴의 기능을 한눈에 확인
 */
session_start();
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/includes/admin_auth.php';

$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die("DB 연결 실패");
mysqli_set_charset($connect, 'utf8mb4');
require_once __DIR__ . '/theme_helper.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
loadThemeSettings($connect);


if (!check_admin_login()) { header('Location: dashboard.php'); exit; }

$admin_page_title = '메뉴 안내';
$admin_current = 'menu_guide';
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="row g-3">
<?php
// $admin_menus와 $menu_descriptions는 admin_header.php에서 이미 정의됨
$group_index = 0;
foreach ($admin_menus as $menu):
    // 구분선 → 시각적 구분
    if (isset($menu['type']) && $menu['type'] === 'divider'):
        if ($group_index > 0): ?>
        <div class="col-12"><hr class="my-1" style="opacity:0.2;"></div>
        <?php endif;
        $group_index++;
        continue;
    endif;

    // 그룹 메뉴
    if (isset($menu['type']) && $menu['type'] === 'group'):
        // 권한 체크
        if (function_exists('check_permission') && !check_permission($menu['key'])) continue;
?>
    <div class="col-12">
        <h6 class="text-body-secondary fw-bold mt-2 mb-2">
            <i class="bi <?php echo $menu['icon']; ?>"></i> <?php echo $menu['label']; ?>
        </h6>
    </div>
    <?php foreach ($menu['children'] as $child):
        if (function_exists('check_permission') && !check_permission($child['key'])) continue;
        $desc = $menu_descriptions[$child['key']] ?? '';
        $is_external = isset($child['target']) && $child['target'] === '_blank';
    ?>
    <div class="col-md-6 col-lg-4">
        <a href="<?php echo $child['url']; ?>"<?php echo $is_external ? ' target="_blank"' : ''; ?>
           class="card text-decoration-none h-100 border" style="transition:box-shadow 0.2s;"
           onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)'" onmouseout="this.style.boxShadow='none'">
            <div class="card-body py-3">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi <?php echo $child['icon']; ?> fs-5 text-primary mt-1" style="min-width:20px;"></i>
                    <div>
                        <div class="fw-semibold text-body" style="font-size:14px;">
                            <?php echo $child['label']; ?>
                            <?php if ($is_external): ?><i class="bi bi-box-arrow-up-right" style="font-size:10px;"></i><?php endif; ?>
                        </div>
                        <?php if ($desc): ?>
                        <div class="text-body-secondary" style="font-size:12px;line-height:1.5;"><?php echo htmlspecialchars($desc); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>

<?php
    // 단일 메뉴
    else:
        if (function_exists('check_permission') && !check_permission($menu['key'])) continue;
        $desc = $menu_descriptions[$menu['key']] ?? '';
        // 메뉴 안내 자체는 건너뛰기
        if ($menu['key'] === 'menu_guide') continue;
?>
    <div class="col-md-6 col-lg-4">
        <a href="<?php echo $menu['url']; ?>" class="card text-decoration-none h-100 border"
           style="transition:box-shadow 0.2s;"
           onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,0.1)'" onmouseout="this.style.boxShadow='none'">
            <div class="card-body py-3">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi <?php echo $menu['icon']; ?> fs-5 text-primary mt-1" style="min-width:20px;"></i>
                    <div>
                        <div class="fw-semibold text-body" style="font-size:14px;"><?php echo $menu['label']; ?></div>
                        <?php if ($desc): ?>
                        <div class="text-body-secondary" style="font-size:12px;line-height:1.5;"><?php echo htmlspecialchars($desc); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </a>
    </div>
<?php
    endif;
endforeach;
?>
</div>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
