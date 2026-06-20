<?php
/**
 * PG 결제 테스트 페이지 (외부 사이트용)
 * 외부 사이트에서 직접 PG 결제를 테스트하는 페이지
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 공통 파일 포함
include_once dirname(__FILE__).'/../common.php';

// 데이터베이스 테이블 확인
if (!isset($g5['api_key_table'])) {
    $g5['api_key_table'] = G5_TABLE_PREFIX.'api_keys';
}

if (!isset($g5['shop_item_table'])) {
    $g5['shop_item_table'] = G5_TABLE_PREFIX.'shop_item';
}

if (!isset($g5['shop_category_table'])) {
    $g5['shop_category_table'] = G5_TABLE_PREFIX.'shop_category';
}

// 현재 도메인 및 경로 구성
$api_url = G5_URL . '/api/pg';
$base_url = G5_URL;

// 관리자 메뉴 API 키 목록
$sql = "SELECT ak_id, ak_name, ak_key, ak_type FROM {$g5['api_key_table']} WHERE ak_status = 1 AND (ak_type = 'pg' OR ak_type = 'all') ORDER BY ak_id DESC";
$api_key_result = sql_query($sql);

// PG 사 목록
$pg_companies = array(
    'inicis' => '이니시스',
    'lg' => 'LG U+',
    'kcp' => 'KCP',
    'kakaopay' => '카카오페이',
    'nicepay' => '나이스페이'
);

// 카테고리 목록 조회
$category_sql = "SELECT ca_id, ca_name FROM {$g5['shop_category_table']} WHERE ca_use = 1 ORDER BY ca_order ASC, ca_id ASC";
$category_result = sql_query($category_sql);
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PG 결제 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 30px; text-align: center; }
        h2 { color: #555; margin-top: 30px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
        h3 { color: #666; margin-top: 20px; margin-bottom: 15px; }
        h4 { color: #777; margin-top: 20px; margin-bottom: 10px; }
        .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-group input[type="checkbox"] { width: auto; margin-right: 5px; }
        .btn-group { margin-top: 20px; }
        .btn { padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .btn-primary { background: #2196f3; color: white; }
        .btn-primary:hover { background: #1976d2; }
        .btn-success { background: #4caf50; color: white; }
        .btn-success:hover { background: #45a049; }
        .btn-warning { background: #ff9800; color: white; }
        .btn-warning:hover { background: #fb8c00; }
        .btn-danger { background: #f44336; color: white; }
        .btn-danger:hover { background: #da190b; }
        .step { background: #f9f9f9; padding: 20px; margin-bottom: 20px; border-radius: 4px; border-left: 4px solid #2196f3; }
        .step h3 { margin-top: 0; }
        .result-box { display: none; margin-top: 20px; background: #f9f9f9; padding: 20px; border-radius: 4px; border: 1px solid #ddd; }
        .result-box.success { border-left: 4px solid #4caf50; background: #f1f8e9; }
        .result-box.error { border-left: 4px solid #f44336; background: #ffebee; }
        .result-box h3 { margin-top: 0; }
        .result-item { margin-bottom: 10px; padding: 10px; background: white; border-radius: 4px; }
        .result-item label { font-weight: bold; display: inline-block; width: 150px; }
        .result-item .value { display: inline-block; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th, table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        table th { background: #f5f5f5; font-weight: bold; }
        .success { color: #4caf50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .warning { color: #ff9800; font-weight: bold; }
        .item-list { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; margin-top: 10px; }
        .item-list-item { padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; }
        .item-list-item:hover { background: #f9f9f9; }
        .item-list-item.selected { background: #e3f2fd; }
        .progress { width: 100%; height: 20px; background: #e0e0e0; border-radius: 10px; overflow: hidden; margin: 20px 0; }
        .progress-bar { height: 100%; background: #2196f3; width: 0%; transition: width 0.3s; }
        .step-number { display: inline-block; width: 30px; height: 30px; background: #2196f3; color: white; border-radius: 50%; text-align: center; line-height: 30px; font-weight: bold; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PG 결제 테스트</h1>
        
        <div class="info-box">
            <strong>API 정보:</strong><br>
            API URL: <code><?php echo $api_url; ?>/item.php</code><br>
            테스트 페이지 URL: <code><?php echo $base_url; ?>/api/pg/test_payment.php</code>
        </div>

        <div class="step">
            <h3><span class="step-number">1</span> API 인증 정보</h3>
            <form id="authForm">
                <div class="form-group">
                    <label for="apiKey">API 키</label>
                    <select id="apiKey" name="apiKey" required>
                        <option value="">API 키를 선택해주세요</option>
                        <?php
                        while ($row = sql_fetch_array($api_key_result)) {
                            ?>
                            <option value="<?php echo htmlspecialchars($row['ak_key']); ?>" data-secret="<?php echo htmlspecialchars($row['ak_key']); ?>">
                                <?php echo htmlspecialchars($row['ak_name']); ?> (<?php echo strtoupper(htmlspecialchars($row['ak_type'])); ?>)
                            </option>
                            <?php
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="apiSecret">API 시크릿</label>
                    <input type="text" id="apiSecret" name="apiSecret" readonly>
                </div>
            </form>
        </div>

        <div class="step">
            <h3><span class="step-number">2</span> 상품 조회</h3>
            <form id="itemForm">
                <div class="form-group">
                    <label for="category">카테고리</label>
                    <select id="category" name="category">
                        <option value="">전체</option>
                        <?php
                        while ($row = sql_fetch_array($category_result)) {
                            ?>
                            <option value="<?php echo $row['ca_id']; ?>"><?php echo htmlspecialchars($row['ca_name']); ?></option>
                            <?php
                        }
                        ?>
                    </select>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" onclick="getItemList()">상품 목록 조회</button>
                </div>
            </form>
            
            <div id="itemListResult" style="display:none; margin-top:20px;">
                <h4>상품 목록</h4>
                <div class="item-list" id="itemList"></div>
            </div>
        </div>

        <div class="step">
            <h3><span class="step-number">3</span> 장바구니에 추가</h3>
            <form id="cartForm">
                <div class="form-group">
                    <label for="cartItemId">상품</label>
                    <input type="text" id="cartItemId" name="itemId" readonly placeholder="상품 목록에서 상품을 선택해주세요">
                </div>
                <div class="form-group">
                    <label for="cartItemName">상품명</label>
                    <input type="text" id="cartItemName" name="itemName" readonly>
                </div>
                <div class="form-group">
                    <label for="cartItemPrice">가격</label>
                    <input type="text" id="cartItemPrice" name="itemPrice" readonly>
                </div>
                <div class="form-group">
                    <label for="cartQuantity">수량</label>
                    <input type="number" id="cartQuantity" name="quantity" value="1" min="1">
                </div>
                <div class="form-group">
                    <label for="cartOption1">옵션1</label>
                    <input type="text" id="cartOption1" name="option1">
                </div>
                <div class="form-group">
                    <label for="cartOption2">옵션2</label>
                    <input type="text" id="cartOption2" name="option2">
                </div>
                <div class="form-group">
                    <label for="cartId">장바구니 ID</label>
                    <input type="text" id="cartId" name="cartId" value="test_cart_<?php echo time(); ?>">
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-success" onclick="addToCart()">장바구니에 추가</button>
                </div>
            </form>
        </div>

        <div class="step">
            <h3><span class="step-number">4</span> 주문 생성</h3>
            <form id="orderForm">
                <div class="form-group">
                    <label for="orderCartId">장바구니 ID</label>
                    <input type="text" id="orderCartId" name="cartId" readonly>
                </div>
                <div class="form-group">
                    <label for="externalOrderId">외부 주문 ID</label>
                    <input type="text" id="externalOrderId" name="externalOrderId" value="test_order_<?php echo time(); ?>">
                </div>
                <div class="form-group">
                    <label for="receiverName">수신자명</label>
                    <input type="text" id="receiverName" name="receiverName" value="홍길동">
                </div>
                <div class="form-group">
                    <label for="receiverPhone">연락처</label>
                    <input type="text" id="receiverPhone" name="receiverPhone" value="01012345678">
                </div>
                <div class="form-group">
                    <label for="receiverZipcode">우편번호</label>
                    <input type="text" id="receiverZipcode" name="receiverZipcode" value="12345">
                </div>
                <div class="form-group">
                    <label for="receiverAddress1">기본 주소</label>
                    <input type="text" id="receiverAddress1" name="receiverAddress1" value="서울시 강남구 테헤란로">
                </div>
                <div class="form-group">
                    <label for="receiverAddress2">상세 주소</label>
                    <input type="text" id="receiverAddress2" name="receiverAddress2" value="123동 456호">
                </div>
                <div class="form-group">
                    <label for="receiverAddress3">나머지 주소</label>
                    <input type="text" id="receiverAddress3" name="receiverAddress3">
                </div>
                <div class="form-group">
                    <label for="paymentMethod">결제 방법</label>
                    <select id="paymentMethod" name="paymentMethod">
                        <?php
                        foreach ($pg_companies as $code => $name) {
                            ?>
                            <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                            <?php
                        }
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="callbackUrl">콜백 URL</label>
                    <input type="text" id="callbackUrl" name="callbackUrl" value="<?php echo $base_url; ?>/api/pg/test_webhook.php">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="autoPayment" name="autoPayment" value="1" checked>
                        주문 생성 후 자동 결제
                    </label>
                </div>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" onclick="createOrder()">주문 생성</button>
                </div>
            </form>
        </div>

        <div class="step">
            <h3><span class="step-number">5</span> 결제</h3>
            <div id="paymentSection" style="display:none;">
                <form id="paymentForm">
                    <div class="form-group">
                        <label for="paymentOrderId">주문 ID</label>
                        <input type="text" id="paymentOrderId" name="orderId" readonly>
                    </div>
                    <div class="form-group">
                        <label for="paymentExternalOrderId">외부 주문 ID</label>
                        <input type="text" id="paymentExternalOrderId" name="externalOrderId" readonly>
                    </div>
                    <div class="btn-group">
                        <button type="button" class="btn btn-warning" onclick="requestPayment()">결제 요청</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="progress" id="progressBar">
            <div class="progress-bar" id="progress"></div>
        </div>

        <div id="resultSection">
            <h2>테스트 결과</h2>
            <div id="resultContent"></div>
        </div>
    </div>

    <script>
        // API 키 선택 시 시크릿 자동 입력
        document.getElementById('apiKey').addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var secret = selectedOption.getAttribute('data-secret');
            document.getElementById('apiSecret').value = secret || '';
        });

        // 상품 목록 조회
        function getItemList() {
            var apiKey = document.getElementById('apiKey').value;
            var apiSecret = document.getElementById('apiSecret').value;
            var category = document.getElementById('category').value;
            
            if (!apiKey) {
                alert('API 키를 선택해주세요.');
                return false;
            }
            
            if (!apiSecret) {
                alert('API 시크릿을 입력해주세요.');
                return false;
            }
            
            var params = 'action=list&page=1&limit=20';
            if (category) {
                params += '&ca_id=' + encodeURIComponent(category);
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
            updateProgress(20);
        }

        // 상품 목록 표시
        function displayItemList(response) {
            var resultDiv = document.getElementById('itemListResult');
            var itemList = document.getElementById('itemList');
            
            if (response.success && response.data.items) {
                resultDiv.style.display = 'block';
                itemList.innerHTML = '';
                
                response.data.items.forEach(function(item) {
                    var itemDiv = document.createElement('div');
                    itemDiv.className = 'item-list-item';
                    itemDiv.innerHTML = `
                        <strong>${item.name}</strong><br>
                        <small>ID: ${item.item_id} | 가격: ${item.amount.toLocaleString()}원 | 재고: ${item.stock_quantity}개</small>
                    `;
                    itemDiv.addEventListener('click', function() {
                        selectItem(item.item_id, item.name, item.amount);
                        document.querySelectorAll('.item-list-item').forEach(function(el) {
                            el.classList.remove('selected');
                        });
                        this.classList.add('selected');
                    });
                    itemList.appendChild(itemDiv);
                });
            } else {
                alert('상품 목록을 가져오는데 실패했습니다.');
            }
            
            updateProgress(30);
        }

        // 상품 선택
        function selectItem(itemId, itemName, price) {
            document.getElementById('cartItemId').value = itemId;
            document.getElementById('cartItemName').value = itemName;
            document.getElementById('cartItemPrice').value = price.toLocaleString() + '원';
            document.getElementById('orderItemName').value = itemName;
            document.getElementById('orderItemPrice').value = price;
        }

        // 장바구니에 추가
        function addToCart() {
            var apiKey = document.getElementById('apiKey').value;
            var apiSecret = document.getElementById('apiSecret').value;
            var itemId = document.getElementById('cartItemId').value;
            var quantity = document.getElementById('cartQuantity').value;
            var option1 = document.getElementById('cartOption1').value;
            var option2 = document.getElementById('cartOption2').value;
            var cartId = document.getElementById('cartId').value;
            
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
            
            var formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('quantity', quantity);
            if (option1) formData.append('option1', option1);
            if (option2) formData.append('option2', option2);
            formData.append('cart_id', cartId);
            
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo $api_url; ?>/cart.php', true);
            xhr.setRequestHeader('X-API-Key', apiKey);
            xhr.setRequestHeader('X-API-Secret', apiSecret);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert('장바구니에 추가되었습니다.\n장바구니 ID: ' + response.data.cart_id);
                        document.getElementById('orderCartId').value = response.data.cart_id;
                        addResult('장바구니 추가', response);
                        updateProgress(40);
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
            var apiKey = document.getElementById('apiKey').value;
            var apiSecret = document.getElementById('apiSecret').value;
            var cartId = document.getElementById('orderCartId').value;
            var externalOrderId = document.getElementById('externalOrderId').value;
            var receiverName = document.getElementById('receiverName').value;
            var receiverPhone = document.getElementById('receiverPhone').value;
            var receiverZipcode = document.getElementById('receiverZipcode').value;
            var receiverAddress1 = document.getElementById('receiverAddress1').value;
            var receiverAddress2 = document.getElementById('receiverAddress2').value;
            var receiverAddress3 = document.getElementById('receiverAddress3').value;
            var paymentMethod = document.getElementById('paymentMethod').value;
            var callbackUrl = document.getElementById('callbackUrl').value;
            var autoPayment = document.getElementById('autoPayment').checked;
            
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
                alert('연�처를 입력해주세요.');
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
            
            if (!callbackUrl) {
                alert('콜백 URL을 입력해주세요.');
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
            if (receiverAddress3) formData.append('receiver_address3', receiverAddress3);
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
                    if (autoPayment && response.success) {
                        requestPayment(response.data.order_id, response.data.external_order_id);
                    }
                    updateProgress(60);
                } else {
                    alert('요청 실패: ' + xhr.status);
                }
            };
            
            xhr.onerror = function() {
                alert('서버 오류가 발생했습니다.');
            };
            
            xhr.send(formData);
        }

        // 주문 결과 표시
        function displayOrderResult(response) {
            addResult('주문 생성', response);
            
            if (response.success) {
                document.getElementById('paymentSection').style.display = 'block';
                document.getElementById('paymentOrderId').value = response.data.order_id;
                document.getElementById('paymentExternalOrderId').value = response.data.external_order_id;
            }
        }

        // 결제 요청
        function requestPayment(orderId, externalOrderId) {
            var apiKey = document.getElementById('apiKey').value;
            var apiSecret = document.getElementById('apiSecret').value;
            
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
                    updateProgress(80);
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
            addResult('결제', response);
            
            if (response.success) {
                updateProgress(100);
            }
        }

        // 결과 추가
        function addResult(title, response) {
            var resultSection = document.getElementById('resultContent');
            var resultBox = document.createElement('div');
            resultBox.className = 'result-box';
            
            if (response.success) {
                resultBox.classList.add('success');
            } else {
                resultBox.classList.add('error');
            }
            
            var statusHtml = response.success ? 
                '<span class="success">성공</span>' : 
                '<span class="error">실패</span>';
            
            var contentHtml = '';
            
            if (title === '장바구니 추가') {
                contentHtml = `
                    <div class="result-item">
                        <label>상태:</label>
                        <span class="value">${statusHtml}</span>
                    </div>
                    <div class="result-item">
                        <label>장바구니 ID:</label>
                        <span class="value">${response.data.cart_id || '-'}</span>
                    </div>
                `;
            } else if (title === '주문 생성') {
                contentHtml = `
                    <div class="result-item">
                        <label>상태:</label>
                        <span class="value">${statusHtml}</span>
                    </div>
                    <div class="result-item">
                        <label>주문 ID:</label>
                        <span class="value">${response.data.order_id || '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>외부 주문 ID:</label>
                        <span class="value">${response.data.external_order_id || '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>상품명:</label>
                        <span class="value">${response.data.payment_data ? response.data.payment_data.item_name : '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>결제 금액:</label>
                        <span class="value">${(response.data.amount || 0).toLocaleString()}원</span>
                    </div>
                    <div class="result-item">
                        <label>결제 방법:</label>
                        <span class="value">${response.data.payment_data ? response.data.payment_data.pg_code.toUpperCase() : '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>생성일:</label>
                        <span class="value">${response.data.created_at || '-'}</span>
                    </div>
                `;
            } else if (title === '결제') {
                contentHtml = `
                    <div class="result-item">
                        <label>상태:</label>
                        <span class="value">${statusHtml}</span>
                    </div>
                    <div class="result-item">
                        <label>PG TID:</label>
                        <span class="value">${response.data.tid || '-'}</span>
                    </div>
                `;
            }
            
            resultBox.innerHTML = `
                <h3>${title}</h3>
                ${contentHtml}
                <div class="result-item" style="margin-top:10px;">
                    <label>전체 응답:</label>
                    <pre>${JSON.stringify(response, null, 2)}</pre>
                </div>
            `;
            
            resultSection.appendChild(resultBox);
        }

        // 진행률 업데이트
        function updateProgress(percentage) {
            document.getElementById('progress').style.width = percentage + '%';
        }
    </script>
</body>
</html>
