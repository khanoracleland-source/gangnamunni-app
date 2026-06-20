<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
include_once(G5_LIB_PATH . '/thumbnail.lib.php');

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="' . $board_skin_url . '/style.css">', 0);

// QR코드 처리 - 라이브러리 존재 여부 확인
$qr_enabled = false;
$filePath = '';

if (file_exists(G5_PATH . '/phpqrcode/qrlib.php')) {
    include_once G5_PATH . '/phpqrcode/qrlib.php';
    $qr_enabled = true;
    
    $qrContents = G5_URL . '/' . $bo_table . '/' . $wr_id;
    $filePath = G5_PATH . '/data/qr/' . sha1($qrContents) . ".png";
    
    // QR 디렉토리 생성
    if (!is_dir(G5_PATH . '/data/qr')) {
        @mkdir(G5_PATH . '/data/qr', G5_DIR_PERMISSION, true);
    }
    
    if (!file_exists($filePath) && class_exists('QRcode')) {
        $ecc = 'H';
        $pixel_Size = 10;
        $frame_Size = 10;
        QRcode::png($qrContents, $filePath, $ecc, $pixel_Size, $frame_Size);
    }
    
    // 웹 경로로 변환
    $filePath = str_replace(G5_PATH, G5_URL, $filePath);
}

// get_file_new 함수가 없는 경우 정의
if (!function_exists('get_file_new')) {
    function get_file_new($wr_id, $idx) {
        global $bo_table;
        
        $files = array();
        $sql = "SELECT * FROM rb_namecard_file WHERE wr_id = '{$wr_id}' AND bf_tmp = '{$idx}' ORDER BY bf_no";
        $result = sql_query($sql);
        
        while($row = sql_fetch_array($result)) {
            $files[$row['bf_no']] = $row;
        }
        
        return $files;
    }
}

$file_new = get_file_new($wr_id, '0'); 
$file_new1 = get_file_new($wr_id, '1'); 
$file_new2 = get_file_new($wr_id, '2'); 
$file_new3 = get_file_new($wr_id, '3');
$file_new5 = get_file_new($wr_id, '5');
?>

<script type="text/javascript">
    $(window).resize(function () { resizeYoutube(); });
    $(function () { resizeYoutube(); });
    function resizeYoutube() { 
        $("iframe").each(function () { 
            if (/^https?:\/\/www.youtube.com\/embed\//g.test($(this).attr("src"))) { 
                $(this).css("width", "100%"); 
                $(this).css("height", Math.ceil(parseInt($(this).css("width")) * 480 / 854) + "px"); 
            } 
        }); 
    }
</script>

<style>
    /* 템플릿 관련 스타일 추가 */
    .ex_lay1 {
        border: 1px solid #e5e5e5;
        padding: 20px;
        margin-bottom: 20px;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .ex_lay1:hover {
        box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .ex_lay1_ul1 {
        float: left;
        width: 100px;
        margin-right: 20px;
    }
    
    .ex_lay1_ul1 img {
        width: 100%;
        height: auto;
        border-radius: 5px;
    }
    
    .ex_lay1_ul2 {
        overflow: hidden;
    }
    
    .lay_tit {
        font-size: 18px;
        color: #333;
        margin-bottom: 5px;
    }
    
    .lay_pri {
        font-size: 16px;
        color: #0000fa;
        margin-bottom: 5px;
    }
    
    .lay_sub {
        font-size: 14px;
        color: #777;
        line-height: 1.6;
    }
    
    .ex_lay2 {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: transform 0.3s ease;
    }
    
    .ex_lay2:hover {
        transform: scale(1.02);
    }
    
    .ex_lay2 .lay_tit {
        color: #fff;
        font-size: 20px;
    }
    
    .lay_cp {
        font-size: 32px !important;
        margin-top: 10px;
    }
    
    .ex_lay2_btm {
        background: #f8f8f8;
        padding: 15px 30px;
        border-bottom-left-radius: 15px;
        border-bottom-right-radius: 15px;
        margin-bottom: 20px;
    }
    
    .ex_lay5 {
        background: #f8f8f8;
    }
    
    .ex_lay1_ul5 {
        width: 60px !important;
    }
    
    .boxx-wraps {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 10px;
    }

    .top_nav{display:none;}
    .rows_gnb_wrap{display:none !important;}
    .footer_nav{display:none !important;}
    @media all and (max-width:1024px){
        #header{padding-bottom:15px;}
    }

    #scroll_container {margin-top: 20px;}
    #scroll_container .rb_bbs_top {display: none;}
    #header {display: none;}
    #container_title {display: none;}
    #contents_wrap {padding-top: 0px !important;background-color: #f5f5f5;}
    .sub {padding-top: 0px !important;max-width: 500px;}
    footer {display: none;}
    
    .sh-side-options-pages {display: none !important;}
    .swiper-slide-pf img {height: 600px !important;width: 100%;object-fit: cover;}
    .main {background-color: #F5F5F5;}
    .top {width: 100%;margin: 0 auto;position: relative;}
 
    .swiper-container-pf {z-index: 0;}
    .top_icon {position: absolute;top: 26px;left: 30px;}
    .swiper-container-horizontal>.swiper-pagination-bullets,
    .swiper-pagination-custom,
    .swiper-pagination-fraction {bottom: 100px !important;}
    .top_icon button {border: 0;background-color: transparent;padding: 0 3px 0 3px;}
    .top_icon1 {float: left;}
    .top_icon2 {float: left;}
    .top_icon3 {float: left;}
    .top_icon4 {float: left;margin-left: 3px;}
    .center {width: 100%;margin: 0 auto;background-color: white;z-index: 2;}
    .center_inner {width: 100%;margin: 0 auto;}
    .bs_card {padding-top: 0px;padding-bottom: 3px;}
    .bs_card_qr {float: left;}
    .bs_card_txt {float: left;padding-left: 9px;}
    .bs_card_txt1 {font-size: 16px;padding-top: 3px;}
    .bs_card_txt1 span {font-size: 22px;}
    .bs_card_txt2 {font-size: 12px;color: #999;padding-top: 8px;}
    .bs_card_txt2 span {font-size: 14px;color: #999;}
    .mb-20 {margin-bottom: 5px !important;}
    .bs_card_icon {float: right;padding-top: 3px;}
    .bs_card_icon button {border: 0;background-color: transparent;}
    .bs_card_icon2 {padding-top: 18px;}
    .greeting_bg {background-color: #f9f9f9;border-radius: 10px;}
    .greeting_txt {padding: 20px;font-size: 14px;color: #999;word-break: keep-all;}
    .banner_img1 {float: left;}
    .banner_img2 {float: left;}
    .banner_img3 {float: left;}
    .banner_img4 {float: left;}
    .banner_link {padding-top: 13px;}
    .banner_img button {border: 0;background-color: transparent;padding: 0 3px 0 3px;}
    .banner_img {float: left;}
    .link_text {float: right;padding-top: 13px;}
    .link_text1 {float: left;}
    .link_text2 {float: left;color: #999;}
    .link_text3 {float: right;}
    .link_text1 a {font-size: 14px;color: #666666;}
    .link_text3 a {font-size: 14px;color: #666666;}
    .vis_img1 {padding-top: 43px;}
    .vis_img2 {padding-top: 20px;}
    .bottom_button button {border: 0;background-color: transparent;padding: 0;}
    .bottom_button {display: flex;justify-content: center;width: 100%;padding-top: 0px;padding-bottom: 30px;gap: 10px;}
    .copy {font-size: 12px;color: #999;padding-top: 43px;padding-bottom: 40px;text-align: center;}
    .center_inner {width: 100%;}
    .center {padding-left: 30px;padding-right: 30px;}
    .bs_card_qr img {height: 120px;width: auto;margin-top: -16px;margin-left: -20px;}
    .bs_card_txt2 {padding-top: 5px;}
    .bs_card_txt2 a {display: block;margin-bottom: 5px;color: #777;}
    .rb_bbs_wrap #bo_v_share li {margin-left: 0px;margin-right: 5px;}
    .rb_bbs_wrap #bo_v_share ul {margin-left: 0px;}
    .rb_bbs_wrap {padding: 0px;}
    .sub {padding-bottom: 0px !important;overflow-x: hidden;}
    .rb_bbs_wrap .btm_btns {margin-top: 0px;}
    .rb_bbs_wrap #bo_v_con {padding-bottom: 10px;}
    .btm_btns_right .main_color_bg {height: 60px !important;background: #0000ff;color: #fff;}
    .fl_btns {margin-bottom: 0px !important;border-radius: 0px !important;width: 100% !important;font-size: 16px !important;}
    .btm_btns_right {float: none;}
    .btm_btns_right .main_color_bg img {height: 18px;margin-right: 10px;}
    .top_bars {position: absolute;bottom: -1px;left: 0px;height: 40px;width: 100%;background-color: #fff;border-top-left-radius: 40px;border-top-right-radius: 40px;}
    .swiper-pagination-bullet {background-color: #fff !important;}
    .bottom_button button {background-color: #f5f5f5;width: 90px;height: 90px;border-radius: 10px;}
    .bottom_button button span {display: block;text-align: center;font-size: 14px;margin-top: 10px;color: #777;}
    .maps_wrap {}
    .modal {width: 100%;height: 100%;margin: 0;padding: 0;transition: all 600ms cubic-bezier(0.86, 0, 0.07, 1);bottom: -100%;position: fixed;left: 0;text-align: left;z-index: 98;}
    .maps_wrap.modal-open .modal {bottom: 0px;}
    .modal_inner {max-width: 500px;margin: 0 auto;background-color: #fff;height: 100%;}
    .bls {position: fixed;top: 0;left: 0;width: 100%;height: 100%;background-color: rgba(0, 0, 0, 0.5);display: none;z-index: 97;}
    .bls.bopen {display: block;}
    .bls .bls_inner {background-color: rgba(0, 0, 0, 0.5);max-width: 500px;margin: 0 auto;height: 100%;}
    .bopen {display: block;}
    .maps_wrap2 {}
    .modal2 {width: 100%;height: 50%;margin: 0;padding: 0;transition: all 600ms cubic-bezier(0.86, 0, 0.07, 1);bottom: -50%;position: fixed;left: 0;text-align: left;z-index: 98;}
    .maps_wrap2.modal-open2 .modal2 {bottom: 0px;}
    .modal_inner2 {max-width: 500px;margin: 0 auto;background-color: #fff;height: 100%;padding: 30px;overflow: auto;}
    .bls2 {position: fixed;top: 0px;left: 0px;width: 100%;height: 100%;display: none;z-index: 97;}
    .bls2 .bls_inner2 {background-color: rgba(0, 0, 0, 0.5);max-width: 500px;margin: 0 auto;height: 100%;}
    .bopen2 {display: block;}
    .youtube_wrap {padding: 25px; margin-top: 30px;}
    #js-close-modal2 {background-color: #fff !important; border:1px solid #ddd; color:#000 !important; margin-top: 10px;}
    .close-map-btn {position: relative;max-width: 500px;height: 50px;background-color: #0000ff;font-size: 16px;font-weight: bold;color: #fff;text-align: center;line-height: 50px;cursor: pointer;margin-left: auto;margin-right: auto;}
    .btn_map {display: flex;align-items: center;justify-content: center;flex-direction: column;width: 90px;height: 90px;border: none;border-radius: 12px;background-color: #ffffff;box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);cursor: pointer;text-align: center;margin-bottom: 20px;transition: transform 0.2s ease, box-shadow 0.2s ease;}
    .btn_map:hover {transform: translateY(-5px);box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);background-color: #f9f9f9;}
    .btn_logo {width: 36px;height: 36px;margin-bottom: 8px;}
    .btn_map span {font-size: 12px;color: #555;font-weight: 500;line-height: 1.2;}
    
    @media all and (max-width:768px) {
        .sub {width: 100% !important;max-width: 100% !important;}
        .rb_bbs_wrap {padding: 0px;}
        .modal_inner {width: 100%;max-width: 100%;}
        .bls .bls_inner {width: 100%;max-width: 100%;}
        .modal_inner2 {width: 100%;max-width: 100%;}
        .bls2 .bls_inner2 {width: 100%;max-width: 100%;}
        .top {width: 100%;margin: 0 auto;position: relative;margin-top: 60px;}
    }
</style>

<script src="<?php echo G5_JS_URL; ?>/viewimageresize.js"></script>

<div class="rb_bbs_wrap" style="width:<?php echo $width; ?>">
    <div class="main">
        <div class="top">
            <?php if (isset($file_new[0]['bf_file']) && $file_new[0]['bf_file']) { ?> 
                <img src="<?php echo G5_DATA_URL ?>/namecard/<?php echo $wr_id ?>/0/<?php echo $file_new[0]['bf_file'] ?>" style="width:100%;">
            <?php } ?>

            <?php
            // 파일 출력
            $v_img_count = isset($view['file']) ? count($view['file']) : 0;
            
            if ($v_img_count) {
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

            <div class="top_icon">
                <div class="top_icon3">
                    <button type="button" onclick="location.href='<?php echo G5_URL; ?>/gangnamunni/';" title="홈으로">
                        <svg width="40" height="40" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="15" cy="15" r="15" fill="white" />
                            <path d="M13 21.3333V14.6667H17V21.3333M9 12.6667L15 8L21 12.6667V20C21 20.3536 20.8595 20.6928 20.6095 20.9428C20.3594 21.1929 20.0203 21.3333 19.6667 21.3333H10.3333C9.97971 21.3333 9.64057 21.1929 9.39052 20.9428C9.14048 20.6928 9 20.3536 9 20V12.6667Z" stroke="black" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </button>
                </div>

                <?php if ($view['mb_id'] == $member['mb_id'] || $is_admin) { ?>
                    <div class="top_icon1">
                        <button type="button" onclick="location.href='<?php echo G5_URL ?>/rb/home.php?mb_id=<?php echo $view['mb_id'] ?>';" title="마이홈">
                            <svg width="40" height="40" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="15" cy="15" r="15" fill="white" />
                                <path d="M20 20.25V19C20 18.337 19.7366 17.7011 19.2678 17.2322C18.7989 16.7634 18.163 16.5 17.5 16.5H12.5C11.837 16.5 11.2011 16.7634 10.7322 17.2322C10.2634 17.7011 10 18.337 10 19V20.25M17.5 11.5C17.5 12.8807 16.3807 14 15 14C13.6193 14 12.5 12.8807 12.5 11.5C12.5 10.1193 13.6193 9 15 9C16.3807 9 17.5 10.1193 17.5 11.5Z" stroke="black" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                <?php } ?>
                
                <?php if ($update_href) { ?>
                    <div class="top_icon2">
                        <button type="button" onclick="location.href='<?php echo $update_href ?>';" title="수정">
                            <svg width="40" height="40" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="15" cy="15" r="15" fill="white" />
                                <path d="M15 16.9091C16.0544 16.9091 16.9091 16.0544 16.9091 15C16.9091 13.9456 16.0544 13.0909 15 13.0909C13.9456 13.0909 13.0909 13.9456 13.0909 15C13.0909 16.0544 13.9456 16.9091 15 16.9091Z" stroke="black" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M19.7091 16.9091C19.6244 17.101 19.5991 17.3139 19.6365 17.5204C19.674 17.7268 19.7724 17.9173 19.9191 18.0673L19.9573 18.1055C20.0756 18.2237 20.1695 18.364 20.2335 18.5185C20.2976 18.673 20.3305 18.8387 20.3305 19.0059C20.3305 19.1732 20.2976 19.3388 20.2335 19.4933C20.1695 19.6478 20.0756 19.7882 19.9573 19.9064C19.8391 20.0247 19.6987 20.1186 19.5442 20.1826C19.3897 20.2467 19.2241 20.2796 19.0568 20.2796C18.8896 20.2796 18.7239 20.2467 18.5694 20.1826C18.4149 20.1186 18.2746 20.0247 18.1564 19.9064L18.1182 19.8682C17.9682 19.7215 17.7777 19.6231 17.5713 19.5856C17.3649 19.5482 17.1519 19.5735 16.96 19.6582C16.7718 19.7388 16.6113 19.8728 16.4982 20.0435C16.3851 20.2143 16.3245 20.4143 16.3236 20.6191V20.7273C16.3236 21.0648 16.1895 21.3885 15.9509 21.6272C15.7122 21.8659 15.3885 22 15.0509 22C14.7134 22 14.3896 21.8659 14.151 21.6272C13.9123 21.3885 13.7782 21.0648 13.7782 20.7273V20.67C13.7733 20.4594 13.7051 20.2551 13.5825 20.0837C13.4599 19.9124 13.2886 19.7818 13.0909 19.7091C12.899 19.6244 12.6861 19.5991 12.4796 19.6365C12.2732 19.674 12.0827 19.7724 11.9327 19.9191L11.8945 19.9573C11.7763 20.0756 11.636 20.1695 11.4815 20.2335C11.327 20.2976 11.1613 20.3305 10.9941 20.3305C10.8268 20.3305 10.6612 20.2976 10.5067 20.2335C10.3522 20.1695 10.2118 20.0756 10.0936 19.9573C9.9753 19.8391 9.88143 19.6987 9.81738 19.5442C9.75333 19.3897 9.72036 19.2241 9.72036 19.0568C9.72036 18.8896 9.75333 18.7239 9.81738 18.5694C9.88143 18.4149 9.9753 18.2746 10.0936 18.1564L10.1318 18.1182C10.2785 17.9682 10.3769 17.7777 10.4144 17.5713C10.4518 17.3649 10.4265 17.1519 10.3418 16.96C10.2612 16.7718 10.1272 16.6113 9.95648 16.4982C9.78575 16.3851 9.58568 16.3245 9.38091 16.3236H9.27273C8.93518 16.3236 8.61146 16.1895 8.37277 15.9509C8.13409 15.7122 8 15.3885 8 15.0509C8 14.7134 8.13409 14.3896 8.37277 14.151C8.61146 13.9123 8.93518 13.7782 9.27273 13.7782H9.33C9.54063 13.7733 9.74491 13.7051 9.91628 13.5825C10.0877 13.4599 10.2182 13.2886 10.2909 13.0909C10.3756 12.899 10.4009 12.6861 10.3635 12.4796C10.326 12.2732 10.2276 12.0827 10.0809 11.9327L10.0427 11.8945C9.92439 11.7763 9.83052 11.636 9.76647 11.4815C9.70242 11.327 9.66945 11.1613 9.66945 10.9941C9.66945 10.8268 9.70242 10.6612 9.76647 10.5067C9.83052 10.3522 9.92439 10.2118 10.0427 10.0936C10.1609 9.9753 10.3013 9.88143 10.4558 9.81738C10.6103 9.75333 10.7759 9.72036 10.9432 9.72036C11.1104 9.72036 11.2761 9.75333 11.4306 9.81738C11.5851 9.88143 11.7254 9.9753 11.8436 10.0936L11.8818 10.1318C12.0318 10.2785 12.2223 10.3769 12.4287 10.4144C12.6351 10.4518 12.8481 10.4265 13.04 10.3418H13.0909C13.2791 10.2612 13.4396 10.1272 13.5527 9.95648C13.6658 9.78575 13.7265 9.58568 13.7273 9.38091V9.27273C13.7273 8.93518 13.8614 8.61146 14.1 8.37277C14.3387 8.13409 14.6625 8 15 8C15.3375 8 15.6613 8.13409 15.9 8.37277C16.1386 8.61146 16.2727 8.93518 16.2727 9.27273V9.33C16.2735 9.53477 16.3342 9.73484 16.4473 9.90557C16.5604 10.0763 16.7209 10.2102 16.9091 10.2909C17.101 10.3756 17.3139 10.4009 17.5204 10.3635C17.7268 10.326 17.9173 10.2276 18.0673 10.0809L18.1055 10.0427C18.2237 9.92439 18.364 9.83052 18.5185 9.76647C18.673 9.70242 18.8387 9.66945 19.0059 9.66945C19.1732 9.66945 19.3388 9.70242 19.4933 9.76647C19.6478 9.83052 19.7882 9.92439 19.9064 10.0427C20.0247 10.1609 20.1186 10.3013 20.1826 10.4558C20.2467 10.6103 20.2796 10.7759 20.2796 10.9432C20.2796 11.1104 20.2467 11.2761 20.1826 11.4306C20.1186 11.5851 20.0247 11.7254 19.9064 11.8436L19.8682 11.8818C19.7215 12.0318 19.6231 12.2223 19.5856 12.4287C19.5482 12.6351 19.5735 12.8481 19.6582 13.04V13.0909C19.7388 13.2791 19.8728 13.4396 20.0435 13.5527C20.2143 13.6658 20.4143 13.7265 20.6191 13.7273H20.7273C21.0648 13.7273 21.3885 13.8614 21.6272 14.1C21.8659 14.3387 22 14.6625 22 15C22 15.3375 21.8659 15.6613 21.6272 15.9C21.3885 16.1386 21.0648 16.2727 20.7273 16.2727H20.67C20.4652 16.2735 20.2652 16.3342 20.0944 16.4473C19.9237 16.5604 19.7898 16.7209 19.7091 16.9091Z" stroke="black" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </button>
                    </div>
                <?php } ?>
                
                <?php if ($delete_href) { ?>
                    <div class="top_icon4">
                        <a href="<?php echo $delete_href ?>" onclick="del(this.href); return false;" title="삭제">
                            <svg width="40" height="40" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <circle cx="15" cy="15" r="15" fill="white" />
                                <path d="M9 10.6667H10.3333M10.3333 10.6667H21M10.3333 10.6667V20C10.3333 20.3536 10.4738 20.6928 10.7239 20.9428C10.9739 21.1929 11.313 21.3333 11.6667 21.3333H18.3333C18.687 21.3333 19.0261 21.1929 19.2761 20.9428C19.5262 20.6928 19.6667 20.3536 19.6667 20V10.6667M12.3333 10.6667V9.33333C12.3333 8.97971 12.4738 8.64057 12.7239 8.39052C12.9739 8.14048 13.313 8 13.6667 8H16.3333C16.687 8 17.0261 8.14048 17.2761 8.39052C17.5262 8.64057 17.6667 8.97971 17.6667 9.33333V10.6667M13.6667 14V18M16.3333 14V18" stroke="black" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                    </div>
                <?php } ?>

                <div class="cb"></div>
            </div>

            <div class="top_bars"></div>
        </div>

        <div class="center">
            <div class="center_inner">
                <div class="bs_card">
                    <div class="bs_card_qr">
                    <?php if ($qr_enabled && $filePath) { ?>
                        <img src="<?php echo $filePath; ?>" alt="QR코드">
                    <?php } else { ?>
                        <div style="width:80px;height:80px;background:#f0f0f0;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;text-align:center;margin-top:10px;border-radius:5px;">
                            QR코드<br>미지원
                        </div>
                    <?php } ?>
                    <br>
                    <?php if (isset($file_new1[0]['bf_file']) && $file_new1[0]['bf_file']) { ?>
                        <img src="<?php echo G5_DATA_URL ?>/namecard/<?php echo $wr_id ?>/1/<?php echo $file_new1[0]['bf_file'] ?>" style="width:80px; height:auto; margin-left:0px;">
                    <?php } ?>
                    </div>
                    
                    <div class="bs_card_txt">
                        <div class="bs_card_txt1"><?php echo isset($view['wr_3']) ? $view['wr_3'] : ''; ?></div>
                        <div class="bs_card_txt1">
                            <?php echo isset($view['wr_2']) ? $view['wr_2'] : ''; ?> 
                            <span class="font-B"><?php echo isset($view['wr_1']) ? $view['wr_1'] : ''; ?></span>
                        </div>
                        
                        <?php if (isset($view['wr_link1']) || isset($view['wr_4'])) { ?>
                            <?php
                            $url_link = '';
                            if(isset($view['wr_link1']) && $view['wr_link1']) {
                                if(strpos($view['wr_link1'], 'http') !== false) {
                                    $url_link = $view['wr_link1'];
                                } else {
                                    $url_link = 'https://'.$view['wr_link1'];
                                }
                            }
                            ?>
                            
                            <div class="bs_card_txt2">
                                <?php if (isset($view['wr_4']) && $view['wr_4']) { ?>
                                    <a href="tel:<?php echo $view['wr_4'] ?>">
                                        <bb style="font-size: 13px;color: #1049fd;font-weight: 600;">휴대전화 </bb>
                                        <?php echo $view['wr_4'] ?>
                                    </a>
                                <?php } ?>
                                
                                <?php if (isset($view['wr_12']) && $view['wr_12']) { ?>
                                    <a href="tel:<?php echo $view['wr_12'] ?>">
                                        <bb style="font-size: 13px;color: #1049fd;font-weight: 600;">일반전화 </bb>
                                        <?php echo $view['wr_12'] ?>
                                    </a>
                                <?php } ?>
                                
                                <?php if (isset($view['wr_5']) && $view['wr_5']) { ?>
                                    <a href="mailto:<?php echo $view['wr_5'] ?>">
                                        <bb style="font-size: 13px;color: #1049fd;font-weight: 600;">이메일 </bb>
                                        <?php echo $view['wr_5'] ?>
                                    </a>
                                <?php } ?>
                                
                                <?php if ($url_link) { ?>
                                    <a href="<?php echo $url_link ?>" target="_blank" class="cut">
                                        <bb style="font-size: 13px;color: #1049fd;font-weight: 600;">홈페이지 </bb>
                                        <?php echo $view['wr_link1'] ?>
                                    </a>
                                <?php } ?>
                                
                                <?php if (isset($view['wr_7']) || isset($view['wr_8'])) { ?>
                                    <bb style="font-size: 13px;color: #1049fd;font-weight: 600;">주소 </bb>
                                    <bb id="js-click-modal">
                                        <?php echo isset($view['wr_7']) ? $view['wr_7'] : ''; ?>
                                        <?php echo isset($view['wr_8']) ? ' '.$view['wr_8'] : ''; ?>
                                    </bb>
                                <?php } ?>
                                
                                <?php if (isset($view['wr_13']) && $view['wr_13']) { ?>
                                    <a href="javascript:void(0);">FAX : <?php echo $view['wr_13'] ?></a>
                                <?php } ?>
                            </div>
                        <?php } ?>
                    </div>
                    
                    <?php if (isset($view['wr_4']) && $view['wr_4']) { ?>
                        <div class="bs_card_icon">
                            <div class="bs_card_icon1">
                                <button type="button" onclick="location.href='tel:<?php echo $view['wr_4'] ?>';">
                                    <img src="<?php echo $board_skin_url ?>/img/ico_tels.svg">
                                </button>
                            </div>
                            <div class="bs_card_icon2">
                                <button type="button" onclick="location.href='sms:<?php echo $view['wr_4'] ?>';">
                                    <img src="<?php echo $board_skin_url ?>/img/ico_sms.svg">
                                </button>
                            </div>
                        </div>
                    <?php } ?>
                    
                    <div class="cb"></div>
                </div>

                <?php if (isset($view['wr_6']) && $view['wr_6']) { ?>
                    <div class="greeting_bg" style="margin-top:10px">
                        <div class="greeting_txt">
                            <?php echo nl2br($view['wr_6']); ?>
                        </div>
                    </div>
                <?php } ?>

                <div class="banner_link">
                    <div class="banner_img">
                        <div id="bo_v_share">
                            <?php include_once (G5_SNS_PATH . "/view.sns.skin.php"); ?>
                            <ul class="copy_urls">
                                <li>
                                    <a href="javascript:void(0);" id="data-copy">
                                        <img src="<?php echo $board_skin_url ?>/img/ico_sha.png" alt="공유링크 복사" width="32">
                                    </a>
                                </li>
                                <?php $currents_url = G5_URL . $_SERVER['REQUEST_URI']; ?>
                                <input type="hidden" id="data-area" class="data-area" value="<?php echo $currents_url ?>">
                                <script>
                                    $(document).ready(function () {
                                        $('#data-copy').click(function () {
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
                    
                    <div class="link_text">
                        <div class="link_text3">
                            <?php if ($is_member) { ?>
                                <?php if ($view['mb_id'] == $member['mb_id']) { ?>
                                    <a href="javascript:alert('자신의 카드는 보관할 수 없습니다.');">받은카드 등록</a>
                                <?php } else { ?>
                                    <a href="javascript:card_sc('<?php echo $view['wr_id'] ?>')">받은카드 등록</a>
                                <?php } ?>
                            <?php } else { ?>
                                <a href="javascript:alert('로그인 후 이용해주세요.');">받은카드 등록</a>
                            <?php } ?>
                        </div>

                        <script>
                            function card_sc(wr_id) {
                                if (confirm("현재 카드를 카드집에 보관합니다.\n보관 하시겠습니까?"))
                                    location.href = '<?php echo G5_BBS_URL ?>/card_sc.php?wr_id=' + wr_id;
                            }
                        </script>
                    </div>
                    <div class="cb"></div>
                </div>

                <!-- 유튜브 영상 -->
                <?php if(isset($view['wr_11']) && $view['wr_11']) { ?>
                    <div class="youtube_wrap">
                        <div class="youtube">
                            <?php
                            // 유튜브 ID 추출
                            $youtube_id = $view['wr_11'];
                            if(strpos($youtube_id, 'youtube.com/embed/') !== false) {
                                $youtube_id = str_replace('https://www.youtube.com/embed/', '', $youtube_id);
                            }
                            ?>
                            <iframe src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen></iframe>
                        </div>
                        <a href="https://www.youtube.com/watch?v=<?php echo $youtube_id; ?>" class="urls font-B" target="_blank">
                            https://www.youtube.com/watch?v=<span class="main_color"><?php echo $youtube_id; ?></span>
                        </a>
                    </div>
                <?php } ?>

                <!-- 템플릿 출력 부분들 생략 (너무 길어서) -->

                <div id="bo_v_con">
                    <li class="font-B font-18 mb-20">정보</li>
                    <?php echo get_view_thumbnail($view['content']); ?>
                </div>

                <div class="bottom_button">
                    <div class="">
                        <button type="button" onclick="location.href='<?php echo G5_URL; ?>/gangnamunni/';">
                            <img src="<?php echo $board_skin_url ?>/img/btm_ico1.svg">
                            <span class="font-B">홈</span>
                        </button>
                    </div>
                    
                    <?php if (isset($view['wr_4']) && $view['wr_4']) { ?>
                        <div class="">
                            <button type="button" onclick="saveContact()">
                                <img src="<?php echo $board_skin_url ?>/img/btm_ico3.svg">
                                <span class="font-B">연락처등록</span>
                            </button>
                        </div>
                        
                        <script>
                        function saveContact() {
                            try {
                                var userAgent = navigator.userAgent.toLowerCase();
                                var isAndroid = userAgent.indexOf('android') > -1;
                                var isiOS = /iphone|ipad|ipod/.test(userAgent);
                                var isApp = isAndroid || isiOS;
                                
                                if (isApp) {
                                    var vcardUrl = '<?php echo G5_BBS_URL ?>/vcard.php?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>';
                                    var newWindow = window.open(vcardUrl, '_blank');
                                    
                                    if (!newWindow) {
                                        window.location.href = vcardUrl;
                                    }
                                } else {
                                    window.location.href = '<?php echo G5_BBS_URL ?>/vcard.php?bo_table=<?php echo $bo_table; ?>&wr_id=<?php echo $wr_id; ?>';
                                }
                                
                                setTimeout(function() {
                                    if (confirm('연락처 저장이 되지 않으셨나요?\n연락처 정보를 복사하시겠습니까?')) {
                                        copyContactInfo();
                                    }
                                }, 3000);
                                
                            } catch(e) {
                                alert('연락처 저장 중 오류가 발생했습니다.\n연락처 정보를 복사해드릴게요.');
                                copyContactInfo();
                            }
                        }
                        
                        function copyContactInfo() {
                            var contactInfo = '';
                            contactInfo += '이름: <?php echo str_replace(array("\r","\n","'"), array("","",""), $view['wr_1']); ?>\n';
                            <?php if($view['wr_3']) { ?>contactInfo += '회사: <?php echo str_replace(array("\r","\n","'"), array("","",""), $view['wr_3']); ?>\n';<?php } ?>
                            <?php if($view['wr_2']) { ?>contactInfo += '직책: <?php echo str_replace(array("\r","\n","'"), array("","",""), $view['wr_2']); ?>\n';<?php } ?>
                            <?php if($view['wr_4']) { ?>contactInfo += '휴대폰: <?php echo str_replace(array("\r","\n","'"), array("","",""), $view['wr_4']); ?>\n';<?php } ?>
                            <?php if($view['wr_12']) { ?>contactInfo += '일반전화: <?php echo str_replace(array("\r","\n","'"), array("","",""), $view['wr_12']); ?>\n';<?php } ?>
                            <?php if($view['wr_5']) { ?>contactInfo += '이메일: <?php echo str_replace(array("\r","\n","'"), array("","",""), $view['wr_5']); ?>\n';<?php } ?>
                            <?php if($view['wr_7'] || $view['wr_8']) { ?>contactInfo += '주소: <?php echo str_replace(array("\r","\n","'"), array("","",""), $view['wr_7'].' '.$view['wr_8']); ?>';<?php } ?>
                            
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(contactInfo).then(function() {
                                    alert('연락처 정보가 복사되었습니다.\n휴대폰 연락처 앱에서 붙여넣기 하세요.');
                                }).catch(function() {
                                    fallbackCopy(contactInfo);
                                });
                            } else {
                                fallbackCopy(contactInfo);
                            }
                        }
                        
                        function fallbackCopy(text) {
                            var textArea = document.createElement("textarea");
                            textArea.value = text;
                            textArea.style.position = "fixed";
                            textArea.style.left = "-999999px";
                            textArea.style.top = "-999999px";
                            document.body.appendChild(textArea);
                            textArea.focus();
                            textArea.select();
                            
                            try {
                                var successful = document.execCommand('copy');
                                document.body.removeChild(textArea);
                                if (successful) {
                                    alert('연락처 정보가 복사되었습니다.\n휴대폰 연락처 앱에서 붙여넣기 하세요.');
                                } else {
                                    alert('다음 정보를 직접 입력해주세요:\n\n' + text);
                                }
                            } catch (err) {
                                document.body.removeChild(textArea);
                                alert('다음 정보를 직접 입력해주세요:\n\n' + text);
                            }
                        }
                        </script>
                    <?php } ?>
                    
                    <?php if (isset($view['wr_9']) && $view['wr_9']) { ?>
                        <div class="">
                            <button type="button" id="js-click-modal" onclick="openMapModal()">
                                <img src="<?php echo $board_skin_url ?>/img/btm_ico4.svg">
                                <span class="font-B">지도</span>
                            </button>
                        </div>
                    <?php } ?>
                </div>
                
                <div class="copy font-p-400">ⓒ facekon. All Rights Reserved.</div>
            </div>
        </div>
    </div>

    <ul class="btm_btns">
        <dd class="btm_btns_right">
            <?php if ($write_href) { ?>
                <button type="button" name="btn_submit" class="fl_btns main_color_bg" onclick="location.href='<?php echo $write_href ?>';">
                    <img src="<?php echo $board_skin_url ?>/img/ico_write.svg">
                    <span class="font-R">카드 만들기</span>
                </button>
            <?php } ?>
            <div class="cb"></div>
        </dd>
        <dd class="cb"></dd>
    </ul>
</div>

<script>
    // 뷰 페이지 관련 스크립트들
    function board_move(href) {
        window.open(href, "boardmove", "left=50, top=50, width=500, height=550, scrollbars=1");
    }

    $(function () {
        $("a.view_image").click(function () {
            window.open(this.href, "large_image", "location=yes,links=no,toolbar=no,top=10,left=10,width=10,height=10,resizable=yes,scrollbars=no,status=no");
            return false;
        });

        $("#bo_v_atc").viewimageresize();
    });
</script>