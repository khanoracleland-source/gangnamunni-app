<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가
?>
<style>
    #container_title {display: none;}
</style>


<div class="rb_prof rb_prof_partner">
    <ul class="rb_prof_info">
        <div class="rb_prof_info_img">
        <span id="prof_image_ch"><?php echo get_member_profile_img($pm['mb_id']); ?></span>
        <?php if($pm['mb_id'] == $member['mb_id']) { ?>
        <button type="button" id="prof_ch_btn">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path fill-rule="evenodd" clip-rule="evenodd" d="M9.58597 1.1C9.93996 0.746476 10.4136 0.538456 10.9134 0.516981C11.4132 0.495507 11.903 0.662139 12.286 0.984002L12.414 1.101L14.314 3H17C17.5044 3.00009 17.9901 3.19077 18.3599 3.53384C18.7297 3.8769 18.9561 4.34702 18.994 4.85L19 5V7.686L20.9 9.586C21.2538 9.94004 21.462 10.4139 21.4834 10.9139C21.5049 11.414 21.3381 11.9039 21.016 12.287L20.899 12.414L18.999 14.314V17C18.9991 17.5046 18.8086 17.9906 18.4655 18.3605C18.1224 18.7305 17.6521 18.9572 17.149 18.995L17 19H14.315L12.415 20.9C12.0609 21.2538 11.5871 21.462 11.087 21.4835C10.587 21.505 10.097 21.3382 9.71397 21.016L9.58697 20.9L7.68697 19H4.99997C4.49539 19.0002 4.0094 18.8096 3.63942 18.4665C3.26944 18.1234 3.04281 17.6532 3.00497 17.15L2.99997 17V14.314L1.09997 12.414C0.746165 12.06 0.537968 11.5861 0.516492 11.0861C0.495016 10.586 0.661821 10.0961 0.98397 9.713L1.09997 9.586L2.99997 7.686V5C3.00006 4.4956 3.19074 4.00986 3.53381 3.64009C3.87687 3.27032 4.34699 3.04383 4.84997 3.006L4.99997 3H7.68597L9.58597 1.1ZM11 8C10.2043 8 9.44126 8.31607 8.87865 8.87868C8.31604 9.44129 7.99997 10.2044 7.99997 11C7.99997 11.7957 8.31604 12.5587 8.87865 13.1213C9.44126 13.6839 10.2043 14 11 14C11.7956 14 12.5587 13.6839 13.1213 13.1213C13.6839 12.5587 14 11.7957 14 11C14 10.2044 13.6839 9.44129 13.1213 8.87868C12.5587 8.31607 11.7956 8 11 8Z" fill="#09244B"/>
            </svg>
        </button>
        <?php } ?>
        <input type="file" id="prof_image_ch_input" style="display:none" accept="image/*" style="display:none;" readonly>
        
        <script>
            $(document).ready(function(){
                $('#prof_ch_btn').on('click', function() {
                    $('#prof_image_ch_input').click();
                });

                $('#prof_image_ch_input').on('change', function(event) {
                    const file = event.target.files[0];
                    if (file) {
                        const img = new Image();
                        img.onload = function() {
                            if (img.width === <?php echo $config['cf_member_img_width'] ?> && img.height === <?php echo $config['cf_member_img_height'] ?>) {
                                const formData = new FormData();
                                formData.append('profile_image', file);

                                $.ajax({
                                    url: '<?php echo G5_URL ?>/rb/rb.lib/ajax.upload_prof_image.php',
                                    type: 'POST',
                                    data: formData,
                                    contentType: false,
                                    processData: false,
                                    success: function(response) {
                                        const data = JSON.parse(response);
                                        if (data.success) {
                                            $('#prof_image_ch').html('<img src="' + data.image_url + '" alt="profile_image">');
                                        } else {
                                            alert(data.message);
                                        }
                                    }
                                });
                            } else {
                                alert('이미지는 <?php echo $config['cf_member_img_width'] ?>X<?php echo $config['cf_member_img_height'] ?> 크기로 업로드 해주세요.');
                            }
                        }
                        img.src = URL.createObjectURL(file);
                    }
                });
            });
        </script>
        </div>

        <div class="rb_prof_info_info">
            <li class="rb_prof_info_nick font-B"><?php echo $pm['mb_nick'] ?><!--<span><?php echo $pm['mb_level'] ?> Lv</span>--></li>
            <li class="rb_prof_info_txt">
            <span>게시물 <?php echo number_format(wr_cnt($pm['mb_id'], "w")); ?>개</span>
            <span>댓글 <?php echo number_format(wr_cnt($pm['mb_id'], "c")); ?>개</span>
            <span>상품 <?php echo $total_count; ?>개</span>
            <?php if(isset($sb['sb_use']) && $sb['sb_use'] == 1) { // 구독 사용시 ?><?php echo sb_cnt($pm['mb_id']) ?><?php } ?>
            </li>
            
        </div>
        
        <div class="cb"></div>
        
        
        <?php if(isset($pm['mb_profile']) && $pm['mb_profile']) { ?>
        <li class="rb_prof_info_txt"><?php echo $pm['mb_profile'] ?></li>
        <?php } ?>
        
        
    </ul>
    <ul class="rb_prof_btn">
        <div id="bo_v_share">
        	<ul class="copy_urls">
                <li>

                    
                    
                    

                    <a class="fl_btns" id="data-copy" title="공유링크 복사" alt="공유링크 복사" href="javascript:void(0);">
                        <img src="<?php echo $partner_url ?>/image/ico_link.svg">
                    </a>

                    <?php if($pm['mb_id'] == $member['mb_id']) { ?>
                        　<a class="fl_btns fl_btns_txt" title="정보수정" alt="정보수정" href="<?php echo G5_BBS_URL ?>/member_confirm.php?url=<?php echo G5_BBS_URL ?>/register_form.php">정보수정</a>
                        <a class="fl_btns fl_btns_txt" title="탈퇴" alt="탈퇴" href="javascript:member_leave();">탈퇴</a>
                    <?php } else { ?>
                        <a class="fl_btns" title="쪽지보내기" alt="쪽지보내기" href="<?php echo G5_BBS_URL ?>/memo_form.php?me_recv_mb_id=<?php echo $pm['mb_id'] ?>" onclick="win_memo(this.href); return false;">
                            <img src="<?php echo $partner_url ?>/image/ico_msg.svg">
                        </a>
                        
                        <?php if (isset($chat_set['ch_use']) && $chat_set['ch_use'] == 1) { ?>
                        <a class="fl_btns" title="채팅하기" alt="채팅하기" href="<?php echo G5_URL ?>/rb/chat_form.php?me_recv_mb_id=<?php echo $pm['mb_id'] ?>" onclick="win_chat(this.href); return false;">
                            <img src="<?php echo $partner_url ?>/image/ico_chat.svg">
                        </a>
                        <?php } ?>
                        
                        <a class="fl_btns" title="미니홈" alt="미니홈" href="<?php echo G5_URL ?>/rb/home.php?mb_id=<?php echo $pm['mb_id'] ?>">
                            <img src="<?php echo $partner_url ?>/image/ico_home_p.svg">
                        </a>
                        
                    
                    <?php 
                        if(isset($sb['sb_use']) && $sb['sb_use'] == 1) { // 구독 사용시
                            $sb_mb_id = $pm['mb_id'];
                            include_once(G5_PATH.'/rb/rb.mod/subscribe/subscribe_my.skin.php');
                        }
                    ?>
                    
                    <?php } ?>
                    
        	    </li>
        	    <?php
                $currents_url = G5_URL."/store/?p=".$pm['mb_id'];
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
        	                    alert("스토어 링크가 복사 되었습니다."); // 사용자 알림
        	                }
        	            });

        	        });
        	    </script>
        	</ul>
	    </div>
    </ul>
    <div class="cb"></div>
    
</div>




<div class="partner_item_wrap">
<div id="ssch">

	        <form name="frmdetailsearch">
	        <input type="hidden" name="qsort" id="qsort" value="<?php echo $qsort ?>">
	        <input type="hidden" name="qorder" id="qorder" value="<?php echo $qorder ?>">
	        <input type="hidden" name="qcaid" id="qcaid" value="<?php echo $qcaid ?>">
	        <input type="hidden" name="p" id="p" value="<?php echo $p ?>">
        	</form>
        	
        	
        <div class="ss_list_wraps">
        	
        <?php if($total_count > 0) { ?>
		<!-- 검색된 분류 시작 { -->
	    <div id="ssch_cate">

            <div class="swiper-container swiper-container-ss">
                <ul class="swiper-wrapper swiper-wrapper-ss">
                    <?php
                    //echo '<li class="swiper-slide swiper-slide-ss"><a href="#" onclick="set_ca_id(\'\'); return false;" class="font-naver-EB">전체보기</a></li>'.PHP_EOL;
                    $total_cnt = 0;
                    foreach((array) $categorys as $row){
                        if( empty($row) ) continue;
                        echo "<li class=\"swiper-slide swiper-slide-ss\"><a href=\"#\" onclick=\"set_ca_id('{$row['ca_id']}'); return false;\" class=\"font-naver-B\">{$row['ca_name']} (".$row['cnt'].")</a></li>\n";
                        $total_cnt += $row['cnt'];
                    }
                    ?>
                    <?php if(isset($_GET['qcaid']) && $_GET['qcaid'] || isset($_GET['qitemtype']) && $_GET['qitemtype']) { ?>
                    <li class="swiper-slide swiper-slide-ss"><a href="#" onclick="set_ca_id(''); return false;" style="color:#888;">전체보기</a></li>
                    <?php } ?>
                </ul>
            </div>

            <script>
                var swiper = new Swiper('.swiper-container-ss', {
                    slidesPerView: 'auto', //가로갯수
                    spaceBetween: 25, // 간격
                    touchRatio: 1, // 드래그 가능여부(1, 0)
                    slidesOffsetBefore: 20, //좌측여백 px
                    slidesOffsetAfter: 20, // 우측여백 px

                    breakpoints: { // 반응형 처리
                        1024: {
                            slidesPerView: 'auto',
                            touchRatio: 1,
                            spaceBetween: 20,
                        },
                        768: {
                            slidesPerView: 'auto',
                            touchRatio: 1,
                            spaceBetween: 20,
                        },
                        10: {
                            slidesPerView: 'auto',
                            touchRatio: 1,
                            spaceBetween: 20,
                        }
                    }

                });
            </script>

        </div>
	    <!-- } 검색된 분류 끝 -->
        <?php } ?>
        
            <div class="sort_wrpas">
                <select id="ssch_sort_all" onchange="set_sort(this.value.split(',')[0], this.value.split(',')[1])" class="select">
                    <option value="">상품정렬</option>
                    <option value="it_sum_qty,desc" <?php if(isset($_GET['qsort']) && $_GET['qsort'] == "it_sum_qty") { ?>selected<?php } ?>>판매순</option>
                    <option value="it_price,asc" <?php if(isset($_GET['qsort']) && $_GET['qsort'] == "it_price" && $_GET['qorder'] == "asc") { ?>selected<?php } ?>>낮은가격순</option>
                    <option value="it_price,desc" <?php if(isset($_GET['qsort']) && $_GET['qsort'] == "it_price" && $_GET['qorder'] == "desc") { ?>selected<?php } ?>>높은가격순</option>
                    <option value="it_use_avg,desc" <?php if(isset($_GET['qsort']) && $_GET['qsort'] == "it_use_avg") { ?>selected<?php } ?>>평점순</option>
                    <option value="it_use_cnt,desc" <?php if(isset($_GET['qsort']) && $_GET['qsort'] == "it_use_cnt") { ?>selected<?php } ?>>후기순</option>
                    <option value="it_update_time,desc" <?php if(isset($_GET['qsort']) && $_GET['qsort'] == "it_update_time") { ?>selected<?php } ?>>등록일순</option>
                </select>
            </div>
            
            <div class="cb"></div>
            
        </div>
		
    <!-- 검색결과 시작 { -->
    <div>
        <?php
        // 리스트 유형별로 출력
        if (isset($list) && is_object($list) && method_exists($list, 'run')) {
            $list->set_is_page(true);
            $list->set_view('it_img', true);
            $list->set_view('it_name', true);
            $list->set_view('it_basic', true);
            $list->set_view('it_cust_price', false);
            $list->set_view('it_price', true);
            $list->set_view('it_icon', true);
            $list->set_view('sns', true);
            $list->set_view('star', true);
            echo $list->run();
        }
        else
        {
            $i = 0;
            $error = '<p class="sct_nofile">'.$list_file.' 파일을 찾을 수 없습니다.<br>관리자에게 알려주시면 감사하겠습니다.</p>';
        }

        if ($i==0)
        {
            echo '<div>'.$error.'</div>';
        }

        $query_string = 'qname='.$qname.'&amp;qexplan='.$qexplan.'&amp;qid='.$qid;
        if($qfrom && $qto) $query_string .= '&amp;qfrom='.$qfrom.'&amp;qto='.$qto;
        $query_string .= '&amp;qcaid='.$qcaid.'&amp;q='.urlencode($q);
        $query_string .='&amp;qsort='.$qsort.'&amp;qorder='.$qorder;
        echo get_paging($config['cf_write_pages'], $page, $total_page, $_SERVER['SCRIPT_NAME'].'?'.$query_string.'&amp;page=');
        ?>
    </div>
    <!-- } 검색결과 끝 -->
</div>

</div>



<script>
function set_sort(qsort, qorder)
{
    var f = document.frmdetailsearch;
    f.qsort.value = qsort;
    f.qorder.value = qorder;
    f.submit();
}

function set_ca_id(qcaid)
{
    var f = document.frmdetailsearch;
    f.qcaid.value = qcaid;
    f.submit();
}

</script>


<script>
function member_leave() {  // 회원 탈퇴
    if (confirm("탈퇴시 보유하신 포인트 및 기타 혜택, 개인정보 등\n모든 정보가 삭제 되며 동일 아이디로 재가입이 불가능합니다.\n\n정말 탈퇴 하시겠습니까?"))
        location.href = '<?php echo G5_BBS_URL ?>/member_confirm.php?url=member_leave.php';
}
</script>