<?php
/**
 * 회원가입 완료 결과 페이지
 * - 가입 성공 메시지 표시
 * - "메인으로" 버튼 → index.php
 */
require_once __DIR__ . '/includes/init.php';

$page_title = '회원가입 완료';
$show_search = false;
include __DIR__ . '/includes/header.php';
?>

<div style="max-width:640px;margin:0 auto;padding:60px 20px;text-align:center;">
    <div style="font-size:56px;margin-bottom:20px;">🎉</div>
    <h2 style="font-size:22px;font-weight:700;color:var(--bs-body-color,#222);margin-bottom:12px;">회원가입이 완료되었습니다!</h2>
    <p style="font-size:15px;color:var(--bs-secondary-color,#888);margin-bottom:32px;line-height:1.6;">
        서울미녀에 오신 것을 환영합니다.<br>
        지금 바로 다양한 서비스를 이용해보세요.
    </p>
    <a href="index.php" style="display:inline-block;padding:14px 48px;background:#AE1932;color:#fff;border-radius:12px;font-size:16px;font-weight:600;text-decoration:none;transition:background 0.2s;">
        메인으로
    </a>
</div>

<?php
echo '<div style="height:70px;"></div>';
include_once __DIR__ . '/includes/bottom_nav.php';
include __DIR__ . '/includes/footer.php';
mysqli_close($connect);
?>
