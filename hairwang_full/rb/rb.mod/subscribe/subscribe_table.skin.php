<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
$subscribe_url = G5_URL . "/rb/rb.mod/subscribe/";
?>

<link rel="stylesheet" href="<?php echo $subscribe_url ?>subscribe.css?ver=<?php echo G5_TIME_YMDHIS ?>">  
       
        
        <?php 
            if($ca == "fn") { 
        ?>
        
        
        <?php
            $sql_commons = " from rb_subscribe where sb_mb_id = '{$mb['mb_id']}' order by sb_id desc ";
            $sql = " select * {$sql_commons} ";
            $sb_res = sql_query($sql);
        ?>
        
            <ul class="rb_bbs_list">


                <div class="rb-subs-container">

                    <div class="rb-subs-content">
                        <table class="rb-subs-table">
                            <thead>
                                <tr>
                                    <th>구독회원</th>
                                    <th>알림설정</th>
                                    <th class="mh_pc">구독일시</th>
                                    <th>삭제</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                for ($i=0; $rows=sql_fetch_array($sb_res); $i++) { 
                                    $fw_nick = get_member($rows['sb_fw_id']);
                                ?>
                                <tr>
                                    <td class="rb-subs-title" style="padding-left:15px; width:55%;">
                                        <a href="<?php echo G5_URL ?>/rb/home.php?mb_id=<?php echo $rows['sb_fw_id'] ?>" class="font-R">
                                           <span class="lists_fw_prof"><?php echo get_member_profile_img($rows['sb_fw_id']); ?></span><?php echo $fw_nick['mb_nick'] ?>　<span class="font-12 color-999"><?php echo sb_cnt($rows['sb_fw_id']) ?></span>
                                        </a>
                                    </td>
                                    <td class="rb-subs-views">
                                        <div id="sb_push_btn_ch_wrap_<?php echo $rows['sb_id'] ?>">
                                            <?php if($rows['sb_push'] == 1) { ?>
                                            <button type="button" class="fw_push_btn" onclick="sb_submit('<?php echo $rows['sb_id'] ?>', '0')">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-bell"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                                            </button>
                                            <?php } else { ?>
                                            <button type="button" class="fw_push_btn" onclick="sb_submit('<?php echo $rows['sb_id'] ?>', '1')">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#bbb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-bell-off"><path d="M13.73 21a2 2 0 0 1-3.46 0"></path><path d="M18.63 13A17.89 17.89 0 0 1 18 8"></path><path d="M6.26 6.26A5.86 5.86 0 0 0 6 8c0 7-3 9-3 9h14"></path><path d="M18 8a6 6 0 0 0-9.33-5"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                                            </button>
                                            <?php } ?>
                                        </div>
                                    </td>
                                    <td class="rb-subs-writer mh_pc"><?php echo $rows['sb_datetime'] ?></td>
                                    
                                    <td class="rb-subs-no">
                                        <button type="button" class="fw_del_btn" onclick="sb_submit_del('<?php echo $rows['sb_id'] ?>')">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                        </button>
                                    </td>
                                </tr>
                                <?php } ?>
                                

                            </tbody>
                        </table>
                        <?php if ($i == 0) { echo "<div class=\"no_data\" style=\"text-align:center\">내가 구독중인 회원이 없습니다.</div>"; } ?>
                    </div>
                </div>




            </ul>
            
            
            <script>
                function sb_submit(sb_id, sb_push) {
                        var sb_type = "push";

                        $.ajax({
                            url: '<?php echo G5_URL ?>/rb/rb.mod/subscribe/ajax.subscribe_update.php', // PHP 파일 경로
                            type: 'POST',
                            dataType: 'html',
                            data: {
                                "sb_id": sb_id,
                                "sb_push": sb_push,
                                "sb_type": sb_type,
                            },
                            success: function(response) {
                                $("#sb_push_btn_ch_wrap_" + sb_id).html(response); //성공
                            },
                            error: function(xhr, status, error) {
                                // 에러 시 처리할 작업
                                alert('알림설정에 문제가 있습니다.\n잠시 후 다시 시도해주세요.');
                            }
                        });
                }
                
                
                function sb_submit_del(sb_id) {
                    if (confirm("선택하신 회원의 구독을 취소합니다.\n계속 하시겠습니까?")) { 
                        var sb_type = "del";

                        $.ajax({
                            url: '<?php echo G5_URL ?>/rb/rb.mod/subscribe/ajax.subscribe_update.php', // PHP 파일 경로
                            type: 'POST',
                            dataType: 'html',
                            data: {
                                "sb_id": sb_id,
                                "sb_type": sb_type,
                            },
                            success: function(response) {
                                location.reload();
                            },
                            error: function(xhr, status, error) {
                                alert('알림설정에 문제가 있습니다.\n잠시 후 다시 시도해주세요.');
                            }
                        });
                    } else { 
                    }
                }
            </script>
            
        <?php } ?>
        
       
        <?php 
            if($ca == "fw") { 
        ?>
           
        <?php
            $sql_commons = " from rb_subscribe where sb_fw_id = '{$mb['mb_id']}' order by sb_id desc ";
            $sql = " select * {$sql_commons} ";
            $sb_res = sql_query($sql);
        ?>
            <div class="sb_fw_wrap cont_info_wrap cont_info_wrap_mmt swiper-container swiper-container-fw-list">
                <div class="swiper-wrapper swiper-wrapper-fw-list">
                <?php 
                    for ($i=0; $rows=sql_fetch_array($sb_res); $i++) { 
                    $fw_nick = get_member($rows['sb_mb_id']);
                ?>
                <ul class="swiper-slide swiper-slide-fw-list">
                    <a href="<?php echo G5_URL ?>/rb/home.php?mb_id=<?php echo $rows['sb_mb_id'] ?>"><?php echo get_member_profile_img($rows['sb_mb_id']); ?></a>
                    <span class="cut"><?php echo $fw_nick['mb_nick']; ?></span>
                </ul>
                <?php } ?>
                <?php if ($i == 0) { echo "<div class=\"no_data\" style=\"text-align:center; width:100% !important;\">나를 구독중인 회원이 아직 없습니다.</div>"; } ?>
                </div>
            </div>
            
            <script>
                var swiper = new Swiper('.swiper-container-fw-list', {
                    slidesPerView: 12, //가로갯수
                    spaceBetween: 20, // 간격
                    slidesPerColumnFill: 'row', //세로형
                    slidesPerColumn: 9999, // 세로갯수
                    slidesOffsetBefore: 0, //좌측여백
                    slidesOffsetAfter: 0, // 우측여백
                    observer: true, //리셋
                    observeParents: true, //리셋
                    touchRatio: 0, // 드래그 가능여부

                    breakpoints: { // 반응형
                        1024: {
                            slidesPerView: 10, //가로갯수
                            slidesPerColumn: 9999, // 세로갯수
                            slidesPerColumnFill: 'row', //세로형
                            spaceBetween: 20, // 간격
                        },
                        768: {
                            slidesPerView: 10, //가로갯수
                            slidesPerColumn: 9999, // 세로갯수
                            slidesPerColumnFill: 'row', //세로형
                            spaceBetween: 20, // 간격
                        },
                        450: {
                            slidesPerView: 5, //가로갯수
                            slidesPerColumn: 9999, // 세로갯수
                            slidesPerColumnFill: 'row', //세로형
                            spaceBetween: 15, // 간격
                        },
                        0: {
                            slidesPerView: 4, //가로갯수
                            slidesPerColumn: 9999, // 세로갯수
                            slidesPerColumnFill: 'row', //세로형
                            spaceBetween: 10, // 간격
                        }
                        
                    }

                });
            </script>
        <?php } ?>