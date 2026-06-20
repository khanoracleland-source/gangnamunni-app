<?php
/**
 * PG 결제 테스트 페이지
 * 외부 사이트에서 PG 결제 후 결과를 확인하는 테스트 페이지
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './../_common.php';

$g5['title'] = 'PG 결제 테스트';

// 현재 도메인 및 경로 구성
$api_url = G5_URL . '/api/pg';
$base_url = G5_URL;

// 관리자 메뉴 API 키 목록
$sql = "SELECT ak_id, ak_name, ak_key, ak_type FROM {$g5['api_key_table']} WHERE ak_status = 1 AND (ak_type = 'pg' OR ak_type = 'all') ORDER BY ak_id DESC";
$api_key_result = sql_query($sql);
if (!$api_key_result) {
    $api_key_result = false;
}

// PG 사 목록
$pg_companies = array(
    'inicis' => '이니시스',
    'lg' => 'LG U+',
    'kcp' => 'KCP',
    'kakaopay' => '카카오페이',
    'nicepay' => '나이스페이'
);

// 카테고리 목록 조회
$table_prefix = defined('G5_TABLE_PREFIX') ? G5_TABLE_PREFIX : 'g5_';
$category_sql = "SELECT ca_id, ca_name FROM {$table_prefix}shop_category WHERE ca_use = 1 ORDER BY ca_order ASC, ca_id ASC";
$category_result = sql_query($category_sql);

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./keylist.php" class="ov_list">API 키 목록</a>
    <span class="btn_ov01"><span class="ov_txt">PG 결제 테스트</span></span>
</div>

<h2>PG 결제 API 테스트</h2>
<p class="local_desc">
    외부 사이트에서 PG 결제를 테스트할 수 있습니다.
</p>

<h3>1. API 키 선택</h3>
<form name="fapi_key" id="fapi_key" method="post" action="">
    <div class="tbl_frm01 tbl_wrap">
        <table>
            <caption>API 키 선택</caption>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="api_key">API 키</label></th>
                    <td>
                        <select name="api_key" id="api_key" class="frm_input" style="width:50%;">
                            <option value="">API 키를 선택해주세요</option>
                            <?php
                            if ($api_key_result && sql_num_rows($api_key_result) > 0) {
                                while ($row = sql_fetch_array($api_key_result)) {
                                    ?>
                                    <option value="<?php echo htmlspecialchars($row['ak_key']); ?>" data-secret="<?php echo htmlspecialchars($row['ak_key']); ?>">
                                        <?php echo htmlspecialchars($row['ak_name']); ?> (<?php echo strtoupper(htmlspecialchars($row['ak_type'])); ?>)
                                    </option>
                                    <?php
                                }
                            }
                            ?>
                        </select>
                        <span class="frm_info">PG 결제에 사용할 API 키를 선택하세요.</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="api_secret">API 시크릿</label></th>
                    <td>
                        <input type="text" name="api_secret" id="api_secret" class="frm_input" size="50" readonly>
                        <span class="frm_info">API 시크릿은 관리자 페이지에서 확인하실 수 있습니다.</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</form>

<h3>2. 상품 조회</h3>
<form name="fitem" id="fitem" method="post" action="">
    <div class="tbl_frm01 tbl_wrap">
        <table>
            <caption>상품 조회</caption>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="category">카테고리</label></th>
                    <td>
                        <select name="ca_id" id="category" class="frm_input">
                            <option value="">전체</option>
                            <?php
                            if ($category_result && sql_num_rows($category_result) > 0) {
                                while ($row = sql_fetch_array($category_result)) {
                                    ?>
                                    <option value="<?php echo $row['ca_id']; ?>"><?php echo htmlspecialchars($row['ca_name']); ?></option>
                                    <?php
                                }
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="btn_fixed_top">
        <input type="button" value="상품 목록 조회" class="btn_submit btn" onclick="getItemList();">
    </div>
</form>

<div id="item_list_result" style="display:none; margin-top:20px;">
    <h4>상품 목록</h4>
    <div class="tbl_wrap tbl_wrap01">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th style="width:10%;">선택</th>
                    <th style="width:10%;">상품 ID</th>
                    <th style="width:30%;">상품명</th>
                    <th style="width:10%;">가격</th>
                    <th style="width:10%;">재고</th>
                    <th style="width:20%;">등록일</th>
                </tr>
            </thead>
            <tbody id="item_list_tbody">
            </tbody>
        </table>
    </div>
</div>

<h3>3. 장바구니에 추가</h3>
<form name="fcart" id="fcart" method="post" action="">
    <div class="tbl_frm01 tbl_wrap">
        <table>
            <caption>장바구니에 추가</caption>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="cart_item_id">상품</label></th>
                    <td>
                        <select name="item_id" id="cart_item_id" class="frm_input">
                            <option value="">상품을 선택해주세요</option>
                        </select>
                        <span class="frm_info">상품 목록에서 상품을 먼저 조회해주세요.</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cart_quantity">수량</label></th>
                    <td>
                        <input type="number" name="quantity" id="cart_quantity" class="frm_input" size="10" value="1" min="1">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="cart_id">장바구니 ID</label></th>
                    <td>
                        <input type="text" name="cart_id" id="cart_id" class="frm_input" size="30" value="test_cart_<?php echo time(); ?>">
                        <span class="frm_info">장바구니 ID (테스트용)</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="btn_fixed_top">
        <input type="button" value="장바구니에 추가" class="btn_submit btn" onclick="addToCart();">
    </div>
</form>

<h3>4. 주문 생성</h3>
<form name="forder" id="forder" method="post" action="">
    <div class="tbl_frm01 tbl_wrap">
        <table>
            <caption>주문 생성</caption>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="order_cart_id">장바구니 ID</label></th>
                    <td>
                        <input type="text" name="cart_id" id="order_cart_id" class="frm_input" size="30">
                        <span class="frm_info">주문할 장바구니 ID</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="external_order_id">외부 주문 ID</label></th>
                    <td>
                        <input type="text" name="external_order_id" id="external_order_id" class="frm_input" size="30" value="test_order_<?php echo time(); ?>">
                        <span class="frm_info">외부 사이트의 주문 ID (유일해야 함)</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="receiver_name">수신자명</label></th>
                    <td>
                        <input type="text" name="receiver_name" id="receiver_name" class="frm_input" size="30" value="홍길동">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="receiver_phone">연락처</label></th>
                    <td>
                        <input type="text" name="receiver_phone" id="receiver_phone" class="frm_input" size="20" value="01012345678">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="receiver_zipcode">우편번호</label></th>
                    <td>
                        <input type="text" name="receiver_zipcode" id="receiver_zipcode" class="frm_input" size="10" value="12345">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="receiver_address1">기본 주소</label></th>
                    <td>
                        <input type="text" name="receiver_address1" id="receiver_address1" class="frm_input" size="50" value="서울시 강남구 테헤란로">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="receiver_address2">상세 주소</label></th>
                    <td>
                        <input type="text" name="receiver_address2" id="receiver_address2" class="frm_input" size="50" value="123동 456호">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="receiver_address3">나머지 주소</label></th>
                    <td>
                        <input type="text" name="receiver_address3" id="receiver_address3" class="frm_input" size="50">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="payment_method">결제 방법</label></th>
                    <td>
                        <select name="payment_method" id="payment_method" class="frm_input">
                            <?php
                            foreach ($pg_companies as $code => $name) {
                                ?>
                                <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="callback_url">콜백 URL</label></th>
                    <td>
                        <input type="text" name="callback_url" id="callback_url" class="frm_input" size="80" value="<?php echo $base_url; ?>/api/pg/test_webhook.php">
                        <span class="frm_info">결제 완료 후 콜백을 받을 URL</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="btn_fixed_top">
        <input type="submit" value="주문 생성" class="btn_submit btn" onclick="createOrder(); return false;">
    </div>
</form>

<h3>5. 결제 결과</h3>
<div id="order_result" style="display:none;">
    <div class="tbl_wrap tbl_wrap01">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th style="width:30%;">항목</th>
                    <th>내용</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th>상태</th>
                    <td id="result_status"></td>
                </tr>
                <tr>
                    <th>주문 ID</th>
                    <td id="result_order_id"></td>
                </tr>
                <tr>
                    <th>외부 주문 ID</th>
                    <td id="result_external_order_id"></td>
                </tr>
                <tr>
                    <th>상품명</th>
                    <td id="result_item_name"></td>
                </tr>
                <tr>
                    <th>결제 금액</th>
                    <td id="result_amount"></td>
                </tr>
                <tr>
                    <th>PG 사</th>
                    <td id="result_pg_code"></td>
                </tr>
                <tr>
                    <th>PG TID</th>
                    <td id="result_pg_tid"></td>
                </tr>
                <tr>
                    <th>주문 시간</th>
                    <td id="result_order_time"></td>
                </tr>
                <tr>
                    <th>전체 응답</th>
                    <td>
                        <pre id="result_full_response" style="background:#f5f5f5; padding:10px; border:1px solid #ddd; max-height:300px; overflow:auto;"></pre>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<h3>6. 외부 사이트에서 테스트</h3>
<div class="local_desc02 local_desc">
    <p>외부 사이트에서 직접 PG 결제를 테스트하려면 다음 페이지를 사용하세요:</p>
    <p>
        <a href="<?php echo $base_url; ?>/api/pg/test_payment.php" target="_blank" class="btn btn_01">외부 사이트 결제 테스트 &raquo;</a>
    </p>
    <p style="margin-top:10px;">
        <small>※ 새 창에서 외부 사이트 결제 테스트 페이지가 열립니다.</small>
    </p>
</div>

<script>
// API 키 선택 시 시크릿 자동 입력
document.getElementById('api_key').addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    var secret = selectedOption.getAttribute('data-secret');
    document.getElementById('api_secret').value = secret || '';
});

// 상품 목록 조회
function getItemList() {
    var apiKey = document.getElementById('api_key').value;
    var apiSecret = document.getElementById('api_secret').value;
    var categoryId = document.getElementById('category').value;
    
    if (!apiKey) {
        alert('API 키를 선택해주세요.');
        return false;
    }
    
    if (!apiSecret) {
        alert('API 시크릿을 입력해주세요.');
        return false;
    }
    
    var params = 'action=list&page=1&limit=20';
    if (categoryId) {
        params += '&ca_id=' + encodeURIComponent(categoryId);
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '<?php echo $api_url; ?>/item.php?' + params, true);
    xhr.setRequestHeader('X-API-Key', apiKey);
    xhr.setRequestHeader('X-API-Secret', apiSecret);
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            displayItemList(response);
        } else {
            alert('요청 실패: ' + xhr.status);
        }
    };
    
    xhr.onerror = function() {
        alert('서버 오류가 발생했습니다.');
    };
    
    xhr.send();
}

// 상품 목록 표시
function displayItemList(response) {
    var resultDiv = document.getElementById('item_list_result');
    var tbody = document.getElementById('item_list_tbody');
    
    if (response.success && response.data.items) {
        resultDiv.style.display = 'block';
        tbody.innerHTML = '';
        
        var itemSelect = document.getElementById('cart_item_id');
        itemSelect.innerHTML = '<option value="">상품을 선택해주세요</option>';
        
        response.data.items.forEach(function(item) {
            var tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="radio" name="select_item" value="${item.item_id}" onclick="selectItem('${item.item_id}', '${item.name}', ${item.price})"></td>
                <td>${item.item_id}</td>
                <td>${item.name}</td>
                <td>${item.amount.toLocaleString()}원</td>
                <td>${item.stock_quantity}개</td>
                <td>${item.created_at}</td>
            `;
            tbody.appendChild(tr);
            
            var option = document.createElement('option');
            option.value = item.item_id;
            option.textContent = item.name + ' (' + item.amount.toLocaleString() + '원)';
            itemSelect.appendChild(option);
        });
    } else {
        alert('상품 목록을 가져오는데 실패했습니다.');
    }
}

// 상품 선택
function selectItem(itemId, itemName, price) {
    document.getElementById('cart_item_id').value = itemId;
    document.getElementById('order_item_id').value = itemId;
    document.getElementById('order_item_name').value = itemName;
    document.getElementById('order_item_price').value = price;
}

// 장바구니에 추가
function addToCart() {
    var apiKey = document.getElementById('api_key').value;
    var apiSecret = document.getElementById('api_secret').value;
    var itemId = document.getElementById('cart_item_id').value;
    var quantity = document.getElementById('cart_quantity').value;
    var cartId = document.getElementById('cart_id').value;
    
    if (!apiKey) {
        alert('API 키를 선택해주세요.');
        return false;
    }
    
    if (!apiSecret) {
        alert('API 시크릿을 입력해주세요.');
        return false;
    }
    
    if (!itemId) {
        alert('상품을 선택해주세요.');
        return false;
    }
    
    if (!cartId) {
        alert('장바구니 ID를 입력해주세요.');
        return false;
    }
    
    var formData = new FormData();
    formData.append('item_id', itemId);
    formData.append('quantity', quantity);
    formData.append('cart_id', cartId);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo $api_url; ?>/cart.php', true);
    xhr.setRequestHeader('X-API-Key', apiKey);
    xhr.setRequestHeader('X-API-Secret', apiSecret);
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                alert('장바구니에 추가되었습니다.\\n장바구니 ID: ' + response.data.cart_id);
            } else {
                alert('장바구니 추가 실패: ' + (response.error ? response.error.message : '알 수 없는 오류'));
            }
        } else {
            alert('요청 실패: ' + xhr.status);
        }
    };
    
    xhr.onerror = function() {
        alert('서버 오류가 발생했습니다.');
    };
    
    xhr.send(formData);
}

// 주문 생성
function createOrder() {
    var apiKey = document.getElementById('api_key').value;
    var apiSecret = document.getElementById('api_secret').value;
    var cartId = document.getElementById('order_cart_id').value;
    var externalOrderId = document.getElementById('external_order_id').value;
    var receiverName = document.getElementById('receiver_name').value;
    var receiverPhone = document.getElementById('receiver_phone').value;
    var receiverZipcode = document.getElementById('receiver_zipcode').value;
    var receiverAddress1 = document.getElementById('receiver_address1').value;
    var receiverAddress2 = document.getElementById('receiver_address2').value;
    var receiverAddress3 = document.getElementById('receiver_address3').value;
    var paymentMethod = document.getElementById('payment_method').value;
    var callbackUrl = document.getElementById('callback_url').value;
    
    if (!apiKey) {
        alert('API 키를 선택해주세요.');
        return false;
    }
    
    if (!apiSecret) {
        alert('API 시크릿을 입력해주세요.');
        return false;
    }
    
    if (!cartId) {
        alert('장바구니 ID를 입력해주세요.');
        return false;
    }
    
    if (!externalOrderId) {
        alert('외부 주문 ID를 입력해주세요.');
        return false;
    }
    
    if (!receiverName) {
        alert('수신자명을 입력해주세요.');
        return false;
    }
    
    if (!receiverPhone) {
        alert('연락처를 입력해주세요.');
        return false;
    }
    
    if (!receiverZipcode) {
        alert('우편번호를 입력해주세요.');
        return false;
    }
    
    if (!receiverAddress1) {
        alert('기본 주소를 입력해주세요.');
        return false;
    }
    
    if (!paymentMethod) {
        alert('결제 방법을 선택해주세요.');
        return false;
    }
    
    var formData = new FormData();
    formData.append('external_order_id', externalOrderId);
    formData.append('cart_id', cartId);
    formData.append('receiver_name', receiverName);
    formData.append('receiver_phone', receiverPhone);
    formData.append('receiver_zipcode', receiverZipcode);
    formData.append('receiver_address1', receiverAddress1);
    formData.append('receiver_address2', receiverAddress2);
    formData.append('receiver_address3', receiverAddress3);
    formData.append('payment_method', paymentMethod);
    formData.append('callback_url', callbackUrl);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo $api_url; ?>/order.php', true);
    xhr.setRequestHeader('X-API-Key', apiKey);
    xhr.setRequestHeader('X-API-Secret', apiSecret);
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            displayOrderResult(response);
        } else {
            alert('요청 실패: ' + xhr.status);
        }
    };
    
    xhr.onerror = function() {
        alert('서버 오류가 발생했습니다.');
    };
    
    xhr.send(formData);
    
    return false;
}

// 주문 결과 표시
function displayOrderResult(response) {
    var resultDiv = document.getElementById('order_result');
    resultDiv.style.display = 'block';
    
    if (response.success) {
        document.getElementById('result_status').innerHTML = '<span style="color:orange;">주문 생성 완료 (결제 대기)</span>';
        document.getElementById('result_order_id').textContent = response.data.order_id || '-';
        document.getElementById('result_external_order_id').textContent = response.data.external_order_id || '-';
        document.getElementById('result_item_name').textContent = response.data.payment_data ? response.data.payment_data.item_name : '-';
        document.getElementById('result_amount').textContent = (response.data.amount || 0).toLocaleString() + '원';
        document.getElementById('result_pg_code').textContent = response.data.payment_data ? response.data.payment_data.pg_code.toUpperCase() : '-';
        document.getElementById('result_pg_tid').textContent = '-';
        document.getElementById('result_order_time').textContent = response.data.created_at || '-';
        
        // 주문 생성 후 결제 요청
        if (confirm('주문이 생성되었습니다. 결제를 진행하시겠습니까?')) {
            requestPayment(response.data.order_id, response.data.external_order_id);
        }
    } else {
        document.getElementById('result_status').innerHTML = '<span style="color:red;">주문 생성 실패</span>';
        document.getElementById('result_order_id').textContent = '-';
        document.getElementById('result_external_order_id').textContent = '-';
        document.getElementById('result_item_name').textContent = '-';
        document.getElementById('result_amount').textContent = '-';
        document.getElementById('result_pg_code').textContent = '-';
        document.getElementById('result_pg_tid').textContent = '-';
        document.getElementById('result_order_time').textContent = '-';
    }
    
    document.getElementById('result_full_response').textContent = JSON.stringify(response, null, 2);
}

// 결제 요청
function requestPayment(orderId, externalOrderId) {
    var apiKey = document.getElementById('api_key').value;
    var apiSecret = document.getElementById('api_secret').value;
    
    var formData = new FormData();
    formData.append('order_id', orderId);
    formData.append('external_order_id', externalOrderId);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo $api_url; ?>/payment.php', true);
    xhr.setRequestHeader('X-API-Key', apiKey);
    xhr.setRequestHeader('X-API-Secret', apiSecret);
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            displayPaymentResult(response);
        } else {
            alert('요청 실패: ' + xhr.status);
        }
    };
    
    xhr.onerror = function() {
        alert('서버 오류가 발생했습니다.');
    };
    
    xhr.send(formData);
}

// 결제 결과 표시
function displayPaymentResult(response) {
    if (response.success) {
        document.getElementById('result_status').innerHTML = '<span style="color:green;">결제 완료</span>';
        document.getElementById('result_pg_tid').textContent = response.data.tid || '-';
        document.getElementById('result_order_time').textContent = '-';
    } else {
        document.getElementById('result_status').innerHTML = '<span style="color:red;">결제 실패</span>';
        document.getElementById('result_pg_tid').textContent = '-';
    }
    
    document.getElementById('result_full_response').textContent = JSON.stringify(response, null, 2);
}
</script>

<?php
include_once G5_ADMIN_PATH . '/admin.tail.php';
?>
