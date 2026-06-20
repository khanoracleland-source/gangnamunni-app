<?php
include_once('../../../../../common.php');
header("Content-Type: text/css");
$columns = isset($_GET['columns']) ? intval($_GET['columns']) : 4;

$bo_gallery_width = isset($_GET['bo_gallery_width']) ? htmlspecialchars($_GET['bo_gallery_width']) : htmlspecialchars($rb_core['bo_gallery_width']);
$bo_mobile_gallery_width = isset($_GET['bo_mobile_gallery_width']) ? htmlspecialchars($_GET['bo_mobile_gallery_width']) : htmlspecialchars($rb_core['bo_mobile_gallery_width']);
$bo_gallery_height = isset($_GET['bo_gallery_height']) ? htmlspecialchars($_GET['bo_gallery_height']).'px' : htmlspecialchars($rb_core['bo_gallery_height']).'px';
$bo_mobile_gallery_height = isset($_GET['bo_mobile_gallery_height']) ? htmlspecialchars($_GET['bo_mobile_gallery_height']).'px' : htmlspecialchars($rb_core['bo_mobile_gallery_height']).'px';

$gap_width_pc = $bo_gallery_width + 30;
$gap_width_mo = $bo_mobile_gallery_width + 30;


if(isset($columns) && $columns == "1") {
    $grids = "50%";
} else if(isset($columns) && $columns == "2") {
    $grids = "35%";
} else if(isset($columns) && $columns == "3") {
    $grids = "25%";
} else if(isset($columns) && $columns == "4") {
    $grids = "20%";
} else if(isset($columns) && $columns == "5") {
    $grids = "15%";
} else if(isset($columns) && $columns == "6") {
    $grids = "13%";
} else { 
    $grids = "25%";
}
?>



.rb_gallery_grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(<?php echo $grids ?>, 1fr));
    gap: 30px;
    margin: 0 auto;
    padding-top:40px;
    padding-bottom:40px;
}

.bbs_prd_list_img img {width: <?php echo $bo_gallery_width ?>px; height:<?php echo $bo_gallery_height ?>; border-radius: 10px; object-fit: cover;}
.bbs_prd_list_con {padding-left: <?php echo $gap_width_pc ?>px;}

@media all and (max-width:1024px) {
    .rb_gallery_grid {
        grid-template-columns: repeat(auto-fill, minmax(100% ,1fr));
        gap: 20px;
    }
    .bbs_prd_list_img img {width: <?php echo $bo_mobile_gallery_width ?>px; height:<?php echo $bo_mobile_gallery_height ?>; border-radius: 10px; object-fit: cover;}
    .bbs_prd_list_con {padding-left: <?php echo $gap_width_mo ?>px;}
}
