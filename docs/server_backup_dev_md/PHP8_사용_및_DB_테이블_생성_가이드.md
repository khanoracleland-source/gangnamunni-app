# PHP 8 사용 및 DB 테이블 생성 가이드

## ✅ 완료된 작업

### PHP 8로 복구
- **파일**: `C:\xampp\apache\conf\extra\httpd-xampp.conf`
- **상태**: PHP 8.2.12 활성화, PHP 7.4 비활성화

**이유**: PHP 7.4가 Apache와 Visual C++ 런타임 호환성 문제로 계속 크래시 발생

---

## 🚀 즉시 실행

### 1단계: Apache 시작
XAMPP Control Panel → Apache → **Start** 버튼 클릭

**예상 결과**: 정상 시작됨 (녹색 "Running")

---

### 2단계: PHP 버전 확인
```
http://carebaby.seoulbeauty.local/test_connection.php
```

**표시될 내용**:
```
PHP Version: 8.2.12
✓ DB 연결 성공!
```

---

### 3단계: 광고 시스템 DB 테이블 생성

현재 광고 시스템 테이블들이 존재하지 않습니다:
```
✗ nf_advertiser - 없음
✗ nf_advertisement - 없음
✗ nf_ad_statistics - 없음
✗ nf_ad_pricing - 없음
✗ nf_ad_payment - 없음
```

#### 방법 1: SQL 파일 실행 (권장)

SQL 파일이 있다면:
```bash
mysql -u root -p<REMOVED_DB_PASSWORD> carecraft < H:/carebaby_project/server_backup_dev/carebaby/sql/advertisement_tables.sql
```

#### 방법 2: phpMyAdmin 사용

1. `http://localhost/phpmyadmin` 접속
2. 좌측에서 `carecraft` 데이터베이스 선택
3. 상단 "SQL" 탭 클릭
4. 아래 SQL 복사 & 붙여넣기
5. "실행" 버튼 클릭

#### 방법 3: 직접 SQL 실행

```sql
-- MySQL 접속
mysql -u root -p<REMOVED_DB_PASSWORD>

USE carecraft;

-- 1. 광고주 테이블
CREATE TABLE IF NOT EXISTS `nf_advertiser` (
  `advertiser_no` int(11) NOT NULL AUTO_INCREMENT,
  `member_no` int(11) NOT NULL,
  `advertiser_company` varchar(200) DEFAULT NULL COMMENT '회사명',
  `advertiser_business_no` varchar(50) DEFAULT NULL COMMENT '사업자등록번호',
  `advertiser_ceo_name` varchar(100) DEFAULT NULL COMMENT '대표자명',
  `advertiser_tel` varchar(20) DEFAULT NULL COMMENT '전화번호',
  `advertiser_email` varchar(100) DEFAULT NULL COMMENT '이메일',
  `advertiser_address` varchar(255) DEFAULT NULL COMMENT '주소',
  `advertiser_address_detail` varchar(255) DEFAULT NULL COMMENT '상세주소',
  `advertiser_zipcode` varchar(10) DEFAULT NULL COMMENT '우편번호',
  `advertiser_status` enum('pending','approved','rejected','suspended') DEFAULT 'pending' COMMENT '상태',
  `advertiser_created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '등록일',
  `advertiser_approved_at` datetime DEFAULT NULL COMMENT '승인일',
  `advertiser_deleted_at` datetime DEFAULT NULL COMMENT '삭제일 (Soft Delete)',
  PRIMARY KEY (`advertiser_no`),
  KEY `idx_member_no` (`member_no`),
  KEY `idx_status` (`advertiser_status`),
  KEY `idx_deleted` (`advertiser_deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='광고주 정보';

-- 2. 광고 테이블
CREATE TABLE IF NOT EXISTS `nf_advertisement` (
  `ad_no` int(11) NOT NULL AUTO_INCREMENT,
  `advertiser_no` int(11) NOT NULL COMMENT '광고주 번호',
  `ad_title` varchar(200) NOT NULL COMMENT '광고 제목',
  `ad_image` varchar(255) DEFAULT NULL COMMENT '광고 이미지 경로',
  `ad_link_url` varchar(500) DEFAULT NULL COMMENT '광고 링크 URL',
  `ad_target_blank` tinyint(1) DEFAULT '1' COMMENT '새창 열기 (1: 새창, 0: 현재창)',
  `ad_status` enum('pending','approved','rejected','active','paused','expired') DEFAULT 'pending' COMMENT '광고 상태',
  `ad_start_date` datetime NOT NULL COMMENT '광고 시작일',
  `ad_end_date` datetime NOT NULL COMMENT '광고 종료일',
  `ad_priority` int(11) DEFAULT '0' COMMENT '우선순위 (높을수록 먼저 노출)',
  `ad_position` varchar(50) DEFAULT 'all' COMMENT '광고 위치 (all, main, hospital_view)',
  `ad_view_count` int(11) DEFAULT '0' COMMENT '조회수',
  `ad_click_count` int(11) DEFAULT '0' COMMENT '클릭수',
  `ad_daily_price` decimal(10,2) DEFAULT '10000.00' COMMENT '일일 광고비',
  `ad_payment_status` enum('pending','paid','expired') DEFAULT 'pending' COMMENT '결제 상태',
  `ad_created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '등록일',
  `ad_updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일',
  `ad_deleted_at` datetime DEFAULT NULL COMMENT '삭제일 (Soft Delete)',
  PRIMARY KEY (`ad_no`),
  KEY `idx_advertiser_no` (`advertiser_no`),
  KEY `idx_status` (`ad_status`),
  KEY `idx_dates` (`ad_start_date`,`ad_end_date`),
  KEY `idx_priority` (`ad_priority`),
  KEY `idx_position` (`ad_position`),
  KEY `idx_deleted` (`ad_deleted_at`),
  CONSTRAINT `fk_ad_advertiser` FOREIGN KEY (`advertiser_no`) REFERENCES `nf_advertiser` (`advertiser_no`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='광고 정보';

-- 3. 광고 통계 테이블
CREATE TABLE IF NOT EXISTS `nf_ad_statistics` (
  `stat_no` int(11) NOT NULL AUTO_INCREMENT,
  `ad_no` int(11) NOT NULL COMMENT '광고 번호',
  `stat_date` date NOT NULL COMMENT '통계 날짜',
  `stat_view_count` int(11) DEFAULT '0' COMMENT '일별 조회수',
  `stat_click_count` int(11) DEFAULT '0' COMMENT '일별 클릭수',
  `stat_ctr` decimal(5,2) DEFAULT '0.00' COMMENT '클릭률 (CTR %)',
  `stat_created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '생성일',
  PRIMARY KEY (`stat_no`),
  UNIQUE KEY `unique_ad_date` (`ad_no`,`stat_date`),
  KEY `idx_date` (`stat_date`),
  CONSTRAINT `fk_stat_ad` FOREIGN KEY (`ad_no`) REFERENCES `nf_advertisement` (`ad_no`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='광고 통계';

-- 4. 광고 요금 테이블
CREATE TABLE IF NOT EXISTS `nf_ad_pricing` (
  `pricing_no` int(11) NOT NULL AUTO_INCREMENT,
  `pricing_type` enum('daily','priority','position') NOT NULL COMMENT '요금 유형',
  `daily_price` decimal(10,2) DEFAULT '10000.00' COMMENT '일일 기본 요금',
  `priority_level` int(11) DEFAULT NULL COMMENT '우선순위 레벨 (10, 20, 30, 50)',
  `priority_price` decimal(10,2) DEFAULT NULL COMMENT '우선순위별 추가 요금',
  `position_type` varchar(50) DEFAULT NULL COMMENT '위치 유형 (all, main, hospital_view)',
  `position_price` decimal(10,2) DEFAULT NULL COMMENT '위치별 추가 요금',
  `pricing_status` enum('active','inactive') DEFAULT 'active' COMMENT '요금 상태',
  `pricing_created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '생성일',
  `pricing_updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '수정일',
  PRIMARY KEY (`pricing_no`),
  KEY `idx_type` (`pricing_type`),
  KEY `idx_status` (`pricing_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='광고 요금표';

-- 5. 광고 결제 테이블
CREATE TABLE IF NOT EXISTS `nf_ad_payment` (
  `payment_no` int(11) NOT NULL AUTO_INCREMENT,
  `ad_no` int(11) NOT NULL COMMENT '광고 번호',
  `advertiser_no` int(11) NOT NULL COMMENT '광고주 번호',
  `payment_amount` decimal(10,2) NOT NULL COMMENT '결제 금액',
  `payment_method` varchar(50) DEFAULT NULL COMMENT '결제 수단',
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending' COMMENT '결제 상태',
  `payment_date` datetime DEFAULT NULL COMMENT '결제 완료일',
  `payment_tid` varchar(100) DEFAULT NULL COMMENT '결제 거래 ID',
  `payment_created_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '생성일',
  PRIMARY KEY (`payment_no`),
  KEY `idx_ad_no` (`ad_no`),
  KEY `idx_advertiser_no` (`advertiser_no`),
  KEY `idx_status` (`payment_status`),
  KEY `idx_tid` (`payment_tid`),
  CONSTRAINT `fk_payment_ad` FOREIGN KEY (`ad_no`) REFERENCES `nf_advertisement` (`ad_no`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_advertiser` FOREIGN KEY (`advertiser_no`) REFERENCES `nf_advertiser` (`advertiser_no`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='광고 결제 내역';

-- 6. 기본 요금 데이터 삽입
INSERT IGNORE INTO nf_ad_pricing (pricing_type, daily_price, priority_level, priority_price, position_type, position_price) VALUES
-- 일일 기본 요금
('daily', 10000.00, NULL, NULL, NULL, NULL),

-- 우선순위별 요금
('priority', NULL, 10, 50000.00, NULL, NULL),
('priority', NULL, 20, 100000.00, NULL, NULL),
('priority', NULL, 30, 150000.00, NULL, NULL),
('priority', NULL, 50, 300000.00, NULL, NULL),

-- 위치별 요금
('position', NULL, NULL, NULL, 'hospital_view', 5000.00),
('position', NULL, NULL, NULL, 'main', 30000.00),
('position', NULL, NULL, NULL, 'all', 50000.00);

-- 완료 메시지
SELECT '광고 시스템 테이블 생성 완료!' AS message;
SHOW TABLES LIKE 'nf_ad%';
```

---

## 📋 테이블 생성 확인

테이블이 제대로 생성되었는지 확인:

```sql
USE carecraft;

-- 테이블 목록 확인
SHOW TABLES LIKE 'nf_ad%';

-- 예상 결과:
-- nf_advertiser
-- nf_advertisement
-- nf_ad_payment
-- nf_ad_pricing
-- nf_ad_statistics

-- 각 테이블 구조 확인
DESCRIBE nf_advertiser;
DESCRIBE nf_advertisement;
DESCRIBE nf_ad_statistics;
DESCRIBE nf_ad_pricing;
DESCRIBE nf_ad_payment;

-- 기본 요금 데이터 확인
SELECT * FROM nf_ad_pricing;
```

---

## ✔️ 광고 시스템 테스트

### 1. test_connection.php 재접속
```
http://carebaby.seoulbeauty.local/test_connection.php
```

**예상 결과**:
```
PHP Version: 8.2.12
✓ DB 연결 성공!
✓ nf_advertiser - 존재함
✓ nf_advertisement - 존재함
✓ nf_ad_statistics - 존재함
✓ nf_ad_pricing - 존재함
✓ nf_ad_payment - 존재함
```

### 2. 광고 관리 페이지 접속
```
http://carebaby.seoulbeauty.local/admin/ad_list.php
```

---

## 🔍 PHP 8 호환성 문제 해결

NFOR 소스가 PHP 7.0용이지만, **대부분 PHP 8에서도 작동**합니다.

### 예상 가능한 문제와 해결

#### 1. Deprecated 경고
```
Deprecated: ... in ...
```
→ 경고일 뿐, 작동은 됨. 무시 가능.

#### 2. 엄격한 타입 체크 에러
```
TypeError: ... must be of type ...
```
→ 발생 시 해당 파일의 해당 라인 수정 필요

#### 3. mysqli 함수 호환성
대부분 호환됨. PHP 8에서도 mysqli 계속 지원.

---

## 💡 PHP 7.4 대신 PHP 8 사용의 장단점

### 장점
✅ Apache 안정적으로 작동
✅ 최신 보안 업데이트
✅ 더 나은 성능
✅ 더 많은 기능

### 단점
❌ 일부 deprecated 함수 경고
❌ 엄격한 타입 체크로 일부 코드 수정 필요할 수 있음

### 결론
**PHP 8에서 먼저 테스트**하고, 문제 발생 시 개별 수정하는 것이 더 실용적입니다.

---

## 🎯 다음 단계

1. **Apache 시작** (XAMPP Control Panel)
2. **test_connection.php 확인** (PHP 8.2.12 표시 확인)
3. **DB 테이블 생성** (위 SQL 실행)
4. **광고 관리 페이지 접속 테스트**
5. **에러 발생 시 보고** → 개별 수정

---

**작성일**: 2025-12-30
**PHP 버전**: 8.2.12 (PHP 7.4 호환성 문제로 변경)
**상태**: Apache 복구 완료, DB 테이블 생성 대기
