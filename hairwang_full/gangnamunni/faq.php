<?php
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/faq_categories.php';

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
        ['포인트','포인트는 어떻게 적립하고 사용하나요?','후기 작성, 이벤트 참여 등으로 포인트가 적립되며, 마이페이지 > 포인트에서 적립·사용 내역을 확인할 수 있습니다.'],
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
$res = mysqli_query($connect, "SELECT id, category, question, answer FROM gangnamunni_faq WHERE is_active=1 ORDER BY sort_order ASC, id ASC");
if ($res) while ($r = mysqli_fetch_assoc($res)) $faqs[] = $r;

// 탭 = 디자인 기준 카테고리(결제/계정/예약/일반/포인트/후기) + 활성 FAQ 가 있는 레거시 카테고리
$categories = gangnamunni_faq_categories($connect, true);
$grouped = array_fill_keys($categories, []);
foreach ($faqs as $f) {
    $c = trim((string)$f['category']);
    if ($c === '') $c = '일반';
    $grouped[$c][] = $f;
}

// 초기 탭: ?cat= 값이 유효하면 그 탭, 아니면 첫 번째 탭(결제)
$active_cat = $_GET['cat'] ?? '';
if (!isset($grouped[$active_cat])) $active_cat = $categories[0];
?>
<!DOCTYPE html><html lang="ko" data-bs-theme="light"><head>
<?php require_once __DIR__ . '/../lib/site_favicon.php'; echo seoulBeautyFaviconLinks(); ?>

<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title>자주 묻는 질문 - 서울미녀</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script>(function(){var t=localStorage.getItem('theme');if(t==='dark')document.documentElement.setAttribute('data-bs-theme','dark');})();</script>
<style>
:root{--pri:#AE1932;--faq-line:#E5E5E5;--faq-gap:#F2F2F2;}
[data-bs-theme="dark"]{--faq-line:#333;--faq-gap:#1c1c1c;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:640px;margin:0 auto;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#111);padding-bottom:80px;}
/* 헤더: 좌측 뒤로가기 + 가운데 제목 */
.faq-header{position:sticky;top:0;z-index:10;height:56px;display:flex;align-items:center;justify-content:center;background:var(--bs-body-bg,#fff);}
.faq-header .back{position:absolute;left:8px;top:50%;transform:translateY(-50%);width:40px;height:40px;border:none;background:none;font-size:calc(22px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;color:inherit;display:flex;align-items:center;justify-content:center;}
.faq-header h1{font-size:calc(17px * var(--fs-scale,1) * var(--fs-page,1));font-weight:500;}
/* 카테고리 탭 */
.faq-tabs{display:flex;gap:8px;padding:12px 16px 8px;overflow-x:auto;scrollbar-width:none;}
.faq-tabs::-webkit-scrollbar{display:none;}
.faq-tab{flex:1 0 auto;min-width:52px;height:30px;padding:0 10px;border:1px solid #BDBDBD;border-radius:6px;background:var(--bs-body-bg,#fff);color:#9E9E9E;font-size:calc(13px * var(--fs-scale,1) * var(--fs-page,1));cursor:pointer;white-space:nowrap;}
.faq-tab.active{border-color:var(--pri);color:var(--pri);}
/* 질문 리스트 (아코디언) */
.faq-panel{display:none;border-bottom:12px solid var(--faq-gap);}
.faq-panel.active{display:block;}
.faq-item{margin:0 16px;border-bottom:1px solid var(--faq-line);}
.faq-item:last-child{border-bottom:none;}
.faq-q{width:100%;min-height:68px;padding:16px 8px 16px 10px;display:flex;align-items:center;justify-content:space-between;gap:12px;border:none;background:none;color:inherit;text-align:left;cursor:pointer;font-size:calc(15px * var(--fs-scale,1) * var(--fs-page,1));font-weight:500;line-height:1.4;}
.faq-q i{flex-shrink:0;color:#C8C8C8;font-size:calc(16px * var(--fs-scale,1) * var(--fs-page,1));transition:transform .2s;}
.faq-item.open .faq-q i{transform:rotate(180deg);}
.faq-a{display:none;margin:0 0 16px;padding:14px 16px;border-radius:8px;background:var(--bs-tertiary-bg,#F7F7F7);font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));line-height:1.7;color:var(--bs-secondary-color,#555);}
.faq-item.open .faq-a{display:block;}
.faq-empty{padding:60px 16px;text-align:center;color:var(--bs-secondary-color,#999);font-size:calc(14px * var(--fs-scale,1) * var(--fs-page,1));}
</style><?php if (function_exists('generateFontScaleCSS')) echo generateFontScaleCSS(); ?>
</head><body>
<div class="faq-header">
    <button type="button" class="back" onclick="history.back()" aria-label="뒤로가기"><i class="bi bi-chevron-left"></i></button>
    <h1>자주 묻는 질문</h1>
</div>

<div class="faq-tabs" role="tablist">
<?php foreach ($categories as $i => $cat): ?>
    <button type="button" class="faq-tab<?php echo $cat === $active_cat ? ' active' : ''; ?>" role="tab" data-idx="<?php echo $i; ?>" aria-selected="<?php echo $cat === $active_cat ? 'true' : 'false'; ?>"><?php echo htmlspecialchars($cat); ?></button>
<?php endforeach; ?>
</div>

<?php foreach ($categories as $i => $cat): $items = $grouped[$cat] ?? []; ?>
<div class="faq-panel<?php echo $cat === $active_cat ? ' active' : ''; ?>" role="tabpanel" data-idx="<?php echo $i; ?>">
    <?php if (empty($items)): ?>
    <div class="faq-empty">등록된 질문이 없습니다.</div>
    <?php else: foreach ($items as $f): ?>
    <div class="faq-item">
        <button type="button" class="faq-q" aria-expanded="false">
            <span><?php echo htmlspecialchars($f['question']); ?></span><i class="bi bi-chevron-down"></i>
        </button>
        <div class="faq-a"><?php echo nl2br(htmlspecialchars($f['answer'])); ?></div>
    </div>
    <?php endforeach; endif; ?>
</div>
<?php endforeach; ?>

<script>
(function(){
    var tabs = document.querySelectorAll('.faq-tab'), panels = document.querySelectorAll('.faq-panel');
    tabs.forEach(function(t){
        t.addEventListener('click', function(){
            var idx = t.getAttribute('data-idx');
            tabs.forEach(function(x){ var on = x === t; x.classList.toggle('active', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); });
            panels.forEach(function(p){ p.classList.toggle('active', p.getAttribute('data-idx') === idx); });
        });
    });
    // 질문 클릭 시 답변 펼침/접힘
    document.querySelectorAll('.faq-q').forEach(function(q){
        q.addEventListener('click', function(){
            var item = q.parentNode, open = !item.classList.contains('open');
            item.classList.toggle('open', open);
            q.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });
})();
</script>
<?php include_once __DIR__ . '/includes/bottom_nav.php'; ?>
</body></html>
