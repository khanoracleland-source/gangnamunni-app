# SNS 로그인 & PG 연동 AI 개발 프롬프트

## 1단계: 기반 구축 (프롬프트)

```
그누보드5 리빌더에 API 기반 SNS 로그인과 PG 결제 시스템을 개발하려고 합니다.

## 작업 내용:
1. API 인증 시스템 구축 (API 키 + IP 화이트리스트)
2. API 프레임워크 작성 (요청/응답 표준화, 로깅, 에러 처리)
3. 데이터베이스 테이블 생성 (g5_api_keys, g5_api_ips, g5_api_logs 등)

## 기술 요구사항:
- PHP 7.4+, MySQL 5.7+
- HMAC SHA256 기반 API 토큰 인증
- JSON RESTful API
- 기존 그누보드 소스는 최소 수정 (extend 시스템 활용)
- 독립적인 폴더 구조 (api/sns/, api/pg/)

## 코드 구조:
```
api/common.php           # API 공통 (인증, 로깅, 응답)
api/_config.php          # API 설정
extend/api_auth.extend.php # API 인증 확장
```

## 기능 요구사항:
1. API 키 발급: 64자 토큰 + 시크릿 키
2. IP 화이트리스트: 여러 IP 등록 가능
3. 요청 로깅: 모든 API 요청 기록
4. 에러 처리: 표준 JSON 포맷 (success, code, message, data)
5. CORS: 외부 도메인에서 접근 허용

## 데이터베이스 테이블:
```sql
-- g5_api_keys: API 키 저장 (ak_id, ak_name, ak_key, ak_secret, ak_type, ak_ip_whitelist, ak_status, ak_created_at, ak_expired_at)
-- g5_api_ips: IP 화이트리스트 (ai_id, ak_id, ai_ip, ai_description, ai_status, ai_created_at)
-- g5_api_logs: 요청 로그 (al_id, ak_id, al_endpoint, al_method, al_request_ip, al_request_data, al_response_code, al_response_data, al_error, al_execution_time, al_created_at)
```

## 주의사항:
- 기존 그누보드 함수 활용 (sql_fetch, sql_query 등)
- 보안: SQL Injection 방지, XSS 방지
- 로그에 민감 정보 제거
- 자동 테이블 생성 기능 포함

## 실행 순서:
1. api/_config.php 작성
2. api/common.php 작성 (인증 미들웨어, 로깅, 응답)
3. 데이터베이스 테이블 생성 SQL 작성
4. 설치 테스트

코드를 작성하고 각 파일의 역할을 설명해주세요.
```

---

## 2단계: SNS 로그인 API (프롬프트)

```
그누보드5에서 외부 사이트에서 IP로 SNS 로그인을 호출하는 API를 개발하려고 합니다.

## 작업 내용:
1. SNS 로그인 API 엔드포인트 (api/sns/login.php)
2. SNS 콜백 처리 (api/sns/callback.php)
3. 추가 필드 시스템 (유연한 필드 저장)

## 기존 플러그인:
- plugin/social/ 에 이미 SNS 로그인 플러그인 존재
- HybridAuth 라이브러리 사용 (Naver, Kakao, Facebook, Google, Twitter, Payco)
- include_once(G5_SOCIAL_LOGIN_PATH.'/includes/functions.php') 에 함수들 존재

## 기존 SNS 로그인 흐름:
```
사용자 클릭 → SNS 인증 → 콜백 → 세션 저장 → 로그인/회원가입
```

## 새로운 API 흐름:
```
외부 사이트 → API 요청 (헤더: API-Key) → SNS 인증 → JSON 응답
```

## API 요청 형식:
```
POST /api/sns/login.php
Headers:
  API-Key: [발급받은 API 키]
  API-Secret: [시크릿 키]
  X-API-IP: [호출 사이트 IP]

Body (JSON):
{
  "provider": "naver",  // naver, kakao, facebook, google, twitter, payco
  "access_token": "naver_access_token",  // 외부 사이트에서 이미 받은 SNS 토큰
  "custom_fields": {      // 추가 필드 (유연)
    "site_id": "123",
    "user_type": "premium"
  }
}
```

## API 응답 형식:
```json
{
  "success": true,
  "code": "LOGIN_SUCCESS",
  "message": "로그인 성공",
  "data": {
    "mb_id": "user123",
    "mb_name": "홍길동",
    "mb_email": "user@example.com",
    "mb_nick": "길동이",
    "mb_point": 1000,
    "custom_fields": {
      "site_id": "123",
      "user_type": "premium"
    }
  }
}
```

## 기존 함수 활용:
- social_get_provider_service_name($provider): SNS 서비스 이름 반환
- get_social_convert_id($identifier, $service): 아이디 생성
- social_extends_get_keys($provider): SNS 키 반환

## 새로운 기능:
1. access_token으로 SNS 사용자 정보 가져오기
2. 회원이면 로그인, 신규면 자동 회원가입
3. 추가 필드 JSON 형태로 저장 (g5_sns_api_logs 테이블)
4. 로그 기록 (g5_sns_api_logs)

## 데이터베이스 테이블:
```sql
-- g5_sns_api_logs: SNS 로그인 로그 (sl_id, ak_id, sl_provider, sl_action, sl_mb_id, sl_identifier, sl_success, sl_error_code, sl_error_message, sl_custom_fields, sl_created_at)
```

## 에러 코드:
- MISSING_API_KEY: API 키 없음
- INVALID_API_KEY: 유효하지 않은 API 키
- INVALID_PROVIDER: 지원하지 않는 SNS
- INVALID_ACCESS_TOKEN: 유효하지 않은 액세스 토큰
- LOGIN_SUCCESS: 로그인 성공
- REGISTER_SUCCESS: 회원가입 성공

## 주의사항:
- 기존 plugin/social/functions.php 함수 활용
- 세션 대신 JSON 응답
- 기존 로그인 흐름 방해하지 않기
- 보안: API 키 검증, IP 검증, 토큰 유효성 검증

## 코드 구조:
```
api/sns/
├── login.php         # 메인 엔드포인트
├── callback.php      # SNS 콜백 (필요한 경우)
└── _common.php       # SNS API 공통
```

## 실행 순서:
1. api/sns/_common.php 작성 (함수 로드)
2. api/sns/login.php 작성 (메인 로직)
3. 테스트 케이스 작성
4. 각 SNS 별 테스트

코드를 작성하고, 기존 plugin/social/functions.php의 어떤 함수를 어떻게 활용했는지 설명해주세요.
```

---

## 3단계: PG 결제 API (프롬프트)

```
그누보드5 쇼핑몰에서 외부 사이트에서 PG 결제를 호출하는 API를 개발하려고 합니다.

## 작업 내용:
1. 상품 조회 API (api/pg/item.php)
2. 주문 생성 API (api/pg/order.php)
3. 결제 요청 API (api/pg/payment.php)
4. 결제 콜백/웹훅 (api/pg/webhook.php)

## 기존 결제 모듈:
- shop/settle_inicis.inc.php (이니시스)
- shop/settle_lg.inc.php (LG U+)
- shop/settle_kcp.inc.php (KCP)
- shop/settle_kakaopay.inc.php (카카오페이)
- shop/settle_nicepay.inc.php (나이스페이)

## 기존 주문 처리 흐름:
```
장바구니 → orderform.php → 결제 모듈 → PG 사 → 콜백 → orderformupdate.php
```

## 새로운 API 흐름:
```
외부 사이트 → API 요청 → 결제 모듈 → PG 사 → 외부 사이트 콜백 → 웹훅 → 주문 완료
```

## 1. 상품 조회 API:
```
GET /api/pg/item.php
Headers:
  API-Key: [API 키]
  API-Secret: [시크릿 키]

Query:
  it_id: [상품 ID, 선택사항]

응답:
{
  "success": true,
  "code": "ITEM_SUCCESS",
  "data": [
    {
      "it_id": "123",
      "it_name": "상품명",
      "it_price": 10000,
      "it_stock_qty": 50,
      "it_img1": "이미지URL"
    }
  ]
}
```

## 2. 주문 생성 API:
```
POST /api/pg/order.php
Headers:
  API-Key: [API 키]
  API-Secret: [시크릭 키]

Body:
{
  "items": [
    {
      "it_id": "123",
      "it_qty": 2
    }
  ],
  "od_name": "주문자명",
  "od_tel": "전화번호",
  "od_hp": "휴대폰",
  "od_zip1": "우편번호1",
  "od_addr1": "주소1",
  "od_addr2": "주소2",
  "od_b_name": "받는분이름",
  "od_b_tel": "받는분전화",
  "od_b_hp": "받는분휴대폰",
  "od_b_zip1": "우편번호1",
  "od_b_addr1": "주소1",
  "od_b_addr2": "주소2",
  "od_memo": "메모",
  "callback_url": "https://external-site.com/payment/callback",
  "custom_fields": {
    "external_order_id": "EXT12345",
    "site_id": "123"
  }
}

응답:
{
  "success": true,
  "code": "ORDER_CREATED",
  "data": {
    "po_id": 1,
    "po_external_order_id": "EXT12345",
    "od_id": "G5123456789",
    "total_amount": 20000,
    "pg_url": "https://pg-site.com/payment?order_id=...",
    "pg_code": "inicis"
  }
}
```

## 3. 결제 요청 API:
```
POST /api/pg/payment.php
Headers:
  API-Key: [API 키]
  API-Secret: [시크릿 키]

Body:
{
  "po_id": 1,
  "pg_code": "inicis",  // inicis, lg, kcp, kakaopay, nicepay
  "pay_method": "card",  // card, bank, vbank, phone
  "pay_name": "주문자명",
  "pay_email": "이메일"
}

응답:
{
  "success": true,
  "code": "PAYMENT_READY",
  "data": {
    "pg_url": "https://pg-site.com/checkout",
    "pg_params": {...}
  }
}
```

## 4. 웹훅 (PG 사에서 콜백):
```
POST /api/pg/webhook.php
Headers:
  API-Key: [API 키]

Body:
{
  "pg_code": "inicis",
  "pg_tid": "TID123456789",
  "od_id": "G5123456789",
  "po_id": 1,
  "amount": 20000,
  "status": "paid"
}

응답:
{
  "success": true,
  "code": "WEBHOOK_SUCCESS",
  "data": {
    "od_id": "G5123456789",
    "status": "결제완료"
  }
}
```

## 기존 함수 활용:
- get_item_info($it_id): 상품 정보
- get_order_info($od_id): 주문 정보
- settle_inicis(): 이니시스 결제
- settle_lg(): LG 결제

## 데이터베이스 테이블:
```sql
-- g5_pg_api_orders: PG API 주문 (po_id, ak_id, po_external_order_id, po_g5_order_id, po_item_id, po_item_name, po_amount, po_status, po_pg_code, po_pg_tid, po_pg_response, po_callback_url, po_custom_fields, po_created_at, po_updated_at)
```

## 주문 상태:
- pending: 주문 대기
- paid: 결제 완료
- cancelled: 취소
- failed: 실패

## 에러 코드:
- ITEM_NOT_FOUND: 상품 없음
- OUT_OF_STOCK: 재고 부족
- ORDER_FAILED: 주문 실패
- PAYMENT_FAILED: 결제 실패
- WEBHOOK_SUCCESS: 웹훅 성공

## 주의사항:
- 기존 shop/ 결제 모듈 함수 활용
- 외부 사이트 콜백 URL 동적 처리
- 기존 주문 흐름 방해하지 않기
- 보안: API 키 검증, 금액 검증, 콜백 서명 검증

## 코드 구조:
```
api/pg/
├── _common.php        # PG API 공통
├── item.php           # 상품 조회
├── order.php          # 주문 생성
├── payment.php        # 결제 요청
├── webhook.php        # 웹훅
└── settle.inc.php     # 결제 모듈 (기존 확장)
```

## 실행 순서:
1. api/pg/_common.php 작성
2. api/pg/item.php 작성
3. api/pg/order.php 작성
4. api/pg/payment.php 작성
5. api/pg/webhook.php 작성
6. 각 PG 모듈 테스트

코드를 작성하고, 기존 shop/ 결제 모듈의 어떤 함수를 어떻게 활용했는지 설명해주세요.
```

---

## 4단계: 관리자 페이지 (프롬프트)

```
그누보드5 관리자 페이지에서 SNS 로그인 API와 PG 결제 API를 관리하는 기능을 개발하려고 합니다.

## 작업 내용:
1. API 관리 메뉴
2. SNS 로그인 API 관리
3. PG 결제 API 관리
4. 로그 조회 및 통계

## 기존 관리자 구조:
- adm/_common.php: 관리자 공통
- adm/admin.menu100.php: 기본 설정 메뉴
- adm/admin.menu400.shop_1of2.php: 쇼핑몰 관리 메뉴

## 메뉴 구조 (admin.menu600.php):
```php
<?php
$menu['menu600'] = array(
    array('600000', 'API 관리', G5_ADMIN_URL.'/api_admin/', 'api_config'),
    array('600100', 'API 키 관리', G5_ADMIN_URL.'/api_admin/keylist.php', 'api_key'),
    array('600110', 'IP 화이트리스트', G5_ADMIN_URL.'/api_admin/iplist.php', 'api_ip'),
    array('600200', 'SNS 로그인 API', G5_ADMIN_URL.'/api_admin/sns/', 'api_sns'),
    array('600210', '로그인 로그', G5_ADMIN_URL.'/api_admin/sns/loginlog.php', 'api_sns_log'),
    array('600300', 'PG 결제 API', G5_ADMIN_URL.'/api_admin/pg/', 'api_pg'),
    array('600310', '주문 요청', G5_ADMIN_URL.'/api_admin/pg/orderlist.php', 'api_pg_order'),
    array('600320', '결제 로그', G5_ADMIN_URL.'/api_admin/pg/paymentlog.php', 'api_pg_log'),
    array('600400', 'API 통계', G5_ADMIN_URL.'/api_admin/stats.php', 'api_stats'),
);
```

## 1. API 키 관리 (keylist.php):
- API 키 발급/삭제/수정
- 토큰 생성 (64자 랜덤)
- 만료일 설정
- 상태 설정 (활성/비활성)

## 2. IP 화이트리스트 (iplist.php):
- IP 등록/삭제/수정
- 설명 추가

## 3. SNS 로그인 로그 (sns/loginlog.php):
- 로그인 로그 조회 (날짜, 제공자, 성공/실패)
- 로그 내용 상세 보기
- 엑셀 다운로드
- 페이징

## 4. PG 결제 주문 (pg/orderlist.php):
- 주문 요청 목록
- 주문 상태 변경
- 주문 상세 보기
- 취소 처리

## 5. PG 결제 로그 (pg/paymentlog.php):
- 결제 로그 조회
- PG 응답 내용 보기
- 에러 로그 확인

## 6. 통계 (stats.php):
- 일별 API 호출 횟수
- SNS 로그인 성공/실패 비율
- PG 결제 금액 합계
- 차트/그래프

## 기존 관리자 함수 활용:
- sql_fetch(), sql_query(): DB 쿼리
- alert(), goto_url(): 알림, 이동
- get_paging(): 페이징
- 관리자 스킨: adm/admin.head.php, adm/admin.tail.php

## 코드 구조:
```
adm/api_admin/
├── _common.php              # API 관리자 공통
├── index.php                # API 관리 메인
├── keylist.php              # API 키 목록
├── keyform.php              # API 키 등록/수정 폼
├── iplist.php               # IP 화이트리스트
├── sns/
│   └── loginlog.php         # SNS 로그인 로그
├── pg/
│   ├── orderlist.php        # PG 주문 목록
│   └── paymentlog.php      # PG 결제 로그
└── stats.php                # 통계
```

## 스킨 구조:
```
theme/basic/admin/api_admin/
├── keylist.skin.php
├── iplist.skin.php
├── sns/loginlog.skin.php
├── pg/orderlist.skin.php
├── pg/paymentlog.skin.php
└── stats.skin.php
```

## 주의사항:
- 기존 관리자 스타일 유지
- XSS 방지 (clean_xss_tags)
- CSRF 보안 (토큰)
- 대량 데이터 페이징
- 엑셀 다운로드 (PHPExcel 또는 CSV)

## 실행 순서:
1. adm/admin.menu600.php 작성
2. adm/api_admin/_common.php 작성
3. adm/api_admin/keylist.php 작성 (키 발급/관리)
4. adm/api_admin/iplist.php 작성
5. adm/api_admin/sns/loginlog.php 작성
6. adm/api_admin/pg/orderlist.php 작성
7. adm/api_admin/pg/paymentlog.php 작성
8. adm/api_admin/stats.php 작성
9. 각 스킨 작성

코드를 작성하고, 기존 관리자 시스템의 어떤 부분을 재사용했는지 설명해주세요.
```

---

## 5단계: 설치 및 이식 (프롬프트)

```
그누보드5에 SNS 로그인 API와 PG 결제 API 시스템을 설치하고, 다른 그누보드 사이트에 이식하는 SQL 파일과 가이드를 작성하려고 합니다.

## 작업 내용:
1. 설치 SQL 파일 작성 (테이블 생성, 초기 데이터)
2. 삭제 SQL 파일 작성 (테이블 삭제, 롤백)
3. 설치 가이드 작성
4. 이식 가이드 작성
5. API 문서 작성

## 1. 설치 SQL (install/sns_pg_api.sql):
```sql
-- API 키 테이블
CREATE TABLE IF NOT EXISTS `g5_api_keys` (
  `ak_id` int(11) NOT NULL AUTO_INCREMENT,
  `ak_name` varchar(100) NOT NULL,
  `ak_key` varchar(64) NOT NULL,
  `ak_secret` varchar(64) NOT NULL,
  `ak_type` enum('sns','pg') NOT NULL,
  `ak_ip_whitelist` text,
  `ak_status` tinyint(4) NOT NULL DEFAULT '1',
  `ak_created_at` datetime NOT NULL,
  `ak_expired_at` datetime,
  `ak_last_used` datetime,
  PRIMARY KEY (`ak_id`),
  UNIQUE KEY `ak_key` (`ak_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- IP 화이트리스트 테이블
CREATE TABLE IF NOT EXISTS `g5_api_ips` (
  `ai_id` int(11) NOT NULL AUTO_INCREMENT,
  `ak_id` int(11) NOT NULL,
  `ai_ip` varchar(45) NOT NULL,
  `ai_description` varchar(255),
  `ai_status` tinyint(4) NOT NULL DEFAULT '1',
  `ai_created_at` datetime NOT NULL,
  PRIMARY KEY (`ai_id`),
  KEY `ak_id` (`ak_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API 로그 테이블
CREATE TABLE IF NOT EXISTS `g5_api_logs` (
  `al_id` int(11) NOT NULL AUTO_INCREMENT,
  `ak_id` int(11),
  `al_endpoint` varchar(255) NOT NULL,
  `al_method` varchar(10) NOT NULL,
  `al_request_ip` varchar(45) NOT NULL,
  `al_request_data` text,
  `al_response_code` int(11),
  `al_response_data` text,
  `al_error` text,
  `al_execution_time` float,
  `al_created_at` datetime NOT NULL,
  PRIMARY KEY (`al_id`),
  KEY `ak_id` (`ak_id`),
  KEY `al_created_at` (`al_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SNS 로그인 API 로그
CREATE TABLE IF NOT EXISTS `g5_sns_api_logs` (
  `sl_id` int(11) NOT NULL AUTO_INCREMENT,
  `ak_id` int(11),
  `sl_provider` varchar(50) NOT NULL,
  `sl_action` enum('login','register','error') NOT NULL,
  `sl_mb_id` varchar(20),
  `sl_identifier` varchar(255),
  `sl_success` tinyint(4) NOT NULL,
  `sl_error_code` varchar(50),
  `sl_error_message` text,
  `sl_custom_fields` text,
  `sl_created_at` datetime NOT NULL,
  PRIMARY KEY (`sl_id`),
  KEY `ak_id` (`ak_id`),
  KEY `sl_created_at` (`sl_created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PG 결제 API 주문
CREATE TABLE IF NOT EXISTS `g5_pg_api_orders` (
  `po_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ak_id` int(11) NOT NULL,
  `po_external_order_id` varchar(100) NOT NULL,
  `po_g5_order_id` bigint(20),
  `po_item_id` varchar(20),
  `po_item_name` varchar(255),
  `po_amount` int(11) NOT NULL,
  `po_status` enum('pending','paid','cancelled','failed') NOT NULL,
  `po_pg_code` varchar(50),
  `po_pg_tid` varchar(100),
  `po_pg_response` text,
  `po_callback_url` varchar(255),
  `po_custom_fields` text,
  `po_created_at` datetime NOT NULL,
  `po_updated_at` datetime NOT NULL,
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `po_external_order_id` (`po_external_order_id`),
  KEY `ak_id` (`ak_id`),
  KEY `po_status` (`po_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API 필드 정의
CREATE TABLE IF NOT EXISTS `g5_api_fields` (
  `af_id` int(11) NOT NULL AUTO_INCREMENT,
  `af_name` varchar(50) NOT NULL,
  `af_type` enum('text','number','select','checkbox','textarea') NOT NULL,
  `af_category` enum('sns','pg','common') NOT NULL,
  `af_default_value` text,
  `af_required` tinyint(4) NOT NULL DEFAULT '0',
  `af_status` tinyint(4) NOT NULL DEFAULT '1',
  `af_order` int(11) NOT NULL DEFAULT '0',
  `af_created_at` datetime NOT NULL,
  PRIMARY KEY (`af_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API 필드 값
CREATE TABLE IF NOT EXISTS `g5_api_field_values` (
  `fv_id` int(11) NOT NULL AUTO_INCREMENT,
  `af_id` int(11) NOT NULL,
  `al_id` int(11),
  `sl_id` int(11),
  `po_id` bigint(20),
  `fv_value` text,
  `fv_created_at` datetime NOT NULL,
  PRIMARY KEY (`fv_id`),
  KEY `af_id` (`af_id`),
  KEY `al_id` (`al_id`),
  KEY `sl_id` (`sl_id`),
  KEY `po_id` (`po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 샘플 API 키
INSERT INTO `g5_api_keys` (`ak_name`, `ak_key`, `ak_secret`, `ak_type`, `ak_status`, `ak_created_at`)
VALUES ('SNS 로그인 API', MD5(CONCAT('sns_api_', UNIX_TIMESTAMP())), SHA1(CONCAT('secret_', UNIX_TIMESTAMP())), 'sns', 1, NOW()),
       ('PG 결제 API', MD5(CONCAT('pg_api_', UNIX_TIMESTAMP())), SHA1(CONCAT('secret_', UNIX_TIMESTAMP())), 'pg', 1, NOW());
```

## 2. 삭제 SQL (install/sns_pg_api_uninstall.sql):
```sql
DROP TABLE IF EXISTS `g5_api_field_values`;
DROP TABLE IF EXISTS `g5_api_fields`;
DROP TABLE IF EXISTS `g5_pg_api_orders`;
DROP TABLE IF EXISTS `g5_sns_api_logs`;
DROP TABLE IF EXISTS `g5_api_logs`;
DROP TABLE IF EXISTS `g5_api_ips`;
DROP TABLE IF EXISTS `g5_api_keys`;
```

## 3. 설치 가이드 (INSTALL.md):
```markdown
# SNS 로그인 & PG 연동 API 설치 가이드

## 설치 순서:
1. 파일 복사
2. SQL 실행
3. 권한 설정
4. 테스트

## 파일 복사:
```
api/          → 그누보드 루트/api/
adm/api_admin/ → 그누보드 루트/adm/api_admin/
extend/       → 그누보드 루트/extend/
theme/basic/admin/api_admin/ → 그누보드 루트/theme/basic/admin/api_admin/
```

## SQL 실행:
```
PHPMyAdmin에서 install/sns_pg_api.sql 실행
또는
mysql -u user -p dbname < install/sns_pg_api.sql
```

## 권한 설정:
```
chmod 755 api/
chmod 755 adm/api_admin/
chmod 777 data/api/
```

## 테스트:
1. 관리자 접속 → API 관리 메뉴 확인
2. API 키 발급
3. curl로 테스트
```

## 4. 이식 가이드 (MIGRATION.md):
```markdown
# 다른 그누보드에 이식 가이드

## 이식 순서:
1. 파일 복사
2. SQL 실행
3. 설정 확인
4. 테스트

## 파일 복사 (위와 동일)

## SQL 실행 (위와 동일)

## 설정 확인:
- config.php 확인
- shop.config.php 확인 (쇼핑몰인 경우)
- plugin/social/ 확인 (SNS 로그인인 경우)

## 기존 소스 수정:
- 없음 (extend 시스템 활용)
```

## 5. API 문서 (API.md):
```markdown
# SNS 로그인 & PG 결제 API 문서

## 기본 URL:
https://your-site.com/api/

## 인증:
Headers:
  API-Key: [API 키]
  API-Secret: [시크릿 키]

## SNS 로그인 API:
POST /api/sns/login.php
...

## PG 결제 API:
GET /api/pg/item.php
POST /api/pg/order.php
POST /api/pg/payment.php
POST /api/pg/webhook.php
...
```

## 주의사항:
- 그누보드 버전 호환성 (5.4 이상)
- PHP 버전 (7.4+)
- MySQL 버전 (5.7+)
- SSL 인증서 (HTTPS 필수)

## 실행 순서:
1. install/sns_pg_api.sql 작성
2. install/sns_pg_api_uninstall.sql 작성
3. INSTALL.md 작성
4. MIGRATION.md 작성
5. API.md 작성

각 파일을 작성하고, 설치/이식 시 주의사항을 설명해주세요.
```

---

## 6단계: 전체 테스트 (프롬프트)

```
그누보드5 SNS 로그인 API와 PG 결제 API 시스템 전체를 테스트하려고 합니다.

## 테스트 항목:
1. API 인증 시스템 테스트
2. SNS 로그인 API 테스트 (각 SNS별)
3. PG 결제 API 테스트 (각 PG별)
4. 관리자 페이지 테스트
5. 통합 테스트
6. 성능 테스트
7. 보안 테스트
8. 이식 테스트

## 1. API 인증 시스템 테스트:
```bash
# 유효한 API 키 테스트
curl -X GET https://site.com/api/common/test \
  -H "API-Key: valid_key" \
  -H "API-Secret: valid_secret"

# 유효하지 않은 API 키 테스트
curl -X GET https://site.com/api/common/test \
  -H "API-Key: invalid_key" \
  -H "API-Secret: invalid_secret"

# IP 화이트리스트 테스트
curl -X GET https://site.com/api/common/test \
  -H "API-Key: valid_key" \
  -H "X-API-IP: 123.123.123.123"
```

## 2. SNS 로그인 API 테스트:
```bash
# 네이버 로그인 테스트
curl -X POST https://site.com/api/sns/login.php \
  -H "API-Key: valid_key" \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "naver",
    "access_token": "naver_access_token"
  }'

# 카카오 로그인 테스트
curl -X POST https://site.com/api/sns/login.php \
  -H "API-Key: valid_key" \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "kakao",
    "access_token": "kakao_access_token"
  }'

# 추가 필드 테스트
curl -X POST https://site.com/api/sns/login.php \
  -H "API-Key: valid_key" \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "naver",
    "access_token": "naver_access_token",
    "custom_fields": {
      "site_id": "123",
      "user_type": "premium"
    }
  }'
```

## 3. PG 결제 API 테스트:
```bash
# 상품 조회 테스트
curl -X GET "https://site.com/api/pg/item.php?it_id=123" \
  -H "API-Key: valid_key"

# 주문 생성 테스트
curl -X POST https://site.com/api/pg/order.php \
  -H "API-Key: valid_key" \
  -H "Content-Type: application/json" \
  -d '{
    "items": [{"it_id": "123", "it_qty": 2}],
    "od_name": "홍길동",
    "od_tel": "0212345678",
    "od_hp": "01012345678",
    "od_zip1": "123",
    "od_addr1": "서울시",
    "od_addr2": "강남구",
    "callback_url": "https://external.com/callback"
  }'

# 결제 요청 테스트
curl -X POST https://site.com/api/pg/payment.php \
  -H "API-Key: valid_key" \
  -H "Content-Type: application/json" \
  -d '{
    "po_id": 1,
    "pg_code": "inicis",
    "pay_method": "card"
  }'
```

## 4. 관리자 페이지 테스트:
1. API 키 발급
2. IP 화이트리스트 등록
3. 로그 조회
4. 주문 취소
5. 통계 확인

## 5. 통합 테스트:
```
외부 사이트 → API 요청 → SNS 로그인 → 회원가입 →
주문 생성 → 결제 → 웹훅 → 주문 완료
```

## 6. 성능 테스트:
```bash
# Apache Bench
ab -n 1000 -c 10 https://site.com/api/sns/login.php

# 기준:
- 응답 시간: < 1초
- 동시 요청: 10개 이상
- 처리량: 100 req/sec 이상
```

## 7. 보안 테스트:
- SQL Injection 테스트
- XSS 테스트
- CSRF 테스트
- 인증 우회 테스트
- IP 스푸핑 테스트

## 8. 이식 테스트:
- 다른 그누보드에 설치
- 기능 테스트
- 롤백 테스트

## 테스트 케이스:
```php
<?php
// tests/ApiTest.php
class ApiTest extends PHPUnit\Framework\TestCase {
    public function testValidApiKey() {
        $response = $this->callApi('GET', '/api/common/test', [
            'API-Key' => 'valid_key'
        ]);
        $this->assertEquals(200, $response['code']);
    }

    public function testInvalidApiKey() {
        $response = $this->callApi('GET', '/api/common/test', [
            'API-Key' => 'invalid_key'
        ]);
        $this->assertEquals('INVALID_API_KEY', $response['code']);
    }

    // ... 추가 테스트
}
```

## 테스트 보고서:
```markdown
# 테스트 보고서

## 1. API 인증 시스템
- ✅ 유효한 API 키
- ✅ 유효하지 않은 API 키
- ✅ IP 화이트리스트

## 2. SNS 로그인 API
- ✅ 네이버 로그인
- ✅ 카카오 로그인
- ✅ 회원가입
- ✅ 추가 필드

## 3. PG 결제 API
- ✅ 상품 조회
- ✅ 주문 생성
- ✅ 결제 요청
- ✅ 웹훅

## 4. 관리자 페이지
- ✅ API 키 관리
- ✅ 로그 조회
- ✅ 통계

## 5. 통합 테스트
- ✅ 전체 흐름

## 6. 성능 테스트
- 응답 시간: 0.5초 ✅
- 동시 요청: 10개 ✅
- 처리량: 150 req/sec ✅

## 7. 보안 테스트
- ✅ SQL Injection 방지
- ✅ XSS 방지
- ✅ CSRF 보호
- ✅ 인증 우회 방지
- ✅ IP 스푸핑 방지

## 8. 이식 테스트
- ✅ 설치
- ✅ 기능 테스트
- ✅ 롤백

## 이슈:
- 없음
```

## 실행 순서:
1. 테스트 케이스 작성
2. 각 기능별 테스트
3. 통합 테스트
4. 성능 테스트
5. 보안 테스트
6. 이식 테스트
7. 테스트 보고서 작성

테스트 케이스를 작성하고, 각 테스트 결과를 정리해주세요.
```

---

## 전체 작업 일정 (AI 개발 시)

```
1일차:
- 기반 구축 (API 인증, 프레임워크)
- 데이터베이스 설치 SQL 작성

2일차:
- SNS 로그인 API 개발
- 각 SNS별 테스트

3일차:
- PG 결제 API 개발 (상품, 주문)
- 각 PG별 테스트

4일차:
- PG 결제 API 개발 (결제, 웹훅)
- 결제 테스트

5일차:
- 관리자 페이지 개발
- 관리자 페이지 테스트

6일차:
- 설치/이식 가이드 작성
- API 문서 작성

7일차:
- 전체 테스트
- 버그 수정
- 배포
```

---

## AI 개발 팁

1. **단계별 요청**: 각 프롬프트를 순서대로 실행
2. **코드 검토**: AI가 작성한 코드를 검토 후 다음 단계로
3. **테스트 우선**: 각 단계에서 테스트 후 다음 단계로
4. **버그 수정**: 버그 발견 시 즉시 수정 요청
5. **최적화**: 성능, 보안 최적화 추가 요청

## 기존 소스 최소 수정 확인

```php
// ✅ 좋은 예 (extend 활용)
<?php
// extend/api_auth.extend.php
if (!defined('_GNUBOARD_')) exit;

$g5['api_keys_table'] = G5_TABLE_PREFIX.'api_keys';
$g5['api_ips_table'] = G5_TABLE_PREFIX.'api_ips';
$g5['api_logs_table'] = G5_TABLE_PREFIX.'api_logs';
```

```php
// ❌ 나쁜 예 (config.php 직접 수정)
<?php
// config.php 수정 (권장하지 않음)
$g5['api_keys_table'] = 'g5_api_keys';
```

---

## 요약

AI로 개발 시 **1주 (7일)**이면 가능합니다.

1. **1일차**: 기반 구축
2. **2일차**: SNS 로그인 API
3. **3일차**: PG 결제 API (1부)
4. **4일차**: PG 결제 API (2부)
5. **5일차**: 관리자 페이지
6. **6일차**: 문서화
7. **7일차**: 테스트 및 배포

각 프롬프트를 순서대로 실행하면 됩니다!
