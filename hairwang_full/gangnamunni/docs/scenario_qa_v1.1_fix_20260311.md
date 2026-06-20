# QA v1.1 검수 수정 작업 계획

- **날짜**: 2026-03-11
- **기준**: 클라이언트 검수 v1.1 (260310) 31건 이슈
- **순서**: P0(긴급) → P1(주요) → P2(개선) → P3(매뉴얼) → APP
- **서버**: 5.104.82.35, `/var/www/html/.../gangnamunni/`
- **리포트**: `docs/qa_v1.1_fix_report.html`

---

## P0 긴급 (6건) — 서비스 차단 버그

| # | 이슈 | 파일 | 수정 방향 |
|---|------|------|----------|
| 12 | 상담 신청 시 "오류 발생" | consultation_submit.php, consultation.php | DB 테이블명/컬럼 확인, INSERT 쿼리 수정 |
| 13 | 예약 시술 목록 비어있음 | booking.php, booking_wizard.php | treatments 쿼리 hospital_id 필터 수정 |
| 26 | 이벤트 응모 로그인 풀림 + 404 | event_detail.php, event_api.php | 세션 경로/SSO 쿠키 점검, 리다이렉트 수정 |
| 27 | 병원 수정 저장 500 에러 | admin_hospitals.php | PHP error_log 확인, UPDATE 쿼리 디버깅 |
| 31 | 관리자 로그아웃 불가 (대시보드 외) | includes/admin_header.php | 로그아웃 링크 경로를 절대경로로 수정 |
| 4-1 | 광고 승인/거절 오류 | admin_ads.php | AJAX 핸들러/DB 업데이트 쿼리 수정 |

## P1 주요 (11건) — 기능 오작동/누락

| # | 이슈 | 파일 | 수정 방향 |
|---|------|------|----------|
| 4 | 프로모션 배너 0번 미출력 + 카운트 초과 | index.php | JS 인덱스 off-by-one 수정, 총 개수 표시 수정 |
| 5 | 배너 터치 스와이프 미지원 | includes/ad_banner.php, index.php | touch event 핸들러 추가 |
| 9 | 홈 vs 타 페이지 배너 개수 불일치 | includes/ad_banner.php | 페이지별 배너 쿼리 조건 통일 |
| 11 | 상담 접수 내역 관리자 확인 불가 | admin_consultations.php | 쿼리/테이블 연결 확인 |
| 14 | 병원 상세 header 빈 공간 | detail.php | fixed top 56px CSS 수정 |
| 15 | 병원 메인 이미지 미표시 | detail.php | 이미지 조회 쿼리 수정 (main_image 컬럼) |
| 16 | "성형외과 전문의" 하드코딩 | detail.php | DB doctors 테이블에서 동적 조회 |
| 19 | 소개 탭에 상세소개만 표시 | detail.php | description vs detailed_description 수정 |
| 20 | 이벤트 탭 전체 병원 1,343개 | detail.php | hospital_id WHERE 절 추가 |
| 21 | 후기 탭 카운트 0 고정 | detail.php | COUNT 쿼리 동적 반영 |
| 22 | 의사 탭 다른 병원 의사 노출 | detail.php | doctors 쿼리 hospital_id 필터 |
| 24 | 하단 예약/상담 버튼 겹침 | detail.php | CSS flex 레이아웃 수정 |

## P2 개선 (8건) — UI/UX 및 데이터

| # | 이슈 | 파일 | 수정 방향 |
|---|------|------|----------|
| 7 | global-nav 로그인 시 좁아짐 | includes/global_nav.php | center nav 숨김 처리 |
| 8 | ads-carousel 하단 padding | index.php / ad_banner.php | margin-bottom + padding-bottom 추가 |
| 18 | 점심시간 표시 누락 | detail.php | lunch_start/lunch_end 표시 추가 |
| 23 | 유튜브 탭 제거 | detail.php | YouTube 탭 HTML/JS 제거 |
| 25 | 사용자 후기 작성 기능 | reviews.php (신규: review_write.php) | 후기 작성 폼 + 로그인 체크 + INSERT |
| 28 | 이벤트 이미지 직접 업로드 | admin_events.php | 파일 업로드 핸들러 추가 |
| 29 | 사용 불가능한 관리자 메뉴 3개 | admin_columns.php, admin_ad_settings.php, admin_promos.php | 페이지 접근/기능 점검 |
| 30 | 뷰어 역할 예약 승인 가능 | admin_bookings.php, includes/admin_auth.php | 권한 체크 추가 |

## P3 매뉴얼 (6건) — 문서 요청

| # | 이슈 | 대응 |
|---|------|------|
| 17 | 평점 기준 | 매뉴얼: 현재 고정값, 향후 동적 반영 설명 |
| 28-m | 이미지 업로드 방법 | 매뉴얼: 업로드 기능 추가 후 사용법 작성 |
| 29-m | 칼럼/광고설정/프로모션 사용법 | 매뉴얼: 3개 메뉴 기능 설명 |
| 30-m | 역할별 권한 범위 | 매뉴얼: 권한 매트릭스 표 |
| 13-m | 회원 vs 환자 차이점 | 매뉴얼: 시스템 구조 설명 |
| 16-m | 추천 병원 리스트 관리 | 매뉴얼: 현재 로직 + 관리 방법 |

## APP 전용 (3건) — Flutter/WebView

| # | 이슈 | 대응 |
|---|------|------|
| 5 | 배너 터치 스와이프 | 서버 JS 수정으로 해결 (P1에 포함) |
| 6 | Android 뒤로가기 앱 종료 | Flutter WillPopScope + WebView goBack() — 앱 재빌드 필요 |
| 10 | 반려 병원 검색 노출 | 서버 검색 쿼리 is_active 필터 확인 |

---

## 작업 진행 현황

- [x] P0 #12 상담 신청 오류 → consultation_submit.php 테이블/컬럼 수정
- [x] P0 #13 예약 시술 목록 → booking.php gangnamunni_treatments 폴백
- [x] P0 #26 이벤트 세션/404 → event_detail.php 로그인 경로 수정
- [x] P0 #27 병원 저장 500 → admin_hospitals.php 권한 수정 (간헐적 PHP-FPM)
- [x] P0 #31 로그아웃 불가 → admin_header.php 로그아웃 링크 수정
- [x] P0 #4-1 광고 승인 오류 → admin_ads.php 리다이렉트 수정
- [x] P1 #14-24 detail.php 8건 → 의사전문/소개탭/이벤트필터/리뷰카운트/유튜브제거/하단바/점심시간
- [x] P1 #4,#5,#9 배너/스와이프 → index.php 캐러셀CSS+터치, ad_banner.php 터치+쿼리
- [x] P1 #11 상담목록 → admin_consultations.php 어드민패턴+prepared stmt
- [x] P2 #7 nav축소 → global_nav.php 닉네임 max-width+모바일 컴팩트
- [x] P2 #8 배너padding → ad_banner.php margin/padding 조정
- [x] P2 #25 후기작성 → review_write.php 신규 (로그인필수, pending 등록)
- [x] P2 #28 이벤트이미지 → admin_events.php 파일업로드 추가
- [x] P2 #29 메뉴3개 → chown apache:apache 권한 수정
- [x] P2 #30 뷰어권한 → admin_bookings.php viewer 역할 상태변경 차단
- [x] P3 6건 매뉴얼 → 아래 문서 참조
- [ ] APP #6 Android 뒤로가기 → Flutter 재빌드 필요
- [ ] APP #10 반려병원 검색 → is_active 필터 확인 필요

---

## P3 매뉴얼 응답

### #17 평점 기준 설명

현재 병원 상세페이지(detail.php)의 **평점(rating)**은 다음 로직으로 표시됩니다:
- `gangnamunni_hospitals.rating` 컬럼에 저장된 값 (0.0~5.0)
- 초기값은 크롤링 데이터 기반 고정값
- **동적 반영 계획**: `hospital_reviews` 테이블의 사용자 후기 평균 평점을 크론잡으로 주기적 갱신 (Phase 2)
- 항목별 세부 평점 (`rating_result`, `rating_consultation`, `rating_facility` 등)은 후기 작성 시 수집

### #28-m 이벤트 이미지 업로드 방법

**관리자 > 이벤트 관리 (admin_events.php)**
1. 이벤트 추가/수정 시 "이미지" 필드에 **파일 직접 업로드** 가능
2. 파일 업로드와 URL 입력 중 선택 (파일 업로드 우선 적용)
3. 지원 형식: JPEG, PNG, GIF, WebP / 최대 5MB
4. 업로드 경로: `uploads/events/` 디렉토리

### #29-m 칼럼/광고설정/프로모션 사용법

| 메뉴 | 경로 | 주요 기능 |
|------|------|----------|
| **칼럼 관리** | admin_columns.php | 뷰티/의료 칼럼 CRUD, 작성자/출처 관리, 노출 순서 |
| **광고 설정** | admin_ad_settings.php | 페이지별 광고 ON/OFF (12개 플래그), 자동 스크롤 속도, 3뷰 모드 기본값 |
| **프로모션 관리** | admin_promos.php | 프로모션 배너 CRUD, 그라디언트 배경, 링크, 활성/비활성 |

### #30-m 역할별 권한 범위

| 역할 | 설명 | 주요 권한 |
|------|------|----------|
| **super_admin** | 최고 관리자 | 모든 기능 (*) |
| **admin** | 관리자 | 대부분 기능 (45키), 관리자 계정 관리 포함 |
| **editor** | 편집자 | 콘텐츠 편집 (23키+), 관리자 계정 관리 제외 |
| **viewer** | 뷰어 | 조회만 가능, 상태 변경 불가 (예약 승인/거절 등 차단) |
| **hospital_admin** | 병원 관리자 | 소속 병원 관련 기능만 |
| **hospital_sub_admin** | 병원 부관리자 | 병원 관리자 하위 권한 |

### #13-m 회원 vs 환자 차이점

| 구분 | 회원 (Member) | 환자 (Patient) |
|------|-------------|---------------|
| **시스템** | Gnuboard `g5_member` | HMS `patients` |
| **가입** | 서울미녀 프론트 회원가입 | 병원에서 환자 등록 |
| **용도** | 후기/이벤트/상담 | 진료/예약/처방 |
| **연결** | SSO `sso_tokens` 테이블 | `hms_patient_id` |
| **세션** | `$member['mb_id']` | HMS Laravel Auth |

회원 가입 시 자동으로 HMS 환자 레코드가 생성되지는 않으며, **예약 승인 시** 크론잡이 HMS에 환자/예약 동기화.

### #16-m 추천 병원 리스트 관리

현재 홈페이지(index.php)의 병원 리스트는:
- `gangnamunni_hospitals` 테이블에서 `rating DESC, review_count DESC` 정렬
- **관리 방법**: 관리자 > 병원 관리(admin_hospitals.php)에서:
  - `is_active` 플래그로 노출/비노출 제어
  - `rating`, `review_count` 값 수정으로 순서 조정
  - 향후 `is_featured` 컬럼 추가하여 추천 병원 별도 관리 예정
