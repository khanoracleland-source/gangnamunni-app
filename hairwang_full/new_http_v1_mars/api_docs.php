<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>푸시 알림 API 호출 방법</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css">
    <style>
        body {
            font-family: 'Noto Sans KR', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .docs-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        .docs-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .docs-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        .docs-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0;
        }
        .docs-nav {
            background: #f8f9fa;
            padding: 20px 40px;
            border-bottom: 2px solid #e9ecef;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        .docs-nav a {
            color: #667eea;
            text-decoration: none;
            margin-right: 20px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .docs-nav a:hover {
            color: #764ba2;
        }
        .docs-content {
            padding: 40px;
        }
        .docs-content h2 {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
            margin-top: 40px;
            margin-bottom: 20px;
            font-size: 2rem;
        }
        .docs-content h3 {
            color: #764ba2;
            margin-top: 30px;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }
        .docs-content h4 {
            color: #555;
            margin-top: 25px;
            margin-bottom: 12px;
            font-size: 1.2rem;
        }
        .docs-content pre {
            background: #282c34;
            border-radius: 10px;
            padding: 20px;
            overflow-x: auto;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        .docs-content code {
            background: #f4f4f4;
            padding: 3px 8px;
            border-radius: 4px;
            color: #e83e8c;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }
        .docs-content pre code {
            background: transparent;
            padding: 0;
            color: #abb2bf;
            font-size: 0.95em;
        }
        .docs-content table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .docs-content table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .docs-content table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        .docs-content table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .docs-content table tbody tr:hover {
            background: #f8f9fa;
        }
        .copy-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #667eea;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
            opacity: 0.7;
            transition: all 0.3s;
        }
        .copy-btn:hover {
            opacity: 1;
            background: #764ba2;
        }
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #667eea;
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: all 0.3s;
            opacity: 0;
            visibility: hidden;
        }
        .back-to-top.show {
            opacity: 1;
            visibility: visible;
        }
        .back-to-top:hover {
            background: #764ba2;
            transform: translateY(-5px);
        }
        .alert-info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .section-divider {
            border: 0;
            height: 2px;
            background: linear-gradient(to right, transparent, #667eea, transparent);
            margin: 40px 0;
        }
    </style>
</head>
<body>
    <div class="docs-container">
        <div class="docs-header">
            <h1><i class="fas fa-rocket me-3"></i>푸시 알림 API 호출 방법</h1>
            <p><i class="fas fa-link me-2"></i>https://hairwang.com/new_http_v1_mars/</p>
        </div>

        <div class="docs-nav">
            <a href="#auth"><i class="fas fa-key me-1"></i>인증</a>
            <a href="#individual"><i class="fas fa-user me-1"></i>개별 푸시</a>
            <a href="#group"><i class="fas fa-users me-1"></i>전체 푸시</a>
            <a href="#ios"><i class="fab fa-apple me-1"></i>iOS</a>
            <a href="#android"><i class="fab fa-android me-1"></i>Android</a>
            <a href="#response"><i class="fas fa-code me-1"></i>응답</a>
            <a href="index.php" class="float-end"><i class="fas fa-arrow-left me-1"></i>돌아가기</a>
        </div>

        <div class="docs-content">

            <!-- 인증 방법 -->
            <h2 id="auth"><i class="fas fa-key me-2"></i>인증 방법</h2>
            <p>API 호출 시 <strong>2가지 인증 방법</strong> 중 하나를 선택할 수 있습니다.</p>

            <h3>방법 1: 세션 인증 (웹 브라우저)</h3>
            <div class="alert-info-box">
                <ol>
                    <li>https://hairwang.com/new_http_v1_mars/login.php 에서 로그인</li>
                    <li>세션 쿠키로 자동 인증</li>
                    <li>API 호출 시 별도 인증 불필요</li>
                </ol>
            </div>

            <h3>방법 2: API 키 인증 (프로그램)</h3>
            <p><strong>API 키</strong>: <code>mars_push_api_key_2025</code></p>

            <p><strong>HTTP 헤더 방식</strong>:</p>
            <pre><code class="language-http">X-API-Key: mars_push_api_key_2025</code></pre>

            <p><strong>URL 파라미터 방식</strong>:</p>
            <pre><code>?api_key=mars_push_api_key_2025</code></pre>

            <hr class="section-divider">

            <!-- 개별 푸시 알림 -->
            <h2 id="individual"><i class="fas fa-user me-2"></i>개별 푸시 알림</h2>
            <p>특정 사용자에게 푸시 알림을 전송합니다.</p>

            <h3><i class="fas fa-map-marker-alt me-2"></i>엔드포인트</h3>
            <pre><code>POST/GET https://hairwang.com/new_http_v1_mars/push_api.php</code></pre>

            <h3><i class="fas fa-clipboard-list me-2"></i>필수 파라미터</h3>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>파라미터</th>
                        <th>타입</th>
                        <th>설명</th>
                        <th>예시</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>app</code></td>
                        <td>string</td>
                        <td>기기 유형</td>
                        <td><code>android</code> 또는 <code>ios</code></td>
                    </tr>
                    <tr>
                        <td><code>user_token</code></td>
                        <td>string</td>
                        <td>FCM 토큰</td>
                        <td><code>dXYz...</code> (152자)</td>
                    </tr>
                    <tr>
                        <td><code>title</code></td>
                        <td>string</td>
                        <td>알림 제목</td>
                        <td><code>새로운 메시지</code></td>
                    </tr>
                    <tr>
                        <td><code>memo</code></td>
                        <td>string</td>
                        <td>알림 내용</td>
                        <td><code>안녕하세요</code></td>
                    </tr>
                </tbody>
            </table>

            <h3><i class="fas fa-cog me-2"></i>선택 파라미터</h3>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>파라미터</th>
                        <th>타입</th>
                        <th>설명</th>
                        <th>기본값</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>url</code></td>
                        <td>string</td>
                        <td>클릭 시 이동 URL</td>
                        <td><code>https://hairwang.com?call=push</code></td>
                    </tr>
                    <tr>
                        <td><code>file_url</code></td>
                        <td>string</td>
                        <td>이미지 URL</td>
                        <td>(없음)</td>
                    </tr>
                    <tr>
                        <td><code>arg1</code></td>
                        <td>string</td>
                        <td>커스텀 데이터 1</td>
                        <td><code>$arg1</code></td>
                    </tr>
                    <tr>
                        <td><code>arg2</code></td>
                        <td>string</td>
                        <td>커스텀 데이터 2</td>
                        <td><code>1</code> (팝업 표시)</td>
                    </tr>
                </tbody>
            </table>

            <h3><i class="fas fa-code me-2"></i>호출 예시</h3>

            <h4><i class="fab fa-linux me-1"></i> cURL (Linux/Mac)</h4>
            <pre><code class="language-bash">curl -X POST "https://hairwang.com/new_http_v1_mars/push_api.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "X-API-Key: mars_push_api_key_2025" \
  -d "app=android" \
  -d "user_token=dXYz1234567890abcdefg..." \
  -d "title=새로운 메시지" \
  -d "memo=안녕하세요. 테스트 메시지입니다." \
  -d "url=https://hairwang.com/board.php?bo_table=notice" \
  -d "file_url=https://hairwang.com/images/notification.jpg"</code></pre>

            <h4><i class="fab fa-windows me-1"></i> PowerShell (Windows)</h4>
            <pre><code class="language-powershell">$body = @{
    app = "android"
    user_token = "dXYz1234567890abcdefg..."
    title = "새로운 메시지"
    memo = "안녕하세요. 테스트 메시지입니다."
    url = "https://hairwang.com/board.php?bo_table=notice"
    file_url = "https://hairwang.com/images/notification.jpg"
}

Invoke-RestMethod -Uri "https://hairwang.com/new_http_v1_mars/push_api.php" `
  -Method POST `
  -Headers @{"X-API-Key"="mars_push_api_key_2025"} `
  -Body $body</code></pre>

            <h4><i class="fab fa-php me-1"></i> PHP</h4>
            <pre><code class="language-php">&lt;?php
$data = [
    'app' => 'android',
    'user_token' => 'dXYz1234567890abcdefg...',
    'title' => '새로운 메시지',
    'memo' => '안녕하세요. 테스트 메시지입니다.',
    'url' => 'https://hairwang.com/board.php?bo_table=notice',
    'file_url' => 'https://hairwang.com/images/notification.jpg'
];

$ch = curl_init('https://hairwang.com/new_http_v1_mars/push_api.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: mars_push_api_key_2025'
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
print_r($result);
?&gt;</code></pre>

            <h4><i class="fab fa-js me-1"></i> JavaScript (fetch)</h4>
            <pre><code class="language-javascript">const data = {
    app: 'android',
    user_token: 'dXYz1234567890abcdefg...',
    title: '새로운 메시지',
    memo: '안녕하세요. 테스트 메시지입니다.',
    url: 'https://hairwang.com/board.php?bo_table=notice',
    file_url: 'https://hairwang.com/images/notification.jpg'
};

fetch('https://hairwang.com/new_http_v1_mars/push_api.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-API-Key': 'mars_push_api_key_2025'
    },
    body: new URLSearchParams(data)
})
.then(response => response.json())
.then(result => console.log(result))
.catch(error => console.error('Error:', error));</code></pre>

            <h4><i class="fab fa-python me-1"></i> Python</h4>
            <pre><code class="language-python">import requests

data = {
    'app': 'android',
    'user_token': 'dXYz1234567890abcdefg...',
    'title': '새로운 메시지',
    'memo': '안녕하세요. 테스트 메시지입니다.',
    'url': 'https://hairwang.com/board.php?bo_table=notice',
    'file_url': 'https://hairwang.com/images/notification.jpg'
}

headers = {
    'X-API-Key': 'mars_push_api_key_2025'
}

response = requests.post(
    'https://hairwang.com/new_http_v1_mars/push_api.php',
    headers=headers,
    data=data
)

print(response.json())</code></pre>

            <hr class="section-divider">

            <!-- 전체 푸시 알림 -->
            <h2 id="group"><i class="fas fa-users me-2"></i>전체 푸시 알림 (토픽)</h2>
            <p>모든 사용자에게 푸시 알림을 전송합니다.</p>

            <h3><i class="fas fa-map-marker-alt me-2"></i>엔드포인트</h3>
            <pre><code>POST/GET https://hairwang.com/new_http_v1_mars/push_api.php</code></pre>

            <h3><i class="fas fa-clipboard-list me-2"></i>필수 파라미터</h3>
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>파라미터</th>
                        <th>타입</th>
                        <th>설명</th>
                        <th>예시</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>push_type</code></td>
                        <td>string</td>
                        <td>푸시 타입</td>
                        <td><code>group</code></td>
                    </tr>
                    <tr>
                        <td><code>title</code></td>
                        <td>string</td>
                        <td>알림 제목</td>
                        <td><code>공지사항</code></td>
                    </tr>
                    <tr>
                        <td><code>memo</code></td>
                        <td>string</td>
                        <td>알림 내용</td>
                        <td><code>새로운 업데이트</code></td>
                    </tr>
                </tbody>
            </table>

            <h3><i class="fas fa-code me-2"></i>호출 예시</h3>

            <h4><i class="fab fa-linux me-1"></i> cURL (Linux/Mac)</h4>
            <pre><code class="language-bash">curl -X POST "https://hairwang.com/new_http_v1_mars/push_api.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "X-API-Key: mars_push_api_key_2025" \
  -d "push_type=group" \
  -d "title=공지사항" \
  -d "memo=새로운 업데이트가 있습니다." \
  -d "url=https://hairwang.com/notice" \
  -d "file_url=https://hairwang.com/images/update.jpg"</code></pre>

            <h4><i class="fab fa-php me-1"></i> PHP</h4>
            <pre><code class="language-php">&lt;?php
$data = [
    'push_type' => 'group',
    'title' => '공지사항',
    'memo' => '새로운 업데이트가 있습니다.',
    'url' => 'https://hairwang.com/notice',
    'file_url' => 'https://hairwang.com/images/update.jpg'
];

$ch = curl_init('https://hairwang.com/new_http_v1_mars/push_api.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: mars_push_api_key_2025'
]);

$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
print_r($result);
?&gt;</code></pre>

            <hr class="section-divider">

            <!-- iOS 호출 방법 -->
            <h2 id="ios"><i class="fab fa-apple me-2"></i>iOS 호출 방법</h2>

            <h3>Swift URLSession 방식</h3>

            <h4>개별 푸시</h4>
            <pre><code class="language-swift">import Foundation

func sendIndividualPush() {
    let url = URL(string: "https://hairwang.com/new_http_v1_mars/push_api.php")!
    var request = URLRequest(url: url)
    request.httpMethod = "POST"
    request.setValue("mars_push_api_key_2025", forHTTPHeaderField: "X-API-Key")
    request.setValue("application/x-www-form-urlencoded", forHTTPHeaderField: "Content-Type")

    let parameters = [
        "app": "ios",
        "user_token": "YOUR_FCM_TOKEN",
        "title": "새로운 메시지",
        "memo": "안녕하세요. 테스트 메시지입니다.",
        "url": "https://hairwang.com/board.php?bo_table=notice",
        "file_url": "https://hairwang.com/images/notification.jpg"
    ]

    let bodyString = parameters.map { "\($0.key)=\($0.value)" }.joined(separator: "&")
    request.httpBody = bodyString.addingPercentEncoding(withAllowedCharacters: .urlQueryAllowed)?.data(using: .utf8)

    let task = URLSession.shared.dataTask(with: request) { data, response, error in
        guard let data = data, error == nil else {
            print("Error: \(error?.localizedDescription ?? "Unknown error")")
            return
        }

        if let json = try? JSONSerialization.jsonObject(with: data) as? [String: Any] {
            print("Response: \(json)")
        }
    }

    task.resume()
}</code></pre>

            <hr class="section-divider">

            <!-- Android 호출 방법 -->
            <h2 id="android"><i class="fab fa-android me-2"></i>Android 호출 방법</h2>

            <h3>Kotlin Retrofit 방식</h3>

            <h4>1. Retrofit 인터페이스 정의</h4>
            <pre><code class="language-kotlin">import retrofit2.Call
import retrofit2.http.*

interface PushApiService {
    @FormUrlEncoded
    @POST("push_api.php")
    fun sendIndividualPush(
        @Header("X-API-Key") apiKey: String,
        @Field("app") app: String,
        @Field("user_token") userToken: String,
        @Field("title") title: String,
        @Field("memo") memo: String,
        @Field("url") url: String? = null,
        @Field("file_url") fileUrl: String? = null
    ): Call&lt;PushResponse&gt;
}

data class PushResponse(
    val success: Boolean,
    val message: String,
    val data: Map&lt;String, Any&gt;?,
    val timestamp: String
)</code></pre>

            <h4>2. 개별 푸시 호출</h4>
            <pre><code class="language-kotlin">fun sendIndividualPushNotification(fcmToken: String) {
    RetrofitClient.pushApi.sendIndividualPush(
        apiKey = "mars_push_api_key_2025",
        app = "android",
        userToken = fcmToken,
        title = "새로운 메시지",
        memo = "안녕하세요. 테스트 메시지입니다.",
        url = "https://hairwang.com/board.php?bo_table=notice",
        fileUrl = "https://hairwang.com/images/notification.jpg"
    ).enqueue(object : Callback&lt;PushResponse&gt; {
        override fun onResponse(call: Call&lt;PushResponse&gt;, response: Response&lt;PushResponse&gt;) {
            if (response.isSuccessful) {
                val result = response.body()
                println("Success: ${result?.message}")
            }
        }

        override fun onFailure(call: Call&lt;PushResponse&gt;, t: Throwable) {
            println("Failure: ${t.message}")
        }
    })
}</code></pre>

            <hr class="section-divider">

            <!-- 응답 형식 -->
            <h2 id="response"><i class="fas fa-code me-2"></i>응답 형식</h2>

            <h3><i class="fas fa-check-circle text-success me-2"></i>성공 응답</h3>
            <pre><code class="language-json">{
    "success": true,
    "message": "푸시 알림이 성공적으로 전송되었습니다.",
    "data": {
        "app": "android",
        "user_token": "dXYz1234567890...",
        "title": "새로운 메시지",
        "memo": "안녕하세요. 테스트 메시지입니다.",
        "url": "https://hairwang.com/board.php?bo_table=notice?call=push",
        "file_url": "https://hairwang.com/images/notification.jpg",
        "arg1": "$arg1",
        "arg2": "1",
        "arg3": "HAIRWANG"
    },
    "timestamp": "2025-01-03 14:30:25"
}</code></pre>

            <h3><i class="fas fa-times-circle text-danger me-2"></i>실패 응답</h3>
            <pre><code class="language-json">{
    "success": false,
    "message": "푸시 알림 전송에 실패했습니다.",
    "data": {
        "app": "android",
        "user_token": "dXYz1234567890...",
        "error": "Invalid registration token",
        "firebase_config": "/path/to/firebase.json",
        "project_id": "hairwang-web-app"
    },
    "timestamp": "2025-01-03 14:30:25"
}</code></pre>

            <hr class="section-divider">

            <h2><i class="fas fa-cog me-2"></i>설정 정보</h2>

            <h3>Firebase 설정</h3>
            <ul>
                <li><strong>프로젝트 ID</strong>: <code>hairwang-web-app</code></li>
                <li><strong>토픽 이름</strong>: <code>news_test</code> (전체 푸시용)</li>
                <li><strong>기본 URL</strong>: <code>https://hairwang.com</code></li>
            </ul>

            <h3>앱 설정</h3>
            <ul>
                <li><strong>arg3 값</strong>: <code>HAIRWANG</code> (보낸 사람 식별자)</li>
                <li><strong>arg2 값</strong>: <code>1</code> (팝업 표시 여부)</li>
            </ul>

            <hr class="section-divider">

            <h2><i class="fas fa-exclamation-triangle me-2"></i>주의사항</h2>
            <ol>
                <li><strong>토큰 유효성</strong>: FCM 토큰은 152자 이상의 문자열입니다.</li>
                <li><strong>URL 인코딩</strong>: 파라미터에 특수문자가 포함된 경우 URL 인코딩이 필요합니다.</li>
                <li><strong>이미지 크기</strong>: <code>file_url</code> 이미지는 1MB 이하를 권장합니다.</li>
                <li><strong>토픽 구독</strong>: 전체 푸시를 받으려면 앱에서 <code>news_test</code> 토픽을 구독해야 합니다.</li>
                <li><strong>인증 보안</strong>: API 키는 서버 측에서만 사용하고, 클라이언트 앱에 노출하지 마세요.</li>
            </ol>

            <div class="alert-info-box mt-4">
                <h4><i class="fas fa-info-circle me-2"></i>문의</h4>
                <p class="mb-0">문제가 발생하거나 추가 정보가 필요하면 시스템 관리자에게 문의하세요.<br>
                <strong>테스트 페이지</strong>: <a href="index.php">https://hairwang.com/new_http_v1_mars/index.php</a></p>
            </div>

        </div>
    </div>

    <!-- Back to Top 버튼 -->
    <div class="back-to-top" id="backToTop">
        <i class="fas fa-arrow-up"></i>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>
        // 코드 하이라이팅
        hljs.highlightAll();

        // 코드 블록에 복사 버튼 추가
        document.querySelectorAll('pre').forEach((block) => {
            const wrapper = document.createElement('div');
            wrapper.style.position = 'relative';
            block.parentNode.insertBefore(wrapper, block);
            wrapper.appendChild(block);

            const copyBtn = document.createElement('button');
            copyBtn.className = 'copy-btn';
            copyBtn.innerHTML = '<i class="fas fa-copy me-1"></i>복사';
            copyBtn.onclick = function() {
                const code = block.querySelector('code').textContent;
                navigator.clipboard.writeText(code).then(() => {
                    copyBtn.innerHTML = '<i class="fas fa-check me-1"></i>복사됨!';
                    setTimeout(() => {
                        copyBtn.innerHTML = '<i class="fas fa-copy me-1"></i>복사';
                    }, 2000);
                });
            };
            wrapper.appendChild(copyBtn);
        });

        // 스무스 스크롤
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Back to Top 버튼
        const backToTop = document.getElementById('backToTop');

        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                backToTop.classList.add('show');
            } else {
                backToTop.classList.remove('show');
            }
        });

        backToTop.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    </script>
</body>
</html>
