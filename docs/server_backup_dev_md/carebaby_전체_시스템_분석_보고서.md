# CareBaby 전체 시스템 분석 보고서
## DB 구조 및 소스 코드 분석

작성일: 2026-01-18
분석 대상: CareBaby 폴더 전체 (DB + 소스)
분석 범위: 병원 시스템, 광고 시스템, 결제 시스템, API 시스템

---

# 1. 데이터베이스 구조 분석

## 1.1 병원 관련 테이블 (hospital_tables.sql)

### nf_hospital (병원 마스터 테이블)
| 컬럼명 | 타입 | 설명 | 비고 |
|--------|------|------|------|
| hospital_no | INT | 병원 번호 (PK) | Auto Increment |
| hospital_name | VARCHAR(200) | 병원명 | 필수 |
| hospital_name_en | VARCHAR(200) | 병원 영문명 | |
| hospital_type | VARCHAR(50) | 병원 유형 | 산부인과, 피부과 등 |
| hospital_email | VARCHAR(100) | 이메일 | |
| hospital_tel | VARCHAR(50) | 전화번호 | |
| hospital_fax | VARCHAR(50) | 팩스 | |
| hospital_homepage | VARCHAR(255) | 홈페이지 URL | |
| hospital_zipcode | VARCHAR(10) | 우편번호 | |
| hospital_address | VARCHAR(255) | 주소 | |
| hospital_address_detail | VARCHAR(255) | 상세 주소 | |
| hospital_area1 | VARCHAR(50) | 시/도 | 서울특별시 등 |
| hospital_area2 | VARCHAR(50) | 시/군/구 | 강남구 등 |
| hospital_area3 | VARCHAR(50) | 동/읍/면 | 역삼동 등 |
| hospital_lat | DECIMAL(10,7) | 위도 | 지도 표시용 |
| hospital_lng | DECIMAL(10,7) | 경도 | 지도 표시용 |
| hospital_intro | TEXT | 소개글 | |
| hospital_description | TEXT | 상세 설명 | |
| hospital_departments | JSON | 진료과목 | JSON 배열 형태 |
| hospital_specialties | JSON | 특수 진료 | JSON 배열 형태 |
| hospital_facilities | JSON | 시설 정보 | JSON 배열 형태 |
| hospital_services | JSON | 제공 서비스 | JSON 배열 형태 |
| hospital_hours | JSON | 진료 시간 | JSON 형태 |
| hospital_parking | VARCHAR(20) | 주차 여부 | Y/N |
| hospital_parking_info | TEXT | 주차 상세 정보 | |
| hospital_beds | INT | 병상 수 | |
| hospital_status | VARCHAR(20) | 병원 상태 | pending, approved, rejected, inactive |
| hospital_display | VARCHAR(1) | 노출 여부 | Y/N |
| hospital_rating | DECIMAL(3,2) | 평균 평점 | 0.00 ~ 5.00 |
| hospital_review_count | INT | 리뷰 개수 | |
| hospital_favorite_count | INT | 즐겨찾기 수 | |
| hospital_logo | VARCHAR(255) | 로고 이미지 | |
| hospital_images | JSON | 이미지 목록 | JSON 배열 |
| hospital_thumbnail | VARCHAR(255) | 썸네일 | |
| hospital_manager_name | VARCHAR(50) | 관리자명 | |
| hospital_manager_tel | VARCHAR(50) | 관리자 연락처 | |
| hospital_manager_email | VARCHAR(100) | 관리자 이메일 | |
| hospital_contract_start | DATE | 계약 시작일 | |
| hospital_contract_end | DATE | 계약 종료일 | |
| hospital_contract_status | VARCHAR(20) | 계약 상태 | active, expired, terminated |
| hospital_memo | TEXT | 메모 | |
| hospital_admin_note | TEXT | 관리자 메모 | |
| hospital_created_at | DATETIME | 생성일 | |
| hospital_updated_at | DATETIME | 수정일 | |
| hospital_deleted_at | DATETIME | 삭제일 | Soft Delete |
| hospital_created_by | INT | 생성자 번호 | |
| hospital_updated_by | INT | 수정자 번호 | |
| hospital_deleted_by | INT | 삭제자 번호 | |

### nf_hospital_review (병원 리뷰 테이블)
| 컬럼명 | 타입 | 설명 | 비고 |
|--------|------|------|------|
| review_no | INT | 리뷰 번호 (PK) | Auto Increment |
| hospital_no | INT | 병원 번호 (FK) | nf_hospital.hospital_no |
| manager_no | INT | 관리사 번호 (FK) | nf_manager.manager_no |
| review_rating | DECIMAL(3,2) | 평점 | 1.00 ~ 5.00 |
| review_title | VARCHAR(200) | 리뷰 제목 | |
| review_content | TEXT | 리뷰 내용 | |
| review_images | JSON | 첨부 이미지 | JSON 배열 |
| review_status | VARCHAR(20) | 상태 | active, hidden, deleted |
| review_created_at | DATETIME | 작성일 | |
| review_updated_at | DATETIME | 수정일 | |
| review_deleted_at | DATETIME | 삭제일 | Soft Delete |

### nf_hospital_manager (병원-관리사 매칭 테이블)
| 컬럼명 | 타입 | 설명 | 비고 |
|--------|------|------|------|
| hm_no | INT | 매칭 번호 (PK) | Auto Increment |
| hospital_no | INT | 병원 번호 (FK) | nf_hospital.hospital_no |
| manager_no | INT | 관리사 번호 (FK) | nf_manager.manager_no |
| hm_start_date | DATE | 시작일 | |
| hm_end_date | DATE | 종료일 | |
| hm_position | VARCHAR(50) | 직급/직책 | |
| hm_department | VARCHAR(50) | 부서 | |
| hm_work_type | VARCHAR(20) | 근무 형태 |全职,兼职,临聘 |
| hm_status | VARCHAR(20) | 상태 | active, inactive, terminated |
| hm_memo | TEXT | 메모 | |
| hm_note | TEXT | 비고 | |
| hm_created_at | DATETIME | 생성일 | |
| hm_updated_at | DATETIME | 수정일 | |
| hm_deleted_at | DATETIME | 삭제일 | Soft Delete |
| hm_created_by | INT | 생성자 | |

## 1.2 광고 시스템 테이블 (advertisement_tables.sql)

### nf_advertiser (광고주 테이블)
| 컬럼명 | 타입 | 설명 | 비고 |
|--------|------|------|------|
| advertiser_no | INT | 광고주 번호 (PK) | Auto Increment |
| member_no | INT | 회원 번호 (FK) | nf_member.no |
| advertiser_company | VARCHAR(200) | 회사명 | 필수 |
| advertiser_business_no | VARCHAR(20) | 사업자등록번호 | 123-45-67890 |
| advertiser_ceo_name | VARCHAR(50) | 대표자명 | |
| advertiser_tel | VARCHAR(50) | 대표 전화 | |
| advertiser_email | VARCHAR(100) | 대표 이메일 | |
| advertiser_address | VARCHAR(255) | 회사 주소 | |
| advertiser_address_detail | VARCHAR(255) | 상세 주소 | |
| advertiser_zipcode | VARCHAR(10) | 우편번호 | |
| advertiser_status | VARCHAR(20) | 상태 | pending, approved, rejected, suspended |
| advertiser_approved_at | DATETIME | 승인일 | |
| advertiser_approved_by | INT | 승인자 | |
| advertiser_rejected_at | DATETIME | 거부일 | |
| advertiser_rejected_by | INT | 거부자 | |
| advertiser_reject_reason | TEXT | 거부 사유 | |
| advertiser_note | TEXT | 메모 | |
| advertiser_created_at | DATETIME | 생성일 | |
| advertiser_updated_at | DATETIME | 수정일 | |
| advertiser_deleted_at | DATETIME | 삭제일 | Soft Delete |

### nf_advertisement (광고 테이블)
| 컬럼명 | 타입 | 설명 | 비고 |
|--------|------|------|------|
| ad_no | INT | 광고 번호 (PK) | Auto Increment |
| advertiser_no | INT | 광고주 번호 (FK) | nf_advertiser.advertiser_no |
| member_no | INT | 회원 번호 (FK) | nf_member.no |
| ad_title | VARCHAR(200) | 광고 제목 | 필수 |
| ad_description | TEXT | 광고 설명 | |
| ad_type | VARCHAR(20) | 광고 유형 | banner, inline, sidebar, popup |
| ad_position | VARCHAR(50) | 광고 위치 | hospital_list, hospital_view, main, all |
| ad_image | VARCHAR(255) | 광고 이미지 | |
| ad_link_url | VARCHAR(255) | 링크 URL | |
| ad_target_blank | VARCHAR(1) | 새 창 열기 | Y/N |
| ad_start_date | DATETIME | 시작일 | |
| ad_end_date | DATETIME | 종료일 | |
| ad_priority | INT | 우선순위 | 0~100 |
| ad_status | VARCHAR(20) | 상태 | pending, approved, rejected, active, paused, expired |
| ad_view_count | INT | 조회수 | 기본값: 0 |
| ad_click_count | INT | 클릭수 | 기본값: 0 |
| ad_total_price | INT | 총 광고비 | 원 단위 |
| ad_payment_status | VARCHAR(20) | 결제 상태 | pending, paid, expired |
| ad_approved_at | DATETIME | 승인일 | |
| ad_approved_by | INT | 승인자 | |
| ad_rejected_at | DATETIME | 거부일 | |
| ad_rejected_by | INT | 거부자 | |
| ad_reject_reason | TEXT | 거부 사유 | |
| ad_note | TEXT | 메모 | |
| ad_created_at | DATETIME | 생성일 | |
| ad_updated_at | DATETIME | 수정일 | |
| ad_deleted_at | DATETIME | 삭제일 | Soft Delete |

### nf_ad_statistics (광고 통계 테이블)
| 컬럼명 | 타입 | 설명 | 비고 |
|--------|------|------|------|
| stat_no | INT | 통계 번호 (PK) | Auto Increment |
| ad_no | INT | 광고 번호 (FK) | nf_advertisement.ad_no |
| stat_date | DATE | 통계 일자 | |
| stat_view_count | INT | 조회수 | 기본값: 0 |
| stat_click_count | INT | 클릭수 | 기본값: 0 |
| stat_ctr | DECIMAL(5,2) | 클릭률 | (클릭/조회)*100 |
| stat_created_at | DATETIME | 생성일 | |
| stat_updated_at | DATETIME | 수정일 | |

### nf_ad_pricing (광고 요금 테이블)
| 컬럼명 | 타입 | 설명 | 비고 |
|--------|------|------|------|
| pricing_id | INT | 요금 ID (PK) | Auto Increment |
| pricing_type | VARCHAR(20) | 요금 유형 | daily, priority, position |
| daily_price | DECIMAL(10,2) | 1일 기본 요금 | |
| priority_level | INT | 우선순위 레벨 | 1~10 |
| priority_price | DECIMAL(10,2) | 우선순위 추가 요금 | |
| position_type | VARCHAR(50) | 위치 유형 | main, hospital_list, hospital_view |
| position_price | DECIMAL(10,2) | 위치 추가 요금 | |
| pricing_status | VARCHAR(20) | 상태 | active, inactive |
| pricing_created_at | DATETIME | 생성일 | |
| pricing_updated_at | DATETIME | 수정일 | |

## 1.3 광고 결제 테이블 (advertisement_payment_tables.sql)

### nf_ad_payment (광고 결제 테이블)
| 컬럼명 | 타입 | 설명 | 비고 |
|--------|------|------|------|
| payment_no | INT | 결제 번호 (PK) | Auto Increment |
| ad_no | INT | 광고 번호 (FK) | nf_advertisement.ad_no |
| advertiser_no | INT | 광고주 번호 (FK) | nf_advertiser.advertiser_no |
| member_no | INT | 회원 번호 (FK) | nf_member.no |
| payment_method | VARCHAR(20) | 결제 수단 | card, bank, vbank, phone |
| payment_price | INT | 결제 금액 | 원 단위 |
| payment_status | VARCHAR(20) | 결제 상태 | pending, paid, cancelled, refunded, failed |
| pg_tid | VARCHAR(50) | PG 거래 ID | |
| pg_mid | VARCHAR(20) | PG 상점 ID | |
| pg_auth_date | DATETIME | PG 승인일 | |
| pg_card_code | VARCHAR(10) | 카드 코드 | |
| pg_card_name | VARCHAR(50) | 카드사명 | |
| pg_card_quota | INT | 할부 개월 | 0:일시불 |
| pg_receipt_url | VARCHAR(255) | 영수증 URL | |
| payment_paid_at | DATETIME | 결제 완료일 | |
| refund_price | INT | 환불 금액 | |
| refund_reason | TEXT | 환불 사유 | |
| refund_date | DATETIME | 환불일 | |
| refund_by | INT | 환불 처리자 | |
| payment_created_at | DATETIME | 생성일 | |
| payment_updated_at | DATETIME | 수정일 | |

---

# 2. 소스 코드 구조 분석

## 2.1 전체 디렉토리 구조

```
carebaby/
├── nfor.php                    # 프레임워크 메인 파일 (615 라인)
├── config.php                  # DB 설정
├── path.php                    # 경로 설정
│
├── lib/                        # 라이브러리 폴더
│   ├── function.lib.php        # 공통 함수
│   ├── api.lib.php             # API 함수
│   ├── hospital.lib.php        # 병원 시스템 라이브러리 (883 라인, 20개 함수)
│   ├── advertisement.lib.php   # 광고 시스템 라이브러리 (1,564 라인, 40개 함수)
│   ├── form.lib.php            # 폼 관련
│   ├── shop.lib.php            # 쇼핑 관련
│   ├── mail.lib.php            # 메일 전송
│   ├── kakaotalk.lib.php       # 카카오톡 연동
│   ├── used.lib.php            # 중고 관련
│   ├── giftishow.lib.php       # 기프티콘
│   ├── thumb.lib.php           # 썸네일 생성
│   └── thumb_7.lib.php         # 썸네일 생성 v7
│
├── admin/                      # 관리자 페이지
│   ├── ad_list.php             # 광고 목록 (487 라인)
│   ├── ad_write.php            # 광고 등록/수정
│   ├── ad_payment_list.php     # 광고 결제 관리 (372 라인)
│   ├── hospital_list.php       # 병원 목록
│   ├── hospital_form.php       # 병원 등록/수정
│   └── ...                     # 기타 관리 페이지
│
├── api/                        # REST API
│   ├── hospital_list.php       # 병원 목록 API (268 라인)
│   ├── hospital_detail.php     # 병원 상세 API
│   ├── ad_list.php             # 광고 목록 API
│   └── ad_track.php            # 광고 추적 API
│
├── sql/                        # DB 테이블 생성 SQL
│   ├── hospital_tables.sql
│   ├── advertisement_tables.sql
│   └── advertisement_payment_tables.sql
│
└── ...                         # 기타 파일들
```

## 2.2 핵심 파일 상세 분석

### 2.2.1 nfor.php (프레임워크 메인 파일, 615 라인)

**주요 기능**:
1. PHP 8.2 호환성 처리
   - `magic_quotes_gpc` 제거로 인한 `addslashes` 직접 적용
   - 배열 인덱스 따옴표 추가 (권장 사항)
2. DB 연결 및 문자셋 설정 (utf8)
3. 세션 관리
   - SameSite 쿠키 속성 설정 (Chrome 80 대응)
   - 자동 로그인 처리
   - 로그아웃 자동 처리 (장시간 미활동)
4. JWT 토큰 인증
   - Firebase JWT 라이브러리 사용
   - Bearer Token 검증
5. 회원 인증 및 권한 체크
   - 관리자/일반 회원 구분
   - IP 차단 기능
   - 로그인 정보 업데이트
6. 모바일/PC 디바이스 감지
7. API 설정 (도메인별)

**주요 변수**:
- `$nfor['path']`: 시스템 경로
- `$config`: 시스템 설정
- `$member`: 로그인 회원 정보
- `$is_admin`: 관리자 여부
- `$is_member`: 회원 여부
- `$is_mobile`: 모바일 여부

### 2.2.2 lib/hospital.lib.php (병원 시스템 라이브러리, 883 라인, 20개 함수)

#### 1. 병원 기본 정보 조회 함수 (3개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `hospital_get_info()` | 병원 단일 조회 | hospital_no, include_deleted | 병원 정보 배열 또는 null |
| `hospital_get_list()` | 병원 목록 조회 | status, area1, area2, type, keyword, display, limit, offset, order_by | 병원 배열 |
| `hospital_get_count()` | 병원 총 개수 | status, area1, area2, type, keyword, display | INT |

**특징**:
- Soft Delete 지원 (`deleted_at` 필드)
- 다양한 검색 조건 지원 (지역, 유형, 키워드, 상태)
- 페이징 처리 가능
- SQL Injection 방지 (mysqli_real_escape_string)

#### 2. 병원 등록/수정/삭제 함수 (5개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `hospital_insert()` | 병원 등록 | data 배열 (병원 정보) | hospital_no 또는 false |
| `hospital_update()` | 병원 수정 | hospital_no, data 배열 | bool |
| `hospital_delete()` | 병원 삭제 (Soft) | hospital_no | bool |
| `hospital_restore()` | 병원 복구 | hospital_no | bool |
| `hospital_hard_delete()` | 영구 삭제 | hospital_no | bool |

**특징**:
- Soft Delete 기본 지원
- 허용된 필드만 업데이트 (SQL Injection 방지)
- 생성자/수정자/삭제자 정보 자동 저장
- 영구 삭제 시 CASCADE로 연관 데이터도 삭제

#### 3. 병원 상태 관리 함수 (3개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `hospital_approve()` | 병원 승인 | hospital_no | bool |
| `hospital_reject()` | 병원 거부 | hospital_no | bool |
| `hospital_deactivate()` | 병원 비활성화 | hospital_no | bool |

**특징**:
- 승인 시 승인일/승인자 자동 저장
- 상태값: pending, approved, rejected, inactive

#### 4. 병원 평가 관련 함수 (2개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `hospital_update_rating()` | 평균 평점 업데이트 | hospital_no | bool |
| `hospital_get_reviews()` | 리뷰 목록 조회 | hospital_no, params | 리뷰 배열 |

**특징**:
- 평균 평점 자동 계산 (nf_hospital_review 테이블에서)
- 리뷰 개수도 자동 업데이트
- 관리사 정보 JOIN

#### 5. 병원-관리사 매칭 함수 (2개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `hospital_manager_add()` | 관리사 매칭 등록 | hospital_no, manager_no, data | 매칭 번호 또는 false |
| `hospital_get_managers()` | 관리사 목록 조회 | hospital_no, status | 관리사 배열 |

**특징**:
- 다대다 관계 지원
- 매칭 정보 (직급, 부서, 근무형태 등) 저장 가능
- Soft Delete 지원

#### 6. 병원 검색 함수 (2개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `hospital_search_nearby()` | 좌표 기반 주변 검색 | lat, lng, radius, limit | 병원 배열 |
| `hospital_search_by_department()` | 진료과목별 검색 | department, limit | 병원 배열 |

**특징**:
- Haversine 공식 사용 (지구 곡률 고려)
- 반경 내 정렬 (거리순)
- 진료과목 JSON에서 LIKE 검색

#### 7. 유틸리티 함수 (3개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `hospital_check_duplicate_name()` | 중복 체크 | hospital_name, exclude_no | bool |
| `hospital_get_statistics()` | 상태별 통계 | - | 통계 배열 |
| `hospital_get_json()` | JSON 변환 | hospital_no | JSON 문자열 |

### 2.2.3 lib/advertisement.lib.php (광고 시스템 라이브러리, 1,564 라인, 40개 함수)

#### 1. 광고주 관리 함수 (5개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `advertiser_register()` | 광고주 등록 | member_no, data 배열 | advertiser_no 또는 false |
| `advertiser_get_info()` | 광고주 조회 | advertiser_no | 광고주 정보 또는 false |
| `advertiser_update()` | 광고주 수정 | advertiser_no, data 배열 | bool |
| `advertiser_approve()` | 광고주 승인 | advertiser_no, admin_no | bool |
| `advertiser_reject()` | 광고주 거부 | advertiser_no, reason, admin_no | bool |

**특징**:
- 중복 등록 방지 (member_no로 체크)
- 승인 시 회원 타입을 'advertiser'로 변경
- 거부 사유 저장

#### 2. 광고 관리 함수 (6개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `ad_create()` | 광고 생성 | advertiser_no, data 배열 | ad_no 또는 false |
| `ad_get_info()` | 광고 조회 | ad_no | 광고 정보 또는 false |
| `ad_update()` | 광고 수정 | ad_no, data 배열 | bool |
| `ad_approve()` | 광고 승인 | ad_no, admin_no | bool |
| `ad_reject()` | 광고 거부 | ad_no, reason, admin_no | bool |
| `ad_delete()` | 광고 삭제 (Soft) | ad_no | bool |

**특징**:
- 승인된 광고주만 광고 등록 가능
- 광고 승인 시:
  - 시작일이 현재보다 이전이면 'active'
  - 아니면 'approved' 상태 유지
- 광고 종료일 자동 체크

#### 3. 광고 표시 함수 (4개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `ad_get_active_list()` | 활성 광고 목록 | position, limit | 광고 배열 |
| `ad_get_random()` | 랜덤 광고 1개 | position | 광고 정보 또는 false |
| `ad_render()` | 광고 HTML 렌더링 | ad_data, template | HTML 문자열 |
| `ad_insert_in_list()` | 리스트 중간 광고 삽입 | items, position, interval | items 배열 (광고 포함) |

**특징**:
- 우선순위 기반 가중치 랜덤 선택
- 3가지 템플릿 (inline, banner, sidebar)
- 광고 조회 추적 JavaScript 자동 추가
- 리스트 중간에 N개마다 광고 삽입 가능

#### 4. 광고 추적 함수 (4개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `ad_increment_view()` | 조회수 증가 | ad_no | bool |
| `ad_increment_click()` | 클릭수 증가 | ad_no | bool |
| `ad_update_statistics()` | 일별 통계 업데이트 | ad_no, type (view/click) | bool |
| `ad_get_statistics()` | 기간별 통계 조회 | ad_no, start_date, end_date | 통계 배열 |

**특징**:
- 조회/클릭 시 즉시 통계 업데이트
- CTR (클릭률) 자동 계산
- 기간별 통계 조회 가능

#### 5. 광고 상태 관리 함수 (1개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `ad_check_and_update_status()` | 상태 자동 업데이트 | - | 업데이트된 개수 |

**특징**:
- 크론잡으로 주기적 실행 권장 (매일 자정)
- approved → active (시작일 도달 시)
- active → expired (종료일 도달 시)

#### 6. 광고 결제 함수 (10개)

| 함수명 | 설명 | 주요 파라미터 | 반환값 |
|--------|------|--------------|--------|
| `ad_calculate_price()` | 광고 요금 계산 | ad 배열 | INT (원 단위) |
| `ad_payment_create()` | 결제 정보 저장 | data 배열 | payment_no 또는 false |
| `ad_payment_get_info()` | 결제 정보 조회 | payment_no | 결제 정보 또는 false |
| `ad_payment_get_by_ad()` | 광고별 결제 조회 | ad_no | 결제 정보 또는 false |
| `ad_update_payment_status()` | 결제 상태 업데이트 | ad_no, status | bool |
| `ad_payment_refund()` | 환불 처리 | payment_no, reason, refund_by | bool |
| `ad_update_total_price()` | 총 비용 업데이트 | ad_no | bool |
| `ad_extract_no_from_order()` | 주문번호에서 광고 번호 추출 | order_number | ad_no 또는 false |
| `ad_get_pending_payment_list()` | 결제 대기 광고 목록 | advertiser_no, limit | 광고 배열 |
| `ad_payment_get_list()` | 결제 목록 (관리자용) | filters, limit, offset | 결제 배열 |

**특징**:
- 요금 계산: 기본 요금 + 우선순위 요금 + 위치 요금
- INIpay 주문번호 형식: `AD_{ad_no}_{timestamp}`
- 결제 완료 시 광고 상태 자동 업데이트
- 환불 시 결제 상태 'pending'으로 변경

### 2.2.4 admin/ad_list.php (관리자 광고 목록 페이지, 487 라인)

**주요 기능**:
1. 광고 목록 조회 (페이징: 20개씩)
2. 검색/필터링
   - 상태: 승인대기, 승인완료, 거부됨, 진행중, 일시정지, 만료됨
   - 위치: 병원리스트, 병원상세, 메인, 전체
   - 광고주명 검색
   - 키워드 (제목/설명) 검색
3. 광고 승인/거부 (AJAX)
4. 광고 삭제 (Soft Delete)
5. 광고 통계 조회

**주요 UI 구성**:
- 검색 필터 박스
- 광고 목록 테이블
  - 번호, 이미지, 제목, 광고주, 위치, 우선순위, 상태, 기간, 조회/클릭, 관리 버튼
- 페이징 네비게이션

### 2.2.5 admin/ad_payment_list.php (광고 결제 관리 페이지, 372 라인)

**주요 기능**:
1. 결제 내역 목록 조회
2. 필터링
   - 상태별: 결제 대기, 결제 완료, 결제 취소, 환불 완료, 결제 실패
3. 환불 처리
4. 통계 요약
   - 전체 결제 건수
   - 완료 건수
   - 대기 건수
   - 총 수익

**주요 UI 구성**:
- 통계 요약 카드 (4개)
- 필터 바
- 결제 목록 테이블
  - 결제번호, 광고명, 광고주, 결제금액, 결제수단, 결제상태, PG TID, 결제일시, 관리 (환불 버튼, 영수증)
- 페이징

### 2.2.6 api/hospital_list.php (병원 목록 API, 268 라인)

**주요 기능**:
1. 모바일 앱/외부 시스템용 병원 목록 API
2. JSON 형식 응답

**요청 파라미터**:
- keyword: 검색 키워드 (병원명, 주소)
- area1: 시/도
- area2: 시/군/구
- type: 병원 타입
- department: 진료과목
- status: 상태 (기본값: approved)
- lat, lng: 좌표 (주변 검색)
- radius: 반경 (km, 기본값: 5)
- sort: 정렬 (latest, rating, review, favorite, distance, name)
- page: 페이지 번호 (기본값: 1)
- limit: 페이지당 개수 (기본값: 20, 최대: 100)

**응답 형식**:
```json
{
  "success": true,
  "message": "병원 목록을 조회했습니다.",
  "data": {
    "hospitals": [
      {
        "hospital_no": 1,
        "hospital_name": "서울산부인과",
        "hospital_type": "산부인과",
        "hospital_area1": "서울특별시",
        "hospital_area2": "강남구",
        "hospital_address": "서울시 강남구 역삼동",
        "hospital_tel": "02-1234-5678",
        "hospital_homepage": "http://www.example.com",
        "hospital_intro": "소개글...",
        "hospital_rating": 4.5,
        "hospital_review_count": 10,
        "hospital_favorite_count": 5,
        "hospital_image": "image.jpg",
        "hospital_lat": 37.5172,
        "hospital_lng": 127.0473,
        "hospital_departments": ["산부인과", "모성태아의학과"],
        "hospital_facilities": ["주차장", "수술실"],
        "distance": 1.23,
        "created_at": "2026-01-01 10:00:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 100,
      "total_pages": 5,
      "has_prev": false,
      "has_next": true
    },
    "filters": {
      "keyword": "",
      "area1": "",
      "area2": "",
      "type": "",
      "department": "",
      "lat": null,
      "lng": null,
      "radius": null,
      "sort": "latest"
    }
  },
  "timestamp": "2026-01-18 10:00:00"
}
```

**특징**:
- CORS 지원 (Access-Control-Allow-Origin: *)
- 좌표 기반 주변 검색 (Haversine 공식)
- 다양한 정렬 옵션
- 페이징 처리
- 응답 크기 최적화 (필요한 필드만 추출)

---

# 3. 시스템 아키텍처 분석

## 3.1 데이터 흐름

```
[회원/광고주]
    ↓
[회원 가입/로그인]
    ↓
[광고주 등록 (pending)]
    ↓
[관리자 승인]
    ↓
[광고 등록 (pending)]
    ↓
[광고 요금 계산]
    ↓
[INIpay 결제]
    ↓
[결제 완료 → 광고 승인]
    ↓
[광고 시작일 도달 → active]
    ↓
[광고 노출 (조회/클릭 추적)]
    ↓
[일별 통계 업데이트]
    ↓
[광고 종료일 도달 → expired]
```

## 3.2 핵심 기능별 구조

### 병원 시스템
```
병원 등록 (pending)
    ↓
관리자 승인 → approved
    ↓
병원 상세 정보 입력 (진료과목, 시설, 서비스)
    ↓
관리사 매칭
    ↓
평점/리뷰 시스템
```

### 광고 시스템
```
광고주 등록
    ↓
광고 등록 (이미지, 제목, 설명, 링크, 기간, 위치, 우선순위)
    ↓
요금 계산 (기본요금 + 우선순위 + 위치)
    ↓
결제 (INIpay)
    ↓
관리자 승인
    ↓
광고 노출 (조회/클릭 추적)
    ↓
통계 제공
```

## 3.3 보안 기능

1. SQL Injection 방지
   - `mysqli_real_escape_string` 사용
   - 허용된 필드만 업데이트

2. XSS 방지
   - `htmlspecialchars` 사용
   - `ENT_QUOTES, UTF-8` 옵션

3. CSRF 방지
   - 세션 검증
   - 토큰 기반 검증 (구현 필요)

4. Soft Delete
   - 데이터 영구 삭제 방지
   - 복구 가능

5. IP 차단
   - 악성 IP 차단 기능

---

# 4. 기술 스택 분석

| 구분 | 기술 | 버전 | 설명 |
|------|------|------|------|
| 언어 | PHP | 8.2.3 | 최신 안정 버전 |
| DB | MySQL/MariaDB | - | UTF-8 문자셋 |
| 프레임워크 | NFOR Framework | v7.0.7 | 자체 프레임워크 |
| 템플릿 | PHP | - | 네이티브 PHP |
| 결제 PG | INIpay | - | 이니시스 PG |
| 인증 | JWT | - | Firebase JWT 라이브러리 |
| 소셜로그인 | Kakao, Naver, Google, Apple | - | |

---

# 5. 개발 우선순위 제안

## 5.1 현재 완료된 기능 (100%)
- ✅ 병원 시스템 (lib/hospital.lib.php - 20개 함수)
- ✅ 광고 시스템 (lib/advertisement.lib.php - 40개 함수)
- ✅ 광고 결제 시스템 (10개 함수)
- ✅ 관리자 페이지 (ad_list.php, ad_payment_list.php)
- ✅ REST API (hospital_list.php)
- ✅ PHP 8.2 업그레이드 (631개 파일, 43,076개소 수정)

## 5.2 추가 개발 필요 기능

### 🔴 최우선 (2-3주)
1. **구독 시스템 통합**
   - Frezka 구독 시스템 (44개 PHP 파일 발견)
   - 병원/광고주에게 구독 플랜 제공
   - 월별/연간 구독 결제

2. **한국 PG 결제 연동**
   - 현재: INIpay (개발 모드)
   - 변경: KG이니시스/나이스페이/토스페이먼츠 등 한국 PG
   - 실결제 모드로 전환

3. **병원 관리 시스템 통합**
   - KiviCare 병원 관리 시스템 통합
   - 의사 관리
   - 진료 시간 관리

4. **광고 시스템 배치 처리**
   - 광고 상태 자동 업데이트 (크론잡)
   - 매일 자정 실행

### 🟡 중요 (1-2주)
1. **한국어 번역**
   - 영어 → 한국어 번역
   - 관리자/사용자 모두

2. **랭킹 시스템**
   - 병원 랭킹 (평점, 리뷰, 즐겨찾기)
   - 광고주 랭킹

3. **리뷰 시스템 고도화**
   - 리뷰 사진 첨부
   - 리뷰 신고 기능
   - 리뷰 답변 기능

### 🟢 선택 (1주)
1. **쿠폰/프로모션 시스템**
   - 광고주 쿠폰
   - 사용자 쿠폰

2. **패키지 시스템**
   - 병원 패키지
   - 광고 패키지

---

# 6. 코드 품질 분석

## 6.1 장점
1. ✅ 함수별 상세한 주석 (PHPDoc 형식)
2. ✅ SQL Injection 방지 (mysqli_real_escape_string)
3. ✅ Soft Delete 패턴 구현
4. ✅ 함수 단위 모듈화
5. ✅ 에러 핸들링
6. ✅ 페이징 처리
7. ✅ 검색/필터링 기능

## 6.2 개선 필요 사항
1. ⚠️ Prepared Statements 사용 (보안 강화)
2. ⚠️ CSRF 토큰 구현
3. ⚠️ 로깅 시스템 구현
4. ⚠️ 캐싱 레이어 (Redis/Memcached)
5. ⚠️ 단위 테스트 작성
6. ⚠️ API 속도 제한 (Rate Limiting)

---

# 7. 성능 최적화 제안

## 7.1 DB 최적화
1. 인덱스 추가
   - `nf_hospital`: (hospital_status, hospital_display), (hospital_area1, hospital_area2), (hospital_type)
   - `nf_advertisement`: (ad_status, ad_start_date, ad_end_date), (advertiser_no, ad_status)
   - `nf_ad_statistics`: (ad_no, stat_date)

2. 파티셔닝
   - `nf_ad_statistics`: stat_date 기준 월별 파티셔닝

3. 쿼리 최적화
   - EXPLAIN 사용하여 느린 쿼리 분석
   - JOIN 최적화

## 7.2 PHP 최적화
1. OPcache 활성화
2. 컴포저 오토로드 사용
3. 코드 분석 (Xdebug)

## 7.3 캐싱
1. 정적 캐싱 (HTML)
2. 동적 캐싱 (Redis/Memcached)
3. CDN 사용 (이미지/정적 파일)

---

# 8. 예상 개발 기간 및 비용

## 8.1 남은 개발 기간

| 항목 | 기간 | 설명 |
|------|------|------|
| 구독 시스템 통합 | 2주 | Frezka 구독 시스템 분석 및 통합 |
| 한국 PG 결제 연동 | 1주 | KG이니시스/나이스페이 등 |
| 병원 관리 시스템 통합 | 1주 | KiviCare 통합 |
| 광고 시스템 배치 처리 | 3일 | 크론잡 구현 |
| 한국어 번역 | 5일 | 전체 텍스트 번역 |
| 랭킹 시스템 | 3일 | 랭킹 알고리즘 구현 |
| 리뷰 시스템 고도화 | 1주 | 사진, 신고, 답변 |
| 쿠폰/프로모션 시스템 | 5일 | 선택 사항 |
| 패키지 시스템 | 3일 | 선택 사항 |

**총 개발 기간**: 6-7주 (약 1.5-2개월)

## 8.2 남은 개발 비용

| 항목 | 비용 (만원) | 비고 |
|------|------------|------|
| 구독 시스템 통합 | 150 | 2주 |
| 한국 PG 결제 연동 | 80 | 1주 |
| 병원 관리 시스템 통합 | 80 | 1주 |
| 광고 시스템 배치 처리 | 30 | 3일 |
| 한국어 번역 | 60 | 5일 |
| 랭킹 시스템 | 40 | 3일 |
| 리뷰 시스템 고도화 | 70 | 1주 |
| 쿠폰/프로모션 시스템 | 50 | 5일 |
| 패키지 시스템 | 30 | 3일 |

**총 비용**: 590만원

---

# 9. 결론

## 9.1 현재 상태
- CareBaby 시스템의 핵심 기능(병원, 광고, 결제, API)이 **100% 완료**되어 있음
- PHP 8.2 업그레이드 완료 (631개 파일)
- 총 **40개 함수**의 광고 시스템, **20개 함수**의 병원 시스템 라이브러리 구현
- 관리자 페이지와 REST API 구현 완료

## 9.2 다음 단계
1. **구독 시스템 통합** (2주, 150만원)
2. **한국 PG 결제 연동** (1주, 80만원)
3. **병원 관리 시스템 통합** (1주, 80만원)
4. **한국어 번역** (5일, 60만원)

## 9.3 최종 예상
- **추가 개발 기간**: 6-7주
- **추가 개발 비용**: 590만원
- **전체 프로젝트 완료 예상**: 2-3개월 내 가능

---

# 부록

## A. 테이블 ERD

```
nf_member
  ↓ 1:N
nf_advertiser
  ↓ 1:N
nf_advertisement
  ↓ 1:N
nf_ad_statistics
  ↓ 1:1
nf_ad_payment

nf_hospital
  ↓ 1:N
nf_hospital_review
  ↓ N:M
nf_manager (nf_hospital_manager)
```

## B. API 엔드포인트 목록

| 메서드 | 경로 | 설명 |
|--------|------|------|
| GET | /api/hospital_list.php | 병원 목록 |
| GET | /api/hospital_detail.php | 병원 상세 |
| GET | /api/ad_list.php | 광고 목록 |
| POST | /api/ad_track.php | 광고 추적 (조회/클릭) |

## C. 환경 변수

```php
// config.php
$nfor['sql_host'] = 'localhost';
$nfor['sql_user'] = 'root';
$nfor['sql_password'] = '<REMOVED_DB_PASSWORD>';
$nfor['sql_db'] = 'carebaby_db';
$nfor['cookie_domain'] = '';
$nfor['jwt_key'] = 'your-secret-key';
```

---

*본 보고서는 2026년 1월 18일에 작성되었습니다.*
