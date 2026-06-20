<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
include_once(G5_LIB_PATH.'/thumbnail.lib.php');

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css?ver='.G5_TIME_YMDHIS.'">', 0);

$loca = isset($_GET['loca']) ? $_GET['loca'] : '';
$write_table = $g5['write_prefix'] . $bo_table;
$status = isset($_GET['status']) ? $_GET['status'] : '';

if(isset($config['cf_kakao_js_apikey']) && $config['cf_kakao_js_apikey']) { 
    
                    if($loca == "서울") {
                        $lat = 37.4929603863248;
                        $lng = 126.989487610175;
                    } else if($loca == "인천") {
                        $lat = 37.4562562632513;
                        $lng = 126.704702815512;
                    } else if($loca == "세종특별자치시") {
                        $lat = 36.4800579897497;
                        $lng = 127.289039408864;
                    } else if($loca == "대전") {
                        $lat = 36.3503849976553;
                        $lng = 127.384633005948;
                    } else if($loca == "광주") {
                        $lat = 35.1595454;
                        $lng = 126.8526012;
                    } else if($loca == "대구") {
                        $lat = 35.8920272135627;
                        $lng = 128.598409309488;
                    } else if($loca == "울산") {
                        $lat = 35.5379472830778;
                        $lng = 129.311256608093;
                    } else if($loca == "부산") {
                        $lat = 35.17992598569;
                        $lng = 129.07509523457;
                    } else if($loca == "제주특별자치도") {
                        $lat = 33.2555817572486;
                        $lng = 126.510527414272;
                    } else if($loca == "경기") {
                        $lat = 37.2746661643172;
                        $lng = 127.009619860326;
                    } else if($loca == "강원특별자치도") {
                        $lat = 37.8800729197963;
                        $lng = 127.727907820318;
                    } else if($loca == "충북") {
                        $lat = 36.6353867908159;
                        $lng = 127.491428436987;
                    } else if($loca == "충남") {
                        $lat = 36.6589926132573;
                        $lng = 126.672803575984;
                    } else if($loca == "전북특별자치도") {
                        $lat = 35.8194473147472;
                        $lng = 127.106373795093;
                    } else if($loca == "전남") {
                        $lat = 34.8174988528003;
                        $lng = 126.465423854957;
                    } else if($loca == "경북") {
                        $lat = 36.5761205474728;
                        $lng = 128.505722686385;
                    } else if($loca == "경남") {
                        $lat = 35.2378032514675;
                        $lng = 128.691940442146;
                    } else { 
                        $lat = 37.4929603863248;
                        $lng = 126.989487610175;
                    }

}

//검색 재설정
function get_board_sfl_select_options2($sfl){
    global $is_admin;
    $str = '';

    $str .= '<option value="wr_subject" '.get_selected($sfl, 'wr_subject').'>상품명</option>';
    $str .= '<option value="wr_9" '.get_selected($sfl, 'wr_9', true).'>광역시/도</option>';
    $str .= '<option value="wr_10" '.get_selected($sfl, 'wr_10').'>시/군/구</option>';
    $str .= '<option value="mb_id" '.get_selected($sfl, 'mb_id', true).'>판매자 ID</option>';
    
    return run_replace('get_board_sfl_select_options2', $str, $sfl);
}

//카테고리 재설정
$category_option2 = '';
if ($board['bo_use_category']) {
    $is_category = true;
    $category_href = get_pretty_url($bo_table);

    $category_option2 .= '<li><a href="'.$category_href.'"';
    if ($sca=='')
        $category_option2 .= ' id="bo_cate_on"';
    $category_option2 .= '>전체</a></li>';

    $categories = explode('|', $board['bo_category_list']); // 구분자가 , 로 되어 있음
    for ($i=0; $i<count($categories); $i++) {
        $category = trim($categories[$i]);
        if ($category=='') continue;
        $category_option2 .= '<li><a href="'.(get_pretty_url($bo_table,'','sop=and&sfl=wr_9&stx='.$loca.'&loca='.$loca.'&sca='.urlencode($category))).'"';
        $category_msg = '';
        if ($category==$sca) { // 현재 선택된 카테고리라면
            $category_option2 .= ' id="bo_cate_on"';
            $category_msg = '<span class="sound_only">열린 분류 </span>';
        }
        $category_option2 .= '>'.$category_msg.$category.'</a></li>';
    }
}

?>


<link rel="stylesheet" href="<?php echo $board_skin_url ?>/style.php?columns=<?php echo $board['bo_gallery_cols']; ?>">

<?php if(isset($config['cf_kakao_js_apikey']) && $config['cf_kakao_js_apikey']) {  ?>

    <script type="text/javascript" src="//dapi.kakao.com/v2/maps/sdk.js?appkey=<?php echo $config['cf_kakao_js_apikey'] ?>&libraries=services"></script>
    <div id="map" class="v_maps" style="width: 100%; height: 30vh; margin:0px; margin-bottom:30px; border-radius:6px;"></div>

    <script>
        var mapContainer = document.getElementById('map'), // 지도를 표시할 div 
            mapOption = {
                center: new kakao.maps.LatLng('<?php echo isset($lat) ? $lat : 0; ?>', '<?php echo isset($lng) ? $lng : 0; ?>'), // 지도의 중심좌표
                level: 12 // 지도 초기 확대레벨
            };

        var map = new kakao.maps.Map(mapContainer, mapOption);

        // 일반 지도와 스카이뷰로 지도 타입을 전환할 수 있는 지도타입 컨트롤을 생성합니다
        var mapTypeControl = new daum.maps.MapTypeControl();

        // 지도에 컨트롤을 추가해야 지도위에 표시됩니다
        // daum.maps.ControlPosition은 컨트롤이 표시될 위치를 정의하는데 TOPRIGHT는 오른쪽 위를 의미합니다
        map.addControl(mapTypeControl, daum.maps.ControlPosition.TOPRIGHT);

        // 지도 확대 축소를 제어할 수 있는 줌 컨트롤을 생성합니다
        var zoomControl = new daum.maps.ZoomControl();
        map.addControl(zoomControl, daum.maps.ControlPosition.RIGHT);

        <?php 

        $ca = str_replace("/", "", isset($_GET['sca']) ? $_GET['sca'] : ''); 
                                                                                 

        if(isset($ca) && $ca) { 
            $sql = " SELECT * FROM {$write_table} WHERE ca_name = '{$ca}' AND (wr_8 = '판매중' OR wr_8 = '') ORDER BY wr_id ASC ";
        } else if(isset($loca) && $loca) { 
            $sql = " SELECT * FROM {$write_table} WHERE wr_9 = '{$loca}' AND (wr_8 = '판매중' OR wr_8 = '') ORDER BY wr_id ASC ";
        } else { 
            $sql = "SELECT * FROM {$write_table} WHERE (wr_8 = '판매중' OR wr_8 = '') ORDER BY wr_id ASC ";
        }

        $result = sql_query($sql);
        $cnt = 0;
        while ($row = sql_fetch_array($result)) { 

            //필드분할
            $wr_1 = isset($row["wr_1"]) ? explode("|", $row["wr_1"]) : [];
            $wr_2 = isset($row["wr_2"]) ? explode("|", $row["wr_2"]) : [];
            $wr_3 = isset($row["wr_3"]) ? explode("|", $row["wr_3"]) : [];
            $wr_4 = isset($row["wr_4"]) ? explode("|", $row["wr_4"]) : [];
            $wr_5 = isset($row["wr_5"]) ? explode("|", $row["wr_5"]) : [];

            if(isset($wr_3[4]) && isset($wr_3[5])) {
                $thumb = get_list_thumbnail($board['bo_table'], $row['wr_id'], 100, 100, false, true);
                if(isset($thumb['src'])) {
                    $img_content = $thumb['src'];
                } else { 
                    $img_content = '';
                }
        ?>


        var imageSrc = '<?php echo $board_skin_url ?>/img/pin.svg',


        imageSize = new kakao.maps.Size(24, 35), // 마커이미지의 크기입니다
        imageOption = {
            offset: new kakao.maps.Point(12, 35)
        }; // 마커이미지의 옵션입니다. 마커의 좌표와 일치시킬 이미지 안에서의 좌표를 설정합니다.

        // 마커의 이미지정보를 가지고 있는 마커이미지를 생성합니다
        var markerImage = new kakao.maps.MarkerImage(imageSrc, imageSize, imageOption),
            markerPosition = new kakao.maps.LatLng('<?php echo $wr_3[4] ?>', '<?php echo $wr_3[5] ?>'); // 마커가 표시될 위치입니다

        // 마커를 생성합니다
        var marker = new kakao.maps.Marker({
            position: markerPosition,
            image: markerImage
        });

        // 마커가 지도 위에 표시되도록 설정합니다
        marker.setMap(map);

        var wr_subject = '<?php echo str_replace("'", "\\'", get_text($row['wr_subject'], 1)); ?>';
        
        <?php if(isset($row['wr_9'])) { ?>
            var wr_9 = '<?php echo isset($row['wr_9']) ? str_replace("'", "\\'", get_text($row['wr_9'], 1)) : ''; ?>';
            var wr_10 = '<?php echo isset($row['wr_10']) ? str_replace("'", "\\'", get_text($row['wr_10'], 1)) : ''; ?>';
        <?php } ?>
        
        // 커스텀 오버레이에 표시할 컨텐츠 입니다
        var content = '<div class="wrap">' +
            '    <div class="info">' +
            '        <div class="body">' +
            '            <div class="desc">' +
            '                <img src="<?php echo $board_skin_url ?>/img/close_black_24dp.svg" class="close" onclick="closeOverlay_<?php echo $row['wr_id'] ?>()" title="닫기">' +
            '                <div class="titles"><a href="<?php echo G5_BBS_URL ?>/board.php?bo_table=<?php echo $bo_table ?>&wr_id=<?php echo $row['wr_id']; ?>" class="cut80">' + wr_subject + '</a></div>' +
            '                <div class="sub3 cut80"><?php if(isset($row['ca_name']) && $row['ca_name']) { ?><?php echo $row['ca_name']; ?>　<?php } ?>' + wr_9 + ' ' + wr_10 + '</div>' +
            '            </div>' +
            '        </div>' +
            '    </div>' +
            '</div>';

        // 마커 위에 커스텀오버레이를 표시합니다
        var position = new kakao.maps.LatLng('<?php echo $wr_3[4] ?>', '<?php echo $wr_3[5] ?>');

        // 마커를 중심으로 커스텀 오버레이를 표시하기위해 CSS를 이용해 위치를 설정
        var overlay_<?php echo $row['wr_id'] ?> = new kakao.maps.CustomOverlay({
            content: content,
            map: map,
            position: position,
            yAnchor: 1
        });

        // 마커를 클릭했을 때 커스텀 오버레이를 표시합니다
        kakao.maps.event.addListener(marker, 'click', function() {
            overlay_<?php echo $row['wr_id'] ?>.setMap(map);
        });

        // 커스텀 오버레이를 닫기 위해 호출되는 함수입니다 
        function closeOverlay_<?php echo $row['wr_id'] ?>() {
            overlay_<?php echo $row['wr_id'] ?>.setMap(null);
        }

        overlay_<?php echo $row['wr_id'] ?>.setMap(null);

        <?php
            $cnt++;
        }
    }
    ?>
    </script>


<?php } ?>


<div class="rb_bbs_wrap" id="scroll_container" style="width:<?php echo $width; ?>">


    <form name="fboardlist" id="fboardlist" action="<?php echo G5_BBS_URL; ?>/board_list_update.php" onsubmit="return fboardlist_submit(this);" method="post">
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
        

        <ul class="wrap_selec">
                <div>
                    
                    <select name="loca" id="loca" class="select">
                        <option value="">전체지역</option>
                        <option value="서울">서울특별시</option>
                        <option value="인천">인천광역시</option>
                        <option value="세종특별자치시">세종특별자치시</option>
                        <option value="대전">대전광역시</option>
                        <option value="광주">광주광역시</option>
                        <option value="대구">대구광역시</option>
                        <option value="울산">울산광역시</option>
                        <option value="부산">부산광역시</option>
                        <option value="경기">경기도</option>
                        <option value="강원특별자치도">강원특별자치도</option>
                        <option value="충북">충청북도</option>
                        <option value="충남">충청남도</option>
                        <option value="전북특별자치도">전북특별자치도</option>
                        <option value="전남">전라남도</option>
                        <option value="경북">경상북도</option>
                        <option value="경남">경상남도</option>
                        <option value="제주특별자치도">제주특별자치도</option>
                    </select>
                    <script>
                        $("#loca").change(function() {
                            location.href = "?bo_table=<?php echo $bo_table ?>&sop=and&sfl=wr_9&stx=" + encodeURIComponent($(this).val()) + "&loca=" + encodeURIComponent($(this).val());
                        })

                        $("#loca").val("<?php echo $loca ?>").prop("selected", true);
                    </script>
                    
                </div>
                
        </ul>

        

        <!-- 갯수, 전체선택 { -->
        <ul class="rb_bbs_top" <?php if(isset($config['cf_kakao_js_apikey']) && $config['cf_kakao_js_apikey']) {  ?>style="top:0px;"<?php } ?>>
            
            

            <?php if($board['bo_read_point'] || $board['bo_write_point'] || $board['bo_comment_point'] || $board['bo_download_point']) { ?>
            <li class="point_info_btns_wrap">
                <button type="button" class="point_info_btns" id="point_info_opens_btn">
                    <i><svg width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10 0C15.523 0 20 4.477 20 10C20 15.523 15.523 20 10 20C4.477 20 0 15.523 0 10C0 4.477 4.477 0 10 0ZM11.5 5H9C8.46957 5 7.96086 5.21071 7.58579 5.58579C7.21071 5.96086 7 6.46957 7 7V14C7 14.2652 7.10536 14.5196 7.29289 14.7071C7.48043 14.8946 7.73478 15 8 15C8.26522 15 8.51957 14.8946 8.70711 14.7071C8.89464 14.5196 9 14.2652 9 14V12H11.5C12.4283 12 13.3185 11.6313 13.9749 10.9749C14.6313 10.3185 15 9.42826 15 8.5C15 7.57174 14.6313 6.6815 13.9749 6.02513C13.3185 5.36875 12.4283 5 11.5 5ZM11.5 7C11.8978 7 12.2794 7.15804 12.5607 7.43934C12.842 7.72064 13 8.10218 13 8.5C13 8.89782 12.842 9.27936 12.5607 9.56066C12.2794 9.84196 11.8978 10 11.5 10H9V7H11.5Z" fill="#09244B" />
                        </svg></i>
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
                <?php echo $category_option2 ?>
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

        <ul class="rb_bbs_list rb_gallery_grid" <?php if (!$is_category) { ?>style="padding-top:20px !important;"<?php } ?>>

            <?php 

            for ($i=0; $i<count($list); $i++) { 
            
            $thumb = get_list_thumbnail($board['bo_table'], $list[$i]['wr_id'], $board['bo_gallery_width'], $board['bo_gallery_height'], false, true);
            
            if($thumb['src']) {
                if (strstr($list[$i]['wr_option'], 'secret')) {
                    $img_content = '<img src="'.G5_THEME_URL.'/rb.img/sec_image.png" alt="'.$thumb['alt'].'" >';
                } else {
                    $img_content = '<img src="'.$thumb['src'].'" alt="'.$thumb['alt'].'" >';
                }
            } else { 
                $img_content = '<img src="'.G5_THEME_URL.'/rb.img/no_image.png" alt="이미지가 없습니다." >';
            }
            

            $wr_href = $list[$i]['href'];
            $sec_txt = '<span style="opacity:0.6">작성자 및 관리자 외 열람할 수 없습니다.<br>비밀글 기능으로 보호된 글입니다.</span>';
            
            $wr_content = preg_replace("/<(.*?)\>/","",$list[$i]['wr_content']);
            $wr_content = preg_replace("/&nbsp;/","",$wr_content);
            $wr_content = get_text($wr_content);
            
            //필드분할
            $wr_1 = isset($list[$i]["wr_1"]) ? explode("|", $list[$i]["wr_1"]) : [];
            $wr_2 = isset($list[$i]["wr_2"]) ? explode("|", $list[$i]["wr_2"]) : [];
            $wr_3 = isset($list[$i]["wr_3"]) ? explode("|", $list[$i]["wr_3"]) : [];
            $wr_4 = isset($list[$i]["wr_4"]) ? explode("|", $list[$i]["wr_4"]) : [];
            $wr_5 = isset($list[$i]["wr_5"]) ? explode("|", $list[$i]["wr_5"]) : [];
            
        ?>



            <div class="bbs_prd_list">
                <div class="g_img">
                    <a href="<?php echo $wr_href ?>"><?php echo $img_content ?></a>
                    
                    <?php if(isset($list[$i]['wr_8']) && $list[$i]['wr_8'] == "예약중") { ?>
                    <a href="<?php echo $wr_href ?>">
                    <span class="po_rel_blank">
                        <dd class="font-R">
                        <img src="<?php echo $board_skin_url ?>/img/deal_ico_y.svg" style="height:40px; width:auto;"><br><br>
                        예약중
                        </dd>
                    </span>
                    </a>
                    <?php } else if(isset($list[$i]['wr_8']) && $list[$i]['wr_8'] == "판매완료") { ?>
                    <a href="<?php echo $wr_href ?>">
                    <span class="po_rel_blank">
                        <dd class="font-R">
                        판매완료
                        </dd>
                    </span>
                    </a>
                    <?php } ?>
                    
                    <span class="list_prof_img"><?php echo get_member_profile_img($list[$i]['mb_id'], 40, 40); ?></span>
                </div>
                <div class="bbs_prd_list_wrap" onclick="location.href='<?php echo $wr_href ?>';">

                    <ul class="bbs_prd_list_con" <?php if(isset($list[$i]['wr_9']) && $list[$i]['wr_9']) { ?><?php } else { ?>style="padding-bottom:18px;"<?php } ?>>



                        <?php if(isset($list[$i]['ca_name']) && $list[$i]['ca_name']) { ?>
                        <li class="bbs_prd_list_con_li1 font-B">
                            <?php echo isset($list[$i]['ca_name']) ? $list[$i]['ca_name'] : ''; ?>
                        </li>
                        <?php } ?>
                       

                        <li class="bbs_prd_list_con_li2 cut"><a href="<?php echo $wr_href ?>" class="font-B"><?php echo $list[$i]['subject'] ?></a></li>
                        <li class="bbs_prd_list_con_li3"><?php echo passing_time3($list[$i]['wr_datetime']) ?>　<?php echo get_text($list[$i]['wr_name']); ?></li>

                        <?php if($list[$i]['wr_4']) { ?>
                        <div class="bbs_prd_list_con_li4 mt-10">
                            <li>
                                <?php if(isset($list[$i]['wr_7']) && $list[$i]['wr_7']) { ?>
                                <dd class="font-B main_color"><?php if(isset($list[$i]['wr_8']) && $list[$i]['wr_8'] == "판매완료") { ?><strike style="opacity:0.5; color:#999"><?php } ?>별도협의<?php if(isset($list[$i]['wr_8']) && $list[$i]['wr_8'] == "판매완료") { ?></strike><?php } ?></dd>
                                <?php } else { ?>
                                <dd class="font-B main_color"><?php if(isset($list[$i]['wr_8']) && $list[$i]['wr_8'] == "판매완료") { ?><strike style="opacity:0.5; color:#999"><?php } ?><?php echo isset($list[$i]['wr_6']) ? number_format($list[$i]['wr_6']) : ''; ?>원<?php if(isset($list[$i]['wr_8']) && $list[$i]['wr_8'] == "판매완료") { ?></strike><?php } ?></dd>
                                <?php } ?>
                            </li>
                            <!--
                            <li>
                                <span class="font-R"><?php echo $list[$i]['wr_4'] ?></span>
                            </li>
                            -->
                        </div>
                        <?php } ?>


                    </ul>


                </div>

                <?php if(isset($list[$i]['wr_9']) && $list[$i]['wr_9']) { ?>
                    <div class="lists_rc_p1">
                        <li class="">
                            <dd><img src="<?php echo $board_skin_url ?>/img/ico_pin.svg"></dd>
                        </li>
                        <li class="font-B color-999">
                            <dd><?php echo get_text($list[$i]['wr_9']); ?> <?php echo get_text($list[$i]['wr_10']); ?></dd>
                        </li>
                    </div>

                <?php } ?>

                

                <?php if ($is_checkbox) { ?>
                <div class="gall_chk_is">
                    <input type="checkbox" name="chk_wr_id[]" value="<?php echo $list[$i]['wr_id'] ?>" id="chk_wr_id_<?php echo $i ?>" class="">
                    <label for="chk_wr_id_<?php echo $i ?>"></label>
                </div>
                <?php } ?>
                
                <?php if($list[$i]['icon_new'] || $list[$i]['icon_hot'] || $list[$i]['is_notice']) { ?>
                <li class="gallery-item-ico lists_rc_p2">
                    <?php if ($list[$i]['is_notice']) echo "<span class=\"bbs_list_label label5\">프리미엄</span>"; ?>
                    <?php if ($list[$i]['icon_new']) echo "<span class=\"bbs_list_label label3\">신규</span>"; ?>
                    <?php if ($list[$i]['icon_hot']) echo "<span class=\"bbs_list_label label1\">인기</span>"; ?>
                </li>
                <?php } ?>


            </div>


            <?php } ?>



        </ul>

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
                <?php echo get_board_sfl_select_options2($sfl); ?>
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
    $('.bo_sch_bg, .bo_sch_cls').click(function() {
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

        for (var i = 0; i < f.length; i++) {
            if (f.elements[i].name == "chk_wr_id[]")
                f.elements[i].checked = sw;
        }
    }

    function fboardlist_submit(f) {
        var chk_count = 0;

        for (var i = 0; i < f.length; i++) {
            if (f.elements[i].name == "chk_wr_id[]" && f.elements[i].checked)
                chk_count++;
        }

        if (!chk_count) {
            alert(document.pressed + "할 게시물을 하나 이상 선택하세요.");
            return false;
        }

        if (document.pressed == "선택복사") {
            select_copy("copy");
            return;
        }

        if (document.pressed == "선택이동") {
            select_copy("move");
            return;
        }

        if (document.pressed == "선택삭제") {
            if (!confirm("선택한 게시물을 정말 삭제하시겠습니까?\n\n한번 삭제한 자료는 복구할 수 없습니다\n\n답변글이 있는 게시글을 선택하신 경우\n답변글도 선택하셔야 게시글이 삭제됩니다."))
                return false;

            f.removeAttribute("target");
            f.action = g5_bbs_url + "/board_list_update.php";
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
        f.action = g5_bbs_url + "/move.php";
        f.submit();
    }

    // 게시판 리스트 관리자 옵션
    jQuery(function($) {
        $(".btn_more_opt.is_list_btn").on("click", function(e) {
            e.stopPropagation();
            $(".more_opt.is_list_btn").toggle();
        });
        $(document).on("click", function(e) {
            if (!$(e.target).closest('.is_list_btn').length) {
                $(".more_opt.is_list_btn").hide();
            }
        });
    });
</script>
<?php } ?>
<!-- } 게시판 목록 끝 -->