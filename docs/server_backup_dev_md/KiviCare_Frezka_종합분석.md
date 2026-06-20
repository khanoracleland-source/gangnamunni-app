# KiviCare & Frezka 프로젝트 종합 분석 보고서

> 작성일: 2026-01-28
> 분석 대상: KiviCare v1.9.0, Frezka v4.0.3

---

## 📋 목차
1. [프로젝트 개요](#프로젝트-개요)
2. [폴더 구조 비교](#폴더-구조-비교)
3. [데이터베이스 스키마](#데이터베이스-스키마)
4. [병원/지점 데이터 구조](#병원지점-데이터-구조)
5. [멀티 병원 지원 구조](#멀티-병원-지원-구조)
6. [구독 시스템](#구독-시스템)
7. [광고/프로모션 시스템](#광고프로모션-시스템)
8. [핵심 Models](#핵심-models)
9. [주요 차이점](#주요-차이점)
10. [개발 권장사항](#개발-권장사항)

---

## 1. 프로젝트 개요

### KiviCare (kivicare-laravel-web-v1.9.0)
- **유형**: 의료 관리 시스템 (Healthcare Management System)
- **주요 기능**: 의료진 관리, 진료 예약, 환자 관리, 청구 시스템
- **플랫폼**: Laravel 기반 멀티 테넌트 구조
- **데이터베이스**: kivicare_laravel (약 6MB, 100+ 테이블)

### Frezka (frezka_admin_panel_v4.0.3)
- **유형**: 뷰티/미용 서비스 플랫폼
- **주요 기능**: 예약 시스템, 서비스 관리, 상품 판매
- **플랫폼**: Laravel 기반 마케팅 플랫폼
- **데이터베이스**: frezka (약 100MB, 80+ 테이블)

---

## 2. 폴더 구조 비교

### KiviCare 구조
```
kivicare-laravel-web-v1.9.0/
├── app/
│   ├── Models/
│   ├── Http/
│   ├── Exports/ (60+ 내보내기 클래스)
│   ├── Events/
│   └── Helpers/
├── Modules/ (47개 모듈)
│   ├── Clinic/          [병원 관리]
│   ├── Appointment/     [진료 예약]
│   ├── Subscriptions/   [구독 관리]
│   ├── Promotion/       [프로모션]
│   ├── Service/         [서비스]
│   ├── Customer/        [환자]
│   ├── employee/        [직원]
│   ├── MultiVendor/     [다중 공급업체]
│   └── ...
├── resources/
├── routes/
│   ├── api.php
│   └── web.php
└── config/
```

### Frezka 구조
```
frezka_admin_panel_v4.0.3/
├── app/
├── Modules/ (36개 모듈)
│   ├── Bank/
│   ├── Booking/
│   ├── Category/
│   ├── Commission/
│   ├── Employee/
│   ├── Product/
│   ├── Subscriptions/
│   └── ...
├── resources/
├── routes/
└── config/
```

---

## 3. 데이터베이스 스키마

### KiviCare 주요 테이블 (100+)

#### 병원 관련
- `clinic` - 병원 기본 정보
- `clinic_appointments` - 병원별 예약
- `clinichours` - 영업시간
- `clinic_gallery` - 갤러리
- `clinics_categories` - 카테고리
- `clinics_services` - 제공 서비스

#### 진료 관련
- `appointments` - 진료 예약
- `appointment_patient_records` - 환자 기록
- `encounter_prescription` - 처방
- `billing_record` - 청구

#### 의료진 관련
- `doctors` - 의료진 정보
- `doctor_clinic_mapping` - 의료진-병원 매핑
- `doctor_service_mappings` - 의료진-서비스 매핑
- `doctor_ratings` - 평점

### Frezka 주요 테이블 (80+)

#### 지점 관련
- `branches` - 지점 정보
- `branch_employee` - 지점별 직원
- `branch_galleries` - 갤러리

#### 예약 관련
- `bookings` - 예약 정보
- `booking_services` - 예약 서비스
- `booking_packages` - 예약 패키지
- `booking_transactions` - 거래

#### 상품 관련
- `products` - 상품
- `product_variations` - 상품 변형
- `orders` - 주문
- `order_items` - 주문 항목

---

## 4. 병원/지점 데이터 구조

### KiviCare Clinic 스키마
```sql
CREATE TABLE `clinic` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `description` text,
  `contact_number` varchar(191) NOT NULL,
  `address` varchar(191) NOT NULL,
  `city` int,
  `state` int,
  `country` int,
  `pincode` varchar(191),
  `latitude` varchar(191),
  `longitude` varchar(191),
  `status` tinyint(1) DEFAULT '0',
  `time_slot` int,
  `vendor_id` int,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
```

**관계도:**
```
clinic
├── clinics_services (1:N)
├── clinics_categories (1:N)
├── doctor_clinic_mapping (1:N)
├── clinic_appointments (1:N)
├── clinichours (1:N)
└── clinic_gallery (1:N)
```

### Frezka Branch 스키마
```sql
CREATE TABLE `branches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `address` varchar(191) NOT NULL,
  `city` int DEFAULT NULL,
  `state` int DEFAULT NULL,
  `country` int DEFAULT NULL,
  `latitude` double,
  `longitude` double,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
```

---

## 5. 멀티 병원 지원 구조

### KiviCare 멀티 테넌트
```
핵심 메커니즘:
1. clinic 테이블이 기본 테넌트 단위
2. clinic_id로 모든 데이터 분리:
   - appointments.clinic_id
   - doctors (doctor_clinic_mapping 통해)
   - clinic_session.clinic_id
3. vendor_id로 벤더 구분
4. MultiVendor 모듈로 다중 벤더 관리

특징:
- 의료진은 여러 병원에 속할 수 있음
- 예약은 clinic_id로 구분
- 수익/커미션이 병원별로 추적
```

### Frezka 멀티 로케이션
```
핵심 메커니즘:
1. branches 테이블이 기본 단위
2. user_id로 소유자 관리
3. branch_id로 데이터 분리:
   - bookings.branch_id
   - employees (branch_employee 통해)
4. delivery_zones로 배송 지역 관리

특징:
- 직원은 특정 지점에 속함
- 예약은 branch_id로 구분
- 배송 영역과 연계
```

---

## 6. 구독 시스템

### 공통 테이블 구조

```sql
-- Plan (요금제)
CREATE TABLE `plan` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `type` varchar(191),
  `duration` int NOT NULL DEFAULT '1',
  `amount` int,
  `trial_period` int NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
);

-- PlanLimitation (요금제 제한사항)
CREATE TABLE `planlimitation` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `limit` bigint NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
);

-- Subscriptions (사용자 구독)
CREATE TABLE `subscriptions` (
  `id` bigint UNSIGNED NOT NULL,
  `plan_id` int,
  `user_id` int,
  `start_date` datetime,
  `end_date` datetime,
  `status` varchar(191),
  `amount` double,
  PRIMARY KEY (`id`)
);

-- Subscriptions_transactions (구독 거래)
CREATE TABLE `subscriptions_transactions` (
  `id` bigint UNSIGNED NOT NULL,
  `subscriptions_id` int,
  `payment_method` varchar(191),
  `amount` double,
  `payment_status` varchar(191),
  PRIMARY KEY (`id`)
);
```

### 구독 흐름
```
Plan (요금제 정의)
├── 기본/프리미엄/엔터프라이즈
├── 월간/연간 구분
├── 무료 체험 기간
└── 제한사항 설정

PlanLimitation
├── 최대 진료실 수
├── 최대 의료진 수
├── 최대 예약 수
└── 기능 제한

Subscriptions
├── 활성 구독 관리
├── 구독 기간 추적
└── 자동 갱신 처리

Transactions
└── 결제 내역 추적
```

---

## 7. 광고/프로모션 시스템

### Slider (배너/광고)
```sql
CREATE TABLE `sliders` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `link` varchar(191),
  `type` varchar(191) NOT NULL DEFAULT 'category',
  `link_id` int,
  PRIMARY KEY (`id`)
);
```

### Promotion (프로모션)
```sql
CREATE TABLE `promotions` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` longtext NOT NULL,
  `start_date_time` datetime NOT NULL,
  `end_date_time` datetime NOT NULL,
  `status` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`)
);

CREATE TABLE `promotions_coupon` (
  `id` bigint UNSIGNED NOT NULL,
  `promotion_id` bigint UNSIGNED NOT NULL,
  `code` varchar(191) NOT NULL,
  `discount_value` double NOT NULL,
  `discount_type` varchar(191),
  `max_usage` int,
  `current_usage` int,
  PRIMARY KEY (`id`)
);
```

---

## 8. 핵심 Models

### KiviCare Models
```
User
├── has many: Subscriptions
├── has many: Appointments (환자)
└── polymorphic: Addresses

Clinic
├── has many: ClinicsServices
├── has many: Doctor (through mapping)
├── has many: Appointments
└── has many: ClinicHours

Doctor
├── belongs to: Clinic (through mapping)
├── has many: Appointments
├── has many: DoctorSession
└── has many: DoctorRating

Appointment
├── belongs to: User (patient)
├── belongs to: Clinic
├── belongs to: Doctor
└── has many: AppointmentTransactions

Subscription
├── belongs to: User
├── belongs to: Plan
└── has many: SubscriptionsTransactions
```

### Frezka Models
```
User
├── has many: Branches
├── has many: Bookings
└── has many: Subscriptions

Branch
├── belongs to: User
├── has many: Employees
├── has many: Bookings
└── has many: Services

Employee
├── belongs to: Branch
├── has many: Bookings
└── has many: EmployeeCommissions

Booking
├── belongs to: User
├── belongs to: Branch
├── belongs to: Employee
└── has many: BookingTransactions
```

---

## 9. 주요 차이점

| 항목 | KiviCare | Frezka |
|------|----------|--------|
| **도메인** | 의료 관리 | 뷰티/미용 서비스 |
| **기본 단위** | Clinic (병원) | Branch (지점) |
| **핵심 비즈니스** | 진료 예약 + 청구 | 서비스 예약 + 상품 판매 |
| **전문가** | Doctor (의료진) | Employee (직원) |
| **모듈 수** | 47개 | 36개 |
| **테이블 수** | 100+ | 80+ |
| **Vendor 지원** | MultiVendor 모듈 O | X |
| **배송 시스템** | X | Logistics O |
| **상품 관리** | 기본 | 고급 (변형, 태그) |
| **진료 기록** | Encounter + Records | X |
| **의약품 관리** | Medicine | X |

---

## 10. 개발 권장사항

### 통합 개발 전략

#### Option 1: 완전 분리 (권장)
```
장점:
- 각 시스템 독립 운영
- 성능 최적화 용이
- 데이터 보안 강화

단점:
- 데이터 동기화 필요
- 관리 포인트 증가
```

#### Option 2: 통합 시스템
```
통합 DB 스키마:
├── clinic (병원 + 뷰티 센터 통합)
├── doctor (의료진 + 직원 통합)
├── appointment (진료 + 뷰티 예약 통합)
├── services (의료 + 뷰티 서비스)
├── products (의약품 + 뷰티 상품)
└── subscriptions (공통 구독 관리)
```

### 개발 우선순위

1. **Clinic/Branch 통합**: 병원-센터 관리 통일
2. **Subscription 정책**: 공통 요금제 관리
3. **Payment Gateway**: 결제 시스템 통합
4. **Notification**: FCM 통합 알림 시스템
5. **Analytics**: 통합 대시보드

### 보안 고려사항

- ✅ 환경변수 보안 강화
- ✅ API 인증 강화 (Sanctum)
- ✅ 데이터 암호화 (환자/고객 정보)
- ✅ HTTPS 강제
- ✅ SQL Injection 방어

### 성능 최적화

- ✅ 데이터베이스 인덱싱
- ✅ 캐싱 전략 (Redis)
- ✅ API 응답 최적화
- ✅ 이미지 최적화
- ✅ CDN 활용

---

## 📌 마이그레이션 체크리스트

### Phase 1: 데이터 매핑
- [ ] Clinic ↔ Branch 매핑 정의
- [ ] Doctor ↔ Employee 매핑 정의
- [ ] Appointment 구조 통합
- [ ] Service 구조 통합

### Phase 2: 스키마 정규화
- [ ] 중복 테이블 제거
- [ ] 공통 테이블 통합
- [ ] 외래키 재정의
- [ ] 인덱스 최적화

### Phase 3: API 통합
- [ ] 단일 인증 체계
- [ ] 공통 응답 형식
- [ ] 에러 처리 표준화
- [ ] API 문서화

### Phase 4: 테스트 및 검증
- [ ] 데이터 무결성 검증
- [ ] 성능 테스트
- [ ] 보안 취약점 점검
- [ ] 부하 테스트

---

## 🔗 관련 문서

- [KiviCare 공식 문서](https://kivicare.com)
- [Laravel 공식 문서](https://laravel.com/docs)
- [MySQL 8.0 참조](https://dev.mysql.com/doc/)

---

**작성자**: Claude
**최종 수정**: 2026-01-28
**버전**: 1.0
