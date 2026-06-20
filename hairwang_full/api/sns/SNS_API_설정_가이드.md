# SNS 로그인 API 설정 가이드

**작성일**: 2026-01-29
**버전**: 1.0.0

---

## 📋 목차

1. [개요](#개요)
2. [빠른 시작](#빠른-시작)
3. [SNS별 상세 설정](#sns별-상세-설정)
   - [Naver](#1-naver-로그인)
   - [Kakao](#2-kakao-로그인)
   - [Google](#3-google-로그인)
   - [Facebook](#4-facebook-로그인)
   - [Twitter](#5-twitter-로그인)
   - [Payco](#6-payco-로그인)
4. [보안 설정](#보안-설정)
5. [테스트 방법](#테스트-방법)
6. [문제 해결](#문제-해결)

---

## 개요

이 가이드는 Hair Wang 프로젝트에서 SNS 로그인 API를 설정하는 방법을 설명합니다.

### 지원하는 SNS

- ✅ **Naver** (네이버 아이디로 로그인)
- ✅ **Kakao** (카카오 로그인)
- ✅ **Google** (Google Sign-In)
- ✅ **Facebook** (Facebook Login)
- ✅ **Twitter** (Twitter OAuth 2.0)
- ✅ **Payco** (페이코 로그인)

---

## 빠른 시작

### 1. 설정 파일 생성

```bash
cd api/sns
cp config.example.php config.php
```

### 2. API 키 입력

`config.php` 파일을 열고 각 SNS의 API 키를 입력하세요.

```php
// Naver 예시
define('NAVER_CLIENT_ID', 'YOUR_ACTUAL_CLIENT_ID');
define('NAVER_CLIENT_SECRET', 'YOUR_ACTUAL_CLIENT_SECRET');
```

### 3. Callback URL 설정

모든 SNS 플랫폼의 개발자 콘솔에서 다음 Callback URL을 등록하세요:

```
https://yourdomain.com/api/sns/callback.php?provider={sns_name}
```

예시:
- Naver: `https://yourdomain.com/api/sns/callback.php?provider=naver`
- Kakao: `https://yourdomain.com/api/sns/callback.php?provider=kakao`

### 4. 보안 설정

```bash
# config.php 파일 권한 설정
chmod 600 config.php

# .gitignore에 추가
echo "config.php" >> .gitignore
```

---

## SNS별 상세 설정

### 1. Naver 로그인

#### 1.1 애플리케이션 등록

1. [네이버 개발자 센터](https://developers.naver.com/apps/#/register) 접속
2. **애플리케이션 등록** 클릭
3. **애플리케이션 이름** 입력 (예: Hair Wang)
4. **사용 API** 선택: **네아로 (네이버 아이디로 로그인)**

#### 1.2 서비스 환경 설정

- **서비스 URL**: `https://yourdomain.com`
- **Callback URL**: `https://yourdomain.com/api/sns/callback.php?provider=naver`

#### 1.3 제공 정보 설정

로그인 시 제공받을 정보 선택:
- ✅ 회원 이름
- ✅ 이메일 주소
- ✅ 프로필 이미지
- ⬜ 생일
- ⬜ 연령대
- ⬜ 성별

#### 1.4 API 키 확인

등록 완료 후:
- **Client ID**: 애플리케이션 정보 페이지에서 확인
- **Client Secret**: 애플리케이션 정보 페이지에서 확인

#### 1.5 config.php 설정

```php
define('NAVER_CLIENT_ID', 'YOUR_CLIENT_ID');
define('NAVER_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
define('NAVER_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=naver');
```

---

### 2. Kakao 로그인

#### 2.1 애플리케이션 추가

1. [Kakao Developers](https://developers.kakao.com/console/app) 접속
2. **애플리케이션 추가하기** 클릭
3. **앱 이름**, **회사명** 입력

#### 2.2 플랫폼 설정

1. **앱 설정 > 플랫폼** 메뉴
2. **Web 플랫폼 등록**
3. **사이트 도메인**: `https://yourdomain.com`

#### 2.3 카카오 로그인 활성화

1. **제품 설정 > 카카오 로그인** 메뉴
2. **카카오 로그인 활성화** ON
3. **Redirect URI 등록**: `https://yourdomain.com/api/sns/callback.php?provider=kakao`

#### 2.4 동의항목 설정

**제품 설정 > 카카오 로그인 > 동의항목**:
- ✅ 닉네임 (필수)
- ✅ 프로필 사진 (선택)
- ✅ 카카오계정(이메일) (필수)

#### 2.5 API 키 확인

**앱 설정 > 앱 키**:
- **REST API 키**: 서버 사이드에서 사용
- **JavaScript 키**: 클라이언트 사이드에서 사용
- **Admin 키**: 관리자 API 호출 시 사용

#### 2.6 config.php 설정

```php
define('KAKAO_REST_API_KEY', 'YOUR_REST_API_KEY');
define('KAKAO_JAVASCRIPT_KEY', 'YOUR_JAVASCRIPT_KEY');
define('KAKAO_ADMIN_KEY', 'YOUR_ADMIN_KEY');
define('KAKAO_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=kakao');
```

---

### 3. Google 로그인

#### 3.1 Google Cloud 프로젝트 생성

1. [Google Cloud Console](https://console.cloud.google.com/) 접속
2. **새 프로젝트** 생성 (예: Hair Wang)

#### 3.2 OAuth 동의 화면 구성

1. **API 및 서비스 > OAuth 동의 화면**
2. **사용자 유형** 선택: **외부**
3. **앱 이름**, **사용자 지원 이메일** 입력
4. **승인된 도메인** 추가: `yourdomain.com`
5. **개발자 연락처 정보** 입력

#### 3.3 OAuth 2.0 클라이언트 ID 생성

1. **API 및 서비스 > 사용자 인증 정보**
2. **+ 사용자 인증 정보 만들기 > OAuth 클라이언트 ID**
3. **애플리케이션 유형**: **웹 애플리케이션**
4. **이름** 입력 (예: Hair Wang Web Client)
5. **승인된 자바스크립트 원본**:
   - `https://yourdomain.com`
6. **승인된 리디렉션 URI**:
   - `https://yourdomain.com/api/sns/callback.php?provider=google`

#### 3.4 API 키 확인

생성 완료 후 팝업에서:
- **클라이언트 ID**: `XXXXX.apps.googleusercontent.com`
- **클라이언트 보안 비밀**: `XXXXX`

#### 3.5 config.php 설정

```php
define('GOOGLE_CLIENT_ID', 'YOUR_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
define('GOOGLE_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=google');
```

---

### 4. Facebook 로그인

#### 4.1 앱 만들기

1. [Facebook for Developers](https://developers.facebook.com/apps/) 접속
2. **앱 만들기** 클릭
3. **앱 유형** 선택: **소비자**
4. **앱 이름** 입력 (예: Hair Wang)

#### 4.2 Facebook 로그인 제품 추가

1. **제품 추가** > **Facebook 로그인**
2. **설정** 클릭
3. **플랫폼** 선택: **웹**
4. **사이트 URL**: `https://yourdomain.com`

#### 4.3 OAuth 리디렉션 URI 설정

1. **Facebook 로그인 > 설정**
2. **유효한 OAuth 리디렉션 URI**:
   - `https://yourdomain.com/api/sns/callback.php?provider=facebook`

#### 4.4 API 키 확인

**설정 > 기본 설정**:
- **앱 ID**: 숫자로 된 ID
- **앱 시크릿**: `표시` 클릭하여 확인

#### 4.5 config.php 설정

```php
define('FACEBOOK_APP_ID', 'YOUR_APP_ID');
define('FACEBOOK_APP_SECRET', 'YOUR_APP_SECRET');
define('FACEBOOK_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=facebook');
```

---

### 5. Twitter 로그인

#### 5.1 프로젝트 및 앱 생성

1. [Twitter Developer Portal](https://developer.twitter.com/en/portal/dashboard) 접속
2. **+ Create Project** 클릭
3. **Project name** 입력
4. **+ Add App** 클릭
5. **App name** 입력

#### 5.2 API Keys 생성

1. **Keys and tokens** 탭
2. **API Key and Secret** 생성
3. **Bearer Token** 생성 (선택사항)

#### 5.3 Authentication Settings

1. **Settings** 탭
2. **User authentication settings > Set up**
3. **App permissions**: **Read**
4. **Type of App**: **Web App**
5. **Callback URI / Redirect URL**:
   - `https://yourdomain.com/api/sns/callback.php?provider=twitter`
6. **Website URL**: `https://yourdomain.com`

#### 5.4 config.php 설정

```php
define('TWITTER_API_KEY', 'YOUR_API_KEY');
define('TWITTER_API_SECRET', 'YOUR_API_SECRET');
define('TWITTER_BEARER_TOKEN', 'YOUR_BEARER_TOKEN');
define('TWITTER_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=twitter');
```

---

### 6. Payco 로그인

#### 6.1 서비스 등록

1. [Payco Developers](https://developers.payco.com/) 접속
2. **서비스 등록** 클릭
3. **서비스명**, **서비스 설명** 입력

#### 6.2 Redirect URI 설정

- **Redirect URI**: `https://yourdomain.com/api/sns/callback.php?provider=payco`

#### 6.3 API 키 확인

서비스 등록 완료 후:
- **Client ID**: 발급된 클라이언트 ID
- **Client Secret**: 발급된 시크릿 키

#### 6.4 config.php 설정

```php
define('PAYCO_CLIENT_ID', 'YOUR_CLIENT_ID');
define('PAYCO_CLIENT_SECRET', 'YOUR_CLIENT_SECRET');
define('PAYCO_CALLBACK_URL', 'https://yourdomain.com/api/sns/callback.php?provider=payco');
```

---

## 보안 설정

### 1. config.php 보호

```bash
# 파일 권한 설정 (읽기 전용)
chmod 600 config.php

# 웹에서 접근 차단 (.htaccess)
echo "<Files config.php>
    Order Allow,Deny
    Deny from all
</Files>" >> .htaccess
```

### 2. HTTPS 필수

모든 SNS 로그인은 **HTTPS** 연결이 필수입니다:
- SSL 인증서 설치
- HTTP → HTTPS 리다이렉션 설정

### 3. CSRF 보호

```php
// config.php에서 활성화
define('SNS_ENABLE_CSRF', true);
define('SNS_ENABLE_STATE', true);
```

### 4. IP 화이트리스트 (선택사항)

```php
// 특정 IP만 API 호출 허용
define('SNS_IP_WHITELIST', [
    '127.0.0.1',
    '192.168.1.100'
]);
```

---

## 테스트 방법

### 1. 개발 환경 테스트

```bash
# 로컬 테스트용 도메인 설정 (hosts 파일)
127.0.0.1 test.hairwang.local

# 각 SNS별 테스트 URL
https://test.hairwang.local/api/sns/login.php?provider=naver
https://test.hairwang.local/api/sns/login.php?provider=kakao
```

### 2. API 응답 확인

```php
// test_sns_api.php 생성
<?php
require_once 'config.php';

echo "=== SNS API 설정 확인 ===\n\n";

// Naver
echo "Naver Client ID: " . substr(NAVER_CLIENT_ID, 0, 10) . "...\n";
echo "Naver Callback: " . NAVER_CALLBACK_URL . "\n\n";

// Kakao
echo "Kakao REST API Key: " . substr(KAKAO_REST_API_KEY, 0, 10) . "...\n";
echo "Kakao Callback: " . KAKAO_CALLBACK_URL . "\n\n";

// ... 다른 SNS 확인
?>
```

### 3. 로그 확인

```bash
# SNS API 로그 확인
tail -f api/sns/logs/sns_api.log

# 에러 로그 확인
tail -f api/sns/logs/error.log
```

---

## 문제 해결

### 1. "Invalid Redirect URI" 오류

**원인**: Callback URL이 SNS 플랫폼에 등록되지 않음

**해결**:
1. SNS 개발자 콘솔에서 Callback URL 확인
2. 프로토콜 (http vs https) 확인
3. 도메인, 경로, 쿼리 파라미터 정확히 일치하는지 확인

```
예시: https://yourdomain.com/api/sns/callback.php?provider=naver
```

### 2. "Invalid Client" 오류

**원인**: API 키가 올바르지 않음

**해결**:
1. config.php의 Client ID, Client Secret 재확인
2. SNS 개발자 콘솔에서 키 재발급
3. 공백, 특수문자 포함 여부 확인

### 3. "Access Denied" 오류

**원인**: 동의 항목이 설정되지 않음

**해결**:
1. SNS 개발자 콘솔에서 동의 항목 설정
2. 필수 항목 (이름, 이메일) 활성화
3. 앱 검수 상태 확인 (Facebook, Google)

### 4. HTTPS 요구 오류

**원인**: HTTP로 접속 시도

**해결**:
```apache
# .htaccess에 HTTPS 리다이렉션 추가
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}/$1 [R=301,L]
```

### 5. 세션 오류

**원인**: 세션이 올바르게 시작되지 않음

**해결**:
```php
// callback.php 최상단에 추가
session_start();
```

---

## 추가 리소스

### 공식 문서
- [Naver Developers](https://developers.naver.com/docs/login/api/)
- [Kakao Developers](https://developers.kakao.com/docs/latest/ko/kakaologin/common)
- [Google Identity](https://developers.google.com/identity/sign-in/web/sign-in)
- [Facebook Login](https://developers.facebook.com/docs/facebook-login/web)
- [Twitter OAuth 2.0](https://developer.twitter.com/en/docs/authentication/oauth-2-0)
- [Payco Developers](https://developers.payco.com/)

### 커뮤니티
- [Stack Overflow - SNS Login](https://stackoverflow.com/questions/tagged/social-login)
- [GitHub Issues](https://github.com/search?q=sns+login)

---

## 변경 이력

- **2026-01-29**: 최초 작성 (v1.0.0)
- 6개 SNS 플랫폼 설정 가이드 포함
- 보안 설정 및 문제 해결 가이드 추가

---

**작성자**: Claude AI Assistant
**라이선스**: MIT
**문의**: 개발팀
