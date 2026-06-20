<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

add_replace('admin_menu', 'add_admin_bbs_menu_partner', 0, 1); // 관리자 메뉴를 추가함

function add_admin_bbs_menu_partner($admin_menu){ // 메뉴추가
    
    $admin_menu['menu000'][] = array('000650', '입점 설정', G5_ADMIN_URL.'/rb/partner_form.php', 'rb_config');
    $admin_menu['menu000'][] = array('000651', '입점사 관리', G5_ADMIN_URL.'/rb/partner_list.php', 'rb_config');
    $admin_menu['menu000'][] = array('000652', '입점사 상품관리', G5_ADMIN_URL.'/rb/partner_item_list.php', 'rb_config');
    $admin_menu['menu000'][] = array('000653', '입점사 정산관리', G5_ADMIN_URL.'/rb/partner_js_list.php', 'rb_config');
    
    $admin_menu['menu000'][] = array('000000', '　', G5_ADMIN_URL, 'rb_config');
    return $admin_menu;
}

$pa = sql_fetch (" select * from rb_partner "); // 테이블 조회
add_stylesheet('<link rel="stylesheet" href="'.G5_THEME_URL.'/rb.css/partner.css?ver='.G5_TIME_YMDHIS.'" />', 0);

// 배송비 구함
function get_sendcost_rb($cart_id, $partner_id, $selected=1)
{
    global $default, $g5;

    $send_cost = 0;
    $total_price = 0;
    $total_send_cost = 0;
    $diff = 0;

    $sql = " select distinct it_id
                from {$g5['g5_shop_cart_table']}
                where od_id = '$cart_id'
                  and ct_send_cost = '0'
                  and ct_status IN ( '쇼핑', '주문', '입금', '준비', '배송', '완료' )
                  and ct_partner = '{$partner_id}' 
                  and ct_select = '$selected' ";

    $result = sql_query($sql);
    for($i=0; $sc=sql_fetch_array($result); $i++) {
        // 합계
        $sql = " select SUM(IF(io_type = 1, (io_price * ct_qty), ((ct_price + io_price) * ct_qty))) as price,
                        SUM(ct_qty) as qty
                    from {$g5['g5_shop_cart_table']}
                    where it_id = '{$sc['it_id']}'
                      and od_id = '$cart_id'
                      and ct_status IN ( '쇼핑', '주문', '입금', '준비', '배송', '완료' )
                      and ct_partner = '{$partner_id}' 
                      and ct_select = '$selected'";
        $sum = sql_fetch($sql);

        $send_cost = get_item_sendcost($sc['it_id'], $sum['price'], $sum['qty'], $cart_id);

        if($send_cost > 0)
            $total_send_cost += $send_cost;

        if($default['de_send_cost_case'] == '차등' && $send_cost == -1) {
            $total_price += $sum['price'];
            $diff++;
        }
    }

    $send_cost = 0;
    if($default['de_send_cost_case'] == '차등' && $total_price >= 0 && $diff > 0) {
        // 금액별차등 : 여러단계의 배송비 적용 가능
        $send_cost_limit = explode(";", $default['de_send_cost_limit']);
        $send_cost_list  = explode(";", $default['de_send_cost_list']);
        $send_cost = 0;
        for ($k=0; $k<count($send_cost_limit); $k++) {
            // 총판매금액이 배송비 상한가 보다 작다면
            if ($total_price < preg_replace('/[^0-9]/', '', $send_cost_limit[$k])) {
                $send_cost = preg_replace('/[^0-9]/', '', $send_cost_list[$k]);
                break;
            }
        }
    }

    return ($total_send_cost + $send_cost);
}