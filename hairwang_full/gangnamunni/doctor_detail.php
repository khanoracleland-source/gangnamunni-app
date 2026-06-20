<?php
/**
 * 의사 상세 페이지
 * - gangnamunni_doctors 테이블 기반
 * - 프로필, 약력, 진료 스케줄, 소속 병원
 */
require_once __DIR__ . '/includes/init.php';

$doctor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($doctor_id <= 0) {
    header('Location: index.php');
    exit;
}

// 의사 정보 조회
$stmt = $connect->prepare("SELECT d.*, h.name as hospital_name, h.address as hospital_address, h.phone as hospital_phone, h.profile_image_url as hospital_image, h.business_hours as hospital_hours, h.latitude, h.longitude
    FROM gangnamunni_doctors d
    LEFT JOIN gangnamunni_hospitals h ON d.hospital_id = h.id
    WHERE d.id = ?");
$stmt->bind_param('i', $doctor_id);
$stmt->execute();
$doctor = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$doctor) {
    die(term("staff") . " 정보를 찾을 수 없습니다.");
}

// 의사 스케줄 파싱
$schedule = json_decode($doctor['schedule'] ?? '{}', true) ?: [];
$day_names = ['월' => '월요일', '화' => '화요일', '수' => '수요일', '목' => '목요일', '금' => '금요일', '토' => '토요일', '일' => '일요일'];

// 오늘 요일
$today_idx = date('N'); // 1=월 ~ 7=일
$day_keys = ['월','화','수','목','금','토','일'];
$today_key = $day_keys[$today_idx - 1] ?? '월';
$today_schedule = $schedule[$today_key] ?? '';
$is_today_off = ($today_schedule === '휴무' || $today_schedule === '');

// 상태 텍스트
$status_map = ['active' => term('appointment', '진료') . ' 중', 'vacation' => '휴가', 'leave' => '휴직', 'resigned' => '퇴직'];
$status_text = $status_map[$doctor['status'] ?? 'active'] ?? '진료 중';
$is_active = ($doctor['status'] ?? 'active') === 'active';

// 테마 / 네비게이션
if (!function_exists('getSettingValue')) {
    require_once __DIR__ . '/includes/settings_helper.php';
}
$page_title = htmlspecialchars($doctor['name']) . ' - ' . term("staff") . ' 상세';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --color-primary: #AE1932;
            --color-bg: #ffffff;
            --color-bg-light: #f8f9fa;
            --color-text: #222;
            --color-text-secondary: #666;
            --color-border: #eee;
        }
        [data-bs-theme="dark"] {
            --color-bg: #1a1a2e;
            --color-bg-light: #16213e;
            --color-text: #e0e0e0;
            --color-text-secondary: #aaa;
            --color-border: #333;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: var(--color-bg); color: var(--color-text); padding-bottom: 80px; }
        .container { max-width: 640px; margin: 0 auto; }

        /* 프로필 헤더 */
        .profile-header {
            text-align: center;
            padding: 32px 20px 20px;
            background: linear-gradient(135deg, var(--color-primary) 0%, #ff8a50 100%);
            color: white;
        }
        .profile-photo {
            width: 100px; height: 100px; border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: calc(48px * var(--fs-scale,1) * var(--fs-page,1)); margin: 0 auto 12px;
            border: 3px solid rgba(255,255,255,0.5);
            overflow: hidden;
        }
        .profile-photo img { width: 100%; height: 100%; object-fit: cover; }
        .profile-name { font-size: calc(22px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 4px; }
        .profile-specialty { font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); opacity: 0.9; margin-bottom: 8px; }
        .profile-badges { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .badge-pill {
            padding: 4px 12px; border-radius: 20px; font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1));
            background: rgba(255,255,255,0.2); backdrop-filter: blur(4px);
        }
        .badge-status { background: #10b981; }
        .badge-status.off { background: var(--color-primary, #AE1932); }

        /* 섹션 */
        .section { padding: 16px 20px; border-bottom: 1px solid var(--color-border); }
        .section-title { font-size: calc(15px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }

        /* 정보 행 */
        .info-row { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; }
        .info-label { font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--color-text-secondary); min-width: 70px; flex-shrink: 0; }
        .info-value { font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); flex: 1; }

        /* 스케줄 테이블 */
        .schedule-table { width: 100%; border-collapse: collapse; font-size: calc(13px * var(--fs-scale,1) * var(--fs-page,1)); }
        .schedule-table td { padding: 8px 10px; border-bottom: 1px solid var(--color-border); }
        .schedule-table .day { font-weight: 600; width: 70px; }
        .schedule-table .today { background: rgba(255, 84, 15, 0.08); }
        .schedule-table .off { color: var(--color-primary, #AE1932); }

        /* 약력 */
        .bio-text { font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); line-height: 1.7; white-space: pre-line; color: var(--color-text-secondary); }

        /* 병원 카드 */
        .hospital-card {
            display: flex; gap: 12px; padding: 12px;
            border: 1px solid var(--color-border); border-radius: 10px;
            text-decoration: none; color: inherit; cursor: pointer;
        }
        .hospital-card:hover { border-color: var(--color-primary); }
        .hospital-card-img {
            width: 60px; height: 60px; border-radius: 8px;
            background: var(--color-bg-light); display: flex; align-items: center; justify-content: center;
            font-size: calc(28px * var(--fs-scale,1) * var(--fs-page,1)); flex-shrink: 0; overflow: hidden;
        }
        .hospital-card-img img { width: 100%; height: 100%; object-fit: cover; }
        .hospital-card-name { font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; margin-bottom: 2px; }
        .hospital-card-addr { font-size: calc(12px * var(--fs-scale,1) * var(--fs-page,1)); color: var(--color-text-secondary); }

        /* CTA 버튼 */
        .cta-bar {
            position: fixed; bottom: 0; left: 0; right: 0;
            background: var(--color-bg); border-top: 1px solid var(--color-border);
            padding: 12px 20px; display: flex; gap: 10px;
            max-width: 640px; margin: 0 auto; z-index: 100;
        }
        .cta-btn {
            flex: 1; padding: 12px; border-radius: 8px; border: none;
            font-size: calc(14px * var(--fs-scale,1) * var(--fs-page,1)); font-weight: 600; cursor: pointer;
            text-decoration: none; text-align: center; display: flex;
            align-items: center; justify-content: center; gap: 6px;
        }
        .cta-consult { background: var(--color-bg-light); color: var(--color-text); border: 1px solid var(--color-border); }
        .cta-book { background: var(--color-primary); color: white; }

        /* 뒤로가기 */
        .back-btn {
            position: absolute; top: 12px; left: 12px; z-index: 10;
            width: 36px; height: 36px; border-radius: 50%;
            background: rgba(0,0,0,0.3); color: white; border: none;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; font-size: calc(18px * var(--fs-scale,1) * var(--fs-page,1));
        }
    </style>
    <script>
        // 다크모드 anti-flash
        (function(){
            var t = localStorage.getItem('theme') || localStorage.getItem('admin_theme');
            if (t === 'dark') document.documentElement.setAttribute('data-bs-theme', 'dark');
        })();
    </script>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>
<?php include __DIR__ . '/includes/global_nav.php'; ?>

<div class="container" style="position:relative;">
    <!-- 뒤로가기 -->
    <button class="back-btn" onclick="history.back()"><i class="bi bi-chevron-left"></i></button>

    <!-- 프로필 헤더 -->
    <div class="profile-header">
        <div class="profile-photo">
            <?php if (!empty($doctor['photo_url'])): ?>
            <img src="<?php echo htmlspecialchars($doctor['photo_url']); ?>" alt="<?php echo htmlspecialchars($doctor['name']); ?>">
            <?php else: ?>
            👨‍⚕️
            <?php endif; ?>
        </div>
        <div class="profile-name"><?php echo htmlspecialchars($doctor['name']); ?></div>
        <div class="profile-specialty"><?php echo htmlspecialchars($doctor['specialty'] ?: '전문의'); ?></div>
        <div class="profile-badges">
            <?php if ($doctor['experience'] > 0): ?>
            <span class="badge-pill">경력 <?php echo $doctor['experience']; ?>년</span>
            <?php endif; ?>
            <span class="badge-pill badge-status <?php echo $is_active ? '' : 'off'; ?>"><?php echo $status_text; ?></span>
            <?php if (!empty($doctor['qualification'])): ?>
            <span class="badge-pill"><?php echo htmlspecialchars($doctor['qualification']); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 오늘 진료 정보 -->
    <div class="section">
        <div class="section-title">
            <?php if ($is_today_off || !$is_active): ?>
            <span style="color:#ef4444;">🔴 오늘 <?php echo term('appointment', '진료'); ?> 없음</span>
            <?php else: ?>
            <span style="color:#10b981;">🟢 오늘 <?php echo term('appointment', '진료'); ?> 중</span>
            <span style="font-weight:400; font-size:13px; color:var(--color-text-secondary);">(<?php echo htmlspecialchars($today_schedule); ?>)</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 기본 정보 -->
    <div class="section">
        <div class="section-title"><i class="bi bi-person-badge"></i> 기본 정보</div>
        <?php if (!empty($doctor['specialty'])): ?>
        <div class="info-row">
            <span class="info-label">전문 분야</span>
            <span class="info-value"><?php echo htmlspecialchars($doctor['specialty']); ?></span>
        </div>
        <?php endif; ?>
        <?php if ($doctor['experience'] > 0): ?>
        <div class="info-row">
            <span class="info-label">경력</span>
            <span class="info-value"><?php echo $doctor['experience']; ?>년</span>
        </div>
        <?php endif; ?>
        <?php if (!empty($doctor['qualification'])): ?>
        <div class="info-row">
            <span class="info-label">자격</span>
            <span class="info-value"><?php echo htmlspecialchars($doctor['qualification']); ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($doctor['email'])): ?>
        <div class="info-row">
            <span class="info-label">이메일</span>
            <span class="info-value"><?php echo htmlspecialchars($doctor['email']); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- 약력 / 소개 -->
    <?php if (!empty($doctor['bio'])): ?>
    <div class="section">
        <div class="section-title"><i class="bi bi-journal-text"></i> 약력</div>
        <div class="bio-text"><?php echo nl2br(htmlspecialchars($doctor['bio'])); ?></div>
    </div>
    <?php endif; ?>

    <!-- 진료 스케줄 -->
    <?php if (!empty($schedule)): ?>
    <div class="section">
        <div class="section-title"><i class="bi bi-clock"></i> <?php echo term('appointment', '진료'); ?> 시간</div>
        <table class="schedule-table">
            <?php foreach ($day_keys as $dk):
                $time_val = $schedule[$dk] ?? '-';
                $is_off = ($time_val === '휴무' || $time_val === '-' || $time_val === '');
                $is_today_row = ($dk === $today_key);
            ?>
            <tr class="<?php echo $is_today_row ? 'today' : ''; ?>">
                <td class="day"><?php echo $day_names[$dk]; ?><?php if ($is_today_row): ?> <span style="font-size:11px;color:var(--color-primary);">오늘</span><?php endif; ?></td>
                <td class="<?php echo $is_off ? 'off' : ''; ?>"><?php echo htmlspecialchars($time_val ?: '휴무'); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <!-- 소속 병원 -->
    <?php if (!empty($doctor['hospital_name'])): ?>
    <div class="section">
        <div class="section-title"><i class="bi bi-hospital"></i> 소속 <?php echo term("business"); ?></div>
        <a href="detail.php?id=<?php echo $doctor['hospital_id']; ?>" class="hospital-card">
            <div class="hospital-card-img">
                <?php if (!empty($doctor['hospital_image'])): ?>
                <img src="<?php echo htmlspecialchars($doctor['hospital_image']); ?>" alt="">
                <?php else: ?>
                🏥
                <?php endif; ?>
            </div>
            <div>
                <div class="hospital-card-name"><?php echo htmlspecialchars($doctor['hospital_name']); ?></div>
                <?php if (!empty($doctor['hospital_address'])): ?>
                <div class="hospital-card-addr"><?php echo htmlspecialchars($doctor['hospital_address']); ?></div>
                <?php endif; ?>
                <?php if (!empty($doctor['hospital_phone'])): ?>
                <div class="hospital-card-addr" style="margin-top:2px;"><?php echo htmlspecialchars($doctor['hospital_phone']); ?></div>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <!-- 소속 병원 지도 -->
    <?php
    $dr_lat = floatval($doctor['latitude'] ?? 0);
    $dr_lng = floatval($doctor['longitude'] ?? 0);
    // DB에서 직접 좌표 조회 (JOIN에서 못 가져온 경우)
    if ($dr_lat == 0 && $dr_lng == 0 && !empty($doctor['hospital_id'])) {
        $loc_stmt = $connect->prepare("SELECT latitude, longitude FROM gangnamunni_hospitals WHERE id = ?");
        $loc_stmt->bind_param('i', $doctor['hospital_id']);
        $loc_stmt->execute();
        $loc_row = $loc_stmt->get_result()->fetch_assoc();
        if ($loc_row) { $dr_lat = floatval($loc_row['latitude']); $dr_lng = floatval($loc_row['longitude']); }
        $loc_stmt->close();
    }
    $map_config = [
        'container_id' => 'doctorMap',
        'latitude'     => $dr_lat,
        'longitude'    => $dr_lng,
        'address'      => $doctor['hospital_address'] ?? '',
        'name'         => $doctor['hospital_name'] ?? '',
        'height'       => '220px',
    ];
    include __DIR__ . '/includes/kakao_map.php';
    ?>
</div>

<!-- 하단 CTA -->
<?php if ($is_active): ?>
<div class="cta-bar">
    <a href="detail.php?id=<?php echo $doctor['hospital_id']; ?>#consultModal" class="cta-btn cta-consult" onclick="event.preventDefault(); location.href='detail.php?id=<?php echo $doctor['hospital_id']; ?>&open_consult=doctor&doctor_id=<?php echo $doctor_id; ?>';">
        <i class="bi bi-chat-dots"></i> <?php echo term("consultation"); ?>
    </a>
    <a href="booking.php?id=<?php echo $doctor['hospital_id']; ?>&doctor=<?php echo $doctor_id; ?>" class="cta-btn cta-book">
        <i class="bi bi-calendar-plus"></i> <?php echo term('booking', '예약'); ?>
    </a>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/bottom_navigation.php'; ?>
</body>
</html>
