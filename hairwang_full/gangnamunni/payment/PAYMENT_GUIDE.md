# 광고 결제 시스템 가이드

## 개요

PortOne (구 아임포트) 결제 시스템을 사용한 광고 과금 모듈입니다.

## 파일 구조

```
payment/
├── portone_config.php      # PortOne API 설정 및 상품 정의
├── ad_payment.php           # 결제 페이지 (프론트엔드)
├── payment_callback.php     # 결제 검증 및 처리 (백엔드)
├── ad_refund.php            # 환불 처리
└── PAYMENT_GUIDE.md         # 이 문서

sql_dumps/
├── create_payment_tables.sql      # 결제 테이블 생성
└── update_ads_for_payment.sql     # 광고 테이블 업데이트
```

## 설치

### 1. DB 테이블 생성

```bash
# 결제 테이블 생성
mysql -u root -p hairwang < sql_dumps/create_payment_tables.sql

# 광고 테이블 업데이트
mysql -u root -p hairwang < sql_dumps/update_ads_for_payment.sql
```

### 2. PortOne 설정

`payment/portone_config.php` 파일에서 API 키 설정:

```php
define('PORTONE_IMP_CODE', 'imp12345678');  // PortOne 가맹점 식별코드
define('PORTONE_API_KEY', 'your_api_key');
define('PORTONE_API_SECRET', 'your_api_secret');
```

**테스트 모드:**
- `PORTONE_TEST_MODE = true` 로 설정하면 테스트 결제 가능
- 실제 결제는 발생하지 않음

## 사용 흐름

### 1. 광고 등록 및 결제

```
admin_ads.php
  └─> 광고 추가 폼 작성
      ├─> 광고 유형 선택 (배너/프리미엄)
      ├─> 결제 상품 선택 (1개월/3개월)
      └─> "광고 추가 후 결제하기" 클릭
           └─> payment/ad_payment.php?ad_id=123&product_id=banner_1month
                ├─> PortOne 결제 팝업 열림
                ├─> 사용자 결제 완료
                └─> payment/payment_callback.php 호출
                     ├─> PortOne API로 결제 검증
                     ├─> DB에 결제 정보 저장
                     └─> 광고 활성화 + 종료일 설정
```

### 2. 결제 검증 프로세스

```php
// payment_callback.php에서 수행

1. imp_uid로 PortOne API에서 실제 결제 정보 조회
2. DB의 주문 금액과 실제 결제 금액 비교
3. 금액 일치 시:
   - gangnamunni_ad_payments 상태 업데이트 (ready → paid)
   - gangnamunni_ads 활성화 (is_active = 1)
   - start_date, end_date 설정
4. 금액 불일치 시:
   - 결제 실패 처리
   - 오류 로그 기록
```

### 3. 환불 처리

```
admin_ads.php
  └─> 광고 목록에서 "환불" 버튼 클릭
      └─> payment/ad_refund.php?ad_id=123
           ├─> PortOne API로 환불 요청
           ├─> DB 업데이트:
           │   ├─> gangnamunni_ad_payments.status = 'cancelled'
           │   └─> gangnamunni_ads.is_active = 0
           └─> 환불 로그 기록
```

## 상품 정보

| 상품 ID | 상품명 | 금액 | 기간 | 광고 유형 |
|---------|--------|------|------|-----------|
| banner_1month | 배너 광고 1개월 | 300,000원 | 30일 | banner |
| banner_3months | 배너 광고 3개월 | 800,000원 | 90일 | banner |
| premium_1month | 프리미엄 광고 1개월 | 500,000원 | 30일 | premium |
| premium_3months | 프리미엄 광고 3개월 | 1,350,000원 | 90일 | premium |

## DB 스키마

### gangnamunni_ad_payments

```sql
CREATE TABLE gangnamunni_ad_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_uid VARCHAR(100) NOT NULL UNIQUE,  -- 주문번호
    imp_uid VARCHAR(100) DEFAULT NULL,           -- PortOne 결제 ID
    ad_id INT NOT NULL,                          -- 광고 ID
    product_id VARCHAR(50) NOT NULL,             -- 상품 ID
    product_name VARCHAR(200) NOT NULL,          -- 상품명
    amount INT NOT NULL,                         -- 주문 금액
    paid_amount INT DEFAULT 0,                   -- 실결제 금액
    buyer_name VARCHAR(100) NOT NULL,
    buyer_email VARCHAR(200) DEFAULT NULL,
    buyer_tel VARCHAR(50) DEFAULT NULL,
    pg_provider VARCHAR(50) DEFAULT NULL,        -- PG사
    pg_tid VARCHAR(200) DEFAULT NULL,            -- PG 거래 ID
    status VARCHAR(20) DEFAULT 'ready',          -- ready, paid, cancelled, failed
    cancel_reason TEXT DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### gangnamunni_ads (추가 컬럼)

```sql
ALTER TABLE gangnamunni_ads
ADD COLUMN ad_type VARCHAR(20) DEFAULT 'banner',
ADD COLUMN paid_at DATETIME DEFAULT NULL,
ADD COLUMN start_date DATETIME DEFAULT NULL,
ADD COLUMN end_date DATETIME DEFAULT NULL;
```

## 자동 만료 처리

MySQL 이벤트 스케줄러가 1시간마다 만료된 광고를 자동으로 비활성화합니다:

```sql
CREATE EVENT deactivate_expired_ads
ON SCHEDULE EVERY 1 HOUR
DO
BEGIN
    UPDATE gangnamunni_ads
    SET is_active = 0
    WHERE is_active = 1
    AND end_date IS NOT NULL
    AND end_date < NOW();
END;
```

**활성화 확인:**
```sql
SHOW VARIABLES LIKE 'event_scheduler';  -- 'ON'이어야 함
SET GLOBAL event_scheduler = ON;         -- 필요시 활성화
```

## 로그 파일

결제/환불 처리 시 자동으로 로그가 기록됩니다:

```
payment/logs/
├── payment_2026-02-08.log       # 결제 성공 로그
├── payment_error_2026-02-08.log # 결제 실패 로그
├── refund_2026-02-08.log        # 환불 성공 로그
└── refund_error_2026-02-08.log  # 환불 실패 로그
```

## 테스트

### 테스트 결제 카드 (PortOne 테스트 모드)

- 카드번호: `5262-1234-5678-9000` (BC카드)
- 유효기간: 임의의 미래 날짜
- CVC: 123
- 비밀번호: 00

### 수동 테스트

1. `admin_ads.php` 로그인 (admin / admin123)
2. 새 광고 추가 폼에서:
   - 제목, 이미지 URL 등 입력
   - 광고 유형 선택 (배너/프리미엄)
   - 결제 상품 선택
   - "광고 추가 후 결제하기" 클릭
3. 결제 페이지에서 테스트 카드로 결제
4. 결제 완료 후 `admin_ads.php`로 리다이렉트
5. 광고 목록에서 결제 상태, 광고 기간 확인

### 환불 테스트

1. 결제 완료된 광고의 "환불" 버튼 클릭
2. 확인 다이얼로그에서 "확인"
3. 광고 비활성화 및 결제 취소 확인

## 보안 고려사항

1. **서버사이드 검증 필수**
   - 클라이언트에서 전송된 금액을 믿지 않음
   - PortOne API로 실제 결제 금액 확인

2. **금액 변조 방지**
   - `payment_callback.php`에서 DB 주문금액 vs 실제 결제금액 비교
   - 불일치 시 결제 실패 처리

3. **merchant_uid 중복 방지**
   - `AD_YmdHis_RAND` 형식으로 고유 ID 생성
   - DB UNIQUE 제약조건

4. **결제 상태 관리**
   - ready: 결제 대기
   - paid: 결제 완료
   - cancelled: 취소/환불
   - failed: 결제 실패

## 프로덕션 배포 전 체크리스트

- [ ] PortOne 실제 API 키 설정
- [ ] `PORTONE_TEST_MODE = false` 설정
- [ ] MySQL 이벤트 스케줄러 활성화 확인
- [ ] 로그 디렉토리 권한 설정 (`chmod 755 payment/logs/`)
- [ ] 상품 가격 및 기간 최종 확인
- [ ] 환불 정책 문서화
- [ ] 고객 지원 연락처 추가

## 문제 해결

### 결제 팝업이 안 뜨는 경우

1. PortOne JS SDK 로드 확인: `https://cdn.iamport.kr/v1/iamport.js`
2. IMP.init() 호출 확인
3. 브라우저 콘솔에서 오류 확인

### 결제는 성공했는데 광고가 활성화 안 되는 경우

1. `payment/logs/payment_error_*.log` 확인
2. DB `gangnamunni_ad_payments` 테이블에서 status 확인
3. `payment_callback.php`에서 금액 검증 로직 확인

### 자동 만료가 작동하지 않는 경우

```sql
-- 이벤트 스케줄러 확인
SHOW VARIABLES LIKE 'event_scheduler';

-- 이벤트 목록 확인
SHOW EVENTS;

-- 수동으로 만료 처리
UPDATE gangnamunni_ads
SET is_active = 0
WHERE is_active = 1
AND end_date IS NOT NULL
AND end_date < NOW();
```

## 연락처

- PortOne 고객센터: https://portone.io/korea/ko/support
- PortOne 개발자 문서: https://developers.portone.io/docs/ko/readme
