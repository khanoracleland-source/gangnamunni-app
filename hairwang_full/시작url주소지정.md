# 앱 시작 URL 주소 지정 가이드

## 파일 정보

| 항목 | 내용 |
|------|------|
| 파일명 | start_app.php |
| 위치 | `/rb/start_app.php` |
| URL | https://hairwang.com/rb/start_app.php |
| 용도 | 앱 시작 시 리다이렉트할 페이지 지정 |

---

## 소스 코드 전체

**파일 경로**: `c:\xampp\htdocs\hairwang_server_backup_and_dev\2025_11_27\www\rb\start_app.php`

```php
<?php
/**
 * 앱 시작 페이지 리다이렉트
 *
 * Android/iOS 앱에서 시작 시 호출하는 페이지
 * 아래 $start_url 변수만 수정하면 앱 시작 페이지를 변경할 수 있습니다.
 *
 * URL: https://hairwang.com/rb/start_app.php
 */

// ============================================
// 앱 시작 페이지 URL 설정 (이 부분만 수정하세요)
// ============================================
$start_url = "https://hairwang.com";

// 리다이렉트 실행
header("Location: " . $start_url);
exit;
?>
```

---

## 사용 방법

### 1. 시작 URL 변경 방법

파일을 열어서 **8번째 줄**의 `$start_url` 값만 수정하면 됩니다.

#### 예시

**메인 페이지로 이동**
```php
$start_url = "https://hairwang.com";
```

**홈 페이지로 이동**
```php
$start_url = "https://hairwang.com/rb/home.php";
```

**특정 게시판으로 이동**
```php
$start_url = "https://hairwang.com/bbs/board.php?bo_table=free";
```

**외부 URL로 이동**
```php
$start_url = "https://www.example.com";
```

---

## 앱에서 사용하는 방법

### Android (Java/Kotlin)

#### MainActivity.java
```java
package com.hairwang;

import android.os.Bundle;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import androidx.appcompat.app.AppCompatActivity;

public class MainActivity extends AppCompatActivity {

    private WebView webView;

    // 앱 시작 URL
    private static final String START_URL = "https://hairwang.com/rb/start_app.php";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        // WebView 초기화
        webView = findViewById(R.id.webView);
        webView.setWebViewClient(new WebViewClient());

        // WebView 설정
        webView.getSettings().setJavaScriptEnabled(true);
        webView.getSettings().setDomStorageEnabled(true);

        // 앱 시작 URL 로드
        webView.loadUrl(START_URL);
    }
}
```

#### MainActivity.kt (Kotlin)
```kotlin
package com.hairwang

import android.os.Bundle
import android.webkit.WebView
import android.webkit.WebViewClient
import androidx.appcompat.app.AppCompatActivity

class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView

    // 앱 시작 URL
    companion object {
        private const val START_URL = "https://hairwang.com/rb/start_app.php"
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        // WebView 초기화
        webView = findViewById(R.id.webView)
        webView.webViewClient = WebViewClient()

        // WebView 설정
        webView.settings.apply {
            javaScriptEnabled = true
            domStorageEnabled = true
        }

        // 앱 시작 URL 로드
        webView.loadUrl(START_URL)
    }
}
```

---

### iOS (Swift)

#### ViewController.swift
```swift
import UIKit
import WebKit

class ViewController: UIViewController, WKNavigationDelegate {

    var webView: WKWebView!

    // 앱 시작 URL
    let startURL = "https://hairwang.com/rb/start_app.php"

    override func loadView() {
        // WKWebView 초기화
        let webConfiguration = WKWebViewConfiguration()
        webView = WKWebView(frame: .zero, configuration: webConfiguration)
        webView.navigationDelegate = self
        view = webView
    }

    override func viewDidLoad() {
        super.viewDidLoad()

        // 앱 시작 URL 로드
        if let url = URL(string: startURL) {
            let request = URLRequest(url: url)
            webView.load(request)
        }
    }
}
```

#### AppDelegate.swift (SwiftUI)
```swift
import SwiftUI
import WebKit

struct WebView: UIViewRepresentable {
    let url: URL

    func makeUIView(context: Context) -> WKWebView {
        let webView = WKWebView()
        return webView
    }

    func updateUIView(_ webView: WKWebView, context: Context) {
        let request = URLRequest(url: url)
        webView.load(request)
    }
}

struct ContentView: View {
    // 앱 시작 URL
    let startURL = URL(string: "https://hairwang.com/rb/start_app.php")!

    var body: some View {
        WebView(url: startURL)
            .edgesIgnoringSafeArea(.all)
    }
}
```

---

## 동작 원리

```
1. 앱 시작
   ↓
2. https://hairwang.com/rb/start_app.php 호출
   ↓
3. start_app.php에서 $start_url 읽기
   ↓
4. header("Location: $start_url") 실행
   ↓
5. 지정된 URL로 자동 리다이렉트
   ↓
6. 앱에서 해당 페이지 표시
```

---

## URL 변경 예시

### 시나리오 1: 이벤트 페이지로 시작

```php
// 크리스마스 이벤트 기간
$start_url = "https://hairwang.com/event/christmas.php";
```

### 시나리오 2: 공지사항으로 시작

```php
// 중요 공지 확인
$start_url = "https://hairwang.com/bbs/board.php?bo_table=notice&wr_id=123";
```

### 시나리오 3: 앱 전용 페이지로 시작

```php
// 앱 전용 메인 페이지
$start_url = "https://hairwang.com/app/main.php";
```

---

## 서버 업로드

### 업로드 경로

| 로컬 경로 | 서버 경로 |
|-----------|-----------|
| `rb/start_app.php` | `/rb/start_app.php` |

### FTP/SFTP 업로드 명령

```bash
# SFTP 연결
sftp user@hairwang.com

# 파일 업로드
put rb/start_app.php /var/www/html/rb/start_app.php
```

### 파일 권한 설정

```bash
chmod 644 /var/www/html/rb/start_app.php
```

---

## 테스트 방법

### 1. 브라우저 테스트

브라우저에서 다음 URL 접속:
```
https://hairwang.com/rb/start_app.php
```

지정한 URL로 자동 이동되면 정상 작동

### 2. 앱 테스트

1. 앱 빌드 및 설치
2. 앱 실행
3. 지정한 페이지가 표시되는지 확인

---

## 문제 해결

### Q1. 리다이렉트가 작동하지 않아요

**원인**: 헤더가 이미 전송된 경우

**해결**: start_app.php 파일 맨 앞에 공백이나 BOM이 있는지 확인
```php
<?php  // ← 이 앞에 공백이 있으면 안 됨
```

### Q2. 앱에서 페이지가 안 열려요

**원인**: WebView 설정 문제

**해결**: JavaScript 활성화 확인
```java
// Android
webView.getSettings().setJavaScriptEnabled(true);
```

### Q3. HTTPS 오류가 발생해요

**원인**: SSL 인증서 문제

**해결**: WebView에서 SSL 오류 무시 (개발 환경에서만)
```java
// 주의: 운영 환경에서는 사용 금지
webView.setWebViewClient(new WebViewClient() {
    @Override
    public void onReceivedSslError(WebView view, SslErrorHandler handler, SslError error) {
        handler.proceed(); // 개발 환경에서만!
    }
});
```

---

## 주의사항

1. **절대 경로 사용 권장**
   ```php
   // 권장
   $start_url = "https://hairwang.com/rb/home.php";

   // 비권장 (상대 경로)
   $start_url = "/rb/home.php";
   ```

2. **URL 검증**
   ```php
   // XSS 방지를 위한 URL 검증
   if (filter_var($start_url, FILTER_VALIDATE_URL)) {
       header("Location: " . $start_url);
   }
   ```

3. **리다이렉트 루프 방지**
   ```php
   // start_app.php 자신을 가리키지 않도록 주의
   // ❌ 잘못된 예
   $start_url = "https://hairwang.com/rb/start_app.php";
   ```

---

## 업데이트 이력

| 날짜 | 버전 | 내용 |
|------|------|------|
| 2025-12-09 | 1.0.0 | 초기 생성 |

---

## 문의

문제 발생 시 연락처:
- 이메일: support@hairwang.com
- 웹사이트: https://hairwang.com
