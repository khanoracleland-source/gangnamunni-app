# SNS 앱 WebView 개발 분석 및 계획

**작성일**: 2026-01-19
**목적**: 3개 앱(웹, Android, iOS)에서 SNS 자동 로그인을 위한 WebView 개발

---

## 📋 목차

1. [현재 상태 분석](#1-현재-상태-분석)
2. [기술 스택 분석](#2-기술-스택-분석)
3. [개발 요구사항](#3-개발-요구사항)
4. [개발 계획 (단계별 체크리스트)](#4-개발-계획-단계별-체크리스트)
5. [세부 구현 가이드](#5-세부-구현-가이드)
6. [테스트 계획](#6-테스트-계획)

---

## 1. 현재 상태 분석

### 1.1 서버 API 현황 ✅

**완료된 API 엔드포인트**:
- ✅ `POST /api/sns/login.php` - SNS 로그인 처리
- ✅ `POST /api/sns/token.php` - JWT 토큰 발급/갱신/검증
- ✅ `POST /api/sns/callback.php` - SNS 콜백 처리

**지원 SNS 제공자**:
- 네이버, 카카오, 구글, 페이스북, 트위터, 페이코 (6개)

**API 인증 방식**:
- API Key + Secret 헤더 인증
- IP 화이트리스트 보안
- JWT 토큰 기반 세션 관리

### 1.2 Android 앱 현황 ⚠️

**기존 WebView 구현**:
- ✅ WKWebView 기반 구현 완료 ([MainActivity.java](hairwang_app_source/hairwang3/android/mars/app/src/main/java/mars/mobile/MainActivity.java))
- ✅ WebViewHelper 유틸리티 클래스 존재 ([WebViewHelper.java](hairwang_app_source/hairwang3/android/mars/app/src/main/java/mars/mobile/WebViewHelper.java))
- ✅ 쿠키 공유 설정 완료 (CookieManager 사용)
- ✅ JavaScript 인터페이스 구현 완료

**부족한 부분**:
- ❌ Chrome Custom Tabs 미구현
- ❌ SNS OAuth 자동 로그인 로직 미구현
- ❌ 앱 → 웹 → SNS → 웹 → 앱 흐름 처리 미구현

**앱 설정**:
- Package: `mars.mobile`
- Base URL: `https://hairwang.com`
- WebView Home URL: `https://hairwang.com/rb/splash.php`

### 1.3 iOS 앱 현황 ⚠️

**기존 WebView 구현**:
- ✅ WKWebView 기반 구현 완료 ([SwiftWebVC.swift](hairwang_app_source/hairwang3/ios/mars/2.iOS/chamgosu/WebView/SwiftWebVC.swift))
- ✅ SwiftModalWebVC 모달 WebView 구현
- ✅ JavaScript 메시지 핸들러 구현
- ✅ 쿠키 공유 설정 완료

**부족한 부분**:
- ❌ ASWebAuthenticationSession 미구현
- ❌ SFSafariViewController를 통한 SNS OAuth 미구현
- ❌ SNS 자동 로그인 로직 미구현
- ❌ 앱 → 웹 → SNS → 웹 → 앱 흐름 처리 미구현

**앱 설정**:
- Bundle ID: `com.hairwang.chamgosu` (추정)
- Base URL: `https://hairwang.com`
- WebView Home URL: `https://hairwang.com/rb/splash.php`

### 1.4 웹 현황 ✅

**서버 SNS 로그인 시스템**:
- ✅ 기존 SNS 로그인 플러그인 존재 (`/plugin/social`)
- ✅ HybridAuth 라이브러리 사용
- ✅ 6개 SNS 제공자 지원

**부족한 부분**:
- ❌ 앱에서 전달된 토큰 자동 로그인 로직 미구현
- ❌ 앱 → 웹 파라미터 전달 처리 미구현

---

## 2. 기술 스택 분석

### 2.1 플랫폼별 SNS 로그인용 WebView 구성요소

| 플랫폼 | 컴포넌트 | 버전/특징 | 구현 상태 |
|--------|----------|-----------|-----------|
| **iOS** | ASWebAuthenticationSession | iOS 12+ 최신 권장 | ❌ 미구현 |
| **iOS** | SFSafariViewController | iOS 9+ 기본 방식 | ❌ 미구현 |
| **iOS** | WKWebView | 일반 웹뷰 | ✅ 구현됨 |
| **Android** | Chrome Custom Tabs | 주요 사용 방식 | ❌ 미구현 |
| **Android** | WebView | 기본 방식 (보안상 비권장) | ✅ 구현됨 |
| **웹** | 표준 OAuth 2.0 | 서버 사이드 처리 | ✅ 구현됨 |

### 2.2 Chrome Custom Tabs vs 일반 WebView (Android)

#### 일반 WebView의 문제점:
- ❌ 앱 내부에 완전히 격리된 브라우저 환경
- ❌ 시스템 브라우저(Chrome)의 쿠키/세션과 분리
- ❌ 사용자가 Chrome에서 이미 구글/페이스북에 로그인되어 있어도 **다시 로그인**해야 함

#### Chrome Custom Tabs의 장점:
- ✅ **쿠키/세션 공유**: 시스템 Chrome 브라우저의 로그인 상태 그대로 활용
- ✅ **자동 로그인**: 이미 로그인된 계정이 있으면 "계속하시겠습니까?" 단계로 바로 이동
- ✅ **보안성**: OAuth 표준에 부합하는 안전한 인증 흐름
- ✅ **사용자 경험**: 앱 컨텍스트 유지하면서 브라우저 기능 제공

### 2.3 ASWebAuthenticationSession vs SFSafariViewController (iOS)

#### SFSafariViewController (iOS 9+):
- ✅ 브라우저 UI 제공
- ✅ 쿠키 공유 가능
- ⚠️ OAuth 전용 API 아님

#### ASWebAuthenticationSession (iOS 12+) - **권장**:
- ✅ OAuth 전용으로 설계됨
- ✅ 시스템 브라우저의 쿠키 자동 공유
- ✅ 더 간단한 API
- ✅ 사용자 프라이버시 강화
- ✅ 자동 로그인 지원

---

## 3. 개발 요구사항

### 3.1 핵심 요구사항

1. **자동 로그인 지원**
   - 사용자가 이미 SNS에 로그인되어 있으면 자동으로 로그인 처리
   - 재로그인 최소화

2. **쿠키/세션 공유**
   - 시스템 브라우저의 로그인 상태를 앱에서 활용
   - Chrome/Safari의 쿠키를 앱 WebView와 공유

3. **플랫폼별 최적화**
   - Android: Chrome Custom Tabs 사용
   - iOS: ASWebAuthenticationSession 사용
   - 웹: 기존 OAuth 2.0 흐름 유지

4. **보안**
   - OAuth 2.0 표준 준수
   - PKCE (Proof Key for Code Exchange) 적용
   - State 파라미터를 통한 CSRF 방지

5. **사용자 경험**
   - 로그인 과정이 매끄럽고 빠름
   - 앱 → 웹 → SNS → 웹 → 앱 흐름이 자연스러움
   - 로딩 인디케이터 및 에러 처리

### 3.2 비기능적 요구사항

- **호환성**: Android 5.0+ (API 21+), iOS 12.0+
- **성능**: SNS 로그인 완료까지 3초 이내
- **안정성**: 에러 발생 시 명확한 메시지 표시
- **테스트**: 6개 SNS 제공자 모두 테스트 완료

---

## 4. 개발 계획 (단계별 체크리스트)

### Phase 1: 서버 API 확장 (1일)

#### 1.1 앱 전용 SNS 로그인 엔드포인트 개발
- [ ] **파일**: `api/sns/app_login.php`
- [ ] 앱에서 전달된 파라미터 처리:
  - `platform`: android | ios | web
  - `provider`: naver | kakao | google | facebook | twitter | payco
  - `redirect_uri`: 앱 딥링크 URL (예: `hairwang://oauth/callback`)
  - `state`: CSRF 방지 토큰
- [ ] SNS별 OAuth 인증 URL 생성 및 반환
- [ ] 로그 기록 (g5_sns_api_logs)

#### 1.2 앱 전용 SNS 콜백 엔드포인트 개발
- [ ] **파일**: `api/sns/app_callback.php`
- [ ] SNS에서 전달된 `code` 파라미터 처리
- [ ] Access Token 발급
- [ ] 사용자 프로필 조회
- [ ] 자동 회원가입 또는 로그인 처리
- [ ] JWT 토큰 발급
- [ ] 앱 딥링크로 리디렉션 (토큰 전달)

#### 1.3 JWT 토큰 → 세션 변환 API 개발
- [ ] **파일**: `api/sns/token_to_session.php`
- [ ] 앱에서 JWT 토큰 전달
- [ ] 토큰 검증 및 사용자 확인
- [ ] 웹 세션 생성 (자동 로그인)
- [ ] 성공/실패 응답

#### 1.4 테스트 페이지 개발
- [ ] **파일**: `api/sns/test_app_login.html`
- [ ] Android/iOS 시뮬레이션 페이지
- [ ] 6개 SNS 제공자 테스트 버튼

---

### Phase 2: Android 앱 개발 (2일)

#### 2.1 Chrome Custom Tabs 라이브러리 추가
- [ ] **파일**: `app/build.gradle`
```gradle
dependencies {
    implementation "androidx.browser:browser:1.7.0"
}
```
- [ ] Gradle Sync 실행

#### 2.2 SnsAuthManager 클래스 개발
- [ ] **파일**: `app/src/main/java/mars/mobile/SnsAuthManager.java`
- [ ] Chrome Custom Tabs 초기화
- [ ] SNS 로그인 시작 메서드
  - `startSnsLogin(provider, callback)`
- [ ] OAuth URL 생성 (서버 API 호출)
- [ ] Custom Tabs 실행
- [ ] 딥링크 콜백 처리

#### 2.3 SnsCallbackActivity 개발
- [ ] **파일**: `app/src/main/java/mars/mobile/SnsCallbackActivity.java`
- [ ] SNS 콜백 딥링크 처리 (`hairwang://oauth/callback`)
- [ ] JWT 토큰 추출
- [ ] WebView에 토큰 전달 (JavaScript Interface)
- [ ] Activity 종료 및 메인 화면 복귀

#### 2.4 딥링크 설정
- [ ] **파일**: `app/src/main/AndroidManifest.xml`
```xml
<activity android:name=".SnsCallbackActivity">
    <intent-filter>
        <action android:name="android.intent.action.VIEW" />
        <category android:name="android.intent.category.DEFAULT" />
        <category android:name="android.intent.category.BROWSABLE" />
        <data
            android:scheme="hairwang"
            android:host="oauth"
            android:pathPrefix="/callback" />
    </intent-filter>
</activity>
```

#### 2.5 JavaScript Interface 확장
- [ ] **파일**: `app/src/main/java/mars/mobile/MainActivity.java`
- [ ] `@JavascriptInterface` 메서드 추가:
  - `startSnsLogin(provider)`: SNS 로그인 시작
  - `getSnsToken()`: 저장된 JWT 토큰 반환
- [ ] WebView에 인터페이스 등록
```java
webView.addJavascriptInterface(new SnsInterface(), "AndroidSns");
```

#### 2.6 웹 ↔ 앱 통신 구조 구축
- [ ] 앱 → 웹: JavaScript 함수 호출
  - `onSnsLoginSuccess(token)`: 로그인 성공 시 토큰 전달
  - `onSnsLoginError(error)`: 로그인 실패 시 에러 전달
- [ ] 웹 → 앱: JavaScript Interface 호출
  - `AndroidSns.startSnsLogin('naver')`

---

### Phase 3: iOS 앱 개발 (2일)

#### 3.1 ASWebAuthenticationSession 구현
- [ ] **파일**: `chamgosu/SNS/SnsAuthManager.swift` (신규)
- [ ] `import AuthenticationServices` 추가
- [ ] SNS 로그인 시작 메서드
```swift
func startSnsLogin(provider: String, completion: @escaping (String?, Error?) -> Void)
```
- [ ] OAuth URL 생성 (서버 API 호출)
- [ ] ASWebAuthenticationSession 실행
- [ ] 콜백 URL 처리 및 JWT 토큰 추출

#### 3.2 딥링크 URL Scheme 설정
- [ ] **파일**: `chamgosu/Info.plist`
```xml
<key>CFBundleURLTypes</key>
<array>
    <dict>
        <key>CFBundleURLSchemes</key>
        <array>
            <string>hairwang</string>
        </array>
        <key>CFBundleURLName</key>
        <string>com.hairwang.oauth</string>
    </dict>
</array>
```

#### 3.3 AppDelegate 딥링크 처리
- [ ] **파일**: `chamgosu/AppDelegate.swift`
- [ ] `application(_:open:options:)` 메서드 구현
- [ ] URL 파싱 및 토큰 추출
- [ ] 토큰 저장 (UserDefaults 또는 Keychain)
- [ ] Notification 발송 → WebView에서 수신

#### 3.4 JavaScript Bridge 확장
- [ ] **파일**: `chamgosu/WebView/SwiftWebVC.swift`
- [ ] WKScriptMessageHandler 확장
- [ ] 메시지 핸들러 추가:
  - `startSnsLogin`: SNS 로그인 시작
  - `getSnsToken`: 저장된 토큰 반환
- [ ] JavaScript → Swift 호출 구조
```javascript
window.webkit.messageHandlers.startSnsLogin.postMessage({provider: 'naver'});
```

#### 3.5 Swift → JavaScript 통신
- [ ] JavaScript 함수 호출 메서드 추가
```swift
func notifySnsLoginSuccess(token: String) {
    wkwebView.evaluateJavaScript("onSnsLoginSuccess('\(token)')")
}
```

---

### Phase 4: 웹 프론트엔드 개발 (1일)

#### 4.1 SNS 로그인 JavaScript 라이브러리 개발
- [ ] **파일**: `js/app_sns_login.js` (신규)
- [ ] 플랫폼 감지 함수
```javascript
function detectPlatform() {
    const ua = navigator.userAgent;
    if (ua.includes('hairwang-android')) return 'android';
    if (ua.includes('hairwang-ios')) return 'ios';
    return 'web';
}
```
- [ ] SNS 로그인 시작 함수
```javascript
function startSnsLogin(provider) {
    const platform = detectPlatform();
    if (platform === 'android') {
        AndroidSns.startSnsLogin(provider);
    } else if (platform === 'ios') {
        window.webkit.messageHandlers.startSnsLogin.postMessage({provider});
    } else {
        // 웹 기존 OAuth 흐름
        location.href = '/plugin/social/auth.php?provider=' + provider;
    }
}
```
- [ ] 콜백 함수 (앱에서 호출)
```javascript
function onSnsLoginSuccess(token) {
    // 서버에 토큰 전달하여 세션 생성
    fetch('/api/sns/token_to_session.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({token})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload(); // 로그인 후 새로고침
        }
    });
}
```

#### 4.2 로그인 페이지 수정
- [ ] **파일**: `member/login.php` 또는 해당 로그인 페이지
- [ ] SNS 로그인 버튼에 `app_sns_login.js` 적용
```html
<button onclick="startSnsLogin('naver')">네이버 로그인</button>
<button onclick="startSnsLogin('kakao')">카카오 로그인</button>
```
- [ ] 기존 OAuth 버튼과 통합

---

### Phase 5: 통합 테스트 (2일)

#### 5.1 Android 앱 테스트
- [ ] **네이버 로그인 테스트**
  - [ ] 로그인 버튼 클릭 → Chrome Custom Tabs 실행
  - [ ] 네이버 로그인 화면 표시
  - [ ] 자동 로그인 확인 (이미 로그인된 경우)
  - [ ] 로그인 완료 → 앱으로 복귀
  - [ ] WebView에서 로그인 상태 확인
- [ ] **카카오 로그인 테스트** (동일 절차)
- [ ] **구글 로그인 테스트** (동일 절차)
- [ ] **페이스북 로그인 테스트** (동일 절차)
- [ ] **트위터 로그인 테스트** (동일 절차)
- [ ] **페이코 로그인 테스트** (동일 절차)

#### 5.2 iOS 앱 테스트
- [ ] **네이버 로그인 테스트** (Android와 동일 절차)
- [ ] **카카오 로그인 테스트**
- [ ] **구글 로그인 테스트**
- [ ] **페이스북 로그인 테스트**
- [ ] **트위터 로그인 테스트**
- [ ] **페이코 로그인 테스트**
- [ ] **ASWebAuthenticationSession 권한 팝업 테스트**

#### 5.3 웹 테스트
- [ ] 웹 브라우저에서 기존 OAuth 흐름 정상 작동 확인
- [ ] 모바일 웹에서 로그인 테스트

#### 5.4 크로스 플랫폼 테스트
- [ ] Android 앱에서 로그인 → 웹에서 세션 확인
- [ ] iOS 앱에서 로그인 → 웹에서 세션 확인
- [ ] 웹에서 로그인 → 앱에서 세션 확인

#### 5.5 에러 케이스 테스트
- [ ] SNS 로그인 취소 시 처리
- [ ] 네트워크 오류 시 처리
- [ ] 토큰 만료 시 처리
- [ ] 권한 거부 시 처리

---

### Phase 6: 문서화 및 배포 (1일)

#### 6.1 개발 문서 작성
- [ ] **파일**: `SNS_APP_LOGIN_개발_가이드.md`
- [ ] API 명세서
- [ ] 앱 설정 가이드
- [ ] 딥링크 설정 가이드
- [ ] 트러블슈팅 가이드

#### 6.2 사용자 매뉴얼 작성
- [ ] **파일**: `SNS_APP_LOGIN_사용자_매뉴얼.md`
- [ ] 각 플랫폼별 사용 방법
- [ ] 스크린샷 포함

#### 6.3 배포
- [ ] 서버 API 배포 (프로덕션 환경)
- [ ] Android 앱 빌드 및 테스트
- [ ] iOS 앱 빌드 및 테스트
- [ ] 스토어 제출 준비 (선택)

---

## 5. 세부 구현 가이드

### 5.1 Android: Chrome Custom Tabs 구현

#### SnsAuthManager.java 예시 코드:

```java
package mars.mobile;

import android.app.Activity;
import android.content.Intent;
import android.net.Uri;
import androidx.browser.customtabs.CustomTabsIntent;
import androidx.browser.customtabs.CustomTabsSession;

import org.json.JSONObject;
import java.io.BufferedReader;
import java.io.InputStreamReader;
import java.net.HttpURLConnection;
import java.net.URL;

public class SnsAuthManager {

    private Activity activity;
    private String apiBaseUrl = AppConfig.SERVER_BASE_URL + "/api/sns";

    public SnsAuthManager(Activity activity) {
        this.activity = activity;
    }

    /**
     * SNS 로그인 시작
     * @param provider SNS 제공자 (naver, kakao, google 등)
     */
    public void startSnsLogin(String provider) {
        // 백그라운드에서 서버 API 호출하여 OAuth URL 가져오기
        new Thread(() -> {
            try {
                String authUrl = getAuthUrlFromServer(provider);

                // Chrome Custom Tabs 실행 (UI 스레드에서)
                activity.runOnUiThread(() -> {
                    launchCustomTabs(authUrl);
                });

            } catch (Exception e) {
                e.printStackTrace();
                activity.runOnUiThread(() -> {
                    // 에러 처리
                    MainActivity.showToast("SNS 로그인 오류: " + e.getMessage());
                });
            }
        }).start();
    }

    /**
     * 서버로부터 OAuth URL 가져오기
     */
    private String getAuthUrlFromServer(String provider) throws Exception {
        String urlString = apiBaseUrl + "/app_login.php";
        String redirectUri = "hairwang://oauth/callback";
        String state = generateRandomState();

        // POST 요청
        URL url = new URL(urlString);
        HttpURLConnection conn = (HttpURLConnection) url.openConnection();
        conn.setRequestMethod("POST");
        conn.setRequestProperty("Content-Type", "application/x-www-form-urlencoded");
        conn.setRequestProperty("X-API-Key", "YOUR_API_KEY");
        conn.setRequestProperty("X-API-Secret", "YOUR_API_SECRET");
        conn.setDoOutput(true);

        String params = "platform=android&provider=" + provider +
                       "&redirect_uri=" + redirectUri + "&state=" + state;
        conn.getOutputStream().write(params.getBytes());

        // 응답 읽기
        BufferedReader reader = new BufferedReader(
            new InputStreamReader(conn.getInputStream())
        );
        StringBuilder response = new StringBuilder();
        String line;
        while ((line = reader.readLine()) != null) {
            response.append(line);
        }
        reader.close();

        // JSON 파싱
        JSONObject json = new JSONObject(response.toString());
        if (json.getBoolean("success")) {
            return json.getJSONObject("data").getString("auth_url");
        } else {
            throw new Exception(json.getJSONObject("error").getString("message"));
        }
    }

    /**
     * Chrome Custom Tabs 실행
     */
    private void launchCustomTabs(String url) {
        CustomTabsIntent.Builder builder = new CustomTabsIntent.Builder();
        CustomTabsIntent customTabsIntent = builder.build();
        customTabsIntent.launchUrl(activity, Uri.parse(url));
    }

    /**
     * 랜덤 State 생성 (CSRF 방지)
     */
    private String generateRandomState() {
        return java.util.UUID.randomUUID().toString();
    }
}
```

#### SnsCallbackActivity.java 예시 코드:

```java
package mars.mobile;

import android.app.Activity;
import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;

public class SnsCallbackActivity extends Activity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Intent에서 데이터 가져오기
        Intent intent = getIntent();
        Uri data = intent.getData();

        if (data != null) {
            // URL에서 토큰 추출
            String token = data.getQueryParameter("token");
            String error = data.getQueryParameter("error");

            if (token != null) {
                // 성공: 토큰 저장 및 MainActivity에 전달
                MainActivity.onSnsLoginSuccess(token);
            } else if (error != null) {
                // 실패: 에러 처리
                MainActivity.onSnsLoginError(error);
            }
        }

        // Activity 종료 및 메인 화면 복귀
        finish();
    }
}
```

#### MainActivity.java에 추가할 메서드:

```java
public class MainActivity extends AppCompatActivity {

    private static String snsToken = null;

    /**
     * JavaScript Interface: SNS 로그인 시작
     */
    public class SnsInterface {
        @JavascriptInterface
        public void startSnsLogin(String provider) {
            runOnUiThread(() -> {
                SnsAuthManager authManager = new SnsAuthManager(MainActivity.this);
                authManager.startSnsLogin(provider);
            });
        }

        @JavascriptInterface
        public String getSnsToken() {
            return snsToken;
        }
    }

    /**
     * SNS 로그인 성공 콜백
     */
    public static void onSnsLoginSuccess(String token) {
        snsToken = token;

        // WebView에 JavaScript 함수 호출
        if (webView != null) {
            webView.post(() -> {
                webView.evaluateJavascript(
                    "onSnsLoginSuccess('" + token + "');",
                    null
                );
            });
        }
    }

    /**
     * SNS 로그인 실패 콜백
     */
    public static void onSnsLoginError(String error) {
        if (webView != null) {
            webView.post(() -> {
                webView.evaluateJavascript(
                    "onSnsLoginError('" + error + "');",
                    null
                );
            });
        }
    }
}
```

---

### 5.2 iOS: ASWebAuthenticationSession 구현

#### SnsAuthManager.swift 신규 파일:

```swift
import Foundation
import AuthenticationServices

class SnsAuthManager: NSObject {

    private let apiBaseUrl = AppConfig.serverBaseURL + "/api/sns"
    private var authSession: ASWebAuthenticationSession?

    /**
     * SNS 로그인 시작
     * @param provider SNS 제공자 (naver, kakao, google 등)
     * @param presentationContext 현재 Window
     * @param completion 완료 핸들러 (token, error)
     */
    func startSnsLogin(
        provider: String,
        presentationContext: ASWebAuthenticationPresentationContextProviding,
        completion: @escaping (String?, Error?) -> Void
    ) {
        // 1. 서버로부터 OAuth URL 가져오기
        getAuthUrlFromServer(provider: provider) { [weak self] authUrl, error in
            guard let self = self else { return }

            if let error = error {
                completion(nil, error)
                return
            }

            guard let authUrl = authUrl else {
                completion(nil, NSError(domain: "SnsAuth", code: -1,
                    userInfo: [NSLocalizedDescriptionKey: "Auth URL is nil"]))
                return
            }

            // 2. ASWebAuthenticationSession 실행
            DispatchQueue.main.async {
                self.launchAuthSession(
                    authUrl: authUrl,
                    presentationContext: presentationContext,
                    completion: completion
                )
            }
        }
    }

    /**
     * 서버로부터 OAuth URL 가져오기
     */
    private func getAuthUrlFromServer(
        provider: String,
        completion: @escaping (String?, Error?) -> Void
    ) {
        let urlString = apiBaseUrl + "/app_login.php"
        guard let url = URL(string: urlString) else {
            completion(nil, NSError(domain: "SnsAuth", code: -1,
                userInfo: [NSLocalizedDescriptionKey: "Invalid URL"]))
            return
        }

        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("application/x-www-form-urlencoded", forHTTPHeaderField: "Content-Type")
        request.setValue("YOUR_API_KEY", forHTTPHeaderField: "X-API-Key")
        request.setValue("YOUR_API_SECRET", forHTTPHeaderField: "X-API-Secret")

        let redirectUri = "hairwang://oauth/callback"
        let state = UUID().uuidString
        let params = "platform=ios&provider=\(provider)&redirect_uri=\(redirectUri)&state=\(state)"
        request.httpBody = params.data(using: .utf8)

        URLSession.shared.dataTask(with: request) { data, response, error in
            if let error = error {
                completion(nil, error)
                return
            }

            guard let data = data else {
                completion(nil, NSError(domain: "SnsAuth", code: -1,
                    userInfo: [NSLocalizedDescriptionKey: "No data"]))
                return
            }

            do {
                let json = try JSONSerialization.jsonObject(with: data) as? [String: Any]
                if let success = json?["success"] as? Bool, success,
                   let dataDict = json?["data"] as? [String: Any],
                   let authUrl = dataDict["auth_url"] as? String {
                    completion(authUrl, nil)
                } else if let errorDict = json?["error"] as? [String: Any],
                          let message = errorDict["message"] as? String {
                    completion(nil, NSError(domain: "SnsAuth", code: -1,
                        userInfo: [NSLocalizedDescriptionKey: message]))
                }
            } catch {
                completion(nil, error)
            }
        }.resume()
    }

    /**
     * ASWebAuthenticationSession 실행
     */
    private func launchAuthSession(
        authUrl: String,
        presentationContext: ASWebAuthenticationPresentationContextProviding,
        completion: @escaping (String?, Error?) -> Void
    ) {
        guard let url = URL(string: authUrl) else {
            completion(nil, NSError(domain: "SnsAuth", code: -1,
                userInfo: [NSLocalizedDescriptionKey: "Invalid auth URL"]))
            return
        }

        // callbackURLScheme: 앱의 URL Scheme (hairwang)
        authSession = ASWebAuthenticationSession(
            url: url,
            callbackURLScheme: "hairwang"
        ) { callbackURL, error in
            if let error = error {
                completion(nil, error)
                return
            }

            guard let callbackURL = callbackURL else {
                completion(nil, NSError(domain: "SnsAuth", code: -1,
                    userInfo: [NSLocalizedDescriptionKey: "No callback URL"]))
                return
            }

            // URL에서 토큰 추출
            if let token = self.extractToken(from: callbackURL) {
                completion(token, nil)
            } else {
                completion(nil, NSError(domain: "SnsAuth", code: -1,
                    userInfo: [NSLocalizedDescriptionKey: "Token not found"]))
            }
        }

        authSession?.presentationContextProvider = presentationContext
        authSession?.prefersEphemeralWebBrowserSession = false // 쿠키 공유 활성화
        authSession?.start()
    }

    /**
     * 콜백 URL에서 토큰 추출
     */
    private func extractToken(from url: URL) -> String? {
        guard let components = URLComponents(url: url, resolvingAgainstBaseURL: false),
              let queryItems = components.queryItems else {
            return nil
        }

        for item in queryItems {
            if item.name == "token" {
                return item.value
            }
        }

        return nil
    }
}

// ASWebAuthenticationPresentationContextProviding 구현
extension SwiftWebVC: ASWebAuthenticationPresentationContextProviding {
    public func presentationAnchor(for session: ASWebAuthenticationSession) -> ASPresentationAnchor {
        return self.view.window!
    }
}
```

#### SwiftWebVC.swift에 추가할 메서드:

```swift
class SwiftWebVC: UIViewController {

    private var snsAuthManager: SnsAuthManager?

    // WKScriptMessageHandler 확장
    func setupSnsMessageHandlers() {
        let contentController = wkwebView.configuration.userContentController

        // SNS 로그인 시작 핸들러
        contentController.add(self, name: "startSnsLogin")

        snsAuthManager = SnsAuthManager()
    }

    // 메시지 핸들러 처리
    public func userContentController(
        _ userContentController: WKUserContentController,
        didReceive message: WKScriptMessage
    ) {
        if message.name == "startSnsLogin" {
            guard let body = message.body as? [String: Any],
                  let provider = body["provider"] as? String else {
                return
            }

            // SNS 로그인 시작
            snsAuthManager?.startSnsLogin(
                provider: provider,
                presentationContext: self
            ) { [weak self] token, error in
                DispatchQueue.main.async {
                    if let token = token {
                        self?.notifySnsLoginSuccess(token: token)
                    } else if let error = error {
                        self?.notifySnsLoginError(error: error.localizedDescription)
                    }
                }
            }
        }
    }

    /**
     * WebView에 로그인 성공 알림
     */
    private func notifySnsLoginSuccess(token: String) {
        SwiftWebVC.wkwebView.evaluateJavaScript(
            "onSnsLoginSuccess('\(token)');"
        ) { result, error in
            if let error = error {
                print("Error calling JavaScript: \(error)")
            }
        }
    }

    /**
     * WebView에 로그인 실패 알림
     */
    private func notifySnsLoginError(error: String) {
        SwiftWebVC.wkwebView.evaluateJavaScript(
            "onSnsLoginError('\(error)');"
        )
    }
}
```

---

### 5.3 서버 API 구현

#### api/sns/app_login.php:

```php
<?php
/**
 * 앱 전용 SNS 로그인 시작 API
 * OAuth 인증 URL 생성 및 반환
 */

include_once dirname(__FILE__).'/../common.php';

// 요청 메서드 확인
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Only POST method is allowed', 405, 'METHOD_NOT_ALLOWED');
}

// API 인증 확인
$auth = ApiAuth::getAuthFromHeader();
if (!$auth) {
    ApiResponse::error('API authentication required', 401, 'AUTH_REQUIRED');
}

$api_key_info = ApiAuth::verifyApiKey($auth['api_key'], $auth['api_secret']);
if (!$api_key_info) {
    ApiResponse::error('Invalid API key or secret', 401, 'INVALID_API_KEY');
}

// 파라미터 가져오기
$platform = isset($_POST['platform']) ? trim($_POST['platform']) : '';
$provider = isset($_POST['provider']) ? trim($_POST['provider']) : '';
$redirect_uri = isset($_POST['redirect_uri']) ? trim($_POST['redirect_uri']) : '';
$state = isset($_POST['state']) ? trim($_POST['state']) : '';

// 유효성 검사
$errors = array();

if (!in_array($platform, ['android', 'ios', 'web'])) {
    $errors['platform'] = 'Invalid platform';
}

if (!in_array($provider, ['naver', 'kakao', 'google', 'facebook', 'twitter', 'payco'])) {
    $errors['provider'] = 'Invalid provider';
}

if (empty($redirect_uri)) {
    $errors['redirect_uri'] = 'Redirect URI is required';
}

if (empty($state)) {
    $errors['state'] = 'State parameter is required';
}

if (!empty($errors)) {
    ApiResponse::validationError($errors);
}

try {
    // SNS OAuth 인증 URL 생성
    $auth_url = generate_sns_oauth_url($provider, $redirect_uri, $state);

    // 성공 응답
    ApiResponse::success([
        'auth_url' => $auth_url,
        'provider' => $provider,
        'state' => $state
    ]);

} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500, 'INTERNAL_ERROR');
}

/**
 * SNS OAuth 인증 URL 생성
 */
function generate_sns_oauth_url($provider, $redirect_uri, $state) {
    // 기존 SNS 플러그인의 설정 불러오기
    $sns_config = get_sns_config($provider);

    $params = [
        'client_id' => $sns_config['client_id'],
        'redirect_uri' => $redirect_uri,
        'response_type' => 'code',
        'state' => $state
    ];

    // 제공자별 OAuth URL
    $oauth_urls = [
        'naver' => 'https://nid.naver.com/oauth2.0/authorize',
        'kakao' => 'https://kauth.kakao.com/oauth/authorize',
        'google' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'facebook' => 'https://www.facebook.com/v12.0/dialog/oauth',
        'twitter' => 'https://twitter.com/i/oauth2/authorize',
        'payco' => 'https://id.payco.com/oauth2.0/authorize'
    ];

    // 제공자별 추가 파라미터
    switch ($provider) {
        case 'google':
            $params['scope'] = 'openid email profile';
            $params['access_type'] = 'offline';
            break;
        case 'facebook':
            $params['scope'] = 'email,public_profile';
            break;
        case 'naver':
            $params['scope'] = 'name,email';
            break;
    }

    $base_url = $oauth_urls[$provider];
    return $base_url . '?' . http_build_query($params);
}

/**
 * SNS 설정 불러오기
 */
function get_sns_config($provider) {
    // 실제 구현: 데이터베이스나 설정 파일에서 client_id, client_secret 불러오기
    // 여기서는 예시로 하드코딩
    return [
        'client_id' => 'YOUR_' . strtoupper($provider) . '_CLIENT_ID',
        'client_secret' => 'YOUR_' . strtoupper($provider) . '_CLIENT_SECRET'
    ];
}
?>
```

#### api/sns/app_callback.php:

```php
<?php
/**
 * 앱 전용 SNS 로그인 콜백 API
 * SNS로부터 authorization code 받아서 처리
 */

include_once dirname(__FILE__).'/../common.php';

// GET 요청 허용 (OAuth 콜백)
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Only GET/POST method is allowed', 405, 'METHOD_NOT_ALLOWED');
}

// 파라미터 가져오기
$code = isset($_GET['code']) ? $_GET['code'] : (isset($_POST['code']) ? $_POST['code'] : '');
$state = isset($_GET['state']) ? $_GET['state'] : (isset($_POST['state']) ? $_POST['state'] : '');
$provider = isset($_GET['provider']) ? $_GET['provider'] : (isset($_POST['provider']) ? $_POST['provider'] : '');
$error = isset($_GET['error']) ? $_GET['error'] : '';

// 에러 처리
if (!empty($error)) {
    // 앱 딥링크로 리디렉션 (에러 포함)
    $redirect_url = "hairwang://oauth/callback?error=" . urlencode($error);
    header("Location: " . $redirect_url);
    exit;
}

// 유효성 검사
if (empty($code) || empty($state) || empty($provider)) {
    $redirect_url = "hairwang://oauth/callback?error=missing_parameters";
    header("Location: " . $redirect_url);
    exit;
}

try {
    // 1. Access Token 발급
    $access_token = exchange_code_for_token($provider, $code);

    // 2. 사용자 프로필 조회
    $user_profile = get_user_profile($provider, $access_token);

    // 3. 자동 회원가입 또는 로그인 처리
    $member = process_sns_login($provider, $user_profile);

    // 4. JWT 토큰 발급
    $jwt_token = generate_jwt_token($member);

    // 5. 앱 딥링크로 리디렉션 (토큰 포함)
    $redirect_url = "hairwang://oauth/callback?token=" . urlencode($jwt_token);
    header("Location: " . $redirect_url);
    exit;

} catch (Exception $e) {
    // 에러 발생 시 앱으로 리디렉션
    $redirect_url = "hairwang://oauth/callback?error=" . urlencode($e->getMessage());
    header("Location: " . $redirect_url);
    exit;
}

/**
 * Authorization Code를 Access Token으로 교환
 */
function exchange_code_for_token($provider, $code) {
    $sns_config = get_sns_config($provider);

    // 제공자별 토큰 엔드포인트
    $token_urls = [
        'naver' => 'https://nid.naver.com/oauth2.0/token',
        'kakao' => 'https://kauth.kakao.com/oauth/token',
        'google' => 'https://oauth2.googleapis.com/token',
        // ... 다른 제공자들
    ];

    $params = [
        'grant_type' => 'authorization_code',
        'client_id' => $sns_config['client_id'],
        'client_secret' => $sns_config['client_secret'],
        'code' => $code,
        'redirect_uri' => 'hairwang://oauth/callback' // 동일한 redirect_uri
    ];

    // HTTP POST 요청
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_urls[$provider]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    if (isset($data['access_token'])) {
        return $data['access_token'];
    } else {
        throw new Exception('Failed to get access token');
    }
}

/**
 * 사용자 프로필 조회
 */
function get_user_profile($provider, $access_token) {
    // 제공자별 프로필 API 엔드포인트
    $profile_urls = [
        'naver' => 'https://openapi.naver.com/v1/nid/me',
        'kakao' => 'https://kapi.kakao.com/v2/user/me',
        'google' => 'https://www.googleapis.com/oauth2/v2/userinfo',
        // ... 다른 제공자들
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $profile_urls[$provider]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $profile = json_decode($response, true);

    // 제공자별 프로필 정규화
    return normalize_profile($provider, $profile);
}

/**
 * SNS 로그인 처리 (자동 회원가입 포함)
 */
function process_sns_login($provider, $profile) {
    global $g5;

    $identifier = $provider . '_' . $profile['id'];

    // 기존 회원 확인
    $sql = "SELECT * FROM {$g5['member_table']} WHERE mb_id = '{$identifier}'";
    $member = sql_fetch($sql);

    if (!$member) {
        // 자동 회원가입
        $member = auto_register_member($provider, $profile);
    }

    return $member;
}

/**
 * JWT 토큰 발급
 */
function generate_jwt_token($member) {
    // JWT 라이브러리 사용 (예: Firebase JWT)
    require_once G5_LIB_PATH . '/jwt/JWT.php';

    $payload = [
        'iss' => G5_DOMAIN,
        'iat' => time(),
        'exp' => time() + (60 * 60 * 24), // 24시간
        'mb_id' => $member['mb_id'],
        'mb_name' => $member['mb_name']
    ];

    $secret = 'YOUR_JWT_SECRET_KEY'; // 보안: 설정 파일에서 관리

    return \Firebase\JWT\JWT::encode($payload, $secret, 'HS256');
}
?>
```

#### api/sns/token_to_session.php:

```php
<?php
/**
 * JWT 토큰을 웹 세션으로 변환
 * 앱에서 받은 토큰으로 웹 자동 로그인
 */

include_once './_common.php'; // 그누보드 공통 파일

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Only POST allowed']);
    exit;
}

// JSON 요청 파싱
$input = json_decode(file_get_contents('php://input'), true);
$token = isset($input['token']) ? $input['token'] : '';

if (empty($token)) {
    echo json_encode(['success' => false, 'error' => 'Token is required']);
    exit;
}

try {
    // JWT 토큰 검증
    require_once G5_LIB_PATH . '/jwt/JWT.php';
    $secret = 'YOUR_JWT_SECRET_KEY';

    $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($secret, 'HS256'));

    $mb_id = $decoded->mb_id;

    // 회원 정보 조회
    $member = get_member($mb_id);

    if (!$member) {
        throw new Exception('Member not found');
    }

    // 그누보드 세션 생성 (자동 로그인)
    set_session('ss_mb_id', $member['mb_id']);
    set_session('ss_mb_key', md5($member['mb_datetime']));

    // 성공 응답
    echo json_encode([
        'success' => true,
        'data' => [
            'mb_id' => $member['mb_id'],
            'mb_name' => $member['mb_name'],
            'mb_nick' => $member['mb_nick']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
```

---

### 5.4 웹 JavaScript 구현

#### js/app_sns_login.js (신규 파일):

```javascript
/**
 * 앱 SNS 로그인 통합 JavaScript 라이브러리
 * 플랫폼 감지 및 자동 분기 처리
 */

(function() {
    'use strict';

    /**
     * 플랫폼 감지
     */
    window.detectPlatform = function() {
        const ua = navigator.userAgent;
        if (ua.includes('hairwang-android')) return 'android';
        if (ua.includes('hairwang-ios')) return 'ios';
        return 'web';
    };

    /**
     * SNS 로그인 시작
     * @param {string} provider - SNS 제공자 (naver, kakao, google, facebook, twitter, payco)
     */
    window.startSnsLogin = function(provider) {
        const platform = detectPlatform();

        console.log('[SNS Login] Platform:', platform, 'Provider:', provider);

        if (platform === 'android') {
            // Android: JavascriptInterface 호출
            if (typeof AndroidSns !== 'undefined') {
                AndroidSns.startSnsLogin(provider);
            } else {
                console.error('[SNS Login] AndroidSns interface not found');
                alert('앱 버전이 너무 오래되었습니다. 업데이트 해주세요.');
            }
        } else if (platform === 'ios') {
            // iOS: WKWebView Message Handler 호출
            if (window.webkit && window.webkit.messageHandlers && window.webkit.messageHandlers.startSnsLogin) {
                window.webkit.messageHandlers.startSnsLogin.postMessage({
                    provider: provider
                });
            } else {
                console.error('[SNS Login] iOS message handler not found');
                alert('앱 버전이 너무 오래되었습니다. 업데이트 해주세요.');
            }
        } else {
            // 웹: 기존 OAuth 흐름
            const currentUrl = encodeURIComponent(window.location.href);
            window.location.href = '/plugin/social/auth.php?provider=' + provider + '&return_url=' + currentUrl;
        }
    };

    /**
     * SNS 로그인 성공 콜백 (앱에서 호출)
     * @param {string} token - JWT 토큰
     */
    window.onSnsLoginSuccess = function(token) {
        console.log('[SNS Login] Success, token:', token);

        // 로딩 인디케이터 표시
        showLoadingIndicator('로그인 중...');

        // 서버에 토큰 전달하여 세션 생성
        fetch('/api/sns/token_to_session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ token: token })
        })
        .then(response => response.json())
        .then(data => {
            hideLoadingIndicator();

            if (data.success) {
                console.log('[SNS Login] Session created:', data.data);

                // 로그인 성공 메시지
                alert('로그인되었습니다.');

                // 페이지 새로고침 (로그인 상태 반영)
                window.location.reload();
            } else {
                console.error('[SNS Login] Session creation failed:', data.error);
                alert('로그인 처리 중 오류가 발생했습니다: ' + data.error);
            }
        })
        .catch(error => {
            hideLoadingIndicator();
            console.error('[SNS Login] Network error:', error);
            alert('네트워크 오류가 발생했습니다. 다시 시도해주세요.');
        });
    };

    /**
     * SNS 로그인 실패 콜백 (앱에서 호출)
     * @param {string} error - 에러 메시지
     */
    window.onSnsLoginError = function(error) {
        console.error('[SNS Login] Error:', error);

        if (error === 'user_cancelled') {
            // 사용자가 취소한 경우: 조용히 처리
            console.log('[SNS Login] User cancelled');
        } else {
            // 다른 오류: 사용자에게 표시
            alert('SNS 로그인 중 오류가 발생했습니다: ' + error);
        }
    };

    /**
     * 로딩 인디케이터 표시
     */
    function showLoadingIndicator(message) {
        let indicator = document.getElementById('sns-loading-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'sns-loading-indicator';
            indicator.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            `;
            indicator.innerHTML = `
                <div style="
                    background: white;
                    padding: 20px 40px;
                    border-radius: 8px;
                    text-align: center;
                ">
                    <div style="
                        border: 4px solid #f3f3f3;
                        border-top: 4px solid #3498db;
                        border-radius: 50%;
                        width: 40px;
                        height: 40px;
                        animation: spin 1s linear infinite;
                        margin: 0 auto 10px;
                    "></div>
                    <div id="sns-loading-message">${message}</div>
                </div>
            `;
            document.body.appendChild(indicator);

            // CSS 애니메이션 추가
            const style = document.createElement('style');
            style.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
        }
        indicator.style.display = 'flex';
        document.getElementById('sns-loading-message').textContent = message;
    }

    /**
     * 로딩 인디케이터 숨김
     */
    function hideLoadingIndicator() {
        const indicator = document.getElementById('sns-loading-indicator');
        if (indicator) {
            indicator.style.display = 'none';
        }
    }

    console.log('[SNS Login] Library loaded, platform:', detectPlatform());
})();
```

---

## 6. 테스트 계획

### 6.1 단위 테스트

#### 서버 API 테스트:
- [ ] `app_login.php`: 각 SNS 제공자별 OAuth URL 생성 확인
- [ ] `app_callback.php`: Access Token 발급 및 사용자 프로필 조회 확인
- [ ] `token_to_session.php`: JWT 토큰 검증 및 세션 생성 확인

#### Android 테스트:
- [ ] Chrome Custom Tabs 실행 확인
- [ ] JavaScript Interface 동작 확인
- [ ] 딥링크 처리 확인

#### iOS 테스트:
- [ ] ASWebAuthenticationSession 실행 확인
- [ ] WKScriptMessageHandler 동작 확인
- [ ] URL Scheme 처리 확인

### 6.2 통합 테스트

#### 전체 흐름 테스트 (각 SNS별):
1. 앱 로그인 페이지에서 "네이버 로그인" 버튼 클릭
2. Chrome Custom Tabs (Android) 또는 ASWebAuthenticationSession (iOS) 실행
3. 네이버 로그인 화면 표시
4. 이미 로그인된 경우: "계속하시겠습니까?" 팝업 → "예" 클릭
5. 로그인되지 않은 경우: 아이디/비밀번호 입력 → 로그인
6. 권한 동의 (처음 한 번만)
7. 앱으로 복귀 (딥링크)
8. WebView에서 자동 로그인 확인
9. 사용자 프로필 표시 확인

#### 에러 케이스 테스트:
- [ ] 사용자가 로그인 중 취소한 경우
- [ ] 네트워크 오류 발생 시
- [ ] 토큰 검증 실패 시
- [ ] 권한 거부 시

### 6.3 성능 테스트
- [ ] 로그인 완료까지 소요 시간 측정 (목표: 3초 이내)
- [ ] 쿠키 공유 동작 확인 (이미 로그인된 경우 즉시 처리)

### 6.4 보안 테스트
- [ ] CSRF 공격 방어 확인 (State 파라미터)
- [ ] 중간자 공격 방어 확인 (HTTPS 강제)
- [ ] JWT 토큰 위조 시도 차단 확인

---

## 7. 개발 우선순위

### 높음 (필수)
1. ✅ 서버 API 개발 (Phase 1)
2. ✅ Android Chrome Custom Tabs 구현 (Phase 2)
3. ✅ iOS ASWebAuthenticationSession 구현 (Phase 3)
4. ✅ 웹 JavaScript 라이브러리 (Phase 4)

### 중간 (권장)
5. 에러 처리 및 사용자 피드백 강화
6. 로딩 인디케이터 UI 개선
7. 로그 시스템 강화

### 낮음 (선택)
8. 통계 대시보드 개발
9. A/B 테스트 기능
10. 다국어 지원

---

## 8. 참고 자료

### 공식 문서:
- **Android**: [Chrome Custom Tabs](https://developer.chrome.com/docs/android/custom-tabs/)
- **iOS**: [ASWebAuthenticationSession](https://developer.apple.com/documentation/authenticationservices/aswebauthenticationsession)
- **OAuth 2.0**: [RFC 6749](https://datatracker.ietf.org/doc/html/rfc6749)

### 라이브러리:
- Android: `androidx.browser:browser:1.7.0`
- iOS: `AuthenticationServices` (내장 프레임워크)
- JWT: [Firebase JWT PHP](https://github.com/firebase/php-jwt)

---

## 9. 예상 일정

| Phase | 작업 내용 | 예상 시간 | 담당자 |
|-------|----------|----------|--------|
| Phase 1 | 서버 API 확장 | 1일 | Backend |
| Phase 2 | Android 앱 개발 | 2일 | Android |
| Phase 3 | iOS 앱 개발 | 2일 | iOS |
| Phase 4 | 웹 프론트엔드 | 1일 | Frontend |
| Phase 5 | 통합 테스트 | 2일 | QA |
| Phase 6 | 문서화 및 배포 | 1일 | All |
| **총계** | | **9일** | |

---

## 10. 리스크 및 대응 방안

### 리스크 1: Chrome Custom Tabs가 설치되지 않은 Android 기기
**대응**: Fallback으로 일반 WebView 사용 (쿠키 공유는 제한적)

### 리스크 2: iOS 12 미만 기기
**대응**: SFSafariViewController로 Fallback

### 리스크 3: SNS API 변경
**대응**: 정기적인 SNS API 모니터링 및 업데이트 계획 수립

### 리스크 4: 딥링크 충돌
**대응**: 고유한 URL Scheme 사용 (`hairwang://`)

---

## 11. 완료 기준 (Definition of Done)

- [x] 서버 API 3개 엔드포인트 개발 완료
- [ ] Android 앱에서 6개 SNS 제공자 모두 로그인 성공
- [ ] iOS 앱에서 6개 SNS 제공자 모두 로그인 성공
- [ ] 웹에서 기존 OAuth 흐름 정상 작동
- [ ] 자동 로그인 (쿠키 공유) 동작 확인
- [ ] 에러 케이스 모두 테스트 완료
- [ ] 개발 문서 작성 완료
- [ ] 코드 리뷰 완료
- [ ] 프로덕션 배포 완료

---

**작성자**: Claude Code
**최종 수정일**: 2026-01-19
**버전**: 1.0
