<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
include_once(G5_LIB_PATH.'/thumbnail.lib.php');

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css">', 0);

//////////////////////////////////////////////////
//// 1. 신고 게시글 시작 /////////////////////////////
//////////////////////////////////////////////////

include_once(G5_ADMIN_PATH.'/boardreport.lib.php');

// 설정 로드
$conf = function_exists('g5_report_conf') ? g5_report_conf() : [];

// 신고 누적 임계치: 설정값 우선, 없으면 5
$REPORT_HIDE_LIMIT = (int)($conf['hide_limit_post'] ?? 5);
if (!defined('REPORT_HIDE_LIMIT')) define('REPORT_HIDE_LIMIT', $REPORT_HIDE_LIMIT);

if (empty($report_table)) { $report_table = $g5['board_table'].'_report'; }

$sql_report = "
	SELECT
	  (
		SELECT COUNT(*)
		FROM `{$report_table}` r
		WHERE r.bo_table = '{$bo_table}'
		  AND r.wr_id    = '{$wr_id}'
		  AND r.comment_id = 0
	  ) AS wr_report_count
";
$row_report = sql_fetch($sql_report);
$wr_report_count = (int)($row_report['wr_report_count'] ?? 0);

// 글 작성자 여부 + 열람 가능 주체
$is_owner_post = isset($member['mb_id']) && $member['mb_id'] && ($member['mb_id'] === $view['mb_id']);
$can_view_post = $is_admin || $is_owner_post;

// wr_report 잠금 여부(글 레코드에 wr_report 사용 중)
$is_locked_post = (isset($view['wr_report']) && $view['wr_report'] === '잠금');

// 일반 사용자에게만 가림
$should_hide_post = (!$can_view_post) && ( $is_locked_post || ($wr_report_count >= REPORT_HIDE_LIMIT) );

add_stylesheet('<link rel="stylesheet" href="'.G5_CSS_URL.'/report.css">', 0);
//////////////////////////////////////////////////
//// 1. 신고 게시글 끝 //////////////////////////////
//////////////////////////////////////////////////
?>

<script src="<?php echo G5_JS_URL; ?>/viewimageresize.js"></script>

<!-- 게시물 읽기 시작 { -->

<article id="bo_v" style="width:<?php echo $width; ?>">
    <header>
        <h2 id="bo_v_title">
            <?php if ($category_name) { ?>
            <span class="bo_v_cate"><?php echo $view['ca_name']; // 분류 출력 끝 ?></span> 
            <?php } ?>
            <span class="bo_v_tit">
            <?php
            echo cut_str(get_text($view['wr_subject']), 70); // 글제목 출력
            ?></span>
        </h2>
    </header>

    <section id="bo_v_info">
        <h2>페이지 정보</h2>
        <div class="profile_info">
        	<div class="pf_img"><?php echo get_member_profile_img($view['mb_id']) ?></div>
        	<div class="profile_info_ct">
        		<span class="sound_only">작성자</span> <strong><?php echo $view['name'] ?><?php if ($is_ip_view) { echo "&nbsp;($ip)"; } ?></strong><br>
       		 	<span class="sound_only">댓글</span><strong><a href="#bo_vc"> <i class="fa fa-commenting-o" aria-hidden="true"></i> <?php echo number_format($view['wr_comment']) ?>건</a></strong>
        		<span class="sound_only">조회</span><strong><i class="fa fa-eye" aria-hidden="true"></i> <?php echo number_format($view['wr_hit']) ?>회</strong>
        		<strong class="if_date"><span class="sound_only">작성일</span><i class="fa fa-clock-o" aria-hidden="true"></i> <?php echo date("y-m-d H:i", strtotime($view['wr_datetime'])) ?></strong>
    		</div>
    	</div>

    	<!-- 게시물 상단 버튼 시작 { -->
	    <div id="bo_v_top">
	        <?php ob_start(); ?>

	        <ul class="btn_bo_user bo_v_com">
				<li><a href="<?php echo $list_href ?>" class="btn_b01 btn" title="목록"><i class="fa fa-list" aria-hidden="true"></i><span class="sound_only">목록</span></a></li>
	            <?php if ($reply_href) { ?><li><a href="<?php echo $reply_href ?>" class="btn_b01 btn" title="답변"><i class="fa fa-reply" aria-hidden="true"></i><span class="sound_only">답변</span></a></li><?php } ?>
	            <?php if ($write_href) { ?><li><a href="<?php echo $write_href ?>" class="btn_b01 btn" title="글쓰기"><i class="fa fa-pencil" aria-hidden="true"></i><span class="sound_only">글쓰기</span></a></li><?php } ?>
	        	<?php if($update_href || $delete_href || $copy_href || $move_href || $search_href) { ?>
	        	<li>
	        		<button type="button" class="btn_more_opt is_view_btn btn_b01 btn" title="게시판 리스트 옵션"><i class="fa fa-ellipsis-v" aria-hidden="true"></i><span class="sound_only">게시판 리스트 옵션</span></button>
		        	<ul class="more_opt is_view_btn"> 
			            <?php if ($update_href) { ?><li><a href="<?php echo $update_href ?>">수정<i class="fa fa-pencil-square-o" aria-hidden="true"></i></a></li><?php } ?>
			            <?php if ($delete_href) { ?><li><a href="<?php echo $delete_href ?>" onclick="del(this.href); return false;">삭제<i class="fa fa-trash-o" aria-hidden="true"></i></a></li><?php } ?>
			            <?php if ($copy_href) { ?><li><a href="<?php echo $copy_href ?>" onclick="board_move(this.href); return false;">복사<i class="fa fa-files-o" aria-hidden="true"></i></a></li><?php } ?>
			            <?php if ($move_href) { ?><li><a href="<?php echo $move_href ?>" onclick="board_move(this.href); return false;">이동<i class="fa fa-arrows" aria-hidden="true"></i></a></li><?php } ?>
			            <?php if ($search_href) { ?><li><a href="<?php echo $search_href ?>">검색<i class="fa fa-search" aria-hidden="true"></i></a></li><?php } ?>
			        </ul> 
	        	</li>
	        	<?php } ?>
	        </ul>
	        <script>

            jQuery(function($){
                // 게시판 보기 버튼 옵션
				$(".btn_more_opt.is_view_btn").on("click", function(e) {
                    e.stopPropagation();
				    $(".more_opt.is_view_btn").toggle();
				})
;
                $(document).on("click", function (e) {
                    if(!$(e.target).closest('.is_view_btn').length) {
                        $(".more_opt.is_view_btn").hide();
                    }
                });
            });
            </script>
	        <?php
	        $link_buttons = ob_get_contents();
	        ob_end_flush();
	         ?>
	    </div>
	    <!-- } 게시물 상단 버튼 끝 -->
    </section>

    <section id="bo_v_atc">
        <h2 id="bo_v_atc_title">본문</h2>
        <div id="bo_v_share">
        	<?php include_once(G5_SNS_PATH."/view.sns.skin.php"); ?>
	        <?php if ($scrap_href) { ?><a href="<?php echo $scrap_href;  ?>" target="_blank" class="btn btn_b03" onclick="win_scrap(this.href); return false;"><i class="fa fa-bookmark" aria-hidden="true"></i> 스크랩</a><?php } ?>
			<?php
			//////////////////////////////////////////////////
			//// 2. 신고 게시글 시작 /////////////////////////////
			//////////////////////////////////////////////////
			
			// 잠금 글이면 신고 버튼 숨김 
			if (!$is_locked_post) { 
			?>
				<a href="javascript:void(0);" class="btn btn_b03 report-btn"
					onclick="handleReportClick('<?php echo $bo_table; ?>', '<?php echo $view['wr_id']; ?>')">
					<i class="fa fa-flag"></i> 신고
				</a>
			<?php 
			}
			
			if (!empty($is_admin) && $wr_report_count > 0) {
				$admin_report_url = G5_ADMIN_URL.'/boardreport_list.php?bo_table='.urlencode($bo_table).'&kw='.$view['wr_id'];
				?>
				<a href="<?php echo $admin_report_url; ?>" class="btn btn_b03 report-btn" target="_blank">
					신고내역
					<?php if (!empty($is_admin) && $wr_report_count > 0) { ?>
					<span id="report_count_badge"
						class="report-count-badge"
						data-count="<?php echo $wr_report_count; ?>"
						style="display:<?php echo $wr_report_count > 0 ? 'inline-block':'none'; ?>">
					<?php echo $wr_report_count > 0 ? $wr_report_count : ''; ?>
					</span>
					<?php } ?>
				</a>
			<?php
			}
			//////////////////////////////////////////////////
			//// 2. 신고 게시글 끝 //////////////////////////////
			//////////////////////////////////////////////////
			?>
	    </div>

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

        <!-- 본문 내용 시작 { -->
		<?php
		//////////////////////////////////////////////////
		//// 3. 신고 게시글 시작 /////////////////////////////
		//////////////////////////////////////////////////
		/*
        <div id="bo_v_con"><?php echo get_view_thumbnail($view['content']); ?></div>
		<?php
		*/

		if (!empty($is_admin) && isset($view['wr_report']) && $view['wr_report'] === '잠금') {
			$admin_report_url = G5_ADMIN_URL.'/boardreport_list.php?bo_table='.urlencode($bo_table).'&kw='.$view['wr_id'];
		?>
			<div class="report-admin-lock-banner">
				<span class="report-admin-badge">신고 누적 잠금</span>
			</div>
		<?php 
		}

		if ($should_hide_post) {
		?>
			<div id="bo_v_con">
				<div class="report-admin-lock-banner">
					<span class="report-admin-badge">잠금됨</span>
					신고가 누적되어 본문이 비공개 처리되었습니다. 관리자의 확인 후 복구될 수 있습니다.
				</div>
			</div>
		<?php } else { ?>
			<div id="bo_v_con"><?php echo get_view_thumbnail($view['content']); ?></div>
		<?php 
		}
		//////////////////////////////////////////////////
		//// 3. 신고 게시글 끝 //////////////////////////////
		//////////////////////////////////////////////////
		?>
        <?php //echo $view['rich_content']; // {이미지:0} 과 같은 코드를 사용할 경우 ?>
        <!-- } 본문 내용 끝 -->

        <?php if ($is_signature) { ?><p><?php echo $signature ?></p><?php } ?>


        <!--  추천 비추천 시작 { -->
        <?php if ( $good_href || $nogood_href) { ?>
        <div id="bo_v_act">
            <?php if ($good_href) { ?>
            <span class="bo_v_act_gng">
                <a href="<?php echo $good_href.'&amp;'.$qstr ?>" id="good_button" class="bo_v_good"><i class="fa fa-thumbs-o-up" aria-hidden="true"></i><span class="sound_only">추천</span><strong><?php echo number_format($view['wr_good']) ?></strong></a>
                <b id="bo_v_act_good"></b>
            </span>
            <?php } ?>
            <?php if ($nogood_href) { ?>
            <span class="bo_v_act_gng">
                <a href="<?php echo $nogood_href.'&amp;'.$qstr ?>" id="nogood_button" class="bo_v_nogood"><i class="fa fa-thumbs-o-down" aria-hidden="true"></i><span class="sound_only">비추천</span><strong><?php echo number_format($view['wr_nogood']) ?></strong></a>
                <b id="bo_v_act_nogood"></b>
            </span>
            <?php } ?>
        </div>
        <?php } else {
            if($board['bo_use_good'] || $board['bo_use_nogood']) {
        ?>
        <div id="bo_v_act">
            <?php if($board['bo_use_good']) { ?><span class="bo_v_good"><i class="fa fa-thumbs-o-up" aria-hidden="true"></i><span class="sound_only">추천</span><strong><?php echo number_format($view['wr_good']) ?></strong></span><?php } ?>
            <?php if($board['bo_use_nogood']) { ?><span class="bo_v_nogood"><i class="fa fa-thumbs-o-down" aria-hidden="true"></i><span class="sound_only">비추천</span><strong><?php echo number_format($view['wr_nogood']) ?></strong></span><?php } ?>
        </div>
        <?php
            }
        }
        ?>
        <!-- }  추천 비추천 끝 -->
    </section>

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
    <!-- 첨부파일 시작 { -->
    <section id="bo_v_file">
        <h2>첨부파일</h2>
        <ul>
        <?php
        // 가변 파일
        for ($i=0; $i<count($view['file']); $i++) {
            if (isset($view['file'][$i]['source']) && $view['file'][$i]['source'] && !$view['file'][$i]['view']) {
         ?>
            <li>
               	<i class="fa fa-folder-open" aria-hidden="true"></i>
                <a href="<?php echo $view['file'][$i]['href'];  ?>" class="view_file_download">
                    <strong><?php echo $view['file'][$i]['source'] ?></strong> <?php echo $view['file'][$i]['content'] ?> (<?php echo $view['file'][$i]['size'] ?>)
                </a>
                <br>
                <span class="bo_v_file_cnt"><?php echo $view['file'][$i]['download'] ?>회 다운로드 | DATE : <?php echo $view['file'][$i]['datetime'] ?></span>
            </li>
        <?php
            }
        }
         ?>
        </ul>
    </section>
    <!-- } 첨부파일 끝 -->
    <?php } ?>

    <?php if(isset($view['link']) && array_filter($view['link'])) { ?>
    <!-- 관련링크 시작 { -->
    <section id="bo_v_link">
        <h2>관련링크</h2>
        <ul>
        <?php
        // 링크
        $cnt = 0;
        for ($i=1; $i<=count($view['link']); $i++) {
            if ($view['link'][$i]) {
                $cnt++;
                $link = cut_str($view['link'][$i], 70);
            ?>
            <li>
                <i class="fa fa-link" aria-hidden="true"></i>
                <a href="<?php echo $view['link_href'][$i] ?>" target="_blank">
                    <strong><?php echo $link ?></strong>
                </a>
                <br>
                <span class="bo_v_link_cnt"><?php echo $view['link_hit'][$i] ?>회 연결</span>
            </li>
            <?php
            }
        }
        ?>
        </ul>
    </section>
    <!-- } 관련링크 끝 -->
    <?php } ?>
    
    <?php if ($prev_href || $next_href) { ?>
    <ul class="bo_v_nb">
        <?php if ($prev_href) { ?><li class="btn_prv"><span class="nb_tit"><i class="fa fa-chevron-up" aria-hidden="true"></i> 이전글</span><a href="<?php echo $prev_href ?>"><?php echo $prev_wr_subject;?></a> <span class="nb_date"><?php echo str_replace('-', '.', substr($prev_wr_date, '2', '8')); ?></span></li><?php } ?>
        <?php if ($next_href) { ?><li class="btn_next"><span class="nb_tit"><i class="fa fa-chevron-down" aria-hidden="true"></i> 다음글</span><a href="<?php echo $next_href ?>"><?php echo $next_wr_subject;?></a>  <span class="nb_date"><?php echo str_replace('-', '.', substr($next_wr_date, '2', '8')); ?></span></li><?php } ?>
    </ul>
    <?php } ?>

    <?php
    // 코멘트 입출력
    include_once(G5_BBS_PATH.'/view_comment.php');
	?>
</article>
<!-- } 게시판 읽기 끝 -->

<?php 
//////////////////////////////////////////////////
//// 4. 신고 게시글 시작 /////////////////////////////
//////////////////////////////////////////////////
?>
<!-- 신고 모달 -->
<div id="report_modal" class="report-modal" style="display:none;">
    <div class="report-modal-bg" onclick="closeReportModal()"></div>
    <div class="report-modal-box">
        <div id="report_modal_inner" class="report-modal-inner">로딩 중...</div>
    </div>
</div>

<script>
const G5_BBS_URL = "<?php echo G5_BBS_URL; ?>";

// g5_is_member 폴백(없으면 PHP 값으로 주입)
if (typeof window.g5_is_member === 'undefined') {
  window.g5_is_member = <?php echo $is_member ? 'true' : 'false'; ?>;
}

/** 신고 버튼 클릭 핸들러: 회원만 모달 오픈 */
function handleReportClick(bo_table, wr_id, comment_id = 0) {
  if (window.g5_is_member) {
    openReportForm(bo_table, wr_id, comment_id);
    return;
  }
  const back = encodeURIComponent(location.href);
  if (confirm('회원만 신고할 수 있습니다.\n로그인 하시겠습니까?')) {
    // GNUBOARD 기본 로그인 페이지로 이동 (로그인 후 원래 페이지 복귀)
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

      // 포커스 이동(접근성)
      const firstInput = inner.querySelector('input, textarea, select, button');
      if (firstInput) firstInput.focus();

      // 로드 직후 "기타" 선택 상태면 textarea 즉시 반영
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

// 모달 내부 change 이벤트 위임 (동적 주입 대응)
document.addEventListener('change', function(e) {
  if (!document.getElementById('report_modal')) return;

  // 신고 사유 라디오 선택 시
  if (e.target.matches('#report_modal input[name="reason"]')) {
    const selected = e.target.value;
    const etcBox   = document.querySelector('#report_modal .reason-etc-box');
    if (etcBox) etcBox.style.display = (selected === '기타' ? 'block' : 'none');
  }
});

// 배지 갱신 & 잠금 반영 헬퍼
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

// 모달 폼 AJAX 제출 (이 부분만 교체/보강)
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
      // 댓글 신고 갱신
      updateCommentBadge(commentId, count);
      if (locked) hideCommentContent(commentId);
    } else {
      // 게시글 신고 갱신
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

// 유효성 검사 (폼 onsubmit 훅에서 호출)
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
<?php 
//////////////////////////////////////////////////
//// 4. 신고 게시글 끝 //////////////////////////////
//////////////////////////////////////////////////
?>

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

    // 추천, 비추천
    $("#good_button, #nogood_button").click(function() {
        var $tx;
        if(this.id == "good_button")
            $tx = $("#bo_v_act_good");
        else
            $tx = $("#bo_v_act_nogood");

        excute_good(this.href, $(this), $tx);
        return false;
    });

    // 이미지 리사이즈
    $("#bo_v_atc").viewimageresize();
});

function excute_good(href, $el, $tx)
{
    $.post(
        href,
        { js: "on" },
        function(data) {
            if(data.error) {
                alert(data.error);
                return false;
            }

            if(data.count) {
                $el.find("strong").text(number_format(String(data.count)));
                if($tx.attr("id").search("nogood") > -1) {
                    $tx.text("이 글을 비추천하셨습니다.");
                    $tx.fadeIn(200).delay(2500).fadeOut(200);
                } else {
                    $tx.text("이 글을 추천하셨습니다.");
                    $tx.fadeIn(200).delay(2500).fadeOut(200);
                }
            }
        }, "json"
    );
}
</script>
<!-- } 게시글 읽기 끝 -->