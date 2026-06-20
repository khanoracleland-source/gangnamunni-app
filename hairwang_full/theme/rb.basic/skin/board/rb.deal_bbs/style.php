<?php
include_once('../../../../../common.php');
header("Content-Type: text/css");
$columns = isset($_GET['columns']) ? intval($_GET['columns']) : 4;
$widths = isset($_GET['tb_width']) ? htmlspecialchars($_GET['tb_width']) : htmlspecialchars($rb_core['tb_width']).'px';

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

.bbs_prd_list .g_img img {width: 100%; height:auto; border-radius: 0px; object-fit: cover;}

@media all and (max-width:1024px) {
    .rb_gallery_grid {
        grid-template-columns: repeat(auto-fill, minmax(35% ,1fr));
        gap: 20px;
    }
}
