<?php
include_once('./_common.php');
$ed_id = $_GET['ed_id'];
$rb_editor_edit_table2 = G5_TABLE_PREFIX . "rb_editor_edit_content"; //테이블명

$content = sql_fetch ("select ed_wr_content from {$rb_editor_edit_table2} where ed_id = '{$ed_id}' ");
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <title>미리보기</title>
    <link rel="stylesheet" href="../../css/preview.css?v=2">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
    <div id="rb_editor_preview">
        <?php echo $content['ed_wr_content'] ?>
    </div>
</body>

</html>