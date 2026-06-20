<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 레이아웃 폴더내 style.css 파일
add_stylesheet('<link rel="stylesheet" href="'.G5_THEME_URL.'/shop/rb.layout_hd/'.$rb_core['layout_hd'].'/style.css?ver='.G5_TIME_YMDHIS.'">', 0);

?>
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />


<!-- 헤더 { -->
<header id="header">
       
       <!-- GNB { -->
       <div class="gnb_wrap">
           
           <div class="inner">
              
               <!-- 토글메뉴 { -->
               <ul class="tog_wrap mobile">
                   <li>
                       <button type="button" alt="메뉴열기" id="tog_gnb_mobile">
                           <svg width="18" height="16" viewBox="0 0 18 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                           <path d="M17 14C17.2549 14.0003 17.5 14.0979 17.6854 14.2728C17.8707 14.4478 17.9822 14.687 17.9972 14.9414C18.0121 15.1958 17.9293 15.4464 17.7657 15.6418C17.6021 15.8373 17.3701 15.9629 17.117 15.993L17 16H1C0.74512 15.9997 0.499968 15.9021 0.314632 15.7272C0.129296 15.5522 0.017765 15.313 0.00282788 15.0586C-0.0121092 14.8042 0.0706746 14.5536 0.234265 14.3582C0.397855 14.1627 0.629904 14.0371 0.883 14.007L1 14H17ZM17 7C17.2652 7 17.5196 7.10536 17.7071 7.29289C17.8946 7.48043 18 7.73478 18 8C18 8.26522 17.8946 8.51957 17.7071 8.70711C17.5196 8.89464 17.2652 9 17 9H1C0.734784 9 0.48043 8.89464 0.292893 8.70711C0.105357 8.51957 0 8.26522 0 8C0 7.73478 0.105357 7.48043 0.292893 7.29289C0.48043 7.10536 0.734784 7 1 7H17ZM17 0C17.2652 0 17.5196 0.105357 17.7071 0.292893C17.8946 0.48043 18 0.734784 18 1C18 1.26522 17.8946 1.51957 17.7071 1.70711C17.5196 1.89464 17.2652 2 17 2H1C0.734784 2 0.48043 1.89464 0.292893 1.70711C0.105357 1.51957 0 1.26522 0 1C0 0.734784 0.105357 0.48043 0.292893 0.292893C0.48043 0.105357 0.734784 0 1 0H17Z" fill="#09244B"/>
                           </svg>
                       </button>
                       
                       <script>
                           $(document).ready(function() {
                               $('#tog_gnb_mobile').click(function() {
                                   $('#cbp-hrmenu-btm').addClass('active');
                                   $('#m_gnb_close_btn').addClass('active');
                                   $('main').addClass('moves');
                                   $('header').addClass('moves');
                               });
                           });
                       </script>
                   </li>
               </ul>
               <!-- } -->
                
                <!-- 로고 { -->
                <ul class="logo_wrap">
                    <li>
                        <a href="<?php echo G5_URL ?>/shop" alt="<?php echo $config['cf_title']; ?>">
                           
                            <picture id="logo_img">
                               
                                <?php if (!empty($rb_builder['bu_logo_mo']) && !empty($rb_builder['bu_logo_mo_w'])) { ?>
                                    <source id="sourceSmall" srcset="<?php echo G5_URL ?>/data/logos/mo<?php if($rb_core['header'] != "co_header0") { ?>_w<?php } ?>?ver=<?php echo G5_SERVER_TIME ?>" media="(max-width: 1024px)">
                                <?php } else { ?>
                                    <source id="sourceSmall" srcset="<?php echo G5_THEME_URL ?>/rb.img/logos/mo<?php if($rb_core['header'] != "co_header0") { ?>_w<?php } ?>.png?ver=<?php echo G5_SERVER_TIME ?>" media="(max-width: 1024px)">
                                <?php } ?>
                                
                                <?php if (!empty($rb_builder['bu_logo_pc']) && !empty($rb_builder['bu_logo_pc_w'])) { ?>
                                    <source id="sourceLarge" srcset="<?php echo G5_URL ?>/data/logos/pc<?php if($rb_core['header'] != "co_header0") { ?>_w<?php } ?>?ver=<?php echo G5_SERVER_TIME ?>" media="(min-width: 1025px)">
                                <?php } else { ?>
                                    <source id="sourceSmall" srcset="<?php echo G5_THEME_URL ?>/rb.img/logos/pc<?php if($rb_core['header'] != "co_header0") { ?>_w<?php } ?>.png?ver=<?php echo G5_SERVER_TIME ?>" media="(max-width: 1024px)">
                                <?php } ?>
                                
                                <?php if (!empty($rb_builder['bu_logo_pc']) && !empty($rb_builder['bu_logo_pc_w'])) { ?>
                                    <img id="fallbackImage" src="<?php echo G5_URL ?>/data/logos/pc<?php if($rb_core['header'] != "co_header0") { ?>_w<?php } ?>?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>" class="responsive-image">
                                <?php } else { ?>
                                    <img id="fallbackImage" src="<?php echo G5_THEME_URL ?>/rb.img/logos/pc<?php if($rb_core['header'] != "co_header0") { ?>_w<?php } ?>.png?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>" class="responsive-image">
                                <?php } ?>
                                
                            </picture>

                        </a>
                        
                    </li>
                </ul>
                <!-- } -->

                <div class="hd__mnav__wr">
					<ul class="hd__mnav__ul clearfix center__style">
						<li class="hd__mnav__list">
							<a href="/" class="top_sub_menu">
								<span>커뮤니티</span>
							</a>
							<div class="sub__menu sub__menu01">
								<ul>
                                    <li><a href="/">홈</a></li>
                                    <li><a href="/bbs/board.php?bo_table=free">헤어톡</a></li>
                                    <li><a href="/bbs/board.php?bo_table=qa">오늘의 질문</a></li>
                                    <li><a href="/bbs/board.php?bo_table=youtube">콘텐츠</a></li>
                                    <li><a href="/second_hand">중고거래</a></li>
                                    <li><a href="/recruit">채용정보</a></li>
                                    <li><a href="/contents">이벤트</a></li>
								</ul>
							</div>
						</li>

						<style>
							.hd__mnav__wr .hd__mnav__list .sub__menu ul .main__cate__list a.chk {
								color: #00A3FF;
							}

							.cate__drop__menu {
								background-color: #fff;
								position: fixed;
								top: 132px;
								left: 0;
								width: 100%;
								opacity: 0;
								visibility: hidden;
								transition: all 0.4s;
								transform: translateY(-100%);
							}

							.cate__drop__menu .in__box__wr {
								z-index: 10;
								position: relative;
								background-color: #fff;
								padding: 30px 0 10px;
								overflow-x: auto; /* 좌우 스크롤 가능 */
								white-space: nowrap; /* 아이템들이 줄바꿈되지 않고 한 줄에 나열됨 */
							}

							.cate__drop__menu .in__box__wr .in__box {
								display: flex;
								gap: 20px;
								max-width: 1400px;
								margin: 0 auto;
							}

							.cate__drop__menu .in__box__wr .in__box a {
								display: inline-block; /* 인라인 블록으로 변경하여 스크롤 가능 */
								flex-shrink: 0; /* 축소되지 않도록 설정 */
							}

							.cate__drop__menu .in__box__wr .in__box a img {
								width: 60%; /* 이미지가 부모 요소의 100%만큼 크기를 조정 */
								max-width: 90px; /* 너무 커지지 않도록 최대 크기 제한 */
								display: block;
								border-radius: 10px;
								margin: 0 auto;
							}

							.cate__drop__menu .in__box__wr .in__box a .tit {
								color: #000;
								font-size: 12px;
								text-align: center;
							}

							.cate__drop__menu .in__box__wr .in__box a:hover .tit {
								color: #00A3FF;
							}

							.cate__drop__menu.open-active {
								opacity: 1;
								visibility: visible;
								transform: translateY(0);
								top: 132px;
							}

							.fixed .cate__drop__menu.open-active {
								top: 52px;
							}

							.cate__drop__bg {
								position: absolute;
								top: 0;
								left: 0;
								width: 100vw;
								height: 100vh;
								background-color: rgba(0, 0, 0, 0.5);
							}

							@media screen and (max-width: 1280px) {
								.cate__drop__menu .in__box__wr .in__box {
									padding: 0 30px;
								}
							}

							@media screen and (max-width: 570px) {
								.cate__drop__menu .in__box__wr .in__box {
									padding: 0 12px;
									gap: 10px; /* 요소 간격을 좁게 조정 */
								}

								.cate__drop__menu .in__box__wr .in__box a img {
									max-width: 70px; /* 작은 화면에서 이미지 크기 축소 */
								}

								.cate__drop__menu .in__box__wr .in__box a .tit {
									font-size: 10px; /* 작은 화면에서 글자 크기 축소 */
								}
                                .sub__menu ul {
                                    overflow-x: auto; /* 좌우 스크롤 가능하게 설정 */
                                    overflow-y: hidden; /* 위아래 스크롤 차단 */
                                    white-space: nowrap; /* 항목들이 줄 바꿈되지 않도록 설정 */
                                }

                                .sub__menu ul li {
                                    flex-shrink: 0; /* 항목들이 축소되지 않도록 설정 */
                                }

							}
						</style>
						<li class="hd__mnav__list on">
							<a href="/shop" class="top_sub_menu">
								<span>마켓</span>
							</a>
							<div class="sub__menu">
								<ul>
									<li><a href="/shop">마켓홈</a></li>
									<li class="main__cate__list"><a href="#">카테고리</a></li>
									<li><a href="/shop/listtype.php?type=2">베스트</a></li>
									<li><a href="/shop/listtype.php?type=5">오늘의딜</a></li>
									<li><a href="/shop/listtype.php?type=4">오!이건</a></li>
									<li><a href="/shop/list.php?ca_id=a0">기획전</a></li>
								</ul>
								<div class="cate__drop__menu">
									<div class="cate__drop__bg"></div>
                                        <div class="in__box__wr">
                                            <div class="in__box">
                                                <a href="/shop/list.php?ca_id=10">
                                                    <img src="/theme/rb.basic/img/top/top1.gif" alt="">
                                                    <p class="tit">클리닉</p>
                                                </a>
                                                <a href="/shop/list.php?ca_id=20">
                                                    <img src="/theme/rb.basic/img/top/top2.gif" alt="">
                                                    <p class="tit">샴푸</p>
                                                </a>
                                                <a href="/shop/list.php?ca_id=30">
                                                    <img src="/theme/rb.basic/img/top/top3.gif" alt="">
                                                    <p class="tit">바버샵</p>
                                                </a>
										</div>
									</div>
								</div>
							</div>
						</li>
						<script>
							$(document).ready(function () {
								$(".main__cate__list").click(function (e) {
									e.preventDefault();
									$(".main__cate__list > a").toggleClass('chk');
									$(".cate__drop__menu").toggleClass('open-active');
								});

								$(".sub__menu").mouseleave(function (e) {
									e.preventDefault();
									$(".cate__drop__menu").removeClass('open-active');
									$(".main__cate__list > a").removeClass('chk');
								});

								$(".cate__drop__bg").mouseenter(function (e) {
									e.preventDefault();
									$(".cate__drop__menu").removeClass('open-active');
									$(".main__cate__list > a").removeClass('chk');
								});
							});
						</script>
					</ul>
				</div>

				

                <div class="hd__right__nav">

					<!-- 퀵메뉴 { -->
					<ul class="snb_wrap">
						<li class="qm_wrap">
							<a href="<?php echo G5_SHOP_URL ?>/cart.php" alt="장바구니" class="top_cart_svg pc" title="장바구니">
                            
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M5.5 17C5.89782 17 6.27936 17.158 6.56066 17.4393C6.84196 17.7206 7 18.1022 7 18.5C7 18.8978 6.84196 19.2794 6.56066 19.5607C6.27936 19.842 5.89782 20 5.5 20C5.10218 20 4.72064 19.842 4.43934 19.5607C4.15804 19.2794 4 18.8978 4 18.5C4 18.1022 4.15804 17.7206 4.43934 17.4393C4.72064 17.158 5.10218 17 5.5 17ZM15.5 17C15.8978 17 16.2794 17.158 16.5607 17.4393C16.842 17.7206 17 18.1022 17 18.5C17 18.8978 16.842 19.2794 16.5607 19.5607C16.2794 19.842 15.8978 20 15.5 20C15.1022 20 14.7206 19.842 14.4393 19.5607C14.158 19.2794 14 18.8978 14 18.5C14 18.1022 14.158 17.7206 14.4393 17.4393C14.7206 17.158 15.1022 17 15.5 17ZM1.138 0C1.89654 9.04185e-05 2.62689 0.287525 3.18203 0.804444C3.73717 1.32136 4.07589 2.02939 4.13 2.786L4.145 3H17.802C18.095 2.99996 18.3844 3.06429 18.6498 3.18844C18.9152 3.31259 19.15 3.49354 19.3378 3.71848C19.5255 3.94342 19.6615 4.20686 19.7362 4.49017C19.8109 4.77348 19.8224 5.06974 19.77 5.358L18.133 14.358C18.0492 14.8188 17.8062 15.2356 17.4466 15.5357C17.0869 15.8357 16.6334 16.0001 16.165 16H4.931C4.42514 16 3.93807 15.8083 3.56789 15.4636C3.1977 15.1188 2.97192 14.6466 2.936 14.142L2.136 2.929C2.11802 2.67645 2.00492 2.44012 1.81951 2.2677C1.6341 2.09528 1.39019 1.99961 1.137 2H1C0.734784 2 0.48043 1.89464 0.292893 1.70711C0.105357 1.51957 0 1.26522 0 1C0 0.734784 0.105357 0.48043 0.292893 0.292893C0.48043 0.105357 0.734784 0 1 0H1.138ZM17.802 5H4.288L4.931 14H16.165L17.802 5Z" fill="#09244B"/>
                            </svg>

                        </a>
                        
                        <a href="<?php echo G5_SHOP_URL ?>/orderinquiry.php" alt="주문조회" class="pc" title="주문조회">
                            
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14.994 0C15.4757 3.88649e-05 15.9412 0.173945 16.305 0.489755C16.6688 0.805565 16.9063 1.24206 16.974 1.719L16.991 1.875L17.866 15.875C17.899 16.4003 17.7237 16.9175 17.3779 17.3143C17.0322 17.7112 16.5439 17.9558 16.019 17.995L15.869 18H2C1.4737 18 0.968595 17.7926 0.594205 17.4227C0.219814 17.0528 0.00631561 16.5503 0 16.024L0.00300002 15.875L0.878 1.875C0.908093 1.39407 1.11081 0.940174 1.44887 0.596786C1.78693 0.253398 2.2376 0.0436091 2.718 0.00600004L2.874 0H14.994ZM14.994 2H2.874L1.999 16H15.869L14.994 2ZM11.934 4C12.1992 4 12.4536 4.10536 12.6411 4.29289C12.8286 4.48043 12.934 4.73478 12.934 5C12.934 6.06087 12.5126 7.07828 11.7624 7.82843C11.0123 8.57857 9.99487 9 8.934 9C7.87313 9 6.85572 8.57857 6.10557 7.82843C5.35543 7.07828 4.934 6.06087 4.934 5C4.934 4.73478 5.03936 4.48043 5.22689 4.29289C5.41443 4.10536 5.66878 4 5.934 4C6.19922 4 6.45357 4.10536 6.64111 4.29289C6.82864 4.48043 6.934 4.73478 6.934 5C6.93168 5.51898 7.13119 6.01855 7.49038 6.39315C7.84957 6.76776 8.34032 6.98807 8.85894 7.00754C9.37756 7.02701 9.88345 6.84412 10.2697 6.49751C10.656 6.15089 10.8924 5.6677 10.929 5.15L10.934 5C10.934 4.73478 11.0394 4.48043 11.2269 4.29289C11.4144 4.10536 11.6688 4 11.934 4Z" fill="#09244B"/>
                            </svg>

                        </a>
                        
                        <a href="<?php echo G5_SHOP_URL ?>/couponzone.php" alt="쿠폰존" class="top_cart_svg pc" title="쿠폰존">
                            
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M8.85022 0.356702C9.25397 0.319966 9.66098 0.365447 10.0467 0.4904C10.4324 0.615352 10.7887 0.817183 11.0942 1.0837L11.2442 1.2237L19.0662 9.0467C19.6045 9.58512 19.918 10.3081 19.9431 11.069C19.9681 11.83 19.7029 12.572 19.2012 13.1447L19.0662 13.2887L13.4092 18.9457C12.8708 19.484 12.1478 19.7975 11.3869 19.8225C10.6259 19.8476 9.8839 19.5824 9.31122 19.0807L9.16722 18.9457L1.34322 11.1227C1.05669 10.8362 0.831178 10.4946 0.680278 10.1186C0.529377 9.74252 0.456219 9.3398 0.465217 8.9347L0.476218 8.7297L0.948217 3.5447C1.00882 2.87649 1.29167 2.24787 1.75157 1.75933C2.21147 1.27078 2.82187 0.950511 3.48522 0.849702L3.66422 0.828702L8.85022 0.356702ZM9.15822 2.3457L9.03122 2.3487L3.84622 2.8207C3.63358 2.8399 3.43266 2.92665 3.27288 3.06827C3.1131 3.20988 3.00283 3.39891 2.95822 3.6077L2.94122 3.7257L2.46922 8.9107C2.44649 9.16266 2.5201 9.41386 2.67522 9.6137L2.75822 9.7087L10.5812 17.5317C10.7534 17.7039 10.9825 17.8073 11.2255 17.8226C11.4686 17.8379 11.7088 17.764 11.9012 17.6147L11.9952 17.5317L17.6522 11.8747C17.8244 11.7025 17.9278 11.4734 17.9431 11.2304C17.9584 10.9874 17.8845 10.7471 17.7352 10.5547L17.6522 10.4607L9.82922 2.6377C9.65048 2.45909 9.41074 2.35476 9.15822 2.3457ZM5.63022 5.5107C5.90882 5.2321 6.23956 5.01111 6.60357 4.86033C6.96758 4.70955 7.35772 4.63195 7.75172 4.63195C8.14572 4.63195 8.53586 4.70955 8.89987 4.86033C9.26387 5.01111 9.59462 5.2321 9.87322 5.5107C10.1518 5.7893 10.3728 6.12005 10.5236 6.48405C10.6744 6.84806 10.752 7.2382 10.752 7.6322C10.752 8.0262 10.6744 8.41634 10.5236 8.78035C10.3728 9.14436 10.1518 9.4751 9.87322 9.7537C9.31056 10.3164 8.54743 10.6325 7.75172 10.6325C6.956 10.6325 6.19287 10.3164 5.63022 9.7537C5.06756 9.19105 4.75146 8.42792 4.75146 7.6322C4.75146 6.83648 5.06756 6.07336 5.63022 5.5107ZM8.45922 6.9247C8.36637 6.83179 8.25614 6.75808 8.13481 6.70777C8.01347 6.65746 7.88342 6.63155 7.75207 6.6315C7.62072 6.63145 7.49065 6.65728 7.36928 6.7075C7.24792 6.75772 7.13763 6.83136 7.04472 6.9242C6.95181 7.01705 6.87809 7.12728 6.82779 7.24861C6.77748 7.36995 6.75156 7.5 6.75151 7.63135C6.75147 7.7627 6.77729 7.89277 6.82752 8.01414C6.87774 8.1355 6.95137 8.24579 7.04422 8.3387C7.23173 8.52634 7.48609 8.63181 7.75136 8.6319C8.01663 8.632 8.27108 8.52671 8.45872 8.3392C8.64636 8.15169 8.75183 7.89733 8.75192 7.63206C8.75201 7.36679 8.64673 7.11234 8.45922 6.9247Z" fill="#09244B"/>
                            </svg>

                        </a>
                        
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
						</li>
						
						<li class="member_info_wrap">
							<?php if($is_member) { ?>
							<a href="<?php echo G5_BBS_URL ?>/member_confirm.php?url=<?php echo G5_BBS_URL ?>/register_form.php" class="font-B notranslate"><?php echo $member['mb_nick'] ?></a>　<a href="<?php echo G5_BBS_URL; ?>/point.php" target="_blank" class="win_point"><span class="font-H"><?php echo number_format($member['mb_point']); ?> P</span></a> 
							<?php } ?>
						</li>
						<li class="my_btn_wrap">
							<?php if($is_member) { ?>
								<button type="button" alt="로그아웃" class="btn_round" onclick="location.href='<?php echo G5_BBS_URL ?>/logout.php';">로그아웃</button>
								<button type="button" alt="마이페이지" class="btn_round arr_bg font-B" onclick="location.href='<?php echo G5_URL; ?>/shop/mypage.php';">My</button>
							<?php } else { ?>
								<button type="button" alt="로그인" class="btn_round"  onclick="location.href='<?php echo G5_BBS_URL; ?>/login.php?url=<?php echo $_SERVER['REQUEST_URI']; ?>';">로그인</button>
								<button type="button" alt="회원가입" class="btn_round arr_bg font-B" onclick="location.href='<?php echo G5_BBS_URL ?>/register.php';">회원가입</button>
							<?php } ?>
						</li>
						
						<div class="cb"></li>
					</ul>
					<!-- } -->
					
					<div class="mobile_cb"></div>

					<button type="button" alt="검색" id="search_top_btn" class="ser_open">
						<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path fill-rule="evenodd" clip-rule="evenodd" d="M8.49928 1.91687e-08C7.14387 0.000115492 5.80814 0.324364 4.60353 0.945694C3.39893 1.56702 2.36037 2.46742 1.57451 3.57175C0.788656 4.67609 0.278287 5.95235 0.0859852 7.29404C-0.106316 8.63574 0.0250263 10.004 0.469055 11.2846C0.913084 12.5652 1.65692 13.7211 2.63851 14.6557C3.6201 15.5904 4.81098 16.2768 6.11179 16.6576C7.4126 17.0384 8.78562 17.1026 10.1163 16.8449C11.447 16.5872 12.6967 16.015 13.7613 15.176L17.4133 18.828C17.6019 19.0102 17.8545 19.111 18.1167 19.1087C18.3789 19.1064 18.6297 19.0012 18.8151 18.8158C19.0005 18.6304 19.1057 18.3796 19.108 18.1174C19.1102 17.8552 19.0094 17.6026 18.8273 17.414L15.1753 13.762C16.1633 12.5086 16.7784 11.0024 16.9504 9.41573C17.1223 7.82905 16.8441 6.22602 16.1475 4.79009C15.4509 3.35417 14.3642 2.14336 13.0116 1.29623C11.659 0.449106 10.0952 -0.000107143 8.49928 1.91687e-08ZM1.99928 8.5C1.99928 6.77609 2.6841 5.12279 3.90308 3.90381C5.12207 2.68482 6.77537 2 8.49928 2C10.2232 2 11.8765 2.68482 13.0955 3.90381C14.3145 5.12279 14.9993 6.77609 14.9993 8.5C14.9993 10.2239 14.3145 11.8772 13.0955 13.0962C11.8765 14.3152 10.2232 15 8.49928 15C6.77537 15 5.12207 14.3152 3.90308 13.0962C2.6841 11.8772 1.99928 10.2239 1.99928 8.5Z" fill="#09244B"></path>
						</svg>
                    </button>
					
					<!-- 검색 { -->
					<div id="search_box_wrap">
						<form name="fsearchbox" method="get" action="https://rebuilder.co.kr/bbs/search.php" onsubmit="return fsearchbox_submit(this);">
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

					<ul class="search_top_wrap">
						<form name="fsearchbox" method="get" action="<?php echo G5_BBS_URL ?>/search.php" onsubmit="return fsearchbox_submit(this);">
							<div class="search_top_wrap_inner">
							<input type="hidden" name="sfl" value="wr_subject||wr_content">
							<input type="hidden" name="sop" value="and">

							<input type="text" value="" name="stx" class="font-B" placeholder="통합검색" maxlength="20">
							<button type="submit">
								<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
									<path fill-rule="evenodd" clip-rule="evenodd" d="M8.49928 1.91687e-08C7.14387 0.000115492 5.80814 0.324364 4.60353 0.945694C3.39893 1.56702 2.36037 2.46742 1.57451 3.57175C0.788656 4.67609 0.278287 5.95235 0.0859852 7.29404C-0.106316 8.63574 0.0250263 10.004 0.469055 11.2846C0.913084 12.5652 1.65692 13.7211 2.63851 14.6557C3.6201 15.5904 4.81098 16.2768 6.11179 16.6576C7.4126 17.0384 8.78562 17.1026 10.1163 16.8449C11.447 16.5872 12.6967 16.015 13.7613 15.176L17.4133 18.828C17.6019 19.0102 17.8545 19.111 18.1167 19.1087C18.3789 19.1064 18.6297 19.0012 18.8151 18.8158C19.0005 18.6304 19.1057 18.3796 19.108 18.1174C19.1102 17.8552 19.0094 17.6026 18.8273 17.414L15.1753 13.762C16.1633 12.5086 16.7784 11.0024 16.9504 9.41573C17.1223 7.82905 16.8441 6.22602 16.1475 4.79009C15.4509 3.35417 14.3642 2.14336 13.0116 1.29623C11.659 0.449106 10.0952 -0.000107143 8.49928 1.91687e-08ZM1.99928 8.5C1.99928 6.77609 2.6841 5.12279 3.90308 3.90381C5.12207 2.68482 6.77537 2 8.49928 2C10.2232 2 11.8765 2.68482 13.0955 3.90381C14.3145 5.12279 14.9993 6.77609 14.9993 8.5C14.9993 10.2239 14.3145 11.8772 13.0955 13.0962C11.8765 14.3152 10.2232 15 8.49928 15C6.77537 15 5.12207 14.3152 3.90308 13.0962C2.6841 11.8772 1.99928 10.2239 1.99928 8.5Z" fill="#09244B"/>
								</svg>
							</button>
							</div>
						</form>
						

						<script>
                            
                            
                            //검색창
                            $(document).ready(function() {
                                var isSearchBoxVisible = false;

                                $('#search_top_btn').click(function(event) {
                                    event.stopPropagation(); // Prevent click event from propagating to document
                                    isSearchBoxVisible = !isSearchBoxVisible;
                                    if (isSearchBoxVisible) {
                                        $('#search_box_wrap').show();
                                        $('#search_top_btn').addClass('ser_open');
                                        $('#ser_inp_fc').focus();
                                        
                                    } else {
                                        $('#search_box_wrap').hide();
                                        $('#search_top_btn').removeClass('ser_open');
                                    }
                                });

							$('.close_btn_wr .search_box_close_btn').click(function(event) {
								$('#search_box_wrap').hide();
								$('#search_top_btn').removeClass('ser_open');
								$('#ser_inp_fc').focus();
									
							});



                                $(document).click(function() {
                                    if (isSearchBoxVisible) {
                                        $('#search_box_wrap').hide();
                                        $('#search_top_btn').removeClass('ser_open');
                                        isSearchBoxVisible = false;
                                    }
                                });

                                $('#search_box_wrap').click(function(event) {
                                    event.stopPropagation(); // Prevent click event from propagating to document
                                });
                                
                                $('.ser_label').click(function() {
                                    var dataKey = $(this).attr('data-key');
                                    $('#ser_inp_fc').val(dataKey);
                                });
                            });
                        </script>

						
						<script>
							function fsearchbox_submit(f) //검색
							{
								var stx = f.stx.value.trim();
								if (stx.length < 2) {
									alert("검색어는 두글자 이상 입력해주세요.");
									f.stx.select();
									f.stx.focus();
									return false;
								}

								// 검색에 많은 부하가 걸리는 경우 이 주석을 제거하세요.
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
							
							
						</script>
					</ul>
				</div>
				<!-- } -->
                
                <div class="cb"></div>
            </div>
            <div class="rows_gnb_wrap re_hide">
				<script>
					$(function(){
						$(".hd__mnav__ul .hd__mnav__list").mouseenter(function() {
						  $(this).toggleClass('on').addClass('on');
						});

						$(".hd__mnav__ul .hd__mnav__list").mouseleave(function() {
						  $(this).toggleClass('on').removeClass('on');
						});

					});
				</script>

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
            
        </div>
        <!-- } -->
		
		
			
			
    </header>
    <!-- } -->

<script>
	//20241005 추가
	$(function() {
		// 현재 페이지의 경로 및 쿼리 문자열을 모두 추출
		var currentUrl = window.location.pathname.replace(/\/$/, '') + window.location.search;

		// #container_title의 텍스트를 가져옴
		var containerTitle = $('#container_title').text().trim();

		var urlParams = new URLSearchParams(window.location.search);
		var caId = urlParams.get('ca_id') || '';

		//쇼핑몰 ca_id
		var caIdPrefixes = ['10', '20', '30', '40', '50'];

		function checkCaIdPrefix(href) {
			for (var i = 0; i < caIdPrefixes.length; i++) {
				var prefix = caIdPrefixes[i];
				if (caId.startsWith(prefix) && href.includes('ca_id=' + prefix)) {
					return true;
				}
			}
			return false;
		}

		// .sub__menu a 요소에서 href와 currentUrl을 비교
		var foundMatch = false;

		$('.sub__menu a').each(function() {
			var href = $(this).attr('href').replace(/\/$/, '');
			var menuText = $(this).text().trim();

			// href가 currentUrl과 정확히 일치할 경우에만 클래스 추가
			if (currentUrl === href || containerTitle === menuText || checkCaIdPrefix(href)) {
				$(this).closest('li').addClass('on active');
				$(this).closest('.hd__mnav__list').addClass('on active');
				foundMatch = true;
			}
		});

		if (!foundMatch && currentUrl.startsWith('/shop')) {
			$('.sub__menu a[href="/shop"]').closest('.hd__mnav__list').addClass('on active');
		}

		foundMatch = false;

		// .list__sub__menu__box a 요소에서 href와 currentUrl을 비교
		$('.list__sub__menu__box a').each(function() {
			var href = $(this).attr('href').replace(/\/$/, '');
			var menuText = $(this).text().trim();

			// href가 currentUrl과 정확히 일치할 경우에만 클래스 추가
			if (currentUrl === href || containerTitle === menuText || checkCaIdPrefix(href)) {
				$(this).closest('li').addClass('on active');
				$(this).closest('.list__sub__menu__box').addClass('on active');
				foundMatch = true;
			}
		});

		if (!foundMatch && currentUrl.startsWith('/shop')) {
			$('.list__sub__menu__box a[href="/shop"]').closest('.list__sub__menu__box').addClass('on active');
		}
	});

</script>