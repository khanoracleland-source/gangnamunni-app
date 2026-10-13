<?php
include_once('./_common.php');

// 로그인중인 경우 회원가입 할 수 없습니다.
if ($is_member) {
    goto_url(G5_URL);
}

// 회원유형 선택(일반회원 / 병원관계자) 화면 노출 여부. false 면 선택 화면 없이 일반 이메일 가입으로 바로 이동한다.
// 병원관계자 선택 시 hospital_apply_public.php(병원 등록 신청)로 이동한다.
$show_hospital_registration = true;

// 일반 회원가입은 커스텀 이메일 페이지로 바로 이동한다.
// 약관 보기는 아래의 순수 read-only 화면을 사용한다.
if (($_GET['view'] ?? '') !== 'terms') {
    if (!$show_hospital_registration) {
        // 회원유형 화면을 건너뛰어도 추천인 정보는 유지한다.
        if (isset($_GET['mb_recommend']) && is_string($_GET['mb_recommend'])) {
            $mb_recommend = preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['mb_recommend']);
            if ($mb_recommend !== '') {
                set_cookie('reg_mb_recommend', $mb_recommend, 86400);
                set_session('ss_mb_recommend', $mb_recommend);
            }
        }
        goto_url(G5_URL.'/gangnamunni/auth/register.php');
        exit;
    }
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
