<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
include_once(G5_LIB_PATH.'/thumbnail.lib.php');

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css">', 0);

$bo_page_rows = isset($board['bo_page_rows']) ? $board['bo_page_rows'] : '';
$bo_mobile_page_rows = isset($board['bo_mobile_page_rows']) ? $board['bo_mobile_page_rows'] : '';
$bo_gallery_cols = isset($board['bo_gallery_cols']) ? $board['bo_gallery_cols'] : '';
$bo_gallery_height = isset($board['bo_gallery_height']) ? $board['bo_gallery_height'] : '';
$bo_mobile_gallery_height = isset($board['bo_mobile_gallery_height'])  ? $board['bo_mobile_gallery_height'] : '';

// 댓글 가져오는 함수
function get_first_comment($bo_table, $wr_id) {
    global $g5;
    
    $sql = "SELECT wr_id, wr_content, wr_name, mb_id, wr_datetime 
            FROM {$g5['write_prefix']}{$bo_table} 
            WHERE wr_parent = '{$wr_id}' 
            AND wr_is_comment = 1 
            ORDER BY wr_id ASC 
            LIMIT 1";
    
    $result = sql_fetch($sql);
    
    if($result) {
        $result['wr_content'] = preg_replace("/<(.*?)\>/","",$result['wr_content']);
        $result['wr_content'] = preg_replace("/&nbsp;/","",$result['wr_content']);
        $result['wr_content'] = get_text($result['wr_content']);
        return $result;
    }
    
    return false;
}

?>

<link rel="stylesheet" href="<?php echo $board_skin_url ?>/style.php?bo_gallery_height=<?php echo $bo_gallery_height; ?>&bo_mobile_gallery_height=<?php echo $bo_mobile_gallery_height; ?>">

<style>
/* 오늘의집 스타일 추가 */
.swiper-container-<?php echo $bo_table ?> {
    overflow: visible !important;
}

.swiper-wrapper-<?php echo $bo_table ?> {
    display: grid !important;
    grid-template-columns: repeat(<?php echo isset($bo_gallery_cols) ? $bo_gallery_cols : '3'; ?>, 1fr) !important;
    gap: 20px !important;
    transform: none !important;
    width: 100% !important;
}

.swiper-slide-<?php echo $bo_table ?> {
    width: 100% !important;
    margin: 0 !important;
    height: auto !important;
}

/* 카드 전체 링크 스타일 */
.gallery-card-link {
    display: block;
    text-decoration: none;
    color: inherit;
}

.gallery-card-link:hover {
    text-decoration: none;
}

/* 카드 스타일 */
.gallery_v_mtop {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid #eaedef;
    position: relative;
}

/* 사용자 정보 영역 추가 */
.insta-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
}

.insta-user-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.insta-user-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, #405de6, #5851db, #833ab4, #c13584, #e1306c, #fd1d1d);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
    overflow: hidden;
}

.insta-user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.insta-user-name {
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

/* 이미지 영역 수정 */
.gallery-item-img {
    position: relative;
    width: 100%;
    height: 280px;
    overflow: hidden !important;
}

.gallery-item-img a {
    display: block;
    width: 100%;
    height: 100%;
}

/* PC에서만 이미지 hover 효과 적용 */
@media (min-width: 769px) {
    .gallery-item-img img {
        transition: transform 0.3s ease;
    }
    
    .gallery_v_mtop:hover .gallery-item-img img {
        transform: scale(1.02);
    }
}

/* 액션 버튼 영역 */
.insta-actions {
    padding: 10px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 10;
    border-top: 1px solid #EAEDEF;
}

.insta-actions-left {
    display: flex;
    gap: 16px;
}

.insta-action-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    color: #262626;
}

.insta-action-btn:hover {
    opacity: 0.6;
}

.insta-action-btn svg {
    width: 24px;
    height: 24px;
    stroke: #262626;
    stroke-width: 2;
    fill: none;
}

.insta-action-btn.liked svg {
    fill: #ed4956;
    stroke: #ed4956;
}

.insta-action-btn.bookmarked svg {
    fill: #262626;
    stroke: #262626;
}

/* 제목 영역 수정 */
.gallery-item-tit {
    padding: 0 16px !important;
    margin: 8px 0 !important;
}

.gallery-item-tit a {
    font-size: 15px !important;
    color: #292929 !important;
}

/* 내용 영역 - 3줄 제한 */
.gallery-item-con {
    padding: 0 16px 8px !important;
    margin: 0 !important;
    font-size: 14px;
    line-height: 1.4;
    min-height: 58.8px;
    max-height: 58.8px;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    cursor: pointer;
}

/* 댓글 영역 추가 */
.insta-comments {
    padding: 15px 10px;
    min-height: 40px;
    cursor: pointer;
}

.insta-comments:hover {
    background-color: #f8f9fa;
}

.insta-comment {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.insta-comment-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, #35c5f0, #2bb8e8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    flex-shrink: 0;
    overflow: hidden;
}

.insta-comment-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.insta-comment-content {
    font-size: 14px;
    color: #262626;
    line-height: 1.4;
}

.insta-comment-content .username {
    font-weight: 600;
    margin-right: 8px;
}

.insta-comment-empty {
    color: #c0c0c0;
    font-size: 14px;
    padding: 8px 0;
}

/* 정보 영역 수정 */
.gallery-item-info {
    padding: 8px 16px !important;
    font-size: 12px !important;
    color: #828c94 !important;
    margin: 0 !important;
}

.gallery-item-info-sub {
    padding: 12px 16px !important;
    border-top: 1px solid #f7f8fa !important;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* 플로팅 글쓰기 버튼 */
.floating-write-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    background: #35c5f0;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(53, 197, 240, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
    z-index: 999;
    text-decoration: none;
    font-size: 24px;
}

.floating-write-btn:hover {
    background: #2bb8e8;
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(53, 197, 240, 0.5);
}

/* 추천 메시지 스타일 */
.good-message {
    position: absolute;
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 8px 12px;
    border-radius: 4px;
    font-size: 12px;
    z-index: 1000;
    white-space: nowrap;
    pointer-events: none;
}

/* 무한 스크롤 관련 스타일 */
.infinite-loading {
    padding: 30px;
    text-align: center;
}

.infinite-loading::after {
    content: '';
    display: inline-block;
    width: 30px;
    height: 30px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #35c5f0;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.infinite-no-more {
    padding: 30px;
    text-align: center;
    color: #999;
    font-size: 14px;
}

/* 체크박스 위치 조정 */
.gall_chk_is {
    position: absolute;
    top: 10px;
    right: 10px;
    z-index: 11;
}
@media (max-width: 768px) {
    .swiper-wrapper-<?php echo $bo_table ?> {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 16px !important;
    }
    
    .floating-write-btn {
        bottom: 20px;
        right: 20px;
        width: 56px;
        height: 56px;
    }
}

@media (max-width: 480px) {
    .swiper-wrapper-<?php echo $bo_table ?> {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
    }
    
    .gallery-item-img {
        height: 240px;
    }
    
    .floating-write-btn {
        bottom: 15px;
        right: 15px;
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
}
</style>

<div class="rb_bbs_wrap" id="scroll_container" style="width:<?php echo $width; ?>">
  
   
    <form name="fboardlist"  id="fboardlist" action="<?php echo G5_BBS_URL; ?>/board_list_update.php" onsubmit="return fboardlist_submit(this);" method="post">
    <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
    <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
    <input type="hidden" name="stx" value="<?php echo $stx ?>">
    <input type="hidden" name="spt" value="<?php echo $spt ?>">
    <input type="hidden" name="sst" value="<?php echo $sst ?>">
    <input type="hidden" name="sod" value="<?php echo $sod ?>">
    <input type="hidden" name="page" value="<?php echo $page ?>">
    <input type="hidden" name="sw" value="">
    
    <div class="btns_gr_wrap">
      
       <!-- $rb_core['sub_width'] 는 반드시 포함해주세요 (환경설정 > 서브가로폭에 따른 버튼의 위치 설정) -->
       <div class="sub" style="width:<?php echo $rb_core['sub_width'] ?>px;">
            
            <?php if(!$wr_id) { //목록보기를 했을 경우 노출되는 부분 방지?>
            
            <div class="btns_gr">
               <?php if ($admin_href) { ?>
               <button type="button" class="fl_btns" onclick="window.open('<?php echo $admin_href ?>');">
               <img src="<?php echo $board_skin_url ?>/img/ico_set.svg">
               <span class="tooltips">관리</span>
               </button>
               <?php } ?>
              
               <button type="button" class="fl_btns btn_bo_sch">
               <img src="<?php echo $board_skin_url ?>/img/ico_ser.svg">
               <span class="tooltips">검색</span>
               </button>

               
               <?php if ($rss_href) { ?>
               <button type="button" class="fl_btns" onclick="window.open('<?php echo $rss_href ?>');">
               <img src="<?php echo $board_skin_url ?>/img/ico_rss.svg">
               <span class="tooltips">RSS</span>
               </button>
               <?php } ?>
               
               
               <?php if ($write_href) { ?>
               <button type="button" class="fl_btns main_color_bg" onclick="location.href='<?php echo $write_href ?>';">
               <img src="<?php echo $board_skin_url ?>/img/ico_write.svg">
               <span class="tooltips">글 등록</span>
               </button>
               <?php } ?>
               
            </div>
            <?php } ?>
            
            <div class="cb"></div>
        </div>
    </div>
    
    <!-- 갯수, 전체선택 { -->
    <ul class="rb_bbs_top">
      
        <?php if($board['bo_read_point'] || $board['bo_write_point'] || $board['bo_comment_point'] || $board['bo_download_point']) { ?>
        <li class="point_info_btns_wrap">
            <button type="button" class="point_info_btns" id="point_info_opens_btn">
            <i><svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 0C15.523 0 20 4.477 20 10C20 15.523 15.523 20 10 20C4.477 20 0 15.523 0 10C0 4.477 4.477 0 10 0ZM11.5 5H9C8.46957 5 7.96086 5.21071 7.58579 5.58579C7.21071 5.96086 7 6.46957 7 7V14C7 14.2652 7.10536 14.5196 7.29289 14.7071C7.48043 14.8946 7.73478 15 8 15C8.26522 15 8.51957 14.8946 8.70711 14.7071C8.89464 14.5196 9 14.2652 9 14V12H11.5C12.4283 12 13.3185 11.6313 13.9749 10.9749C14.6313 10.3185 15 9.42826 15 8.5C15 7.57174 14.6313 6.6815 13.9749 6.02513C13.3185 5.36875 12.4283 5 11.5 5ZM11.5 7C11.8978 7 12.2794 7.15804 12.5607 7.43934C12.842 7.72064 13 8.10218 13 8.5C13 8.89782 12.842 9.27936 12.5607 9.56066C12.2794 9.84196 11.8978 10 11.5 10H9V7H11.5Z" fill="#09244B"/></svg></i>
            <span class="pc">포인트정책</span></button>
            
            <div class="point_info_opens">
                <h6><?php echo $board['bo_subject'] ?> 포인트 정책</h6>
                <ul>
                    <?php if($board['bo_read_point']) { ?>
                    <dl>
                        <dd>글읽기</dd>
                        <dd class="font-B"><?php echo number_format($board['bo_read_point']); ?>P</dd>
                    </dl>
                    <?php } ?>
                    <?php if($board['bo_write_point']) { ?>
                    <dl>
                        <dd>글쓰기</dd>
                        <dd class="font-B"><?php echo number_format($board['bo_write_point']); ?>P</dd>
                    </dl>
                    <?php } ?>
                    <?php if($board['bo_comment_point']) { ?>
                    <dl>
                        <dd>댓글</dd>
                        <dd class="font-B"><?php echo number_format($board['bo_comment_point']); ?>P</dd>
                    </dl>
                    <?php } ?>
                    <?php if($board['bo_download_point']) { ?>
                    <dl>
                        <dd>다운로드</dd>
                        <dd class="font-B"><?php echo number_format($board['bo_download_point']); ?>P</dd>
                    </dl>
                    <?php } ?>
                </ul>
            </div>
            
            <script>
                $(document).ready(function() {
                    $(document).click(function(event) {
                        if (!$(event.target).closest('#point_info_opens_btn, .point_info_opens').length) {
                            if ($('.point_info_opens').is(':visible')) {
                                $('.point_info_opens').hide();
                                $('#point_info_opens_btn').removeClass('act');
                            }
                        }
                    });

                    $('#point_info_opens_btn').click(function(event) {
                        event.stopPropagation(); 
                        $('.point_info_opens').toggle();
                        $(this).toggleClass('act');
                    });
                });
            </script>
            
            
        </li>
        <?php } ?>
       
        <?php if ($is_checkbox) { ?>
        <li>
            <input type="checkbox" id="chkall" onclick="if (this.checked) all_checked(true); else all_checked(false);">
            <label for="chkall"></label>
        </li>
        <?php } ?>
        
        <li class="cnts">
            전체 <?php echo number_format($total_count) ?>건 / <?php echo $page ?> 페이지
        </li>
        
        <div class="cb"></div>
    </ul>
    <!-- } -->
    
    <!-- 카테고리 { -->
    <?php if ($is_category) { ?>
    <nav id="bo_cate" class="swiper-container swiper-container-category">
        <ul id="bo_cate_ul" class="swiper-wrapper swiper-wrapper-category">
            <?php echo $category_option ?>
        </ul>
    </nav>
    <script>
        $(document).ready(function() {
            $("#bo_cate_ul li").addClass("swiper-slide swiper-slide-category");

            var activeElement = document.querySelector('#bo_cate_on'); // ID로 바로 찾기
            var initialSlideIndex = 0;

            if (activeElement) {
                var parentLi = activeElement.closest('li.swiper-slide-category');
                var allSlides = document.querySelectorAll('li.swiper-slide-category');
                initialSlideIndex = Array.prototype.indexOf.call(allSlides, parentLi);
            }

            //console.log('초기 인덱스:', initialSlideIndex);

            var swiper = new Swiper('.swiper-container-category', {
                slidesPerView: 'auto',
                spaceBetween: 0,
                observer: true,
                observeParents: true,
                touchRatio: 1,
                initialSlide: initialSlideIndex
            });
        });
    </script>
    <?php } ?>
    <!-- } -->
    <div class="swiper-container swiper-container-<?php echo $bo_table ?>">
    <ul class="gallery_top_mt rb_bbs_list swiper-wrapper swiper-wrapper-<?php echo $bo_table ?>">
       
        <?php 
        for ($i=0; $i<count($list); $i++) { 
            
            // 기존 썸네일 함수로 먼저 시도
            $thumb = get_list_thumbnail($board['bo_table'], $list[$i]['wr_id'], $board['bo_gallery_width'], $board['bo_gallery_height'], false, true);
            
            // 썸네일이 없으면 본문에서 이미지 추출
            if(!$thumb['src']) {
                // 본문에서 첫 번째 이미지 찾기
                preg_match('/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $list[$i]['wr_content'], $matches);
                if (!empty($matches[1])) {
                    $thumb['src'] = $matches[1];
                    $thumb['alt'] = $list[$i]['subject'];
                }
            }
            
            // 이미지 표시 로직
            if($thumb['src']) {
                if (strstr($list[$i]['wr_option'], 'secret')) {
                    $img_content = '<img src="'.G5_THEME_URL.'/rb.img/sec_image.png" alt="'.$thumb['alt'].'" style="width:100%; height:100%; object-fit:cover;">';
                } else {
                    $img_content = '<img src="'.$thumb['src'].'" alt="'.$thumb['alt'].'" style="width:100%; height:100%; object-fit:cover;" onerror="this.src=\''.G5_THEME_URL.'/rb.img/no_image.png\'">';
                }
            } else { 
                $img_content = '<img src="'.G5_THEME_URL.'/rb.img/no_image.png" alt="이미지가 없습니다." style="width:100%; height:100%; object-fit:cover;">';
            }
            

            $wr_href = $list[$i]['href'];
            $sec_txt = '<span style="opacity:0.6">작성자 및 관리자 외 열람할 수 없습니다.<br>비밀글 기능으로 보호된 글입니다.</span>';
            
            $wr_content = preg_replace("/<(.*?)\>/","",$list[$i]['wr_content']);
            $wr_content = preg_replace("/&nbsp;/","",$wr_content);
            $wr_content = get_text($wr_content);
            
            // 첫 번째 댓글 가져오기
            $first_comment = get_first_comment($bo_table, $list[$i]['wr_id']);
            
        ?>
        
        <div class="gallery_v_mtop swiper-slide swiper-slide-<?php echo $bo_table ?>">
            <a href="<?php echo $wr_href ?>" class="gallery-card-link">
                <!-- 인스타그램 스타일 헤더 추가 -->
                <div class="insta-card-header">
                    <div class="insta-user-info">
                        <div class="insta-user-avatar">
                            <?php echo get_member_profile_img($list[$i]['mb_id']); ?>
                        </div>
                        <span class="insta-user-name"><?php echo $list[$i]['name'] ?></span>
                    </div>
                </div>
                
                <ul class="gallery-item-img" style="overflow:hidden;">
                    <?php echo run_replace('thumb_image_tag', $img_content, $thumb); ?>
                    <div class="gallery-item-ico">
                        <?php if ($list[$i]['icon_new']) echo "<span class=\"bbs_list_label label3\">새글</span>"; ?>
                        <?php if ($list[$i]['icon_hot']) echo "<span class=\"bbs_list_label label1\">인기</span>"; ?>
                    </div>
                </ul>
                
                <ul class="gallery-item-info">
                <?php echo passing_time($list[$i]['wr_datetime']) ?>　
                <?php if($list[$i]['ca_name']) { ?>
                        <?php echo $list[$i]['ca_name'] ?>　
                <?php } ?>
                </ul>
                
                <ul class="gallery-item-tit cut2"><span class="font-B"><?php echo $list[$i]['subject'] ?></span></ul>
                <ul class="gallery-item-con cut2">
                <?php if (strstr($list[$i]['wr_option'], 'secret')) { ?>
                    <?php echo $sec_txt ?>
                <?php } else { ?>
                    <?php echo $wr_content ?>
                <?php } ?>
                </ul>
                
                <!-- 첫 번째 댓글 표시 영역 - 항상 표시 -->
                <div class="insta-comments">
                    <?php if($first_comment && !strstr($list[$i]['wr_option'], 'secret')) { ?>
                    <div class="insta-comment">
                        <div class="insta-comment-avatar">
                            <?php echo get_member_profile_img($first_comment['mb_id']); ?>
                        </div>
                        <div class="insta-comment-content">
                            <span class="username"><?php echo $first_comment['wr_name'] ?></span>
                            <?php 
                            $comment_text = $first_comment['wr_content'];
                            echo mb_strlen($comment_text) > 50 ? mb_substr($comment_text, 0, 50) . '...' : $comment_text;
                            ?>
                        </div>
                    </div>
                    <?php } else { ?>
                    <div class="insta-comment-empty">
                        <span>첫 댓글을 남겨보세요</span>
                    </div>
                    <?php } ?>
                </div>
                
                <ul class="gallery-item-info gallery-item-info-sub">
                    <span class="prof_tiny_name font-B"><?php echo $list[$i]['name'] ?>　</span>
                    <span>
                        조회 <?php echo number_format($list[$i]['wr_hit']); ?>　
                        <?php if($list[$i]['wr_comment'] > 0) { ?>
                        댓글 <?php echo number_format($list[$i]['wr_comment']); ?>
                        <?php } ?>
                    </span>
                </ul>
            </a>
            
            <!-- 인스타그램 스타일 액션 버튼 추가 -->
            <div class="insta-actions">
                <div class="insta-actions-left">
                    <button class="insta-action-btn like-btn" data-wr-id="<?php echo $list[$i]['wr_id'] ?>">
                        <svg viewBox="0 0 24 24">
                            <path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/>
                        </svg>
                        <span class="like-count"><?php echo number_format($list[$i]['wr_good']); ?></span>
                    </button>
                    
                    <button class="insta-action-btn" onclick="location.href='<?php echo $wr_href ?>';">
                        <svg viewBox="0 0 24 24">
                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
                        </svg>
                        <span><?php echo number_format($list[$i]['wr_comment']); ?></span>
                    </button>
                </div>
                
                <button class="insta-action-btn bookmark-btn" data-wr-id="<?php echo $list[$i]['wr_id'] ?>">
                    <svg viewBox="0 0 24 24">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"/>
                    </svg>
                </button>
            </div>
            
            <?php if ($is_checkbox) { ?>
            <div class="gall_chk_is">
                <input type="checkbox" name="chk_wr_id[]" value="<?php echo $list[$i]['wr_id'] ?>" id="chk_wr_id_<?php echo $i ?>" class="">
                <label for="chk_wr_id_<?php echo $i ?>"></label>
            </div>
            <?php } ?>
        </div>
        
        <?php } ?>
        
        
        
    </ul>
    </div>
    
    
    <script>
        // Swiper 비활성화 (그리드 레이아웃으로 대체했으므로)
        // 기존 Swiper 스크립트는 유지하되 실행되지 않도록 함
    </script>
    
    <?php if (count($list) == 0) { echo "<div class=\"no_data\" style=\"text-align:center; padding-top:0px !important;\">데이터가 없습니다.</div>"; } ?>
    
    <ul class="btm_btns">
        
        <dd class="btm_btns_right">

            <?php if ($rss_href) { ?>
            <button type="button" name="btn_submit" class="fl_btns rss_pc" onclick="window.open('<?php echo $rss_href ?>');">
                RSS
            </button>
            <?php } ?>

            <?php if ($write_href) { ?>
            <button type="button" name="btn_submit" class="fl_btns main_color_bg" onclick="location.href='<?php echo $write_href ?>';">
                <img src="<?php echo $board_skin_url ?>/img/ico_write.svg">
                <span class="font-R">글 등록</span>
            </button>
            <?php } ?>

        </dd>
        
        <dd class="btm_btns_left">
        <?php if ($is_admin == 'super' || $is_auth) { ?>
            <?php if ($is_checkbox) { ?>
                <button type="submit" name="btn_submit" class="fl_btns" value="선택삭제" onclick="document.pressed=this.value">
                <span class="font-B">선택삭제</span>
                </button>

                <button type="submit" name="btn_submit" class="fl_btns" value="선택복사" onclick="document.pressed=this.value">
                <span class="font-B">선택복사</span>
                </button>

                <button type="submit" name="btn_submit" class="fl_btns" value="선택이동" onclick="document.pressed=this.value">
                <span class="font-B">선택이동</span>
                </button>
            <?php } ?>
        <?php } ?>
        
        <button type="button" name="btn_submit" class="fl_btns btn_bo_sch"><span class="font-B">검색</span></button>     
        </dd>
        <dd class="cb"></dd>
    </ul>
    
    
    <!-- 페이지 -->
    <?php echo $write_pages; ?>
    <!-- 페이지 -->
    
    
    
    
    </form>
    
</div>

<!-- 플로팅 글쓰기 버튼 -->
<?php if ($write_href) { ?>
<a href="<?php echo $write_href ?>" class="floating-write-btn" title="글쓰기">
    <img src="<?php echo $board_skin_url ?>/img/ico_write.svg" style="width: 24px; height: 24px; filter: brightness(0) invert(1);">
</a>
<?php } ?>

    
    
    
    <!--</form>-->

    <!-- 게시판 검색 시작 { -->
			    <div class="bo_sch_wrap">
				    <fieldset class="bo_sch">
				    	<h3>검색</h3>
				        <legend>게시물 검색</legend>
				        <form name="fsearch" method="get">
                        <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
                        <input type="hidden" name="sca" value="<?php echo $sca ?>">
                        <input type="hidden" name="sop" value="and">
                        <label for="sfl" class="sound_only">검색대상</label>
                        
                        <select name="sfl" id="sfl" class="select">
                            <?php echo get_board_sfl_select_options($sfl); ?>
                        </select>
                        
				        <label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
				        <div class="sch_bar">
				       		<input type="text" name="stx" value="<?php echo stripslashes($stx); ?>" id="stx" required class="input" maxlength="20" placeholder="검색어를 입력해주세요">
							<button type="submit" value="검색" class="sch_btn" title="검색"><img src="<?php echo $board_skin_url ?>/img/ico_ser.svg"></button>
				        </div>
				        <button type="button" class="bo_sch_cls"><img src="<?php echo $board_skin_url ?>/img/icon_close.svg"></button>
				        </form>
				    </fieldset>
			    	<div class="bo_sch_bg"></div>
			    </div>
			    <script>
					// 게시판 검색
					$(".btn_bo_sch").on("click", function() {
					    $(".bo_sch_wrap").toggle();
					})
					$('.bo_sch_bg, .bo_sch_cls').click(function(){
					    $('.bo_sch_wrap').hide();
					});
				</script>
			    <!-- } 게시판 검색 끝 -->
			    
   

<?php if($is_checkbox) { ?>
<noscript>
<p>자바스크립트를 사용하지 않는 경우<br>별도의 확인 절차 없이 바로 선택삭제 처리하므로 주의하시기 바랍니다.</p>
</noscript>
<?php } ?>

<?php if ($is_checkbox) { ?>
<script>
function all_checked(sw) {
    var f = document.fboardlist;

    for (var i=0; i<f.length; i++) {
        if (f.elements[i].name == "chk_wr_id[]")
            f.elements[i].checked = sw;
    }
}

function fboardlist_submit(f) {
    var chk_count = 0;

    for (var i=0; i<f.length; i++) {
        if (f.elements[i].name == "chk_wr_id[]" && f.elements[i].checked)
            chk_count++;
    }

    if (!chk_count) {
        alert(document.pressed + "할 게시물을 하나 이상 선택하세요.");
        return false;
    }

    if(document.pressed == "선택복사") {
        select_copy("copy");
        return;
    }

    if(document.pressed == "선택이동") {
        select_copy("move");
        return;
    }

    if(document.pressed == "선택삭제") {
        if (!confirm("선택한 게시물을 정말 삭제하시겠습니까?\n\n한번 삭제한 자료는 복구할 수 없습니다\n\n답변글이 있는 게시글을 선택하신 경우\n답변글도 선택하셔야 게시글이 삭제됩니다."))
            return false;

        f.removeAttribute("target");
        f.action = g5_bbs_url+"/board_list_update.php";
    }

    return true;
}

// 선택한 게시물 복사 및 이동
function select_copy(sw) {
    var f = document.fboardlist;

    if (sw == 'copy')
        str = "복사";
    else
        str = "이동";

    var sub_win = window.open("", "move", "left=50, top=50, width=500, height=550, scrollbars=1");

    f.sw.value = sw;
    f.target = "move";
    f.action = g5_bbs_url+"/move.php";
    f.submit();
}

// 게시판 리스트 관리자 옵션
jQuery(function($){
    $(".btn_more_opt.is_list_btn").on("click", function(e) {
        e.stopPropagation();
        $(".more_opt.is_list_btn").toggle();
    });
    $(document).on("click", function (e) {
        if(!$(e.target).closest('.is_list_btn').length) {
            $(".more_opt.is_list_btn").hide();
        }
    });
});
</script>
<?php } ?>

<script>
// 좋아요 버튼 이벤트 - 그누보드 추천 시스템 연동
$(document).on('click', '.like-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    var $this = $(this);
    var wrId = $this.data('wr-id');
    var $countSpan = $this.find('.like-count');
    
    <?php if (!$is_member) { ?>
        alert('로그인 후 이용하실 수 있습니다.');
        return false;
    <?php } else { ?>
        // 추천 URL 생성
        var good_href = g5_bbs_url + '/good.php?bo_table=<?php echo $bo_table; ?>&wr_id=' + wrId + '&good=good';
        
        $.post(
            good_href,
            { js: "on" },
            function(data) {
                if(data.error) {
                    alert(data.error);
                    return false;
                }

                if(data.count) {
                    $countSpan.text(number_format(String(data.count)));
                    
                    if(data.status == 'cancel') {
                        // 추천 취소
                        $this.removeClass('liked');
                        showMessage($this, '추천을 취소하셨습니다.');
                    } else {
                        // 추천 추가
                        $this.addClass('liked');
                        showMessage($this, '이 글을 추천하셨습니다.');
                    }
                }
            }, "json"
        ).fail(function() {
            alert('추천 처리 중 오류가 발생했습니다.');
        });
    <?php } ?>
    
    return false;
});

// 액션 버튼 클릭 시 링크 이벤트 차단
$(document).on('click', '.insta-actions button', function(e) {
    e.preventDefault();
    e.stopPropagation();
});

// 메시지 표시 함수
function showMessage($element, message) {
    var $msg = $('<div class="good-message">' + message + '</div>');
    
    var offset = $element.offset();
    $msg.css({
        position: 'absolute',
        top: offset.top - 35,
        left: offset.left - 20
    });
    
    $('body').append($msg);
    $msg.fadeIn(200).delay(1500).fadeOut(200, function() {
        $(this).remove();
    });
}

// 북마크(스크랩) 버튼 이벤트
$(document).on('click', '.bookmark-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    var $this = $(this);
    var wrId = $this.data('wr-id');
    
    <?php if (!$is_member) { ?>
        alert('로그인 후 이용하실 수 있습니다.');
        return false;
    <?php } else { ?>
        // 스크랩 처리 (AJAX로 처리)
        $.post(
            g5_bbs_url + '/scrap_popin_update.php',
            {
                bo_table: '<?php echo $bo_table; ?>',
                wr_id: wrId,
                wr_content: '',
                wr_secret: 'secret'
            },
            function(response) {
                if(response.indexOf('이미 스크랩하신 글') > -1) {
                    // 스크랩 삭제
                    if(confirm('이미 스크랩한 게시물입니다. 스크랩을 취소하시겠습니까?')) {
                        $.post(
                            g5_bbs_url + '/scrap_delete.php',
                            {
                                bo_table: '<?php echo $bo_table; ?>',
                                wr_id: wrId
                            },
                            function() {
                                $this.removeClass('bookmarked');
                                alert('스크랩이 취소되었습니다.');
                            }
                        );
                    }
                } else if(response.indexOf('스크랩하였습니다') > -1) {
                    $this.addClass('bookmarked');
                    alert('스크랩하였습니다.');
                } else {
                    alert(response);
                }
            }
        ).fail(function() {
            alert('스크랩 처리 중 오류가 발생했습니다.');
        });
    <?php } ?>
    
    return false;
});

// 숫자 포맷 함수
function number_format(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// 페이지 로드 시 이미 추천한 게시물 표시
$(document).ready(function() {
    <?php if ($is_member) { ?>
    // 추천 여부 확인을 위한 AJAX 요청
    var wrIds = [];
    $('.like-btn').each(function() {
        wrIds.push($(this).data('wr-id'));
    });
    
    if(wrIds.length > 0) {
        // 각 게시물에 대해 개별적으로 확인
        wrIds.forEach(function(wr_id) {
            $.get(g5_bbs_url + '/good.php?bo_table=<?php echo $bo_table; ?>&wr_id=' + wr_id + '&good=good&check=1', function(response) {
                if(response.already) {
                    $('.like-btn[data-wr-id="' + wr_id + '"]').addClass('liked');
                }
            }, 'json').fail(function() {
                // 실패해도 무시
            });
        });
    }
    <?php } ?>
});

// 무한 스크롤
$(document).ready(function() {
    var page = <?php echo $page; ?>;
    var is_loading = false;
    var no_more_data = false;
    
    // 로딩 표시
    var loadingHtml = '<div class="infinite-loading" style="display:none; text-align:center; padding:40px 0;"></div>';
    $('.swiper-container-<?php echo $bo_table ?>').after(loadingHtml);
    
    // 페이지네이션 숨기기
    $('.pg_wrap').hide();
    
    function loadMorePosts() {
        if (is_loading || no_more_data) return;
        
        is_loading = true;
        page++;
        
        $('.infinite-loading').show();
        
        // 다음 페이지 URL
        var nextUrl = '<?php echo get_pretty_url($bo_table); ?>&page=' + page;
        if ('<?php echo $sca; ?>') nextUrl += '&sca=<?php echo urlencode($sca); ?>';
        if ('<?php echo $stx; ?>') nextUrl += '&stx=<?php echo urlencode($stx); ?>&sfl=<?php echo urlencode($sfl); ?>';
        
        $.get(nextUrl, function(data) {
            var $data = $(data);
            var $newItems = $data.find('.swiper-slide-<?php echo $bo_table ?>');
            
            if ($newItems.length > 0) {
                $('.swiper-wrapper-<?php echo $bo_table ?>').append($newItems);
                
                // 중복 ID 방지
                var existingCount = $('.swiper-slide-<?php echo $bo_table ?>').length;
                $newItems.each(function(index) {
                    $(this).find('[id]').each(function() {
                        var oldId = $(this).attr('id');
                        var newId = oldId + '_p' + page + '_' + index;
                        $(this).attr('id', newId);
                        
                        // label의 for 속성도 변경
                        $(this).find('[for="' + oldId + '"]').attr('for', newId);
                    });
                });
                
                // 새로 추가된 게시물의 추천 여부 확인
                <?php if ($is_member) { ?>
                $newItems.find('.like-btn').each(function() {
                    var wr_id = $(this).data('wr-id');
                    var $btn = $(this);
                    $.get(g5_bbs_url + '/good.php?bo_table=<?php echo $bo_table; ?>&wr_id=' + wr_id + '&good=good&check=1', function(response) {
                        if(response.already) {
                            $btn.addClass('liked');
                        }
                    }, 'json').fail(function() {
                        // 실패해도 무시
                    });
                });
                <?php } ?>
            } else {
                no_more_data = true;
                $('.infinite-loading').after('<div class="infinite-no-more" style="text-align:center; padding:30px; color:#999;">더 이상 게시물이 없습니다.</div>');
            }
            
            $('.infinite-loading').hide();
            is_loading = false;
        }).fail(function() {
            $('.infinite-loading').hide();
            is_loading = false;
            page--;
        });
    }
    
    // 스크롤 이벤트
    $(window).on('scroll', function() {
        if ($(window).scrollTop() + $(window).height() > $(document).height() - 200) {
            loadMorePosts();
        }
    });
});
</script>
<!-- } 게시판 목록 끝 -->