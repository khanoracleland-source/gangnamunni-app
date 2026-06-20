# SNS PG 연동 API

외부 사이트에서 SNS 로그인 및 PG 결제 기능을 사용할 수 있는 API 시스템입니다.

## 기능

### 1. SNS 로그인 API
- 다양한 SNS 제공자 지원 (네이버, 카카오, 구글, 페이스북, 트위터, 페이코)
- API 키 기반 인증
- IP 화이트리스트 보안
- 자동 회원 가입
- JWT 토큰 기반 인증
- 커스텀 필드 지원

### 2. PG 결제 API
- 상품 조회 (목록, 상세, 재고 확인)
- 장바구니 관리 (추가, 수정, 삭제)
- 주문 생성
- 결제 처리 (이니시스, LG U+, KCP, 카카오페이, 나이스페이)
- 웹훅 기능

### 3. 관리자 페이지
- API 키 발급/관리
- IP 화이트리스트 관리
- API 로그 조회
- SNS 로그인 로그 조회
- PG 주문 관리

## 설치

### 1. 데이터베이스 설치

```bash
mysql -u username -p database_name < install/sns_pg_api.sql
```

### 2. 파일 배치

이 프로젝트의 파일을 그누보드 루트 디렉토리에 배치합니다.

### 3. 확장 파일

extend 폴더의 파일들이 자동으로 로드됩니다.

### 4. 관리자 메뉴

adm/admin.menu600.php 파일이 자동으로 로드됩니다.

## 사용법

### API 키 발급

1. 관리자 페이지 접속
2. API 관리 > API 키 관리 메뉴 이동
3. API 키 생성

### API 사용

#### 요청 형식

```
POST /api/sns/login.php
Content-Type: application/x-www-form-urlencoded
X-API-Key: YOUR_API_KEY
X-API-Secret: YOUR_API_SECRET

provider=naver&token=ACCESS_TOKEN&auto_register=true
```

#### 응답 형식

성공:
```json
{
  "success": true,
  "data": {
    "mb_id": "naver_123456",
    "mb_name": "홍길동",
    "mb_nick": "홍길동",
    "provider": "naver"
  }
}
```

실패:
```json
{
  "success": false,
  "error": {
    "message": "Invalid API key or secret",
    "code": "INVALID_API_KEY"
  }
}
```

### API 엔드포인트

#### SNS 로그인 API
- `POST /api/sns/login.php` - SNS 로그인
- `POST /api/sns/token.php` - 토큰 발급/갱신/검증
- `POST /api/sns/callback.php` - 콜백 처리

#### PG 결제 API
- `GET /api/pg/item.php` - 상품 조회
- `GET /api/pg/cart.php` - 장바구니 조회
- `POST /api/pg/cart.php` - 장바구니 추가
- `PUT /api/pg/cart.php` - 장바구니 수정
- `DELETE /api/pg/cart.php` - 장바구니 삭제
- `POST /api/pg/order.php` - 주문 생성
- `POST /api/pg/payment.php` - 결제 요청
- `POST /api/pg/webhook.php` - 웹훅 처리

## 보안

### API 키 인증
모든 API 요청은 API 키와 시크릿을 헤더에 포함해야 합니다.

```
X-API-Key: YOUR_API_KEY
X-API-Secret: YOUR_API_SECRET
```

### IP 화이트리스트
API 키별로 IP 화이트리스트를 설정하여 특정 IP에서만 접근을 허용할 수 있습니다.

### HTTPS 사용
모든 API 요청은 HTTPS를 통해 전송되어야 합니다.

## 관리자 페이지

### API 키 관리
- API 키 발급
- API 키 수정 (이름, 상태, 만료일)
- API 키 삭제
- 시크릿 재발급

### IP 화이트리스트 관리
- IP 추가
- IP 삭제
- CIDR 지원 (예: 192.168.1.0/24)

### 로그 조회
- API 요청 로그
- SNS 로그인 로그
- PG 주문 로그

## 확장

### 새로운 SNS 제공자 추가

api/sns/login.php에 새로운 제공자 처리 함수를 추가합니다.

### 새로운 PG 사 추가

api/pg/payment.php에 새로운 PG 사 처리 함수를 추가합니다.

### 커스텀 필드 추가

관리자 페이지에서 필드 정의를 추가하여 API 요청에 커스텀 필드를 포함할 수 있습니다.

## 이식

다른 그누보드 설치에 이식하려면:

1. API 폴더 전체 복사
2. 관리자 폴더 전체 복사
3. 확장 파일을 extend 폴더에 복사
4. SQL 파일 실행
5. 관리자 메뉴 파일에 메뉴 추가

## 라이선스

이 프로젝트는 그누보드와 동일한 라이선스를 따릅니다.

## 지원

문제가 있거나 도움이 필요한 경우, 개발 내역 파일(sns_pg연동_개발_내역.md)을 참조하십시오.

---

**작성일**: 2026-01-19
**버전**: 1.0
