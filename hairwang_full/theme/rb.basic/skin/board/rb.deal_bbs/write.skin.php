<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css">', 0);

//필드분할
$wr_3 = isset($write["wr_3"]) ? explode("|", $write["wr_3"]) : [];
?>

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
    <input type="hidden" name="wr_8" value="<?php echo isset($write['wr_8']) ? $write['wr_8'] : ''; ?>">

<div class="rb_bbs_wrap rb_bbs_write_wrap">

    
    <div>
        
        
                    <?php if(isset($board['bo_upload_count']) && $board['bo_upload_count'] > 0) { ?>
                    <?php
                        $wr_file = isset($wr_file) ? $wr_file : [];
                        $wf_cnt = count((array)$wr_file) + 1;
                    ?>
                    <?php if (isset($is_file) && $is_file && $wf_cnt > 0): ?>
                        
                    <!-- 파일 { -->
                    <div class="rb_inp_wrap">
                        <label class="help_text">
                        최대 <?php echo $board['bo_upload_count']; ?>개 / 이미지(사진)를 등록해주세요.<br>
                        </label>
                        
                        <div class="">

                        
                          <?php
                          $new_files = [];
                          if (isset($w) && $w == 'u') {
                            // 파일이 존재하는지 확인
                            if (isset($file) && is_array($file)) {
                              foreach ($file as $k => $v) {
                                // 등록된 파일에는 삭제시 필요한 bf_file 필드 추가
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
                        
                        
                        <div class="divmb-10">
                            <input type="hidden" id="ajax_files" name="ajax_files" value="" />
                            <div style="position:relative;">
                                <input type="file" id="pic" name="pic" onchange="upload_start()" multiple="multiple" class="au_input" accept="image/*"/>
                                <div class="au_btn_search_file font-b">파일을 여기에 끌어놓으세요.</div>
                            </div>

                            
                            <div class="swiper-container swiper-wfile" style="overflow: inherit; padding-bottom:15px; font-size:11px;">
                            <div class="swiper-wrapper" id="file_list">
                                <?php foreach($new_files as $v): ?>
                                <div class="swiper-slide swiper-slide_lists">
                                    <div class="au_file_list">
                                        <div class="au_file_list_img_wrap">
                                            <?php if($v['view']) { ?>
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
                            
                            <script>
                                var swiper_file = new Swiper('.swiper-wfile', {
                                    slidesPerColumnFill: 'row',
                                    slidesPerView: 10, // 가로갯수
                                    slidesPerColumn: 1, // 세로갯수
                                    spaceBetween: 7, // 간격
                                    touchRatio: 0, // 드래그 가능여부(1, 0)
                                    
                                    breakpoints: { // 반응형 처리
                                        1024: {
                                            slidesPerView: 10, // 가로갯수
                                            slidesPerColumn: 1, // 세로갯수
                                            touchRatio: 0,
                                        },
                                        10: {
                                            slidesPerView: 3.5, // 가로갯수
                                            slidesPerColumn: 1, // 세로갯수
                                            touchRatio: 1,
                                        }
                                    }
                                });
                            </script>

                            <div class="au_progress">
                                <div id="son" class="font-R au_bars"></div>
                            </div>
                        </div>

                        <script type="text/javascript">
                            var ajax_files = {
                                'files': <?php echo empty($new_files) ? '[]' : json_encode($new_files)?>,
                                'del': []
                            };
                            var xhr = new XMLHttpRequest();

                            function upload_start() {
                                var cnts = $("#file_list .swiper-slide_lists").length;
                                var maxUploadCount = <?php echo $board['bo_upload_count']; ?>;
                                var picFileList = $("#pic").get(0).files;

                                if (cnts + picFileList.length > maxUploadCount) {
                                    alert("첨부파일은 " + maxUploadCount + "개 이하만 업로드 가능합니다.");
                                    return false;
                                }

                                var formData = new FormData();
                                formData.append("act_type", "upload");
                                formData.append("write_table", "<?php echo $write_table ?>");
                                formData.append("bo_table", "<?php echo $bo_table ?>");
                                formData.append("wr_id", "<?php echo $wr_id ?>");
                                for (var i = 0; i < picFileList.length; i++) {
                                    formData.append("file[]", picFileList[i]);
                                }

                                var xhr = new XMLHttpRequest();
                                xhr.upload.addEventListener("progress", onprogress, false);
                                xhr.addEventListener("error", upload_failed, false);
                                xhr.addEventListener("load", upload_success, false);
                                xhr.open("POST", "<?php echo G5_URL ?>/rb/rb.lib/ajax.upload.php");
                                xhr.send(formData);
                            }

                            function onprogress(evt) {
                                var loaded = evt.loaded;
                                var tot = evt.total;
                                var per = Math.floor(100 * loaded / tot);
                                $("#son").parent().css("display", "block");
                                //$("#son").html(per + "%");
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
                                formData.append("write_table", "<?php echo $write_table ?>");
                                formData.append("bo_table", "<?php echo $bo_table ?>");
                                formData.append("wr_id", "<?php echo $wr_id ?>");
                                formData.append("bf_file", file);
                                xhr.open("POST", "<?php echo G5_URL ?>/rb/rb.lib/ajax.upload.php");
                                xhr.send(formData);
                                $(obj).closest('.swiper-slide').remove();
                                ajax_files.del.push(file);
                                $("#ajax_files").val(JSON.stringify(ajax_files));
                            }
                            

                        </script>
   

                            

 
                        </div>
                    </div>
                <!-- } -->
                <?php endif; ?>
                <?php } ?>
                
                

        <!-- 카테고리 { -->
        <?php if ($is_category) { ?>
        <div class="rb_inp_wrap">
            <ul>
                <select name="ca_name" id="ca_name" required class="select ca_name">
                    <option value="">분류를 선택하세요</option>
                    <?php echo $category_option ?>
                </select>
            </ul>
        </div>
        <?php } ?>
        <!-- } -->

        <!-- 제목 { -->
        <div class="rb_inp_wrap">
            <div id="autosave_wrapper" class="write_div">
                <ul class="autosave_wrapper_ul1" <?php if (!$is_member) { ?>style="padding-right:0px;"<?php } ?>>
                <input type="text" name="wr_subject" value="<?php echo $subject ?>" id="wr_subject" required class="input required full_input" maxlength="255" placeholder="제목을 입력하세요.">
                </ul>
                <?php if ($is_member) { // 임시 저장된 글 기능 ?>
                <ul class="autosave_wrapper_ul2">
                    <script src="<?php echo G5_JS_URL; ?>/autosave.js"></script>
                    <?php if($editor_content_js) echo $editor_content_js; ?>
                    <button type="button" id="btn_autosave" class="btn_frmline">임시저장 <span id="autosave_count" class="font-B"><?php echo $autosave_count; ?></span></button>
                    <div id="autosave_pop">
                        <strong>임시 저장된 글 목록</strong>
                        <?php if($autosave_count > 0) { ?>
                            <ul></ul>
                        <?php } else { ?>
                            <div class="autosave_guide">저장된 데이터가 없습니다.</div>
                        <?php } ?>
                        <div class="autosave_btn_wrap">
                        <button type="button" class="autosave_close autosave_save font-B" onclick="autosave()">저장</button>
                        <button type="button" class="autosave_close font-B">닫기</button>
                        </div>
                    </div>
                </ul>
                <?php } ?>
            </div>
        </div>
        <!-- } -->
        
        <div class="rb_inp_wrap">
            <ul>
                <input type="number" name="wr_6" value="<?php echo isset($write['wr_6']) ? $write['wr_6'] : ''; ?>" id="wr_6" required class="input required w20" placeholder="판매금액(숫자)">　
                <input type="checkbox" id="wr_7" name="wr_7" value="협의" <?php echo (isset($write['wr_7']) && $write['wr_7'] == "협의") ? 'checked' : ''; ?>><label for="wr_7">협의</label>　
            </ul>
        </div>
        

        
        
        <div class="rb_inp_wrap new_bbs_border_wrap">
            <ul>

                <h6 class="bbs_sub_titles font-B mb-15">거래옵션</h6>

                <li>
                    <input type="radio" id="wr_1_1" name="wr_1" value="배송비 포함" <?php echo (isset($write['wr_1']) && $write['wr_1'] == "배송비 포함") ? 'checked' : ''; ?>><label for="wr_1_1">배송비 포함</label>　
                    <input type="radio" id="wr_1_2" name="wr_1" value="배송비 별도" <?php echo (isset($write['wr_1']) && $write['wr_1'] == "배송비 별도") ? 'checked' : ''; ?>><label for="wr_1_2">배송비 별도</label>　
                    <input type="radio" id="wr_1_3" name="wr_1" value="배송불가" <?php echo (isset($write['wr_1']) && $write['wr_1'] == "배송불가") ? 'checked' : ''; ?>><label for="wr_1_3">배송불가</label>　　
                </li>
                
                <li class="mt-10">
                    <input type="checkbox" id="wr_2" name="wr_2" value="직거래 가능" <?php echo (isset($write['wr_2']) && $write['wr_2'] == "직거래 가능") ? 'checked' : ''; ?>><label for="wr_2">직거래 가능</label>　
                    <input type="checkbox" id="wr_5" name="wr_5" value="1" <?php echo (isset($write['wr_5']) && $write['wr_5'] == "1") ? 'checked' : ''; ?>><label for="wr_5">연락처 노출 (회원정보의 연락처를 노출 합니다.)</label>　
                </li>
                
            </ul>
        </div>


        <?php 
        if(isset($config['cf_kakao_js_apikey']) && $config['cf_kakao_js_apikey']) { 
            
            add_javascript(G5_POSTCODE_JS, 0);    //다음 주소 js
            
            //기본좌표
            if (empty($wr_3[4])) {
                $wr_3[4] = 37.566400714093284;
            }
            if (empty($wr_3[5])) {
                $wr_3[5] = 126.9785391897507;
            }


        ?>
        <div class="rb_inp_wrap new_bbs_border_wrap" id="loca_div" style="display:none;">
            <ul>

                <h6 class="bbs_sub_titles font-B">거래위치</h6>
                <label class="helps">거래위치를 설정해주세요.</label>

                <li>
                <input type="text" name="wr_3_ex[0]" value="<?php echo isset($wr_3[0]) ? $wr_3[0] : ''; ?>" id="wr_3_0" class="input w60 map_inp" placeholder="주소" readonly>
                <input type="text" name="wr_3_ex[1]" value="<?php echo isset($wr_3[1]) ? $wr_3[1] : ''; ?>" id="wr_3_1" class="input w30 mobile_mt5" placeholder="나머지 주소">
                </li>
                <li class="mt-10">
                    <input type="text" name="wr_9" value="<?php echo isset($write['wr_9']) ? $write['wr_9'] : ''; ?>" id="wr_9" class="input w20" placeholder="광역시/도">
                    <input type="text" name="wr_10" value="<?php echo isset($write['wr_10']) ? $write['wr_10'] : ''; ?>" id="wr_10" class="input w20 mobile_mt5" placeholder="시/군/구">
                    
                    <input type="hidden" name="wr_3_ex[2]" value="-" class="" id="wr_3_2">
                    <input type="hidden" name="wr_3_ex[3]" value="-" class="" id="wr_3_3">
                    
                    <input type="hidden" name="wr_3_ex[4]" value="<?php echo isset($wr_3[4]) ? $wr_3[4] : ''; ?>" class="" id="wr_3_4">
                    <input type="hidden" name="wr_3_ex[5]" value="<?php echo isset($wr_3[5]) ? $wr_3[5] : ''; ?>" class="" id="wr_3_5">
                </li>
                <li class="mt-10">
                    <script type="text/javascript" src="//dapi.kakao.com/v2/maps/sdk.js?appkey=<?php echo $config['cf_kakao_js_apikey'] ?>&libraries=services"></script>
                    <div style="background-color:#f9f9f9; width:100%; height:200px; border-radius:10px;" id="map"></div>
                    
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



                    // 주소검색 API (주소 > 좌표변환처리)
                    $(function() {
                        $("#wr_3_0").on("click", function() {
                            new daum.Postcode({
                                oncomplete: function(data) {

                                    //$("#reg_mb_zip").val(data.zonecode); //우편번호
                                    $("#wr_3_0").val(data.address); //주소
                                    /*
                                    if(data.buildingName) { //건물명 있는경우
                                        $("#reg_mb_addr3").val('('+data.bname+', '+data.buildingName+')');  //동, 건물명
                                    } else { 
                                        $("#reg_mb_addr3").val('('+data.bname+')');  //동
                                    }
                                    */

                                    $("#wr_9").val(data.sido);
                                    $("#wr_10").val(data.sigungu);

                                    geocoder.addressSearch(data.address, function(results, status) {
                                        // 정상적으로 검색이 완료됐으면
                                        if (status === daum.maps.services.Status.OK) {

                                            //첫번째 결과의 값을 활용
                                            var result = results[0];

                                            // 해당 주소에 대한 좌표를 받아서
                                            var coords = new daum.maps.LatLng(result.y, result.x);

                                            // 지도를 보여준다.
                                            map.relayout();

                                            // 지도 중심을 변경한다.
                                            map.setCenter(coords);

                                            // 좌표값을 넣어준다.
                                            document.getElementById('wr_3_4').value = coords.getLat();
                                            document.getElementById('wr_3_5').value = coords.getLng();

                                            // 마커를 결과값으로 받은 위치로 옮긴다.
                                            marker.setPosition(coords);
                                        }
                                    });

                                }
                            }).open();
                        });


                    });

                    //마커를 기준으로 가운데 정렬이 될 수 있도록 추가
                    var markerPosition = marker.getPosition(); 
                    map.relayout();
                    map.setCenter(markerPosition);



                    //브라우저가 리사이즈될때 지도 리로드
                    $(window).on('resize', function () {
                        var markerPosition = marker.getPosition(); 
                        map.relayout();
                        map.setCenter(markerPosition)
                    });


                    </script>
                </li>
            </ul>
        </div>
        
        <script>
            function checkDirectDeal() {
                if ($('input[name="wr_2"]:checked').val() === '직거래 가능') {
                    $('#loca_div').show();
                    var markerPosition = marker.getPosition(); 
                    map.relayout();
                    map.setCenter(markerPosition)
                } else {
                    $('#loca_div').hide();
                    $('#wr_3_0').val('');
                    $('#wr_3_1').val('');
                    $('#wr_9').val('');
                    $('#wr_10').val('');
                    $('#wr_3_4').val('');
                    $('#wr_3_5').val('');
                }
            }

            $(document).ready(function() {
                checkDirectDeal();

                $('input[name="wr_2"]').on('change', function() {
                    checkDirectDeal();
                });
            });
        </script>
        <?php } else { ?>
            <?php if($is_admin) { ?>
            <div class="rb_inp_wrap new_bbs_border_wrap">
                <ul>

                    <h6 class="bbs_sub_titles font-B main_color">지도 API 를 사용할 수 없습니다.</h6>
                    <label class="helps">관리자모드 > 환경설정 > 카카오 JavaScript 키 항목에 카카오 자바스크립트키를 등록해주세요.</label>

                </ul>
            </div>
            <?php } ?>
        <?php } ?>
        
        <div class="rb_inp_wrap new_bbs_border_wrap">
            <ul>

                <h6 class="bbs_sub_titles font-B mb-15">상품상태</h6>

                <li>
                    <input type="radio" id="wr_4_1" name="wr_4" value="새상품" <?php echo (isset($write['wr_4']) && $write['wr_4'] == "새상품") ? 'checked' : ''; ?>><label for="wr_4_1">새상품</label>　
                    <input type="radio" id="wr_4_2" name="wr_4" value="사용감 적음" <?php echo (isset($write['wr_4']) && $write['wr_4'] == "사용감 적음") ? 'checked' : ''; ?>><label for="wr_4_2">사용감 적음</label>　
                    <input type="radio" id="wr_4_3" name="wr_4" value="사용감 많음" <?php echo (isset($write['wr_4']) && $write['wr_4'] == "사용감 많음") ? 'checked' : ''; ?>><label for="wr_4_3">사용감 많음</label>　
                    <input type="radio" id="wr_4_4" name="wr_4" value="고장/파손" <?php echo (isset($write['wr_4']) && $write['wr_4'] == "고장/파손") ? 'checked' : ''; ?>><label for="wr_4_4">고장/파손</label>　
                </li>
                
            </ul>
        </div>
        


    </div>
    

    
</div>

<div class="rb_bbs_wrap rb_bbs_write_wrap">
    
    <?php
        $option = '';
        $option_hidden = '';
        if ($is_notice || $is_html || $is_secret || $is_mail) { 
            $option = '';
            if ($is_notice) {
                $option .= PHP_EOL.'<input type="checkbox" id="notice" name="notice"  class="selec_chk" value="1" '.$notice_checked.'>'.PHP_EOL.'<label for="notice"><span></span>프리미엄 등록</label>　';
            }
            if ($is_html) {
                if ($is_dhtml_editor) {
                    $option_hidden .= '<input type="hidden" value="html1" name="html">';
                } else {
                    $option .= PHP_EOL.'<input type="checkbox" id="html" name="html" onclick="html_auto_br(this);" class="selec_chk" value="'.$html_value.'" '.$html_checked.'>'.PHP_EOL.'<label for="html"><span></span>html</label>　';
                }
            }
            if ($is_secret) {
                if ($is_admin || $is_secret==1) {
                    $option .= PHP_EOL.'<input type="checkbox" id="secret" name="secret"  class="selec_chk" value="secret" '.$secret_checked.'>'.PHP_EOL.'<label for="secret"><span></span>비밀글</label>　';
                } else {
                    $option_hidden .= '<input type="hidden" name="secret" value="secret">';
                }
            }
            if ($is_mail) {
                $option .= PHP_EOL.'<input type="checkbox" id="mail" name="mail"  class="selec_chk" value="mail" '.$recv_email_checked.'>'.PHP_EOL.'<label for="mail"><span></span>답변메일받기</label>　';
            }
        }
        echo $option_hidden;
    ?>
    
    
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
        
    <h2 id="container_title" class="mt-30">상품설명</h2>
    
    <!-- 내용 { -->
    <div class="rb_inp_wrap">
        <ul>
            <div class="wr_content <?php echo $is_dhtml_editor ? $config['cf_editor'] : ''; ?>">
                <?php if($board['bo_write_min'] || $board['bo_write_max']) { ?>
                <!-- 최소/최대 글자 수 사용 시 -->
                <p id="char_count_desc" class="help_text">이 게시판은 최소 <strong><?php echo $board['bo_write_min']; ?></strong>글자 이상, 최대 <strong><?php echo $board['bo_write_max']; ?></strong>글자 이하까지 글을 쓰실 수 있습니다.</p>
                <?php } ?>
                <?php echo $editor_html; // 에디터 사용시는 에디터로, 아니면 textarea 로 노출 ?>

                <?php if($board['bo_write_min'] || $board['bo_write_max']) { ?>
                        <?php if(!$is_dhtml_editor) { ?>
                        <div id="char_count_wrap"><span id="char_count"></span>글자</div>
                        <?php } ?>
                <?php } ?>

            </div>

            <?php if(!$is_dhtml_editor) { ?>
            <style>
                .wr_content>textarea {
                    overflow: hidden;
                }
            </style>
            <script>
                //에디터가 아닌경우 textarea의 높이 자동설정
                $(document).ready(function() {
                    $('.wr_content > textarea').on('input', function() {
                        this.style.height = 'auto'; /* 높이를 자동으로 설정합니다. */
                        this.style.height = (this.scrollHeight) + 'px'; /* 스크롤 높이를 textarea에 적용합니다. */
                        this.style.minHeight = '300px';
                    });
                });
            </script>
            <?php } ?>
        </ul>
    </div>
    <!-- } -->
    
    
    <!-- 비회원 { -->
    <?php if ($is_name) { ?>
    <div class="rb_inp_wrap">
        <ul class="guest_inp_wrap">

            <lebel class="help_text">작성자 정보를 입력해주세요. 비밀번호는 게시글 수정 시 사용됩니다.</lebel>
            <li>
                
                <input type="text" name="wr_name" value="<?php echo $name ?>" id="wr_name" required class="input_tiny required" placeholder="성함">
                

                <?php if ($is_password) { ?>
                <input type="password" name="wr_password" id="wr_password" <?php echo $password_required ?> class="input_tiny <?php echo $password_required ?>" placeholder="비밀번호">
                <?php } ?>
            </li>


            <li>
                <?php if ($is_email) { ?>
                <input type="text" name="wr_email" value="<?php echo $email ?>" id="wr_email" class="input_tiny email " placeholder="이메일">
                <?php } ?>
            </li>

        </ul>
    </div>
    <!-- } -->
    <?php } ?>

    <?php if(isset($is_link) && $is_link) { ?>
    <!-- 링크 {
    <div class="rb_inp_wrap rb_inp_wrap_gap">
        <label class="help_text">링크 주소를 입력할 수 있어요.</label>
        <?php for ($i=1; $is_link && $i<=G5_LINK_COUNT; $i++) { ?>
        <ul class="rb_inp_wrap_link">
            <i><img src="<?php echo $board_skin_url ?>/img/ico_link.svg"></i>
            <input type="text" name="wr_link<?php echo $i ?>" value="<?php if($w=="u"){ echo $write['wr_link'.$i]; } ?>" id="wr_link<?php echo $i ?>" class="input full_input">
        </ul>
        <?php } ?>

        </ul>
    </div>
    } -->
    <?php } ?>
    
                    
                
                
    <?php if ($is_use_captcha) { //자동등록방지  ?>
    <div class="rb_inp_wrap">
        <ul>
        <?php echo $captcha_html ?>
        </ul>
    </div>
    <?php } ?>
    
    
    <div class="rb_inp_wrap_confirm">
        <a href="<?php echo get_pretty_url($bo_table); ?>" class="btn_cancel btn font-B">취소</a>
        <button type="submit" id="btn_submit" accesskey="s" class="btn_submit btn font-B">작성완료</button>
    </div>

    
</div>

</form>




<script>
        <?php if($board['bo_write_min'] || $board['bo_write_max']) { ?>
        // 글자수 제한
        var char_min = parseInt(<?php echo $board['bo_write_min']; ?>); // 최소
        var char_max = parseInt(<?php echo $board['bo_write_max']; ?>); // 최대
        check_byte("wr_content", "char_count");

        $(function() {
            $("#wr_content").on("keyup", function() {
                check_byte("wr_content", "char_count");
            });
        });

        <?php } ?>

        function html_auto_br(obj) {
            if (obj.checked) {
                result = confirm("자동 줄바꿈을 하시겠습니까?\n\n자동 줄바꿈은 게시물 내용중 줄바뀐 곳을<br>태그로 변환하는 기능입니다.");
                if (result)
                    obj.value = "html2";
                else
                    obj.value = "html1";
            } else
                obj.value = "";
        }

        function fwrite_submit(f) {
            <?php echo $editor_js; // 에디터 사용시 자바스크립트에서 내용을 폼필드로 넣어주며 내용이 입력되었는지 검사함   ?>

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

            <?php echo $captcha_js; // 캡챠 사용시 자바스크립트에서 입력된 캡챠를 검사함  ?>

            document.getElementById("btn_submit").disabled = "disabled";

            return true;
        }
    </script>