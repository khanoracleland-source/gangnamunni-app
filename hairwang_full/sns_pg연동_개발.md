# SNS 로그인 & PG 연동 개발 분석 보고서

## 요구사항

1. 다른 사이트에서 SNS 로그인을 IP로 호출하는 기능 추가
2. 필드들도 추가될 수 있음
3. 기존 관리자에 관리하는 부분도 추가 개발
4. 쇼핑몰에 아이템을 등록하여 다른 사이트에서 피지 결제 가능하도록
5. 다른 그누보드에 이식이 가능하도록 각각 별도 폴더로 구축
6. 최소한으로 기존 소스는 유지

---

## 1. 그누보드5 리빌더 구조 분석

### 1.1 핵심 디렉토리 구조

```
├── config.php              # 기본 설정 파일 (상수, 경로 정의)
├── common.php              # 공통 파일 (세션, DB 연결, 보안)
├── bbs/                   # 게시판 및 회원 관련
├── shop/                  # 쇼핑몰 관련
├── adm/                   # 관리자 페이지
├── plugin/                # 플러그인 시스템
│   └── social/           # SNS 로그인 플러그인
├── api/                   # API 엔드포인트
├── extend/                # 확장 기능
├── lib/                   # 라이브러리
├── theme/                 # 테마 시스템
└── data/                  # 데이터 (업로드, 캐시 등)
```

### 1.2 데이터베이스 구조

#### 핵심 테이블
- `g5_member` - 회원 정보
- `g5_member_social_profiles` - SNS 로그인 연동 정보
- `g5_config` - 기본 설정
- `g5_auth` - 관리자 권한

#### 쇼핑몰 관련 테이블
- `g5_shop_item` - 상품 정보
- `g5_shop_order` - 주문 정보
- `g5_shop_cart` - 장바구니
- `g5_shop_category` - 카테고리
- `g5_shop_default` - 쇼핑몰 설정

### 1.3 설정 시스템

**config.php**: 경로 상수, DB 엔진, 캐릭터셋, 쿠키 도메인 등 핵심 설정
**shop.config.php**: 쇼핑몰 관련 상수와 설정

---

## 2. SNS 로그인 시스템 분석

### 2.1 현재 구조

**플러그인 경로**: `plugin/social/`

**핵심 파일**:
- `index.php` - SNS 로그인 엔드포인트
- `register_member.php` - SNS 회원가입
- `includes/functions.php` - SNS 로그인 함수 모음 (1065라인)
- `Hybrid/` - HybridAuth 라이브러리 (OAuth/OAuth2 지원)

**지원하는 SNS 서비스**:
- 네이버 (Naver)
- 카카오 (Kakao)
- 페이스북 (Facebook)
- 구글 (Google)
- 트위터 (Twitter)
- 페이코 (Payco)

### 2.2 SNS 로그인 흐름

```
1. 사용자가 SNS 로그인 버튼 클릭
   ↓
2. social/index.php 로 리다이렉트
   ↓
3. HybridAuth 라이브러리를 통해 SNS 서버 인증
   ↓
4. 사용자 프로필 정보 가져오기
   ↓
5. g5_member_social_profiles 테이블에서 연동 확인
   ↓
6. 기존 회원: 로그인 처리
   신규 회원: register_member.php 로 이동 (회원가입)
```

### 2.3 확장 시스템 활용

**extend/social_login.extend.php**:
```php
// SNS 로그인 테이블 정의
$g5['social_profile_table'] = G5_TABLE_PREFIX.'member_social_profiles';

// 상수 정의
define('G5_SOCIAL_LOGIN_DIR', 'social');
define('G5_SOCIAL_USE_POPUP', ! is_mobile());
define('G5_SOCIAL_DELETE_DAY', 0);
```

**장점**: 기존 소스를 수정하지 않고 extend 폴더에서 설정 가능

### 2.4 SNS 로그인 API 호출 가능성 분석

**현재 제한사항**:
- 웹 기반의 OAuth/OAuth2 흐름 (팝업/리다이렉트)
- 세션 기반 인증
- 브라우저 사용자 에이전트 체크

**IP 기반 API 호출 구현을 위해 필요한 수정**:
1. API 토큰 기반 인증 시스템 추가
2. IP 화이트리스트/인증 키 시스템 구현
3. 세션 없이 SNS 로그인 처리 가능한 별도 API 엔드포인트 생성

---

## 3. 쇼핑몰 시스템 분석

### 3.1 현재 구조

**경로**: `shop/`

**핵심 파일**:
- `shop.config.php` - 쇼핑몰 설정
- `_common.php` - 쇼핑몰 공통 파일
- `item.php` - 상품 상세
- `orderform.php` - 주문서
- `cart.php` - 장바구니
- `list.php` - 상품 목록

**결제 모듈**:
- `settle_inicis.inc.php` - 이니시스
- `settle_lg.inc.php` - LG U+
- `settle_kcp.inc.php` - KCP
- `settle_kakaopay.inc.php` - 카카오페이
- `settle_nicepay.inc.php` - 나이스페이

### 3.2 주문 처리 흐름

```
1. 장바구니에서 상품 선택
   ↓
2. orderform.php (주문서 작성)
   ↓
3. 결제 모듈 호출 (PG 사 연동)
   ↓
4. PG 사에서 결제 승인
   ↓
5. orderformupdate.php (주문 완료 처리)
   ↓
6. DB에 주문 정보 저장
   ↓
7. 결제 완료 페이지로 이동
```

### 3.3 외부 사이트 결제 연동 가능성 분석

**현재 제한사항**:
- 세션 기반 장바구니 관리
- 회원 로그인 필수 (대부분 기능)
- PG 사 콜백 URL이 현재 도메인으로 고정

**외부 사이트 결제 구현을 위해 필요한 수정**:
1. API 기반 주문 생성 시스템
2. 외부 사이트용 결제 엔드포인트
3. API 토큰 인증 시스템
4. 콜백 URL 외부 사이트로 전달 가능하도록 수정
5. IP 화이트리스트 기반 접근 제어

---

## 4. 관리자 페이지 구조 분석

### 4.1 관리자 메뉴 시스템

**파일 구조**:
- `adm/_common.php` - 관리자 공통 파일
- `adm/admin.menu100.php` - 기본 설정 메뉴
- `adm/admin.menu200.php` - 회원 관리 메뉴
- `adm/admin.menu300.php` - 게시판 관리 메뉴
- `adm/admin.menu400.shop_1of2.php` - 쇼핑몰 관리 메뉴 (1부)
- `adm/admin.menu500.shop_2of2.php` - 쇼핑몰 현황 메뉴 (2부)

### 4.2 메뉴 추가 방법

```php
// admin.menuXXX.php
$menu['menu100'][] = array(
    '100XXX',                    // 메뉴 코드
    '메뉴 이름',                // 메뉴 표시명
    G5_ADMIN_URL.'/파일명.php',  // 링크 URL
    'cf_config',                 // 권한 코드
    1                           // 숨김 여부 (1: 숨김, 0: 표시)
);
```

### 4.3 권한 관리

**g5_auth 테이블**:
- `mb_id` - 관리자 아이디
- `au_menu` - 메뉴 코드
- `au_auth` - 권한 (r: 읽기, w: 쓰기, d: 삭제)

---

## 5. API 시스템 분석

### 5.1 현재 API 구조

**경로**: `api/`

**기존 API**:
- `push_token_save.php` - FCM 푸시 토큰 저장
- `push_token_delete.php` - FCM 푸시 토큰 삭제

**특징**:
- JSON 기반 요청/응답
- CORS 설정 (Access-Control-Allow-Origin: *)
- POST 메서드만 허용
- 자동 테이블 생성 기능

### 5.2 API 인증

**현재 상태**: 인증 없이 모든 요청 허용

**개선 필요 사항**:
- API 토큰 기반 인증
- IP 화이트리스트
- 요청 제한 (Rate Limiting)
- 로깅 시스템

---

## 6. 개발 가능성 분석

### 6.1 SNS 로그인 IP 호출 기능

**개발 가능 여부**: ✅ 가능

**필요한 구현**:

1. **API 토큰 인증 시스템**
   - 외부 사이트용 API 키 발급 시스템
   - 토큰 발급/갱신/만료 관리
   - 요청 시 헤더에서 토큰 검증

2. **IP 화이트리스트 시스템**
   - 외부 사이트 IP 등록 관리
   - 요청 시 IP 검증
   - 관리자 페이지에서 IP CRUD

3. **SNS 로그인 API 엔드포인트**
   - 새로운 API 파일: `api/sns_login.php`
   - JSON 기반 요청/응답
   - 세션 대신 토큰 기반 인증
   - 회원가입/로그인 결과를 JSON으로 반환

4. **추가 필드 시스템**
   - 유연한 필드 추가 시스템 (JSON 형태로 저장)
   - 필드 정의 테이블: `g5_api_fields`
   - 필드 값 테이블: `g5_api_field_values`

**권장 구조**:
```
api/
├── sns/
│   ├── login.php          # SNS 로그인 API
│   ├── token.php          # 토큰 발급/갱신
│   └── callback.php      # SNS 콜백 처리
└── pg/
    ├── order.php          # 주문 생성 API
    ├── payment.php        # 결제 API
    └── status.php        # 주문 상태 조회 API
```

**예상 개발 시간**: 5-7일

### 6.2 관리자 페이지 추가 개발

**개발 가능 여부**: ✅ 가능

**필요한 구현**:

1. **API 관리 메뉴**
   - API 키 발급/관리
   - IP 화이트리스트 관리
   - 로그 조회

2. **SNS 로그인 API 관리**
   - API 연동 현황
   - 요청 로그
   - 오류 로그

3. **PG 결제 API 관리**
   - 주문 요청 현황
   - 결제 로그
   - 통계

**권장 메뉴 구조**:
```php
// admin.menu600.php (새로운 메뉴 파일)
$menu['menu600'] = array(
    array('600100', 'API 관리', G5_ADMIN_URL.'/api_admin/', 'api_config'),
    array('600110', 'API 키 관리', G5_ADMIN_URL.'/api_admin/keylist.php', 'api_key'),
    array('600120', 'IP 화이트리스트', G5_ADMIN_URL.'/api_admin/iplist.php', 'api_ip'),
    array('600200', 'SNS 로그인 API', G5_ADMIN_URL.'/api_admin/sns/', 'api_sns'),
    array('600210', '로그인 로그', G5_ADMIN_URL.'/api_admin/sns/loginlog.php', 'api_sns_log'),
    array('600300', 'PG 결제 API', G5_ADMIN_URL.'/api_admin/pg/', 'api_pg'),
    array('600310', '주문 요청', G5_ADMIN_URL.'/api_admin/pg/orderlist.php', 'api_pg_order'),
    array('600320', '결제 로그', G5_ADMIN_URL.'/api_admin/pg/paymentlog.php', 'api_pg_log'),
);
```

**예상 개발 시간**: 3-5일

### 6.3 쇼핑몰 PG 결제 API

**개발 가능 여부**: ✅ 가능

**필요한 구현**:

1. **API 기반 상품 조회**
   - 상품 목록 조회 API
   - 상품 상세 조회 API
   - 재고 확인 API

2. **API 기반 주문 생성**
   - 주문 생성 API
   - 장바구니 API
   - 주문 상태 변경 API

3. **외부 사이트 결제**
   - 결제 요청 API
   - PG 사와 연동 (기존 모듈 활용)
   - 콜백 URL을 외부 사이트로 전달
   - 결제 완료 웹훅

4. **주문 동기화**
   - 결제 완료 시 원본 사이트로 알림
   - 주문 상태 동기화
   - 배송 정보 동기화

**기존 결제 모듈 활용 방안**:
- `settle_inicis.inc.php`, `settle_lg.inc.php` 등을 확장하여 API 모드 추가
- API 요청 시 PG 사와 직접 통신
- 콜백 URL을 동적으로 설정하여 외부 사이트로 전달

**권장 구조**:
```
api/pg/
├── item.php            # 상품 조회 API
├── cart.php            # 장바구니 API
├── order.php           # 주문 생성 API
├── payment.php         # 결제 요청 API
├── settle.inc.php      # 결제 모듈 (기존 확장)
└── webhook.php         # 웹훅 처리
```

**예상 개발 시간**: 7-10일

---

## 7. 이식 가능성 분석

### 7.1 이식 가능 여부: ✅ 매우 높음

**이유**:

1. **그누보드5의 모듈화 구조**
   - 플러그인 시스템: 기존 소스 수정 없이 기능 추가
   - 확장 시스템 (extend): hook 방식으로 기능 확장
   - 테마 시스템: 디자인 분리

2. **독립적인 폴더 구조 가능**
   - `api/sns/` - SNS 로그인 API (독립)
   - `api/pg/` - PG 결제 API (독립)
   - `adm/api_admin/` - 관리자 페이지 (독립)
   - `extend/sns_api.extend.php` - 확장 설정 (독립)

3. **데이터베이스 테이블 독립성**
   - 별도 테이블 생성: `g5_api_keys`, `g5_api_logs` 등
   - 기존 테이블 필드 추가 최소화
   - SQL 파일로 설치/삭제 스크립트 제공

### 7.2 이식 가이드라인

**설치 절차**:
1. API 폴더 전체를 복사
2. 관리자 폴더 전체를 복사
3. 확장 파일을 extend 폴더에 복사
4. SQL 파일 실행 (테이블 생성)
5. 관리자 메뉴 파일에 메뉴 추가 (또는 별도 파일로 제공)
6. 권한 설정

**삭제 절차**:
1. SQL 파일 실행 (테이블 삭제)
2. API 폴더 삭제
3. 관리자 폴더 삭제
4. 확장 파일 삭제
5. 메뉴 파일에서 메뉴 제거

---

## 8. 기존 소스 최소 수정 전략

### 8.1 확장 시스템 활용

**extend 폴더**:
- `extend/sns_api.extend.php` - SNS API 설정
- `extend/pg_api.extend.php` - PG API 설정
- `extend/api_auth.extend.php` - API 인증 설정

**장점**: 기존 소스 수정 없이 상수, 함수, 설정 추가 가능

### 8.2 플러그인 시스템 활용

**plugin 폴더**:
- `plugin/sns_api/` - SNS API 플러그인 (plugin/social 확장)
- `plugin/pg_api/` - PG API 플러그인

**장점**: 독립적인 패키지로 관리 가능

### 8.3 Hook 시스템 활용

**lib/hook.lib.php**:
- 회원가입 후 이벤트: `register_form_update_after`
- 로그인 후 이벤트: `login_after`
- 주문 완료 후 이벤트: `order_complete_after`

**장점**: 기존 코드 수정 없이 이벤트 후킹 가능

### 8.4 최소 수정 포인트

**수정 필요 파일**:
1. `config.php` - API 관련 상수 추가 (또는 extend에서 처리)
2. `dbconfig.php` - 테이블 정의 추가 (자동 생성 처리 가능)
3. `adm/admin.menu600.php` - 메뉴 추가 (새 파일)

**수정 없이 가능**:
- API 엔드포인트 생성
- 관리자 페이지 생성
- 데이터베이스 테이블 생성/삭제
- 로깅 시스템 구현

---

## 9. 권장 구조

### 9.1 최종 디렉토리 구조

```
그누보드/
├── api/                          # API 루트 (기존)
│   ├── sns/                      # SNS 로그인 API (새로운)
│   │   ├── login.php
│   │   ├── callback.php
│   │   └── token.php
│   └── pg/                       # PG 결제 API (새로운)
│       ├── item.php
│       ├── order.php
│       ├── payment.php
│       └── webhook.php
├── adm/
│   ├── api_admin/                # API 관리자 (새로운)
│   │   ├── _common.php
│   │   ├── keylist.php
│   │   ├── iplist.php
│   │   ├── sns/
│   │   └── pg/
│   └── admin.menu600.php         # API 관리 메뉴 (새로운)
├── extend/                       # 확장 (기존)
│   ├── sns_api.extend.php        # SNS API 확장 (새로운)
│   └── pg_api.extend.php         # PG API 확장 (새로운)
├── install/
│   └── sns_pg_api.sql            # 설치 SQL (새로운)
└── data/
    └── api/                      # API 데이터 (새로운)
        ├── logs/
        └── cache/
```

### 9.2 데이터베이스 구조

**필요한 테이블**:

```sql
-- API 키 테이블
CREATE TABLE `g5_api_keys` (
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
CREATE TABLE `g5_api_ips` (
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
CREATE TABLE `g5_api_logs` (
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
CREATE TABLE `g5_sns_api_logs` (
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
CREATE TABLE `g5_pg_api_orders` (
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
CREATE TABLE `g5_api_fields` (
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
CREATE TABLE `g5_api_field_values` (
  `fv_id` int(11) NOT NULL AUTO_INCREMENT,
  `af_id` int(11) NOT NULL,
  `al_id` int(11),              -- API 로그 ID
  `sl_id` int(11),              -- SNS 로그 ID
  `po_id` bigint(20),           -- PG 주문 ID
  `fv_value` text,
  `fv_created_at` datetime NOT NULL,
  PRIMARY KEY (`fv_id`),
  KEY `af_id` (`af_id`),
  KEY `al_id` (`al_id`),
  KEY `sl_id` (`sl_id`),
  KEY `po_id` (`po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 10. 개발 순서 및 일정

### 10.1 1단계: 기반 구축 (3일)

1. **데이터베이스 설치 스크립트**
   - 테이블 생성 SQL 작성
   - 샘플 데이터 삽입

2. **API 인증 시스템**
   - API 키 발급/갱신/만료
   - IP 화이트리스트
   - 미들웨어 (인증, 로깅)

3. **기본 API 프레임워크**
   - 요청/응답 표준화
   - 에러 처리
   - 로깅 시스템

### 10.2 2단계: SNS 로그인 API (5일)

1. **SNS 로그인 API**
   - 외부 사이트용 로그인 엔드포인트
   - 회원가입/로그인 처리
   - JSON 기반 응답

2. **콜백 처리**
   - SNS 서버 콜백 처리
   - 세션 없이 상태 유지
   - 결과 반환

3. **추가 필드 시스템**
   - 동적 필드 저장
   - 필드 정의 관리
   - 필드 값 조회

### 10.3 3단계: PG 결제 API (7일)

1. **상품 조회 API**
   - 상품 목록/상세
   - 재고 확인
   - 가격 조회

2. **주문 생성 API**
   - 장바구니 API
   - 주문 생성
   - 주문 상태 관리

3. **결제 시스템**
   - PG 사 연동 (기존 모듈 확장)
   - 결제 요청/콜백
   - 외부 사이트 콜백 URL 처리

4. **웹훅 시스템**
   - 결제 완료 알림
   - 주문 상태 동기화
   - 보안 처리

### 10.4 4단계: 관리자 페이지 (5일)

1. **API 관리**
   - API 키 발급/관리
   - IP 화이트리스트 관리
   - 로그 조회/분석

2. **SNS 로그인 API 관리**
   - 로그인 로그
   - 오류 로그
   - 통계

3. **PG 결제 API 관리**
   - 주문 요청 현황
   - 결제 로그
   - 통계

4. **필드 관리**
   - 필드 정의 CRUD
   - 필드 값 조회

### 10.5 5단계: 테스트 및 배포 (3일)

1. **단위 테스트**
   - 각 API 엔드포인트 테스트
   - 인증 시스템 테스트
   - 데이터베이스 테스트

2. **통합 테스트**
   - 전체 흐름 테스트
   - 다른 그누보드 이식 테스트
   - 성능 테스트

3. **문서화**
   - 설치 가이드
   - API 문서
   - 이식 가이드

**총 예상 기간**: 23일 (약 1개월)

---

## 11. 기술 스택 및 라이브러리

### 11.1 사용할 기술

- **백엔드**: PHP 7.4+
- **데이터베이스**: MySQL 5.7+ / MariaDB 10.3+
- **인증**: JWT (JSON Web Token) 또는 HMAC SHA256
- **통신**: RESTful API (JSON)
- **로그**: Monolog (PHP 로깅 라이브러리)

### 11.2 기존 라이브러리 활용

- **SNS 로그인**: HybridAuth (이미 사용 중)
- **결제 모듈**: 이니시스, LG U+, KCP, 카카오페이, 나이스페이 (이미 사용 중)
- **암호화**: `create_hash()`, `get_string_encrypt()` (그누보드 기본 함수)

---

## 12. 보안 고려사항

### 12.1 인증 및 권한

1. **API 키 기반 인증**
   - 각 요청에 API 키 검증
   - IP 화이트리스트와 결합하여 이중 보안
   - 토큰 만료 및 갱신

2. **HTTPS 사용**
   - 모든 API 요청은 HTTPS만 허용
   - SSL 인증서 필수

3. **요청 제한 (Rate Limiting)**
   - 분당 요청 횟수 제한
   - IP 기반 제한
   - API 키 기반 제한

### 12.2 데이터 보호

1. **민감 정보 암호화**
   - API Secret 암호화 저장
   - 개인정보 마스킹 처리
   - 로그에서 민감 정보 제거

2. **SQL Injection 방지**
   - Prepared Statement 사용
   - 입력값 검증 및 필터링

3. **XSS 방지**
   - 출력 이스케이핑
   - CSP (Content Security Policy) 설정

### 12.3 로깅 및 감사

1. **모든 API 요청 로깅**
   - 요청 시간, IP, 데이터
   - 응답 코드, 데이터
   - 실행 시간

2. **오류 로깅**
   - 에러 코드, 메시지
   - 스택 트레이스 (개발 환경)
   - 알림 시스템

---

## 13. 결론

### 13.1 개발 가능성

**SNS 로그인 IP 호출 기능**: ✅ 가능
- API 토큰 기반 인증 시스템 추가
- IP 화이트리스트 시스템 구현
- 기존 SNS 로그인 플러그인 확장
- 예상 개발 기간: 5-7일

**PG 결제 API**: ✅ 가능
- API 기반 상품/주문 시스템 구현
- 기존 결제 모듈 확장하여 외부 사이트 결제 지원
- 웹훅 시스템으로 동기화
- 예상 개발 기간: 7-10일

**관리자 페이지 추가**: ✅ 가능
- API 관리, SNS 로그인, PG 결제 메뉴 추가
- 로그 조회 및 통계 기능
- 예상 개발 기간: 3-5일

**다른 그누보드 이식**: ✅ 매우 쉬움
- 독립적인 폴더 구조
- 확장 시스템 활용하여 기존 소스 수정 최소화
- SQL 파일로 테이블 생성/삭제 자동화
- 설치/삭제 가이드 제공

### 13.2 권장 접근 방식

1. **단계별 개발**: 기반 → SNS 로그인 → PG 결제 → 관리자 → 테스트
2. **기존 소스 최소 수정**: extend 시스템, 플러그인 시스템 활용
3. **독립적인 패키지**: 각 기능을 별도 폴더로 구성하여 이식 용이
4. **보안 최우선**: 인증, 권한, 로깅 시스템 철저히 구현
5. **문서화**: 설치 가이드, API 문서, 이식 가이드 제공

### 13.3 최종 권장 구조

```
api/
├── sns/                  # SNS 로그인 API (독립)
├── pg/                   # PG 결제 API (독립)
└── common.php            # API 공통 (인증, 로깅)

adm/
└── api_admin/            # API 관리자 (독립)

extend/
├── sns_api.extend.php    # SNS API 설정
└── pg_api.extend.php     # PG API 설정

install/
└── sns_pg_api.sql        # 설치 SQL
```

### 13.4 총 예상 개발 기간

- **1단계 (기반 구축)**: 3일
- **2단계 (SNS 로그인 API)**: 5일
- **3단계 (PG 결제 API)**: 7일
- **4단계 (관리자 페이지)**: 5일
- **5단계 (테스트 및 배포)**: 3일

**총**: **23일 (약 1개월)**

---

## 14. 추가 고려사항

### 14.1 성능 최적화

- API 캐싱 (Redis 사용 권장)
- 데이터베이스 인덱스 최적화
- 로그 주기적 백업/삭제

### 14.2 확장성

- 미래에 추가될 SNS 서비스 대응
- 새로운 PG 사 대응
- 추가 필드 유연한 처리

### 14.3 유지보수

- 모니터링 시스템
- 알림 시스템
- 자동 테스트 시스템

---

**작성일**: 2026-01-19
**분석자**: AI Assistant
**버전**: 1.0
