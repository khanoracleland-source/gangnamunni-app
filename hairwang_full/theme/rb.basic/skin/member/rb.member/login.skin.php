<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// add_stylesheet('css 경로', 우선순위); 숫자가 낮을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css">', 0);

// ========== DEMO MODE ==========
// false로 변경하면 데모 버튼 숨김
$SHOW_DEMO_BUTTONS = false;
// ================================

// ========== 로그인 후 리다이렉트 ==========
// 로그인 후 이동할 URL (빈 문자열이면 기본 Gnuboard 동작)
$AFTER_LOGIN_URL = '/hairwang/gangnamunni/';
// ==========================================
$redirect_url = $AFTER_LOGIN_URL ? urlencode($AFTER_LOGIN_URL) : $login_url;
?>

<style>
    body, html {background-color: #f9fafb;}
    main {background-color: #f9fafb;}
</style>

<div class="rb_member">
    <div class="rb_login">

        <ul class="rb_login_box">
            <form name="flogin" action="<?php echo $login_action_url ?>" onsubmit="return flogin_submit(this);" method="post">
            <input type="hidden" name="url" value="<?php echo $redirect_url ?>">

            <li class="rb_login_logo">

            <?php if (!empty($rb_builder['bu_logo_pc'])) { ?>
                <a href="<?php echo G5_URL; ?>/gangnamunni/"><img src="<?php echo G5_URL; ?>/data/logos/pc?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>" id="logo_img"></a>
            <?php } else { ?>
                <a href="<?php echo G5_URL; ?>/gangnamunni/"><img src="<?php echo G5_THEME_URL ?>/rb.img/logos/pc.png?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>" id="logo_img"></a>
            <?php } ?>


            </li>
            <li>
                <span>아이디</span>
                <input type="text" name="mb_id" id="login_id" required class="input required" maxLength="20" placeholder="아이디">
            </li>
            <li>
    <span>비밀번호</span>
    <div style="position:relative;">
        <input type="password" name="mb_password" id="login_pw" required class="input required" maxLength="20" placeholder="비밀번호" style="padding-right:40px;">
        <button type="button" onclick="togglePw()" id="pw_toggle_btn" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#888;font-size:18px;padding:5px;" title="비밀번호 보기">
            <i class="fa fa-eye" id="pw_toggle_icon"></i>
        </button>
    </div>
</li>
            <li>
                <button type="submit" class="btn_submit font-B">로그인</button>
            </li>
            <li>
                <div id="login_info">
                    <div class="login_if_auto">
                        <input type="checkbox" name="auto_login" id="login_auto_login">
                        <label for="login_auto_login"> 자동로그인</label>
                    </div>
                    <div class="login_if_lpl">
                        <a href="<?php echo G5_BBS_URL ?>/password_lost.php">아이디/비밀번호 찾기</a>
                    </div>
                </div>
            </li>
            </form>

            <?php if($config['cf_social_login_use'] == 1) { ?>
            <li>
                <span class="sns_titles">SNS로 간편하게 시작하기</span>
            <?php @include_once(get_social_skin_path().'/social_login.skin.php'); // 소셜로그인 사용시 소셜로그인 버튼 ?>
            </li>
            <?php } ?>

            <li class="join_links">
                아직 <?php echo $config['cf_title'] ?> 회원이 아니신가요?　<a href="<?php echo G5_BBS_URL ?>/register.php" class="font-B">회원가입</a>
            </li>
<?php if ($SHOW_DEMO_BUTTONS): ?>
<li class="demo_accounts" style="margin-top:15px;padding:12px 15px;background:#f0f9ff;border:1px dashed #60a5fa;border-radius:8px;">
    <div style="text-align:center;margin-bottom:8px;color:#2563eb;font-size:13px;font-weight:bold;">Demo Accounts</div>
    <div style="display:flex;gap:8px;justify-content:center;">
        <button type="button" onclick="fillDemo('admin','admin123')" style="flex:1;padding:8px 12px;background:#3b82f6;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:bold;">관리자</button>
        <button type="button" onclick="fillDemo('test','test1234')" style="flex:1;padding:8px 12px;background:#10b981;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;font-weight:bold;">테스트회원</button>
    </div>
</li>
<?php endif; ?>


            <?php if (isset($default['de_level_sell']) && $default['de_level_sell'] == 1) { // 쇼핑몰 사용시 ?>
                <?php if (preg_match("/orderform.php/", $url)) { ?>

                    <li class="bemember_tit"><h2>비회원 주문</h2></li>
                    <li>
                        <span>개인정보 수집 및 이용동의</span>
                        <div class="textarea_divs"><?php echo conv_content($default['de_guest_privacy'], $config['cf_editor']); ?></div>
                        <div class="mt-10">
                            <input type="checkbox" value="1" id="agree3">
                            <label for="agree3">개인정보 수집 및 이용동의에 동의합니다.</label>
                        </div>
                    </li>
                    <li>
                        <button type="button" onclick="javascript:guest_submit(document.flogin);" class="btn_submit font-B">비회원으로 주문하기</button>
                    </li>
                    <li class="join_links">
                        <a href="javascript:history.back();" class="font-B">돌아가기</a>
                    </li>


                    <script>
                    function guest_submit(f)
                    {
                        if (document.getElementById('agree3')) {
                            if (!document.getElementById('agree3').checked) {
                                alert("개인정보취급방침의 내용에 동의를 읽고 이용 동의하셔야 합니다.");
                                return;
                            }
                        }

                        f.url.value = "<?php echo $url; ?>";
                        f.action = "<?php echo $url; ?>";
                        f.submit();
                    }
                    </script>
                    <?php } else if (preg_match("/orderinquiry.php$/", $url)) { ?>


                    <div id="mb_login_od_wr">
                        <h2>비회원 주문조회 </h2>
                        <form name="forderinquiry" method="post" action="<?php echo urldecode($url); ?>" autocomplete="off">
                        <li>
                            <span>주문번호</span>
                            <input type="text" name="od_id"  value="<?php echo get_text($od_id); ?>" id="od_id" required class="input required" placeholder="주문번호">
                        </li>

                        <li>
                            <span>비밀번호</span>
                            <input type="password" name="od_pwd" id="od_pwd" required class="input required" placeholder="비밀번호">
                        </li>
                        <li>
                        <section id="mb_login_odinfo">
                            <p>주문하신 <strong>주문번호</strong> 와 주문 시 입력하신 <strong>비밀번호</strong>를 정확히 입력해주세요.</p>
                        </section>
                        </li>
                        <li>
                            <button type="submit" class="btn_submit font-B">확인</button>
                        </li>
                        </form>

                        <li class="join_links">
                            <a href="javascript:history.back();" class="font-B">돌아가기</a>
                        </li>


                    </div>

                    <?php } ?>
            <?php } ?>



        </ul>

    </div>
</div>

<script>
jQuery(function($){
    $("#login_auto_login").click(function(){
        if (this.checked) {
            this.checked = confirm("자동로그인을 사용하시면 다음부터 회원아이디와 비밀번호를 입력하실 필요가 없습니다.\n\n공공장소에서는 개인정보가 유출될 수 있으니 사용을 자제하여 주십시오.\n\n자동로그인을 사용하시겠습니까?");
        }
    });
});


// 비밀번호 표시/숨김 토글
function togglePw() {
    var pw = document.getElementById("login_pw");
    var icon = document.getElementById("pw_toggle_icon");
    if (pw.type === "password") {
        pw.type = "text";
        icon.className = "fa fa-eye-slash";
    } else {
        pw.type = "password";
        icon.className = "fa fa-eye";
    }
}

// 데모 계정 자동 입력
function fillDemo(id, pw) {
    document.getElementById("login_id").value = id;
    document.getElementById("login_pw").value = pw;
}
function flogin_submit(f)
{
    if( $( document.body ).triggerHandler( 'login_sumit', [f, 'flogin'] ) !== false ){
        return true;
    }
    return false;
}
</script>
<!-- } 로그인 폼 -->