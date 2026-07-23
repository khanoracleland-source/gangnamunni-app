<?php
include_once('./_common.php');

// 로그인중인 경우 회원가입 할 수 없습니다.
if ($is_member) {
    goto_url(G5_URL);
}

// 일반 회원가입은 회원유형 선택 후, general이면 커스텀 email 페이지로 이동한다.
// 약관 보기는 아래의 순수 read-only 화면을 사용한다.
if (($_GET['view'] ?? '') !== 'terms') {
    $g5['title'] = '회원가입약관';
    include_once('./_head.sub.php');
    $register_action_url = G5_BBS_URL.'/register_form.php';
    include_once($member_skin_path.'/register.skin.php');
    include_once('./_tail.sub.php');
    exit;
}

$g5['title'] = '회원가입 약관 및 개인정보 정책';
include_once('./_head.sub.php');
?>
<style>
.terms-only { max-width: 760px; margin: 40px auto; padding: 0 20px 50px; color: #222; }
.terms-only h1 { font-size: 26px; margin-bottom: 32px; }
.terms-only h2 { font-size: 19px; margin: 28px 0 12px; }
.terms-only .terms-box { white-space: pre-wrap; border: 1px solid #ddd; background: #fafafa; padding: 18px; max-height: 320px; overflow: auto; line-height: 1.65; }
.terms-only .back { display: inline-flex; margin-top: 28px; padding: 13px 20px; background: #2b0000; color: #fff; text-decoration: none; }
</style>
<main class="terms-only">
    <h1>회원가입 약관 및 개인정보 정책</h1>
    <h2>회원가입약관</h2>
    <div class="terms-box"><?php echo get_text($config['cf_stipulation']); ?></div>
    <h2>개인정보 수집 및 이용정책</h2>
    <div class="terms-box"><?php echo get_text($config['cf_privacy']); ?></div>
    <a class="back" href="<?php echo G5_URL; ?>/gangnamunni/auth/register.php">회원가입으로 돌아가기</a>
</main>
<?php
include_once('./_tail.sub.php');
