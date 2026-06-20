<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
?>

<link rel="stylesheet" href="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/style.css">

<div class="rb_icon_menu_wrap">
    <ul class="rb_icon_menu">
			<li>
			<a href="/anonymous">
				<dd>
					<img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/13.png" alt="비밀톡">
				</dd>
				<dd>비밀톡</dd>
			</a>
		</li>
        <li>
            <a href="/bbs/board.php?bo_table=free">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/1.png" alt="헤어톡">
                </dd>
                <dd>헤어톡</dd>
            </a>
        </li>
<li>
            <a href="/recruit">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/10.png" alt="채용정보">
                </dd>
                <dd>채용정보</dd>
            </a>
        </li>        		
<li>
            <a href="/bbs/board.php?bo_table=card">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/4.png" alt="시술정보">
                </dd>
                <dd>포트폴리오</dd>
            </a>
        </li>
<!--
        <li>
            <a href="/bbs/board.php?bo_table=qa">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/2.png" alt="멘토Q&A">
                </dd>
                <dd>멘토Q&A</dd>
            </a>
        </li>
        <li>
            <a href="/bbs/board.php?bo_table=youtube">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/3.png" alt="유튜브">
                </dd>
                <dd>유튜브</dd>
            </a>
        </li>
-->	
        <li>
            <a href="/second_hand">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/5.png" alt="중고거래">
                </dd>
                <dd>중고거래</dd>
            </a>
        </li>
        <li>
            <a href="/activity">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/6.png" alt="이벤트">
                </dd>
                <dd>이벤트</dd>
            </a>
        </li>
<li>
			<a href="/notice">
				<dd>
					<img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/14.png" alt="공지사항">
				</dd>
				<dd>공지사항</dd>
			</a>
		</li>        
        <li>
            <a href="/rb/attend.php">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/8.png" alt="출석부">
                </dd>
                <dd>출석부</dd>
            </a>
        </li>
		</ul>
<!-- 
        <li>
            <a href="#">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/7.png" alt="헤어시그널">
                </dd>
                <dd>헤어시그널</dd>
            </a>
        </li>

        <li>
            <a href="#">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/8.png" alt="헤수르">
                </dd>
                <dd>헤수르</dd>
            </a>
        </li>

        <li>
            <a href="/shop">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/9.png" alt="마켓">
                </dd>
                <dd>마켓</dd>
            </a>
        </li>

        <li>
            <a href="#">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/11.png" alt="쇼룸">
                </dd>
                <dd>쇼룸</dd>
            </a>
        </li>

        <li>
            <a href="#">
                <dd>
                    <img src="<?php echo G5_URL ?>/rb/rb.widget/rb.icon_menu/img/12.png" alt="클래스">
                </dd>
                <dd>클래스</dd>
            </a> 
        </li>-->
    
    <!-- <div class="rb_more_btn_wrap">
        <button type="button" class="rb_more_btn" onclick="toggleMoreMenu()">
            <span class="more_text">더보기</span>
            <span class="more_icon">+</span>
        </button>
    </div> -->
</div>

<!-- <script>
function toggleMoreMenu() {
    var menuWrap = document.querySelector('.rb_icon_menu_wrap');
    var moreBtn = document.querySelector('.rb_more_btn');
    var moreText = moreBtn.querySelector('.more_text');
    var moreIcon = moreBtn.querySelector('.more_icon');
    
    if (menuWrap.classList.contains('show_all')) {
        menuWrap.classList.remove('show_all');
        moreText.textContent = '더보기';
        moreIcon.textContent = '+';
    } else {
        menuWrap.classList.add('show_all');
        moreText.textContent = '닫기';
        moreIcon.textContent = '−';
    }
}
</script> -->