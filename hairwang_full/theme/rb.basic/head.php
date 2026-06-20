<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

if (G5_IS_MOBILE) {
    include_once(G5_THEME_MOBILE_PATH.'/head.php');
    return;
}

if(G5_COMMUNITY_USE === false) {
    define('G5_IS_COMMUNITY_PAGE', true);
    include_once(G5_THEME_SHOP_PATH.'/shop.head.php');
    return;
}
include_once(G5_THEME_PATH.'/head.sub.php');
include_once(G5_LIB_PATH.'/latest.lib.php');
include_once(G5_LIB_PATH.'/outlogin.lib.php');
include_once(G5_LIB_PATH.'/poll.lib.php');
include_once(G5_LIB_PATH.'/visit.lib.php');
include_once(G5_LIB_PATH.'/connect.lib.php');
include_once(G5_LIB_PATH.'/popular.lib.php');

if(defined('_INDEX_')) { // index에서만 실행
    include G5_BBS_PATH.'/newwin.inc.php'; // 팝업레이어
}

include_once(G5_PATH.'/rb/rb.mod/alarm/alarm.php'); // 실시간 알림
?>
<!-- 푸시 알림 스크립트 -->
<script src="<?php echo G5_JS_URL ?>/push_notification.js?ver=<?php echo G5_JS_VER ?>"></script>

    <?php 

    if (isset($rb_core['layout_hd']) && $rb_core['layout_hd'] == "") {
        echo "<div class='no_data' style='padding:30px 0 !important; margin-top:0px; border:0px !important; background-color:#f9f9f9;'><span class='no_data_section_ul1 font-B color-000'>선택된 헤더 레이아웃이 없습니다.</span><br>환경설정 패널에서 먼저 헤더 레이아웃을 설정해주세요.</div>";
    } else if (isset($rb_core['layout_hd'])) { 
        // 레이아웃 인클루드
        include_once(G5_THEME_PATH . '/rb.layout_hd/' . $rb_core['layout_hd'] . '/header.php'); 
    } else {
        echo "<div class='no_data' style='padding:30px 0 !important; margin-top:0px; border:0px !important; background-color:#f9f9f9;'>헤더 레이아웃 설정이 올바르지 않습니다.</span><br>환경설정 패널에서 먼저 헤더 레이아웃을 설정해주세요.</div>";
    }

    ?>

    
    <script>
        $(document).ready(function() {
            // header의 높이 구하기
            var height_header = $('#header').outerHeight();
            var sticky_header = $('#header').outerHeight() + 30;
            // contents_wrap 에 구해진 높이값 적용
            $('#contents_wrap').css('padding-top', height_header + 'px');
            $('#rb_sidemenu').css('top', sticky_header + 'px');

            // ============================================
            // 헤어왕 앱 전용: 배너 상단 여백 15px
            // ============================================
            var urlParams = new URLSearchParams(window.location.search);
            var isApp = urlParams.get('app') === '1';
            var fromSplash = urlParams.get('from') === 'splash';

            console.log('================================================');
            console.log('[헤어왕 앱] 현재 페이지:', window.location.pathname);
            console.log('[헤어왕 앱] 전체 URL:', window.location.href);
            console.log('[헤어왕 앱] app 파라미터:', isApp);
            console.log('[헤어왕 앱] from 파라미터:', fromSplash);
            console.log('[헤어왕 앱] 메인 페이지 여부:', window.location.pathname === '/' || window.location.pathname === '/index.php');
            console.log('================================================');

            if (isApp || fromSplash) {
                console.log('[헤어왕 앱] 15px 여백 추가 중...');
                setTimeout(function() {
                    // rb_layout_box 첫 번째 요소에 여백 추가
                    var $banner = $('.rb_layout_box:first');
                    console.log('[헤어왕 앱] rb_layout_box 요소:', $banner.length, '개 찾음');

                    if ($banner.length > 0) {
                        $banner.each(function() {
                            var currentStyle = $(this).attr('style') || '';
                            $(this).attr('style', currentStyle + '; margin-top: 15px !important;');
                        });
                        console.log('[헤어왕 앱] 배너에 15px 여백 추가 완료');
                    } else {
                        console.log('[헤어왕 앱] rb_layout_box를 찾지 못함');
                    }

                    // flex_box 첫 번째 요소에 상단 여백 추가
                    var $flexBox = $('.flex_box:first');
                    console.log('[헤어왕 앱] flex_box 요소:', $flexBox.length, '개 찾음');

                    if ($flexBox.length > 0) {
                        $flexBox.each(function() {
                            var currentStyle = $(this).attr('style') || '';
                            $(this).attr('style', currentStyle + '; padding-top: 15px !important;');
                        });
                        console.log('[헤어왕 앱] flex_box에 상단 여백 15px 추가 완료');
                    } else {
                        console.log('[헤어왕 앱] flex_box를 찾지 못함');
                    }
                }, 300);
            } else {
                console.log('[헤어왕 앱] 앱 모드 아님 - 여백 추가 안함');
            }
        });
    </script>

    <div class="contents_wrap" id="contents_wrap">
       
        <?php if (!defined("_INDEX_")) { ?>
            <?php include_once(G5_PATH.'/rb/rb.config/topvisual.php'); ?>
        <?php } ?>
        
        <!-- 
        $rb_core['sub_width'] 는 반드시 포함해주세요 (환경설정 > 서브가로폭) 
        모듈박스 스타일 설정
        md_border_ : (solid, dashed)
        md_radius_ : (0~30)
        co_inner_padding_ : (0~30)
        co_gap_ : (0~30)
        -->
        <section class="<?php if (defined("_INDEX_")) { ?>index co_gap_pc_<?php echo $rb_core['gap_pc'] ?><?php } else { ?>sub co_gap_pc_<?php echo $rb_core['gap_pc'] ?><?php } ?>" style="<?php if (!defined("_INDEX_")) { ?>width:<?php echo $rb_core['sub_width'] ?>px;<?php } else { ?>width:<?php echo $rb_core['main_width'] ?>px;<?php } ?>">
        
        <?php if (!defined("_INDEX_")) { ?>

            <?php
                $side_float = "";
                if (isset($rb_core['sidemenu']) && $rb_core['sidemenu'] == "left") {
                    $side_float = "float:right; width: calc(100% - ".$rb_core['sidemenu_width']."px);";
                } else if (isset($rb_core['sidemenu']) && $rb_core['sidemenu'] == "right") {
                    $side_float = "float:left; width: calc(100% - ".$rb_core['sidemenu_width']."px);";
                }
            ?>
            <?php if(isset($side_float) && $side_float) { ?>
            <div id="rb_sidemenu_float" style="<?php echo $side_float ?>">
            <?php } ?>
        <?php } ?>
        
        
        <?php if (!defined("_INDEX_")) { ?>
            <?php if(isset($bo_table) && $bo_table) { ?>
                <div class="rb_bo_top flex_box" data-layout="rb_bo_top_<?php echo $bo_table ?>"></div>
            <?php } ?>
            <?php if(isset($co_id) && $co_id) { ?>
                <div class="rb_co_top flex_box" data-layout="rb_co_top_<?php echo $co_id ?>"></div>
            <?php } ?>
        <?php } ?>
        
        <?php if (isset($rb_core['padding_top']) && $rb_core['padding_top'] == 1) { ?>
        <?php if (defined("_INDEX_")) { ?><span style="margin-top:-70px;" class="pc"></span><?php } ?>
        <?php } ?>
        
        <?php if (!defined("_INDEX_")) { ?><h2 id="container_title"><?php echo get_head_title($g5['title']); ?></h2><?php } ?>

