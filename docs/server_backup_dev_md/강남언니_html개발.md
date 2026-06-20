# 강남언니 HTML 개발 진행 상황

**개발 시작일**: 2026-01-15
**프로젝트명**: CareBaby Web
**개발 방식**: 강남언니 디자인 참고 + 직접 재작성 + PHP 연동

---

## 📋 개발 완료 현황

### ✅ Phase 1: 프로젝트 구조 설정 (완료)

**작업 시간**: 30분

#### 생성된 폴더 구조
```
carebaby_web/
├── templates/              # HTML 템플릿
├── assets/
│   ├── css/               # CSS 스타일시트
│   ├── js/                # JavaScript 파일
│   └── images/            # 이미지 파일
├── includes/              # PHP 공통 파일
├── pages/                 # PHP 페이지
├── api/                   # REST API
├── config/                # 설정 파일
└── flutter/               # Flutter 연동 코드
```

**경로**: `h:/carebaby_project/server_backup_dev/carebaby_web/`

---

### ✅ Phase 2: CSS 개발 (완료)

**파일**: [assets/css/gangnam_style.css](carebaby_web/assets/css/gangnam_style.css)

#### 주요 기능
1. **색상 시스템**
   ```css
   --primary-color: #FF540F    /* 오렌지 (강남언니 메인 색상) */
   --bg-white: #FFFFFF
   --bg-gray: #F5F5F5
   --text-primary: #000000
   --text-secondary: #666666
   ```

2. **반응형 디자인**
   - Desktop (>1024px): 4열 그리드
   - Tablet (768-1024px): 3열 그리드
   - Mobile (<768px): 2열 그리드

3. **컴포넌트 스타일**
   - 헤더 (sticky, 65px)
   - 병원 카드 (hover 효과, shadow)
   - 시술 카드
   - 필터 버튼
   - 로딩 스피너
   - 하단 네비게이션 (모바일)

**라인 수**: 약 500줄

---

### ✅ Phase 3: HTML 템플릿 (완료)

#### 1. 병원 목록 페이지
**파일**: [templates/hospital_list.html](carebaby_web/templates/hospital_list.html)

**주요 섹션**:
- 헤더 (로고, 검색바, 로그인/회원가입)
- 필터 섹션 (지역, 정렬)
- 병원 그리드 (동적 로드)
- 더보기 버튼
- 하단 네비게이션 (모바일)

#### 2. 병원 카드 컴포넌트
**파일**: [templates/hospital_card.html](carebaby_web/templates/hospital_card.html)

**표시 정보**:
- 병원 이미지
- 병원 이름
- 위치
- 평점 (9.6)
- 리뷰 수 (평가 63,978개)
- 이벤트 수 (이벤트 70개)

---

### ✅ Phase 4: PHP 백엔드 (완료)

#### 1. 템플릿 로더
**파일**: [includes/template_loader.php](carebaby_web/includes/template_loader.php)

**클래스**: `TemplateLoader`

**메서드**:
```php
load($template_name, $data)           // 템플릿 로드 및 변수 치환
renderHospitalCard($hospital)         // 병원 카드 HTML 생성
renderTreatmentCard($treatment)       // 시술 카드 HTML 생성
renderHospitalList($hospitals, $total) // 병원 목록 페이지 렌더링
```

**기능**:
- HTML 템플릿 파일 읽기
- `{{변수명}}` 형식 치환
- 안전한 HTML 출력 (htmlspecialchars)

#### 2. 병원 목록 페이지
**파일**: [pages/hospital_list.php](carebaby_web/pages/hospital_list.php)

**기능**:
- DB에서 병원 목록 조회
- 정렬 (추천순, 평점순, 리뷰순)
- 페이징 (20개씩)
- 템플릿 렌더링

#### 3. 데이터베이스 설정
**파일**: [config/database.php](carebaby_web/config/database.php)

**설정**:
```php
DB_HOST: localhost
DB_NAME: carebaby
DB_USER: root
DB_CHARSET: utf8mb4
```

**PDO 연결**: 에러 처리 포함

---

### ✅ Phase 5: REST API (완료)

#### 1. 병원 API
**파일**: [api/hospitals.php](carebaby_web/api/hospitals.php)

**엔드포인트**:
```
GET /api/hospitals.php?page=1&limit=20&sort=rcmd&location=강남&keyword=성형
GET /api/hospitals.php?id=123
```

**기능**:
- 병원 목록 조회
- 병원 상세 조회
- 필터링 (지역, 키워드)
- 정렬 (추천순, 평점순, 리뷰순)
- 페이징
- CORS 지원

**응답 형식**:
```json
{
  "success": true,
  "data": {
    "hospitals": [...],
    "pagination": {
      "page": 1,
      "limit": 20,
      "total": 1457,
      "has_next": true
    }
  },
  "timestamp": "2026-01-15 12:34:56"
}
```

#### 2. 검색 API
**파일**: [api/search.php](carebaby_web/api/search.php)

**엔드포인트**:
```
GET /api/search.php?q=쌍꺼풀&category=2&page=1&limit=20
```

**기능**:
- 시술/병원 통합 검색
- 카테고리 필터
- 가격 정보 포함
- 할인율 계산

**응답 데이터**:
- 시술 이름
- 병원 정보
- 평점/리뷰
- 원가/할인가/할인율

---

### ✅ Phase 6: JavaScript (완료)

**파일**: [assets/js/hospital_list.js](carebaby_web/assets/js/hospital_list.js)

#### 주요 기능

1. **병원 목록 로드**
   ```javascript
   loadHospitals()           // API 호출하여 병원 목록 로드
   renderHospitals()         // 병원 카드 HTML 생성 및 삽입
   ```

2. **검색**
   ```javascript
   searchHospitals(keyword)  // 검색 API 호출
   ```

3. **정렬**
   - 추천순 (rcmd)
   - 평점순 (rating)
   - 리뷰순 (reviews)

4. **무한 스크롤**
   ```javascript
   loadMore()                // 다음 페이지 로드
   ```

5. **Flutter 연동**
   ```javascript
   openHospitalDetail(id)    // Flutter 앱에서 병원 상세 열기
   window.onUserInfoReceived // Flutter에서 사용자 정보 받기
   ```

---

### ✅ Phase 7: Flutter WebView 연동 (완료)

**파일**: [flutter/webview_screen.dart](carebaby_web/flutter/webview_screen.dart)

#### 클래스 구조

1. **CareBabyWebView**
   - WebView 컨테이너
   - JavaScript Bridge 관리
   - 로딩 상태 관리

2. **JavaScript Bridge**
   ```dart
   _controller.addJavaScriptChannel('FlutterChannel')
   ```

#### JavaScript → Flutter 메시지

**지원 액션**:
```dart
'open_hospital_detail'    // 병원 상세 (네이티브)
'open_booking'            // 예약하기 (네이티브)
'open_login'              // 로그인 (네이티브)
'get_user_info'           // 사용자 정보 요청
'show_toast'              // 토스트 메시지
'share'                   // 공유 기능
'set_title'               // 페이지 타이틀
```

#### Flutter → JavaScript 메시지

```dart
_sendUserInfoToWeb()      // 사용자 정보 전달
```

**JavaScript에서 사용**:
```javascript
window.userInfo           // 사용자 정보 객체
window.onUserInfoReceived // 콜백 함수
```

#### 네이티브 화면 연동

- `HospitalDetailScreen`: 병원 상세 (네이티브)
- `BookingScreen`: 예약하기 (네이티브)
- `LoginScreen`: 로그인 (네이티브)

---

## 🌐 로컬 테스트 주소

### PHP 내장 서버 실행

```bash
cd h:/carebaby_project/server_backup_dev/carebaby_web
php -S localhost:8000
```

### 접속 주소

#### 1. 웹 브라우저
```
http://localhost:8000/pages/hospital_list.php
```

#### 2. Flutter Android 에뮬레이터
```dart
CareBabyWebView(
  initialUrl: 'http://10.0.2.2:8000/pages/hospital_list.php',
  title: '병원 목록',
)
```

#### 3. Flutter iOS 시뮬레이터
```dart
CareBabyWebView(
  initialUrl: 'http://localhost:8000/pages/hospital_list.php',
  title: '병원 목록',
)
```

#### 4. 실제 기기 (같은 WiFi)
```dart
// 개발 PC IP 확인: ipconfig (Windows) / ifconfig (Mac/Linux)
CareBabyWebView(
  initialUrl: 'http://192.168.0.100:8000/pages/hospital_list.php',
  title: '병원 목록',
)
```

### API 테스트 주소

```
병원 목록: http://localhost:8000/api/hospitals.php?page=1&limit=5
검색:     http://localhost:8000/api/search.php?q=쌍꺼풀
병원 상세: http://localhost:8000/api/hospitals.php?id=1
```

---

## 📊 파일 통계

| 항목 | 개수 | 라인 수 |
|------|------|---------|
| CSS 파일 | 1 | 500 |
| HTML 템플릿 | 2 | 150 |
| PHP 파일 | 5 | 400 |
| JavaScript | 1 | 200 |
| Dart 파일 | 1 | 350 |
| **총계** | **10** | **1,600** |

---

## 🎯 구현된 기능

### ✅ 웹 기능

1. **병원 목록**
   - 그리드 레이아웃 (4열 → 3열 → 2열)
   - 병원 카드 (이미지, 이름, 위치, 평점, 리뷰 수, 이벤트 수)
   - 정렬 (추천순, 평점순, 리뷰순)
   - 무한 스크롤 (더보기)

2. **검색**
   - 검색창 (Enter 키 지원)
   - 키워드 검색
   - 카테고리 필터

3. **반응형 디자인**
   - 데스크톱 최적화
   - 태블릿 최적화
   - 모바일 최적화
   - 하단 네비게이션 (모바일)

### ✅ API 기능

1. **병원 API**
   - 목록 조회 (페이징, 정렬, 필터)
   - 상세 조회
   - CORS 지원
   - JSON 응답

2. **검색 API**
   - 통합 검색 (시술 + 병원)
   - 카테고리 필터
   - 가격 정보
   - 할인율 계산

### ✅ Flutter 연동

1. **WebView**
   - 웹 페이지 로드
   - 로딩 상태 표시
   - 새로고침 버튼

2. **JavaScript Bridge**
   - 양방향 통신
   - 네이티브 화면 열기
   - 사용자 정보 동기화
   - 토스트 메시지
   - 공유 기능

---

## 📝 데이터베이스 스키마

### hospitals 테이블
```sql
id              INT PRIMARY KEY AUTO_INCREMENT
name            VARCHAR(255)    -- 병원 이름
location        VARCHAR(255)    -- 위치
image_url       VARCHAR(500)    -- 이미지 URL
rating          DECIMAL(3,1)    -- 평점 (9.6)
description     TEXT            -- 설명
created_at      TIMESTAMP
```

### reviews 테이블
```sql
id              INT PRIMARY KEY AUTO_INCREMENT
hospital_id     INT             -- 병원 ID
user_id         INT             -- 사용자 ID
rating          INT             -- 평점
content         TEXT            -- 리뷰 내용
status          VARCHAR(20)     -- 상태 (pending, approved)
created_at      TIMESTAMP
```

### events 테이블
```sql
id              INT PRIMARY KEY AUTO_INCREMENT
hospital_id     INT             -- 병원 ID
treatment_id    INT             -- 시술 ID
status          VARCHAR(20)     -- 상태 (active, inactive)
created_at      TIMESTAMP
```

### treatments 테이블
```sql
id              INT PRIMARY KEY AUTO_INCREMENT
name            VARCHAR(255)    -- 시술 이름
hospital_id     INT             -- 병원 ID
category_id     INT             -- 카테고리 ID
image_url       VARCHAR(500)    -- 이미지 URL
created_at      TIMESTAMP
```

---

## 🚀 다음 개발 작업

### Phase 8: 추가 페이지 (예정)

1. **병원 상세 페이지**
   - 병원 정보 상세
   - 시술 목록
   - 리뷰 목록
   - 이벤트 목록
   - 예약하기 버튼

2. **검색 결과 페이지**
   - 시술 카드 그리드
   - 필터 (카테고리, 가격대)
   - 정렬 (평점, 가격, 할인율)

3. **메인 페이지**
   - 히어로 배너
   - 카테고리 그리드
   - 인기 병원
   - 의료 꿀팁

### Phase 9: Frezka 구독 연동 (예정)

1. **구독 플랜 페이지**
   - 플랜 카드 (기본, 프리미엄, 엔터프라이즈)
   - 가격 표시
   - 기능 비교

2. **결제 연동**
   - INIpay 연동
   - 결제 성공/실패 처리
   - Frezka subscriptions 테이블 업데이트

### Phase 10: SNS 로그인 (예정)

1. **카카오 로그인**
   - OAuth 2.0 연동
   - 사용자 정보 가져오기
   - CareBaby DB 연동

2. **네이버 로그인**
   - OAuth 2.0 연동
   - 프로필 정보 동기화

---

## 📈 개발 진행률

| Phase | 작업 | 상태 | 진행률 |
|-------|------|------|--------|
| Phase 1 | 프로젝트 구조 | ✅ 완료 | 100% |
| Phase 2 | CSS 개발 | ✅ 완료 | 100% |
| Phase 3 | HTML 템플릿 | ✅ 완료 | 100% |
| Phase 4 | PHP 백엔드 | ✅ 완료 | 100% |
| Phase 5 | REST API | ✅ 완료 | 100% |
| Phase 6 | JavaScript | ✅ 완료 | 100% |
| Phase 7 | Flutter 연동 | ✅ 완료 | 100% |
| Phase 8 | 추가 페이지 | ⏳ 예정 | 0% |
| Phase 9 | Frezka 구독 | ⏳ 예정 | 0% |
| Phase 10 | SNS 로그인 | ⏳ 예정 | 0% |

**전체 진행률**: **70%** (7/10 Phase 완료)

---

## 💰 예상 개발 비용

| Phase | 예상 시간 | 예상 비용 |
|-------|----------|----------|
| Phase 1-7 (완료) | 3일 | 100만원 |
| Phase 8 (추가 페이지) | 2일 | 70만원 |
| Phase 9 (Frezka 구독) | 2일 | 60만원 |
| Phase 10 (SNS 로그인) | 1일 | 40만원 |
| **총계** | **8일** | **270만원** |

**처음부터 개발 대비**: 16-24일 → **8일** (66% 단축)

---

## 🔗 참고 링크

- **프로젝트 폴더**: `h:/carebaby_project/server_backup_dev/carebaby_web/`
- **강남언니 웹사이트**: https://www.gangnamunni.com
- **활용 방안 문서**: [강남언니_HTML소스_활용방안.md](강남언니_HTML소스_활용방안.md)
- **개발 현황 문서**: [현재_개발_현황_01-15.md](현재_개발_현황_01-15.md)

---

## 📞 테스트 방법

### 1. PHP 서버 시작
```bash
cd h:/carebaby_project/server_backup_dev/carebaby_web
php -S localhost:8000
```

### 2. 브라우저 접속
```
http://localhost:8000/pages/hospital_list.php
```

### 3. Flutter 앱 연동
```dart
import 'package:flutter/material.dart';
import 'webview_screen.dart';

void main() {
  runApp(MaterialApp(
    home: CareBabyWebView(
      initialUrl: 'http://localhost:8000/pages/hospital_list.php',
      title: '병원 목록',
    ),
  ));
}
```

### 4. API 테스트 (Postman/curl)
```bash
# 병원 목록
curl http://localhost:8000/api/hospitals.php?page=1&limit=5

# 검색
curl "http://localhost:8000/api/search.php?q=쌍꺼풀"

# 병원 상세
curl http://localhost:8000/api/hospitals.php?id=1
```

---

**작성자**: Claude Code
**최종 업데이트**: 2026-01-15 (실시간 업데이트)
