<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
include_once(G5_LIB_PATH.'/thumbnail.lib.php');

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css">', 0);

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
   
    <span class="view_info_span mobile"><?php echo date("Y.m.d H:i", strtotime($view['wr_datetime'])) ?></span>
    <h2><?php echo get_text($view['wr_subject']);?></h2>
    
    <!-- 게시물 정보 { -->
    <ul class="rb_bbs_for_mem rb_bbs_for_mem_view">

        <li class="rb_bbs_for_mem_names">
            <?php echo $view['name'] ?> <?php if ($board['bo_use_ip_view']) { echo "<span class='view_info_span_ip'>($ip)</span>"; } ?>
            <span class="view_info_span"><?php echo passing_time3($view['wr_datetime']) ?></span> 
            <span class="view_info_span view_info_span_date"><?php echo date("Y.m.d H:i", strtotime($view['wr_datetime'])) ?></span> 
            <?php if ($category_name) { ?>
            <span class="view_info_span"><a href="<?php echo $view['ca_name_href'] ?>"><?php echo $view['ca_name'] ?></a></span> 
            <?php } ?>
            
            <?php
            $view['icon_new'] = "";
            if ($view['wr_datetime'] >= date("Y-m-d H:i:s", G5_SERVER_TIME - ($board['bo_new'] * 3600)))
                $view['icon_new'] = "<span class=\"lb_ico_new\">새글</span>";
            $view['icon_hot'] = "";
            if ($board['bo_hot'] > 0 && $view['wr_hit'] >= $board['bo_hot'])
                $view['icon_hot'] = "<span class=\"lb_ico_hot\">인기</span>";

            echo $view['icon_new']; //뉴아이콘
            echo $view['icon_hot']; //인기아이콘 
            ?>
        </li>

        <li class="rb_bbs_for_btm_info">
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
    
    <div class="rb_bbs_file">
        <?php
        // 가변 파일
        for ($i=0; $i<count($view['file']); $i++) {
            if (isset($view['file'][$i]['source']) && $view['file'][$i]['source'] && !$view['file'][$i]['view']) {
        ?>
        <ul class="rb_bbs_file_for">
            <i><img src="<?php echo $board_skin_url ?>/img/ico_file.svg"></i>
            <a href="<?php echo $view['file'][$i]['href'];  ?>" class="view_file_download"><?php echo $view['file'][$i]['source'] ?></a> (<?php echo $view['file'][$i]['size'] ?>)　<?php echo number_format($view['file'][$i]['download']); ?>회
            <?php if($view['file'][$i]['content']) { ?>
            <li class="file_contents"><?php echo $view['file'][$i]['content'] ?></li>
            <?php } ?>
        </ul>
        <?php
            }
        }
        ?>
    </div>
    
    <?php } ?>
    
    <?php if(isset($view['link']) && array_filter($view['link'])) { ?>
    
    <div class="rb_bbs_file">
        <?php
        // 링크
        $cnt = 0;
        for ($i=1; $i<=count($view['link']); $i++) {
            if ($view['link'][$i]) {
                $cnt++;
                $link = cut_str($view['link'][$i], 70);
        ?>
        <ul class="rb_bbs_file_for">
            <i><img src="<?php echo $board_skin_url ?>/img/ico_link.svg"></i>
            <a href="<?php echo $view['link_href'][$i] ?>" target="_blank"><?php echo $link ?></a>　<?php echo $view['link_hit'][$i] ?>회
        </ul>
        <?php
            }
        }
        ?>
    </div>
    
    <?php } ?>

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
    
    <!-- 본문 내용 시작 { -->
    <div id="bo_v_con">
    
    <?php if ($should_hide_post) { ?>
        <div class="report-admin-lock-banner">
            <span class="report-admin-badge">잠금됨</span>
            신고가 누적되어 본문이 비공개 처리되었습니다. 관리자의 확인 후 복구될 수 있습니다.
        </div>
    <?php } else { ?>
        <?php
        // 파일 출력
        $v_img_count = count($view['file']);
        if($v_img_count) {
            echo "<div id=\"bo_v_img\">\n";
            foreach($view['file'] as $view_file) {
                echo get_file_thumbnail($view_file);
            }
            echo "</div>\n";
        }
        ?>
        <?php echo get_view_thumbnail($view['content']); ?>
    <?php } ?>
    
    </div>
    
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
    var processing = false; // 중복 클릭 방지

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
                    // 숫자 업데이트
                    if(type == "good") {
                        $("#good_count").text(number_format(String(data.good)));
                    } else {
                        $("#nogood_count").text(number_format(String(data.nogood)));
                    }
                    
                    // 메시지 표시
                    var msg = type == "good" ? "추천하였습니다." : "비추천하였습니다.";
                    $("#bo_v_act_" + type).html(msg).stop(true,true).fadeIn(300).delay(2000).fadeOut(300);
                    
                    // 버튼 클릭 이벤트 변경 (이미 참여한 경우)
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
    
    <?php 
    if(isset($board['bo_use_signature']) && $board['bo_use_signature']) {
        // 서명 출력
        include_once(G5_PATH.'/rb/rb.mod/signature/signature.skin.php');
    } 
    ?>
    
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

      // ESC 닫기
      window.addEventListener('keydown', escClose, { once:true });

      // 포커스 이동
      const firstInput = inner.querySelector('input, textarea, select, button');
      if (firstInput) firstInput.focus();

      // "기타" 선택 상태 체크
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
  con.innerHTML = '<div class="report-post-hidden-note">신고 누적으로 가려진 게시글입니다. 관리자의 확인 후 복구될 수 있습니다.</div>';
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

    // 이미지 리사이즈
    $("#bo_v_atc").viewimageresize();
});
</script>