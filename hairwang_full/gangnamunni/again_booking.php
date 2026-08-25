<?php
require_once __DIR__ . '/includes/init.php';
if (!function_exists('term')) { require_once __DIR__ . '/includes/industry_terms.php'; }
if (!function_exists('getSettingValue') && file_exists(__DIR__ . '/includes/settings_helper.php')) { require_once __DIR__ . '/includes/settings_helper.php'; }

global $is_member, $member;
$is_user_logged_in = isset($is_member) && $is_member && isset($member['mb_id']) && $member['mb_id'];

$booking_id  = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$hospital_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// 이전 예약 정보 조회
$prev_booking = [];
if ($booking_id > 0) {
    $stmt = $connect->prepare("SELECT b.*, h.profile_image_url as hospital_image FROM gangnamunni_bookings b LEFT JOIN gangnamunni_hospitals h ON b.hospital_id = h.id WHERE b.id = ? LIMIT 1");
    $stmt->bind_param('i', $booking_id);
    $stmt->execute();
    $prev_booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($prev_booking) $hospital_id = $prev_booking['hospital_id'];
} elseif ($hospital_id > 0) {
    $stmt = $connect->prepare("SELECT * FROM gangnamunni_hospitals WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $hospital_id);
    $stmt->execute();
    $hospital = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

$page_title = '다시 예약';
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
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

        .header { position: relative; padding: 14px 16px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid var(--color-border); background: var(--color-bg); }
        .header .back { width: 36px; height: 36px; border: none; background: none; font-size: 20px; cursor: pointer; color: inherit; }
        .header h1 { position: absolute; left: 50%; transform: translateX(-50%); font-size: 17px; font-weight: 700; }

        /* 섹션 공통 */
        .section { margin: 24px 30px; }
        .section-booker { background: #F2F2F2; padding-top: 1px; }
        .section-label { font-size: 18px; font-weight: 700; color: var(--color-primary); letter-spacing: 0.5px; margin-bottom: 12px; }

        /* booking_details */
        .booking_details { display: flex; flex-direction: column; gap: 16px; margin-top: 35px; }
        .booker-details { margin: 20px 20px; }
        .detail-row { display: flex; flex-direction: row; align-items: center; gap: 12px; border-bottom: 1px solid var(--color-border); padding-bottom: 16px; }
        .detail-row label { font-size: 18px; font-weight: 600; color: var(--color-text-secondary); width: 30% }
        .detail-row .value { font-size: 18px; font-weight: 600; color: var(--color-text); }
        .section-payment .section-label,
        .section-cancel .section-label { color: var(--color-text); font-weight: 700; }
        .section-payment .detail-row .value,
        .section-cancel .detail-row .value { margin-left: auto; text-align: right; }
        .detail-row input { width: 70%; padding: 10px 14px; border: none; font-size: 15px; color: var(--color-text); }

        /* 취소완료 카드 */
        .cancelled-badge { display: inline-block; font-size: 12px; font-weight: 700; color: #991b1b; padding: 3px 10px; border-radius: 20px; margin-bottom: 8px; }
        .cancelled-date { font-size: 18px; font-weight: 700; color: var(--color-text); margin-bottom: 8px; }
        .cancelled-info { font-size: 14px; color: var(--color-text-secondary); line-height: 1.8; }
        .cancelled-info span { display: block; }
        .cancel-reason-box { margin-top: 10px; padding: 10px 12px; background: #fef2f2; border-radius: 8px; font-size: 13px; color: #991b1b; }
        .refund-notice { margin: 20px auto; width: 90%; background: var(--color-bg-light); border-radius: 10px; padding: 16px; }
        .refund-notice p { font-size: 12px; color: var(--color-text-secondary); line-height: 1.8; }
        .btn-rebook { display: block; width: 90%; margin: 24px auto; padding: 16px; background: #AE1932; color: #fff; font-size: 17px; font-weight: 700; border: none; border-radius: 12px; cursor: pointer; text-align: center; text-decoration: none; }
    </style>
    <script>
        (function(){
            var t = localStorage.getItem('theme');
            if (t === 'dark') document.documentElement.setAttribute('data-bs-theme', 'dark');
        })();
    </script>
<?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head>
<body>

<div class="container">
    <div class="header">
        <button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button>
        <h1>취소/환불 내역</h1>
    </div>

    <div>

    <!-- 취소완료 섹션 -->
    <div class="section">
        <div class="section-label">취소완료</div>
        <div class="booking_details">
            <div class="detail-row">
                <label>예약번호</label>
                <div class="value"><?php echo $prev_booking ? '#' . $prev_booking['id'] : '-'; ?></div>
            </div>
            <div class="detail-row">
                <label>환불일시</label>
                <div class="value"><?php
                    if (!empty($prev_booking['cancelled_at'])) {
                        $_days_ko = ['일','월','화','수','목','금','토'];
                        $_ts = strtotime($prev_booking['cancelled_at']);
                        $_dow = $_days_ko[(int)date('w', $_ts)];
                        $_h = (int)date('G', $_ts);
                        $_ampm = $_h < 12 ? '오전' : '오후';
                        $_h12 = $_h % 12; if ($_h12 === 0) $_h12 = 12;
                        echo date('Y.n.j', $_ts) . '(' . $_dow . ') ' . $_ampm . ' ' . $_h12 . ':' . date('i', $_ts);
                    } else { echo '-'; }
                ?></div>
            </div>
            <div class="detail-row">
                <label>선택시술</label>
                <div class="value"><?php echo !empty($prev_booking['treatment']) ? htmlspecialchars($prev_booking['treatment']) : '-'; ?></div>
            </div>
            <div class="detail-row">
                <label>병원</label>
                <div class="value"><?php echo !empty($prev_booking['hospital_name']) ? htmlspecialchars($prev_booking['hospital_name']) : '-'; ?></div>
            </div>
            <div class="detail-row" style="border-bottom: none;">
                <label>담당의사</label>
                <div class="value"><?php echo !empty($prev_booking['doctor_name']) ? htmlspecialchars($prev_booking['doctor_name']) : '-'; ?></div>
            </div>
        </div>
    </div>

    <!-- 예약자 / 연락처 섹션 -->
    <div class="section section-booker">
        <div class="booking_details booker-details">
            <div class="detail-row">
                <label>예약자</label>
                <div class="value"><?php echo !empty($prev_booking['name']) ? htmlspecialchars($prev_booking['name']) : '-'; ?></div>
            </div>
            <div class="detail-row" style="border-bottom: none;">
                <label>연락처</label>
                <div class="value"><?php echo !empty($prev_booking['phone']) ? htmlspecialchars($prev_booking['phone']) : '-'; ?></div>
            </div>
        </div>
    </div>

    <!-- 결제정보 섹션 -->
    <div class="section section-payment">
        <div class="section-label">결제정보</div>
        <div class="booking_details">
            <div class="detail-row">
                <label>시술가격</label>
                <div class="value"><?php echo !empty($prev_booking['price']) ? number_format($prev_booking['price']) . '원' : '-'; ?></div>
            </div>
            <div class="detail-row" style="border-bottom: none;">
                <label>총 결제금액</label>
                <div class="value"><?php echo !empty($prev_booking['total_amount']) ? number_format($prev_booking['total_amount']) . '원' : '-'; ?></div>
            </div>
        </div>
    </div>

    <!-- 취소/환불 정보 섹션 -->
    <div class="section section-cancel">
        <div class="section-label">취소/환불 정보</div>
        <div class="booking_details">
            <div class="detail-row">
                <label>취소날짜/시간</label>
                <div class="value"><?php
                    if (!empty($prev_booking['booking_date']) && !empty($prev_booking['booking_time'])) {
                        $_days_ko = ['일','월','화','수','목','금','토'];
                        $_ts = strtotime($prev_booking['booking_date'] . ' ' . $prev_booking['booking_time']);
                        $_dow = $_days_ko[(int)date('w', $_ts)];
                        $_h = (int)date('G', $_ts);
                        $_ampm = $_h < 12 ? '오전' : '오후';
                        $_h12 = $_h % 12; if ($_h12 === 0) $_h12 = 12;
                        echo date('Y.n.j', $_ts) . '(' . $_dow . ') ' . $_ampm . ' ' . $_h12 . ':' . date('i', $_ts);
                    } else { echo '-'; }
                ?></div>
            </div>
            <div class="detail-row">
                <label>취소 수수료</label>
                <div class="value"><?php echo isset($prev_booking['cancel_fee']) ? number_format($prev_booking['cancel_fee']) . '원' : '-'; ?></div>
            </div>
            <div class="detail-row" style="border-bottom: none;">
                <label>총 금액(취소/환불)</label>
                <div class="value"><?php echo isset($prev_booking['refund_amount']) ? number_format($prev_booking['refund_amount']) . '원' : '-'; ?></div>
            </div>
        </div>
    </div>

    <!-- 환불 안내 -->
    <div class="refund-notice">
        <p> - 신용카드로 결제한 예약 내역은 취소 즉시 자동으로 카드 승인취소 처리됩니다.<br>

         - 단, 카드사에서의 승인취소 확인은 카드사와 VAN사의 확인절차를 거치는 관계로 주문 취소일로부터 3~5일(영업일 기준) 후 해당 카드사에서 취소내역을 확인하실 수 있습니다.<br>

         - 체크카드의 경우 회원님의 통장으로 환불되기까지 최대 5일(영업일 기준) 정도 소요될 수 있습니다.<br>

         - 정확한 환불기간은 카드사별로 상이하므로, 해당 카드사로 문의하여 주시기 바랍니다.</p>

    </div>

    <a href="booking.php?id=<?php echo (int)$hospital_id; ?>&amp;rebook=1<?php if ($prev_booking): ?>&amp;source_booking_id=<?php echo (int)$prev_booking['id']; ?>&amp;treatment=<?php echo urlencode((string)$prev_booking['treatment']); ?><?php endif; ?>" class="btn-rebook">다시예약</a>

    </div>

</div>

<?php include __DIR__ . '/includes/bottom_nav.php'; ?>
</body>
</html>
