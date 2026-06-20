<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$board_skin_url.'/style.css">', 0);

//필드분할
$wr_1 = isset($write["wr_1"]) ? explode("|", $write["wr_1"]) : [];
$wr_2 = isset($write["wr_2"]) ? explode("|", $write["wr_2"]) : [];
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

<div class="rb_bbs_wrap rb_bbs_write_wrap">

    
    <div class="prd_left">

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
                <textarea name="wr_6" id="wr_6" class="textarea" placeholder="간략 설명을 입력하세요. (제목하단 및 목록)" style="min-height:100px;"><?php echo isset($write['wr_6']) ? $write['wr_6'] : ''; ?></textarea>
            </ul>
        </div>



        <div class="rb_inp_wrap new_bbs_border_wrap">
            <ul>

                <h6 class="bbs_sub_titles font-B">주요내용/뱃지</h6>
                <label class="helps">주요내용(금액 등)과 뱃지를 출력할 수 있습니다. </label>


                <input type="text" name="wr_1_ex[0]" value="<?php echo isset($wr_1[0]) ? $wr_1[0] : ''; ?>" id="wr_1_0" class="input w40" placeholder="주요내용">
                <input type="text" name="wr_1_ex[1]" value="<?php echo isset($wr_1[1]) ? $wr_1[1] : ''; ?>" id="wr_1_1" class="input w25 mobile_mt5" placeholder="뱃지내용">

            </ul>
        </div>


        <div class="rb_inp_wrap new_bbs_border_wrap">
            <ul>

                <h6 class="bbs_sub_titles font-B">버튼설정</h6>
                <label class="helps">보기페이지에 버튼을 추가할 수 있습니다.</label>


                <input type="text" name="wr_2_ex[0]" value="<?php echo isset($wr_2[0]) ? $wr_2[0] : ''; ?>" id="wr_2_0" class="input w30" placeholder="버튼텍스트">
                <input type="text" name="wr_2_ex[1]" value="<?php echo isset($wr_2[1]) ? $wr_2[1] : ''; ?>" id="wr_2_1" class="input w60 mobile_mt5" placeholder="버튼링크"><br>
                <div class="mt-10">
                <input type="checkbox" id="wr_2_2" name="wr_2_ex[2]" value="1" <?php echo (isset($wr_2[2]) && $wr_2[2] == "1") ? 'checked' : ''; ?>><label for="wr_2_2">새창여부</label>
                </div>
            </ul>
        </div>

        <div class="rb_inp_wrap new_bbs_border_wrap">
            <ul>

                <h6 class="bbs_sub_titles font-B">옵션(추가정보)설정</h6>
                <label class="helps">추가정보를 출력할 수 있습니다.</label>

                <li>
                    <input type="text" name="wr_3_ex[0]" value="<?php echo isset($wr_3[0]) ? $wr_3[0] : ''; ?>" id="wr_3_0" class="input w30" placeholder="옵션1 제목">
                    <input type="text" name="wr_3_ex[1]" value="<?php echo isset($wr_3[1]) ? $wr_3[1] : ''; ?>" id="wr_3_1" class="input w50 mobile_mt5" placeholder="옵션1 내용">
                </li>

                <li class="mt-10">
                    <input type="text" name="wr_3_ex[2]" value="<?php echo isset($wr_3[2]) ? $wr_3[2] : ''; ?>" id="wr_3_2" class="input w30" placeholder="옵션2 제목">
                    <input type="text" name="wr_3_ex[3]" value="<?php echo isset($wr_3[3]) ? $wr_3[3] : ''; ?>" id="wr_3_3" class="input w50 mobile_mt5" placeholder="옵션2 내용">
                </li>

                <li class="mt-10">
                    <input type="text" name="wr_3_ex[4]" value="<?php echo isset($wr_3[4]) ? $wr_3[4] : ''; ?>" id="wr_3_4" class="input w30" placeholder="옵션3 제목">
                    <input type="text" name="wr_3_ex[5]" value="<?php echo isset($wr_3[5]) ? $wr_3[5] : ''; ?>" id="wr_3_5" class="input w50 mobile_mt5" placeholder="옵션3 내용">
                </li>

                <li class="mt-10">
                    <input type="text" name="wr_3_ex[6]" value="<?php echo isset($wr_3[6]) ? $wr_3[6] : ''; ?>" id="wr_3_6" class="input w30" placeholder="옵션4 제목">
                    <input type="text" name="wr_3_ex[7]" value="<?php echo isset($wr_3[7]) ? $wr_3[7] : ''; ?>" id="wr_3_7" class="input w50 mobile_mt5" placeholder="옵션4 내용">
                </li>

                <li class="mt-10">
                    <input type="text" name="wr_3_ex[8]" value="<?php echo isset($wr_3[8]) ? $wr_3[8] : ''; ?>" id="wr_3_8" class="input w30" placeholder="옵션5 제목">
                    <input type="text" name="wr_3_ex[9]" value="<?php echo isset($wr_3[9]) ? $wr_3[9] : ''; ?>" id="wr_3_9" class="input w50 mobile_mt5" placeholder="옵션5 내용">
                </li>

            </ul>
        </div>

        <!-- wr_1 : 유튜브 ID { -->
        <div class="rb_inp_wrap new_bbs_border_wrap">
            <ul>

                <h6 class="bbs_sub_titles font-B">유튜브 URL/ID</h6>
                <label class="helps">유튜브 동영상 ID 또는 전체 URL을 입력하세요.<br>상세정보 상단에 출력 됩니다.</label>
                <input type="text" name="wr_7" value="<?php echo isset($write['wr_7']) ? $write['wr_7'] : ''; ?>" class="w100" id="wr_7" class="input" placeholder="유튜브 URL/ID">

            </ul>
        </div>
        <!-- } -->

        <div class="rb_inp_wrap">
            <ul>
                <input type="text" name="wr_4" value="<?php echo isset($write['wr_4']) ? $write['wr_4'] : ''; ?>" id="wr_4" class="input" placeholder="목록용 뱃지">
            </ul>
        </div>

        <div class="rb_inp_wrap">
            <ul>
                <textarea name="wr_5" id="wr_5" class="textarea" placeholder="참고사항을 입력할 수 있습니다. (이미지 하단)" style="min-height:100px;"><?php echo isset($write['wr_5']) ? $write['wr_5'] : ''; ?></textarea>
            </ul>
        </div>
        
        

    </div>
    
    <script>
        $(document).ready(function() {
            $('#wr_subject').change(function() {
                var selectedValue = $('#wr_subject option:selected').text();
                $('#wr_subject_inp').text('Selected: ' + selectedValue);
            });

            $('#wr_subject').blur(function() {
                var inputValue = $(this).val();
                $('#wr_subject_inp').text(inputValue);
            });
            

            $('#wr_6').blur(function() {
                var inputValue = $(this).val();
                // 엔터 키를 <br>로 변환하여 줄 바꿈 처리
                var formattedValue = inputValue.replace(/\n/g, '<br>');
                $('#wr_6_inp').html(formattedValue);
            });
            
            $('#wr_1_0').blur(function() {
                var inputValue = $(this).val();
                
                if(inputValue == "") {
                    $('#wr_1_0_inp').hide();
                } else { 
                    $('#wr_1_0_inp').show();
                    $('#wr_1_0_inp').text(inputValue);
                }

            });
            
            $('#wr_1_1').blur(function() {
                var inputValue = $(this).val();
                if(inputValue == "") {
                    $('#wr_1_1_inp').hide();
                } else { 
                    $('#wr_1_1_inp').show();
                    $('#wr_1_1_inp').text(inputValue);
                }
            });
            
            $('#wr_2_0').blur(function() {
                var inputValue = $(this).val();
                if(inputValue == "") {
                    $('.view_info_btns').hide();
                } else { 
                    $('.view_info_btns').show();
                    $('#wr_2_0_inp').text(inputValue);
                }
            });
            
            $('#wr_3_0').blur(function() {
                var inputValue = $(this).val();
                if(inputValue == "") {
                    $('#wr_3_0_wrap').hide();
                    $('#wr_3_1_wrap').hide();
                } else { 
                    $('#wr_3_0_wrap').show();
                    $('#wr_3_1_wrap').show();
                    $('#wr_3_0_inp').text(inputValue);
                }
                
            });
            
            $('#wr_3_1').blur(function() {
                var inputValue = $(this).val();
                $('#wr_3_1_inp').text(inputValue);
            });
            
            
            $('#wr_3_2').blur(function() {
                var inputValue = $(this).val();
                if(inputValue == "") {
                    $('#wr_3_2_wrap').hide();
                    $('#wr_3_3_wrap').hide();
                } else { 
                    $('#wr_3_2_wrap').show();
                    $('#wr_3_3_wrap').show();
                    $('#wr_3_2_inp').text(inputValue);
                }
                
            });
            
            $('#wr_3_3').blur(function() {
                var inputValue = $(this).val();
                $('#wr_3_3_inp').text(inputValue);
            });
            
            
            $('#wr_3_4').blur(function() {
                var inputValue = $(this).val();
                if(inputValue == "") {
                    $('#wr_3_4_wrap').hide();
                    $('#wr_3_5_wrap').hide();
                } else { 
                    $('#wr_3_4_wrap').show();
                    $('#wr_3_5_wrap').show();
                    $('#wr_3_4_inp').text(inputValue);
                }
                
            });
            
            $('#wr_3_5').blur(function() {
                var inputValue = $(this).val();
                $('#wr_3_5_inp').text(inputValue);
            });
            
            
            $('#wr_3_6').blur(function() {
                var inputValue = $(this).val();
                if(inputValue == "") {
                    $('#wr_3_6_wrap').hide();
                    $('#wr_3_7_wrap').hide();
                } else { 
                    $('#wr_3_6_wrap').show();
                    $('#wr_3_7_wrap').show();
                    $('#wr_3_6_inp').text(inputValue);
                }
                
            });
            
            $('#wr_3_7').blur(function() {
                var inputValue = $(this).val();
                $('#wr_3_7_inp').text(inputValue);
            });
            
            
            $('#wr_3_8').blur(function() {
                var inputValue = $(this).val();
                if(inputValue == "") {
                    $('#wr_3_8_wrap').hide();
                    $('#wr_3_9_wrap').hide();
                } else { 
                    $('#wr_3_8_wrap').show();
                    $('#wr_3_9_wrap').show();
                    $('#wr_3_8_inp').text(inputValue);
                }
                
            });
            
            $('#wr_3_9').blur(function() {
                var inputValue = $(this).val();
                $('#wr_3_9_inp').text(inputValue);
            });
           
            
            
            
            
            
            
        });
    </script>
    
   
    <div class="prd_right bbs_sv_wrap_write bbs_sv_wrap">
        <div class="bbs_w_titles font-B">출력예시</div>
        <ul class="bbs_sv_wrap_ul2">

            <div class="gap_btm_bd">

                <ul class="font-B view_info_tit"><span id="wr_subject_inp">제목을 입력하세요</span></ul>
                <ul class="view_info_sub"><span id="wr_6_inp">간략 설명을 입력하세요. (제목하단 및 목록)<br>간략 설명은 엔터시 줄바꿈 처리 됩니다. (2~50자 이내)</ul>
                <ul class="font-B view_info_pri1 main_color"><li id="wr_1_0_inp">150,000원</li><li class="view_info_pri1 labels main_color font-B" id="wr_1_1_inp">15%할인!</li></ul>
                <ul class="view_info_btns"><a href="javascript:void(0);" class="view_info_btns_btn1 font-R" target="_blank" id="wr_2_0_inp">구매 바로가기</a></ul>

            </div>

            <div class="gap_btm_bd">

                <ul class="opt_box_wrap" id="wr_3_0_wrap"><li class="font-B" id="wr_3_0_inp">사은품</li><li id="wr_3_1_inp">포토상품평 이벤트 스탠드 증정</li></ul>
                <ul class="opt_box_wrap" id="wr_3_2_wrap"><li class="font-B" id="wr_3_2_inp">프로젝터 방식</li><li id="wr_3_3_inp">LCD</li></ul>
                <ul class="opt_box_wrap" id="wr_3_4_wrap"><li class="font-B" id="wr_3_4_inp">해상도</li><li id="wr_3_5_inp">Full-HD(1920x1080)</li></ul>
                <ul class="opt_box_wrap" id="wr_3_6_wrap"><li class="font-B" id="wr_3_6_inp">외부기기연결</li><li id="wr_3_7_inp">노트북연결(Wi-Di), USB연결, 노트북연결(Wi-Di), USB연결</li></ul>
                <ul class="opt_box_wrap" id="wr_3_8_wrap"><li class="font-B" id="wr_3_8_inp">USB기능</li><li id="wr_3_9_inp">USB재생(동영상,음악)</li></ul>
            </div>

        </ul>
    </div>
    
    <div class="cb"></div>
    
</div>
<div class="rb_bbs_wrap rb_bbs_write_wrap">
    
    <?php
        $option = '';
        $option_hidden = '';
        if ($is_notice || $is_html || $is_secret || $is_mail) { 
            $option = '';
            if ($is_notice) {
                $option .= PHP_EOL.'<input type="checkbox" id="notice" name="notice"  class="selec_chk" value="1" '.$notice_checked.'>'.PHP_EOL.'<label for="notice"><span></span>공지</label>　';
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
    <!-- 링크 { -->
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
    <!-- } -->
    <?php } ?>
    
                    <?php if(isset($board['bo_upload_count']) && $board['bo_upload_count'] > 0) { ?>
                    <?php
                        $wr_file = isset($wr_file) ? $wr_file : [];
                        $wf_cnt = count((array)$wr_file) + 1;
                        ?>
                        <?php if (isset($is_file) && $is_file && $wf_cnt > 0): ?>
                        
                    <!-- 파일 { -->
                    <div class="rb_inp_wrap rb_inp_wrap_gap">
                        <label class="help_text">
                        최대 <?php echo $board['bo_upload_count']; ?>개 / 이미지 및 일반 파일을 첨부할 수 있어요.<br>
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
                                <input type="file" id="pic" name="pic" onchange="upload_start()" multiple="multiple" class="au_input" />
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