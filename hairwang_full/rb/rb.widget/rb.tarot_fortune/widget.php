<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 한국 시간대 설정
date_default_timezone_set('Asia/Seoul');

// 현재 시간 확인 (한국 시간 기준)
$current_hour = (int)date('H');
$time_class = '';
$time_text = '';
$time_icon = '';

// 디버깅용 (필요시 주석 해제)
// echo "<!-- 현재 시간: " . date('Y-m-d H:i:s') . " -->";

if ($current_hour >= 5 && $current_hour < 10) {
    // 아침 (5시~10시)
    $time_class = 'morning';
    $time_text = '상쾌한 아침 햇살과 함께<br>새로운 하루의 운세를 확인해보세요.';
} elseif ($current_hour >= 10 && $current_hour < 14) {
    // 오전 (10시~14시)
    $time_class = 'daytime';
    $time_text = '따스한 햇살 아래서<br>오늘의 행운을 찾아보세요.';
} elseif ($current_hour >= 14 && $current_hour < 18) {
    // 오후 (14시~18시)
    $time_class = 'afternoon';
    $time_text = '노을빛 하늘과 함께<br>오늘의 특별한 메시지를 받아보세요.';
} else {
    // 저녁/밤 (18시~5시)
    $time_class = 'evening';
    $time_text = '별빛과 은하수가 어우러진 밤하늘 아래,<br>신비로운 이야기를 들어보세요.';
}
?>

<!-- rb.tarot_fortune 위젯 CSS -->
<link rel="stylesheet" href="<?php echo G5_URL ?>/rb/rb.widget/rb.tarot_fortune/style.css">

<!-- rb.tarot_fortune 위젯 시작 -->
<div class="rb_tarot_fortune <?php echo $time_class; ?>" onclick="location.href='/tarot.php'">
    <div class="rb_tarot_fortune_bg">
        <?php if ($time_class === 'morning'): ?>
            <div class="rb_tarot_fortune_sunrise"></div>
            <div class="rb_tarot_fortune_clouds"></div>
        <?php elseif ($time_class === 'daytime'): ?>
            <div class="rb_tarot_fortune_crystals"></div>
        <?php elseif ($time_class === 'afternoon'): ?>
            <div class="rb_tarot_fortune_floating"></div>
        <?php else: ?>
            <div class="rb_tarot_fortune_stars"></div>
            <img class="rb_tarot_fortune_moon" src="https://s3-us-west-2.amazonaws.com/s.cdpn.io/1231630/moon2.png" alt="달" />
        <?php endif; ?>
    </div>
    <span class="rb_tarot_fortune_icon"><?php echo $time_icon; ?></span>
    <span class="rb_tarot_fortune_text"><?php echo $time_text; ?></span>
    <a class="rb_tarot_fortune_btn" href="/tarot.php">타로카드 뽑기</a>
</div>
<!-- rb.tarot_fortune 위젯 끝 -->

<script>
// rb.tarot_fortune 스크립트
(function($) {
    $(function() {
        // 버튼 클릭 시 이벤트 전파 방지
        $('.rb_tarot_fortune_btn').on('click', function(e) {
            e.stopPropagation();
        });
    });
})(jQuery);
</script>