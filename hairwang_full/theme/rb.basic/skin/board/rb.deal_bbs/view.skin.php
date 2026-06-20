<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
include_once(G5_LIB_PATH.'/thumbnail.lib.php');

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css?ver='.G5_TIME_YMDHIS.'">', 0);

//////////////////////////////////////////////////
//// 신고 기능 시작
//////////////////////////////////////////////////
include_once(G5_ADMIN_PATH.'/boardreport.lib.php');

// 설정 로드
$conf = function_exists('g5_report_conf') ? g5_report_conf() : [];

// 신고 누적 임계치
$REPORT_HIDE_LIMIT = (int)($conf['hide_limit_post'] ?? 5);
if (!defined('REPORT_HIDE_LIMIT')) define('REPORT_HIDE_LIMIT', $REPORT_HIDE_LIMIT);

if (empty($report_table)) { $report_table = $g5['board_table'].'_report'; }

$sql_report = "
    SELECT COUNT(*) AS wr_report_count 
    FROM `{$report_table}` r
    WHERE r.bo_table = '{$bo_table}'
      AND r.wr_id    = '{$wr_id}'
      AND r.comment_id = 0
";
$row_report = sql_fetch($sql_report);
$wr_report_count = (int)($row_report['wr_report_count'] ?? 0);

// 권한 체크
$is_owner_post = isset($member['mb_id']) && $member['mb_id'] && ($member['mb_id'] === $view['mb_id']);
$can_view_post = $is_admin || $is_owner_post;

// 잠금 여부
$is_locked_post = (isset($view['wr_report']) && $view['wr_report'] === '잠금');

// 일반 사용자에게만 가림
$should_hide_post = (!$can_view_post) && ($is_locked_post || ($wr_report_count >= REPORT_HIDE_LIMIT));

// 신고 CSS 추가
add_stylesheet('<link rel="stylesheet" href="'.G5_CSS_URL.'/report.css">', 0);
//////////////////////////////////////////////////
//// 신고 기능 끝
//////////////////////////////////////////////////

//필드분할
$wr_3 = isset($view["wr_3"]) ? explode("|", $view["wr_3"]) : [];
$mb = get_member($view['mb_id']);

?>
<style>
#scroll_container {margin-top: 20px;}
#scroll_container .rb_bbs_top{display: none;}

/* 신고 관련 추가 스타일 */
.report-btn {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    background: #f5f5f5;
    border-radius: 4px;
    font-size: 13px;
    color: #666;
    transition: all 0.2s;
    margin-left: 5px;
}
.report-btn:hover {
    background: #ff4444;
    color: white;
}
.report-count-badge {
    display: inline-block;
    background: #ff4444;
    color: white;
    border-radius: 10px;
    padding: 0 6px;
    font-size: 11px;
    margin-left: 5px;
}
.report-admin-lock-banner {
    background: #fff3cd;
    border: 1px solid #ffc107;
    padding: 15px;
    margin: 20px 0;
    border-radius: 4px;
    text-align: center;
}
.report-admin-badge {
    background: #ffc107;
    color: #333;
    padding: 2px 8px;
    border-radius: 3px;
    font-weight: bold;
    margin-right: 10px;
}
.report-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
}
.report-modal-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}
.report-modal-box {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    max-height: 80vh;
    overflow-y: auto;
}
.report-modal-inner {
    padding: 20px;
}
</style>

<script src="<?php echo G5_JS_URL; ?>/viewimageresize.js"></script>

<div class="rb_bbs_wrap" style="width:<?php echo $width; ?>">
       
    <div class="btns_gr_wrap">
      
       <!-- $rb_core['sub_width'] 는 반드시 포함해주세요 (환경설정 > 서브가로폭에 따른 버튼의 위치 설정) -->
       <div class="sub" style="width:<?php echo $rb_core['sub_width'] ?>px;">
            
            <div class="btns_gr">
               <?php if ($admin_href) { ?>
               <button type="button" class="fl_btns" onclick="window.open('<?php echo $admin_href ?>');">
               <img src="<?php echo $board_skin_url ?>/img/ico_set.svg">
               <span class="tooltips">관리</span>
               </button>
               <?php } ?>
               
               <?php if ($scrap_href) { ?>
               <a class="fl_btns" href="<?php echo $scrap_href;  ?>" target="_blank" onclick="win_scrap(this.href); return false;">
               <img src="<?php echo $board_skin_url ?>/img/ico_scr.svg">
               <span class="tooltips">스크랩</span>
               </a>
               <?php } ?>
               
               <?php // 신고 버튼 추가
               if (!$is_locked_post) { ?>
               <button type="button" class="fl_btns" onclick="handleReportClick('<?php echo $bo_table; ?>', '<?php echo $view['wr_id']; ?>')">
               <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
               <span class="tooltips">신고</span>
               </button>
               <?php } ?>
               
               <?php // 관리자용 신고내역
               if (!empty($is_admin) && $wr_report_count > 0) {
                   $admin_report_url = G5_ADMIN_URL.'/boardreport_list.php?bo_table='.urlencode($bo_table).'&kw='.$view['wr_id'];
               ?>
               <a href="<?php echo $admin_report_url; ?>" class="fl_btns" target="_blank">
               <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path><line x1="4" y1="22" x2="4" y2="15"></line></svg>
               <span class="tooltips">신고내역</span>
               <?php if ($wr_report_count > 0) { ?>
               <span id="report_count_badge" class="report-count-badge" data-count="<?php echo $wr_report_count; ?>">
               <?php echo $wr_report_count; ?>
               </span>
               <?php } ?>
               </a>
               <?php } ?>
               
               <?php if ($list_href) { ?>
               <button type="button" class="fl_btns" onclick="location.href='<?php echo $list_href ?>';">
               <img src="<?php echo $board_skin_url ?>/img/ico_list.svg">
               <span class="tooltips">목록</span>
               </button>
               <?php } ?>

               
               <?php if ($write_href) { ?>
               <button type="button" class="fl_btns main_color_bg" onclick="location.href='<?php echo $write_href ?>';">
               <img src="<?php echo $board_skin_url ?>/img/ico_write.svg">
               <span class="tooltips">글 등록</span>
               </button>
               <?php } ?>
               
            </div>
            
            <div class="cb"></div>
        </div>
    </div>
    
    <?php
    // 관리자 전용 잠금 알림
    if (!empty($is_admin) && isset($view['wr_report']) && $view['wr_report'] === '잠금') {
        $admin_report_url = G5_ADMIN_URL.'/boardreport_list.php?bo_table='.urlencode($bo_table).'&kw='.$view['wr_id'];
    ?>
    <div class="report-admin-lock-banner">
        <span class="report-admin-badge">신고 누적 잠금</span>
        이 게시글은 신고가 누적되어 잠금 처리되었습니다.
    </div>
    <?php } ?>
    
    <?php if ($should_hide_post) { ?>
    <!-- 신고로 인한 컨텐츠 숨김 -->
    <div class="bbs_sv_wrap">
        <div class="report-admin-lock-banner" style="margin: 50px 20px;">
            <span class="report-admin-badge">잠금됨</span><br><br>
            신고가 누적되어 본문이 비공개 처리되었습니다.<br>
            관리자의 확인 후 복구될 수 있습니다.
        </div>
    </div>
    
    <?php } else { ?>
    <!-- 정상 컨텐츠 표시 -->
    <div class="bbs_sv_wrap">
       
       
        <ul class="bbs_sv_wrap_ul2">
            <div class="gap_btm_bd flex_top_cat">
                
                <?php if ($category_name) { ?>
                <li class="view_info_span"><?php echo $view['ca_name'] ?></li>
                <?php } ?>
                
                <li class="rb_bbs_for_mem_names">
                        <?php
                        $view['icon_new'] = "";
                        if ($view['wr_datetime'] >= date("Y-m-d H:i:s", G5_SERVER_TIME - ($board['bo_new'] * 3600)))
                            $view['icon_new'] = "<span class=\"lb_ico_new\">신규</span>";
                        $view['icon_hot'] = "";
                        if ($board['bo_hot'] > 0 && $view['wr_hit'] >= $board['bo_hot'])
                            $view['icon_hot'] = "<span class=\"lb_ico_hot\">인기</span>";

                        echo $view['icon_new']; //뉴아이콘
                        echo $view['icon_hot']; //인기아이콘 
                        ?>
                </li>

                
                <div id="bo_v_share">
                        <?php include_once(G5_SNS_PATH."/view.sns.skin.php"); ?>
                        <ul class="copy_urls">
                            <li>
                                <a href="javascript:void(0);" id="data-copy">
                                   <img src="<?php echo $board_skin_url ?>/img/ico_sha.png" alt="공유링크 복사" width="32">
                                </a>
                            </li>
                            <?php
                            $currents_url = G5_URL.$_SERVER['REQUEST_URI'];
                            ?>
                            <input type="hidden" id="data-area" class="data-area" value="<?php echo $currents_url ?>">
                            <script>
                                $(document).ready(function() {

                                    $('#data-copy').click(function() {
                                        $('#data-area').attr('type', 'text');
                                        $('#data-area').select();
                                        var copy = document.execCommand('copy');
                                        $('#data-area').attr('type', 'hidden');
                                        if (copy) {
                                            alert("공유 링크가 복사 되었습니다.");
                                        }
                                    });

                                });
                            </script>
                        </ul>

                </div>
                
                
            </div>
            <div class="gap_btm_bd">

                <ul class="font-B view_info_tit"><?php echo get_text($view['wr_subject']);?></ul>
                
                <?php if(isset($view['wr_8']) && $view['wr_8'] == "판매완료") { ?>
                    <ul class="view_info_sub">판매 완료된 상품 입니다.</ul>
                <?php } else if(isset($view['wr_8']) && $view['wr_8'] == "예약중") { ?>
                    <ul class="view_info_sub">거래 예약중인 상품 입니다.</ul>
                <?php } else { ?>
                    <ul class="view_info_sub">판매금액</ul>
                <?php } ?>
                
                <?php if(isset($view['wr_6']) && $view['wr_6'] || isset($view['wr_7']) && $view['wr_7']) { ?>
                <ul class="font-B view_info_pri1 main_color">
                    <?php if(isset($view['wr_7']) && $view['wr_7']) { ?>
                    <li><?php if(isset($view['wr_8']) && $view['wr_8'] == "판매완료") { ?><strike style="opacity:0.5; color:#999"><?php } ?>별도협의<?php if(isset($view['wr_8']) && $view['wr_8'] == "판매완료") { ?></strike><?php } ?></li>
                    <?php } else { ?>
                    <li><?php if(isset($view['wr_8']) && $view['wr_8'] == "판매완료") { ?><strike style="opacity:0.5; color:#999"><?php } ?><?php echo isset($view['wr_6']) ? number_format($view['wr_6']) : ''; ?>원<?php if(isset($view['wr_8']) && $view['wr_8'] == "판매완료") { ?></strike><?php } ?></li>
                    <?php } ?>
                </ul>
                <?php } ?>
                
                
                
                <ul class="view_info_btns">
                    
<!--  추천 비추천 시작 { -->
<?php if ( $good_href || $nogood_href) { ?>
<div id="bo_v_act">
    <?php if ($good_href) { ?>
    <span class="bo_v_act_gng">
        <a href="javascript:void(0);" onclick="<?php if(!$is_member) { ?>alert('로그인 후 이용하실 수 있습니다.');<?php } else { ?>board_good('good', '<?php echo $bo_table ?>', '<?php echo $wr_id ?>');<?php } ?>" id="good_button" class="bo_v_good">추천해요 <strong id="good_count"><?php echo number_format($view['wr_good']) ?></strong></a>
        <b id="bo_v_act_good" style="display:none;" class="font-R"></b>
    </span>
    <?php } ?>
    <?php if ($nogood_href) { ?>
    <span class="bo_v_act_gng">
        <a href="javascript:void(0);" onclick="<?php if(!$is_member) { ?>alert('로그인 후 이용하실 수 있습니다.');<?php } else { ?>board_good('nogood', '<?php echo $bo_table ?>', '<?php echo $wr_id ?>');<?php } ?>" id="nogood_button" class="bo_v_nogood">별로에요 <strong id="nogood_count"><?php echo number_format($view['wr_nogood']) ?></strong></a>
        <b id="bo_v_act_nogood" style="display:none;" class="font-R"></b>
    </span>
    <?php } ?>
</div>
<?php } else {
        if($board['bo_use_good'] || $board['bo_use_nogood']) {
    ?>
<div id="bo_v_act">
    <?php if($board['bo_use_good']) { ?>
        <span class="bo_v_act_gng">
            <a href="javascript:void(0);" onclick="alert('이미 참여하셨습니다.');" class="bo_v_good">추천해요 <strong id="good_count"><?php echo number_format($view['wr_good']) ?></strong></a>
            <b id="bo_v_act_good" style="display:none;" class="font-R"></b>
        </span>
    <?php } ?>
    <?php if($board['bo_use_nogood']) { ?>
        <span class="bo_v_act_gng">
            <a href="javascript:void(0);" onclick="alert('이미 참여하셨습니다.');" class="bo_v_nogood">별로에요 <strong id="nogood_count"><?php echo number_format($view['wr_nogood']) ?></strong></a>
            <b id="bo_v_act_nogood" style="display:none;" class="font-R"></b>
        </span>
    <?php } ?>
</div>
<?php
        }
    }
?>
<!-- }  추천 비추천 끝 -->

<script>
var processing = false;

function board_good(type, bo_table, wr_id) {
    if (processing) {
        alert('처리 중입니다. 잠시만 기다려주세요.');
        return;
    }
    
    processing = true;
    
    $.ajax({
        url: g5_bbs_url + "/ajax.good.php",
        type: "POST",
        data: { 
            bo_table: bo_table, 
            wr_id: wr_id, 
            good: type 
        },
        dataType: "json",
        success: function(data) {
            processing = false;
            
            if(data.error) {
                alert(data.error);
                return;
            }
            
            if(data.status == "ok") {
                if(type == "good") {
                    $("#good_count").text(number_format(String(data.good)));
                } else {
                    $("#nogood_count").text(number_format(String(data.nogood)));
                }
                
                var msg = type == "good" ? "추천하였습니다." : "비추천하였습니다.";
                $("#bo_v_act_" + type).html(msg).stop(true,true).fadeIn(300).delay(2000).fadeOut(300);
                
                $("#" + type + "_button").attr('onclick', "alert('이미 참여하셨습니다.');");
            }
        },
        error: function(xhr, status, error) {
            processing = false;
            console.log("AJAX Error:", status, error);
            console.log("Response:", xhr.responseText);
            alert("요청 처리 중 오류가 발생했습니다.\n\n" + error);
        }
    });
}

function number_format(num) {
    if(typeof num === 'undefined' || num === null) return '0';
    var n = parseInt(String(num).replace(/,/g, '')) || 0;
    return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}
</script>
                    
                    
                    <?php if(isset($view['wr_8']) && $view['wr_8'] == "판매완료") { ?>
                        
                        <a href="javascript:alert('판매 완료된 상품 입니다.');" class="view_info_btns_btn1 font-R">판매완료</a>
                        
                    <?php } else { ?>
                    
                        <?php if (isset($view['wr_5']) && $view['wr_5'] == "1") { ?>
                        <a href="<?php echo !empty($mb['mb_hp']) ? 'tel:'.$mb['mb_hp'] : 'javascript:alert(\'등록된 연락처가 없습니다.\')'; ?>" class="view_info_btns_btn1 font-R"><?php echo !empty($mb['mb_hp']) ? $mb['mb_hp'] : '연락처 없음'; ?></a>
                        <?php } else { ?>
                            <?php if(isset($mb['mb_id']) && $mb['mb_id']) { ?> 
                                <a href="<?php echo G5_BBS_URL ?>/memo_form.php?me_recv_mb_id=<?php echo $mb['mb_id'] ?>" onclick="win_memo(this.href); return false;" class="view_info_btns_btn1 font-R">쪽지보내기</a>
                            <?php } ?>
                        <?php } ?>
                    
                    <?php } ?>


                </ul>
                
                <?php if($view['mb_id'] == $member['mb_id'] || $is_admin) { ?>
                <ul class="status_exchange_wrap">
                    <select id="status_exchange" name="wr_status" class="select">
                        <option value="">판매상태 변경</option>
                        <option value="판매중" <?php if (isset($view['wr_8']) && $view['wr_8'] == "판매중") { ?>selected<?php } ?>>판매중</option>
                        <option value="예약중" <?php if (isset($view['wr_8']) && $view['wr_8'] == "예약중") { ?>selected<?php } ?>>예약중</option>
                        <option value="판매완료" <?php if (isset($view['wr_8']) && $view['wr_8'] == "판매완료") { ?>selected<?php } ?>>판매완료</option>
                    </select>
                </ul>
                
                <script>
                    $('#status_exchange').change(function() {
                        statusAjax();
                    });
                    
                    function statusAjax() {
                        var bo_table = "<?php echo isset($bo_table) ? $bo_table : ''; ?>";
                        var wr_id = "<?php echo isset($wr_id) ? $wr_id : ''; ?>";
                        var wr_status = $('select[name="wr_status"]').val();
                        var old_status = "<?php echo isset($view['wr_8']) ? get_text($view['wr_8']) : ''; ?>";
                        
                        if(wr_status) {
                            
                            if(wr_status == old_status) {
                                
                                alert('변경하실 판매 상태와 같습니다.');
                                $('select[name="wr_status"]').val('');
                                return false;
                                
                            } else { 

                                if (confirm('판매 상태를 [' + wr_status + '] 으로 변경하시겠습니까?')) {
                                    $.ajax({
                                        url: '<?php echo $board_skin_url ?>/ajax.status.php',
                                        method: 'POST',
                                        dataType: 'json',
                                        data: {
                                            "bo_table": bo_table,
                                            "wr_id": wr_id,
                                            "wr_status": wr_status,
                                        },
                                        success: function(data) {
                                            if (data.status == 'ok') {
                                                location.reload();
                                            } else {
                                                alert('판매상태 변경에 실패 하였습니다.');
                                                $('select[name="wr_status"]').val('');
                                            }
                                        },
                                        error: function(err) {
                                            alert('문제가 발생 했습니다. 다시 시도해주세요.');
                                            $('select[name="wr_status"]').val('');
                                        }
                                    });
                                } else {
                                    $('select[name="wr_status"]').val('');
                                }
                                
                            }
                            
                        } else { 
                            alert('변경하실 판매상태를 선택해주세요.');
                            $('select[name="wr_status"]').val('');
                        }

                    }
            
                </script>
                <?php } ?>
                
                
            </div>
            

            <div class="gap_btm_bd">
                
                <ul class="opt_box_wrap">
                    <li class="font-B">등록일시</li>
                    <li><?php echo $view['wr_datetime']; ?></li>
                </ul>
                
                <ul class="opt_box_wrap">
                    <li class="font-B">판매상태</li>
                    <li class="font-B"><?php echo !empty($view['wr_8']) ? get_text($view['wr_8']) : '판매중'; ?></li>
                </ul>

                <ul class="opt_box_wrap">
                    <li class="font-B">거래옵션</li>
                    <li><?php echo !empty($view['wr_1']) ? get_text($view['wr_1']) : '배송불가'; ?> <?php echo !empty($view['wr_2']) ? '('.get_text($view['wr_2']).')' : '(직거래 불가)'; ?></li>
                </ul>
                
                <ul class="opt_box_wrap">
                    <li class="font-B">상품상태</li>
                    <li><?php echo !empty($view['wr_4']) ? get_text($view['wr_4']) : '정보없음'; ?></li>
                </ul>
                
                

            </div>

            
            <?php 
            if(isset($board['bo_use_signature']) && $board['bo_use_signature']) {
                // 서명 출력
                include_once(G5_PATH.'/rb/rb.mod/signature/signature.skin.php');
            } 
            ?>
               
            <?php if(isset($mb['mb_id']) && $mb['mb_id']) { ?> 
            
            <div class="seller_info_wrap">
                <span class="seller_info_wrap_tit font-B"><?php echo !empty($mb['mb_nick']) ? $mb['mb_nick'] : 'Guest'; ?>님의 상품</span>
                
                <?php
                    $tmp_write_table = $g5['write_prefix'].$bo_table;
                    
                    $sqls = " select * from {$tmp_write_table} where wr_id != '{$wr_id}' and mb_id = '{$mb['mb_id']}' and wr_is_comment = '0' and wr_option NOT IN ('secret') order by wr_id desc limit 6 ";
                    $results = sql_query($sqls);
                
                    $thumb_widths = 120;
                    $thumb_heights = 120;
                ?>

                
                <!-- { -->
                <ul class="bbs_main_wrap_thumb_con">
                    <div class="swiper-container swiper-container-deal">
                        <ul class="swiper-wrapper swiper-wrapper-deal">
                
                <?php 
                for ($i=0; $rows=sql_fetch_array($results); $i++) { 

                    $hrefs = get_pretty_url($bo_table, $rows['wr_id']);
                    
                    //썸네일
                    $thumbs = get_list_thumbnail($bo_table, $rows['wr_id'], $thumb_widths, $thumb_heights, false, true);
                    
                    if($thumbs['src']) {
                        $imgs = $thumbs['src'];
                    } else {
                        $imgs = G5_THEME_URL.'/rb.img/no_image.png';
                    }
                    
                    //썸네일 출력 class="skin_list_image" 필수 (높이값 설정용)
                    $img_contents = '<img src="'.$imgs.'" class="skin_list_image">';

                ?>


                            <dd class="swiper-slide swiper-slide-deal" onclick="location.href='<?php echo $hrefs ?>';">
                                
                                <div>
                                    
                                    
                                    <ul class="bbs_main_wrap_con_ul1">
                                        <?php if($thumbs['src']) { ?>
                                        <a href="<?php echo $hrefs ?>"><?php echo run_replace('thumb_image_tag', $img_contents, $thumbs); ?></a>
                                        <?php } else { ?>
                                        <a href="<?php echo $hrefs ?>"><img src="<?php echo G5_THEME_URL ?>/rb.img/no_image.png" class="skin_list_image" title=""></a>
                                        <?php } ?>
                                    </ul>
                                    
                                    

                                    
                                    <ul class="bbs_main_wrap_con_ul2" <?php if(!$thumbs['src']) { ?>style="padding-right:0px;"<?php } ?>>
                                        <li class="bbs_main_wrap_con_subj cut"><a href="<?php echo $hrefs ?>" class="font-B"><?php echo get_text($rows['wr_subject']); ?></a></li>
                                        


                                            <li class="bbs_main_wrap_con_info">
                                                <?php if($rows['ca_name']) { ?>
                                                <?php echo $rows['ca_name'] ?><br>
                                                <?php } ?>
                                                <?php echo passing_time($rows['wr_datetime']) ?>
                                            </li>

                                    </ul>
                                    <div class="cb"></div>
                                </div>
                            </dd>
                            <!-- } -->
                            
                            <?php }  ?>
                            <?php if ($i == 0) { //게시물이 없을 때  ?>
                            <dd class="no_data" style="width:100% !important;">등록한 상품이 없습니다.</dd>
                            <?php }  ?>
                            

                            
                        </ul>
                    </div>
                    
                    <!-- 모듈세팅 { -->
                    <script>
                                                
                        var swiper = new Swiper('.swiper-container-deal', {
                            slidesPerColumnFill: 'row', //세로형
                            slidesPerView: 3, //가로갯수
                            slidesPerColumn: 999, // 세로갯수
                            spaceBetween: 10, // 간격
                            observer: true, //리셋
                            observeParents: true, //리셋
                            touchRatio: 0, // 드래그 가능여부
                        });
                        

                    </script>
                    <!-- } -->
                    
                </ul>
                <!-- } -->
                
                <button type="button" onclick="location.href='<?php echo G5_BBS_URL ?>/board.php?bo_table=<?php echo $bo_table ?>&sop=and&sfl=mb_id&stx=<?php echo $mb['mb_id'] ?>';" class="more_all_btn font-B">상품 전체보기</button>
                
            </div>
            <?php } ?>
                
                <!-- 첨부파일 / 링크 { -->
                <?php
                $cnt = 0;
                if ($view['file']['count']) {
                    for ($i=0; $i<count($view['file']); $i++) {
                        if (isset($view['file'][$i]['source']) && $view['file'][$i]['source'] && !$view['file'][$i]['view'])
                            $cnt++;
                    }
                }

                ?>

                <?php if($cnt) { ?>
                
                <div class="down_link_wrap">
                <h2 id="container_title" class="none_btm_pd font-R">다운로드</h2>

                    <div class="rb_bbs_file">
                        <?php
                        // 가변 파일
                        for ($i=0; $i<count($view['file']); $i++) {
                            if (isset($view['file'][$i]['source']) && $view['file'][$i]['source'] && !$view['file'][$i]['view']) {
                        ?>
                        <ul class="rb_bbs_file_for view_file_download" onclick="location.href='<?php echo $view['file'][$i]['href']; ?>';">
                            <i><img src="<?php echo $board_skin_url ?>/img/ico_file.svg"></i>
                            <a href="javascript:void(0);"><?php echo $view['file'][$i]['source'] ?></a> <?php echo number_format($view['file'][$i]['download']); ?>회
                            <?php if($view['file'][$i]['content']) { ?>
                            <li class="file_contents"><?php echo $view['file'][$i]['content'] ?></li>
                            <?php } ?>
                        </ul>
                        <?php
                            }
                        }
                        ?>
                    </div>
                    
                </div>
                <?php } ?>



                <?php if(isset($view['link']) && array_filter($view['link'])) { ?>
                <div class="down_link_wrap">
                <h2 id="container_title" class="none_btm_pd font-R">링크</h2>
                    <div class="rb_bbs_file">
                        <?php
                        // 링크
                        $cnt = 0;
                        for ($i=1; $i<=count($view['link']); $i++) {
                            if ($view['link'][$i]) {
                                $cnt++;
                                $link = cut_str($view['link'][$i], 70);
                        ?>
                        <ul class="rb_bbs_file_for" onclick="window.open('<?php echo $view['link_href'][$i]; ?>');">
                            <i><img src="<?php echo $board_skin_url ?>/img/ico_link.svg"></i>
                            <a href="javascript:void(0);"><?php echo $link ?></a>　<?php echo $view['link_hit'][$i] ?>회
                        </ul>
                        <?php
                            }
                        }
                        ?>
                    </div>
                </div>
                <?php } ?>
                
            
            
            
            
        </ul>
        
        
        
        
        <ul class="bbs_sv_wrap_ul1">
            <div class="po_rel">
                <?php 
                $v_img_count = count($view['file']);
                if ($v_img_count > 0) {
                    echo "<div class=\"swiper-container swiper-container-pf\"><ul class=\"swiper-wrapper swiper-wrapper-pf\">\n";
                    for ($i = 0; $i < $v_img_count; $i++) {
                        if (isset($view['file'][$i]['view']) && $view['file'][$i]['view']) {
                            echo "<li class=\"swiper-slide swiper-slide-pf\">" . get_view_thumbnail($view['file'][$i]['view']) . "</li>";
                        }
                    }
                    echo "</ul><div class=\"swiper-pagination swiper-pagination-pf\"></div></div>\n";
                ?>

                <script>
                    var swiper = new Swiper('.swiper-container-pf', {
                        slidesPerView: 1,
                        spaceBetween: 0,
                        observer: true,
                        observeParents: true,
                        touchRatio: 1,
                        autoHeight: true,

                        autoplay: {
                            delay: 3000,
                            disableOnInteraction: false,
                        },

                        pagination: {
                            el: '.swiper-pagination-pf',
                            dynamicBullets: true,
                            clickable: true,
                        }

                    });
                </script>

                    <?php
                    }

                ?>
                
                <?php if(isset($view['wr_8']) && $view['wr_8'] == "예약중") { ?>
                <span class="po_rel_blank">
                    <dd class="font-R">
                    <img src="<?php echo $board_skin_url ?>/img/deal_ico_y.svg"><br><br>
                    이미 다른분과 거래예약이 된<br>
                    상품입니다.
                    </dd>
                </span>
                <?php } else if(isset($view['wr_8']) && $view['wr_8'] == "판매완료") { ?>
                <span class="po_rel_blank">
                    <dd class="font-R">
                    판매 완료된 상품입니다.
                    </dd>
                </span>
                <?php } ?>
                
            </div>
            

            <!-- 본문 내용 시작 { -->
            <div id="bo_v_con">
                <h2 id="container_title" class="mo_pd_none">상세정보</h2>
                <?php $original_content = isset($view['content']) ? $view['content'] : ''; ?>
                <?php echo get_view_thumbnail($view['content']); ?>
            </div>
            
            
            <?php 
                if(isset($wr_3[0]) && $wr_3[0]) {
                if(isset($config['cf_kakao_js_apikey']) && $config['cf_kakao_js_apikey']) { 

                    //기본좌표
                    if (!isset($wr_3[4])) {
                        $wr_3[4] = 37.566400714093284;
                    }
                    if (!isset($wr_3[5])) {
                        $wr_3[5] = 126.9785391897507;
                    }


                ?>
                <h2 id="container_title" class="mo_pd_none">직거래 위치</h2>

                <div class="rc_wrap3">
                            <script type="text/javascript" src="//dapi.kakao.com/v2/maps/sdk.js?appkey=<?php echo $config['cf_kakao_js_apikey'] ?>&libraries=services"></script>
                            <div style="background-color:#f9f9f9; width:100%; height:200px; border-radius:10px;" id="map"></div>

                            <script>
                            var mapContainer = document.getElementById('map'),
                                mapOption = {
                                    center: new daum.maps.LatLng('<?php echo $wr_3[4] ?>', '<?php echo $wr_3[5] ?>'),
                                    level: 3
                                };

                            var map = new daum.maps.Map(mapContainer, mapOption);
                            var mapTypeControl = new daum.maps.MapTypeControl();
                            map.addControl(mapTypeControl, daum.maps.ControlPosition.TOPRIGHT);

                            var zoomControl = new daum.maps.ZoomControl();
                            map.addControl(zoomControl, daum.maps.ControlPosition.RIGHT);

                            var geocoder = new daum.maps.services.Geocoder();

                            var marker = new daum.maps.Marker({
                                map: map,
                                position: map.getCenter()
                            });

                            var markerPosition = marker.getPosition(); 
                            map.relayout();
                            map.setCenter(markerPosition);

                            </script>
                            <div class="flex_gbtns">
                            <span class="rc_sub_title2 font-R"><?php echo isset($wr_3[0]) ? get_text($wr_3[0]) : ''; ?> <?php echo isset($wr_3[1]) ? get_text($wr_3[1]) : ''; ?></span>
                            <a href="https://map.kakao.com/?q=<?php echo isset($wr_3[4]) ? get_text($wr_3[4]) : ''; ?> <?php echo isset($wr_3[5]) ? get_text($wr_3[5]) : ''; ?>" target="_blank">길찾기</a>
                            </div>
                </div>
                <?php } ?>
                <?php } ?>
            
            

            <div class="at_cont">
                <?php echo isset($config['cf_title']) ? $config['cf_title'] : ''; ?>은(는) 통신판매의 당사자가 아닙니다. 전자상거래 등에서의 소비자보호에 관한 법률 등 관련 법령 및 <?php echo isset($config['cf_title']) ? $config['cf_title'] : ''; ?>의 약관에 따라 상품, 상품정보, 거래에 관한 책임은 개별 판매자에게 귀속하고, <?php echo isset($config['cf_title']) ? $config['cf_title'] : ''; ?>은(는) 원칙적으로 회원간 거래에 대하여 책임을 지지 않습니다.
            </div>

            
            
            <div class="mt-40">
                
                
                <!-- 게시물 정보 { -->
                <ul class="rb_bbs_for_mem rb_bbs_for_mem_view">

                    <li class="fl">
                        <dd><?php echo $view['name'] ?></dd>
                    </li>
                    <li class="rb_bbs_for_btm_info">
                       
                        
                        <dd><span><?php echo passing_time3($view['wr_datetime']) ?></span></dd>
                        <dd>
                            <i><img src="<?php echo $board_skin_url ?>/img/ico_eye.svg"></i>
                            <span><?php echo number_format($view['wr_hit']); ?></span>
                        </dd>

                        <dd>
                            <i><img src="<?php echo $board_skin_url ?>/img/ico_comm.svg"></i>
                            <span><?php echo number_format($view['wr_comment']); ?></span>
                        </dd>

                    </li>

                    <div class="cb"></div>

                </ul>
                <!-- } -->




                <ul class="btm_btns">


                    <dd class="btm_btns_right">

                        <?php if ($list_href) { ?>
                        <a href="<?php echo $list_href ?>" type="button" class="fl_btns font-B">목록</a>
                        <?php } ?>


                        <?php if ($scrap_href) { ?>
                        <a href="<?php echo $scrap_href;  ?>" class="fl_btns font-B" target="_blank" onclick="win_scrap(this.href); return false;">스크랩</a>
                        <?php } ?>

                        <?php // 신고 버튼 추가 - 모바일 영역
                        if (!$is_locked_post) { ?>
                        <a href="javascript:void(0);" class="fl_btns font-B" onclick="handleReportClick('<?php echo $bo_table; ?>', '<?php echo $view['wr_id']; ?>')">신고</a>
                        <?php } ?>

                        <?php if ($write_href) { ?>
                        <button type="button" name="btn_submit" class="fl_btns main_color_bg" onclick="location.href='<?php echo $write_href ?>';">
                            <img src="<?php echo $board_skin_url ?>/img/ico_write.svg">
                            <span class="font-R">글 등록</span>
                        </button>
                        <?php } ?>

                        <div class="cb"></div>

                    </dd>

                    <div id="bo_v_btns">
                        <?php ob_start(); ?>

                        <?php if($update_href || $delete_href || $copy_href || $move_href || $search_href) { ?>

                            <?php if ($update_href) { ?>
                            <a href="<?php echo $update_href ?>" class="fl_btns">
                            <span class="font-B">수정</span>
                            </a>
                            <?php } ?>

                            <?php if ($copy_href) { ?>
                            <a href="<?php echo $copy_href ?>" onclick="board_move(this.href); return false;" class="fl_btns">
                            <span class="font-B">복사</span>
                            </a>
                            <?php } ?>

                            <?php if ($move_href) { ?>
                            <a href="<?php echo $move_href ?>" onclick="board_move(this.href); return false;" class="fl_btns">
                            <span class="font-B">이동</span>
                            </a>
                            <?php } ?>

                            <?php if ($delete_href) { ?>
                            <a href="<?php echo $delete_href ?>" onclick="del(this.href); return false;" class="fl_btns">
                            <span class="font-B">삭제</span>
                            </a>
                            <?php } ?>

                        <?php } ?>

                        <?php
                        $link_buttons = ob_get_contents();
                        ob_end_flush();
                       ?>

                    </div>


                    <dd class="cb"></dd>


                </ul>

                

                <ul>
                    <?php if ($prev_href || $next_href) { ?>
                    <div class="bo_v_nb">
                        <?php if ($prev_href) { ?><li class="btn_prv" onclick="location.href='<?php echo $prev_href ?>';"><span class="nb_tit">이전글</span><a href="javascript:void(0);"><?php echo $prev_wr_subject;?></a><span class="nb_date"><?php echo str_replace('-', '.', substr($prev_wr_date, '0', '10')); ?></span></li><?php } ?>
                        <?php if ($next_href) { ?><li class="btn_next" onclick="location.href='<?php echo $next_href ?>';"><span class="nb_tit">다음글</span><a href="javascript:void(0);"><?php echo $next_wr_subject;?></a><span class="nb_date"><?php echo str_replace('-', '.', substr($next_wr_date, '0', '10')); ?></span></li><?php } ?>
                    </div>
                    <?php } ?>

                    <?php
                    // 코멘트 입출력
                    include_once(G5_BBS_PATH.'/view_comment.php');
                    ?>
                </ul>
                
                
            </div>
        </ul>
        
        <div class="cb"></div>
    </div>
    <?php } // 정상 컨텐츠 끝 ?>
    
    
    

</div>

<!-- 신고 모달 -->
<div id="report_modal" class="report-modal" style="display:none;">
    <div class="report-modal-bg" onclick="closeReportModal()"></div>
    <div class="report-modal-box">
        <div id="report_modal_inner" class="report-modal-inner">로딩 중...</div>
    </div>
</div>

<script>
const G5_BBS_URL = "<?php echo G5_BBS_URL; ?>";

// g5_is_member 폴백
if (typeof window.g5_is_member === 'undefined') {
  window.g5_is_member = <?php echo $is_member ? 'true' : 'false'; ?>;
}

/** 신고 버튼 클릭 핸들러 */
function handleReportClick(bo_table, wr_id, comment_id = 0) {
  if (window.g5_is_member) {
    openReportForm(bo_table, wr_id, comment_id);
    return;
  }
  const back = encodeURIComponent(location.href);
  if (confirm('회원만 신고할 수 있습니다.\n로그인 하시겠습니까?')) {
    location.href = `${G5_BBS_URL}/login.php?url=${back}`;
  }
}
	
// 모달 열기 + 폼 로드
function openReportForm(bo_table, wr_id, comment_id = 0) {
  const url   = `${G5_BBS_URL}/report_form.php?bo_table=${bo_table}&wr_id=${wr_id}&comment_id=${comment_id}`;
  const modal = document.getElementById('report_modal');
  const inner = document.getElementById('report_modal_inner');

  inner.textContent = "로딩 중...";
  modal.style.display = 'block';
  document.body.style.overflow = 'hidden';
  modal.addEventListener('touchmove', preventBodyScroll, { passive:false });

  fetch(url)
    .then(res => res.text())
    .then(html => {
      inner.innerHTML = html;

      window.addEventListener('keydown', escClose, { once:true });

      const firstInput = inner.querySelector('input, textarea, select, button');
      if (firstInput) firstInput.focus();

      const selected = inner.querySelector('input[name="reason"]:checked');
      const etcBox   = inner.querySelector('.reason-etc-box');
      if (selected && etcBox) {
        etcBox.style.display = (selected.value === '기타' ? 'block' : 'none');
      }
    })
    .catch(() => {
      inner.innerHTML = "<p style='color:red;'>신고 폼 로딩 중 오류가 발생했습니다.</p>";
    });
}

// 모달 내부 change 이벤트 위임
document.addEventListener('change', function(e) {
  if (!document.getElementById('report_modal')) return;

  if (e.target.matches('#report_modal input[name="reason"]')) {
    const selected = e.target.value;
    const etcBox   = document.querySelector('#report_modal .reason-etc-box');
    if (etcBox) etcBox.style.display = (selected === '기타' ? 'block' : 'none');
  }
});

// 배지 업데이트
function updateCommentBadge(comment_id, count) {
  let badge = document.getElementById('cmt_report_badge_' + comment_id);
  if (!badge) {
    const anchor = document.querySelector('#c_' + comment_id + ' .cmt-report-btn');
    if (!anchor) return;
    badge = document.createElement('span');
    badge.id = 'cmt_report_badge_' + comment_id;
    badge.className = 'cmt-report-badge';
    anchor.appendChild(badge);
  }
  badge.dataset.count = String(count);
  badge.textContent   = count > 0 ? String(count) : '';
  badge.style.display = count > 0 ? 'inline-block' : 'none';
}

function updatePostBadge(count) {
  const badge = document.getElementById('report_count_badge');
  if (!badge) return;
  badge.dataset.count = String(count);
  badge.textContent   = count > 0 ? String(count) : '';
  badge.style.display = count > 0 ? 'inline-block' : 'none';
}

function hideCommentContent(comment_id) {
  const p = document.querySelector('#c_' + comment_id + ' .cmt_contents p');
  if (!p) return;
  p.innerHTML = '<div class="cmt-report-hidden-note">신고 누적으로 가려진 댓글입니다. 관리자의 확인 후 복구될 수 있습니다.</div>';
}

function hidePostContent() {
  const con = document.getElementById('bo_v_con');
  if (!con) return;
  <?php if (!$is_admin) { ?>
  location.reload();
  <?php } ?>
}

// 모달 폼 AJAX 제출
document.addEventListener('submit', function(e){
  if (!e.target.matches('#report_modal #report_form')) return;
  e.preventDefault();
  if (!validateReportForm()) return;

  const form = e.target;
  const fd   = new FormData(form);
  fd.append('ajax', '1');

  fetch(`${G5_BBS_URL}/report_update.php`, {
    method: 'POST',
    body: fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.json())
  .then(data => {
    if (!data || !data.ok) {
      alert((data && data.message) || '신고 처리 중 오류가 발생했습니다.');
      return;
    }

    const count     = Number(data.report_count || 0);
    const locked    = !!data.locked;
    const commentId = Number(data.comment_id || 0);

    if (commentId > 0) {
      updateCommentBadge(commentId, count);
      if (locked) hideCommentContent(commentId);
    } else {
      updatePostBadge(count);
      if (locked) hidePostContent();
    }

    alert(data.message || '신고가 접수되었습니다.');
    closeReportModal();
  })
  .catch(() => {
    alert('신고 요청에 실패했습니다. 잠시 후 다시 시도해주세요.');
  });
});

// 유효성 검사
function validateReportForm() {
  const wrap = document.getElementById('report_modal');
  if (!wrap) return true;

  const form = wrap.querySelector('#report_form');
  if (!form) return true;

  const selected = form.querySelector('input[name="reason"]:checked');
  if (!selected) {
    alert('신고 사유를 선택해주세요.');
    return false;
  }
  if (selected.value === '기타') {
    const etcTextarea = form.querySelector('textarea[name="memo"]');
    if (!etcTextarea || !etcTextarea.value.trim()) {
      alert('기타 사유를 입력해주세요.');
      if (etcTextarea) etcTextarea.focus();
      return false;
    }
  }
  return true;
}

function closeReportModal() {
  const modal = document.getElementById('report_modal');
  modal.style.display = 'none';
  document.body.style.overflow = '';
  modal.removeEventListener('touchmove', preventBodyScroll);
}

function preventBodyScroll(e){ e.preventDefault(); }
function escClose(e){ if (e.key === 'Escape') closeReportModal(); }
</script>

<script>
<?php if ($board['bo_download_point'] < 0) { ?>
$(function() {
    $("a.view_file_download").click(function() {
        if(!g5_is_member) {
            alert("다운로드 권한이 없습니다.\n회원이시라면 로그인 후 이용해 보십시오.");
            return false;
        }

        var msg = "파일을 다운로드 하시면 포인트가 차감(<?php echo number_format($board['bo_download_point']) ?>점)됩니다.\n\n포인트는 게시물당 한번만 차감되며 다음에 다시 다운로드 하셔도 중복하여 차감하지 않습니다.\n\n그래도 다운로드 하시겠습니까?";

        if(confirm(msg)) {
            var href = $(this).attr("href")+"&js=on";
            $(this).attr("href", href);

            return true;
        } else {
            return false;
        }
    });
});
<?php } ?>

function board_move(href)
{
    window.open(href, "boardmove", "left=50, top=50, width=500, height=550, scrollbars=1");
}
</script>

<script>
$(function() {
    $("a.view_image").click(function() {
        window.open(this.href, "large_image", "location=yes,links=no,toolbar=no,top=10,left=10,width=10,height=10,resizable=yes,scrollbars=no,status=no");
        return false;
    });

    $("#bo_v_atc").viewimageresize();
});
</script>