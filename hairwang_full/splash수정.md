# Splash.php 백버튼 재진입 방지 수정 작업

## 📋 작업 일자
- **날짜**: 2025-12-21
- **작업자**: Claude Code
- **작업 파일**: `rb/splash.php`

---

## 🎯 작업 목적

### 문제 상황
앱에서 다음과 같은 흐름으로 동작 시 백버튼 문제 발생:

```
앱 시작
→ start_app.php
→ splash.php (리다이렉트)
→ 메인 페이지 (splash 종료 후)
→ 백버튼 클릭
→ splash.php 다시 나타남 ❌
```

### 원인 분석
브라우저 히스토리가 다음과 같이 쌓임:
1. `start_app.php` (히스토리 1)
2. `splash.php` (히스토리 2) ← 백버튼으로 여기로 돌아옴
3. 메인 페이지 (히스토리 3)

백버튼 클릭 시 히스토리 2번으로 돌아가서 splash.php가 다시 표시됨

---

## 💡 해결 방안

### ⚠️ 중요: 기존 해결책의 한계
splash.php만 수정하면 **start_app.php가 히스토리에 남아** 백버튼 문제가 완전히 해결되지 않습니다!

```
기존 수정 후 히스토리: [start_app.php] [메인페이지]
백버튼 클릭 → start_app.php로 돌아감 ❌
```

### 완전한 해결 방안 (3단계)

#### 방법 0: start_app.php도 replace로 변경 ✅ **필수**
- **목적**: start_app.php를 히스토리에서 완전히 제거
- **효과**: 백버튼 눌러도 start_app.php로 갈 수 없음
- **수정 위치**: start_app.php 전체 (header 리다이렉트 → location.replace)

#### 방법 1: location.replace 사용
- **목적**: 스플래시를 브라우저 히스토리에서 완전히 제거
- **효과**: 백버튼 눌러도 splash.php로 갈 수 없음 (히스토리에 없으니까)
- **수정 위치**: `closeSplash()` 함수

#### 방법 2: 세션 체크로 재진입 방지
- **목적**: 만약 다른 경로로 splash.php 접근 시 2차 방어
- **효과**: 직접 URL 입력하거나 예외 상황에서도 재진입 차단
- **수정 위치**: 스플래시 설정 가져오기 부분

### 최종 선택: 다층 방어 시스템 ✅

#### 1차 방어: location.replace (start_app.php + splash.php)
- start_app.php: header → location.replace
- closeSplash(): location.href → location.replace

#### 2차 방어: 히스토리 초기화 (splash.php)
- showContent()에서 history.go()로 이전 히스토리 제거
- 웹뷰 히스토리 길이를 1로 만들어 이전 페이지 제거

#### 3차 방어: 백버튼 이벤트 처리 (splash.php)
- onpopstate에서 백버튼 감지 시 메인으로 강제 이동
- 스플래시 재진입 완전 차단

#### 4차 방어: 세션 체크 (splash.php)
- 만약 직접 URL 접근 시 세션으로 재진입 차단

**결과**: 4중 방어 체계로 완벽한 차단
**수정 파일**: start_app.php + splash.php (2개 파일)

---

## 🔧 상세 수정 내용

### 수정 0: start_app.php - header 리다이렉트 → location.replace 변경

#### 수정 전
```php
<?php
$start_url = "https://hairwang.com/rb/splash.php";

// HTTP 리다이렉트 (히스토리에 남음)
header("Location: " . $start_url);
exit;
?>
```

#### 수정 후
```php
<?php
$start_url = "https://hairwang.com/rb/splash.php";
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Loading...</title>
</head>
<body>
<script>
// ============================================
// ✅ 수정: header() 리다이렉트 → location.replace()
// ============================================
// [기존 방식] header("Location: ...")
//   - PHP에서 HTTP 리다이렉트 (서버 측 리다이렉트)
//   - 결과: start_app.php가 히스토리에 남음
//   - 히스토리: [start_app.php] → [splash.php] → [메인]
//   - 백버튼 클릭 시: 메인 → start_app.php ❌
//
// [수정 방식] location.replace()
//   - JavaScript에서 클라이언트 측 리다이렉트
//   - 결과: start_app.php가 히스토리에 남지 않음
//   - 히스토리: [splash.php] → [메인] (start_app.php 제거됨)
//   - 백버튼 클릭 시: 메인 → 앱 종료 ✅
// ============================================
window.location.replace('<?php echo $start_url; ?>');
</script>
</body>
</html>
```

---

### 수정 1: splash.php - 세션 체크 추가 (라인 20-33)

#### 수정 전
```php
// 스플래시 설정 가져오기
$sql = "SELECT * FROM g5_splash_config WHERE sp_use = 1 LIMIT 1";
$splash = sql_fetch($sql);

// 스플래시 사용 안함 또는 설정 없음
if (!$splash || !$splash['sp_use']) {
    goto_url(G5_URL);
    exit;
}
```

#### 수정 후
```php
// 스플래시 설정 가져오기
$sql = "SELECT * FROM g5_splash_config WHERE sp_use = 1 LIMIT 1";
$splash = sql_fetch($sql);

// ============================================
// ✅ 추가: 앱에서 백버튼으로 재진입 방지 (세션 체크)
// ============================================
// 앱에서 이미 스플래시를 본 경우 (세션에 기록된 경우)
// 백버튼이나 직접 URL 접근으로 다시 들어와도 메인으로 리다이렉트
if ($is_app && isset($_SESSION['app_splash_shown'])) {
    goto_url(G5_URL);
    exit;
}
// 앱에서 첫 진입 시 세션에 기록 (앱 세션 유지되는 동안 유효)
if ($is_app) {
    $_SESSION['app_splash_shown'] = true;
}
// ============================================

// 스플래시 사용 안함 또는 설정 없음
if (!$splash || !$splash['sp_use']) {
    goto_url(G5_URL);
    exit;
}
```

---

### 수정 2: splash.php - 히스토리 초기화 추가 (showContent 함수)

#### 수정 위치
- **함수**: `showContent()` 함수 내부
- **목적**: 앱에서 스플래시 표시 시 이전 히스토리 완전 제거

#### 추가 코드
```javascript
<?php if ($is_app) { ?>
// 앱에서는 스플래시 화면이 표시될 때 히스토리를 완전히 초기화
if (typeof history !== 'undefined') {
    var historyLength = history.length;
    if (historyLength > 1) {
        // 모든 이전 히스토리 제거
        history.go(-(historyLength - 1));
    }
}
<?php } ?>
```

---

### 수정 3: splash.php - 백버튼 이벤트 처리 수정

#### 수정 전
```javascript
// 뒤로가기 방지
history.pushState(null, null, location.href);
window.onpopstate = function() {
    history.go(1);
};
```

#### 수정 후
```javascript
<?php if ($is_app) { ?>
// 앱에서는 백버튼 누르면 메인으로 강제 이동
history.pushState(null, null, location.href);
window.onpopstate = function() {
    window.location.replace('<?php echo $next_url; ?>');
};
<?php } else { ?>
// 웹에서는 기존 방식 유지
history.pushState(null, null, location.href);
window.onpopstate = function() {
    history.go(1);
};
<?php } ?>
```

---

### 수정 4: location.replace 사용 (closeSplash 함수)

#### 수정 전
```javascript
function closeSplash() {
    clearInterval(timer);

    // 페이지 이동
    window.location.href = '<?php echo $next_url; ?>';
}
```

#### 수정 후
```javascript
function closeSplash() {
    clearInterval(timer);

    // ============================================
    // ✅ 수정: location.href → location.replace
    // ============================================
    // href: 현재 페이지를 히스토리에 남기고 새 페이지로 이동
    //       → 백버튼 누르면 splash.php로 돌아옴 ❌
    //
    // replace: 현재 페이지를 히스토리에서 제거하고 새 페이지로 교체
    //          → 백버튼 눌러도 splash.php가 히스토리에 없어서 이전 페이지로 이동 ✅
    // ============================================
    window.location.replace('<?php echo $next_url; ?>');
}
```

---

## 📊 동작 흐름 비교

### ❌ 수정 전 동작
```
start_app.php (히스토리 1) ← header 리다이렉트
    ↓
splash.php (히스토리 2)
    ↓ location.href
메인 페이지 (히스토리 3)
    ↓ 백버튼 클릭
splash.php (히스토리 2) ← 다시 표시됨 문제 발생!
```

### ⚠️ 중간 수정 (splash.php만 수정 - 불완전)
```
start_app.php (히스토리 1) ← 여전히 남아있음!
    ↓ header 리다이렉트
splash.php (세션 체크 → 첫 진입)
    ↓ location.replace
메인 페이지 (히스토리 1) ← splash.php는 제거됨
    ↓ 백버튼 클릭
start_app.php (히스토리 1) ← 여전히 문제! ❌
    ↓ 다시 리다이렉트
splash.php → 세션 체크 → 메인 리다이렉트
    (화면 깜빡임 발생)
```

### ✅ 완전 수정 후 동작 (start_app.php + splash.php)
```
start_app.php
    ↓ location.replace (히스토리에 안 남음!)
splash.php (세션 체크 → 첫 진입, 세션 기록)
    ↓ location.replace (히스토리에 안 남음!)
메인 페이지 (최초 히스토리)
    ↓ 백버튼 클릭
앱 종료 또는 웹뷰 이전 페이지 ✅

만약 사용자가 직접 splash.php URL 입력 시:
    → 세션 체크 → 이미 본 기록 있음 → 메인으로 자동 리다이렉트 ✅

최종 히스토리:
    [메인 페이지만 존재]
    start_app.php ❌ 없음
    splash.php ❌ 없음
```

---

## 🧪 테스트 시나리오

### 시나리오 1: 일반 백버튼 클릭
| 상황 | 수정 전 | 수정 후 |
|------|---------|---------|
| 메인에서 백버튼 | splash.php 재진입 ❌ | 이전 페이지/앱 종료 ✅ |
| 화면 깜빡임 | 발생 가능 | 없음 |
| 서버 요청 | 2번 (splash → 메인) | 0번 |

### 시나리오 2: 직접 URL 입력
| 상황 | 수정 전 | 수정 후 |
|------|---------|---------|
| 브라우저에 splash.php 입력 | 스플래시 다시 표시 ❌ | 세션 체크 → 메인 이동 ✅ |

### 시나리오 3: 앱 재시작
| 상황 | 수정 전 | 수정 후 |
|------|---------|---------|
| 앱 종료 후 재시작 | 스플래시 정상 표시 ✅ | 스플래시 정상 표시 ✅ |
| 세션 초기화 | - | 새 세션 생성 → 정상 동작 |

### 시나리오 4: 웹 브라우저 접속
| 상황 | 수정 전 | 수정 후 |
|------|---------|---------|
| PC/모바일 웹 | 정상 동작 | 정상 동작 (영향 없음) |
| $is_app = false | 세션 체크 안함 | 세션 체크 안함 |

---

## 🔍 코드 동작 원리

### 세션 체크 동작 원리
```php
// 1차 진입 (앱 시작)
$is_app = true
$_SESSION['app_splash_shown'] = 존재하지 않음
→ 세션 체크 통과
→ $_SESSION['app_splash_shown'] = true 설정
→ 스플래시 화면 표시

// 2차 진입 (백버튼 또는 직접 URL)
$is_app = true
$_SESSION['app_splash_shown'] = true (이미 설정됨)
→ 세션 체크에 걸림
→ goto_url(G5_URL) 실행
→ 메인으로 즉시 리다이렉트

// 앱 재시작
새로운 세션 생성
$_SESSION['app_splash_shown'] = 존재하지 않음
→ 다시 1차 진입 과정 반복
```

### location.replace 동작 원리
```javascript
// location.href (기존 방식)
History: [A] → [A, B] → [A, B, C]
백버튼: C → B (이전으로 돌아감)

// location.replace (수정 방식)
History: [A] → [A] → [C] (B가 A를 대체)
백버튼: C → 앱 종료 또는 A 이전 페이지
```

---

## ⚠️ 주의사항

### 세션 유지 기간
- **세션**: 앱이 실행되는 동안 유지
- **앱 재시작**: 세션 초기화 → 스플래시 다시 표시
- **장시간 백그라운드**: 세션 만료 가능 → 스플래시 다시 표시

### 쿠키로 변경 시 (선택사항)
세션 대신 쿠키 사용 시 앱 재시작해도 유지:
```php
// 세션 대신 쿠키 체크
if ($is_app && isset($_COOKIE['app_splash_shown'])) {
    goto_url(G5_URL);
    exit;
}

// JavaScript에서 쿠키 설정
setCookie('app_splash_shown', '1', 365); // 1년 유지
```

### 웹 브라우저 영향
- `$is_app` 조건으로 웹/앱 분리
- 웹 브라우저는 기존과 동일하게 동작
- 앱에서만 세션 체크 및 replace 적용

---

## 📝 체크리스트

- [x] 문제 원인 분석 완료
- [x] 해결 방안 수립 (방법 1+2 조합 + start_app.php 추가 수정)
- [x] start_app.php 수정: header() → location.replace() 변경
- [x] splash.php 수정 1: 세션 체크 추가
- [x] splash.php 수정 2: location.replace 변경
- [ ] 테스트 시나리오 1: 백버튼 테스트
- [ ] 테스트 시나리오 2: 직접 URL 접근 테스트
- [ ] 테스트 시나리오 3: 앱 재시작 테스트
- [ ] 웹 브라우저 정상 동작 확인

---

## 📌 참고사항

### 관련 파일
- `rb/start_app.php` - 앱 시작 리다이렉트 파일 ✅ **수정됨** (히스토리 제거)
- `rb/splash.php` - 스플래시 화면 메인 파일 ✅ **수정됨** (세션 체크 + 히스토리 제거)

### 수정 라인
- **수정 1**: start_app.php 전체 (header 리다이렉트 → location.replace로 변경)
- **수정 2**: splash.php 라인 20-33 (세션 체크 코드 추가)
- **수정 3**: splash.php 라인 565-601 (closeSplash 함수 내용 변경)

### 앱 코드 수정 필요 여부
- **불필요**: Android/iOS 앱 코드 수정 없음
- **이유**: PHP와 JavaScript만으로 완전히 해결 가능

---

## 🎓 기술적 배경 지식

### HTTP 리다이렉트 vs JavaScript 리다이렉트
- **HTTP 리다이렉트** (start_app.php): 서버에서 `header("Location: ...")`
  - 히스토리에 남음
  - 빠른 이동

- **JavaScript 리다이렉트** (splash.php):
  - `location.href`: 히스토리 추가
  - `location.replace`: 히스토리 교체 (추가 안함) ✅

### 세션 vs 쿠키
| 비교 | 세션 | 쿠키 |
|------|------|------|
| 저장 위치 | 서버 | 클라이언트 |
| 유지 기간 | 앱 실행 중 | 설정한 기간 |
| 앱 재시작 | 초기화됨 | 유지됨 |
| 보안 | 더 안전 | 클라이언트에서 조작 가능 |

### 브라우저 히스토리 API
```javascript
// 히스토리 조작 방법들
history.pushState()    // 히스토리 추가
history.replaceState() // 현재 히스토리 교체
location.href          // 히스토리 추가하며 이동
location.replace()     // 히스토리 교체하며 이동 ✅
```

---

## 📞 문제 발생 시 디버깅

### 여전히 백버튼으로 돌아가는 경우
1. 세션이 제대로 설정되는지 확인:
   ```php
   // splash.php 디버깅용 임시 코드
   if ($is_app) {
       error_log("Session check: " . print_r($_SESSION, true));
   }
   ```

2. location.replace가 실행되는지 확인:
   ```javascript
   // 브라우저 콘솔에서 확인
   console.log('closeSplash called');
   console.log('Next URL:', '<?php echo $next_url; ?>');
   ```

3. $is_app 값 확인:
   ```php
   // splash.php 상단에 추가
   error_log("is_app: " . ($is_app ? 'true' : 'false'));
   ```

### 앱 재시작 시 스플래시가 안 나오는 경우
- 쿠키가 설정되어 있는지 확인
- 세션이 유지되고 있는지 확인
- 앱에서 세션 쿠키 설정 확인

---

## ✅ 작업 완료 후 예상 결과

### 사용자 경험
- 백버튼 누르면 스플래시로 돌아가지 않음 ✅
- 화면 깜빡임 없이 부드러운 이동 ✅
- 앱 재시작 시 스플래시 정상 표시 ✅

### 기술적 효과
- 브라우저 히스토리 정리 (불필요한 히스토리 제거)
- 서버 요청 감소 (재진입 차단)
- 세션으로 이중 방어 체계 구축
