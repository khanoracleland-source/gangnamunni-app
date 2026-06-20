<!-- 편집기능 -->
    <div class="btm_btns mf_wrap_inner">

        <?php if($is_member) { ?>
                <?php if($view['mb_id'] != $member['mb_id']) { ?>
                <?php
                    $edit_acc = rb_edit_acc($member['mb_id'], $view['wr_id'], $bo_table);
                ?>
                <?php if(isset($edit_acc) && $edit_acc == 2) { ?>
                    <a href="<?php echo G5_BBS_URL ?>/write_mod.php?w=u&bo_table=free&wr_id=<?php echo $view['wr_id'] ?>&edit=<?php echo $member['mb_id'] ?>" class="fl_btns">
                        <span class="font-B">편집</span>
                    </a>
                <?php } else if(isset($edit_acc) && $edit_acc == 1) { ?>
                    <a href="javascript:rb_edit_del('<?php echo $member['mb_id'] ?>');" id="rb_edit_add" class="fl_btns">
                        <span class="font-B">요청취소</span>
                    </a>
                <?php } else { ?>
                    <?php if(!$is_admin && $view['mb_id']) { ?>
                    <a href="javascript:rb_edit_add('<?php echo $member['mb_id'] ?>');" id="rb_edit_add" class="fl_btns">
                        <span class="font-B">편집권한 요청</span>
                    </a>
                    <?php } ?>
                <?php } ?>
                
                <script>
                    var rb_edit_add = function(add_id) { // 등록

                        var wr_id = "<?php echo $view['wr_id'] ?>"; //게시물 ID
                        var bo_table = "<?php echo $bo_table; ?>"; //게시판 ID
                        var wr_subject = "<?php echo $view['wr_subject'] ?>"; //게시물 제목
                        var mb_id = add_id; //요청자 ID
                        var write_id = "<?php echo $view['mb_id'] ?>"; //원글 작성자ID
                        
                        if (confirm('[' + wr_subject + ']\n게시물에 대해 <?php echo $view['wr_name'] ?> 님께 편집권한을 요청 하시겠습니까?')) {

                            $.ajax({
                                url: g5_url + '/plugin/editor/' + g5_editor + '/plugin/edit/ajax.edit_add.php', // 쿼리 파일
                                type: 'post', // 전송방식
                                dataType: 'html',
                                data: {
                                    "mb_id": mb_id,
                                    "wr_id": wr_id,
                                    "bo_table": bo_table,
                                    "wr_subject": wr_subject,
                                    "write_id": write_id
                                },
                                success: function(data) {
                                    eval(data)
                                    window.location.reload();
                                },
                                error: function(err) {
                                    alert("오류가 발생 했습니다. 다시 시도해주세요."); //실패
                                }
                            });

                        } else {
                            return false;
                        }
                    }
                    
                    
                    var rb_edit_del = function(add_id) { // 등록

                        var wr_id = "<?php echo $view['wr_id'] ?>"; //게시물 ID
                        var bo_table = "<?php echo $bo_table; ?>"; //게시판 ID
                        var wr_subject = "<?php echo $view['wr_subject'] ?>"; //게시물 제목
                        var mb_id = add_id; //요청자 ID
                        var write_id = "<?php echo $view['mb_id'] ?>"; //원글 작성자ID
                        var ed_type = "del";
                        
                        if (confirm('[' + wr_subject + ']\n게시물에 대해 편집권한 요청을 취소 하시겠습니까?')) {

                            $.ajax({
                                url: g5_url + '/plugin/editor/' + g5_editor + '/plugin/edit/ajax.edit_add.php', // 쿼리 파일
                                type: 'post', // 전송방식
                                dataType: 'html',
                                data: {
                                    "mb_id": mb_id,
                                    "wr_id": wr_id,
                                    "bo_table": bo_table,
                                    "wr_subject": wr_subject,
                                    "write_id": write_id,
                                    "ed_type": ed_type
                                },
                                success: function(data) {
                                    eval(data)
                                    window.location.reload();
                                },
                                error: function(err) {
                                    alert("오류가 발생 했습니다. 다시 시도해주세요."); //실패
                                }
                            });

                        } else {
                            return false;
                        }
                    }
                </script>
                <?php } ?>
                <?php } ?>
                
                <?php 
                if(isset($view['wr_datetime_last_id']) && $view['wr_datetime_last_id']) { 
                    $w_last = get_member($view['wr_datetime_last_id']);
                ?>
                <a href="javascript:void(0);" class="fl_btns" style="border:1px solid rgba(0,0,0,0.1) !important; background-color:transparent; font-size:12px; color:#000;">
                    발행인 <span class="font-B"><?php echo $view['wr_name'] ?></span> | 최종편집 : <?php echo $w_last['mb_nick'] ?>
                </a>
                <?php } ?>
       
        <?php
        if(isset($edit_acc) && $edit_acc == 2 || $is_admin || $view['mb_id'] == $member['mb_id']) {
        
        $rb_editor_edit_table2 = G5_TABLE_PREFIX . "rb_editor_edit_content"; //테이블명
        if($is_admin || $view['mb_id'] == $member['mb_id']) { //관리자 또는 작성자라면,
            $edit_sql_common = " from {$rb_editor_edit_table2} where ed_wr_id = '{$view['wr_id']}' and ed_bo_table = '{$bo_table}' and ed_write_id = '{$view['mb_id']}' ";
        } else { //자신이 요청한 내역만
            $edit_sql_common = " from {$rb_editor_edit_table2} where ed_wr_id = '{$view['wr_id']}' and ed_bo_table = '{$bo_table}' and ed_write_id = '{$view['mb_id']}' and ed_mb_id = '{$member['mb_id']}' ";
        }
        
        $sql_cnt = " select count(*) as cnt " . $edit_sql_common;
        $row_cnt = sql_fetch($sql_cnt);
        $total_count_edit = isset($row_cnt['cnt']) ? $row_cnt['cnt'] : 0;

        $edit_sql  = " select * $edit_sql_common order by ed_time desc ";
        $result_edit = sql_query($edit_sql);
    
        
        ?>


        <div class="rb_mf_wrap_list">


            <div>

                <button type="button" class="cmt_btn2"><span class="total"><b>편집승인 요청</b> <?php echo $total_count_edit ?></span></button>


                <section id="bo_vc2" style="display: block;">
                    <?php 
                for ($i = 0; $row_ed = sql_fetch_array($result_edit); $i++) { 
                    
                    $mbx = get_member($row_ed['ed_mb_id']);
                    $tmp_name = get_text(cut_str($mbx['mb_nick'], $config['cf_cut_name']));
                    
                    if ($board['bo_use_sideview']) {
                        $edit_name = get_sideview($row_ed['ed_mb_id'], $tmp_name, $mbx['mb_email'], $mbx['mb_homepage']);
                    } else {
                        $edit_name = '<span class="'.($row_ed['ed_mb_id']?'member':'guest').'">'.$tmp_name.'</span>';
                    }
                    
                    if($row_ed['ed_status'] == 1) {
                        $ed_status = "(승인)";
                    } else { 
                        $ed_status = "(요청)";
                    }
                ?>
                    <article>
                        <div class="cm_wrap">
                            <header style="z-index:2">
                                <span class="sv_wrap"><?php echo $edit_name ?></span>　
                                <span><?php echo isset($row_ed['ed_time']) ? $row_ed['ed_time'] : ''; ?>　
                                    <?php if($is_admin || $view['mb_id'] == $member['mb_id']) { ?>
                                    <span <?php if(isset($row_ed['ed_status']) && $row_ed['ed_status'] == 1) { ?>class="main_color" <?php } ?>><?php echo $ed_status ?></span>
                                    <?php } ?>
                            </header>
                        </div>



                        <div class="bo_vl_opt edit_add_wrap">

                            <?php if(isset($row_ed['ed_status']) && $row_ed['ed_status'] == 0) { ?>
                            <?php if($row_ed['ed_mb_id'] == $member['mb_id'] || $is_admin || $view['mb_id'] == $member['mb_id']) { ?>
                            <button type="button" class="edit_add_b" onclick="javascript:rb_edit_pc('<?php echo isset($row_ed['ed_mb_id']) ? $row_ed['ed_mb_id'] : ''; ?>', '<?php echo isset($row_ed['ed_id']) ? $row_ed['ed_id'] : ''; ?>');">취소</button>
                            <?php } ?>
                            <?php } ?>


                            <?php if(isset($row_ed['ed_status']) && $row_ed['ed_status'] == 1) { ?>
                            <button type="button" class="edit_add_b" onclick="javascript:alert('편집 승인 처리되어 게재 완료 되었습니다.');">완료</button>
                            <?php } else { ?>
                            <button type="button" class="edit_add_a" onclick="javascript:open_view('<?php echo isset($row_ed['ed_id']) ? $row_ed['ed_id'] : ''; ?>');">보기</button>
                            <?php if($is_admin || $view['mb_id'] == $member['mb_id']) { ?>
                            <button type="button" class="edit_add_a" onclick="javascript:rb_edit_ps('<?php echo isset($row_ed['ed_mb_id']) ? $row_ed['ed_mb_id'] : ''; ?>', '<?php echo isset($row_ed['ed_id']) ? $row_ed['ed_id'] : ''; ?>');">승인</button>
                            <?php } ?>
                            <?php } ?>
                        </div>


                        <div class="cb"></div>


                    </article>
                    <?php } ?>
                    <?php if ($i == 0) { ?>
                    <p id="bo_vc_empty">등록된 편집승인 요청이 없습니다.</p>
                    <?php } ?>

                </section>
                <!-- } 댓글 끝 -->

            </div>

        </div>
        <?php } ?>


        <div class="rb_mf_wrap_list">

        <?php
        $rb_editor_edit_table = G5_TABLE_PREFIX . "rb_editor_edit"; //테이블명
        if($is_admin || $view['mb_id'] == $member['mb_id']) { //승인권한이 있다면,
            $edit_sql_common = " from {$rb_editor_edit_table} where ed_wr_id = '{$view['wr_id']}' and ed_bo_table = '{$bo_table}' and ed_write_id = '{$view['mb_id']}' ";
        } else { //승인권한이 없다면 승인된 회원만
            $edit_sql_common = " from {$rb_editor_edit_table} where ed_wr_id = '{$view['wr_id']}' and ed_bo_table = '{$bo_table}' and ed_write_id = '{$view['mb_id']}' and ed_status = '1' ";
        }
        
        $sql_cnt = " select count(*) as cnt " . $edit_sql_common;
        $row_cnt = sql_fetch($sql_cnt);
        $total_count_edit = isset($row_cnt['cnt']) ? $row_cnt['cnt'] : 0;

        $edit_sql  = " select * $edit_sql_common order by ed_time desc ";
        $result_edit = sql_query($edit_sql);
    
        
        ?>
            <div>
                <button type="button" class="cmt_btn2"><span class="total"><b>편집 참여자</b> <?php echo $total_count_edit ?></span></button>

                <!-- 댓글 시작 { -->
                <section id="bo_vc2" style="display: block;">
                    <?php 
                    for ($i = 0; $row_ed = sql_fetch_array($result_edit); $i++) { 

                        $mbx = get_member($row_ed['ed_mb_id']);
                        $tmp_name = get_text(cut_str($mbx['mb_nick'], $config['cf_cut_name']));

                        if ($board['bo_use_sideview']) {
                            $edit_name = get_sideview($row_ed['ed_mb_id'], $tmp_name, $mbx['mb_email'], $mbx['mb_homepage']);
                        } else {
                            $edit_name = '<span class="'.($row_ed['ed_mb_id']?'member':'guest').'">'.$tmp_name.'</span>';
                        }

                        if($row_ed['ed_status'] == 1) {
                            $ed_status = "(승인)";
                        } else { 
                            $ed_status = "(요청)";
                        }
                ?>
                    <article>
                        <div class="cm_wrap">
                            <header style="z-index:2">
                                <span class="sv_wrap"><?php echo $edit_name ?></span>　
                                <span><?php echo isset($row_ed['ed_time']) ? $row_ed['ed_time'] : ''; ?>　
                                    <?php if($is_admin || $view['mb_id'] == $member['mb_id']) { ?>
                                    <span <?php if(isset($row_ed['ed_status']) && $row_ed['ed_status'] == 1) { ?>class="main_color" <?php } ?>><?php echo $ed_status ?></span>
                                    <?php } ?>
                            </header>
                        </div>



                        <div class="bo_vl_opt edit_add_wrap">
                            <?php if(isset($row_ed['ed_status']) && $row_ed['ed_status'] == 1) { ?>
                            <?php if(isset($row_ed['ed_mb_id']) && $row_ed['ed_mb_id'] == $member['mb_id'] || $is_admin || $view['mb_id'] == $member['mb_id']) { ?>
                            <button type="button" class="edit_add_b" onclick="javascript:rb_edit_c('<?php echo isset($row_ed['ed_mb_id']) ? $row_ed['ed_mb_id'] : ''; ?>', '<?php echo isset($row_ed['ed_id']) ? $row_ed['ed_id'] : ''; ?>');">취소</button>
                            <?php } ?>
                            <?php } ?>

                            <?php if($is_admin || $view['mb_id'] == $member['mb_id']) { ?>
                            <button type="button" class="edit_add_a" onclick="javascript:rb_edit_s('<?php echo isset($row_ed['ed_mb_id']) ? $row_ed['ed_mb_id'] : ''; ?>', '<?php echo isset($row_ed['ed_id']) ? $row_ed['ed_id'] : ''; ?>');">승인</button>
                            <?php } ?>
                        </div>



                        <div class="cb"></div>


                    </article>
                    <?php } ?>
                    <?php if ($i == 0) { ?>
                    <p id="bo_vc_empty">등록된 편집자가 없습니다.</p>
                    <?php } ?>

                </section>
                <!-- } 댓글 끝 -->

            </div>

        </div>



        <script>
            function open_view(ed_id) {
                // #bo_v_con 요소의 width 가져오기
                var bo_v_con = document.querySelector('#bo_v_con');
                var popupWidth = bo_v_con ? bo_v_con.offsetWidth : 400; // 기본값 800

                // URL 설정
                var popupUrl = g5_url + `/plugin/editor/` + g5_editor + `/plugin/edit/popup_view.php?ed_id=${encodeURIComponent(ed_id)}`;

                // 새 창 열기
                window.open(popupUrl, 'previewWindow', `width=${popupWidth},height=600,resizable=yes`);
            }
        </script>


        <script>
            var rb_edit_ps = function(id, ed_id) { // 편집내용 게재

                var wr_id = "<?php echo $view['wr_id'] ?>"; //게시물 ID
                var bo_table = "<?php echo $bo_table; ?>"; //게시판 ID
                var wr_subject = "<?php echo $view['wr_subject'] ?>"; //게시물 제목
                var mb_id = id; //요청자 ID
                var ed_id = ed_id;
                var write_id = "<?php echo $view['mb_id'] ?>"; //원글 작성자ID

                if (confirm('편집을 승인처리 하시겠습니까?\n승인 처리시 해당 게시물의 내용이 승인 요청한 내용으로 변경 되며 취소할 수 없습니다.')) {

                    $.ajax({
                        url: g5_url + '/plugin/editor/' + g5_editor + '/plugin/edit/ajax.edit_add.php', // 쿼리 파일
                        type: 'post', // 전송방식
                        dataType: 'html',
                        data: {
                            "wr_id": wr_id,
                            "bo_table": bo_table,
                            "wr_subject": wr_subject,
                            "mb_id": mb_id,
                            "ed_id": ed_id,
                            "write_id": write_id,
                            "ed_type": "ps",
                        },
                        success: function(data) {
                            eval(data)
                            window.location.reload();
                        },
                        error: function(err) {
                            alert("오류가 발생 했습니다. 다시 시도해주세요."); //실패
                        }
                    });

                } else {
                    return false;
                }
            }

            var rb_edit_pc = function(id, ed_id) { // 편집내용 게재
                var wr_id = "<?php echo $view['wr_id'] ?>"; //게시물 ID
                var bo_table = "<?php echo $bo_table; ?>"; //게시판 ID
                var wr_subject = "<?php echo $view['wr_subject'] ?>"; //게시물 제목
                var mb_id = id; //요청자 ID
                var ed_id = ed_id;
                var write_id = "<?php echo $view['mb_id'] ?>"; //원글 작성자ID


                if (confirm('승인요청을 취소처리 하시겠습니까?')) {

                    $.ajax({
                        url: g5_url + '/plugin/editor/' + g5_editor + '/plugin/edit/ajax.edit_add.php', // 쿼리 파일
                        type: 'post', // 전송방식
                        dataType: 'html',
                        data: {
                            "wr_id": wr_id,
                            "bo_table": bo_table,
                            "wr_subject": wr_subject,
                            "mb_id": mb_id,
                            "ed_id": ed_id,
                            "write_id": write_id,
                            "ed_type": "pc",
                        },
                        success: function(data) {
                            eval(data)
                            window.location.reload();
                        },
                        error: function(err) {
                            alert("오류가 발생 했습니다. 다시 시도해주세요."); //실패
                        }
                    });

                } else {
                    return false;
                }
            }

            var rb_edit_s = function(id, ed_id) { // 등록

                var wr_id = "<?php echo $view['wr_id'] ?>"; //게시물 ID
                var bo_table = "<?php echo $bo_table; ?>"; //게시판 ID
                var wr_subject = "<?php echo $view['wr_subject'] ?>"; //게시물 제목
                var mb_id = id; //요청자 ID
                var ed_id = ed_id;
                var write_id = "<?php echo $view['mb_id'] ?>"; //원글 작성자ID

                if (confirm('편집권한을 승인처리 하시겠습니까?\n승인 처리시 해당 게시물에 대해 편집권한이 부여 됩니다.')) {

                    $.ajax({
                        url: g5_url + '/plugin/editor/' + g5_editor + '/plugin/edit/ajax.edit_add.php', // 쿼리 파일
                        type: 'post', // 전송방식
                        dataType: 'html',
                        data: {
                            "wr_id": wr_id,
                            "bo_table": bo_table,
                            "wr_subject": wr_subject,
                            "mb_id": mb_id,
                            "ed_id": ed_id,
                            "write_id": write_id,
                            "ed_type": "confirm",
                        },
                        success: function(data) {
                            eval(data)
                            window.location.reload();
                        },
                        error: function(err) {
                            alert("오류가 발생 했습니다. 다시 시도해주세요."); //실패
                        }
                    });

                } else {
                    return false;
                }
            }

            var rb_edit_c = function(id, ed_id) { // 등록

                var wr_id = "<?php echo $view['wr_id'] ?>"; //게시물 ID
                var bo_table = "<?php echo $bo_table; ?>"; //게시판 ID
                var wr_subject = "<?php echo $view['wr_subject'] ?>"; //게시물 제목
                var mb_id = id; //요청자 ID
                var ed_id = ed_id;
                var write_id = "<?php echo $view['mb_id'] ?>"; //원글 작성자ID

                if (confirm('편집권한을 취소처리 하시겠습니까?\n취소 처리시 해당 게시물에 대해 편집권한이 제거 됩니다.')) {

                    $.ajax({
                        url: g5_url + '/plugin/editor/' + g5_editor + '/plugin/edit/ajax.edit_add.php', // 쿼리 파일
                        type: 'post', // 전송방식
                        dataType: 'html',
                        data: {
                            "wr_id": wr_id,
                            "bo_table": bo_table,
                            "wr_subject": wr_subject,
                            "mb_id": mb_id,
                            "ed_id": ed_id,
                            "write_id": write_id,
                            "ed_type": "cancle",
                        },
                        success: function(data) {
                            eval(data)
                            window.location.reload();
                        },
                        error: function(err) {
                            alert("오류가 발생 했습니다. 다시 시도해주세요."); //실패
                        }
                    });

                } else {
                    return false;
                }
            }
        </script>


        </div>