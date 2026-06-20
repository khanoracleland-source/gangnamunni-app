<?php
/**
 * SNS 로그인 테스트 페이지
 * 외부 사이트에서 SNS 로그인 후 결과를 확인하는 테스트 페이지
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './../_common.php';

$g5['title'] = 'SNS 로그인 테스트';

// 현재 도메인 및 경로 구성
$api_url = G5_URL . '/api/sns';
$base_url = G5_URL;

// 관리자 메뉴 API 키 목록
$sql = "SELECT ak_id, ak_name, ak_key, ak_type FROM {$g5['api_key_table']} WHERE ak_status = 1 AND (ak_type = 'sns' OR ak_type = 'all') ORDER BY ak_id DESC";
$api_key_result = sql_query($sql);

// SNS 제공자 목록
$sns_providers = array(
    'naver' => '네이버',
    'kakao' => '카카오',
    'google' => '구글',
    'facebook' => '페이스북',
    'twitter' => '트위터',
    'payco' => '페이코'
);

// 로그인 결과 저장 (세션 또는 데이터베이스)
$login_result = isset($_SESSION['sns_login_test_result']) ? $_SESSION['sns_login_test_result'] : null;

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./keylist.php" class="ov_list">API 키 목록</a>
    <span class="btn_ov01"><span class="ov_txt">SNS 로그인 테스트</span></span>
</div>

<h2>SNS 로그인 API 테스트</h2>
<p class="local_desc">
    외부 사이트에서 SNS 로그인을 테스트할 수 있습니다.
</p>

<div class="local_desc01 local_desc" style="background:#f8f9fa; border:1px solid #dee2e6; padding:15px; border-radius:5px; margin-bottom:20px;">
    <h4 style="margin-top:0; color:#495057;">📌 API 엔드포인트 정보</h4>
    <table style="width:100%; border-collapse:collapse;">
        <colgroup>
            <col style="width:25%;">
            <col style="width:60%;">
            <col style="width:15%;">
        </colgroup>
        <tbody>
            <tr style="border-bottom:1px solid #dee2e6;">
                <th style="padding:10px; text-align:left; background:#e9ecef; font-weight:600;">API URL</th>
                <td style="padding:10px; font-family:monospace; font-size:13px;"><?php echo $api_url; ?>/login.php</td>
                <td style="padding:10px; text-align:center;">
                    <a href="<?php echo $api_url; ?>/login.php" target="_blank" class="btn btn_03" style="padding:5px 15px; text-decoration:none; display:inline-block; text-align:center;">보기</a>
                </td>
            </tr>
            <tr style="border-bottom:1px solid #dee2e6;">
                <th style="padding:10px; text-align:left; background:#e9ecef; font-weight:600;">테스트 페이지</th>
                <td style="padding:10px; font-family:monospace; font-size:13px;"><?php echo $base_url; ?>/api/sns/test_login.php</td>
                <td style="padding:10px; text-align:center;">
                    <a href="<?php echo $base_url; ?>/api/sns/test_login.php" target="_blank" class="btn btn_03" style="padding:5px 15px; text-decoration:none; display:inline-block; text-align:center;">보기</a>
                </td>
            </tr>
            <tr>
                <th style="padding:10px; text-align:left; background:#e9ecef; font-weight:600;">콜백 URL</th>
                <td style="padding:10px; font-family:monospace; font-size:13px;"><?php echo $base_url; ?>/api/sns/test_callback.php</td>
                <td style="padding:10px; text-align:center;">
                    <a href="<?php echo $base_url; ?>/api/sns/test_callback.php" target="_blank" class="btn btn_03" style="padding:5px 15px; text-decoration:none; display:inline-block; text-align:center;">보기</a>
                </td>
            </tr>
        </tbody>
    </table>
</div>

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
                        <span class="frm_info">SNS 로그인에 사용할 API 키를 선택하세요.</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="api_secret">API 시크릿</label></th>
                    <td>
                        <input type="text" name="api_secret" id="api_secret" class="frm_input" size="50" readonly>
                        <span class="frm_info">API 시크릿은 관리자 페이지에서 확인하실 수 있습니다.</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="provider">SNS 제공자</label></th>
                    <td>
                        <select name="provider" id="provider" class="frm_input">
                            <option value="">제공자를 선택해주세요</option>
                            <?php
                            foreach ($sns_providers as $code => $name) {
                                ?>
                                <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="token">SNS 액세스 토큰</label></th>
                    <td>
                        <input type="text" name="token" id="token" class="frm_input" size="50">
                        <span class="frm_info">SNS 제공자에서 받은 액세스 토큰을 입력하세요.</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="auto_register">자동 가입</label></th>
                    <td>
                        <input type="checkbox" name="auto_register" id="auto_register" value="1" checked>
                        <span class="frm_info">신규 회원일 경우 자동으로 가입합니다.</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="btn_fixed_top">
        <input type="submit" value="로그인 요청" class="btn_submit btn" onclick="sendLoginRequest(); return false;">
    </div>
</form>

<h3>2. 로그인 결과</h3>
<div id="login_result" style="display:none;">
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
                    <th>회원 ID</th>
                    <td id="result_mb_id"></td>
                </tr>
                <tr>
                    <th>회원명</th>
                    <td id="result_mb_name"></td>
                </tr>
                <tr>
                    <th>닉네임</th>
                    <td id="result_mb_nick"></td>
                </tr>
                <tr>
                    <th>이메일</th>
                    <td id="result_mb_email"></td>
                </tr>
                <tr>
                    <th>SNS 제공자</th>
                    <td id="result_provider"></td>
                </tr>
                <tr>
                    <th>신규 회원 여부</th>
                    <td id="result_is_new_user"></td>
                </tr>
                <tr>
                    <th>로그인 시간</th>
                    <td id="result_login_time"></td>
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

<h3>3. 외부 사이트에서 테스트</h3>
<div class="local_desc02 local_desc">
    <p>외부 사이트에서 직접 SNS 로그인을 테스트하려면 다음 페이지를 사용하세요:</p>
    <p>
        <a href="<?php echo $base_url; ?>/api/sns/test_login.php" target="_blank" class="btn btn_01">외부 사이트 로그인 테스트 &raquo;</a>
    </p>
    <p style="margin-top:10px;">
        <small>※ 새 창에서 외부 사이트 로그인 테스트 페이지가 열립니다.</small>
    </p>
</div>

<h3>4. 토큰 발급 테스트</h3>
<form name="ftoken" id="ftoken" method="post" action="">
    <div class="tbl_frm01 tbl_wrap">
        <table>
            <caption>토큰 발급 테스트</caption>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="token_mb_id">회원 ID</label></th>
                    <td>
                        <input type="text" name="mb_id" id="token_mb_id" class="frm_input" size="20">
                        <span class="frm_info">토큰을 발급할 회원 ID를 입력하세요.</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="btn_fixed_top">
        <input type="submit" value="토큰 발급" class="btn_submit btn" onclick="sendTokenRequest(); return false;">
    </div>
</form>

<div id="token_result" style="display:none; margin-top:20px;">
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
                    <td id="token_status"></td>
                </tr>
                <tr>
                    <th>토큰</th>
                    <td>
                        <input type="text" id="token_value" class="frm_input" size="80" readonly>
                    </td>
                </tr>
                <tr>
                    <th>토큰 타입</th>
                    <td id="token_type"></td>
                </tr>
                <tr>
                    <th>만료 시간</th>
                    <td id="token_expires_in"></td>
                </tr>
                <tr>
                    <th>전체 응답</th>
                    <td>
                        <pre id="token_full_response" style="background:#f5f5f5; padding:10px; border:1px solid #ddd; max-height:300px; overflow:auto;"></pre>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
// API 키 선택 시 시크릿 자동 입력
document.getElementById('api_key').addEventListener('change', function() {
    var selectedOption = this.options[this.selectedIndex];
    var secret = selectedOption.getAttribute('data-secret');
    document.getElementById('api_secret').value = secret || '';
});

// 로그인 요청 전송
function sendLoginRequest() {
    var apiKey = document.getElementById('api_key').value;
    var apiSecret = document.getElementById('api_secret').value;
    var provider = document.getElementById('provider').value;
    var token = document.getElementById('token').value;
    var autoRegister = document.getElementById('auto_register').checked ? 1 : 0;
    
    if (!apiKey) {
        alert('API 키를 선택해주세요.');
        return false;
    }
    
    if (!apiSecret) {
        alert('API 시크릿을 입력해주세요.');
        return false;
    }
    
    if (!provider) {
        alert('SNS 제공자를 선택해주세요.');
        return false;
    }
    
    if (!token) {
        alert('SNS 액세스 토큰을 입력해주세요.');
        return false;
    }
    
    var formData = new FormData();
    formData.append('provider', provider);
    formData.append('token', token);
    formData.append('auto_register', autoRegister);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo $api_url; ?>/login.php', true);
    xhr.setRequestHeader('X-API-Key', apiKey);
    xhr.setRequestHeader('X-API-Secret', apiSecret);
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            displayLoginResult(response);
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

// 로그인 결과 표시
function displayLoginResult(response) {
    var resultDiv = document.getElementById('login_result');
    resultDiv.style.display = 'block';
    
    if (response.success) {
        document.getElementById('result_status').innerHTML = '<span style="color:green;">성공</span>';
        document.getElementById('result_mb_id').textContent = response.data.mb_id || '-';
        document.getElementById('result_mb_name').textContent = response.data.mb_name || '-';
        document.getElementById('result_mb_nick').textContent = response.data.mb_nick || '-';
        document.getElementById('result_mb_email').textContent = response.data.mb_email || '-';
        document.getElementById('result_provider').textContent = response.data.provider ? response.data.provider.toUpperCase() : '-';
        document.getElementById('result_is_new_user').textContent = response.data.is_new_user ? '네 (신규 회원)' : '아니오 (기존 회원)';
        document.getElementById('result_login_time').textContent = response.data.login_time || '-';
    } else {
        document.getElementById('result_status').innerHTML = '<span style="color:red;">실패</span>';
        document.getElementById('result_mb_id').textContent = '-';
        document.getElementById('result_mb_name').textContent = '-';
        document.getElementById('result_mb_nick').textContent = '-';
        document.getElementById('result_mb_email').textContent = '-';
        document.getElementById('result_provider').textContent = '-';
        document.getElementById('result_is_new_user').textContent = '-';
        document.getElementById('result_login_time').textContent = '-';
    }
    
    document.getElementById('result_full_response').textContent = JSON.stringify(response, null, 2);
}

// 토큰 요청 전송
function sendTokenRequest() {
    var apiKey = document.getElementById('api_key').value;
    var apiSecret = document.getElementById('api_secret').value;
    var mbId = document.getElementById('token_mb_id').value;
    
    if (!apiKey) {
        alert('API 키를 선택해주세요.');
        return false;
    }
    
    if (!apiSecret) {
        alert('API 시크릿을 입력해주세요.');
        return false;
    }
    
    if (!mbId) {
        alert('회원 ID를 입력해주세요.');
        return false;
    }
    
    var formData = new FormData();
    formData.append('action', 'issue');
    formData.append('mb_id', mbId);
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', '<?php echo $api_url; ?>/token.php', true);
    xhr.setRequestHeader('X-API-Key', apiKey);
    xhr.setRequestHeader('X-API-Secret', apiSecret);
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            displayTokenResult(response);
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

// 토큰 결과 표시
function displayTokenResult(response) {
    var resultDiv = document.getElementById('token_result');
    resultDiv.style.display = 'block';
    
    if (response.success) {
        document.getElementById('token_status').innerHTML = '<span style="color:green;">성공</span>';
        document.getElementById('token_value').value = response.data.token || '-';
        document.getElementById('token_type').textContent = response.data.token_type || '-';
        document.getElementById('token_expires_in').textContent = response.data.expires_in + '초' || '-';
    } else {
        document.getElementById('token_status').innerHTML = '<span style="color:red;">실패</span>';
        document.getElementById('token_value').value = '-';
        document.getElementById('token_type').textContent = '-';
        document.getElementById('token_expires_in').textContent = '-';
    }
    
    document.getElementById('token_full_response').textContent = JSON.stringify(response, null, 2);
}
</script>

<?php
include_once G5_ADMIN_PATH . '/admin.tail.php';
?>
