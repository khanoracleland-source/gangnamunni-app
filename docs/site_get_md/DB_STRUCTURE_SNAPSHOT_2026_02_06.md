# Hairwang DB 구조 스냅샷

**스냅샷 날짜**: 2026-02-06
**DB Host**: 124.111.85.242
**DB Name**: hairwang
**총 테이블 수**: 164개

---

## 주요 테이블 그룹

| 그룹 | 테이블 수 | 설명 |
|------|----------|------|
| crawled_* | 6개 | 크롤링 원본 데이터 |
| gangnamunni_* | 12개 | 강남언니 서비스 데이터 |
| hospital_* | 5개 | 병원 상세 데이터 |
| g5_* | 약 60개 | 그누보드 기본 테이블 |
| rb_* | 약 30개 | 라이믹스 빌더 테이블 |

---

## 1. 크롤링 데이터 테이블 (crawled_*)

### crawled_sites (3 rows)
크롤링된 사이트 목록

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK, AUTO_INCREMENT |
| site_name | varchar(100) | 사이트명 |
| site_url | varchar(255) | URL (UNIQUE) |
| site_type | varchar(50) | 사이트 유형 |
| total_files | int(11) | 총 파일 수 |
| total_size_mb | decimal(10,2) | 총 용량 (MB) |
| crawled_date | date | 크롤링 날짜 |
| status | enum('active','inactive') | 상태 |
| created_at | timestamp | 생성일 |

### crawled_hospitals (196 rows)
크롤링된 병원 데이터

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK, AUTO_INCREMENT |
| site_id | int(11) | FK -> crawled_sites.id |
| hospital_id | varchar(100) | 원본 병원 ID |
| hospital_name | varchar(255) | 병원명 |
| address | text | 주소 |
| location | varchar(255) | 지역 |
| phone | varchar(50) | 전화번호 |
| description | text | 설명 |
| services | text | 서비스 |
| review_count | int(11) | 리뷰 수 |
| rating | decimal(3,2) | 평점 |
| file_path | text | 파일 경로 |
| file_size_kb | int(11) | 파일 크기 |
| images | text | 이미지 목록 |
| created_at | timestamp | 생성일 |

### crawled_events (2,278 rows)
크롤링된 이벤트 데이터

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK, AUTO_INCREMENT |
| site_id | int(11) | FK -> crawled_sites.id |
| event_id | varchar(100) | 원본 이벤트 ID |
| event_name | varchar(500) | 이벤트명 |
| hospital_name | varchar(255) | 병원명 |
| brand_name | varchar(255) | 브랜드명 |
| category | varchar(100) | 카테고리 |
| description | text | 설명 |
| original_price | int(11) | 원가 |
| discount_price | int(11) | 할인가 |
| review_count | int(11) | 리뷰 수 |
| rating | decimal(3,2) | 평점 |
| file_path | text | 파일 경로 |
| file_size_kb | int(11) | 파일 크기 |
| images | text | 이미지 목록 |
| created_at | timestamp | 생성일 |

### crawled_doctors (141 rows)
크롤링된 의사 데이터

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK, AUTO_INCREMENT |
| site_id | int(11) | FK -> crawled_sites.id |
| doctor_id | varchar(100) | 원본 의사 ID |
| doctor_name | varchar(100) | 의사명 |
| hospital_id | int(11) | 병원 ID |
| hospital_name | varchar(255) | 병원명 |
| specialty | varchar(100) | 전문분야 |
| description | text | 설명 |
| file_path | text | 파일 경로 |
| file_size_kb | int(11) | 파일 크기 |
| created_at | timestamp | 생성일 |

### crawled_tips (40 rows)
크롤링된 팁/칼럼 데이터

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK, AUTO_INCREMENT |
| site_id | int(11) | FK -> crawled_sites.id |
| tip_id | varchar(100) | 원본 팁 ID |
| title | varchar(500) | 제목 |
| content | text | 내용 |
| category | varchar(100) | 카테고리 |
| author | varchar(100) | 작성자 |
| views | int(11) | 조회수 |
| file_path | text | 파일 경로 |
| file_size_kb | int(11) | 파일 크기 |
| created_at | timestamp | 생성일 |

### crawled_images (0 rows)
크롤링된 이미지 데이터

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK, AUTO_INCREMENT |
| site_id | int(11) | FK -> crawled_sites.id |
| image_type | varchar(50) | 이미지 유형 |
| image_path | varchar(500) | 경로 |
| file_name | varchar(255) | 파일명 |
| file_size_kb | int(11) | 파일 크기 |
| format | varchar(10) | 포맷 |
| related_id | int(11) | 관련 ID |
| related_type | enum | hospital/doctor/event/tip/other |
| created_at | timestamp | 생성일 |

---

## 2. 강남언니 서비스 테이블 (gangnamunni_*)

### gangnamunni_hospitals (103 rows)
서비스용 병원 데이터

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK |
| name | varchar(255) | 병원명 |
| description | text | 설명 |
| introduction | text | 소개 |
| address | varchar(500) | 주소 |
| phone | varchar(50) | 전화번호 |
| profile_image_url | varchar(500) | 프로필 이미지 |
| main_image_url | varchar(500) | 메인 이미지 |
| rating | decimal(3,2) | 평점 |
| review_count | int(11) | 리뷰 수 |
| latitude | decimal(10,8) | 위도 |
| longitude | decimal(11,8) | 경도 |
| categories | JSON | 카테고리 목록 |
| badges | JSON | 배지 목록 |
| treatment_tags | JSON | 시술 태그 |
| subway_names | JSON | 지하철역 |
| business_hours | JSON | 영업시간 |
| active_phone_consultation | tinyint(1) | 전화상담 활성화 |
| active_chat_consultation | tinyint(1) | 채팅상담 활성화 |
| active_general_consultation | tinyint(1) | 일반상담 활성화 |
| raw_data | JSON | 원본 데이터 |
| created_at | timestamp | 생성일 |
| updated_at | timestamp | 수정일 |

### gangnamunni_events (43 rows)
서비스용 이벤트 데이터

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK, AUTO_INCREMENT |
| title | varchar(500) | 제목 |
| subtitle | varchar(500) | 부제목 |
| description | text | 설명 |
| image_url | varchar(1000) | 이미지 URL |
| hospital_id | int(11) | 병원 ID |
| hospital_name | varchar(255) | 병원명 |
| category | varchar(100) | 카테고리 |
| original_price | int(11) | 원가 |
| discount_price | int(11) | 할인가 |
| discount_rate | int(11) | 할인율 |
| start_date | date | 시작일 |
| end_date | date | 종료일 |
| view_count | int(11) | 조회수 |
| is_active | tinyint(1) | 활성화 여부 |
| display_order | int(11) | 표시 순서 |
| created_at | timestamp | 생성일 |
| updated_at | timestamp | 수정일 |

### gangnamunni_tips (53 rows)
서비스용 팁/칼럼 데이터

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK, AUTO_INCREMENT |
| title | varchar(255) | 제목 |
| thumbnail_url | varchar(500) | 썸네일 URL |
| description | text | 설명 |
| content | text | 내용 |
| author | varchar(100) | 작성자 |
| views | int(11) | 조회수 |
| likes | int(11) | 좋아요 수 |
| category | varchar(50) | 카테고리 |
| created_at | datetime | 생성일 |
| updated_at | datetime | 수정일 |

### gangnamunni_categories (11 rows)
카테고리 목록

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK, AUTO_INCREMENT |
| name | varchar(100) | 카테고리명 |
| icon | varchar(50) | 아이콘 |
| display_order | int(11) | 표시 순서 |
| is_active | tinyint(1) | 활성화 |
| created_at | timestamp | 생성일 |
| updated_at | timestamp | 수정일 |

### gangnamunni_bookings (0 rows)
예약 데이터

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK, AUTO_INCREMENT |
| hospital_id | int(11) | 병원 ID |
| hospital_name | varchar(200) | 병원명 |
| name | varchar(100) | 예약자명 |
| phone | varchar(20) | 전화번호 |
| treatment | varchar(100) | 시술 |
| booking_date | date | 예약일 |
| booking_time | time | 예약시간 |
| memo | text | 메모 |
| status | varchar(20) | 상태 |
| created_at | datetime | 생성일 |
| updated_at | datetime | 수정일 |

### gangnamunni_theme_settings (35 rows)
테마 설정

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | int(11) | PK, AUTO_INCREMENT |
| setting_key | varchar(100) | 설정 키 (UNIQUE) |
| setting_value | text | 설정 값 |
| setting_type | varchar(50) | 설정 타입 |
| description | varchar(255) | 설명 |
| updated_at | datetime | 수정일 |

---

## 3. 데이터 현황 요약

### 크롤링 데이터
| 테이블 | 레코드 수 | 용량 |
|--------|----------|------|
| crawled_sites | 3 | 16KB |
| crawled_hospitals | 196 | 64KB |
| crawled_events | 2,278 | 1.5MB |
| crawled_doctors | 141 | 48KB |
| crawled_tips | 40 | 16KB |
| crawled_images | 0 | 16KB |

### 서비스 데이터
| 테이블 | 레코드 수 | 용량 |
|--------|----------|------|
| gangnamunni_hospitals | 103 | 1.5MB |
| gangnamunni_events | 43 | 64KB |
| gangnamunni_tips | 53 | 48KB |
| gangnamunni_categories | 11 | 16KB |
| gangnamunni_bookings | 0 | 16KB |
| gangnamunni_theme_settings | 35 | 16KB |

---

## 4. 테이블 관계

```
crawled_sites (1) ──┬── (N) crawled_hospitals
                    ├── (N) crawled_events
                    ├── (N) crawled_doctors
                    ├── (N) crawled_tips
                    └── (N) crawled_images

gangnamunni_hospitals (1) ──┬── (N) gangnamunni_events
                           └── (N) gangnamunni_bookings
```

---

## 5. 변경 추적용 체크리스트

나중에 DB 변경 시 아래 항목 확인:

- [ ] 테이블 추가/삭제 여부
- [ ] 컬럼 추가/삭제/변경 여부
- [ ] 인덱스 변경 여부
- [ ] 레코드 수 변화
- [ ] 외래키 관계 변경 여부

---

**생성일**: 2026-02-06
**생성자**: Claude AI
