# 광고 시스템 개발 문서

## 개발 목표

서울미녀(Seoul Beauty) 플랫폼의 광고 관리 시스템을 구축한다.
병원(광고주)이 광고를 등록/결제하고, 사용자 홈페이지에서 광고 노출/클릭을
추적하여 통계를 제공하는 전체 파이프라인을 구현한다.

### 시스템 아키텍처

```
[병원(광고주)] → [광고 등록] → [결제(PortOne)] → [광고 활성화]
                                                      ↓
[사용자 홈페이지] → 광고 노출 → [impressions 기록]
                  → 광고 클릭 → [clicks 기록]
                                                      ↓
[관리자 대시보드] ← 통계 집계 (노출수, 클릭수, CTR, 기간별)
```

### 사이드바 메뉴 구조

```
▼ 광고 관리
   광고 배너 (admin_ads.php)
   광고주 관리 (admin_advertisers.php)
   광고 통계 (admin_ad_stats.php)
   AI 광고 에디터 (ai_ad_editor.php)
```

---

## 개발 단계

### STAGE 1: 기반 구축 (DB + 에러 수정)

**완료 항목:**
- [x] 레거시 `payment/ad_payment.php` 패키지 결제 폐기 (광고 지갑 방식으로 통합)
  - 원인: `require_once __DIR__ . '/../dbconfig.php'` 경로 불일치
  - 수정: `'/../dbconfig.php'` → `'/../../dbconfig.php'`
  - 추가: `gangnamunni/dbconfig.php` → `hairwang_full/dbconfig.php` 심볼릭 링크
- [x] `gangnamunni_ad_impressions` 테이블 생성 (광고 노출 기록)
  - 컬럼: id, ad_id, page_url, ip_address, user_agent, session_id, user_id, created_at
  - 인덱스: ad_id, created_at, (ad_id+created_at)
- [x] `gangnamunni_ad_clicks` 테이블 생성 (광고 클릭 기록)
  - 컬럼: id, ad_id, page_url, target_url, ip_address, user_agent, session_id, user_id, created_at
- [x] `gangnamunni_advertisers` 테이블 생성 (광고주 관리)
  - 컬럼: id, hospital_name, contact_name/email/phone, business_number, address, memo, hms_user_id, g5_member_id, status, total_spent
- [x] `gangnamunni_ads` 테이블에 컬럼 추가
  - advertiser_id (광고주 연결)
  - impression_count (노출수 캐시)
  - click_count (클릭수 캐시)

**생성된 파일:** 없음 (DB + 심볼릭 링크 + 경로 수정)

---

### STAGE 2: 광고 트래킹 시스템

**완료 항목:**
- [x] `api_ad_tracking.php` 생성 - 노출/클릭 트래킹 REST API
  - POST action=impression: 광고 노출 기록 (5분 중복 방지, 봇 필터링)
  - POST action=click: 광고 클릭 기록
  - 캐시 카운터 자동 업데이트 (gangnamunni_ads.impression_count/click_count)
- [x] `index.php`에 프론트엔드 트래킹 JavaScript 삽입
  - IntersectionObserver로 뷰포트 50% 진입 시 노출 기록
  - 클릭 이벤트 위임으로 광고 클릭 기록
  - `data-ad-id` 속성 기반 자동 트래킹

**생성된 파일:**
- `gangnamunni/api_ad_tracking.php` (트래킹 API)
- `gangnamunni/index.php` (트래킹 JS 추가)

**트래킹 흐름:**
```
사용자 페이지 로드
  → IntersectionObserver 관찰 시작
  → 광고 배너가 뷰포트 50% 진입
  → POST api_ad_tracking.php (action=impression, ad_id=N)
  → DB INSERT gangnamunni_ad_impressions
  → UPDATE gangnamunni_ads SET impression_count +1

사용자가 광고 클릭
  → click 이벤트 캡처
  → POST api_ad_tracking.php (action=click, ad_id=N)
  → DB INSERT gangnamunni_ad_clicks
  → UPDATE gangnamunni_ads SET click_count +1
```

---

### STAGE 3: 광고주 관리 페이지

**완료 항목:**
- [x] `admin_advertisers.php` 생성 - 광고주(병원) CRUD 관리
  - 광고주 등록 (병원명, 담당자, 이메일, 연락처, 사업자번호, 주소, 메모)
  - 광고주 수정 (모달 팝업)
  - 광고주 삭제 (연결된 광고 있으면 차단)
  - 검색/필터 (병원명, 담당자, 상태)
  - 페이지네이션 (20개/페이지)
  - 통계 표시: 광고수, 총 노출, 총 클릭

**생성된 파일:**
- `gangnamunni/admin_advertisers.php`

**기능 상세:**
| 기능 | 설명 |
|------|------|
| 등록 | 모달 폼, 병원명 필수 |
| 수정 | 모달 폼, JS로 데이터 바인딩 |
| 삭제 | 연결된 광고 확인 후 삭제 |
| 검색 | 병원명/담당자/이메일/연락처 LIKE 검색 |
| 상태 필터 | active/inactive/suspended |
| 광고수 | gangnamunni_ads.advertiser_id JOIN 카운트 |
| 통계 | SUM(impression_count), SUM(click_count) |

---

### STAGE 4: 광고 통계 대시보드

**완료 항목:**
- [x] `admin_ad_stats.php` 생성 - 노출/클릭/CTR 통계 대시보드
  - 요약 카드: 기간별 총 노출수, 클릭수, CTR
  - 일별 추이 라인 차트 (Chart.js)
  - 광고별 통계 테이블 (기간 노출/클릭/CTR + 누적)
  - 기간 필터: 오늘, 7일, 30일, 90일
  - 광고별 필터: 특정 광고 선택

**생성된 파일:**
- `gangnamunni/admin_ad_stats.php`

**통계 항목:**
| 지표 | 계산 |
|------|------|
| 노출수 | gangnamunni_ad_impressions COUNT |
| 클릭수 | gangnamunni_ad_clicks COUNT |
| CTR | 클릭수 / 노출수 * 100 |
| 일별 추이 | DATE(created_at) GROUP BY |
| 광고별 | ad_id별 집계 |
| 누적 | gangnamunni_ads.impression_count/click_count |

---

### STAGE 5: 사이드바 그룹 메뉴 + 로그인 통합

**완료 항목:**
- [x] 사이드바 '광고 관리'를 접기/펼치기 그룹 메뉴로 변경
  - 광고 배너 (admin_ads.php)
  - 광고주 관리 (admin_advertisers.php)
  - 광고 통계 (admin_ad_stats.php)
  - AI 광고 에디터 (ai_ad_editor.php)
- [x] admin_ads.php 자체 로그인 → gangnamunni_admins 통합
  - 기존: admin/admin123 하드코딩 세션 인증
  - 변경: admin_auth.php의 require_permission('ads') 사용
  - 하위 호환: 기존 세션 변수 유지 (ad_admin_logged_in 등)

**수정된 파일:**
- `gangnamunni/includes/admin_header.php` (메뉴 배열)
- `gangnamunni/admin_ads.php` (인증 교체)

---

### STAGE 6: 최종 검증

**검증 항목:**
- [ ] 모든 PHP 파일 문법 오류 없음
- [ ] 모든 DB 테이블 정상 생성
- [ ] 사이드바 그룹 메뉴 동작 확인
- [ ] 트래킹 API 동작 확인
- [ ] 통계 페이지 차트 렌더링 확인

---

## DB 테이블 요약

| 테이블 | 용도 | 레코드 |
|--------|------|--------|
| gangnamunni_ads | 광고 배너 | 3개 |
| gangnamunni_promos | 프로모션 배너 | - |
| gangnamunni_ad_payments | 광고 결제 (PortOne) | 0건 |
| gangnamunni_ad_impressions | 광고 노출 기록 | 실시간 |
| gangnamunni_ad_clicks | 광고 클릭 기록 | 실시간 |
| gangnamunni_advertisers | 광고주(병원) | - |

## 파일 목록

| 파일 | 용도 |
|------|------|
| admin_ads.php | 광고 배너 CRUD (기존, 인증 통합) |
| admin_advertisers.php | 광고주 관리 (신규) |
| admin_ad_stats.php | 광고 통계 대시보드 (신규) |
| ai_ad_editor.php | AI 광고 에디터 (기존) |
| api_ad_tracking.php | 노출/클릭 트래킹 API (신규) |
| payment/ad_payment.php | 폐기된 패키지 결제 엔드포인트 (HTTP 410) |
| payment/portone_config.php | PortOne 설정 (기존, 테스트 키) |
| includes/admin_header.php | 사이드바 메뉴 (수정) |

## 향후 계획

- [ ] PortOne 실제 가맹점 키 설정 (현재 테스트 모드)
- [ ] 광고주와 광고 연결 UI (admin_ads.php에서 advertiser_id 선택)
- [ ] 광고 기간 만료 자동 비활성화 (cron job)
- [ ] 결제 완료 시 광고 자동 활성화 (payment_callback.php)
- [ ] 광고주 대시보드 (광고주가 직접 통계 확인)
- [ ] HMS 환자(병원) 계정과 광고주 자동 매핑
