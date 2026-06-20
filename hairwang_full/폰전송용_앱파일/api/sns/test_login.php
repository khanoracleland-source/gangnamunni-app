<?php
/**
 * SNS 로그인 테스트 페이지 (외부 사이트용)
 * 외부 사이트에서 직접 SNS 로그인을 테스트하는 페이지
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 공통 파일 포함
include_once dirname(__FILE__).'/../common.php';

// 데이터베이스 테이블 확인
if (!isset($g5['api_key_table'])) {
    $g5['api_key_table'] = G5_TABLE_PREFIX.'api_keys';
}

// 현재 도메인 및 경로 구성
$api_url = G5_URL . '/api/sns';

// 관리자 메뉴 API 키 목록 (테스트용으로만 활성화된 키)
$sql = "SELECT ak_id, ak_name, ak_key FROM {$g5['api_key_table']} WHERE ak_status = 1 AND (ak_type = 'sns' OR ak_type = 'all') ORDER BY ak_id DESC";
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
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SNS 로그인 테스트</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f5f5f5; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-bottom: 30px; text-align: center; }
        h2 { color: #555; margin-top: 30px; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #eee; }
        h3 { color: #666; margin-top: 20px; margin-bottom: 15px; }
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
    </style>
</head>
<body>
    <div class="container">
        <h1>SNS 로그인 테스트</h1>
        
        <div class="info-box">
            <strong>API 정보:</strong><br>
            API URL: <code><?php echo $api_url; ?>/login.php</code><br>
            테스트 페이지 URL: <code><?php echo G5_URL; ?>/api/sns/test_login.php</code>
        </div>

        <h2>1. API 인증 정보</h2>
        <form id="authForm">
            <div class="form-group">
                <label for="apiKey">API 키</label>
                <select id="apiKey" name="apiKey" required>
                    <option value="">API 키를 선택해주세요</option>
                    <?php
                    while ($row = sql_fetch_array($api_key_result)) {
                        ?>
                        <option value="<?php echo htmlspecialchars($row['ak_key']); ?>" data-secret="<?php echo htmlspecialchars($row['ak_key']); ?>">
                            <?php echo htmlspecialchars($row['ak_name']); ?>
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

        <h2>2. SNS 로그인</h2>
        <form id="loginForm">
            <div class="form-group">
                <label for="provider">SNS 제공자</label>
                <select id="provider" name="provider" required>
                    <option value="">제공자를 선택해주세요</option>
                    <?php
                    foreach ($sns_providers as $code => $name) {
                        ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
            <div class="form-group">
                <label for="token">SNS 액세스 토큰</label>
                <textarea id="token" name="token" rows="3" placeholder="SNS 제공자에서 받은 액세스 토큰을 입력하세요" required></textarea>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" id="autoRegister" name="autoRegister" value="1" checked>
                    신규 회원일 경우 자동 가입
                </label>
            </div>
            <div class="form-group">
                <label for="customFields">추가 필드 (JSON 형식, 선택사항)</label>
                <textarea id="customFields" name="customFields" rows="3" placeholder='{"field1":"value1", "field2":"value2"}'></textarea>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-primary" onclick="sendLoginRequest()">로그인 요청</button>
            </div>
        </form>

        <div id="loginResult" class="result-box">
            <h3>로그인 결과</h3>
            <div id="resultContent"></div>
        </div>

        <h2>3. 토큰 발급 테스트</h2>
        <form id="tokenForm">
            <div class="form-group">
                <label for="mbId">회원 ID</label>
                <input type="text" id="mbId" name="mbId" placeholder="로그인된 회원 ID">
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-success" onclick="sendTokenRequest()">토큰 발급</button>
            </div>
        </form>

        <div id="tokenResult" class="result-box">
            <h3>토큰 결과</h3>
            <div id="tokenContent"></div>
        </div>
    </div>

    <script>
        // API 키 선택 시 시크릿 자동 입력
        document.getElementById('apiKey').addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            var secret = selectedOption.getAttribute('data-secret');
            document.getElementById('apiSecret').value = secret || '';
        });

        // 로그인 요청 전송
        function sendLoginRequest() {
            var apiKey = document.getElementById('apiKey').value;
            var apiSecret = document.getElementById('apiSecret').value;
            var provider = document.getElementById('provider').value;
            var token = document.getElementById('token').value;
            var autoRegister = document.getElementById('autoRegister').checked ? 1 : 0;
            var customFields = document.getElementById('customFields').value;
            
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
            
            if (customFields) {
                try {
                    var fields = JSON.parse(customFields);
                    for (var key in fields) {
                        formData.append('custom_fields[' + key + ']', fields[key]);
                    }
                } catch (e) {
                    alert('추가 필드는 JSON 형식이어야 합니다.');
                    return false;
                }
            }
            
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
        }

        // 로그인 결과 표시
        function displayLoginResult(response) {
            var resultBox = document.getElementById('loginResult');
            var content = document.getElementById('resultContent');
            
            resultBox.style.display = 'block';
            resultBox.classList.remove('success', 'error');
            
            if (response.success) {
                resultBox.classList.add('success');
                
                content.innerHTML = `
                    <div class="result-item">
                        <label>상태:</label>
                        <span class="value success">성공</span>
                    </div>
                    <div class="result-item">
                        <label>회원 ID:</label>
                        <span class="value">${response.data.mb_id || '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>회원명:</label>
                        <span class="value">${response.data.mb_name || '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>닉네임:</label>
                        <span class="value">${response.data.mb_nick || '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>이메일:</label>
                        <span class="value">${response.data.mb_email || '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>SNS 제공자:</label>
                        <span class="value">${response.data.provider ? response.data.provider.toUpperCase() : '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>신규 회원:</label>
                        <span class="value">${response.data.is_new_user ? '네 (신규 회원)' : '아니오 (기존 회원)'}</span>
                    </div>
                    <div class="result-item">
                        <label>로그인 시간:</label>
                        <span class="value">${response.data.login_time || '-'}</span>
                    </div>
                `;
            } else {
                resultBox.classList.add('error');
                
                content.innerHTML = `
                    <div class="result-item">
                        <label>상태:</label>
                        <span class="value error">실패</span>
                    </div>
                    <div class="result-item">
                        <label>에러 코드:</label>
                        <span class="value">${response.error.code || '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>에러 메시지:</label>
                        <span class="value">${response.error.message || '-'}</span>
                    </div>
                `;
            }
            
            content.innerHTML += `
                <div class="result-item" style="margin-top: 20px;">
                    <label>전체 응답:</label>
                    <pre>${JSON.stringify(response, null, 2)}</pre>
                </div>
            `;
        }

        // 토큰 요청 전송
        function sendTokenRequest() {
            var apiKey = document.getElementById('apiKey').value;
            var apiSecret = document.getElementById('apiSecret').value;
            var mbId = document.getElementById('mbId').value;
            
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
        }

        // 토큰 결과 표시
        function displayTokenResult(response) {
            var resultBox = document.getElementById('tokenResult');
            var content = document.getElementById('tokenContent');
            
            resultBox.style.display = 'block';
            resultBox.classList.remove('success', 'error');
            
            if (response.success) {
                resultBox.classList.add('success');
                
                content.innerHTML = `
                    <div class="result-item">
                        <label>상태:</label>
                        <span class="value success">성공</span>
                    </div>
                    <div class="result-item">
                        <label>토큰:</label>
                        <input type="text" value="${response.data.token || ''}" style="width: 80%;" readonly>
                    </div>
                    <div class="result-item">
                        <label>토큰 타입:</label>
                        <span class="value">${response.data.token_type || '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>만료 시간:</label>
                        <span class="value">${response.data.expires_in ? response.data.expires_in + '초' : '-'}</span>
                    </div>
                `;
            } else {
                resultBox.classList.add('error');
                
                content.innerHTML = `
                    <div class="result-item">
                        <label>상태:</label>
                        <span class="value error">실패</span>
                    </div>
                    <div class="result-item">
                        <label>에러 코드:</label>
                        <span class="value">${response.error.code || '-'}</span>
                    </div>
                    <div class="result-item">
                        <label>에러 메시지:</label>
                        <span class="value">${response.error.message || '-'}</span>
                    </div>
                `;
            }
            
            content.innerHTML += `
                <div class="result-item" style="margin-top: 20px;">
                    <label>전체 응답:</label>
                    <pre>${JSON.stringify(response, null, 2)}</pre>
                </div>
            `;
        }
    </script>
</body>
</html>
