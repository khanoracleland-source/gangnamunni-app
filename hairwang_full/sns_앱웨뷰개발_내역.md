# SNS 앱 WebView 개발 내역

**프로젝트**: 헤어왕 앱 SNS 자동 로그인 구현
**개발 시작일**: 2026-01-19
**개발자**: Claude Code

---

## 개발 목표

3개 플랫폼(웹, Android, iOS)에서 SNS 자동 로그인 지원:
- Android: Chrome Custom Tabs 사용
- iOS: ASWebAuthenticationSession 사용
- 웹: 기존 OAuth 유지

---

## 개발 진행 상황

### ✅ Phase 0: 사전 분석 및 계획 수립 (완료)
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내역:
1. ✅ 기존 코드베이스 분석 완료
   - Android 앱: WKWebView 기반 구현 확인
   - iOS 앱: WKWebView 기반 구현 확인
   - 서버: SNS API 6개 제공자 지원 확인

2. ✅ 기술 스택 결정
   - Android: Chrome Custom Tabs (androidx.browser:browser:1.7.0)
   - iOS: ASWebAuthenticationSession (iOS 12+)
   - 서버: PHP + JWT

3. ✅ 개발 문서 작성
   - 파일: `sns_앱웨뷰개발분석.md` (111개 체크리스트)

---

## Phase 1: 서버 API 확장

### ✅ 1.1 앱 전용 SNS 로그인 엔드포인트 개발
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내용:
- ✅ `api/sns/app_login.php` 생성 완료 (400줄)
- ✅ API 인증 구현
- ✅ 6개 SNS OAuth URL 생성
- ✅ 상세 주석 작성

### ✅ 1.2 앱 전용 SNS 콜백 엔드포인트 개발
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내용:
- ✅ `api/sns/app_callback.php` 생성 완료 (650줄)
- ✅ Authorization Code → Access Token 교환
- ✅ 6개 SNS 제공자 프로필 조회 및 정규화
- ✅ 자동 회원가입 로직 구현
- ✅ JWT 토큰 발급
- ✅ 앱 딥링크 리디렉션
- ✅ 상세 주석 작성

### ✅ 1.3 JWT 토큰 → 세션 변환 API 개발
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내용:
- ✅ `api/sns/token_to_session.php` 생성 완료 (400줄)
- ✅ JWT 토큰 검증 및 디코딩
- ✅ 그누보드 세션 생성 로직 구현
- ✅ 로그인 포인트 지급
- ✅ 자동 로그인 쿠키 설정
- ✅ CORS 헤더 설정
- ✅ 상세 주석 작성

---

## ✅ Phase 1 완료: 서버 API 확장
**총 작업 시간**: 약 1시간
**생성된 파일**: 3개 (app_login.php, app_callback.php, token_to_session.php)
**총 코드 라인**: 약 1,450줄

---

## Phase 2: Android 앱 개발

### ✅ 2.1 Chrome Custom Tabs 라이브러리 추가
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내용:
- ✅ `app/build.gradle` 수정 완료
- ✅ Chrome Custom Tabs 라이브러리 추가 (androidx.browser:browser:1.7.0)

### ✅ 2.2 SnsAuthManager.java 개발
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내용:
- ✅ `SnsAuthManager.java` 생성 완료 (450줄)
- ✅ Chrome Custom Tabs 통합
- ✅ 서버 API 호출 로직 (app_login.php)
- ✅ OAuth URL 생성 및 Custom Tabs 실행
- ✅ CSRF 보안 처리 (state 파라미터)
- ✅ OnAuthCompleteListener 인터페이스 정의
- ✅ 상세 주석 작성

### ✅ 2.3 SnsCallbackActivity.java 개발
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내용:
- ✅ `SnsCallbackActivity.java` 생성 완료 (200줄)
- ✅ 딥링크 콜백 처리 (hairwang://oauth/callback)
- ✅ JWT 토큰 추출 및 검증
- ✅ MainActivity로 결과 전달
- ✅ 투명 테마 적용 (사용자 경험 향상)
- ✅ 상세 주석 작성

### ✅ 2.4 AndroidManifest.xml 딥링크 설정
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내용:
- ✅ `AndroidManifest.xml` 수정 완료
- ✅ SnsCallbackActivity 등록
- ✅ intent-filter 설정 (hairwang://oauth/callback)
- ✅ exported="true" 설정 (외부 앱에서 호출 가능)
- ✅ launchMode="singleTask" 설정 (인스턴스 재사용)

### ✅ 2.5 MainActivity.java JavaScript Interface 추가
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내용:
- ✅ MainActivity에 static 콜백 메서드 추가:
  - `onSnsLoginSuccess(String token)` - SNS 로그인 성공 처리
  - `onSnsLoginError(String error)` - SNS 로그인 실패 처리
  - `getSnsToken()` - 저장된 JWT 토큰 조회
  - `clearSnsToken()` - JWT 토큰 삭제 (로그아웃)
- ✅ WebAppInterface에 JavaScript Interface 메서드 추가:
  - `@JavascriptInterface startSnsLogin(String provider)` - JavaScript에서 SNS 로그인 시작
  - `@JavascriptInterface getSnsToken()` - JavaScript에서 토큰 조회
  - `@JavascriptInterface clearSnsToken()` - JavaScript에서 토큰 삭제
  - `@JavascriptInterface getPlatform()` - 플랫폼 정보 반환 ("android")
- ✅ JavaScript 콜백 함수 호출:
  - `onSnsLoginSuccess(token)` - 웹으로 성공 알림
  - `onSnsLoginError(error)` - 웹으로 실패 알림
- ✅ UI 스레드 안전성 보장
- ✅ 오류 처리 및 로깅
- ✅ 상세 주석 작성

---

## ✅ Phase 2 완료: Android 앱 개발
**총 작업 시간**: 약 2시간
**생성/수정된 파일**: 5개
  - `app/build.gradle` (수정)
  - `SnsAuthManager.java` (신규, 450줄)
  - `SnsCallbackActivity.java` (신규, 200줄)
  - `AndroidManifest.xml` (수정)
  - `MainActivity.java` (수정, +120줄)
**총 추가 코드 라인**: 약 770줄

---

## ✅ Phase 3: iOS 앱 개발 (완료)

### ✅ 3.1 SnsAuthManager.swift 개발
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내용:
- ✅ `SnsAuthManager.swift` 생성 완료 (380줄)
- ✅ ASWebAuthenticationSession 통합 (iOS 12+)
- ✅ 서버 API 호출 로직 (app_login.php)
- ✅ OAuth URL 생성 및 WebAuthenticationSession 실행
- ✅ CSRF 보안 처리 (state 파라미터)
- ✅ prefersEphemeralWebBrowserSession = false (쿠키 공유 활성화)
- ✅ Presentation Context Provider 구현 (iOS 13+)
- ✅ 딥링크 콜백 처리 (hairwang://oauth/callback)
- ✅ Result<String, Error> 형식으로 완료 콜백
- ✅ 상세 주석 작성

### ✅ 3.2 Info.plist URL Scheme 설정 확인
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내역:
- ✅ Info.plist 확인 완료
- ✅ URL Scheme "hairwang" 이미 등록되어 있음 (CFBundleURLSchemes)
- ✅ 추가 설정 불필요

### ✅ 3.3 SwiftWebVC.swift JavaScript Interface 추가
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내용:
- ✅ SwiftWebVC 클래스에 SNS 로그인 프로퍼티 추가:
  - `private static var snsToken: String?` - JWT 토큰 저장
  - `private var snsAuthManager: SnsAuthManager?` - SnsAuthManager 인스턴스
- ✅ userContentController 메서드에 메시지 핸들러 추가:
  - `startSnsLogin:provider` - SNS 로그인 시작
  - `getSnsToken` - 저장된 토큰 조회
  - `clearSnsToken` - 토큰 삭제
  - `getPlatform` - 플랫폼 정보 반환 ("ios")
- ✅ Static 콜백 메서드 추가:
  - `static func onSnsLoginSuccess(token: String)` - 로그인 성공 처리
  - `static func onSnsLoginError(error: String)` - 로그인 실패 처리
  - `static func getSnsToken() -> String` - 토큰 조회
  - `static func clearSnsToken()` - 토큰 삭제
- ✅ JavaScript 콜백 함수 호출:
  - `onSnsLoginSuccess(token)` - 웹으로 성공 알림
  - `onSnsLoginError(error)` - 웹으로 실패 알림
  - `onSnsTokenReceived(token)` - 토큰 조회 결과 전달
  - `onPlatformReceived('ios')` - 플랫폼 정보 전달
- ✅ iOS 12+ 버전 체크 (@available(iOS 12.0, *))
- ✅ 오류 처리 및 로깅
- ✅ 상세 주석 작성

---

## ✅ Phase 3 완료: iOS 앱 개발
**총 작업 시간**: 약 2시간
**생성/수정된 파일**: 2개
  - `SnsAuthManager.swift` (신규, 380줄)
  - `SwiftWebVC.swift` (수정, +140줄)
**총 추가 코드 라인**: 약 520줄

---

## ✅ Phase 4: 웹 프론트엔드 개발 (완료)

### ✅ 4.1 app_sns_login.js 개발
**시작 시간**: 2026-01-19 오후
**완료 시간**: 2026-01-19 오후

#### 작업 내용:
- ✅ `js/app_sns_login.js` 생성 완료 (450줄)
- ✅ 플랫폼 자동 감지 기능 (Android / iOS / 웹):
  - Android 감지: `typeof Android !== 'undefined'`
  - iOS 감지: `typeof webkit.messageHandlers !== 'undefined'`
  - 웹 브라우저: 기본값
- ✅ 플랫폼별 SNS 로그인 시작:
  - Android: `Android.startSnsLogin(provider)` 호출
  - iOS: `webkit.messageHandlers.invokeAction.postMessage('startSnsLogin:' + provider)` 호출
  - 웹: 서버에서 OAuth URL 받아 팝업 열기
- ✅ JavaScript 콜백 함수 구현:
  - `window.onSnsLoginSuccess(token)` - 앱에서 성공 콜백
  - `window.onSnsLoginError(error)` - 앱에서 실패 콜백
  - `window.onPlatformReceived(platform)` - iOS 플랫폼 정보 수신
- ✅ Promise 기반 API:
  - `AppSnsLogin.startLogin(provider)` - SNS 로그인 시작 (Promise 반환)
  - `AppSnsLogin.convertTokenToSession(token)` - JWT 토큰을 세션으로 변환
- ✅ 웹 브라우저용 팝업 OAuth 지원:
  - 서버 API 호출하여 OAuth URL 가져오기
  - 팝업 창 열기 및 postMessage 통신
  - 팝업 닫기 감지 (취소 처리)
- ✅ CSRF 보안 처리 (state 파라미터)
- ✅ 상세 주석 작성

---

## ✅ Phase 4 완료: 웹 프론트엔드 개발
**총 작업 시간**: 약 1시간
**생성된 파일**: 1개
  - `js/app_sns_login.js` (신규, 450줄)
**총 코드 라인**: 약 450줄

---

## 📊 전체 개발 완료 요약

### 개발 기간
- **시작**: 2026-01-19 오후
- **완료**: 2026-01-19 오후
- **총 소요 시간**: 약 6시간

### 생성/수정된 파일 목록

#### 서버 (PHP)
1. `api/sns/app_login.php` (신규, 400줄)
2. `api/sns/app_callback.php` (신규, 650줄)
3. `api/sns/token_to_session.php` (신규, 400줄)

#### Android 앱
4. `app/build.gradle` (수정, +1줄)
5. `SnsAuthManager.java` (신규, 450줄)
6. `SnsCallbackActivity.java` (신규, 200줄)
7. `AndroidManifest.xml` (수정, +20줄)
8. `MainActivity.java` (수정, +120줄)

#### iOS 앱
9. `SnsAuthManager.swift` (신규, 380줄)
10. `SwiftWebVC.swift` (수정, +140줄)
11. `Info.plist` (확인, 기존 URL Scheme 사용)

#### 웹 프론트엔드
12. `js/app_sns_login.js` (신규, 450줄)

### 총 코드 라인 수
- **서버 API**: 약 1,450줄
- **Android 앱**: 약 770줄
- **iOS 앱**: 약 520줄
- **웹 프론트엔드**: 약 450줄
- **총계**: 약 3,190줄

### 주요 기능

#### 1. 플랫폼별 SNS 자동 로그인
- **Android**: Chrome Custom Tabs (시스템 Chrome 브라우저 쿠키 공유)
- **iOS**: ASWebAuthenticationSession (시스템 Safari 쿠키 공유)
- **웹**: OAuth 팝업 (기존 방식)

#### 2. 지원 SNS (6개)
- 네이버, 카카오, 구글, 페이스북, 트위터, 페이코

#### 3. 보안 기능
- CSRF 보호 (state 파라미터)
- JWT 토큰 기반 인증
- 딥링크 검증

#### 4. 사용자 경험
- 사용자가 이미 SNS에 로그인되어 있으면 별도의 로그인 없이 자동 인증
- 앱에서 시스템 브라우저와 쿠키 공유
- 원클릭 로그인 가능

---

## 🔄 다음 단계

### ✅ 개발 완료
- Phase 1: 서버 API 확장
- Phase 2: Android 앱 개발
- Phase 3: iOS 앱 개발
- Phase 4: 웹 프론트엔드 개발

### 📋 남은 작업
1. **Android APK 빌드**
   - Android Studio에서 프로젝트 열기
   - Gradle Sync 실행
   - APK 빌드 (Build → Build Bundle(s) / APK(s) → Build APK(s))

2. **iOS IPA 빌드** (Mac 필요)
   - Xcode에서 프로젝트 열기
   - 프로비저닝 프로파일 설정
   - Archive 빌드 (Product → Archive)
   - TestFlight 또는 Ad-Hoc 배포

3. **서버 배포**
   - `api/sns/` 디렉토리를 운영 서버로 복사
   - `js/app_sns_login.js`를 운영 서버로 복사
   - PHP 환경 확인 (PHP 7.4+)

4. **테스트**
   - 각 SNS별 로그인 테스트
   - 플랫폼별(Android/iOS/웹) 테스트
   - 오류 처리 시나리오 테스트

5. **문서화**
   - 사용자 매뉴얼 작성
   - API 문서 작성
   - 트러블슈팅 가이드 작성

---

## 📝 참고 사항

### Android 빌드 전 확인 사항
- `app/build.gradle`에서 `minSdkVersion` 21 이상 확인
- Chrome Custom Tabs 라이브러리 의존성 확인
- 딥링크 스킴 `hairwang://oauth/callback` 확인

### iOS 빌드 전 확인 사항
- iOS 12 이상 지원 확인
- Info.plist의 URL Scheme `hairwang` 확인
- ASWebAuthenticationSession 권한 확인

### 서버 배포 전 확인 사항
- SNS API 키/시크릿 설정 (`api/sns/app_callback.php`)
- JWT 시크릿 키 설정 (`api/sns/app_callback.php`)
- 서버 도메인 설정 확인

