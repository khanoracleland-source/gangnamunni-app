<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
$subscribe_url = G5_URL . "/rb/rb.mod/subscribe/";
$sb_is = sb_is($sb_mb_id);
?>

<link rel="stylesheet" href="<?php echo $subscribe_url ?>subscribe.css?ver=<?php echo G5_TIME_YMDHIS ?>">    
              
            
            <?php if($member['mb_id'] != $sb_mb_id) { ?>
            <div class="my_subs_btn_wrap">
                <a class="fl_btns gd_btn off <?php if(isset($sb_is) && $sb_is > 0) { ?>on<?php } ?>" href="javascript:void(0);" id="subscribe_btn_ajax" onclick="subscribe_add('<?php echo $member['mb_id'] ?>', '<?php echo $sb_mb_id ?>');" title="구독">
                   <i><img src="<?php echo $subscribe_url ?>/image/ico_bell.svg"></i>
                </a>
            </div>
            <div class="cb"></div>
            <script>
                function subscribe_add(sb_mb_id, sb_fw_id) {
                    // 로그인 여부를 확인하여 처리
                    if (!sb_mb_id) {
                        alert('로그인 후 이용해주세요.');
                        return false;
                    } else {
                        if (sb_mb_id == sb_fw_id) {
                            alert('자신은 구독할 수 없습니다.');
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
                                        var subscribe_btn = document.querySelector('#subscribe_btn_ajax');
                                        
                                        if (subscribe_btn) { // 요소가 존재하는지 확인
                                            if (data.status === 'ok') {
                                                subscribe_btn.classList.add('on');
                                                alert('구독 정보가 등록 되었습니다.\n구독정보 및 설정은 마이페이지에서 하실 수 있습니다.');
                                            } else if (data.status === 'del') {
                                                subscribe_btn.classList.remove('on');
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
