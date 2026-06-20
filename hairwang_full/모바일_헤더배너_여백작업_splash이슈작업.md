# 모바일 헤더/배너 여백 및 Splash 이슈 작업 내역

## 📋 작업 정보
- **작업 일자**: 2025-12-21
- **작업자**: Claude Code
- **작업 목적**:
  1. 모바일 앱 상단 여백 추가 (헤더와 컨텐츠 사이)
  2. Splash 화면 1회만 표시 (쿠키 방식)

---

## 🎯 문제 1: 모바일 앱 상단 여백 부족

### 현상
- **문제**: 모바일 앱에서 헤더(검색, 메뉴)와 상단 보라색 배너 사이 여백 없음
- **영향 범위**: 앱에서만 발생, PC/모바일 웹은 정상
- **원인**: 앱 전용 여백 CSS 없음

### 해결 방법
CSS 속성 선택자를 사용하여 앱에서만 여백 적용

---

## 🔧 수정 내역 1: mobile.css 파일

### 수정 파일
```
theme/rb.basic/css/mobile.css
```

### 수정 위치
라인 150-176 (본문 바로가기 CSS 이후)

### 기존 코드
```css
/* 본문 바로가기 */
.to_content a {z-index:100000;position:absolute;top:0;left:0;width:0;height:0;font-size:0;line-height:0;overflow:hidden}

/* 이미지 등비율 리사이징 */
.img_fix {width:100%;height:auto}
```

### 수정 후 코드
```css
/* 본문 바로가기 */
.to_content a {z-index:100000;position:absolute;top:0;left:0;width:0;height:0;font-size:0;line-height:0;overflow:hidden}

/* ============================================ */
/* ✅ 앱 전용 여백 추가 (2025-12-21) */
/* ============================================ */
/*
 * 목적: 모바일 앱에서 헤더와 컨텐츠 사이 여백 추가
 * 조건: body에 data-app="true" 속성이 있을 때만 적용
 * 적용: head.sub.php에서 앱 감지 시 body 태그에 속성 추가
 * 효과: PC와 모바일 웹은 영향 없음, 앱에서만 여백 생김
 */
body[data-app="true"] #hd {
    margin-bottom: 15px;  /* 헤더 하단 15px 여백 추가 */
}

/*
 * 대안: 컨텐츠 상단 여백 방식
 * 필요시 위 코드 대신 아래 코드 사용 가능
 */
/* body[data-app="true"] #container {
    padding-top: 15px !important;
} */
/* ============================================ */

/* 이미지 등비율 리사이징 */
.img_fix {width:100%;height:auto}
```

### 변경 내용 설명
1. **CSS 속성 선택자 사용**: `body[data-app="true"]`
   - body 태그에 `data-app="true"` 속성이 있을 때만 적용
   - PC/모바일 웹에는 영향 없음

2. **여백 적용 방법**: `#hd` 하단에 15px margin 추가
   - 헤더(`#hd`)와 그 아래 컨텐츠 사이 여백 생성
   - 대안으로 `#container` 상단 padding 방식도 주석으로 제공

3. **주석 상세 작성**:
   - 목적, 조건, 적용 방법, 효과 명시
   - 유지보수 시 이해 용이하도록 설명 추가

---

## 🔧 수정 내역 2: head.sub.php 파일

### 수정 파일
```
theme/rb.basic/head.sub.php
```

### 수정 위치
라인 233-250 (`<body>` 태그)

### 기존 코드
```php
</head>
<body<?php echo isset($g5['body_script']) ? $g5['body_script'] : ''; ?>>
<?php
if ($is_member) { // 회원이라면 로그인 중이라는 메세지를 출력해준다.
    $sr_admin_msg = '';
```

### 수정 후 코드
```php
</head>
<body<?php
// ============================================
// ✅ 앱 전용 속성 추가 (2025-12-21)
// ============================================
// 목적: 모바일 앱에서 접속 시 body에 data-app="true" 속성 추가
// 용도: mobile.css에서 body[data-app="true"] 선택자로 앱 전용 스타일 적용
// 감지 방법 1: URL 파라미터 ?app=1 확인
// 감지 방법 2: User-Agent에 'AppName' 포함 여부 확인
$is_app_view = false;
if (isset($_GET['app']) && $_GET['app'] == '1') {
    $is_app_view = true;
} else if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'AppName') !== false) {
    $is_app_view = true;
}
// body 태그에 속성 추가
echo isset($g5['body_script']) ? $g5['body_script'] : '';
if ($is_app_view) echo ' data-app="true"';
?>>
<?php
if ($is_member) { // 회원이라면 로그인 중이라는 메세지를 출력해준다.
    $sr_admin_msg = '';
```

### 변경 내용 설명
1. **앱 감지 로직 추가**:
   - **방법 1**: URL 파라미터 `?app=1` 확인
   - **방법 2**: User-Agent에 'AppName' 포함 여부 확인

2. **body 태그 속성 동적 추가**:
   - 앱에서 접속 시: `<body data-app="true">`
   - 웹에서 접속 시: `<body>` (속성 없음)

3. **주석 상세 작성**:
   - 목적, 용도, 감지 방법 명시
   - CSS와 연동 방법 설명

### 동작 흐름
```
1. 앱에서 https://hairwang.com/?app=1 접속
   ↓
2. head.sub.php에서 $_GET['app'] 확인
   ↓
3. $is_app_view = true 설정
   ↓
4. <body data-app="true"> 출력
   ↓
5. mobile.css의 body[data-app="true"] #hd 스타일 적용
   ↓
6. 헤더 하단 15px 여백 생성 ✅
```

---

## 🎯 문제 2: Splash 화면 반복 표시

### 현상
- **문제**: 앱에서 메인 페이지 방문할 때마다 splash 표시됨
- **원인**: 메인 페이지에서 splash 호출 로직 없음, 1회 제한 없음
- **영향**: 사용자 경험 저하

### 해결 방법
쿠키를 사용하여 splash 1회만 표시

---

## 🔧 수정 내역 3: index.php 파일

### 수정 파일
```
www/index.php
```

### 수정 위치
라인 4-46 (`include_once('./_common.php');` 직후)

### 기존 코드
```php
<?php
include_once('./_common.php');


if($member['mb_id'] == 'developer'){
	//goto_url('test10.php');
	//exit;
}

define('_INDEX_', true);
```

### 수정 후 코드
```php
<?php
include_once('./_common.php');

// ============================================
// ✅ Splash 1회만 표시 (쿠키 방식) - 2025-12-21
// ============================================
/*
 * 목적: 앱에서 메인 페이지 첫 방문 시에만 splash 화면 표시
 * 방법: 쿠키를 사용하여 splash 표시 여부 기록
 * 장점: 앱 재시작해도 쿠키가 유지되어 splash 다시 안 나타남
 *
 * 동작 흐름:
 * 1. 앱 감지 (URL 파라미터 ?app=1 또는 User-Agent)
 * 2. 쿠키 'hairwang_splash_shown' 확인
 * 3. 쿠키 없으면 → splash.php로 리다이렉트 (쿠키 설정)
 * 4. 쿠키 있으면 → 바로 메인 페이지 표시
 */

// 앱 감지
$is_app_splash = false;
if (isset($_GET['app']) && $_GET['app'] == '1') {
    // URL 파라미터로 앱 감지
    $is_app_splash = true;
} else if (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'AppName') !== false) {
    // User-Agent로 앱 감지 (앱에서 User-Agent에 'AppName' 포함시켜야 함)
    $is_app_splash = true;
}

// 앱에서 첫 방문 시에만 splash 표시
if ($is_app_splash && !isset($_COOKIE['hairwang_splash_shown'])) {
    // splash를 본 적이 없으면 쿠키 설정
    // 유효기간: 1년 (365일)
    // 경로: / (전체 사이트에서 유효)
    setcookie('hairwang_splash_shown', '1', time() + (365 * 24 * 60 * 60), '/');

    // 현재 URL을 다음 페이지로 설정 (splash 후 돌아올 주소)
    $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    // splash.php로 리다이렉트
    // ?app=1: 앱에서 접속함을 알림
    // &url=...: splash 종료 후 돌아올 주소
    header("Location: /rb/splash.php?app=1&url=" . urlencode($current_url));
    exit;
}
// 쿠키가 있으면 (이미 splash를 본 경우) 아래 코드 계속 실행 → 메인 페이지 표시
// ============================================


if($member['mb_id'] == 'developer'){
	//goto_url('test10.php');
	//exit;
}

define('_INDEX_', true);
```

### 변경 내용 설명

1. **앱 감지 로직**:
   ```php
   $is_app_splash = false;
   if (isset($_GET['app']) && $_GET['app'] == '1') {
       $is_app_splash = true;
   } else if (strpos($_SERVER['HTTP_USER_AGENT'], 'AppName') !== false) {
       $is_app_splash = true;
   }
   ```
   - URL 파라미터 또는 User-Agent로 앱 감지
   - 웹 브라우저는 영향 받지 않음

2. **쿠키 체크 및 설정**:
   ```php
   if ($is_app_splash && !isset($_COOKIE['hairwang_splash_shown'])) {
       setcookie('hairwang_splash_shown', '1', time() + (365 * 24 * 60 * 60), '/');
       // ... splash.php로 리다이렉트
   }
   ```
   - 쿠키 이름: `hairwang_splash_shown`
   - 유효기간: 1년 (365일)
   - 경로: `/` (전체 사이트)

3. **splash.php 리다이렉트**:
   ```php
   $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
   header("Location: /rb/splash.php?app=1&url=" . urlencode($current_url));
   exit;
   ```
   - 현재 URL을 저장하여 splash 종료 후 돌아올 주소로 전달
   - `?app=1`: 앱임을 알림
   - `&url=...`: 돌아올 주소

4. **주석 상세 작성**:
   - 목적, 방법, 장점, 동작 흐름 명시
   - 각 코드 라인별 설명 추가

### 동작 흐름

#### ✅ 첫 방문 (쿠키 없음)
```
1. 앱 실행 → https://hairwang.com/?app=1 접속
   ↓
2. index.php 실행
   ↓
3. 앱 감지 ($is_app_splash = true)
   ↓
4. 쿠키 체크 (없음)
   ↓
5. setcookie('hairwang_splash_shown', '1', ...)
   ↓
6. header("Location: /rb/splash.php?app=1&url=...")
   ↓
7. splash.php 표시 (3초)
   ↓
8. 메인 페이지로 이동 ✅
```

#### ✅ 재방문 (쿠키 있음)
```
1. 앱 실행 → https://hairwang.com/?app=1 접속
   ↓
2. index.php 실행
   ↓
3. 앱 감지 ($is_app_splash = true)
   ↓
4. 쿠키 체크 (있음! 'hairwang_splash_shown' = '1')
   ↓
5. splash 건너뛰기
   ↓
6. 바로 메인 페이지 표시 ✅
```

---

## 📊 수정 파일 요약

| 파일 | 수정 위치 | 수정 내용 | 목적 |
|------|----------|----------|------|
| **mobile.css** | 라인 153-173 | 앱 전용 여백 CSS 추가 | 헤더 하단 15px 여백 |
| **head.sub.php** | 라인 233-250 | body 태그에 data-app 속성 추가 | 앱 감지 및 CSS 연동 |
| **index.php** | 라인 4-46 | splash 1회 호출 로직 추가 | 쿠키로 1회만 표시 |

---

## 🧪 테스트 방법

### 1. 모바일 여백 테스트

#### ✅ 앱에서 테스트
```
1. 앱 실행
2. https://hairwang.com/?app=1 접속
3. 헤더와 상단 배너 사이 15px 여백 확인
4. 개발자 도구로 body 태그 확인 → data-app="true" 속성 있어야 함
```

#### ✅ PC/모바일 웹에서 테스트
```
1. 브라우저에서 https://hairwang.com 접속
2. 기존과 동일한 레이아웃 확인
3. 개발자 도구로 body 태그 확인 → data-app 속성 없어야 함
```

### 2. Splash 1회 표시 테스트

#### ✅ 첫 방문 테스트
```
1. 앱 종료 및 쿠키 삭제
   - Android: 앱 설정 → 저장소 → 데이터 삭제
   - iOS: 앱 삭제 후 재설치
   - 또는 개발자 도구 → Application → Cookies → hairwang_splash_shown 삭제

2. 앱 실행 → https://hairwang.com/?app=1 접속

3. Splash 화면 표시 확인 (3초)

4. 메인 페이지 자동 이동 확인

5. 쿠키 확인
   - 개발자 도구 → Application → Cookies
   - hairwang_splash_shown = 1 존재 확인
```

#### ✅ 재방문 테스트
```
1. 앱 재시작 (쿠키 유지)

2. https://hairwang.com/?app=1 접속

3. Splash 건너뛰고 바로 메인 페이지 표시 확인 ✅

4. 쿠키 확인
   - hairwang_splash_shown = 1 여전히 존재
```

#### ✅ 웹 브라우저 테스트
```
1. PC/모바일 브라우저에서 https://hairwang.com 접속

2. Splash 안 나타남 확인 (앱 전용이므로)

3. 정상적으로 메인 페이지 표시 확인
```

---

## 🔍 디버깅 가이드

### 문제 1: 앱에서 여백이 안 보이는 경우

#### 체크리스트
1. **body 태그 속성 확인**:
   ```
   개발자 도구 → Elements → <body> 태그
   → data-app="true" 속성 있는지 확인
   ```

2. **CSS 파일 로드 확인**:
   ```
   개발자 도구 → Network → mobile.css 로드 확인
   → 200 OK 상태 확인
   ```

3. **CSS 적용 확인**:
   ```
   개발자 도구 → Elements → #hd 선택
   → Styles 탭에서 margin-bottom: 15px 확인
   ```

4. **앱 URL 파라미터 확인**:
   ```
   앱에서 https://hairwang.com/?app=1 로 접속하는지 확인
   ?app=1 파라미터 누락 시 data-app 속성 안 붙음
   ```

### 문제 2: Splash가 계속 나오는 경우

#### 체크리스트
1. **쿠키 설정 확인**:
   ```
   개발자 도구 → Application → Cookies
   → hairwang_splash_shown 쿠키 존재하는지 확인
   ```

2. **쿠키 경로 확인**:
   ```
   쿠키의 Path가 "/" 인지 확인
   다른 경로면 적용 안됨
   ```

3. **앱 감지 확인**:
   ```php
   // index.php에 임시 디버깅 코드 추가
   error_log("is_app_splash: " . ($is_app_splash ? 'true' : 'false'));
   error_log("Cookie exists: " . (isset($_COOKIE['hairwang_splash_shown']) ? 'yes' : 'no'));
   ```

4. **서버 로그 확인**:
   ```
   PHP error_log 확인
   setcookie() 실행 여부 확인
   ```

### 문제 3: 웹에서도 Splash가 나오는 경우

#### 원인
- 앱 감지 로직이 잘못 동작
- URL에 `?app=1` 파라미터가 붙어있음

#### 해결
```
1. URL 확인: https://hairwang.com (파라미터 없어야 함)
2. User-Agent 확인: 'AppName' 포함 여부
3. 웹에서는 $is_app_splash = false 여야 함
```

---

## 📌 주의사항

### 쿠키 유효기간

**현재 설정**: 1년 (365일)

**변경 방법**:
```php
// index.php 라인 34

// 1일
setcookie('hairwang_splash_shown', '1', time() + (1 * 24 * 60 * 60), '/');

// 1주일
setcookie('hairwang_splash_shown', '1', time() + (7 * 24 * 60 * 60), '/');

// 1개월
setcookie('hairwang_splash_shown', '1', time() + (30 * 24 * 60 * 60), '/');

// 1년 (현재)
setcookie('hairwang_splash_shown', '1', time() + (365 * 24 * 60 * 60), '/');

// 영구 (10년)
setcookie('hairwang_splash_shown', '1', time() + (10 * 365 * 24 * 60 * 60), '/');
```

### 쿠키 삭제 방법

**사용자가 다시 Splash를 보고 싶을 때**:

1. **앱에서**:
   - Android: 설정 → 앱 → 저장소 → 데이터 삭제
   - iOS: 앱 삭제 후 재설치

2. **개발자 도구에서**:
   ```
   Application → Cookies → hairwang.com
   → hairwang_splash_shown 삭제
   ```

3. **코드로 삭제** (관리자 기능으로 추가 가능):
   ```php
   setcookie('hairwang_splash_shown', '', time() - 3600, '/');
   ```

### User-Agent 설정

**현재**: 'AppName' 문자열 감지

**앱에서 설정 필요**:
```kotlin
// Android
webView.settings.userAgentString = "Mozilla/5.0 ... AppName/1.0"
```

```swift
// iOS
let userAgent = "Mozilla/5.0 ... AppName/1.0"
webView.customUserAgent = userAgent
```

---

## 🔄 롤백 방법

### 문제 발생 시 원복

#### 1. mobile.css 원복
```css
/* 라인 153-173 전체 삭제 */
/* ============================================ */
/* ✅ 앱 전용 여백 추가 (2025-12-21) */
/* ... */
/* ============================================ */
```

#### 2. head.sub.php 원복
```php
<!-- 라인 233-250을 아래로 교체 -->
</head>
<body<?php echo isset($g5['body_script']) ? $g5['body_script'] : ''; ?>>
<?php
if ($is_member) {
```

#### 3. index.php 원복
```php
<!-- 라인 4-46 전체 삭제 -->
<?php
include_once('./_common.php');


if($member['mb_id'] == 'developer'){
```

---

## ✅ 작업 완료 체크리스트

- [x] mobile.css에 앱 전용 여백 CSS 추가
- [x] head.sub.php에 body 속성 추가 로직 구현
- [x] index.php에 splash 1회 호출 로직 구현
- [x] 모든 코드에 상세 주석 추가
- [x] 작업 문서 작성 완료
- [ ] 앱에서 여백 확인 테스트
- [ ] PC/모바일 웹에서 기존 레이아웃 유지 확인
- [ ] Splash 1회 표시 테스트
- [ ] 앱 재시작 후 Splash 안 나오는지 확인
- [ ] 웹 브라우저에서 Splash 안 나오는지 확인

---

## 📞 기술 지원

### 추가 수정이 필요한 경우

1. **여백 크기 조정**:
   - mobile.css 라인 163: `margin-bottom: 15px;` → 원하는 크기로 변경

2. **쿠키 유효기간 변경**:
   - index.php 라인 34: `time() + (365 * 24 * 60 * 60)` → 원하는 기간으로 변경

3. **User-Agent 문자열 변경**:
   - head.sub.php 라인 244: `'AppName'` → 실제 앱 User-Agent로 변경
   - index.php 라인 24: `'AppName'` → 실제 앱 User-Agent로 변경

---

## 📚 참고 자료

### CSS 속성 선택자
- `body[data-app="true"]`: body 태그에 data-app 속성이 "true"일 때만 적용
- MDN 문서: https://developer.mozilla.org/ko/docs/Web/CSS/Attribute_selectors

### PHP 쿠키
- `setcookie()`: PHP에서 쿠키 설정
- 유효기간: time() + 초 단위
- MDN 문서: https://www.php.net/manual/en/function.setcookie.php

### User-Agent 감지
- `$_SERVER['HTTP_USER_AGENT']`: 클라이언트 User-Agent 문자열
- `strpos()`: 문자열 포함 여부 확인
- PHP 문서: https://www.php.net/manual/en/reserved.variables.server.php

---

## 🎯 작업 결과

### 예상 효과

1. **모바일 앱 사용자 경험 개선**:
   - 헤더와 컨텐츠 사이 여백 생성으로 가독성 향상
   - PC/모바일 웹은 영향 없음

2. **Splash 화면 최적화**:
   - 첫 방문 시에만 표시로 사용자 경험 개선
   - 앱 재시작해도 쿠키 유지로 반복 표시 방지
   - 웹 브라우저는 영향 없음

3. **유지보수성 향상**:
   - 상세한 주석으로 코드 이해 용이
   - 문서화로 추후 수정 시 참고 가능

---

**작업 완료일**: 2025-12-21
**문서 버전**: 1.0
**작성자**: Claude Code
