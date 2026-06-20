# 서울미녀 프로젝트 갭 분석 보고서
**생성일**: 2026-02-08
**기준**: 113개 MD 문서 전체 분석 vs 현재 서버 상태

---

## 📊 전체 현황 요약

### 현재 서버 상태 (2026-02-08)
- **서버**: contabo1 (5.104.82.35)
- **경로**: `/var/www/html/.../hms-saas/`
- **강남언니 파일**: 150개
- **DB 테이블**: 176개
- **병원 데이터**: 99개
- **Git 커밋**: 2개
- **도메인**: https://hms.seoulbeauty.app

### MD 문서 분석 결과
- **총 MD 파일**: 113개
- **주요 프로젝트**:
  - carebaby (NFOR/Gnuboard 기반)
  - frezka (Laravel 10)
  - kivicare (Laravel 11)
  - hairwang (Gnuboard 5)
  - gangnamunni (서울미녀 서비스)
  - hospital-api (독립 REST API)

---

## ✅ 완료된 작업 (서버 배포 완료)

### 1. 핵심 인프라
- ✅ **통합 폴더 구조**: HMS + hairwang_full + gangnamunni 통합
- ✅ **DB 마이그레이션**: hairwang DB (175→176 테이블) localhost 이전
- ✅ **Apache 설정**: /hairwang Alias 추가
- ✅ **도메인 연결**: hms.seoulbeauty.app (Cloudflare)
- ✅ **Syncthing**: PC↔서버 자동 동기화
- ✅ **GitHub 연동**: carebaby-platform 레포 (2 commits)

### 2. 강남언니 서비스
- ✅ **메인 페이지** (`index.php`): 99개 병원 목록, 카테고리 필터
- ✅ **상세 페이지** (`detail.php`): 병원 정보, 리뷰, 이벤트
- ✅ **검색** (`search.php`): 병원 검색 기능
- ✅ **예약** (`booking.php`): 예약 폼 (placeholder)
- ✅ **팁** (`tips.php`): 시술 팁 20개
- ✅ **이벤트** (`events.php`): 이벤트 106개
- ✅ **테마 관리** (`admin_theme.php`): 관리자 테마 설정
- ✅ **리뷰 상세** (`review_detail.php`): 리뷰 상세 페이지

### 3. 개발 도구
- ✅ **랜딩 페이지** (`/1/`): 서울미녀 브랜딩, 3개 카드
- ✅ **개발 대시보드** (`dev_dashboard.php`):
  - 38개 태스크 관리
  - Chart.js 차트 2개
  - 단계별 진행률 (6단계)
  - DB 통계 (176 테이블, 99 병원, 77 회원)
- ✅ **DEV_SCENARIO.md**: 6단계 개발 로드맵
- ✅ **CSP 수정**: Tailwind, Google Fonts, Chart.js 허용

### 4. 데이터베이스
- ✅ **hairwang DB**: 176 테이블, 99 hospitals, 20 tips, 106 events
- ✅ **dev_log**: 38 tasks across 6 phases
- ✅ **g5_member**: 77 회원
- ✅ **hospital_reviews**: 5 리뷰
- ✅ **SQL 백업**: 10개 SQL dumps (24MB)

### 5. 문서화
- ✅ **WORKSPACE_REPORT.md**: 통합 폴더 구조 문서
- ✅ **TRANSFER_PROGRESS.md**: 서버 이전 완료 보고
- ✅ **DEV_SCENARIO.md**: 6단계 개발 시나리오
- ✅ **MEMORY.md**: Claude 메모리 업데이트
- ✅ **docs/**: 141개 MD 파일 통합

---

## ❌ 미완료 작업 (MD 문서에는 있으나 서버 미배포)

### 1. 인증/회원 시스템 (DEVELOPMENT_COMPLETE_v1.0.md)
- ❌ **register.html/php**: 회원가입 페이지
- ❌ **login.html/php**: 로그인 페이지
- ❌ **auth.js**: 인증 모듈 (isLoggedIn, getCurrentUser, logout)
- ❌ **users 테이블 데이터**: 현재 0건 (테이블은 존재)
- **영향**: 로그인 필요 기능 사용 불가 (마이페이지, 예약 내역, 찜하기)

### 2. 예약 시스템 백엔드 (DEVELOPMENT_COMPLETE.md)
- ❌ **hospital-api**: 독립 REST API 서버 (port 8080)
  - 20+ RESTful endpoints
  - Booking CRUD
  - Review CRUD
  - Hospital nearby search (Haversine)
- ❌ **hospital-frontend**: 프론트엔드 (port 3001)
- ❌ **hospital-admin**: 병원 관리자 패널 (port 3002)
- ❌ **bookings 테이블 데이터**: 현재 0건
- **영향**: 실제 예약 불가, 관리자 예약 관리 불가

### 3. 리뷰 에디터 (FULL_DEVELOPMENT_SCENARIO.md Phase 4-9)
- ❌ **editor/review_editor.php**: Quill.js 리뷰 에디터
- ❌ **editor/assets/editor.css**: 에디터 스타일
- ❌ **editor/assets/editor.js**: 에디터 스크립트
- ❌ **api/reviews.php**: 리뷰 API CRUD
- ❌ **api/upload_image.php**: 이미지 업로드
- ❌ **flutter/review_editor_webview.dart**: Flutter WebView
- **영향**: 리뷰 작성/수정 불가 (현재 5개 리뷰는 seed 데이터)

### 4. Kivicare 통합 (KIVICARE_INTEGRATION_COMPLETE.md)
- ❌ **KivicareAPI.php**: Kivicare Laravel API 래퍼 (350+ lines)
- ❌ **DataTransformer.php**: 데이터 변환 유틸 (400+ lines)
- ❌ **SettingsHelper.php**: 설정 관리 (300+ lines)
- ❌ **api.php 조건부 라우팅**: use_kivicare 플래그 기반
- ❌ **test_kivicare_integration.php**: 6개 테스트
- **영향**: Kivicare 시스템 연동 불가 (기능은 설계만 완료)

### 5. SNS 로그인 (SNS_PG연동_개발_완료_보고서.md)
- ❌ **api/sns/config.php**: 6개 SNS 설정 (Naver, Kakao, Google, FB, Twitter, Payco)
- ❌ **SNS OAuth 연동**: 26개 파일 (완성되었으나 미배포)
- ❌ **nfor_member_social_profiles**: SNS 프로필 테이블 (frezka DB에는 있음)
- **영향**: SNS 간편 로그인 불가

### 6. PG 결제 (sns_pg연동_개발_내역.md)
- ❌ **INICIS PG 연동**: INIpay 결제 모듈
- ❌ **nfor_addon_sns_pg_pg_api_orders**: 결제 주문 테이블
- ❌ **addon_sns_pg_api_keys**: API 키 관리 테이블
- **영향**: 결제 기능 없음

### 7. FCM 푸시 알림 (FCM알림설정체크결과.md)
- ✅ **push.lib.php**: FCM HTTP v1 API (367 lines) - 존재함
- ✅ **.env**: Firebase 설정 - 존재함
- ❌ **push_api.php**: 푸시 전송 API 엔드포인트 (배포 미확인)
- ❌ **g5_push_tokens**: 푸시 토큰 테이블 데이터
- **영향**: 실시간 알림 불가

### 8. App Admin 시스템 (addon_app_admin_개발_완료_보고서.md)
- ✅ **addon_app_admin/**: 폴더 존재
- ❌ **23개 파일 중 일부**: Phase 1만 완료 (13/23)
- ❌ **addon_app_admin_boards**: 게시판 관리 테이블
- ❌ **addon_app_admin_site_config**: 사이트 설정 테이블
- **영향**: 앱 관리 기능 제한적

### 9. Flutter 모바일 앱 (PENDING_TASKS_2026-01-29.md)
- ❌ **Flutter 앱**: 0% (60-100시간 예상)
- ❌ **한국어 지원**: 1,250+ 번역 키
- ❌ **INICIS PG 연동**
- ❌ **백엔드 연동**
- ❌ **FCM 푸시**
- **영향**: 모바일 네이티브 앱 없음 (현재는 웹만)

### 10. 커뮤니티 (DEVELOPMENT_COMPLETE_v1.0.md)
- ✅ **community_posts/comments 테이블**: 생성됨
- ❌ **community.php**: UI/API 미구현
- ❌ **데이터**: 0건
- **영향**: 커뮤니티 기능 없음

### 11. 즐겨찾기 (DEVELOPMENT_COMPLETE_v1.0.md)
- ✅ **favorites 테이블**: 생성됨
- ❌ **favorites API**: hospital-api에만 있음 (gangnamunni 미연동)
- ❌ **UI**: 하트 버튼 없음
- **영향**: 즐겨찾기 기능 없음

### 12. 보안 감사 수정 (gangnamunni-service 구 레포 commit 852d5ba)
- ❌ **sanitizeHtml()**: XSS 방어 함수
- ❌ **rate_limit()**: Rate limiting
- ❌ **CSRF protection**: CSRF 토큰
- **영향**: 보안 취약점 존재 가능

---

## 🔶 부분 완료 (일부만 배포)

### 1. 예약 기능
- ✅ **booking.php**: 예약 폼 UI 존재
- ❌ **백엔드 처리**: placeholder alert만 있음
- **현황**: 프론트엔드만 50% 완료

### 2. 검색 기능
- ✅ **search.php**: 검색 페이지 존재
- ❌ **고급 필터**: 지역, 카테고리, 가격대 필터 미구현
- **현황**: 기본 검색만 가능

### 3. 상담 기능
- ✅ **detail.php**: 상담 버튼 존재
- ❌ **처리 로직**: placeholder alert만
- **현황**: UI만 존재

### 4. 카카오맵
- ✅ **detail.php**: 지도 영역 존재
- ❌ **Kakao Map API**: 통합 안됨
- **현황**: Placeholder만

---

## 📈 프로젝트 완성도

| 카테고리 | 완료율 | 비고 |
|---------|-------|-----|
| **인프라** | 100% | DB, 서버, Git, 도메인 완료 |
| **강남언니 UI** | 80% | 주요 페이지 완료, 일부 기능 placeholder |
| **백엔드 API** | 30% | hospital-api 미배포, gangnamunni API 부분적 |
| **인증/회원** | 10% | 테이블만, 코드 없음 |
| **예약 시스템** | 20% | UI만, 백엔드 없음 |
| **리뷰 시스템** | 30% | Seed 데이터만, 에디터 없음 |
| **SNS 로그인** | 0% | 코드 완성, 배포 안됨 |
| **PG 결제** | 0% | 미구현 |
| **FCM 푸시** | 50% | 코드 있음, 엔드포인트 미확인 |
| **모바일 앱** | 0% | 미착수 |
| **커뮤니티** | 5% | 테이블만 |
| **즐겨찾기** | 5% | 테이블만 |
| **개발 도구** | 90% | 대시보드, DEV_SCENARIO 완료 |
| **문서화** | 95% | 141개 MD 완료 |
| **전체** | **45%** | **인프라 완료, 기능 개발 필요** |

---

## 🎯 우선순위별 작업 계획

### Priority 1 (긴급 - 1-2주)
1. **인증 시스템 배포** (6-8시간)
   - register.html/php, login.html/php
   - auth.js 모듈
   - 세션 관리

2. **hospital-api 배포** (4-6시간)
   - Port 8080으로 실행
   - PM2로 프로세스 관리
   - gangnamunni와 연동

3. **리뷰 작성 UI** (3-4시간)
   - review_editor.php
   - Quill.js 통합
   - 이미지 업로드

4. **즐겨찾기 UI** (2-3시간)
   - 하트 버튼
   - 내 즐겨찾기 페이지

**총**: 15-21시간

### Priority 2 (중요 - 1개월)
1. **SNS 로그인 배포** (8-12시간)
   - config.php 배포
   - OAuth 콜백 설정
   - 6개 SNS 연동 테스트

2. **FCM 푸시 완성** (6-8시간)
   - push_api.php 배포
   - 토큰 저장 로직
   - 테스트 전송

3. **커뮤니티 기능** (6-8시간)
   - community.php UI
   - API CRUD
   - 게시글/댓글

4. **보안 강화** (8-12시간)
   - XSS 방어
   - CSRF 토큰
   - Rate limiting
   - SQL Injection 재검증

**총**: 28-40시간

### Priority 3 (장기 - 2-3개월)
1. **Kivicare 통합** (12-16시간)
   - 3개 PHP 클래스 배포
   - api.php 조건부 라우팅
   - 테스트

2. **PG 결제** (12-16시간)
   - INICIS 연동
   - 주문 테이블
   - 결제 프로세스

3. **Flutter 앱** (60-100시간)
   - 환경 구축
   - 한국어 지원
   - PG 연동
   - 백엔드 연동
   - 배포

4. **성능 최적화** (8-12시간)
   - 쿼리 최적화
   - 캐싱 (Redis)
   - CDN
   - 이미지 최적화

**총**: 92-144시간

---

## 💡 즉시 실행 가능한 간단한 개선

1. **Git 커밋 미완료 파일 커밋** (5분)
   ```bash
   cd /var/www/html/.../hms-saas
   git add .
   git commit -m "fix: update CSP, landing page, dashboard v2"
   git push
   ```

2. **users 테이블 샘플 데이터** (5분)
   ```sql
   INSERT INTO users (name, email, password, phone)
   VALUES ('테스트사용자', 'test@test.com', PASSWORD_BCRYPT_HASH, '010-1234-5678');
   ```

3. **bookings 테이블 샘플 데이터** (5분)
   ```sql
   INSERT INTO bookings (hospital_id, patient_name, booking_date, booking_time)
   VALUES (1, '김철수', '2026-02-15', '14:00');
   ```

4. **community_posts 샘플 데이터** (5분)
   ```sql
   INSERT INTO community_posts (category, title, content, author_id)
   VALUES ('review', '강남 피부과 후기', '시술 받았는데 만족합니다', 1);
   ```

5. **README.md 업데이트** (10분)
   - 현재 상태 반영
   - 갭 분석 링크
   - 실행 방법

---

## 📋 결론

### 완료된 것
- ✅ 인프라 100% (서버, DB, Git, 도메인)
- ✅ 강남언니 UI 80% (주요 페이지)
- ✅ 개발 도구 90% (대시보드, 문서)

### 남은 것
- ❌ 백엔드 API 연동 (70% 미완)
- ❌ 인증/회원 시스템 (90% 미완)
- ❌ SNS/PG 연동 (100% 미완)
- ❌ Flutter 앱 (100% 미완)

### 추천 로드맵
**Week 1-2**: Priority 1 완료 (21시간) → 기본 기능 완성
**Month 1**: Priority 2 완료 (40시간) → 실사용 가능
**Month 2-3**: Priority 3 완료 (144시간) → 프로덕션 준비

**총 예상 시간**: 205시간 (약 3개월, 주 15-20시간 작업 시)

---

**보고서 종료**
