<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
$partner_url = G5_URL . "/rb/rb.mod/partner/";
$paid = isset($it['it_partner']) ? $it['it_partner'] : '';

if($paid) {
    if(isset($sb['sb_use']) && $sb['sb_use'] == 1) {
        $sb_is = sb_is($it['it_partner']);
    }
    $partner = get_member($it['it_partner']);
}

?>


<?php if($paid) { ?>
<div class="partner_info_wrap">
    <div class="writer_prof">
        <ul class="writer_prof_ul1">
            <li class="writer_prof_li_prof">
                <dd class="writer_prof_li_prof_img"><?php echo get_member_profile_img($partner['mb_id']) ?></dd>
                <dd class="writer_prof_li_prof_txt">
                <span class="prof_nick font-B color-000 font-16"><?php echo $partner['mb_nick'] ?></span>
                @<?php echo $partner['mb_id'] ?><?php if(isset($sb['sb_use']) && $sb['sb_use'] == 1) { // 구독 사용시 ?>　<?php echo sb_cnt($partner['mb_id']) ?><?php } ?>
                </dd>
                <div class="cb"></div>
            </li>
            <button type="button" class="store_more_btn" onclick="location.href='<?php echo G5_URL ?>/store/?p=<?php echo $partner['mb_id'] ?>';">
                <span class="font-B">스토어</span>
                <img src="<?php echo $partner_url ?>/image/ico_store.svg">
            </button>
            <?php if (isset($partner['mb_signature']) || isset($partner['mb_profile'])) { ?>
            <li class="writer_prof_li_txt">
                <?php 
                    if($partner['mb_signature']) { 
                        echo $partner['mb_signature'];
                    } else { 
                        echo $partner['mb_profile'];
                    }
                ?>
            </li>
            <?php } ?>

        </ul>
        <ul class="writer_prof_ul2">
           
            <?php if($is_member) { ?>
            <a class="fl_btns" href="<?php echo G5_URL ?>/rb/home.php?mb_id=<?php echo $partner['mb_id'] ?>">
            <?php } else { ?>
            <a class="fl_btns" href="javascript:alert('로그인 후 이용해주세요.');">
            <?php } ?>
               <img src="<?php echo $partner_url ?>/image/ico_home_p.svg">
               <span class="tooltips">미니홈</span>
            </a>

            
            <a class="fl_btns" href="<?php echo G5_BBS_URL ?>/memo_form.php?me_recv_mb_id=<?php echo $partner['mb_id'] ?>" onclick="win_memo(this.href); return false;">
               <img src="<?php echo $partner_url ?>/image/ico_msg.svg">
               <span class="tooltips">쪽지</span>
            </a>
            
            <?php if (isset($chat_set['ch_use']) && $chat_set['ch_use'] == 1) { ?>
            <a class="fl_btns" href="<?php echo G5_URL ?>/rb/chat_form.php?me_recv_mb_id=<?php echo $partner['mb_id'] ?>" onclick="win_chat(this.href); return false;">
               <img src="<?php echo $partner_url ?>/image/ico_chat.svg">
               <span class="tooltips">채팅</span>
            </a>
            <?php } ?>
            
            <?php 
                if(isset($sb['sb_use']) && $sb['sb_use'] == 1) { // 구독 사용시 
                    $sb_mb_id = $partner['mb_id'];
                    include_once(G5_PATH.'/rb/rb.mod/subscribe/subscribe.skin.php');
                }
            ?>

        </ul>
        <div class="cb"></div>
    </div>
</div>
<?php } ?>          