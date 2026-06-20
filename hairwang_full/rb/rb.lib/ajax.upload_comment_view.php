<?php
include_once('../../common.php');

$bo_table = isset($_POST['bo_table']) ? $_POST['bo_table'] : '';
$comment_id = isset($_POST['comment_id']) ? $_POST['comment_id'] : '';
$wr_id = isset($_POST['wr_id']) ? $_POST['wr_id'] : '';

if($bo_table && $comment_id) {         
    $is_file = false;
    if ($member['mb_level'] >= $board['bo_upload_level']) {
        $is_file = true;
    }

    $is_file_content = false;
    if ($board['bo_use_file_content']) {
        $is_file_content = true;
    }

    $file_count = (int)$board['bo_upload_count'];
                                                                                 
    $file_c = get_file_comment($bo_table, $wr_id, $comment_id);
    
    if($file_count < $file_c['count']) {
        $file_count = $file_c['count'];
    }

    for($i=0;$i<$file_count;$i++){
        if(! isset($file_c[$i])) {
            $file_c[$i] = array('file'=>null, 'source'=>null, 'size'=>null, 'bf_content' => null);
        }
    }
                                                        
                                                                                 
$wr_file = isset($wr_file) ? $wr_file : [];
$wf_cnt = count((array)$wr_file) + 1;                                             
                                                                             
?>

<?php if (isset($is_file) && $is_file && $wf_cnt > 0): ?>
    <?php
    $new_files = [];

    // 파일이 존재하는지 확인
    if (isset($file_c) && is_array($file_c)) {
        foreach ($file_c as $k => $v) {
            // 등록된 파일에는 삭제시 필요한 bf_file 필드 추가
            if (empty($v['file'])) {
                continue;
            }
            $new_files[] = $v;
        }
    }

    ?>
        
<?php foreach($new_files as $v): ?>

<div class="swiper-slide swiper-slide_lists swiper-wfile_padding">
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
        <div class="au_btn_del font-r" onclick="delete_file('<?php echo $v['file']?>',this)">삭제</div>
        <div class="cut" style="margin-top:5px;"><?php echo $v['source'] ?></div>
    </div>
                                    
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php }