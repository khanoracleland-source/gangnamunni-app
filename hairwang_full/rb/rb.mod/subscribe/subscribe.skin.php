<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
$subscribe_url = G5_URL . "/rb/rb.mod/subscribe/";

if(isset($wr_id) && $wr_id) {
    $sb_mb_id = $view['mb_id']; //@미니님a 님 오류수정
}

$sb_is = sb_is($sb_mb_id);
$sb_mb = get_member($sb_mb_id);
$is_leave = $sb_mb['mb_leave_date'] && $sb_mb['mb_leave_date'] !== '';

?>

<link rel="stylesheet" href="<?php echo $subscribe_url ?>subscribe.css?ver=<?php echo G5_TIME_YMDHIS ?>">    
               
            <?php if($member['mb_id'] != $sb_mb_id) { ?>
            
            <a class="fl_btns gd_btn main_rb_bg <?php if(isset($sb_is) && $sb_is > 0) { ?>off<?php } ?>" href="javascript:void(0);" id="subscribe_btn_ajax" onclick="subscribe_add('<?php echo $member['mb_id'] ?>', '<?php echo $sb_mb_id ?>', '<?php echo $is_leave ?>');">
               <i><img src="<?php echo $subscribe_url ?>/image/ico_bell.svg"></i>
               
               <?php if(isset($sb_is) && $sb_is > 0) { ?>
                   <span id="subscribe_btn_text">구독중</span>
                   <div class="cb"></div>
                   <span class="tooltips" id="subscribe_btn_text_tooltips">구독취소</span>
               <?php } else { ?>
                   <span id="subscribe_btn_text">구독하기</span>
                   <div class="cb"></div>
                   <span class="tooltips" id="subscribe_btn_text_tooltips">구독하고 알림받기</span>
               <?php } ?>
            </a>
            
            <script>
                function subscribe_add(sb_mb_id, sb_fw_id, is_leave) {
                    // 로그인 여부를 확인하여 처리
                    if (!sb_mb_id) {
                        alert('로그인 후 이용해주세요.');
                        return false;
                    } else {
                        if (sb_mb_id == sb_fw_id) {
                            alert('자신은 구독할 수 없습니다.');
                            return false;
                        } else if(is_leave) {
                            alert('탈퇴한 회원입니다.');
                            return false;
                        } else { 
                            if (sb_fw_id) {
                                $.ajax({
                                    url: '<?php echo G5_URL ?>/rb/rb.mod/subscribe/ajax.subscribe.php',
                                    type: 'post',
                                    dataType: 'json',
                                    data: {
                                        "sb_mb_id": sb_mb_id,
                                        "sb_fw_id": sb_fw_id
                                    },
                                    success: function(data) {
                                        var texts = document.querySelector('#subscribe_btn_text');
                                        var subscribe_btn = document.querySelector('#subscribe_btn_ajax');
                                        var subscribe_btn_tt = document.querySelector('#subscribe_btn_text_tooltips');
                                        

                                        if (texts && subscribe_btn) { // 요소가 존재하는지 확인
                                            if (data.status === 'ok') {
                                                texts.innerText = '구독중';
                                                subscribe_btn_tt.innerText = '구독취소';
                                                subscribe_btn.classList.add('off');
                                                alert('구독 정보가 등록 되었습니다.\n구독정보 및 설정은 마이페이지에서 하실 수 있습니다.');
                                            } else if (data.status === 'del') {
                                                texts.innerText = '구독하기';
                                                subscribe_btn_tt.innerText = '구독하고 알림받기';
                                                subscribe_btn.classList.remove('off');
                                                alert('구독이 취소 되었습니다.');
                                            }
                                        } else {
                                            console.error('HTML 요소가 존재하지 않습니다.');
                                        }
                                    },
                                    error: function(err) {
                                        alert('오류가 발생했습니다. 잠시 후 다시 시도해주세요.');
                                    }
                                });
                            } else { 
                                alert('대상자 아이디가 없습니다.');
                                return false;
                            }
                        }
                        
                    }
                }
            </script>
            
            <?php } ?>
