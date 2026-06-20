<?php
if (!defined("_GNUBOARD_")) exit; // 개별 페이지 접근 불가
include_once(G5_LIB_PATH.'/thumbnail.lib.php');

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css?ver='.G5_TIME_YMDHIS.'">', 0);


//필드분할
$wr_1 = isset($view["wr_1"]) ? explode("|", $view["wr_1"]) : [];
$wr_2 = isset($view["wr_2"]) ? explode("|", $view["wr_2"]) : [];
$wr_3 = isset($view["wr_3"]) ? explode("|", $view["wr_3"]) : [];
$wr_4 = isset($view["wr_4"]) ? explode("|", $view["wr_4"]) : [];
$wr_5 = isset($view["wr_5"]) ? explode("|", $view["wr_5"]) : [];

?>
<style>
#scroll_container {margin-top: 20px;}
#scroll_container .rb_bbs_top{display: none;}
</style>

<script src="<?php echo G5_JS_URL; ?>/viewimageresize.js"></script>


<div class="rb_bbs_wrap" style="width:<?php echo $width; ?>">
       
    <div class="btns_gr_wrap">
      
       <!-- $rb_core['sub_width'] 는 반드시 포함해주세요 (환경설정 > 서브가로폭에 따른 버튼의 위치 설정) -->
       <div class="sub" style="width:<?php echo $rb_core['sub_width'] ?>px;">
            
            <div class="btns_gr">
               <?php if ($admin_href) { ?>
               <button type="button" class="fl_btns" onclick="window.open('<?php echo $admin_href ?>');">
               <img src="<?php echo $board_skin_url ?>/img/ico_set.svg">
               <span class="tooltips">관리</span>
               </button>
               <?php } ?>
               
               <?php if ($scrap_href) { ?>
               <a class="fl_btns" href="<?php echo $scrap_href;  ?>" target="_blank" onclick="win_scrap(this.href); return false;">
               <img src="<?php echo $board_skin_url ?>/img/ico_scr.svg">
               <span class="tooltips">스크랩</span>
               </a>
               <?php } ?>
               
               <?php if ($list_href) { ?>
               <button type="button" class="fl_btns" onclick="location.href='<?php echo $list_href ?>';">
               <img src="<?php echo $board_skin_url ?>/img/ico_list.svg">
               <span class="tooltips">목록</span>
               </button>
               <?php } ?>

               
               <?php if ($write_href) { ?>
               <button type="button" class="fl_btns main_color_bg" onclick="location.href='<?php echo $write_href ?>';">
               <img src="<?php echo $board_skin_url ?>/img/ico_write.svg">
               <span class="tooltips">글 등록</span>
               </button>
               <?php } ?>
               
            </div>
            
            <div class="cb"></div>
        </div>
    </div>
    
    
    <div class="bbs_sv_wrap">
      
      
        <div class="rc_wrap1">
            <?php if ($category_name) { ?>
            <ul class="rc_wrap1_ul1 font-R">
                <?php echo get_text($view['ca_name']);?>
            </ul>
            <?php } ?>
            <ul class="rc_wrap1_ul2 font-B"><?php echo get_text($view['wr_subject']);?></ul>
            
            <?php if(isset($view['wr_8']) && $view['wr_8']) { ?>
            <ul class="rc_wrap1_ul3 font-B"><?php echo isset($view['wr_8']) ? get_text($view['wr_8'], true) : ''; ?></ul>
            <?php } ?>
            
            <?php if(isset($view['wr_6']) && $view['wr_6']) { ?>
            <ul class="rc_wrap1_ul3"><?php echo isset($view['wr_6']) ? get_text($view['wr_6'], true) : ''; ?></ul>
            <?php } ?>
            
            
            <div class="rc_wrap1_infos">
                
                <ul>
                    <li class="rc_wrap1_infos_ico"><i><img src="<?php echo $board_skin_url ?>/img/rc_ico1.svg"></i></li>
                    <li class="rc_wrap1_infos_txt">
                        <dd><?php echo $wr_5[1] ?></dd>
                        <dd class="font-B main_color"><?php if(isset($wr_5[0]) && $wr_5[0]) { ?><?php echo get_text($wr_5[0]); ?><?php } else { ?>미정<?php } ?></dd>
                    </li>
                </ul>
                

                <?php if(isset($wr_4[0]) && $wr_4[0]) { ?>
                <ul>
                    <li class="rc_wrap1_infos_ico"><i><img src="<?php echo $board_skin_url ?>/img/rc_ico2.svg"></i></li>
                    <li class="rc_wrap1_infos_txt">
                        <dd>고용형태</dd>
                        <dd class="font-B"><?php echo get_text($wr_4[0]); ?></dd>
                    </li>
                </ul>
                <?php } ?>
                
                
                <ul>
                    <li class="rc_wrap1_infos_ico"><i><img src="<?php echo $board_skin_url ?>/img/rc_ico3.svg"></i></li>
                    <li class="rc_wrap1_infos_txt">
                        <dd>모집인원</dd>
                        <dd class="font-B"><?php if(isset($wr_2[1]) && $wr_2[1]) { ?><?php echo get_text($wr_2[1]); ?><?php } else { ?>0명<?php } ?></dd>
                    </li>
                </ul>

                
                <?php if(isset($view['wr_9']) && $view['wr_9']) { ?>
                <ul>
                    <li class="rc_wrap1_infos_ico"><i><img src="<?php echo $board_skin_url ?>/img/rc_ico4.svg"></i></li>
                    <li class="rc_wrap1_infos_txt">
                        <dd>근무지역</dd>
                        <dd class="font-B"><?php echo get_text($view['wr_9']); ?></dd>
                    </li>
                </ul>
                <?php } ?>
                
            </div>
            
        </div>
      
    
        <h1 class="rc_sub_title">모집/근무조건</h1>
        <div class="rc_wrap2">
            <div class="rc_wrap2_div">
                <ul>
                    <dt>급여</dt>
                    <dd>
                        <div class="rc_pri">
                            <?php if(isset($wr_5[1]) && $wr_5[1]) { ?><span class="rc_pri_ico"><?php echo isset($wr_5[1]) ? get_text($wr_5[1]) : ''; ?></span><?php } ?>
                            <?php if(isset($wr_5[0]) && $wr_5[0]) { ?><span class="rc_pri_txt font-B"><?php echo isset($wr_5[0]) ? get_text($wr_5[0]) : ''; ?></span><?php } else {  ?>미정<?php } ?>
                        </div>
                    </dd>
                </ul>
                
                
                <ul>
                    <dt>고용형태</dt>
                    <dd>
                      
                        <?php if(isset($wr_4[0]) && $wr_4[0] || isset($wr_4[1]) && $wr_4[1]) { ?>
                            <?php if(isset($wr_4[0]) && $wr_4[0]) { ?><?php echo isset($wr_4[0]) ? get_text($wr_4[0]) : ''; ?><?php } ?>
                            <?php if(isset($wr_4[1]) && $wr_4[1]) { ?> (<?php echo isset($wr_4[1]) ? get_text($wr_4[1]) : ''; ?>)<?php } ?>
                        <?php } else { ?>
                        미정
                        <?php } ?>

                    </dd>
                </ul>

                <?php if(isset($wr_1[1]) && $wr_1[1] || isset($wr_1[2]) && $wr_1[2] || isset($wr_1[3]) && $wr_1[3]) { ?>
                <ul>
                    <dt>모집기간</dt>
                    <dd>
                        <?php if(isset($wr_1[2]) && $wr_1[2]) { ?>
                            <?php echo isset($wr_1[2]) ? get_text($wr_1[2]) : ''; ?>
                        <?php } else { ?>
                            <?php echo isset($wr_1[0]) ? get_text($wr_1[0]) : ''; ?>
                            <?php if(isset($wr_1[1]) && $wr_1[1]) { ?> ~ <?php } ?>
                            <?php echo isset($wr_1[1]) ? get_text($wr_1[1]) : ''; ?>
                        <?php } ?>
                    </dd>
                </ul>
                <?php } ?>
                
                <ul>
                    <dt>모집인원</dt>
                    <dd>
                        <?php if(isset($wr_2[1]) && $wr_2[1]) { ?>
                            <?php echo isset($wr_2[1]) ? get_text($wr_2[1]) : ''; ?>
                        <?php } else { ?>
                            0명 (인원미정)
                        <?php } ?>
                    </dd>
                </ul>

            </div>
            
            
            <div class="rc_wrap2_div">
               
                <ul>
                    <dt>성별</dt>
                    <dd>
                        <?php if(isset($wr_2[3]) && $wr_2[3]) { ?>
                            <?php echo isset($wr_2[3]) ? get_text($wr_2[3]) : ''; ?>
                        <?php } else { ?>
                            성별 무관
                        <?php } ?>
                    </dd>
                </ul>
                

                <ul>
                    <dt>학력</dt>
                    <dd>
                        <?php if(isset($wr_2[0]) && $wr_2[0]) { ?>
                            <?php echo isset($wr_2[0]) ? get_text($wr_2[0]) : ''; ?>
                        <?php } else { ?>
                            학력 무관
                        <?php } ?>
                    </dd>
                </ul>

                
                <ul>
                    <dt>연령</dt>
                    <dd>
                        <?php if(isset($wr_2[2]) && $wr_2[2]) { ?>
                            <?php echo isset($wr_2[2]) ? get_text($wr_2[2]) : ''; ?>
                        <?php } else { ?>
                            연령 무관
                        <?php } ?>
                    </dd>
                </ul>
                
                <?php if(isset($wr_2[4]) && $wr_2[4]) { ?>
                <ul>
                    <dt>우대사항</dt>
                    <dd>
                        <?php echo isset($wr_2[4]) ? get_text($wr_2[4]) : ''; ?>
                    </dd>
                </ul>
                <?php } ?>
            </div>
            
        </div>
      
        <?php 
        if(isset($wr_3[0]) && $wr_3[0]) {
        if(isset($config['cf_kakao_js_apikey']) && $config['cf_kakao_js_apikey']) { 
  
            //기본좌표
            if (!isset($wr_3[4])) {
                $wr_3[4] = 37.566400714093284;
            }
            if (!isset($wr_3[5])) {
                $wr_3[5] = 126.9785391897507;
            }


        ?>
        <h1 class="rc_sub_title">근무지역</h1>
        
        <div class="rc_wrap3">
                    <script type="text/javascript" src="//dapi.kakao.com/v2/maps/sdk.js?appkey=<?php echo $config['cf_kakao_js_apikey'] ?>&libraries=services"></script>
                    <div style="background-color:#f9f9f9; width:100%; height:300px; border-radius:10px;" id="map"></div>
                    
                    <script>
                    var mapContainer = document.getElementById('map'), // 지도를 표시할 div 
                        mapOption = {
                            center: new daum.maps.LatLng('<?php echo $wr_3[4] ?>', '<?php echo $wr_3[5] ?>'), // 지도의 중심좌표
                            level: 3 // 지도의 확대 레벨
                        };

                    // 지도를 생성
                    var map = new daum.maps.Map(mapContainer, mapOption);

                    // 일반 지도와 스카이뷰로 지도 타입을 전환할 수 있는 지도타입 컨트롤을 생성합니다
                    var mapTypeControl = new daum.maps.MapTypeControl();

                    // 지도에 컨트롤을 추가해야 지도위에 표시됩니다
                    // daum.maps.ControlPosition은 컨트롤이 표시될 위치를 정의하는데 TOPRIGHT는 오른쪽 위를 의미합니다
                    map.addControl(mapTypeControl, daum.maps.ControlPosition.TOPRIGHT);

                    // 지도 확대 축소를 제어할 수 있는  줌 컨트롤을 생성합니다
                    var zoomControl = new daum.maps.ZoomControl();
                    map.addControl(zoomControl, daum.maps.ControlPosition.RIGHT);

                    // 주소-좌표 변환 객체 생성
                    var geocoder = new daum.maps.services.Geocoder();

                    // 마커
                    var marker = new daum.maps.Marker({
                        map: map,
                        // 지도 중심좌표에 마커를 생성
                        position: map.getCenter()
                    });

                    //마커를 기준으로 가운데 정렬이 될 수 있도록 추가
                    var markerPosition = marker.getPosition(); 
                    map.relayout();
                    map.setCenter(markerPosition);



                    //브라우저가 리사이즈될때 지도 리로드 //아이폰 이슈
                    /*
                    $(window).on('resize', function () {
                        var markerPosition = marker.getPosition(); 
                        map.relayout();
                        map.setCenter(markerPosition)
                    });
                    */


                    </script>
                    <div class="flex_gbtns">
                    <span class="rc_sub_title2 font-R"><?php echo isset($wr_3[0]) ? $wr_3[0] : ''; ?> <?php echo isset($wr_3[1]) ? get_text($wr_3[1]) : ''; ?></span>
                    <a href="https://map.kakao.com/?q=<?php echo isset($wr_3[4]) ? $wr_3[4] : ''; ?> <?php echo isset($wr_3[5]) ? get_text($wr_3[5]) : ''; ?>" target="_blank">길찾기</a>
                    </div>
        </div>
        <?php } ?>
        <?php } ?>
        
        <?php if(isset($view['wr_7']) && $view['wr_7']) { ?>
        <h1 class="rc_sub_title">지원방법</h1>
        <div class="rc_wrap4">
            <div class="rc_wrap4_div">
                <?php echo isset($view['wr_7']) ? get_text($view['wr_7']) : ''; ?>
            </div>
            
        </div>
        <?php } ?>
      
      

        
        
        
        <!-- 기존 { -->
       
            <div class="btns_gr_wrap">

               <!-- $rb_core['sub_width'] 는 반드시 포함해주세요 (환경설정 > 서브가로폭에 따른 버튼의 위치 설정) -->
               <div class="sub" style="width:<?php echo $rb_core['sub_width'] ?>px;">

                    <div class="btns_gr">
                       <?php if ($admin_href) { ?>
                       <button type="button" class="fl_btns" onclick="window.open('<?php echo $admin_href ?>');">
                       <img src="<?php echo $board_skin_url ?>/img/ico_set.svg">
                       <span class="tooltips">관리</span>
                       </button>
                       <?php } ?>

                       <?php if ($scrap_href) { ?>
                       <a class="fl_btns" href="<?php echo $scrap_href;  ?>" target="_blank" onclick="win_scrap(this.href); return false;">
                       <img src="<?php echo $board_skin_url ?>/img/ico_scr.svg">
                       <span class="tooltips">스크랩</span>
                       </a>
                       <?php } ?>

                       <?php if ($list_href) { ?>
                       <button type="button" class="fl_btns" onclick="location.href='<?php echo $list_href ?>';">
                       <img src="<?php echo $board_skin_url ?>/img/ico_list.svg">
                       <span class="tooltips">목록</span>
                       </button>
                       <?php } ?>


                       <?php if ($write_href) { ?>
                       <button type="button" class="fl_btns main_color_bg" onclick="location.href='<?php echo $write_href ?>';">
                       <img src="<?php echo $board_skin_url ?>/img/ico_write.svg">
                       <span class="tooltips">글 등록</span>
                       </button>
                       <?php } ?>

                    </div>

                    <div class="cb"></div>
                </div>
            </div>


            


            <!-- 본문 내용 시작 { -->
            <h1 class="rc_sub_title">상세요강</h1>
            <div id="bo_v_con" style="padding-top:0px;">


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

                <?php $original_content = isset($view['content']) ? $view['content'] : ''; ?>
                <?php echo get_view_thumbnail($view['content']); ?>
            </div>
            
            
            <p class="info_btm_bg">본 정보는 등록자가 제공한 자료를 바탕으로 <?php echo isset($config['cf_title']) ? $config['cf_title'] : ''; ?> 에서 편집 및 그 표현방법을 수정하여 완성한 것입니다. 본 정보는 <?php echo isset($config['cf_title']) ? $config['cf_title'] : ''; ?>의 동의없이 무단전재 또는 재배포, 재가공할 수 없으며, 게재된 채용기업과 채용담당자의 정보는 구직활동 이외의 용도로 사용할 수 없습니다. <?php echo isset($config['cf_title']) ? $config['cf_title'] : ''; ?>은(는) 등록자가 게재한 자료에 대한 오류와 사용자가 이를 신뢰하여 취한 조치에 대해 책임을 지지 않습니다.</p>
            
            
            


            <div id="bo_v_share">
                    <?php include_once(G5_SNS_PATH."/view.sns.skin.php"); ?>
                    <ul class="copy_urls">
                        <li>
                            <a href="javascript:void(0);" id="data-copy">
                               <img src="<?php echo $board_skin_url ?>/img/ico_sha.png" alt="공유링크 복사" width="32">
                            </a>
                        </li>
                        <?php
                        $currents_url = G5_URL.$_SERVER['REQUEST_URI'];
                        ?>
                        <input type="hidden" id="data-area" class="data-area" value="<?php echo $currents_url ?>">
                        <script>
                            $(document).ready(function() {

                                $('#data-copy').click(function() {
                                    $('#data-area').attr('type', 'text'); // 화면에서 hidden 처리한 input box type을 text로 일시 변환
                                    $('#data-area').select(); // input에 담긴 데이터를 선택
                                    var copy = document.execCommand('copy'); // clipboard에 데이터 복사
                                    $('#data-area').attr('type', 'hidden'); // input box를 다시 hidden 처리
                                    if (copy) {
                                        alert("공유 링크가 복사 되었습니다."); // 사용자 알림
                                    }
                                });

                            });
                        </script>
                    </ul>

            </div>
            
            
            
            <!-- 게시물 정보 { -->
            <ul class="rb_bbs_for_mem rb_bbs_for_mem_view mt-50">

                <li class="rb_bbs_for_mem_names">
                    <?php echo $view['name'] ?> <?php if ($board['bo_use_ip_view']) { echo "<span class='view_info_span_ip'>($ip)</span>"; } ?>
                    <span class="view_info_span"><?php echo passing_time3($view['wr_datetime']) ?></span> 
                    <span class="view_info_span view_info_span_date"><?php echo date("Y.m.d H:i", strtotime($view['wr_datetime'])) ?></span> 

                    <?php
                    $view['icon_new'] = "";
                    if ($view['wr_datetime'] >= date("Y-m-d H:i:s", G5_SERVER_TIME - ($board['bo_new'] * 3600)))
                        $view['icon_new'] = "<span class=\"lb_ico_new\">신규</span>";
                    $view['icon_hot'] = "";
                    if ($board['bo_hot'] > 0 && $view['wr_hit'] >= $board['bo_hot'])
                        $view['icon_hot'] = "<span class=\"lb_ico_hot\">인기</span>";

                    echo $view['icon_new']; //뉴아이콘
                    echo $view['icon_hot']; //인기아이콘 
                    ?>
                </li>

                <li class="rb_bbs_for_btm_info">
                    <dd>
                        <i><img src="<?php echo $board_skin_url ?>/img/ico_eye.svg"></i>
                        <span><?php echo number_format($view['wr_hit']); ?></span>
                    </dd>

                    <dd>
                        <i><img src="<?php echo $board_skin_url ?>/img/ico_comm.svg"></i>
                        <span><?php echo number_format($view['wr_comment']); ?></span>
                    </dd>

                </li>

                <div class="cb"></div>

            </ul>
            <!-- } -->

            <!-- 첨부파일 / 링크 { -->
            <?php
            $cnt = 0;
            if ($view['file']['count']) {
                for ($i=0; $i<count($view['file']); $i++) {
                    if (isset($view['file'][$i]['source']) && $view['file'][$i]['source'] && !$view['file'][$i]['view'])
                    //if (isset($view['file'][$i]['source']) && $view['file'][$i]['source'])
                        $cnt++;
                }
            }

            ?>

            <?php if($cnt) { ?>

            <div class="rb_bbs_file">
                <?php
                // 가변 파일
                for ($i=0; $i<count($view['file']); $i++) {
                    if (isset($view['file'][$i]['source']) && $view['file'][$i]['source'] && !$view['file'][$i]['view']) {
                    //if (isset($view['file'][$i]['source']) && $view['file'][$i]['source']) {
                ?>
                <ul class="rb_bbs_file_for">
                    <i><img src="<?php echo $board_skin_url ?>/img/ico_file.svg"></i>
                    <a href="<?php echo $view['file'][$i]['href'];  ?>" class="view_file_download"><?php echo $view['file'][$i]['source'] ?></a> (<?php echo $view['file'][$i]['size'] ?>)　<!--<?php echo $view['file'][$i]['datetime'] ?>　--><?php echo number_format($view['file'][$i]['download']); ?>회
                    <?php if($view['file'][$i]['content']) { ?>
                    <li class="file_contents"><?php echo $view['file'][$i]['content'] ?></li>
                    <?php } ?>
                </ul>
                <?php
                    }
                }
                ?>
            </div>

            <?php } ?>



            <?php if(isset($view['link']) && array_filter($view['link'])) { ?>

            <div class="rb_bbs_file">
                <?php
                // 링크
                $cnt = 0;
                for ($i=1; $i<=count($view['link']); $i++) {
                    if ($view['link'][$i]) {
                        $cnt++;
                        $link = cut_str($view['link'][$i], 70);
                ?>
                <ul class="rb_bbs_file_for">
                    <i><img src="<?php echo $board_skin_url ?>/img/ico_link.svg"></i>
                    <a href="<?php echo $view['link_href'][$i] ?>" target="_blank"><?php echo $link ?></a>　<?php echo $view['link_hit'][$i] ?>회
                </ul>
                <?php
                    }
                }
                ?>
            </div>

            <?php } ?>


            <?php //echo $view['rich_content']; // {이미지:0} 과 같은 코드를 사용할 경우 ?>
            <!-- } 본문 내용 끝 -->

            <!--  추천 비추천 시작 { -->
            <?php if ( $good_href || $nogood_href) { ?>
            <div id="bo_v_act">
                <?php if ($good_href) { ?>
                <span class="bo_v_act_gng">
                    <a href="<?php if(!$is_member) { ?>javascript:alert('로그인 후 이용하실 수 있습니다.');<?php } else { ?><?php echo $good_href.'&amp;'.$qstr ?><?php } ?>" id="good_button" class="bo_v_good">추천해요 <strong><?php echo number_format($view['wr_good']) ?></strong></a>
                    <b id="bo_v_act_good" class="font-R"></b>
                </span>
                <?php } ?>
                <?php if ($nogood_href) { ?>
                <span class="bo_v_act_gng">
                    <a href="<?php if(!$is_member) { ?>javascript:alert('로그인 후 이용하실 수 있습니다.');<?php } else { ?><?php echo $nogood_href.'&amp;'.$qstr ?><?php } ?>" id="nogood_button" class="bo_v_nogood">별로에요 <strong><?php echo number_format($view['wr_nogood']) ?></strong></a>
                    <b id="bo_v_act_nogood" class="font-R"></b>
                </span>
                <?php } ?>
            </div>
            <?php } else {
                    if($board['bo_use_good'] || $board['bo_use_nogood']) {
                ?>
            <div id="bo_v_act">
                <?php if($board['bo_use_good']) { ?>
                    <span class="bo_v_act_gng">

                        <a href="<?php if(!$is_member) { ?>javascript:alert('로그인 후 이용하실 수 있습니다.');<?php } else { ?>javascript:void(0);<?php } ?>" class="bo_v_good">추천해요 <strong><?php echo number_format($view['wr_good']) ?></strong></a>
                        <b id="bo_v_act_good" class="font-R"></b>
                    </span>
                <?php } ?>
                <?php if($board['bo_use_nogood']) { ?>
                    <span class="bo_v_act_gng">
                        <a href="<?php if(!$is_member) { ?>javascript:alert('로그인 후 이용하실 수 있습니다.');<?php } else { ?>javascript:void(0);<?php } ?>" class="bo_v_nogood">별로에요 <strong><?php echo number_format($view['wr_nogood']) ?></strong></a>
                        <b id="bo_v_act_nogood" class="font-R"></b>
                    </span>
                <?php } ?>
            </div>
            <?php
                    }
                }
            ?>
            <!-- }  추천 비추천 끝 -->


            <ul class="btm_btns">

               <dd class="btm_btns_right">

                    <?php if ($list_href) { ?>
                    <a href="<?php echo $list_href ?>" type="button" class="fl_btns font-B">목록</a>
                    <?php } ?>


                    <?php if ($scrap_href) { ?>
                    <a href="<?php echo $scrap_href;  ?>" class="fl_btns font-B" target="_blank" onclick="win_scrap(this.href); return false;">스크랩</a>
                    <?php } ?>

                    <?php if ($write_href) { ?>
                    <button type="button" name="btn_submit" class="fl_btns main_color_bg" onclick="location.href='<?php echo $write_href ?>';">
                        <img src="<?php echo $board_skin_url ?>/img/ico_write.svg">
                        <span class="font-R">글 등록</span>
                    </button>
                    <?php } ?>

                    <div class="cb"></div>

                </dd>

                <div id="bo_v_btns">
                    <?php ob_start(); ?>

                    <?php if($update_href || $delete_href || $copy_href || $move_href || $search_href) { ?>

                        <?php if ($reply_href) { ?>
                        <a href="<?php echo $reply_href ?>" class="fl_btns">
                        <span class="font-B">답글</span>
                        </a>
                        <?php } ?>

                        <?php if ($update_href) { ?>
                        <a href="<?php echo $update_href ?>" class="fl_btns">
                        <span class="font-B">수정</span>
                        </a>
                        <?php } ?>

                        <?php if ($copy_href) { ?>
                        <a href="<?php echo $copy_href ?>" onclick="board_move(this.href); return false;" class="fl_btns">
                        <span class="font-B">복사</span>
                        </a>
                        <?php } ?>

                        <?php if ($copy_href) { ?>
                        <a href="<?php echo $move_href ?>" onclick="board_move(this.href); return false;" class="fl_btns">
                        <span class="font-B">이동</span>
                        </a>
                        <?php } ?>


                        <?php if ($delete_href) { ?>
                        <a href="<?php echo $delete_href ?>" onclick="del(this.href); return false;" class="fl_btns">
                        <span class="font-B">삭제</span>
                        </a>
                        <?php } ?>

                    <?php } ?>

                    <?php
                    $link_buttons = ob_get_contents();
                    ob_end_flush();
                   ?>

                </div>


               <div class="cb"></div>

            </ul>

            <!-- 배너 {
            <ul class="bbs_bn_box">
                배너를 추가해보세요.
            </ul>
            } -->

            <?php 
            if(isset($board['bo_use_signature']) && $board['bo_use_signature']) {
                // 서명 출력
                include_once(G5_PATH.'/rb/rb.mod/signature/signature.skin.php');
            } 
            ?>
            
            

            <ul>
                <?php if ($prev_href || $next_href) { ?>
                <div class="bo_v_nb">
                    <?php if ($prev_href) { ?><li class="btn_prv" onclick="location.href='<?php echo $prev_href ?>';"><span class="nb_tit">이전글</span><a href="javascript:void(0);"><?php echo $prev_wr_subject;?></a><span class="nb_date"><?php echo str_replace('-', '.', substr($prev_wr_date, '0', '10')); ?></span></li><?php } ?>
                    <?php if ($next_href) { ?><li class="btn_next" onclick="location.href='<?php echo $next_href ?>';"><span class="nb_tit">다음글</span><a href="javascript:void(0);"><?php echo $next_wr_subject;?></a><span class="nb_date"><?php echo str_replace('-', '.', substr($next_wr_date, '0', '10')); ?></span></li><?php } ?>
                </div>
                <?php } ?>

                <?php
                // 코멘트 입출력
                include_once(G5_BBS_PATH.'/view_comment.php');
                ?>
            </ul>
            
            
            


        </div>
        <!-- } -->
      
      
      
      

    

</div>













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