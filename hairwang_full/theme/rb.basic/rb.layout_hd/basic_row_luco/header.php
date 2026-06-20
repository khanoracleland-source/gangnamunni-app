<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 레이아웃 폴더내 style.css 파일
add_stylesheet('<link rel="stylesheet" href="'.G5_THEME_URL.'/rb.layout_hd/'.$rb_core['layout_hd'].'/style.css?ver='.G5_TIME_YMDHIS.'">', 0);
if (!isset($member['mb_id'])) { // 로그인하지 않은 경우
    alert("회원만 이용하실 수 있습니다.", "/bbs/login.php");
    exit;
}
?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

<!-- 헤더 { -->
<header id="header">
    <!-- GNB { -->
    <div class="gnb_wrap">
        <div class="inner">
            <!-- PC 로고 { -->
            <ul class="logo_wrap pc_logo">
                <li>
                    <a href="<?php echo G5_URL; ?>/gangnamunni/" alt="<?php echo $config['cf_title']; ?>">
                        <?php if (!empty($rb_builder['bu_logo_pc']) && !empty($rb_builder['bu_logo_pc_w'])) { ?>
                            <img src="<?php echo G5_URL ?>/data/logos/pc?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>">
                        <?php } else { ?>
                            <img src="<?php echo G5_THEME_URL ?>/rb.img/logos/pc.png?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>">
                        <?php } ?>
                    </a>
                </li>
            </ul>
            <!-- } -->
            
            <!-- 모바일 헤더 상단 영역 -->
            <div class="mobile_header_top">
                <!-- 모바일 로고 { -->
                <ul class="logo_wrap">
                    <li>
                        <a href="<?php echo G5_URL; ?>/gangnamunni/" alt="<?php echo $config['cf_title']; ?>">
                            <picture id="logo_img">
                                <?php if (!empty($rb_builder['bu_logo_mo']) && !empty($rb_builder['bu_logo_mo_w'])) { ?>
                                    <source id="sourceSmall" srcset="<?php echo G5_URL ?>/data/logos/mo?ver=<?php echo G5_SERVER_TIME ?>" media="(max-width: 1024px)">
                                <?php } else { ?>
                                    <source id="sourceSmall" srcset="<?php echo G5_THEME_URL ?>/rb.img/logos/mo.png?ver=<?php echo G5_SERVER_TIME ?>" media="(max-width: 1024px)">
                                <?php } ?>
                                
                                <?php if (!empty($rb_builder['bu_logo_pc']) && !empty($rb_builder['bu_logo_pc_w'])) { ?>
                                    <source id="sourceLarge" srcset="<?php echo G5_URL ?>/data/logos/pc?ver=<?php echo G5_SERVER_TIME ?>" media="(min-width: 1025px)">
                                <?php } else { ?>
                                    <source id="sourceLarge" srcset="<?php echo G5_THEME_URL ?>/rb.img/logos/pc.png?ver=<?php echo G5_SERVER_TIME ?>" media="(max-width: 1024px)">
                                <?php } ?>
                                
                                <?php if (!empty($rb_builder['bu_logo_pc']) && !empty($rb_builder['bu_logo_pc_w'])) { ?>
                                    <img id="fallbackImage" src="<?php echo G5_URL ?>/data/logos/pc?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>" class="responsive-image">
                                <?php } else { ?>
                                    <img id="fallbackImage" src="<?php echo G5_THEME_URL ?>/rb.img/logos/pc.png?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>" class="responsive-image">
                                <?php } ?>
                            </picture>
                        </a>
                    </li>
                </ul>
                <!-- } -->

                <!-- 모바일 오른쪽 버튼들 { -->
                <div class="mobile_right_buttons">
                    <!-- 모바일 검색 버튼 -->
                    <button type="button" alt="검색" id="mobile_search_btn" class="mobile_search_btn">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M8.49928 1.91687e-08C7.14387 0.000115492 5.80814 0.324364 4.60353 0.945694C3.39893 1.56702 2.36037 2.46742 1.57451 3.57175C0.788656 4.67609 0.278287 5.95235 0.0859852 7.29404C-0.106316 8.63574 0.0250263 10.004 0.469055 11.2846C0.913084 12.5652 1.65692 13.7211 2.63851 14.6557C3.6201 15.5904 4.81098 16.2768 6.11179 16.6576C7.4126 17.0384 8.78562 17.1026 10.1163 16.8449C11.447 16.5872 12.6967 16.015 13.7613 15.176L17.4133 18.828C17.6019 19.0102 17.8545 19.111 18.1167 19.1087C18.3789 19.1064 18.6297 19.0012 18.8151 18.8158C19.0005 18.6304 19.1057 18.3796 19.108 18.1174C19.1102 17.8552 19.0094 17.6026 18.8273 17.414L15.1753 13.762C16.1633 12.5086 16.7784 11.0024 16.9504 9.41573C17.1223 7.82905 16.8441 6.22602 16.1475 4.79009C15.4509 3.35417 14.3642 2.14336 13.0116 1.29623C11.659 0.449106 10.0952 -0.000107143 8.49928 1.91687e-08ZM1.99928 8.5C1.99928 6.77609 2.6841 5.12279 3.90308 3.90381C5.12207 2.68482 6.77537 2 8.49928 2C10.2232 2 11.8765 2.68482 13.0955 3.90381C14.3145 5.12279 14.9993 6.77609 14.9993 8.5C14.9993 10.2239 14.3145 11.8772 13.0955 13.0962C11.8765 14.3152 10.2232 15 8.49928 15C6.77537 15 5.12207 14.3152 3.90308 13.0962C2.6841 11.8772 1.99928 10.2239 1.99928 8.5Z" fill="#7A4EFE"/>
                        </svg>
                    </button>
                    
                    <!-- 햄버거 메뉴 -->
                    <ul class="tog_wrap mobile">
                        <li>
                            <button type="button" alt="메뉴열기" id="tog_gnb_mobile">
                                <svg width="18" height="16" viewBox="0 0 18 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M17 14C17.2549 14.0003 17.5 14.0979 17.6854 14.2728C17.8707 14.4478 17.9822 14.687 17.9972 14.9414C18.0121 15.1958 17.9293 15.4464 17.7657 15.6418C17.6021 15.8373 17.3701 15.9629 17.117 15.993L17 16H1C0.74512 15.9997 0.499968 15.9021 0.314632 15.7272C0.129296 15.5522 0.017765 15.313 0.00282788 15.0586C-0.0121092 14.8042 0.0706746 14.5536 0.234265 14.3582C0.397855 14.1627 0.629904 14.0371 0.883 14.007L1 14H17ZM17 7C17.2652 7 17.5196 7.10536 17.7071 7.29289C17.8946 7.48043 18 7.73478 18 8C18 8.26522 17.8946 8.51957 17.7071 8.70711C17.5196 8.89464 17.2652 9 17 9H1C0.734784 9 0.48043 8.89464 0.292893 8.70711C0.105357 8.51957 0 8.26522 0 8C0 7.73478 0.105357 7.48043 0.292893 7.29289C0.48043 7.10536 0.734784 7 1 7H17ZM17 0C17.2652 0 17.5196 0.105357 17.7071 0.292893C17.8946 0.48043 18 0.734784 18 1C18 1.26522 17.8946 1.51957 17.7071 1.70711C17.5196 1.89464 17.2652 2 17 2H1C0.734784 2 0.48043 1.89464 0.292893 1.70711C0.105357 1.51957 0 1.26522 0 1C0 0.734784 0.105357 0.48043 0.292893 0.292893C0.48043 0.105357 0.734784 0 1 0H17Z" fill="#09244B"/>
                                </svg>
                            </button>
                        </li>
                    </ul>
                </div>
                <!-- } -->
            </div>

            <!-- PC 검색 영역 { -->
            <div class="search_top_wrap pc_only">
                <form name="fsearchbox" method="get" action="<?php echo G5_BBS_URL ?>/search.php" onsubmit="return fsearchbox_submit(this);">
                    <div class="search_top_wrap_inner">
                    <input type="hidden" name="sfl" value="wr_subject||wr_content">
                    <input type="hidden" name="sop" value="and">
                    <input type="text" value="" name="stx" class="font-B" placeholder="어떤 서비스가 필요하세요?" maxlength="20">
                    <button type="submit">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M8.49928 1.91687e-08C7.14387 0.000115492 5.80814 0.324364 4.60353 0.945694C3.39893 1.56702 2.36037 2.46742 1.57451 3.57175C0.788656 4.67609 0.278287 5.95235 0.0859852 7.29404C-0.106316 8.63574 0.0250263 10.004 0.469055 11.2846C0.913084 12.5652 1.65692 13.7211 2.63851 14.6557C3.6201 15.5904 4.81098 16.2768 6.11179 16.6576C7.4126 17.0384 8.78562 17.1026 10.1163 16.8449C11.447 16.5872 12.6967 16.015 13.7613 15.176L17.4133 18.828C17.6019 19.0102 17.8545 19.111 18.1167 19.1087C18.3789 19.1064 18.6297 19.0012 18.8151 18.8158C19.0005 18.6304 19.1057 18.3796 19.108 18.1174C19.1102 17.8552 19.0094 17.6026 18.8273 17.414L15.1753 13.762C16.1633 12.5086 16.7784 11.0024 16.9504 9.41573C17.1223 7.82905 16.8441 6.22602 16.1475 4.79009C15.4509 3.35417 14.3642 2.14336 13.0116 1.29623C11.659 0.449106 10.0952 -0.000107143 8.49928 1.91687e-08ZM1.99928 8.5C1.99928 6.77609 2.6841 5.12279 3.90308 3.90381C5.12207 2.68482 6.77537 2 8.49928 2C10.2232 2 11.8765 2.68482 13.0955 3.90381C14.3145 5.12279 14.9993 6.77609 14.9993 8.5C14.9993 10.2239 14.3145 11.8772 13.0955 13.0962C11.8765 14.3152 10.2232 15 8.49928 15C6.77537 15 5.12207 14.3152 3.90308 13.0962C2.6841 11.8772 1.99928 10.2239 1.99928 8.5Z" fill="#09244B"/>
                        </svg>
                    </button>
                    </div>
                </form>
            </div>
            <!-- } -->

<!-- PC 메뉴 영역 { -->
<div class="hd__mnav__wr <?php echo defined('_INDEX_') ? 'main-page' : ''; ?>">
    <ul class="hd__mnav__ul clearfix center__style">
        <li class="hd__mnav__list">
            <div class="sub__menu sub__menu01">
                <ul>
					<li><a href="/anonymous">비밀톡</a></li>
					<li><a href="/bbs/board.php?bo_table=free">헤어톡</a></li>					
					<li><a href="/bbs/board.php?bo_table=recruit">채용정보</a></li>					
					<li><a href="/bbs/board.php?bo_table=card">포트폴리오</a></li>		
                    <li><a href="/second_hand">중고거래</a></li>
                    <li><a href="/activity">이벤트</a></li>		
					<li><a href="/notice">공지사항</a></li>	
					<li><a href="/rb/attend.php">출석부</a></li>	
            </div>
        </li>
    </ul>
</div>
<!-- } -->

            <!-- PC 우측 메뉴 { -->
            <div class="hd__right__nav pc_only">
                <!-- 헤더 위젯 직접 호출 { -->
                <div class="header_point_rank_widget">
                    <?php
                    // 포인트 랭킹 위젯 직접 호출
                    if(file_exists(G5_PATH.'/rb/rb.widget/rb.point_rank/widget.php')) {
                        include_once(G5_PATH.'/rb/rb.widget/rb.point_rank/widget.php');
                    }
                    ?>
                </div>
                <!-- } -->
                
                <!-- 퀵메뉴 { -->
                <ul class="snb_wrap">
                    <li class="qm_wrap">
                        <?php if($is_member) { ?>
                        <a href="<?php echo G5_BBS_URL ?>/memo.php" alt="쪽지" onclick="win_memo(this.href); return false;" title="쪽지">
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M15.2715 2.33675L2.67946 6.88975L6.87446 9.31775L10.5735 5.61775C10.7611 5.43024 11.0155 5.32495 11.2808 5.32505C11.5461 5.32514 11.8005 5.43061 11.988 5.61825C12.1755 5.80589 12.2808 6.06033 12.2807 6.3256C12.2806 6.59087 12.1751 6.84524 11.9875 7.03275L8.28746 10.7328L10.7175 14.9268L15.2715 2.33675ZM15.5945 0.0927503C16.7895 -0.34025 17.9475 0.81775 17.5145 2.01275L12.2325 16.6178C11.7985 17.8158 10.1625 17.9618 9.52346 16.8587L6.30646 11.3008L0.748462 8.08375C-0.354538 7.44475 -0.208537 5.80875 0.989463 5.37475L15.5945 0.0927503Z" fill="#09244B"/>
                            </svg>
                            <?php if($memo_not_read > 0) { ?>
                            <span class="font-H"><?php echo $memo_not_read ?></span>
                            <?php } ?>
                        </a>
                        <?php } ?>
                        <div class="cb"></div>
                    </li>
                    <li class="member_info_wrap">
                        <?php if($is_member) { ?>
                        <a href="<?php echo G5_BBS_URL ?>/member_confirm.php?url=<?php echo G5_BBS_URL ?>/register_form.php" class="font-B notranslate"><?php echo $member['mb_nick'] ?></a>　<a href="<?php echo G5_BBS_URL; ?>/point.php" target="_blank" class="win_point"><span class="font-H"><?php echo number_format($member['mb_point']); ?> P</span></a> 
                        <?php } ?>
                    </li>
                    <li class="my_btn_wrap">
                        <?php if($is_member) { ?>
                            <button type="button" alt="로그아웃" class="btn_round" onclick="location.href='<?php echo G5_BBS_URL ?>/logout.php';">로그아웃</button>
                            <button type="button" alt="마이페이지" class="btn_round arr_bg font-B" onclick="location.href='<?php echo G5_URL; ?>/rb/home.php?mb_id=<?php echo $member['mb_id']; ?>';">My</button>
                        <?php } else { ?>
                            <button type="button" alt="로그인" class="btn_round"  onclick="location.href='<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo $_SERVER['REQUEST_URI']; ?>';">로그인</button>
                            <button type="button" alt="회원가입" class="btn_round arr_bg font-B" onclick="location.href='<?php echo G5_BBS_URL ?>/register.php';">회원가입</button>
                        <?php } ?>
                    </li>
                    <div class="cb"></div>
                </ul>
                <!-- } -->
                
                <div class="mobile_cb"></div>

                <button type="button" alt="검색" id="search_top_btn" class="ser_open">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M8.49928 1.91687e-08C7.14387 0.000115492 5.80814 0.324364 4.60353 0.945694C3.39893 1.56702 2.36037 2.46742 1.57451 3.57175C0.788656 4.67609 0.278287 5.95235 0.0859852 7.29404C-0.106316 8.63574 0.0250263 10.004 0.469055 11.2846C0.913084 12.5652 1.65692 13.7211 2.63851 14.6557C3.6201 15.5904 4.81098 16.2768 6.11179 16.6576C7.4126 17.0384 8.78562 17.1026 10.1163 16.8449C11.447 16.5872 12.6967 16.015 13.7613 15.176L17.4133 18.828C17.6019 19.0102 17.8545 19.111 18.1167 19.1087C18.3789 19.1064 18.6297 19.0012 18.8151 18.8158C19.0005 18.6304 19.1057 18.3796 19.108 18.1174C19.1102 17.8552 19.0094 17.6026 18.8273 17.414L15.1753 13.762C16.1633 12.5086 16.7784 11.0024 16.9504 9.41573C17.1223 7.82905 16.8441 6.22602 16.1475 4.79009C15.4509 3.35417 14.3642 2.14336 13.0116 1.29623C11.659 0.449106 10.0952 -0.000107143 8.49928 1.91687e-08ZM1.99928 8.5C1.99928 6.77609 2.6841 5.12279 3.90308 3.90381C5.12207 2.68482 6.77537 2 8.49928 2C10.2232 2 11.8765 2.68482 13.0955 3.90381C14.3145 5.12279 14.9993 6.77609 14.9993 8.5C14.9993 10.2239 14.3145 11.8772 13.0955 13.0962C11.8765 14.3152 10.2232 15 8.49928 15C6.77537 15 5.12207 14.3152 3.90308 13.0962C2.6841 11.8772 1.99928 10.2239 1.99928 8.5Z" fill="#09244B"></path>
                        </svg>
                </button>
                
                <!-- PC 검색 { -->
                <div id="search_box_wrap">
                    <form name="fsearchbox" method="get" action="<?php echo G5_BBS_URL ?>/search.php" onsubmit="return fsearchbox_submit(this);">
                        <ul>
                            <input type="text" name="stx" maxlength="20" class="w100 font-B" id="ser_inp_fc" placeholder="통합검색">
                            <button type="submit" alt="검색" class="ser_inner_btn">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M8.49928 1.91687e-08C7.14387 0.000115492 5.80814 0.324364 4.60353 0.945694C3.39893 1.56702 2.36037 2.46742 1.57451 3.57175C0.788656 4.67609 0.278287 5.95235 0.0859852 7.29404C-0.106316 8.63574 0.0250263 10.004 0.469055 11.2846C0.913084 12.5652 1.65692 13.7211 2.63851 14.6557C3.6201 15.5904 4.81098 16.2768 6.11179 16.6576C7.4126 17.0384 8.78562 17.1026 10.1163 16.8449C11.447 16.5872 12.6967 16.015 13.7613 15.176L17.4133 18.828C17.6019 19.0102 17.8545 19.111 18.1167 19.1087C18.3789 19.1064 18.6297 19.0012 18.8151 18.8158C19.0005 18.6304 19.1057 18.3796 19.108 18.1174C19.1102 17.8552 19.0094 17.6026 18.8273 17.414L15.1753 13.762C16.1633 12.5086 16.7784 11.0024 16.9504 9.41573C17.1223 7.82905 16.8441 6.22602 16.1475 4.79009C15.4509 3.35417 14.3642 2.14336 13.0116 1.29623C11.659 0.449106 10.0952 -0.000107143 8.49928 1.91687e-08ZM1.99928 8.5C1.99928 6.77609 2.6841 5.12279 3.90308 3.90381C5.12207 2.68482 6.77537 2 8.49928 2C10.2232 2 11.8765 2.68482 13.0955 3.90381C14.3145 5.12279 14.9993 6.77609 14.9993 8.5C14.9993 10.2239 14.3145 11.8772 13.0955 13.0962C11.8765 14.3152 10.2232 15 8.49928 15C6.77537 15 5.12207 14.3152 3.90308 13.0962C2.6841 11.8772 1.99928 10.2239 1.99928 8.5Z" fill="#09244B"/>
                                </svg>
                            </button>
                        </ul>
                        <div class="close_btn_wr">
                            <a href="#" class="search_box_close_btn">취소</a>
                        </div>
                    </form>
                </div>
                <!-- } -->
            </div>
            <!-- } -->
            
            <div class="cb"></div>
        </div>
    </div>
    <!-- } -->
    
    <!-- 모바일 검색바 { -->
    <div id="mobile_search_bar" class="mobile_search_area">
        <div class="search_top_wrap">
            <form name="mobile_fsearchbox" method="get" action="<?php echo G5_BBS_URL ?>/search.php" onsubmit="return fsearchbox_submit(this);">
                <div class="search_top_wrap_inner">
                    <input type="hidden" name="sfl" value="wr_subject||wr_content">
                    <input type="hidden" name="sop" value="and">
                    <input type="text" name="stx" maxlength="20" class="font-B" placeholder="검색어를 입력하세요" id="mobile_search_input">
                    <button type="submit">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M8.49928 1.91687e-08C7.14387 0.000115492 5.80814 0.324364 4.60353 0.945694C3.39893 1.56702 2.36037 2.46742 1.57451 3.57175C0.788656 4.67609 0.278287 5.95235 0.0859852 7.29404C-0.106316 8.63574 0.0250263 10.004 0.469055 11.2846C0.913084 12.5652 1.65692 13.7211 2.63851 14.6557C3.6201 15.5904 4.81098 16.2768 6.11179 16.6576C7.4126 17.0384 8.78562 17.1026 10.1163 16.8449C11.447 16.5872 12.6967 16.015 13.7613 15.176L17.4133 18.828C17.6019 19.0102 17.8545 19.111 18.1167 19.1087C18.3789 19.1064 18.6297 19.0012 18.8151 18.8158C19.0005 18.6304 19.1057 18.3796 19.108 18.1174C19.1102 17.8552 19.0094 17.6026 18.8273 17.414L15.1753 13.762C16.1633 12.5086 16.7784 11.0024 16.9504 9.41573C17.1223 7.82905 16.8441 6.22602 16.1475 4.79009C15.4509 3.35417 14.3642 2.14336 13.0116 1.29623C11.659 0.449106 10.0952 -0.000107143 8.49928 1.91687e-08ZM1.99928 8.5C1.99928 6.77609 2.6841 5.12279 3.90308 3.90381C5.12207 2.68482 6.77537 2 8.49928 2C10.2232 2 11.8765 2.68482 13.0955 3.90381C14.3145 5.12279 14.9993 6.77609 14.9993 8.5C14.9993 10.2239 14.3145 11.8772 13.0955 13.0962C11.8765 14.3152 10.2232 15 8.49928 15C6.77537 15 5.12207 14.3152 3.90308 13.0962C2.6841 11.8772 1.99928 10.2239 1.99928 8.5Z" fill="#09244B"/>
                        </svg>
                    </button>
                    <button type="button" class="mobile_search_close" id="mobile_search_close">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 4L4 12M4 4L12 12" stroke="#666" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- } -->
    
    <!-- 모바일 서브메뉴 { -->
    <?php if(!defined('_INDEX_')) { // 메인페이지가 아닐 때만 표시 ?>
    <div class="mo_sub_mnav__wr">
        <div class="list__sub__menu">
            <ul class="active">
					<li><a href="/anonymous">비밀톡</a></li>
					<li><a href="/bbs/board.php?bo_table=free">헤어톡</a></li>					
					<li><a href="/bbs/board.php?bo_table=recruit">채용정보</a></li>					
					<li><a href="/bbs/board.php?bo_table=card">포트폴리오</a></li>		
                    <li><a href="/second_hand">중고거래</a></li>
                    <li><a href="/activity">이벤트</a></li>		
					<li><a href="/notice">공지사항</a></li>	
					<li><a href="/rb/attend.php">출석부</a></li>				
            </ul>
        </div>
    </div>
    <?php } ?>
    <!-- } -->
    
    <!-- 나머지 필요한 코드들 -->
    <div class="rows_gnb_wrap re_hide">
        <div class="inner row_gnbs" style="width:<?php echo $tb_width_inner ?>; <?php echo $tb_width_padding ?>">
            <nav id="cbp-hrmenu" class="re_hide cbp-hrmenu pc">
                <ul class="">
                <?php
                $menu_datas = get_menu_db(0, true);
                $gnb_zindex = 999; // gnb_1dli z-index 값 설정용
                $i = 0;
                foreach( $menu_datas as $row ){
                    if( empty($row) ) continue;
                ?>
                <li>
                    <a href="<?php echo $row['me_link']; ?>" target="_<?php echo $row['me_target']; ?>" class="font-B"><?php echo $row['me_name'] ?></a>
                    <?php
                    $k = 0;
                    foreach( (array) $row['sub'] as $row2 ){
                        if( empty($row2) ) continue; 
                        if($k == 0)
                            echo '<div class="cbp-hrsub"><div class="cbp-hrsub-inner"><div><!--<h4 class="font-B">그룹</h4>--><ul>'.PHP_EOL;
                    ?>
                        <li><a href="<?php echo $row2['me_link']; ?>" target="_<?php echo $row2['me_target']; ?>"><?php echo $row2['me_name'] ?></a></li>
                    <?php
                    $k++;
                    }   //end foreach $row2

                    if($k > 0)
                        echo '</ul></div></div></div>'.PHP_EOL;
                    ?>
                </li>
                <?php
                $i++;
                }   //end foreach $row
                ?>

                <?php if ($i == 0) {  ?>
                <li><a href="javascript:void(0);">메뉴 준비 중입니다.</a></li>
                <?php } ?>
                </ul>
            </nav>
        </div>
    </div>
</header>
<!-- } -->

<!-- 스타일 추가 -->
<style>
/* 메인 페이지에서 메뉴 숨기기 */
.hd__mnav__wr.main-page .hd__mnav__list {
    visibility: hidden;
}

/* PC 로고 크기 조정 */
.pc_logo img {
    height: 35px !important;
    width: auto;
}

/* 모바일 검색버튼 스타일 */
.mobile_right_buttons {
    display: flex;
    align-items: center;
    gap: 10px;
}

.mobile_search_btn {
    background: none;
    border: none;
    padding: 5px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* 헤더 위젯 스타일 */
.header_point_rank_widget {
    margin-right: 20px;
    display: flex;
    align-items: center;
}

/* 우측 네비게이션 레이아웃 조정 */
.hd__right__nav {
    display: flex;
    align-items: center;
    flex-direction: row;
    justify-content: flex-end;
    gap: 0;
}

.hd__right__nav .header_point_rank_widget {
    order: 1;
}

.hd__right__nav .snb_wrap {
    order: 2;
}

.hd__right__nav #search_top_btn {
    order: 3;
}

.hd__right__nav #search_box_wrap {
    order: 4;
}

/* 모바일 검색바 스타일 - 기존 mobile_search_area 클래스 활용 */
.mobile_search_area {
    display: none;
    width: 100%;
    padding: 0 15px 15px;
    background: #fff;
    animation: slideDown 0.3s ease-out;
    margin-top: 50px;
}

.mobile_search_area.active {
    display: block;
}

.mobile_search_area .search_top_wrap_inner {
    position: relative;
    display: flex;
    align-items: center;
}

.mobile_search_area .search_top_wrap input {
    border: 1px solid #e5e7eb;
    background-color: #f9fafb;
    height: 45px;
    border-radius: 25px;
    font-size: 16px;
    padding: 0 80px 0 20px;
    transition: all 0.3s ease;
    width: 100%;
}

.mobile_search_area .search_top_wrap input:focus {
    border-color: #7A4EFE !important;
    background-color: #fff;
    box-shadow: 0 0 0 3px rgba(122, 78, 254, 0.1);
    outline: none;
}

.mobile_search_area .search_top_wrap button {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    padding: 5px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.3s;
}

.mobile_search_area .search_top_wrap button[type="submit"] {
    right: 45px;
}

.mobile_search_close {
    right: 10px !important;
}

.mobile_search_area .search_top_wrap button:hover {
    background-color: rgba(0, 0, 0, 0.05);
}

.mobile_search_area .search_top_wrap button svg {
    width: 20px;
    height: 20px;
}

.mobile_search_area .search_top_wrap button svg path,
.mobile_search_area .search_top_wrap button svg path {
    fill: #6b7280;
    transition: fill 0.3s ease;
}

.mobile_search_area .search_top_wrap input:focus ~ button svg path {
    fill: #7A4EFE;
}

/* 모바일에서만 검색 버튼과 검색바 표시 */
@media all and (min-width: 1025px) {
    .mobile_search_btn,
    .mobile_search_area,
    .mobile_right_buttons {
        display: none !important;
    }
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* 모바일 서브메뉴 스타일 추가 */
.mo_sub_mnav__wr {
    display: none;
    background: #fff;
    border-bottom: 1px solid #e5e5e5;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.mo_sub_mnav__wr .list__sub__menu {
    white-space: nowrap;
}

.mo_sub_mnav__wr .list__sub__menu ul {
    display: flex;
    gap: 0;
    padding: 0;
    margin: 0;
    list-style: none;
}

.mo_sub_mnav__wr .list__sub__menu li {
    flex-shrink: 0;
}

.mo_sub_mnav__wr .list__sub__menu a {
    display: block;
    padding: 12px 16px;
    color: #666;
    font-size: 14px;
    text-decoration: none;
    white-space: nowrap;
    transition: color 0.3s;
}

.mo_sub_mnav__wr .list__sub__menu li.active a {
    color: #7c3aed;
    font-weight: 600;
}

@media all and (max-width: 768px) {
    .mo_sub_mnav__wr {
        display: block;
    }
    
    /* 모바일에서 헤더 위젯 숨기기 */
    .header_point_rank_widget {
        display: none !important;
    }
}

/* 서브메뉴 fade transition 추가 */
.sub__menu {
    transition: opacity 0.3s ease, visibility 0.3s ease;
}
.sub__menu.fade-out {
    opacity: 0 !important;
}

.cate__drop__menu{background-color: #fff;position:fixed; top:0; left:0; width:100%; opacity:0; visibility:hidden; transition:all 0.2s;}
.cate__drop__menu .in__box__wr{z-index:10; position:relative; background-color: #fff;padding:30px 0 10px;}
.cate__drop__menu .in__box__wr .in__box{ display:flex; gap:20px; max-width: 1400px; margin: 0 auto;}
.cate__drop__menu .in__box__wr .in__box a{display:block;}
.cate__drop__menu .in__box__wr .in__box a img{width: 60%;max-width:90px; display:block; border-radius:10px;margin: 0 auto;}
.cate__drop__menu .in__box__wr .in__box a .tit{color:#000; font-size:13px;text-align: center}
.cate__drop__menu .in__box__wr .in__box a:hover .tit{color:#00A3FF ;}

.cate__drop__menu.open-active{opacity:1; visibility:visible; top:51px;}
.cate__drop__bg{position:absolute; top:0; left:0; width:100vw; height:100vh; background-color:rgba(0,0,0,0.2);}

/* 콘텐츠 드롭다운 메뉴 추가 */
.contents__drop__menu {
    background-color: #fff;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    opacity: 0;
    visibility: hidden;
    transition: all 0.2s;
}
.contents__drop__menu .in__box__wr {
    z-index: 10;
    position: relative;
    background-color: #fff;
    padding: 30px 0 10px;
}
.contents__drop__menu .in__box__wr .in__box {
    display: flex;
    max-width: 1280px;
    margin: 0 auto;
    gap: 30px;
}
.contents__drop__menu .in__box__wr .in__box a {
    display: block;
}
.contents__drop__menu .in__box__wr .in__box a img {
    width: 60%;
    max-width: 90px;
    display: block;
    border-radius: 10px;
    margin: 0 auto;
}
.contents__drop__menu .in__box__wr .in__box a .tit {
    color: #000;
    font-size: 13px;
    text-align: center;
}
.contents__drop__menu .in__box__wr .in__box a:hover .tit {
    color: #00A3FF;
}
.contents__drop__menu.open-active {
    opacity: 1;
    visibility: visible;
    top: 51px;
}
.contents__drop__menu .contents__drop__bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: rgba(0,0,0,0.2);
}

@media all and (max-width:1024px) {
.contents__drop__menu.open-active {
    top: 105px;
    }
}
</style>

<!-- 스크립트 { -->
<script>
$(document).ready(function() {
    // 모바일 메뉴 열기
    $('#tog_gnb_mobile').click(function() {
        $('#cbp-hrmenu-btm').addClass('active');
        $('#m_gnb_close_btn').addClass('active');
        $('main').addClass('moves');
        $('header').addClass('moves');
    });
    
    // 모바일 검색 기능
    var isMobileSearchVisible = false;
    
    $('#mobile_search_btn').click(function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        isMobileSearchVisible = !isMobileSearchVisible;
        
        if (isMobileSearchVisible) {
            $('#mobile_search_bar').addClass('active');
            setTimeout(function() {
                $('#mobile_search_input').focus();
            }, 100);
        } else {
            $('#mobile_search_bar').removeClass('active');
        }
    });
    
    $('#mobile_search_close').click(function(e) {
        e.preventDefault();
        $('#mobile_search_bar').removeClass('active');
        isMobileSearchVisible = false;
    });
    
    // 모바일 검색바 외부 클릭 시 닫기
    $(document).click(function(e) {
        if (isMobileSearchVisible && !$(e.target).closest('#mobile_search_bar, #mobile_search_btn').length) {
            $('#mobile_search_bar').removeClass('active');
            isMobileSearchVisible = false;
        }
    });
    
    // PC 검색창 클릭 이벤트 강제 활성화
    $('.search_top_wrap.pc_only input[name="stx"]').on('click focus', function(e) {
        e.stopPropagation();
        $(this).focus();
    });
    
    // 검색창 부모 요소 클릭 시에도 포커스
    $('.search_top_wrap_inner').on('click', function(e) {
        e.stopPropagation();
        $(this).find('input[name="stx"]').focus();
    });
    
    // 서브메뉴 1초 후 부드럽게 사라짐
    var submenuTimer;
    
    $('.hd__mnav__list').mouseenter(function() {
        clearTimeout(submenuTimer);
        $(this).addClass('on');
    });
    
    $('.hd__mnav__list').mouseleave(function() {
        var $this = $(this);
        submenuTimer = setTimeout(function() {
            $this.find('.sub__menu').addClass('fade-out');
            setTimeout(function() {
                $this.removeClass('on');
                $this.find('.sub__menu').removeClass('fade-out');
            }, 300);
        }, 1000);
    });
    
    $('.sub__menu').mouseenter(function() {
        clearTimeout(submenuTimer);
        $(this).removeClass('fade-out');
        $(this).closest('.hd__mnav__list').addClass('on');
    });
    
    $('.sub__menu').mouseleave(function() {
        var $parentList = $(this).closest('.hd__mnav__list');
        var $submenu = $(this);
        submenuTimer = setTimeout(function() {
            $submenu.addClass('fade-out');
            setTimeout(function() {
                $parentList.removeClass('on');
                $submenu.removeClass('fade-out');
            }, 300);
        }, 1000);
    });
    
    // 모바일 서브메뉴 현재 페이지 활성화
    var currentPath = window.location.pathname;
    $('.mo_sub_mnav__wr .list__sub__menu a').each(function() {
        var href = $(this).attr('href');
        if (currentPath.indexOf(href) !== -1) {
            $(this).parent().addClass('active');
        }
    });
});

// 검색 폼 제출
function fsearchbox_submit(f) {
    var stx = f.stx.value.trim();
    if (stx.length < 2) {
        alert("검색어는 두글자 이상 입력해주세요.");
        f.stx.select();
        f.stx.focus();
        return false;
    }

    var cnt = 0;
    for (var i = 0; i < stx.length; i++) {
        if (stx.charAt(i) == ' ')
            cnt++;
    }

    if (cnt > 1) {
        alert("빠른 검색을 위해 공백은 한번만 입력할 수 있어요.");
        f.stx.select();
        f.stx.focus();
        return false;
    }
    f.stx.value = stx;

    return true;
}

// URL 기반 메뉴 활성화
$(function() {
    var currentUrl = window.location.pathname.replace(/\/$/, '') + window.location.search;
    var containerTitle = $('#container_title').text().trim();
    var excludeUrls = ['/aquascape', '/vivarium', '/paludarium', '/terrarium', '/shop'];

    $('.hd__mnav__list, .mo_sub_mnav__wr .list__sub__menu li').removeClass('on active');

    if (currentUrl.includes('/creature') || currentUrl.includes('bo_table=creature')) {
        $('.hd__mnav__list:contains("커뮤니티")').addClass('on active');
        $('.hd__mnav__list:contains("커뮤니티") .sub__menu a:contains("생물")')
            .closest('li').addClass('on active')
            .siblings().removeClass('on active');

        $('.hd__mnav__list:contains("쇼핑")').removeClass('on active');

        $('.mo_sub_mnav__wr .list__sub__menu').eq(0).addClass('on active');
        $('.mo_sub_mnav__wr .list__sub__menu a:contains("생물")')
            .closest('li').addClass('on active')
            .siblings().removeClass('on active');
    }
    else if (!excludeUrls.includes(currentUrl) && !containerTitle) {
        $('.hd__mnav__list:contains("커뮤니티")').addClass('on active')
            .siblings().removeClass('on active');

        $('.mo_sub_mnav__wr .list__sub__menu').eq(0).addClass('on active')
            .siblings().removeClass('on active');
    } 
    else {
        $('.sub__menu a, .list__sub__menu a').each(function() {
            var href = $(this).attr('href').replace(/\/$/, '');
            var menuText = $(this).text().trim();

            if (currentUrl === href || containerTitle === menuText) {
                $(this).closest('li').addClass('on active').siblings().removeClass('on active');
                $(this).closest('.hd__mnav__list').addClass('on active').siblings().removeClass('on active');
                $(this).closest('.list__sub__menu').addClass('on active').siblings().removeClass('on active');
            }
        });
    }
});

// 콘텐츠 드롭다운 토글
$(document).ready(function () {
    $(".main__contents__list > a").click(function(e){
        e.preventDefault();
        $(this).toggleClass('chk');
        $(".contents__drop__menu").toggleClass('open-active');
    });

    $(".sub__menu").mouseleave(function(e){
        e.preventDefault();
        $(".contents__drop__menu").removeClass('open-active');
        $(".main__contents__list > a").removeClass('chk');
    });

    $(".contents__drop__bg").mouseenter(function(e){
        e.preventDefault();
        $(".contents__drop__menu").removeClass('open-active');
        $(".main__contents__list > a").removeClass('chk');
    });
});

// 모바일 서브메뉴 현재 페이지 활성화 및 자동 스크롤
$(document).ready(function() {
    var currentPath = window.location.pathname;
    var activeItem = null;
    
    $('.mo_sub_mnav__wr .list__sub__menu a').each(function() {
        var href = $(this).attr('href');
        if (currentPath.indexOf(href) !== -1) {
            $(this).parent().addClass('active');
            activeItem = $(this).parent();
        }
    });
    
    // 활성화된 메뉴가 있으면 해당 위치로 스크롤
    if (activeItem && activeItem.length > 0) {
        var container = $('.mo_sub_mnav__wr .list__sub__menu');
        var scrollLeft = activeItem.position().left + container.scrollLeft() - (container.width() / 2) + (activeItem.width() / 2);
        
        // 부드러운 스크롤 애니메이션
        container.animate({
            scrollLeft: scrollLeft
        }, 300);
    }
});

</script>

<!-- } -->