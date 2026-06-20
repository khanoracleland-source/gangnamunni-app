<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css">', 0);
add_javascript(G5_POSTCODE_JS, 0);    //다음 주소 js

// get_file_new 함수가 없는 경우 임시로 정의
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

// 필수 변수 초기화
$write = isset($write) ? $write : array();
$w = isset($w) ? $w : '';
$wr_id = isset($wr_id) ? $wr_id : 0;

// wr_20이 있으면 json 배열로 변환
$json_wr_20_array = '[]';
if ($w == 'u' && isset($write['wr_20']) && $write['wr_20']) {
    $wr_20_array = explode('|', $write['wr_20']);
    $json_wr_20_array = json_encode($wr_20_array);
}
?>
<style>
    /* 공통 스타일 */
    #container_title {display: none;}
    .rows_gnb_wrap {display: none !important;}
    .footer_nav {display: none !important;}
    .nc_titles {color: #666; margin-bottom: 20px;}
    .sub_inp_tit {display: block; margin-bottom: 10px; color: #999;}
    .cd_wrap select, .cd_wrap input {height: 55px; width: 100%;}
    .au_btn_search_file {min-height: 55px; padding-top: 20px !important; box-sizing: border-box;}
    .file_input_wrap {position: relative;}
    .file_input_chk {position: absolute; top: 50%; transform: translateY(-50%); right: 10px;}
    .file_input {line-height: 55px;}
    .new_btn_submit {height: 55px !important; padding-left: 50px !important; padding-right: 50px !important; font-size: 16px !important;}
    .hiddens {display: none;}
    .rb_bbs_wrap .rb_inp_wrap_link i {top: 50%; transform: translateY(-50%);}
    .nc_addr {display: flex; gap: 10px;}
    .nc_addr1 input {width: 50%;}
    .nc_addr2 {float: left; width: 50%;}
    .nc_addr3 {float: right; width: 60%;}
    .nc_addr_btn {
        height: 55px;
        border: 1px solid #000;
        padding-left: 20px;
        padding-right: 20px;
        background-color: #fff;
        border-radius: 10px;
        width: 100%;
    }
    .nc_titles_gap {margin-top: 50px; padding-top: 60px; border-top: 1px solid #ddd;}
    @media all and (max-width: 1024px) {
        #header {padding-bottom: 10px;}
    }

    /* 모달 스타일 */
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    .modal_content {
        background: #fff;
        padding: 20px;
        border-radius: 10px;
        width: 50%;
        text-align: center;
        max-width: 800px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
    }

    /* 카테고리 스타일 */
    .category_list {
        list-style: none;
        padding: 0;
        margin: 20px 0;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: space-between;
    }

    .category_item {
        background: #f8f8f8;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        cursor: pointer;
        transition: background 0.3s ease, color 0.3s ease;
        flex: 1 1 calc(20% - 15px);
        text-align: center;
        font-size: 14px;
        box-sizing: border-box;
    }

    .category_item.selected {
        background: #0000fa;
        color: #fff;
        border-color: #0056b3;
    }

    .category_item.full_width {
        flex: 1 1 100%;
    }

    .btn_category_select {
        display: inline-block;
        padding: 10px 20px;
        background: #fff;
        color: #000;
        border: 1px solid #000;
        border-radius: 10px;
        cursor: pointer;
        height: 55px;
        width: 100%;
        font-size: 15px;
        text-align: center;
        box-sizing: border-box;
    }

    .btn_category_select.selected {
        background: #0000fa;
        color: #fff;
        border: none;
    }

    .btn_done {
        display: inline-block;
        padding: 10px 20px;
        background: #0000fa;
        color: #fff;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        height: 55px;
        width: 100%;
        font-size: 15px;
    }

    .icon_wrapper {
        margin: 10px;
    }

    /* 반응형 디자인 */
    @media screen and (max-width: 768px) {
        .category_item {
            flex: 1 1 calc(33.33% - 10px);
        }
        .modal_content {
            width: 85%;
        }
    }

    @media screen and (max-width: 480px) {
        .category_item {
            flex: 1 1 calc(30% - 10px);
            font-size: 11px;
            padding: 8px;
            font-weight: 600;
        }
    }

    @media screen and (max-width: 320px) {
        .category_item {
            flex: 1 1 100%;
        }
    }

    .tab-content {
        margin-bottom: 5rem;
        display: block;
    }

    .temp_wrap_gnb {
        position: relative;
        z-index: 10;
    }

    .temp_wrap_gnb.fixed {
        position: fixed;
        top: 62px;
        left: 0;
        width: 100%;
        background-color: white;
        box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
    }

    .temp_wrap_gnb.fixed > div {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 10px;
        box-sizing: border-box;
    }

    .temp_wrap_gnb.fixed .mb-20 {
        text-align: center;
    }

    @media all and (max-width: 1024px) {
        html, body {
            overflow-x: unset;
        }
        .temp_wrap_gnb > div {
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            height: 130px;
            white-space: nowrap;
            padding-bottom: 10px;
        }
        .temp_wrap_gnb.fixed {
            width: 100%;
        }
        .switch {
            flex: 0 0 auto;
            margin-right: 10px;
            margin-top: 10px;
        }
    }

    /* Custom radio button design for 명함 이미지 등록 */
    .custom-radio {
        display: flex;
        align-items: center;
        position: relative;
        cursor: pointer;
        font-family: Arial, sans-serif;
        font-size: 16px;
    }

    .custom-radio input[type="radio"] {
        display: none;
    }

    .custom-radio span {
        display: inline-block;
        padding: 10px 20px;
        border-radius: 10px;
        background-color: #f0f0f0;
        color: #666;
        transition: all 0.3s ease;
    }

    .custom-radio input[type="radio"]:checked + span {
        background-color: #0000fa;
        color: #fff;
        font-weight: bold;
    }

    /* 명함 이미지 등록 관련 스타일 */
    .video-container {
        width: 100%;
        height: 250px;
        overflow: hidden;
        position: relative;
    }

    video {
        width: 100%;
        height: auto;
        object-fit: cover;
        border-radius: 10px;
    }

    canvas {
        width: 100%;
        height: 250px;
        background-color: #fff;
        border: 2px dashed #ddd;
        border-radius: 10px;
    }
</style>

<div class="nc_wrap">
    <ul class="nc_tit">
        <li class="nc_tit1 font-B">포토폴리오 만들기</li>
        <li class="nc_tit2">헤어왕에서 멋진 나만의 포토폴리오<br>만들어 보세요.</li>
    </ul>
    <ul class="nc_img"><img src="<?php echo $board_skin_url; ?>/img/ext.png"></ul>
    <div class="cb"></div>
</div>

<div class="cd_wrap rb_bbs_wrap rb_bbs_write_wrap">

    <form name="fwrite" id="fwrite" action="<?php echo $action_url ?>" onsubmit="return fwrite_submit(this);" method="post" enctype="multipart/form-data" autocomplete="off" style="width:<?php echo $width; ?>">
        <input type="hidden" name="uid" value="<?php echo get_uniqid(); ?>">
        <input type="hidden" name="w" value="<?php echo $w ?>">
        <input type="hidden" name="bo_table" value="<?php echo $bo_table ?>">
        <input type="hidden" name="wr_id" value="<?php echo $wr_id ?>">
        <input type="hidden" name="sca" value="<?php echo $sca ?>">
        <input type="hidden" name="sfl" value="<?php echo $sfl ?>">
        <input type="hidden" name="stx" value="<?php echo $stx ?>">
        <input type="hidden" name="spt" value="<?php echo $spt ?>">
        <input type="hidden" name="sst" value="<?php echo $sst ?>">
        <input type="hidden" name="sod" value="<?php echo $sod ?>">
        <input type="hidden" name="page" value="<?php echo $page ?>">

        <?php
        $option = '';
        $option_hidden = '';
        if ($is_notice || $is_html || $is_secret || $is_mail) {
            $option = '';

            if ($is_html) {
                if ($is_dhtml_editor) {
                    $option_hidden .= '<input type="hidden" value="html1" name="html">';
                }
            }

            if ($is_secret) {
                if ($is_admin || $is_secret==1) {
                    $option .= PHP_EOL.'<input type="checkbox" id="secret" name="secret"  class="selec_chk" value="secret" '.$secret_checked.'>'.PHP_EOL.'<label for="secret"><span></span>비공개 (타인이 볼 수 없어요)</label>　';
                } else {
                    $option_hidden .= '<input type="hidden" name="secret" value="secret">';
                }
            }
        }
        echo $option_hidden;
        ?>

        <div id="step1">
            <div class="nc_titles font-B">STEP 1. 기본정보 입력</div>
            <div class="nc_left">

                <?php if ($option) { ?>
                    <div class="rb_inp_wrap">
                        <ul>
                            <div class="write_div">
                                <span class="sound_only">옵션</span>
                                <ul class="bo_v_option">
                                    <?php echo $option ?>
                                </ul>
                            </div>
                        </ul>
                    </div>
                <?php } ?>

                <!-- 카테고리 버튼 -->
                <?php if ($is_category) { ?>
                    <span class="sub_inp_tit font-R">카테고리 선택</span>
                    <div class="rb_inp_wrap">
                        <button type="button" id="openCategoryModal" class="btn_category_select">
                            <?php echo isset($write['ca_name']) && $write['ca_name'] ? htmlspecialchars($write['ca_name']) : "카테고리를 선택하세요"; ?>
                        </button>
                        <input type="hidden" name="ca_name" id="ca_name" value="<?php echo isset($write['ca_name']) ? htmlspecialchars($write['ca_name']) : ''; ?>">
                    </div>
                <?php } ?>

                <!-- 카테고리 모달 -->
                <div id="categoryModal" class="modal" style="display: none;">
                    <div class="modal_content">
                        <h3 style="padding: 10px; font-size: 20px;">아래의 카테고리 중 자신의 직업과 관련 있는 카테고리를 선택해주세요.</h3>
                        <p>관련 분야가 없는 경우 <b style="color: #0000fa; text-decoration: underline;">[기타분야 직업 종사]</b> 선택</p>
                        <ul class="category_list">
                            <li class="category_item" data-category="보험설계">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-pie-chart"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
                                </div>
                                <span>보험설계</span>
                            </li>
                            <li class="category_item" data-category="자산·재무설계">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-database"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                                </div>
                                <span>자산·재무설계</span>
                            </li>
                            <li class="category_item" data-category="대출·여신·채권">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-file-text"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                </div>
                                <span>대출·여신·채권</span>
                            </li>
                            <li class="category_item" data-category="신용카드·상품">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-credit-card"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                </div>
                                <span>신용카드·상품</span>
                            </li>
                            <li class="category_item" data-category="장기렌트·리스">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-key"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path></svg>
                                </div>
                                <span>장기렌트·리스</span>
                            </li>
                            <li class="category_item" data-category="가전·기타 렌탈">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-speaker"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><circle cx="12" cy="14" r="4"></circle><line x1="12" y1="6" x2="12.01" y2="6"></line></svg>
                                </div>
                                <span>가전·기타 렌탈</span>
                            </li>
                            <li class="category_item" data-category="창업·가맹">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-file-minus"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="9" y1="15" x2="15" y2="15"></line></svg>
                                </div>
                                <span>창업·가맹</span>
                            </li>
                            <li class="category_item" data-category="신차·중고차 영업">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-truck"><rect x="1" y="3" width="15" height="13"></rect><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon><circle cx="5.5" cy="18.5" r="2.5"></circle><circle cx="18.5" cy="18.5" r="2.5"></circle></svg>
                                </div>
                                <span>신차·중고차 영업</span>
                            </li>
                            <li class="category_item" data-category="뷰티·헤어">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-scissors"><circle cx="6" cy="6" r="3"></circle><circle cx="6" cy="18" r="3"></circle><line x1="20" y1="4" x2="8.12" y2="15.88"></line><line x1="14.47" y1="14.48" x2="20" y2="20"></line><line x1="8.12" y1="8.12" x2="12" y2="12"></line></svg>
                                </div>
                                <span>뷰티·헤어</span>
                            </li>
                            <li class="category_item" data-category="여행·숙박">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-send"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                                </div>
                                <span>여행·숙박</span>
                            </li>
                            <li class="category_item" data-category="부동산 관련 서비스">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-clipboard"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path><rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect></svg>
                                </div>
                                <span>부동산 관련 서비스</span>
                            </li>
                            <li class="category_item" data-category="결혼·웨딩 서비스">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-smile"><circle cx="12" cy="12" r="10"></circle><path d="M8 14s1.5 2 4 2 4-2 4-2"></path><line x1="9" y1="9" x2="9.01" y2="9"></line><line x1="15" y1="9" x2="15.01" y2="9"></line></svg>
                                </div>
                                <span>결혼·웨딩 서비스</span>
                            </li>
                            <li class="category_item" data-category="광고영업 마케팅">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-message-square"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                                </div>
                                <span>광고영업 마케팅</span>
                            </li>
                            <li class="category_item" data-category="이사,청소 등 생활서비스">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-wind"><path d="M9.59 4.59A2 2 0 1 1 11 8H2m10.59 11.41A2 2 0 1 0 14 16H2m15.73-8.27A2.5 2.5 0 1 1 19.5 12H2"></path></svg>
                                </div>
                                <span>이사,청소 등<br>생활서비스</span>
                            </li>
                            <li class="category_item" data-category="교육·학원">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-book-open"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                                </div>
                                <span>교육·학원</span>
                            </li>
                            <li class="category_item full_width" data-category="기타분야 직업 종사">
                                <div class="icon_wrapper">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="feather feather-grid"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                                </div>
                                <span>기타분야 직업 종사</span>
                            </li>
                        </ul>
                        <button type="button" id="categorySelectDone" class="btn_done">선택완료</button>
                    </div>
                </div>

                <?php $file_new = get_file_new($wr_id, '0'); ?>

                <div class="rb_inp_wrap rb_inp_wrap_gap">
                    <span class="sub_inp_tit font-R">상단 바 <normal style="color:#d50000">※최상단 영역표시</normal></span>
                    <ul class="file_input_wrap">
                        <input type="file" name="bf_file_new[]" title="상단 바" class="input full_input file_input" accept="image/*">
                        <?php if($w == 'u' && isset($file_new[0]['file']) && $file_new[0]['file']) { ?>
                            <span class="file_input_chk">
                                <input type="checkbox" id="bf_file_new_del0" class="magic-checkbox" name="bf_file_new_del[0]" value="1"> 
                                <label for="bf_file_new_del0">삭제</label>
                            </span>
                        <?php } ?>
                    </ul>
                </div>

                <?php if($w == 'u' && isset($file_new[0]['bf_source']) && $file_new[0]['bf_source']) { ?>
                    <div class="mt-10 color-888 font-12">등록파일 : <?php echo $file_new[0]['bf_source'] ?></div>
                <?php } ?>

                <!-- 파일 업로드 섹션 -->
                <div class="rb_inp_wrap rb_inp_wrap_gap">
                    <span class="sub_inp_tit font-R">메인이미지 (최대 <?php echo isset($board['bo_upload_count']) ? $board['bo_upload_count'] : 5 ?>개) <normal style="color:#d50000">※메인이미지 영역표시</normal></span>
                    
                    <div class="">
                        <?php
                        $wr_file = isset($wr_file) ? $wr_file : [];
                        $wf_cnt = count((array)$wr_file) + 1;
                        ?>
                        
                        <?php if (isset($is_file) && $is_file && $wf_cnt > 0): ?>
                            <?php
                            $new_files = [];
                            if (isset($w) && $w == 'u') {
                                if (isset($file) && is_array($file)) {
                                    foreach ($file as $k => $v) {
                                        if (empty($v['file'])) {
                                            continue;
                                        }
                                        $new_files[] = $v;
                                    }
                                }
                            } else {
                                $new_files = [];
                            }
                            ?>
                            <input type="file" name="bf_file[]" style="display:none;" />
                        <?php endif; ?>

                        <div class="divmb-10">
                            <input type="hidden" id="ajax_files" name="ajax_files" value="" />
                            <div style="position:relative;">
                                <input type="file" id="pic" name="pic" onchange="upload_start()" multiple="multiple" class="au_input" />
                                <div class="au_btn_search_file font-b">여러개 파일을 한번에 등록할 수 있어요.</div>
                            </div>

                            <div class="swiper-container swiper-wfile" style="overflow: inherit; padding-bottom:10px; font-size:11px;">
                                <div class="swiper-wrapper" id="file_list">
                                    <?php foreach($new_files as $v): ?>
                                        <div class="swiper-slide swiper-slide_lists">
                                            <div class="au_file_list">
                                                <div class="au_file_list_img_wrap">
                                                    <?php if(isset($v['view']) && $v['view']) { ?>
                                                        <?php echo $v['view']?>
                                                    <?php } else { ?>
                                                        <?php $pinfo = pathinfo($v['source']); ?>
                                                        <div class="w_pd">
                                                            <a href="<?php echo $v['href']?>" class="w_etc w_<?php echo $pinfo['extension']?>" download><?php echo $pinfo['extension']?></a>
                                                        </div>
                                                    <?php } ?>
                                                </div>
                                                <dd class="au_btn_del2 font-r">대표</dd>
                                                <div class="au_btn_del font-r" onclick="delete_file('<?php echo $v['file']?>',this)">삭제</div>
                                                <div class="cut" style="margin-top:5px;"><?php echo $v['source'] ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="au_progress">
                                <div id="son" class="font-R au_bars"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="nc_right">
                <!-- 제목 -->
                <span class="sub_inp_tit font-R">카드 제목</span>
                <div class="rb_inp_wrap">
                    <ul>
                        <input type="text" name="wr_subject" value="<?php echo isset($subject) ? $subject : ''; ?>" id="wr_subject" class="input required full_input" maxlength="255" placeholder="카드제목을 입력하세요.">
                    </ul>
                </div>

                <!-- 유튜브 ID -->
                <div class="rb_inp_wrap rb_inp_wrap_gap">
                    <ul>
                        <span class="sub_inp_tit font-R">동영상 URL</span>
                        <label class="helps">https://www.youtube.com/watch?v=<span class="main_color font-B">ZG4Dqp89sgY</span> 와 같이 유튜브 주소에서 강조된 부분만 입력하세요.<br>유튜브 ID 를 입력하시는 경우 해당 동영상의 썸네일을 가져오며, 본문에 동영상이 출력 됩니다.</label>
                        <input type="text" name="wr_11" value="<?php echo isset($write['wr_11']) ? $write['wr_11'] : ''; ?>" id="wr_11" class="input" placeholder="유튜브 ID를 입력하세요.">
                    </ul>
                </div>

                <?php $file_new = get_file_new($wr_id, '1'); ?>
                <div class="rb_inp_wrap rb_inp_wrap_gap">
                    <span class="sub_inp_tit font-R">로고 이미지</span>
                    <ul class="file_input_wrap">
                        <input type="file" name="bf_file_new[]" title="로고 이미지" class="input full_input file_input" accept="image/*">
                        <?php if($w == 'u' && isset($file_new[1]['file']) && $file_new[1]['file']) { ?>
                            <span class="file_input_chk">
                                <input type="checkbox" id="bf_file_new_del1" class="magic-checkbox" name="bf_file_new_del[1]" value="1"> 
                                <label for="bf_file_new_del1">삭제</label>
                            </span>
                        <?php } ?>
                    </ul>
                </div>

                <?php if($w == 'u' && isset($file_new[1]['bf_source']) && $file_new[1]['bf_source']) { ?>
                    <div class="mt-10 color-888 font-12">등록파일 : <?php echo $file_new[1]['bf_source'] ?></div>
                <?php } ?>
            </div>

            <div class="cb"></div>

            <div class="rb_inp_wrap_confirm">
                <button type="button" class="btn_submit btn font-B" onclick="showNext2()">다음단계</button>
                <?php if($w == 'u'){ ?>
                    <button type="submit" id="btn_submit" accesskey="s" class="btn_submit btn font-B" style="background-color: red!important;">수정완료</button>
                <?php } ?>
            </div>
        </div>

        <!-- STEP 2 -->
        <div id="step2" class="hiddens">
            <div class="nc_titles font-B">STEP 2. 개인정보 입력</div>
            <div class="nc_left">
                <div class="rb_inp_wrap">
                    <ul>
                        <input type="text" name="wr_1" value="<?php echo isset($write['wr_1']) ? $write['wr_1'] : ''; ?>" id="wr_1" class="input required full_input w100" placeholder="이름">
                    </ul>
                </div>

                <div class="rb_inp_wrap">
                    <ul>
                        <input type="text" name="wr_3" value="<?php echo isset($write['wr_3']) ? $write['wr_3'] : ''; ?>" id="wr_3" class="input full_input w100" placeholder="회사명/단체명">
                    </ul>
                </div>

                <div class="rb_inp_wrap">
                    <ul>
                        <input type="tel" name="wr_4" value="<?php echo isset($write['wr_4']) ? $write['wr_4'] : ''; ?>" id="wr_4" class="input full_input w100 required" placeholder="휴대전화 번호">
                    </ul>
                </div>

                <div class="rb_inp_wrap">
                    <ul>
                        <input type="tel" name="wr_12" value="<?php echo isset($write['wr_12']) ? $write['wr_12'] : ''; ?>" id="wr_12" class="input full_input w100" placeholder="일반전화">
                    </ul>
                </div>

                <div class="rb_inp_wrap">
                    <ul>
                        <input type="tel" name="wr_13" value="<?php echo isset($write['wr_13']) ? $write['wr_13'] : ''; ?>" id="wr_13" class="input full_input w100" placeholder="팩스번호">
                    </ul>
                </div>
            </div>

            <div class="nc_right">
                <div class="rb_inp_wrap">
                    <ul>
                        <input type="text" name="wr_2" value="<?php echo isset($write['wr_2']) ? $write['wr_2'] : ''; ?>" id="wr_2" class="input full_input w100" placeholder="직책(직함)">
                    </ul>
                </div>

                <div class="rb_inp_wrap">
                    <ul>
                        <input type="email" name="wr_5" value="<?php echo isset($write['wr_5']) ? $write['wr_5'] : ''; ?>" id="wr_5" class="input full_input w100 required" placeholder="이메일">
                    </ul>
                </div>

                <div class="rb_inp_wrap">
                    <?php for ($i=1; $is_link && $i<=1; $i++) { ?>
                        <ul>
                            <input type="text" name="wr_link<?php echo $i ?>" value="<?php if($w=="u" && isset($write['wr_link'.$i])){ echo $write['wr_link'.$i]; } ?>" id="wr_link<?php echo $i ?>" class="input full_input" placeholder="웹사이트 입력해주세요.">
                        </ul>
                    <?php } ?>
                </div>

                <div class="rb_inp_wrap nc_addr1">
                    <ul>
                        <input type="text" name="wr_7" value="<?php echo isset($write['wr_7']) ? $write['wr_7'] : ''; ?>" id="wr_7" class="input full_input w100" placeholder="주소">
                    </ul>
                </div>

                <div class="nc_addr">
                    <div class="rb_inp_wrap nc_addr2">
                        <button type="button" class="nc_addr_btn font-B" id="nc_addr_btn">주소찾기</button>
                    </div>
                    <div class="rb_inp_wrap nc_addr3">
                        <ul>
                            <input type="text" name="wr_8" value="<?php echo isset($write['wr_8']) ? $write['wr_8'] : ''; ?>" id="wr_8" class="input full_input w100" placeholder="나머지주소 (건물, 층/호수)">
                        </ul>
                    </div>
                </div>
            </div>
            <div class="cb"></div>

            <!-- 명함 이미지 등록 (모바일) -->
            <?php if(IS_MOBILE()) { ?>
                <div class="cd_cp">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span class="sub_inp_tit font-R mt-20">명함 이미지 등록</span>
                        <div class="toggle-section" style="display: flex; align-items: center; gap: 10px;">
                            <label class="custom-radio">
                                <input type="radio" name="toggleModule" value="off" id="radioOff" checked onclick="toggleBusinessCardModule(false)">
                                <span>미사용</span>
                            </label>
                            <label class="custom-radio">
                                <input type="radio" name="toggleModule" value="on" id="radioOn" onclick="toggleBusinessCardModule(true)">
                                <span>사용</span>
                            </label>
                        </div>
                    </div>

                    <div id="businessCardModule" style="display: none;">
                        <div class="video-container">
                            <video id="video" autoplay></video>
                        </div>
                        <div class="rb_inp_wrap_confirm" style="margin-top:0px;">
                            <button type="button" id="capture" class="btn_submit btn font-B w100 mb-20" style="border-top-left-radius:0px; border-top-right-radius:0px">
                                캡쳐하기
                            </button>
                        </div>
                        <canvas id="canvas"></canvas>
                    </div>
                </div>
                <br>
            <?php } ?>

            <input type="hidden" name="nc_file_name" id="filename" value="<?php echo isset($write['nc_file_name']) ? $write['nc_file_name'] : ''; ?>">

            <!-- 카카오맵 관련 -->
            <?php if (isset($config['cf_kakao_js_apikey']) && $config['cf_kakao_js_apikey']) { ?>
                <script type="text/javascript" src="//dapi.kakao.com/v2/maps/sdk.js?appkey=<?php echo $config['cf_kakao_js_apikey'] ?>&libraries=services"></script>

                <?php
                // 기본좌표
                $wr_9 = isset($write['wr_9']) && $write['wr_9'] ? $write['wr_9'] : 37.566500715093284;
                $wr_10 = isset($write['wr_10']) && $write['wr_10'] ? $write['wr_10'] : 126.9785391897507;
                ?>

                <input type="hidden" name="wr_9" value="<?php echo $wr_9; ?>" id="wr_9">
                <input type="hidden" name="wr_10" value="<?php echo $wr_10; ?>" id="wr_10">

                <script>
                    // 주소-좌표 변환 객체 생성
                    var geocoder = new daum.maps.services.Geocoder();

                    // 주소검색 API (주소 > 좌표변환처리)
                    $(function() {
                        $("#wr_7, #nc_addr_btn").on("click", function() {
                            new daum.Postcode({
                                oncomplete: function(data) {
                                    $("#wr_7").val(data.address);
                                    
                                    geocoder.addressSearch(data.address, function(results, status) {
                                        if (status === daum.maps.services.Status.OK) {
                                            var result = results[0];
                                            var coords = new daum.maps.LatLng(result.y, result.x);
                                            
                                            document.getElementById('wr_9').value = coords.getLat();
                                            document.getElementById('wr_10').value = coords.getLng();
                                        }
                                    });
                                }
                            }).open();
                        });
                    });
                </script>
            <?php } ?>

            <span class="sub_inp_tit font-R">프로필</span>
            <div class="rb_inp_wrap">
                <ul>
                    <textarea name="wr_6" id="wr_6" class="textarea" placeholder="프로필 (최대 255자)" style="min-height:100px;"><?php echo isset($write['wr_6']) ? $write['wr_6'] : ''; ?></textarea>
                </ul>
            </div>

            <?php if ($is_use_captcha) { ?>
                <div class="rb_inp_wrap">
                    <ul>
                        <?php echo $captcha_html ?>
                    </ul>
                </div>
            <?php } ?>

            <div class="rb_inp_wrap_confirm">
                <a href="javascript:showPrevious1()" class="btn_cancel btn font-B">이전단계</a>
                <button type="button" class="btn_submit btn font-B" onclick="showNext3()">다음단계</button>
                <?php if($w == 'u'){ ?>
                    <button type="submit" id="btn_submit" accesskey="s" class="btn_submit btn font-B" style="background-color: red!important;">수정완료</button>
                <?php } ?>
            </div>
        </div>

        <!-- STEP 3 -->
        <div id="step3" class="hiddens">
            <div class="nc_titles font-B">STEP 3. 템플릿 및 추가정보 입력</div>
            <div class="temp_wrap">
                <span class="sub_inp_tit font-R">템플릿을 선택하세요.</span>
                <input type="hidden" id="wr_20" name="wr_20" value="">
                <ul class="temp_wrap_gnb">
                    <div class="mb-20">
                        <label class="switch">
                            <input type="radio" name="temp_sel" value="상품/서비스" id="temp_sel1">
                            <span class="toggle_btn">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-shopping-bag"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                                </span>
                                <span class="tog_txt font-B">상품/서비스</span>
                            </span>
                        </label>
                        <label class="switch">
                            <input type="radio" name="temp_sel" value="갤러리" id="temp_sel2">
                            <span class="toggle_btn">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-image"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                </span>
                                <span class="tog_txt font-B">갤러리</span>
                            </span>
                        </label>
                        <label class="switch">
                            <input type="radio" name="temp_sel" value="예약" id="temp_sel3">
                            <span class="toggle_btn">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-calendar"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                </span>
                                <span class="tog_txt font-B">예약</span>
                            </span>
                        </label>
                        <label class="switch">
                            <input type="radio" name="temp_sel" value="쿠폰" id="temp_sel4">
                            <span class="toggle_btn">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-percent"><line x1="19" y1="5" x2="5" y2="19"></line><circle cx="6.5" cy="6.5" r="2.5"></circle><circle cx="17.5" cy="17.5" r="2.5"></circle></svg>
                                </span>
                                <span class="tog_txt font-B">쿠폰</span>
                            </span>
                        </label>
                        <label class="switch">
                            <input type="radio" name="temp_sel" value="파일" id="temp_sel5">
                            <span class="toggle_btn">
                                <span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-folder"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path></svg>
                                </span>
                                <span class="tog_txt font-B">파일</span>
                            </span>
                        </label>
                        <div class="cb"></div>
                    </div>
                </ul>

                <div class="temp_wrap_con">
                    <div id="temp_div1" class="tab-content"></div>
                    <div id="temp_div2" class="tab-content"></div>
                    <div id="temp_div3" class="tab-content"></div>
                    <div id="temp_div4" class="tab-content"></div>
                    <div id="temp_div5" class="tab-content"></div>
                </div>
            </div>

            <div class="rb_inp_wrap_confirm">
                <a href="javascript:showPrevious2()" class="btn_cancel btn font-B">이전단계</a>
                <button type="button" class="btn_submit btn font-B" onclick="showNext4()">다음단계</button>
                <?php if($w == 'u'){ ?>
                    <button type="submit" id="btn_submit" accesskey="s" class="btn_submit btn font-B" style="background-color: red!important;">수정완료</button>
                <?php } ?>
            </div>
        </div>

        <!-- STEP 4 -->
        <div id="step4" class="hiddens">
            <div class="nc_titles font-B">STEP 4. 기타 추가정보 입력 <normal style="color:#d50000">※필수 입력해주세요.</normal></div>

            <div class="rb_inp_wrap rb_inp_wrap_gap">
                <span class="sub_inp_tit font-R">기타 추가정보를 입력하세요.</span>
                <ul>
                    <div class="wr_content <?php echo isset($is_dhtml_editor) && $is_dhtml_editor ? $config['cf_editor'] : ''; ?>">
                        <?php if(isset($board['bo_write_min']) && $board['bo_write_min'] || isset($board['bo_write_max']) && $board['bo_write_max']) { ?>
                            <p id="char_count_desc" class="help_text">이 게시판은 최소 <strong><?php echo $board['bo_write_min']; ?></strong>글자 이상, 최대 <strong><?php echo $board['bo_write_max']; ?></strong>글자 이하까지 글을 쓰실 수 있습니다.</p>
                        <?php } ?>

                        <?php echo isset($editor_html) ? $editor_html : '<textarea name="wr_content" id="wr_content"></textarea>'; ?>

                        <?php if(isset($board['bo_write_min']) && $board['bo_write_min'] || isset($board['bo_write_max']) && $board['bo_write_max']) { ?>
                            <?php if(!isset($is_dhtml_editor) || !$is_dhtml_editor) { ?>
                                <div id="char_count_wrap"><span id="char_count"></span>글자</div>
                            <?php } ?>
                        <?php } ?>
                    </div>

                    <?php if(!isset($is_dhtml_editor) || !$is_dhtml_editor) { ?>
                        <style>
                            .wr_content>textarea {
                                overflow: hidden;
                                width: 100%;
                                min-height: 300px;
                            }
                        </style>
                        <script>
                            $(document).ready(function() {
                                $('.wr_content > textarea').on('input', function() {
                                    this.style.height = 'auto';
                                    this.style.height = (this.scrollHeight) + 'px';
                                    this.style.minHeight = '300px';
                                });
                            });
                        </script>
                    <?php } ?>
                </ul>
            </div>

            <div class="rb_inp_wrap_confirm">
                <a href="javascript:showPrevious3()" class="btn_cancel btn font-B">이전단계</a>
                <a href="<?php echo G5_URL; ?>" class="btn_cancel btn font-B">취소</a>
                <button type="submit" id="btn_submit" accesskey="s" class="btn_submit btn font-B">저장</button>
            </div>
        </div>

    </form>
</div>

<!-- JavaScript 섹션 -->
<script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
<script>
// 카테고리 모달 관련 스크립트
document.addEventListener("DOMContentLoaded", function () {
    const openModalButton = document.getElementById("openCategoryModal");
    const categoryModal = document.getElementById("categoryModal");
    const categoryItems = document.querySelectorAll(".category_item");
    const categoryInput = document.getElementById("ca_name");
    const selectDoneButton = document.getElementById("categorySelectDone");

    if (openModalButton) {
        openModalButton.addEventListener("click", function () {
            categoryModal.style.display = "flex";
            const currentCategory = categoryInput.value;
            categoryItems.forEach(item => {
                item.classList.toggle("selected", item.dataset.category === currentCategory);
            });
        });
    }

    categoryItems.forEach(function (item) {
        item.addEventListener("click", function () {
            categoryItems.forEach(i => i.classList.remove("selected"));
            item.classList.add("selected");
            categoryInput.value = item.dataset.category;
        });
    });

    if (selectDoneButton) {
        selectDoneButton.addEventListener("click", function () {
            if (!categoryInput.value) {
                alert("카테고리를 선택해주세요.");
                return;
            }
            openModalButton.textContent = categoryInput.value;
            openModalButton.classList.add("selected");
            categoryModal.style.display = "none";
        });
    }

    window.addEventListener("click", function (event) {
        if (event.target === categoryModal) {
            categoryModal.style.display = "none";
        }
    });
});

// 명함 이미지 등록 관련 스크립트 (모바일)
<?php if(IS_MOBILE()) { ?>
function toggleBusinessCardModule(isVisible) {
    const module = document.getElementById('businessCardModule');
    module.style.display = isVisible ? 'block' : 'none';
    
    if (isVisible) {
        // 카메라 초기화
        navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: { exact: "environment" },
                width: { ideal: 2560 },
                height: { ideal: 1450 }
            }
        })
        .then(function(stream) {
            const video = document.getElementById('video');
            video.srcObject = stream;
            video.play();
        })
        .catch(function (error) {
            console.error("카메라 접근이 실패하였습니다.", error);
        });
    }
}

let capturedImage = null;

document.getElementById('capture')?.addEventListener('click', function () {
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const context = canvas.getContext('2d');

    const videoWidth = video.videoWidth;
    const videoHeight = video.videoHeight;

    const cardWidth = 500;
    const cardHeight = 300;

    const videoAspect = videoWidth / videoHeight;
    const canvasAspect = cardWidth / cardHeight;

    let sx, sy, sWidth, sHeight;

    if (videoAspect > canvasAspect) {
        sHeight = videoHeight;
        sWidth = videoHeight * canvasAspect;
        sx = (videoWidth - sWidth) / 2;
        sy = 0;
    } else {
        sWidth = videoWidth;
        sHeight = videoWidth / canvasAspect;
        sx = 0;
        sy = 0;
    }

    canvas.width = cardWidth * 4;
    canvas.height = cardHeight * 4;

    context.drawImage(video, sx, sy, sWidth, sHeight, 0, 0, canvas.width, canvas.height);

    capturedImage = canvas.toDataURL('image/jpeg', 1.0);
    uploadImage(capturedImage);
});

function uploadImage(imageData) {
    let xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo $board_skin_url ?>/ajax.nc_upload.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);
                document.getElementById('filename').value = response.filename;
            } else {
                console.error("이미지 업로드 실패");
            }
        }
    };

    xhr.send('image=' + encodeURIComponent(imageData));
}
<?php } ?>

// Swiper 초기화
var swiper_file = new Swiper('.swiper-wfile', {
    slidesPerColumnFill: 'row',
    slidesPerView: 5,
    slidesPerColumn: 1,
    spaceBetween: 7,
    touchRatio: 1,
    breakpoints: {
        1024: {
            slidesPerView: 5,
            slidesPerColumn: 1,
            touchRatio: 1,
        },
        10: {
            slidesPerView: 4,
            slidesPerColumn: 1,
            touchRatio: 1,
        }
    }
});

// 파일 업로드 관련 스크립트
var ajax_files = {
    'files': <?php echo empty($new_files) ? '[]' : json_encode($new_files)?>,
    'del': []
};

function upload_start() {
    var cnts = $("#file_list .swiper-slide_lists").length;
    var maxUploadCount = 5;
    var picFileList = $("#pic").get(0).files;

    if (cnts + picFileList.length > maxUploadCount) {
        alert("첨부파일은 " + maxUploadCount + "개 이하만 업로드 가능합니다.");
        return false;
    }

    var formData = new FormData();
    formData.append("act_type", "upload");
    formData.append("write_table", "<?php echo isset($write_table) ? $write_table : ''; ?>");
    formData.append("bo_table", "<?php echo $bo_table ?>");
    formData.append("wr_id", "<?php echo $wr_id ?>");
    
    for (var i = 0; i < picFileList.length; i++) {
        formData.append("file[]", picFileList[i]);
    }

    var xhr = new XMLHttpRequest();
    xhr.upload.addEventListener("progress", onprogress, false);
    xhr.addEventListener("error", upload_failed, false);
    xhr.addEventListener("load", upload_success, false);
    xhr.open("POST", "<?php echo $board_skin_url ?>/ajax.upload.php");
    xhr.send(formData);
}

function onprogress(evt) {
    var loaded = evt.loaded;
    var tot = evt.total;
    var per = Math.floor(100 * loaded / tot);
    $("#son").parent().css("display", "block");
    $("#son").css("width", per + "%");
    if(per > 99) {
        $("#son").parent().css("display", "none");
    }
}

function upload_failed(evt) {
    alert("업로드에 실패하였습니다.");
}

function upload_success(evt) {
    var res = JSON.parse(evt.target.response);
    if (res.res == 'true') {
        for (var i = 0; i < res.list.length; i++) {
            var str = '<div class="swiper-slide swiper-slide_lists">';
            str += '<div class="au_file_list">';
            str += '<div class="au_file_list_img_wrap">';
            str += '' + res.list[i].view + '';
            str += '</div>';
            str += '</div>';
            str += '<div class="au_btn_del" onclick="delete_file(\'' + res.list[i].bf_file + '\',this)">삭제</div>';
            str += '<div class="cut" style="margin-top:5px;">' + res.list[i].bf_source + '</div>';
            str += '</div>';

            $("#file_list").append(str);
            ajax_files.files.push(res.list[i]);
            swiper_file.update();
        }
        $("#ajax_files").val(JSON.stringify(ajax_files));
    } else {
        alert(res.msg);
    }
}

function delete_file(file, obj) {
    var formData = new FormData();
    formData.append("act_type", "delete");
    formData.append("write_table", "<?php echo isset($write_table) ? $write_table : ''; ?>");
    formData.append("bo_table", "<?php echo $bo_table ?>");
    formData.append("wr_id", "<?php echo $wr_id ?>");
    formData.append("bf_file", file);
    
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "<?php echo $board_skin_url ?>/ajax.upload.php");
    xhr.send(formData);
    
    $(obj).parent().parent().remove();
    ajax_files.del.push(file);
    $("#ajax_files").val(JSON.stringify(ajax_files));
}

// 템플릿 관련 스크립트 - 수정된 부분
var clickedOrder = <?php echo $json_wr_20_array; ?>;

$(document).ready(function () {
    <?php if ($w == "u") { ?>
        renderContent();
    <?php } ?>
    
    $('input[name="temp_sel"]').change(function () {
        var selectedId = $(this).attr('id');
        if (!clickedOrder.includes(selectedId)) {
            clickedOrder.push(selectedId);
        }
        renderContent();
        var joinedString = clickedOrder.join('|');
        $('#wr_20').val(joinedString);
    });

    function loadFile(radioId, targetDiv) {
        var fileMapping = {
            temp_sel1: '<?php echo $board_skin_url ?>/ajax.load_temp1.php',  // 상품/서비스 템플릿 추가
            temp_sel2: '<?php echo $board_skin_url ?>/ajax.load_temp2.php',
            temp_sel3: '<?php echo $board_skin_url ?>/ajax.load_temp3.php',
            temp_sel4: '<?php echo $board_skin_url ?>/ajax.load_temp4.php',
            temp_sel5: '<?php echo $board_skin_url ?>/ajax.load_temp5.php',
        };

        var url = fileMapping[radioId];
        if (!url) return;

        $.ajax({
            url: url,
            type: 'POST',
            data: {
                bo_table: '<?php echo $bo_table; ?>',
                wr_id: '<?php echo $wr_id; ?>',
            },
            success: function (response) {
                $(targetDiv).html(response);
            },
            error: function () {
                alert('파일을 로드하는 데 실패했습니다.');
            }
        });
    }

    function renderContent() {
        clickedOrder.forEach((id, index) => {
            var targetDiv = '#temp_div' + (index + 1);
            loadFile(id, targetDiv);
        });
        var joinedString = clickedOrder.join('|');
        $('#wr_20').val(joinedString);
    }

    $(document).on("click", ".move_up", function () {
        var parentDivId = $(this).closest('[id^="temp_div"]').attr('id');
        var lastNumber = parseInt(parentDivId.replace('temp_div', ''), 10);
        var targetNumber = lastNumber - 1;

        if (targetNumber > 0) {
            swap(clickedOrder, targetNumber - 1, targetNumber);
        }
    });

    $(document).on("click", ".move_down", function () {
        var parentDivId = $(this).closest('[id^="temp_div"]').attr('id');
        var lastNumber = parseInt(parentDivId.replace('temp_div', ''), 10);
        var targetNumber = lastNumber - 1;

        if (targetNumber < clickedOrder.length - 1) {
            swap(clickedOrder, targetNumber, targetNumber + 1);
        }
    });

    function swap(array, index1, index2) {
        var temp = array[index1];
        array[index1] = array[index2];
        array[index2] = temp;
        renderContent();
        var joinedString = clickedOrder.join('|');
        $('#wr_20').val(joinedString);
    }
});

// Step 네비게이션 함수
function showNext2() {
    document.getElementById('step1').classList.add('hiddens');
    document.getElementById('step2').classList.remove('hiddens');
    document.getElementById('step3').classList.add('hiddens');
    document.getElementById('step4').classList.add('hiddens');
    $('html, body').scrollTop(0);
}

function showNext3() {
    document.getElementById('step1').classList.add('hiddens');
    document.getElementById('step2').classList.add('hiddens');
    document.getElementById('step3').classList.remove('hiddens');
    document.getElementById('step4').classList.add('hiddens');
    $('html, body').scrollTop(0);
    
    var tempWrapGnb = $('.temp_wrap_gnb');
    var tempWrapGnbOffsetTop = tempWrapGnb.offset().top - 65;

    document.addEventListener('scroll', function () {
        var scrollTop = document.documentElement.scrollTop;
        if (scrollTop > tempWrapGnbOffsetTop) {
            tempWrapGnb.addClass('fixed');
        } else {
            tempWrapGnb.removeClass('fixed');
        }
    });
}

function showNext4() {
    document.getElementById('step1').classList.add('hiddens');
    document.getElementById('step2').classList.add('hiddens');
    document.getElementById('step3').classList.add('hiddens');
    document.getElementById('step4').classList.remove('hiddens');
    $('html, body').scrollTop(0);
}

function showPrevious1() {
    document.getElementById('step1').classList.remove('hiddens');
    document.getElementById('step2').classList.add('hiddens');
    document.getElementById('step3').classList.add('hiddens');
    document.getElementById('step4').classList.add('hiddens');
    $('html, body').scrollTop(0);
}

function showPrevious2() {
    document.getElementById('step1').classList.add('hiddens');
    document.getElementById('step2').classList.remove('hiddens');
    document.getElementById('step3').classList.add('hiddens');
    document.getElementById('step4').classList.add('hiddens');
    $('html, body').scrollTop(0);
}

function showPrevious3() {
    document.getElementById('step1').classList.add('hiddens');
    document.getElementById('step2').classList.add('hiddens');
    document.getElementById('step3').classList.remove('hiddens');
    document.getElementById('step4').classList.add('hiddens');
    $('html, body').scrollTop(0);
}

// 폼 제출 검증
function fwrite_submit(f) {
    <?php echo isset($editor_js) ? $editor_js : ''; ?>

    var subject = "";
    var content = "";
    $.ajax({
        url: g5_bbs_url + "/ajax.filter.php",
        type: "POST",
        data: {
            "subject": f.wr_subject.value,
            "content": f.wr_content.value
        },
        dataType: "json",
        async: false,
        cache: false,
        success: function(data, textStatus) {
            subject = data.subject;
            content = data.content;
        }
    });

    if (subject) {
        alert("제목에 금지단어('" + subject + "')가 포함되어있습니다");
        f.wr_subject.focus();
        return false;
    }

    if (content) {
        alert("내용에 금지단어('" + content + "')가 포함되어있습니다");
        if (typeof(ed_wr_content) != "undefined")
            ed_wr_content.returnFalse();
        else
            f.wr_content.focus();
        return false;
    }

    if (document.getElementById("char_count")) {
        var char_min = parseInt(<?php echo isset($board['bo_write_min']) ? $board['bo_write_min'] : 0; ?>);
        var char_max = parseInt(<?php echo isset($board['bo_write_max']) ? $board['bo_write_max'] : 0; ?>);
        
        if (char_min > 0 || char_max > 0) {
            var cnt = parseInt(check_byte("wr_content", "char_count"));
            if (char_min > 0 && char_min > cnt) {
                alert("내용은 " + char_min + "글자 이상 쓰셔야 합니다.");
                return false;
            } else if (char_max > 0 && char_max < cnt) {
                alert("내용은 " + char_max + "글자 이하로 쓰셔야 합니다.");
                return false;
            }
        }
    }

    <?php echo isset($captcha_js) ? $captcha_js : ''; ?>

    document.getElementById("btn_submit").disabled = "disabled";
    return true;
}

// 글자수 체크
<?php if(isset($board['bo_write_min']) && $board['bo_write_min'] || isset($board['bo_write_max']) && $board['bo_write_max']) { ?>
function check_byte(id, output) {
    var content = document.getElementById(id).value;
    var bytelen = 0;
    
    for (var i = 0; i < content.length; i++) {
        var c = content.charAt(i);
        bytelen++;
    }
    
    if (output) {
        document.getElementById(output).innerHTML = bytelen;
    }
    
    return bytelen;
}

$(function() {
    $("#wr_content").on("keyup", function() {
        check_byte("wr_content", "char_count");
    });
});
<?php } ?>
</script>