<?php
$sub_menu = '000650';
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, "w");
add_stylesheet('<link rel="stylesheet" href="./css/style.css">', 0);

//테이블이 있는지 검사한다.
if(!sql_query(" DESCRIBE rb_partner ", false)) {
       $query_cp = sql_query(" CREATE TABLE IF NOT EXISTS `rb_partner` (
        `pa_is` int(4) NOT NULL COMMENT '사용여부',
        `pa_use` int(4) NOT NULL COMMENT '입점신청가능여부',
        `pa_ssr` int(4) NOT NULL COMMENT '판매수수료(%)',
        `pa_ssr2` int(11) NOT NULL COMMENT '판매수수료(고정/원)',
        `pa_day` int(11) NOT NULL COMMENT '출금가능일',
        `pa_add_use` int(4) NOT NULL COMMENT '가입자동승인 처리여부',
        `pa_item_use` int(4) NOT NULL COMMENT '상품 자동승인 처리여부',
        `pa_level` int(4) NOT NULL COMMENT '승인이후 레벨조정',
        `pa_point_use` int(4) NOT NULL COMMENT '0:포인트/1:예치금',
        `pa_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00' COMMENT '등록일시(변경일시)'
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8 ", true);
}

if(!sql_query(" DESCRIBE rb_pa_point ", false)) {
       $query_cp2 = sql_query(" CREATE TABLE IF NOT EXISTS `rb_pa_point` (
        `po_id` int(11) NOT NULL AUTO_INCREMENT,
          `mb_id` varchar(20) NOT NULL DEFAULT '',
          `po_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
          `po_content` varchar(255) NOT NULL DEFAULT '',
          `po_point` int(11) NOT NULL DEFAULT 0,
          `po_use_point` int(11) NOT NULL DEFAULT 0,
          `po_expired` tinyint(4) NOT NULL DEFAULT 0,
          `po_expire_date` date NOT NULL DEFAULT '0000-00-00',
          `po_mb_point` int(11) NOT NULL DEFAULT 0,
          `po_rel_table` varchar(20) NOT NULL DEFAULT '',
          `po_rel_id` varchar(20) NOT NULL DEFAULT '',
          `po_rel_action` varchar(100) NOT NULL DEFAULT '',
          PRIMARY KEY (`po_id`),
          KEY `index1` (`mb_id`,`po_rel_table`,`po_rel_id`,`po_rel_action`),
          KEY `index2` (`po_expire_date`)
      ) ENGINE=MyISAM DEFAULT CHARSET=utf8 ", true);
}

$columns_to_add = [
    'mb_partner' => 'INT(11) NOT NULL DEFAULT 0',
    'mb_partner_add_time' => 'datetime NOT NULL DEFAULT \'0000-00-00 00:00:00\'',
    'mb_bank' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
    'mb_ssr' => 'INT(11) NOT NULL DEFAULT 0'
];

foreach ($columns_to_add as $column => $attributes) {
    // 컬럼이 있는지 확인
    $column_check = sql_query("SHOW COLUMNS FROM {$g5['member_table']} LIKE '{$column}'", false);
    if (!sql_num_rows($column_check)) {
        // 컬럼 추가
        sql_query("ALTER TABLE {$g5['member_table']} ADD {$column} {$attributes}", true);
    }
}

$columns_to_add2 = [
    'it_partner' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
    'it_ssr' => 'INT(11) NOT NULL DEFAULT 0',
    'it_ssr_w' => 'INT(11) NOT NULL DEFAULT 0'
];

foreach ($columns_to_add2 as $column2 => $attributes2) {
    // 컬럼이 있는지 확인
    $column_check2 = sql_query("SHOW COLUMNS FROM {$g5['g5_shop_item_table']} LIKE '{$column2}'", false);
    if (!sql_num_rows($column_check2)) {
        // 컬럼 추가
        sql_query("ALTER TABLE {$g5['g5_shop_item_table']} ADD {$column2} {$attributes2}", true);
    }
}

$columns_to_add3 = [
    'ct_partner' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
    'ct_invoice' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
    'ct_delivery_company' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
    'ct_invoice_time' => 'DATETIME NOT NULL',
    'ct_js' => 'INT(4) NOT NULL DEFAULT 0',
    'ct_js_time' => 'DATETIME NOT NULL',
    'ct_js_price' => 'INT(11) NOT NULL DEFAULT 0',
    'ct_js_price_old' => 'INT(11) NOT NULL DEFAULT 0',
    'ct_js_use' => 'VARCHAR(100) NOT NULL DEFAULT \'\'',
    'ct_js_ssr' => 'INT(11) NOT NULL DEFAULT 0',
    'ct_js_cost' => 'INT(11) NOT NULL DEFAULT 0',
    'ct_total_price' => 'INT(11) NOT NULL DEFAULT 0',
    'ct_js_type' => 'VARCHAR(100) NOT NULL DEFAULT \'\''
];

foreach ($columns_to_add3 as $column3 => $attributes3) {
    // 컬럼이 있는지 확인
    $column_check3 = sql_query("SHOW COLUMNS FROM {$g5['g5_shop_cart_table']} LIKE '{$column3}'", false);
    if (!sql_num_rows($column_check3)) {
        // 컬럼 추가
        sql_query("ALTER TABLE {$g5['g5_shop_cart_table']} ADD {$column3} {$attributes3}", true);
    }
}


$sql = " select * from rb_partner limit 1";
$pa = sql_fetch($sql);

$pg_anchor = '<ul class="anchor">
    <li><a href="#anc_ap1">기본설정</a></li>
</ul>';

$g5['title'] = '입점 설정';
include_once (G5_ADMIN_PATH.'/admin.head.php');

?>


<form name="seo_form" id="seo_form" action="./partner_form_update.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="token" value="" id="token">
    
    <section id="anc_ap1">
        <h2 class="h2_frm">기본 설정</h2>
        <?php echo $pg_anchor ?>

        <div class="tbl_frm01 tbl_wrap">
            <table>
                <colgroup>
                    <col class="grid_4">
                    <col>
                    <col class="grid_4">
                    <col>
                </colgroup>
                <tbody>
                    <tr>
                        <th scope="row">입점기능 사용여부</th>
                        <td colspan="3">
                            <?php echo help('체크를 해제 하시면 입점기능을 사용하지 않습니다.<br>입점사가 있더라도 정보는 표기되지 않으며, 상품등록 등의 입점기능을 사용할 수 없습니다.') ?>
                            <input type="checkbox" name="pa_is" value="1" id="pa_is" <?php echo isset($pa['pa_is']) && $pa['pa_is'] == 1 ? 'checked' : ''; ?>> <label for="pa_is">입점기능을 사용 합니다.</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">입점신청 가능여부</th>
                        <td colspan="3">
                            <?php echo help('체크를 해제 하시면 입점신청을 받지 않습니다.') ?>
                            <input type="checkbox" name="pa_use" value="1" id="pa_use" <?php echo isset($pa['pa_use']) && $pa['pa_use'] == 1 ? 'checked' : ''; ?>> <label for="pa_use">입점 신청을 받습니다.</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">자동처리 설정</th>
                        <td colspan="3">
                            <input type="checkbox" name="pa_add_use" value="1" id="pa_add_use" <?php echo isset($pa['pa_add_use']) && $pa['pa_add_use'] == 1 ? 'checked' : ''; ?>> <label for="pa_add_use">입점 신청시 자동승인</label>　
                            <input type="checkbox" name="pa_item_use" value="1" id="pa_item_use" <?php echo isset($pa['pa_item_use']) && $pa['pa_item_use'] == 1 ? 'checked' : ''; ?>> <label for="pa_item_use">상품 등록시 자동노출</label>　
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">승인 이후 레벨설정</th>
                        <td colspan="3">
                            <?php echo help('입점 승인시 승인된 회원을 설정된 레벨로 변경 합니다.<br>0 인 경우 레벨을 변경하지 않습니다.') ?>
                            <input type="number" name="pa_level" value="<?php echo isset($pa['pa_level']) ? get_sanitize_input($pa['pa_level']) : ''; ?>" id="pa_level" class="frm_input" style="width:70px;"> 레벨
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">판매 수수료(공통)</th>
                        <td colspan="3">
                            <?php echo help('배송비를 제외한 결제금액 에서 수수료 만큼 차감 후 정산 합니다.<br>판매대금은 관리자가 해당 주문건에대해 [완료] 처리시 즉시 정산 됩니다.<br>[회원 개별 수수료] 및 [상품 개별 수수료]가 적용되어 있는 경우 설정된 판매 수수료(공통)는 무시 됩니다.') ?>
                            <input type="number" name="pa_ssr" value="<?php echo isset($pa['pa_ssr']) ? get_sanitize_input($pa['pa_ssr']) : ''; ?>" id="pa_ssr" class="frm_input" style="width:70px;"> %　
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">정산방법</th>
                        <td colspan="3">
                            <?php echo help('판매대금을 정산하는 방법을 설정할 수 있습니다.<br>예치금으로 정산시 예치금의 출금신청을 통해 지급할 수 있습니다.') ?>
                            <input type="radio" name="pa_point_use" value="0" id="pa_point_use0" <?php echo (!isset($pa['pa_point_use']) || $pa['pa_point_use'] == 0) ? 'checked' : ''; ?>> <label for="pa_point_use0">직접 정산</label>　
                            
                            <?php
                            //예치금 사용여부 판단
                            $table_rb_point_c_set = sql_query("DESCRIBE rb_point_c_set", false);
                            if ($table_rb_point_c_set) {
                            ?>
                            <input type="radio" name="pa_point_use" value="1" id="pa_point_use1" <?php echo (isset($pa['pa_point_use']) && $pa['pa_point_use'] == 1) ? 'checked' : ''; ?>> <label for="pa_point_use1"><?php echo $pnt_c_name ?> 정산</label>
                            <?php } else { ?>
                            <input type="radio" name="pa_point_use" value="1" id="pa_point_use1" disabled> <label for="pa_point_use1">예치금 기능이 없습니다.</label>
                            <?php } ?>
                        </td>
                    </tr>
                    <!-- 추후고도화 {
                    <tr>
                        <th scope="row">출금설정</th>
                        <td colspan="3">
                            <?php echo help('판매대금 정산 이후 출금신청 가능한 일수를 설정해주세요.') ?>
                            대금 정산일로 부터 <input type="number" name="pa_day" value="<?php echo isset($pa['pa_day']) ? get_sanitize_input($pa['pa_day']) : ''; ?>" id="pa_day" class="frm_input" style="width:70px;"> 일이 지난 금액만 출금신청 가능 합니다.
                        </td>
                    </tr>
                    } -->
                    
                </tbody>
            </table>
        </div>
    </section>
    
<div class="local_desc01 local_desc">
    <p>
        <strong>판매수수료 적용 안내</strong><br><br>
        <strong>1순위 : </strong>상품 개별 수수료 (입점사 상품관리)<br>
        <strong>2순위 : </strong>입점사 개별 수수료 (입점사 관리)<br>
        <strong>3순위 : </strong>판매 수수료(공통) (입점 설정)<br><br>
        
        <strong>상품 개별 수수료 (%, 원) : </strong>각 상품별 수수료 설정이 가능합니다.<br>
        <strong>입점사 개별 수수료 (%) : </strong>각 회원별 수수료 설정이 가능합니다.<br>
        <strong>판매 수수료(공통) (%) : </strong>공통 수수료 설정이 가능합니다.<br><br>
        N% 의 경우 배송비를 제외한 상품 판매금액에서 설정된 N% 를 차감 후 정산 합니다.<br>
        N원 의 경우 상품 1개당 설정된 N원 을 차감 후 정산 합니다.
    </p>
</div>
    

    <div class="btn_fixed_top">
        <input type="submit" value="확인" class="btn_submit btn" accesskey="s">
    </div>
    
</form>




<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');