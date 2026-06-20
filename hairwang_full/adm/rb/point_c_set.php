<?php
$sub_menu = '000690';
include_once('./_common.php');

auth_check_menu($auth, $sub_menu, "w");
add_stylesheet('<link rel="stylesheet" href="./css/style.css">', 0);


// rb_chat_set 테이블 존재 여부 확인
$table_exists = sql_query("DESCRIBE rb_point_c_set", false);


// 테이블이 없는 경우 생성
if (!$table_exists) {
    $query_cp = sql_query("CREATE TABLE IF NOT EXISTS `rb_point_c_set` (
        `pnt_name` varchar(255) NOT NULL,
        `pnt_name_st` varchar(255) NOT NULL,
        `pnt_acc_use` int(4) NOT NULL,
        `pnt_add_use` int(4) NOT NULL,
        `pnt_point_acc_min` int(11) NOT NULL,
        `pnt_point_acc_max` int(11) NOT NULL,
        `pnt_point_add_min` int(11) NOT NULL,
        `pnt_point_add_max` int(11) NOT NULL,
        `pnt_vat`  int(4) NOT NULL,
        `pnt_bk` varchar(255) NOT NULL,
        `pnt_pay` varchar(255) NOT NULL,
        `pnt_agree` longtext NOT NULL,
        `pnt_agree2` longtext NOT NULL,
        `pnt_point_acc_ssr_p` int(11) NOT NULL,
        `pnt_point_acc_ssr_w` int(11) NOT NULL
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8", true);

    // 기본값 삽입
    $sql = "INSERT INTO rb_point_c_set
            SET pnt_name = '예치금',
                pnt_name_st = 'C',
                pnt_acc_use = '0',
                pnt_add_use = '1' ";
    sql_query($sql);
}





if(!sql_query(" DESCRIBE rb_point_c ", false)) {
       $query_cp = sql_query("CREATE TABLE IF NOT EXISTS `rb_point_c` (
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

if(isset($pnt_c['pnt_add_use']) && $pnt_c['pnt_add_use'] == 1) {
    if(!sql_query(" DESCRIBE rb_point_c_add ", false)) {
           $query_cp = sql_query("CREATE TABLE IF NOT EXISTS `rb_point_c_add` (
            `p_id` int(11) NOT NULL AUTO_INCREMENT,
            `p_mb_id` varchar(20) NOT NULL DEFAULT '',
            `p_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            `p_y_time` datetime NOT NULL,
            `p_point` int(11) NOT NULL DEFAULT 0,
            `p_pay` varchar(100) NOT NULL DEFAULT '',
            `p_bk_name` varchar(100) NOT NULL DEFAULT '',
            `p_agree` varchar(100) NOT NULL DEFAULT '',
            `p_price` int(11) NOT NULL DEFAULT 0,
            `p_status` varchar(100) NOT NULL DEFAULT '',
            `p_use` int(4) NOT NULL,
            PRIMARY KEY (`p_id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 ", true);
    }
}

if(isset($pnt_c['pnt_acc_use']) && $pnt_c['pnt_acc_use'] == 1) {
    if(!sql_query(" DESCRIBE rb_point_c_acc ", false)) {
           $query_cp = sql_query("CREATE TABLE IF NOT EXISTS `rb_point_c_acc` (
            `p_id` int(11) NOT NULL AUTO_INCREMENT,
            `p_mb_id` varchar(20) NOT NULL DEFAULT '',
            `p_time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            `p_y_time` datetime NOT NULL,
            `p_point` int(11) NOT NULL DEFAULT 0,
            `p_ssr` int(11) NOT NULL DEFAULT 0,
            `p_price` int(11) NOT NULL DEFAULT 0,
            `p_bank` varchar(100) NOT NULL DEFAULT '',
            `p_agree` varchar(100) NOT NULL DEFAULT '',
            `p_status` varchar(100) NOT NULL DEFAULT '',
            `p_use` int(4) NOT NULL,
            PRIMARY KEY (`p_id`)
          ) ENGINE=MyISAM DEFAULT CHARSET=utf8 ", true);
    }
}

$columns_to_add = [
    'mb_bank' => 'VARCHAR(255) NOT NULL DEFAULT \'\'',
    'rb_point' => 'INT(11) NOT NULL DEFAULT \'0\'',
];

foreach ($columns_to_add as $column => $attributes) {
    // 컬럼이 있는지 확인
    $column_check = sql_query("SHOW COLUMNS FROM {$g5['member_table']} LIKE '{$column}'", false);
    if (!sql_num_rows($column_check)) {
        // 컬럼 추가
        sql_query("ALTER TABLE {$g5['member_table']} ADD {$column} {$attributes}", true);
    }
}


$sql = " select * from rb_point_c_set limit 1";
$pnt_c = sql_fetch($sql);

$pg_anchor = '<ul class="anchor">
    <li><a href="#anc_pnt1">기본설정</a></li>
    <li><a href="#anc_pnt2">'.$pnt_c_name.' 충전설정</a></li>
    <li><a href="#anc_pnt3">'.$pnt_c_name.' 출금설정</a></li>
</ul>';

$g5['title'] = $pnt_c_name.' 설정';
include_once (G5_ADMIN_PATH.'/admin.head.php');

?>


<form name="seo_form" id="seo_form" action="./point_c_set_update.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="token" value="" id="token">
    
    <section id="anc_pnt1">
        <h2 class="h2_frm">기본설정</h2>
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
                        <th scope="row">충전/출금 사용여부</th>
                        <td colspan="3">
                            <?php echo help('충전/출금 사용여부를 설정할 수 있습니다.') ?>
                            <input type="checkbox" name="pnt_add_use" value="1" id="pnt_add_use" <?php echo isset($pnt_c['pnt_add_use']) && $pnt_c['pnt_add_use'] == 1 ? 'checked' : ''; ?>> <label for="pnt_add_use"><?php echo $pnt_c_name ?> 충전 사용</label>　
                            <input type="checkbox" name="pnt_acc_use" value="1" id="pnt_acc_use" <?php echo isset($pnt_c['pnt_acc_use']) && $pnt_c['pnt_acc_use'] == 1 ? 'checked' : ''; ?>> <label for="pnt_acc_use"><?php echo $pnt_c_name ?> 출금 사용</label>　
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">명칭/단위</th>
                        <td colspan="3">
                            <?php echo help('예치금의 명칭과 단위를 설정할 수 있습니다.(입력 예 : 포인트/P)<br>값이 없는 경우 예치금/C 로 자동 설정 됩니다.') ?>
                            <input type="text" name="pnt_name" value="<?php echo isset($pnt_c['pnt_name']) ? get_sanitize_input($pnt_c['pnt_name']) : ''; ?>" id="pnt_name" class="frm_input" size="20" placeholder="명칭"> 
                            <input type="text" name="pnt_name_st" value="<?php echo isset($pnt_c['pnt_name_st']) ? get_sanitize_input($pnt_c['pnt_name_st']) : ''; ?>" id="pnt_name_st" class="frm_input" size="20" placeholder="단위">
                        </td>
                    </tr>
                    
                </tbody>
            </table>
        </div>
    </section>
    
    
    
    <section id="anc_pnt2">
        <h2 class="h2_frm"><?php echo $pnt_c_name ?> 충전설정</h2>
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
                        <th scope="row">충전신청 최소금액</th>
                        <td colspan="3">
                            <?php echo help('숫자로만 입력하세요. (충전신청시 최소금액)') ?>
                            <input type="number" name="pnt_point_add_min" value="<?php echo isset($pnt_c['pnt_point_add_min']) ? get_sanitize_input($pnt_c['pnt_point_add_min']) : ''; ?>" id="pnt_point_add_min" class="frm_input" size="20" placeholder="충전신청 최소금액"> <?php echo $pnt_c_name_st ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">충전신청 최대금액</th>
                        <td colspan="3">
                            <?php echo help('숫자로만 입력하세요. (충전신청시 최대금액)') ?>
                            <input type="number" name="pnt_point_add_max" value="<?php echo isset($pnt_c['pnt_point_add_max']) ? get_sanitize_input($pnt_c['pnt_point_add_max']) : ''; ?>" id="pnt_point_add_max" class="frm_input" size="20" placeholder="충전신청 최대금액"> <?php echo $pnt_c_name_st ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">부가세 추가 여부</th>
                        <td colspan="3">
                            <?php echo help('부가세 추가 설정시 입력되는 금액에 부가세를 추가하여 결제금액에 반영 합니다.') ?>
                            <input type="checkbox" name="pnt_vat" value="1" id="pnt_vat" <?php echo isset($pnt_c['pnt_vat']) && $pnt_c['pnt_vat'] == 1 ? 'checked' : ''; ?>> <label for="pnt_vat">부가세를 추가 합니다.</label>　
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">결제수단</th>
                        <td colspan="3">
                            <input type="radio" name="pnt_pay" value="무통장" id="pnt_pay1" <?php echo isset($pnt_c['pnt_pay']) && $pnt_c['pnt_pay'] == "무통장" ? 'checked' : ''; ?>> <label for="pnt_pay1">무통장입금</label>　
                            <input type="radio" name="pnt_pay" value="신용카드" disabled readonly id="pnt_pay2" <?php echo isset($pnt_c['pnt_pay']) && $pnt_c['pnt_pay'] == "신용카드" ? 'checked' : ''; ?>> <label for="pnt_pay2">신용카드(준비중)</label>　
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">무통장 입금계좌</th>
                        <td colspan="3">
                            <?php echo help('결제받으실 무통장 입금계좌 정보를 입력하세요.') ?>
                            <input type="text" name="pnt_bk" value="<?php echo isset($pnt_c['pnt_bk']) ? get_sanitize_input($pnt_c['pnt_bk']) : ''; ?>" id="pnt_bk" class="frm_input" size="100">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">이용약관</th>
                        <td colspan="3">
                            <?php echo help($pnt_c_name.' 충전신청 약관을 입력하세요.') ?>
                            <textarea name="pnt_agree" id="pnt_agree"><?php echo isset($pnt_c['pnt_agree']) ? get_text($pnt_c['pnt_agree']) : ''; ?></textarea>
                        </td>
                    </tr>
                    
                    
                    
                </tbody>
            </table>
        </div>
    </section>
    
    
    
    
    <section id="anc_pnt3">
        <h2 class="h2_frm"><?php echo $pnt_c_name ?> 출금설정</h2>
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
                        <th scope="row">출금신청 최소금액</th>
                        <td colspan="3">
                            <?php echo help('숫자로만 입력하세요. (출금신청시 최소금액)') ?>
                            <input type="number" name="pnt_point_acc_min" value="<?php echo isset($pnt_c['pnt_point_acc_min']) ? get_sanitize_input($pnt_c['pnt_point_acc_min']) : ''; ?>" id="pnt_point_acc_min" class="frm_input" size="20" placeholder="출금신청 최소금액"> <?php echo $pnt_c_name_st ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">출금신청 최대금액</th>
                        <td colspan="3">
                            <?php echo help('숫자로만 입력하세요. (출금신청시 최대금액)') ?>
                            <input type="number" name="pnt_point_acc_max" value="<?php echo isset($pnt_c['pnt_point_acc_max']) ? get_sanitize_input($pnt_c['pnt_point_acc_max']) : ''; ?>" id="pnt_point_acc_max" class="frm_input" size="20" placeholder="출금신청 최대금액"> <?php echo $pnt_c_name_st ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">출금 수수료 (%)</th>
                        <td colspan="3">
                            <?php echo help('0% 이상 입력시 입력되는 금액에서 수수료를 차감할 수 있습니다.<br> 퍼센트(%)보다 금액('.$pnt_c_name_st.')이 우선적용 됩니다.') ?>
                            <input type="number" name="pnt_point_acc_ssr_p" value="<?php echo isset($pnt_c['pnt_point_acc_ssr_p']) ? get_sanitize_input($pnt_c['pnt_point_acc_ssr_p']) : ''; ?>" id="pnt_point_acc_ssr_p" class="frm_input" size="20" placeholder="출금 수수료 (%)"> %
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">출금 수수료 (<?php echo $pnt_c_name_st ?>)</th>
                        <td colspan="3">
                            <?php echo help('0'.$pnt_c_name_st.' 이상 입력시 입력되는 금액에서 수수료를 차감할 수 있습니다.<br> 퍼센트(%)보다 우선적용 됩니다.') ?>
                            <input type="number" name="pnt_point_acc_ssr_w" value="<?php echo isset($pnt_c['pnt_point_acc_ssr_w']) ? get_sanitize_input($pnt_c['pnt_point_acc_ssr_w']) : ''; ?>" id="pnt_point_acc_ssr_w" class="frm_input" size="20" placeholder="출금 수수료 (<?php echo $pnt_c_name_st ?>)"> <?php echo $pnt_c_name_st ?>
                        </td>
                    </tr>

                    
                    <tr>
                        <th scope="row">이용약관</th>
                        <td colspan="3">
                            <?php echo help($pnt_c_name.' 출금신청 약관을 입력하세요.') ?>
                            <textarea name="pnt_agree2" id="pnt_agree2"><?php echo isset($pnt_c['pnt_agree2']) ? get_text($pnt_c['pnt_agree2']) : ''; ?></textarea>
                        </td>
                    </tr>
                    
                    
                    
                </tbody>
            </table>
        </div>
    </section>
    
    

    
    <div class="btn_fixed_top">
        <input type="submit" value="확인" class="btn_submit btn" accesskey="s">
    </div>
    
</form>

<?php
include_once (G5_ADMIN_PATH.'/admin.tail.php');