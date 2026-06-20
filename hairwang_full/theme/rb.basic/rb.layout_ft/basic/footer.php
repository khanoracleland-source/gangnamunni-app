<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 레이아웃 폴더내 style.css 파일
add_stylesheet('<link rel="stylesheet" href="'.G5_THEME_URL.'/rb.layout_ft/'.$rb_core['layout_ft'].'/style.css">', 0);

// 현재 페이지 확인
$current_url = $_SERVER['REQUEST_URI'];
$is_home = defined('_INDEX_');
$is_hairtalk = strpos($current_url, 'bo_table=free') !== false;
$is_mentortalk = strpos($current_url, 'bo_table=qa') !== false;
$is_youtube = strpos($current_url, 'bo_table=youtube') !== false;
$is_mypage = strpos($current_url, '/rb/mypage.php') !== false || strpos($current_url, '/rb/home.php') !== false;
?>



<!-- 글쓰기 플로팅 버튼 -->
<div class="floating-write-btn">
    <a href="#" class="write-floating-btn" onclick="return false;">
        <i class="fa fa-pencil" aria-hidden="true"></i>
        <span class="btn-text">글쓰기</span>
    </a>
</div>

<?php if ($is_member) { // 로그인한 회원만 게시판 선택 모달 표시 ?>
<!-- 게시판 선택 모달 -->
<div id="board-select-modal" class="board-modal">
    <div class="board-modal-content">
        <div class="board-modal-header">
            <h3>게시판 : 나만의 글을 공유해주세요!</h3>
            <span class="board-modal-close">&times;</span>
        </div>
        <div class="board-modal-body">
            <div class="board-button-grid">
               <a href="/anonymous/write" class="board-button">
                    <div class="board-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2" fill="none"/>
                            <path d="M20 21c0-3.5-3.5-7-8-7s-8 3.5-8 7" stroke="currentColor" stroke-width="2" fill="none"/>
                            <path d="M12 11v2" stroke="currentColor" stroke-width="2" opacity="0.5"/>
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none" opacity="0.3"/>
                        </svg>
                    </div>
                    <span>비밀톡</span>
                </a>
							
                <a href="<?php echo G5_BBS_URL ?>/write.php?bo_table=free" class="board-button">
                    <div class="board-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 14.5C21 9.5 17 8 12 5C7 8 3 9.5 3 14.5c0 3.5 2.5 6.5 9 6.5s9-3 9-6.5z" stroke="currentColor" stroke-width="2" fill="none"/>
                            <circle cx="9" cy="13" r="1" fill="currentColor"/>
                            <circle cx="15" cy="13" r="1" fill="currentColor"/>
                            <path d="M8 16s1.5 2 4 2 4-2 4-2" stroke="currentColor" stroke-width="2" fill="none"/>
                        </svg>
                    </div>
                    <span>헤어톡</span>
                </a>
<!--
                <a href="<?php echo G5_BBS_URL ?>/write.php?bo_table=qa" class="board-button">
                    <div class="board-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                            <path d="M9.5 9.5C9.5 7.8 10.8 7 12 7s2.5 0.8 2.5 2.5-1.1 2.5-2.5 2.5v2" stroke="currentColor" stroke-width="2" fill="none"/>
                            <circle cx="12" cy="17" r="0.5" fill="currentColor"/>
                        </svg>
                    </div>
                    <span>멘토Q&A</span>
                </a>
		
                 <a href="<?php echo G5_BBS_URL ?>/write.php?bo_table=youtube" class="board-button">
                    <div class="board-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 11.75a29 29 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29 29 0 0 0 .46-5.25 29 29 0 0 0-.46-5.33z" stroke="currentColor" stroke-width="2" fill="none"/>
                            <polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02" fill="currentColor"/>
                        </svg>
                    </div>
                    <span>유튜브</span>
                </a>
-->				
                <a href="/card/write" class="board-button">
                    <div class="board-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2" stroke="currentColor" stroke-width="2" fill="none"/>
                            <circle cx="8.5" cy="8.5" r="1.5" stroke="currentColor" stroke-width="2" fill="none"/>
                            <polyline points="21 15 16 10 5 21" stroke="currentColor" stroke-width="2" fill="none"/>
                        </svg>
                    </div>
                    <span>포트폴리오</span>
                </a>
                <a href="/second_hand" class="board-button">
                    <div class="board-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z" stroke="currentColor" stroke-width="2" fill="none"/>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96" stroke="currentColor" stroke-width="2"/>
                            <line x1="12" y1="22.08" x2="12" y2="12" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <span>중고거래</span>
                </a>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<style>
/* 글쓰기 플로팅 버튼 스타일 - PC와 모바일 공통 */
.floating-write-btn {
    position: fixed;
    bottom: 90px;
    right: 20px;
    z-index: 999;
}

.write-floating-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 12px 16px;
    background: #7A4EFE;
    color: #fff;
    border-radius: 10px;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    text-decoration: none;
    transition: all 0.3s ease;
    min-width: 80px;
    position: relative;
    overflow: hidden;
}

.write-floating-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.write-floating-btn:hover::before {
    left: 100%;
}

.write-floating-btn:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 6px 25px rgba(102, 126, 234, 0.6);
    color: #fff;
    text-decoration: none;
}

.write-floating-btn svg {
    margin-bottom: 4px;
    transition: transform 0.3s ease;
}

.write-floating-btn:hover svg {
    transform: rotate(90deg);
}

.write-floating-btn .btn-text {
    font-size: 12px;
    color: #fff;
    font-weight: 600;
    letter-spacing: 0.5px;
}

/* 모바일에서 버튼 크기 조정 */
@media (max-width: 768px) {
    .floating-write-btn {
        bottom: 80px;
        right: 15px;
    }
    
    .write-floating-btn {
        padding: 10px 14px;
        min-width: 70px;
    }
    
    .write-floating-btn svg {
        width: 20px;
        height: 20px;
    }
    
    .write-floating-btn .btn-text {
        font-size: 11px;
    }
}

/* 스크롤 시 버튼 숨김/표시 애니메이션 */
.floating-write-btn.hide {
    transform: translateX(150px);
    transition: transform 0.3s ease;
}

/* 게시판 선택 모달 스타일 */
.board-modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
}

.board-modal-content {
    background-color: #ffffff;
    margin: 10% auto;
    padding: 0;
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

/* 모바일에서 모달 위치 조정 */
@media (max-width: 768px) {
    .board-modal-content {
        margin: 20% auto;
        border-radius: 20px 20px 0 0;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        margin: 0;
        width: 100%;
        animation: modalSlideUp 0.3s ease;
    }
}

@keyframes modalSlideUp {
    from {
        transform: translateY(100%);
    }
    to {
        transform: translateY(0);
    }
}

.board-modal-header {
    padding: 20px 25px;
    background: #7A4EFE;
    color: white;
    border-radius: 20px 20px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.board-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.board-modal-close {
    color: white;
    font-size: 28px;
    font-weight: 300;
    cursor: pointer;
    line-height: 20px;
    transition: transform 0.2s ease;
}

.board-modal-close:hover {
    transform: rotate(90deg);
}

.board-modal-body {
    padding: 30px 25px;
}

/* 게시판 버튼 그리드 스타일 */
.board-button-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.board-button {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 20px 10px;
    background-color: #f8f9fa;
    border: 2px solid transparent;
    border-radius: 16px;
    text-decoration: none;
    color: #495057;
    transition: all 0.3s ease;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.board-button:hover {
    background-color: transparent;
    border-color: #7A4EFE;
    color: #7A4EFE;
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 255, 0.3);
}

.board-icon-wrap {
    position: relative;
    z-index: 1;
    margin-bottom: 8px;
    transition: transform 0.3s ease;
}

.board-button:hover .board-icon-wrap {
    transform: scale(1.1);
}

.board-button svg {
    transition: all 0.3s ease;
}

.board-button:hover svg {
    fill: #7A4EFE;
}

.board-button span {
    font-size: 14px;
    font-weight: 600;
    position: relative;
    z-index: 1;
}

/* 모바일에서 버튼 크기 조정 */
@media (max-width: 480px) {
    .board-button-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .board-button {
        padding: 18px 5px;
    }
    
    .board-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 255, 0.2);
    }
    
    .board-icon-wrap svg {
        width: 24px;
        height: 24px;
    }
    
    .board-button span {
        font-size: 13px;
    }
}
</style>

<script>
$(document).ready(function() {
    // 플로팅 버튼 클릭 시
    $('.write-floating-btn').click(function(e) {
        e.preventDefault();
        
        var isLoggedIn = <?php echo $is_member ? 'true' : 'false'; ?>;
        
        if (!isLoggedIn) {
            // 비로그인 시 로그인 페이지로 이동
            location.href = '<?php echo G5_BBS_URL ?>/login.php?url=' + encodeURIComponent(location.href);
        } else {
            // 로그인 시 게시판 선택 모달 표시
            $('#board-select-modal').fadeIn();
        }
    });
    
    <?php if ($is_member) { ?>
    // 모달 닫기
    $('.board-modal-close').click(function() {
        $('#board-select-modal').fadeOut();
    });
    
    // 모달 바깥 클릭 시 닫기
    $(window).click(function(e) {
        if ($(e.target).hasClass('board-modal')) {
            $('#board-select-modal').fadeOut();
        }
    });
    <?php } ?>
    
    // 스크롤 시 버튼 숨김/표시
    var lastScrollTop = 0;
    var delta = 5;
    var navbarHeight = $('.floating-write-btn').outerHeight();
    
    $(window).scroll(function(event) {
        var st = $(this).scrollTop();
        
        if (Math.abs(lastScrollTop - st) <= delta) {
            return;
        }
        
        if (st > lastScrollTop && st > navbarHeight) {
            // 아래로 스크롤
            $('.floating-write-btn').addClass('hide');
        } else {
            // 위로 스크롤
            $('.floating-write-btn').removeClass('hide');
        }
        
        lastScrollTop = st;
    });
});
</script>

<footer>
    <div class="footer_wrapper">
        <div class="inner">
            <!-- PC 푸터 -->
            <div class="footer_pc">
                <!-- 상단 영역 -->
                <div class="footer_top">
                    <div class="footer_logo">
                        <?php if (!empty($rb_builder['bu_logo_pc_w'])) { ?>
                            <a href="<?php echo G5_URL; ?>/gangnamunni/"><img src="<?php echo G5_URL ?>/data/logos/pc_w?ver=<?php echo G5_SERVER_TIME ?>" alt="HAIR WANG"></a>
                        <?php } else { ?>
                            <a href="<?php echo G5_URL; ?>/gangnamunni/"><img src="<?php echo G5_URL ?>/data/logos/mo_w?ver=<?php echo G5_SERVER_TIME ?>" alt="HAIR WANG"></a>
                        <?php } ?>
                    </div>
                    
                    <div class="footer_links">
                        <a href="<?php echo get_pretty_url('content', 'provision'); ?>">서비스 이용약관</a>
                        <a href="<?php echo get_pretty_url('content', 'privacy'); ?>">개인정보 처리방침</a>
                    </div>
                </div>
                
                <!-- 하단 영역 -->
                <div class="footer_bottom">
                    <div class="footer_info">
                        <div class="company_info">
                            <?php if (!empty($rb_builder['bu_1'])) { ?><span><?php echo $rb_builder['bu_1'] ?></span><?php } ?>
                            <?php if (!empty($rb_builder['bu_2'])) { ?><span>대표자 : <?php echo $rb_builder['bu_2'] ?></span><?php } ?>
                            <?php if (!empty($rb_builder['bu_3'])) { ?><span>대표전화 : <?php echo $rb_builder['bu_3'] ?></span><?php } ?>
                            <?php if (!empty($rb_builder['bu_5'])) { ?><span>사업자등록번호 : <?php echo $rb_builder['bu_5'] ?></span><?php } ?>
                            <?php if (!empty($rb_builder['bu_6'])) { ?><span>통신판매업신고번호 : <?php echo $rb_builder['bu_6'] ?></span><?php } ?>
                            <?php if (!empty($rb_builder['bu_10'])) { ?><span>주소 : <?php if (!empty($rb_builder['bu_9'])) { ?>(<?php echo $rb_builder['bu_9'] ?>) <?php } ?><?php echo $rb_builder['bu_10'] ?></span><?php } ?>
                        </div>
                        
                        <?php if (!empty($rb_builder['bu_12'])) { ?>
                        <div class="copyright">
                            <?php echo $rb_builder['bu_12'] ?>
                        </div>
                        <?php } ?>
                    </div>
                    
                    <div class="footer_menu">
                        <a href="<?php echo G5_URL ?>/notice">공지사항</a>
                        <a href="<?php echo G5_BBS_URL ?>/qalist.php">1:1 문의</a>
                        <a href="<?php echo G5_BBS_URL ?>/faq.php">FAQ</a>
                        <a href="<?php echo G5_URL ?>/rb/new.php">새글</a>
                        <a href="<?php echo G5_BBS_URL ?>/current_connect.php">접속자 <?php echo connect("theme/rb.connect"); ?></a>
                    </div>
                </div>
            </div>
            
            <!-- 모바일 푸터 -->
            <div class="footer_mobile">
                <div class="footer_mobile_content">
                    <div class="footer_mobile_top">
                        <div class="footer_mobile_logo">
                            <img src="<?php echo G5_URL ?>/data/logos/mo?ver=<?php echo G5_SERVER_TIME ?>" alt="HAIR WANG">
                        </div>
                        <div class="footer_mobile_links">
                            <a href="<?php echo get_pretty_url('content', 'provision'); ?>">서비스 이용약관</a>
                            <span class="divider">|</span>
                            <a href="<?php echo get_pretty_url('content', 'privacy'); ?>">개인정보 처리방침</a>
                        </div>
                    </div>
                    
                    <div class="footer_mobile_info">
                        <?php if (!empty($rb_builder['bu_1'])) { ?><?php echo $rb_builder['bu_1'] ?></span><?php } ?>
                        <?php if (!empty($rb_builder['bu_2'])) { ?> 대표자 : <?php echo $rb_builder['bu_2'] ?><?php } ?>
                        <?php if (!empty($rb_builder['bu_3'])) { ?> 대표전화 : <?php echo $rb_builder['bu_3'] ?><?php } ?><br>
                        <?php if (!empty($rb_builder['bu_5'])) { ?>사업자등록번호 : <?php echo $rb_builder['bu_5'] ?><?php } ?><br>
                        <?php if (!empty($rb_builder['bu_6'])) { ?>통신판매업신고번호 : <?php echo $rb_builder['bu_6'] ?><?php } ?><br>
                        <?php if (!empty($rb_builder['bu_10'])) { ?>주소 : <?php if (!empty($rb_builder['bu_9'])) { ?>(<?php echo $rb_builder['bu_9'] ?>) <?php } ?><?php echo $rb_builder['bu_10'] ?><?php } ?>
                    </div>
                    
                    <?php if (!empty($rb_builder['bu_12'])) { ?>
                    <div class="footer_mobile_copyright">
                        <?php echo $rb_builder['bu_12'] ?>
                    </div>
                    <?php } ?>
                    
                    <div class="footer_mobile_menu">
                        <a href="<?php echo G5_BBS_URL ?>/notice">공지사항</a>
                        <span class="divider">|</span>
                        <a href="<?php echo G5_BBS_URL ?>/qalist.php">1:1 문의</a>
                        <span class="divider">|</span>
                        <a href="<?php echo G5_BBS_URL ?>/faq.php">FAQ</a>
                        <span class="divider">|</span>
                        <a href="<?php echo G5_URL ?>/rb/new.php">새글</a>
                        <span class="divider">|</span>
                        <a href="<?php echo G5_BBS_URL ?>/current_connect.php">접속자 <?php echo connect("theme/rb.connect"); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- 모바일 하단 탭 메뉴 -->
<div class="mobile_bottom_tab">
    <a href="<?php echo G5_URL; ?>/gangnamunni/" class="tab_item <?php echo $is_home ? 'active' : ''; ?>">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <polyline points="9 22 9 12 15 12 15 22" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>홈</span>
    </a>
   
   
    <a href="<?php echo G5_BBS_URL ?>/board.php?bo_table=anonymous" class="tab_item <?php echo $is_mentortalk ? 'active' : ''; ?>">
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <g clip-path="url(#clip0_202_1918)">
                <path d="M19.7019 11.6895C22.3076 12.7149 24.1917 15.2475 24.215 18.1696C24.2266 19.6321 23.788 20.9922 23.0287 22.1216L24.0012 24.5362C24.0849 24.744 23.8837 24.9531 23.6708 24.8795L21.1572 24.0097C20.0172 24.7915 18.6345 25.2494 17.1443 25.2494C14.0918 25.2494 11.484 23.3154 10.4961 20.6184" stroke="currentColor" stroke-miterlimit="10" stroke-linejoin="round"/>
                <path d="M11.0882 3.45511C6.38611 3.44156 2.45926 7.29743 2.42215 11.9675C2.40796 13.7548 2.94393 15.4168 3.87179 16.7968L2.68341 19.7472C2.58116 20.0011 2.82713 20.2566 3.08712 20.1666L6.1587 19.1036C7.55176 20.0589 9.24137 20.6185 11.0623 20.6185C15.8326 20.6185 19.7145 16.7532 19.7029 12.0154C19.6914 7.29436 15.8412 3.46903 11.0882 3.45511Z" fill="white" stroke="currentColor" stroke-miterlimit="10" stroke-linejoin="round"/>
                <path d="M7.32422 16.0461V8.68164L10.8934 14.2409" stroke="currentColor" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M14.4639 16.0461V8.68164L10.8945 14.2409" stroke="currentColor" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/>
            </g>
            <defs>
                <clipPath id="clip0_202_1918">
                    <rect width="28" height="28" fill="white"/>
                </clipPath>
            </defs>
        </svg>
        <span>비밀톡</span>
    </a>
	
    <a href="<?php echo G5_BBS_URL ?>/board.php?bo_table=free" class="tab_item <?php echo $is_hairtalk ? 'active' : ''; ?>">
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M7.93945 3.15332H16.1074C18.7211 3.15332 20.8398 5.27211 20.8398 7.88574V20.7861H7.93945C5.32582 20.7861 3.20703 18.6673 3.20703 16.0537V7.88574C3.20703 5.27211 5.32582 3.15332 7.93945 3.15332Z" fill="white" stroke="currentColor" stroke-width="1.01486"/>
            <circle cx="7.53518" cy="11.7188" r="1.19534" fill="white" stroke="currentColor"/>
            <circle cx="12.0235" cy="11.7188" r="1.19534" fill="white" stroke="currentColor"/>
            <circle cx="16.5078" cy="11.7188" r="1.19534" fill="white" stroke="currentColor"/>
            <path d="M22.8037 8.78613C24.4625 8.78613 25.8076 10.1313 25.8076 11.79C25.8076 13.4488 24.4625 14.7939 22.8037 14.7939C22.6841 14.7939 22.5661 14.7862 22.4502 14.7725L21.4922 16.043L24.5117 19.8828L24.6699 20.1045C25.4006 21.2367 25.2543 22.7426 24.2842 23.7129L23.8711 24.125L23.5205 23.6602L16.8135 14.7725C16.6982 14.7862 16.5806 14.7939 16.4609 14.7939C14.8022 14.7939 13.457 13.4488 13.457 11.79C13.457 10.1313 14.8022 8.78614 16.4609 8.78613C18.1197 8.78613 19.4648 10.1313 19.4648 11.79C19.4648 12.2553 19.356 12.6949 19.167 13.0879L19.6318 13.6777L20.0967 13.0879C19.9078 12.6948 19.7998 12.2548 19.7998 11.79C19.7998 10.1313 21.1449 8.78614 22.8037 8.78613ZM16.4609 11.3447C16.215 11.3447 16.0156 11.5441 16.0156 11.79C16.0156 12.036 16.215 12.2353 16.4609 12.2354C16.7069 12.2354 16.9062 12.036 16.9062 11.79C16.9062 11.5441 16.7069 11.3447 16.4609 11.3447ZM22.8037 11.3447C22.5577 11.3447 22.3584 11.5441 22.3584 11.79C22.3584 12.036 22.5577 12.2353 22.8037 12.2354C23.0497 12.2354 23.249 12.036 23.249 11.79C23.249 11.5441 23.0497 11.3447 22.8037 11.3447Z" fill="white" stroke="currentColor" stroke-width="1.01486"/>
            <path d="M17.9131 16.6455L19.1436 18.2754L19.375 18.5811L19.1436 18.8867L15.376 23.8789L15.0254 24.3447L14.6123 23.9326C13.5774 22.8976 13.4803 21.2527 14.3848 20.1025L17.1094 16.6377L17.5166 16.1201L17.9131 16.6455Z" fill="white" stroke="currentColor" stroke-width="1.01486"/>
        </svg>
        <span>헤어톡</span>
    </a>
 <!--    
    <a href="<?php echo G5_BBS_URL ?>/board.php?bo_table=youtube" class="tab_item <?php echo $is_youtube ? 'active' : ''; ?>">
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M22.8255 5.58398H5.17611C3.33138 5.58398 1.83594 7.07943 1.83594 8.92415V19.0758C1.83594 20.9206 3.33138 22.416 5.17611 22.416H22.8255C24.6702 22.416 26.1657 20.9206 26.1657 19.0758V8.92415C26.1657 7.07943 24.6702 5.58398 22.8255 5.58398Z" stroke="currentColor" stroke-miterlimit="10"/>
            <path d="M17.3242 13.6969L12.1525 10.7099C11.9204 10.5737 11.6328 10.7453 11.6328 11.0127V16.9866C11.6328 17.2541 11.9204 17.4206 12.1525 17.2894L17.3242 14.3024C17.5563 14.1662 17.5563 13.8332 17.3242 13.702V13.6969Z" fill="white" stroke="currentColor" stroke-miterlimit="10"/>
        </svg>
        <span>유튜브</span>
    </a>
 -->   
    <?php if ($is_member) { ?>
    <!-- 로그인 상태: 마이페이지 표시 -->
    <a href="<?php echo G5_URL; ?>/rb/home.php?mb_id=<?php echo $member['mb_id']; ?>" class="tab_item <?php echo $is_mypage ? 'active' : ''; ?>">
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M14.001 15.0654C18.5706 15.0654 22.3794 18.3104 23.2324 22.6094L23.3057 23.0283C23.3765 23.4994 23.0024 23.9364 22.5049 23.9365H5.49707C5.03065 23.9365 4.67307 23.5524 4.68848 23.1162L4.69629 23.0283C5.37777 18.5217 9.28262 15.0654 14.001 15.0654ZM14.001 4.06348C16.3645 4.06348 18.2762 5.96999 18.2764 8.31934C18.2764 10.6675 16.3645 12.5762 14.001 12.5762C11.6373 12.5762 9.72559 10.6688 9.72559 8.31934C9.72572 5.96999 11.6374 4.06348 14.001 4.06348Z" stroke="currentColor"/>
        </svg>
        <span>마이페이지</span>
    </a>
    <?php } else { ?>
    <!-- 비로그인 상태: 로그인 표시 -->
    <a href="<?php echo G5_BBS_URL ?>/login.php" class="tab_item">
        <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M14.001 15.0654C18.5706 15.0654 22.3794 18.3104 23.2324 22.6094L23.3057 23.0283C23.3765 23.4994 23.0024 23.9364 22.5049 23.9365H5.49707C5.03065 23.9365 4.67307 23.5524 4.68848 23.1162L4.69629 23.0283C5.37777 18.5217 9.28262 15.0654 14.001 15.0654ZM14.001 4.06348C16.3645 4.06348 18.2762 5.96999 18.2764 8.31934C18.2764 10.6675 16.3645 12.5762 14.001 12.5762C11.6373 12.5762 9.72559 10.6688 9.72559 8.31934C9.72572 5.96999 11.6374 4.06348 14.001 4.06348Z" stroke="currentColor"/>
        </svg>
        <span>로그인</span>
    </a>
    <?php } ?>
</div>

<script>
// JavaScript로 더 정확한 활성화 처리
$(document).ready(function() {
    var currentPath = window.location.pathname;
    var currentSearch = window.location.search;
    
    // 모든 탭의 active 클래스 제거
    $('.mobile_bottom_tab .tab_item').removeClass('active');
    
    // 현재 페이지에 맞는 탭 활성화
    if (currentPath === '/' || currentPath === '/index.php') {
        $('.mobile_bottom_tab .tab_item').eq(0).addClass('active');
    } else if (currentSearch.includes('bo_table=anonymous')) {
        $('.mobile_bottom_tab .tab_item').eq(1).addClass('active');
    } else if (currentSearch.includes('bo_table=free')) {
        $('.mobile_bottom_tab .tab_item').eq(2).addClass('active');
    } else if (currentPath.includes('/rb/home.php?mb_id=') || currentPath.includes('/rb/home.php')) {
        $('.mobile_bottom_tab .tab_item').eq(3).addClass('active');
    }
});

// 마이페이지 클릭 이벤트 제거 (로그인 체크는 PHP에서 처리)
</script>