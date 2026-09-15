<?php
require_once __DIR__ . '/includes/init.php';

// FAQ 테이블 자동 생성
mysqli_query($connect, "CREATE TABLE IF NOT EXISTS gangnamunni_faq (
    id INT AUTO_INCREMENT PRIMARY KEY, category VARCHAR(50) DEFAULT '일반',
    question VARCHAR(500) NOT NULL, answer TEXT NOT NULL, sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// 샘플 FAQ가 없으면 추가
$cnt_res = mysqli_query($connect, "SELECT COUNT(*) as c FROM gangnamunni_faq");
if ($cnt_res && mysqli_fetch_assoc($cnt_res)['c'] == 0) {
    $faqs_sample = [
        ['일반','서울미녀는 어떤 서비스인가요?','서울미녀는 검증된 병원을 쉽게 찾고 예약할 수 있는 플랫폼입니다. 후기, 가격비교, 의사 프로필 등 다양한 정보를 제공합니다.'],
        ['예약','예약은 어떻게 하나요?','병원 상세 페이지에서 [예약하기] 버튼을 누르면 5단계 예약 마법사가 시작됩니다. 날짜, 시술, 의사를 선택할 수 있습니다.'],
        ['예약','예약을 취소하고 싶어요.','마이페이지 > 전체 예약 관리에서 해당 예약을 선택 후 취소 요청을 할 수 있습니다.'],
        ['결제','어떤 결제 수단을 지원하나요?','신용카드, 계좌이체, 카카오페이, 네이버페이 등 다양한 결제 수단을 지원합니다.'],
        ['후기','후기를 작성하면 혜택이 있나요?','후기 작성 시 포인트가 적립됩니다. 사진이 포함된 후기는 추가 포인트를 받을 수 있습니다.'],
        ['쿠폰','쿠폰은 어디서 받나요?','이벤트 페이지나 병원 상세 페이지에서 쿠폰을 받을 수 있으며, 마이페이지에서 보유 쿠폰을 확인할 수 있습니다.'],
        ['계정','회원 탈퇴는 어떻게 하나요?','마이페이지 하단의 [회원 탈퇴] 메뉴에서 진행할 수 있습니다. 탈퇴 시 포인트와 쿠폰은 소멸됩니다.'],
    ];
    foreach ($faqs_sample as $i => $f) {
        $stmt = mysqli_prepare($connect, "INSERT INTO gangnamunni_faq (category,question,answer,sort_order) VALUES (?,?,?,?)");
        mysqli_stmt_bind_param($stmt, 'sssi', $f[0], $f[1], $f[2], $i);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

$faqs = [];
$res = mysqli_query($connect, "SELECT * FROM gangnamunni_faq WHERE is_active=1 ORDER BY category, sort_order");
if ($res) while ($r = mysqli_fetch_assoc($res)) $faqs[] = $r;
$grouped = [];
foreach ($faqs as $f) $grouped[$f['category']][] = $f;
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>FAQ - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;}*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#f8f9fa);color:var(--bs-body-color);}
.header{padding:14px 16px;display:flex;align-items:center;gap:12px;border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.header .back{width:36px;height:36px;border:none;background:none;font-size:calc(20px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;}
.header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;}
.faq-cat{padding:12px 16px;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));font-weight:700;color:var(--pri);background:var(--bs-tertiary-bg,#f5f5f5);border-bottom:1px solid var(--bs-border-color,#eee);}
.faq-item{border-bottom:1px solid var(--bs-border-color,#eee);background:var(--bs-body-bg,#fff);}
.faq-q{padding:14px 16px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:500;}
.faq-q i{transition:transform 0.2s;color:var(--bs-secondary-color,#888);}
.faq-q.open i{transform:rotate(180deg);}
.faq-a{padding:0 16px 14px;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));line-height:1.7;color:var(--bs-secondary-color,#666);display:none;}
.faq-a.show{display:block;}
.contact{padding:20px 16px;text-align:center;}
.contact .disabled-contact{display:inline-block;padding:12px 32px;background:var(--bs-secondary-bg,#ddd);color:var(--bs-secondary-color,#888);border-radius:10px;font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));font-weight:600;cursor:not-allowed;}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="header"><button class="back" onclick="history.back()"><i class="bi bi-chevron-left"></i></button><h1><i class="bi bi-question-circle"></i> FAQ</h1></div>
<?php foreach ($grouped as $cat => $items): ?>
<div class="faq-cat"><?php echo htmlspecialchars($cat); ?></div>
<?php foreach ($items as $f): ?>
<div class="faq-item">
    <div class="faq-q" onclick="this.classList.toggle('open');this.nextElementSibling.classList.toggle('show');">
        <span>Q. <?php echo htmlspecialchars($f['question']); ?></span><i class="bi bi-chevron-down"></i>
    </div>
    <div class="faq-a"><?php echo nl2br(htmlspecialchars($f['answer'])); ?></div>
</div>
<?php endforeach; ?>
<?php endforeach; ?>
<div class="contact"><p style="font-size:13px;color:var(--bs-secondary-color);margin-bottom:12px;">1:1 문의 기능은 현재 준비 중입니다.</p><span class="disabled-contact" aria-disabled="true">1:1 문의 준비중</span></div>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>
