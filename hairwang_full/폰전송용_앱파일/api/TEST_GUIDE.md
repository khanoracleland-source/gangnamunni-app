# SNS PG 연동 API 테스트 가이드

## 1. 데이터베이스 설치 테스트

### 1.1 SQL 파일 실행
```bash
mysql -u username -p database_name < install/sns_pg_api.sql
```

### 1.2 테이블 생성 확인
```sql
SHOW TABLES LIKE 'g5_api%';
```

예상 결과:
- g5_api_keys
- g5_api_ips
- API 로그
- g5_api_logs
- g5_sns_api_logs
- g5_pg_api_orders
- g5_api_fields
- g5_api_field_values

## 2. API 공통 기능 테스트

### 2.1 API 테이블 자동 생성 테스트
```bash
curl -X GET http://your-domain.com/api/common.php
```

예상 결과: 테이블이 자동으로 생성되어야 합니다.

## 3. API 인증 시스템 테스트

### 3.1 API 키 생성 (관리자 페이지에서 수행)
1. 관리자 페이지 접속: http://your-domain.com/adm/api_admin/keylist.php
2. API 키 생성 버튼 클릭
3. API 키 이름, 타입, 만료일 입력
4. API 키 생성

### 3.2 API 키 검증 테스트
```bash
curl -X POST http://your-domain.com/api/sns/login.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d "provider=naver&token=TEST_TOKEN"
```

예상 결과:
```json
{
  "success": false,
  "error": {
    "message": "Invalid token",
    "code": "PROFILE_FETCH_FAILED"
  }
}
```

## 4. SNS 로그인 API 테스트

### 4.1 네이버 로그인 테스트
```bash
curl -X POST http://your-domain.com/api/sns/login.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "provider=naver&token=NAVER_ACCESS_TOKEN&auto_register=true"
```

### 4.2 카카오 로그인 테스트
```bash
curl -X POST http://your-domain.com/api/sns/login.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "provider=kakao&token=KAKAO_ACCESS_TOKEN&auto_register=true"
```

### 4.3 토큰 발급 테스트
```bash
curl -X POST http://your-domain.com/api/sns/token.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "action=issue&mb_id=naver_123456"
```

예상 결과:
```json
{
  "success": true,
  "data": {
    "token": "eyJhbGc...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "mb_id": "naver_123456"
  }
}
```

## 5. PG 결제 API 테스트

### 5.1 상품 목록 조회 테스트
```bash
curl -X GET "http://your-domain.com/api/pg/item.php?action=list&page=1&limit=20" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"
```

예상 결과:
```json
{
  "success": true,
  "data": {
    "action": "list",
    "items": [...],
    "pagination": {...}
  }
}
```

### 5.2 상품 상세 조회 테스트
```bash
curl -X GET "http://your-domain.com/api/pg/item.php?action=detail&item_id=1" \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"
```

### 5.3 장바구니 추가 테스트
```bash
curl -X POST http://your-domain.com/api/pg/cart.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "item_id=1&quantity=1&cart_id=test_cart_123"
```

### 5.4 주문 생성 테스트
```bash
curl -X POST http://your-domain.com/api/pg/order.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "external_order_id=test_order_123&cart_id=test_cart_123&receiver_name=홍길동&receiver_phone=01012345678&receiver_zipcode=12345&receiver_address1=서울시&payment_method=kakaopay&callback_url=http://your-domain.com/api/pg/webhook.php"
```

예상 결과:
```json
{
  "success": true,
  "data": {
    "order_id": "20260119-1234567890-1234",
    "external_order_id": "test_order_123",
    "status": "pending"
  }
}
```

## 6. IP 화이트리스트 테스트

### 6.1 허용된 IP에서 테스트
```bash
curl -X POST http://your-domain.com/api/sns/login.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d "provider=naver&token=TEST_TOKEN"
```

### 6.2 허용되지 않은 IP에서 테스트
```bash
curl -X POST http://your-domain.com/api/sns/login.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d "provider=naver&token=TEST_TOKEN"
```

예상 결과:
```json
{
  "success": false,
  "error": {
    "message": "Your IP address is not authorized",
    "code": "IP_NOT_ALLOWED"
  }
}
```

## 7. 에러 처리 테스트

### 7.1 API 키 없음 테스트
```bash
curl -X POST http://your-domain.com/api/sns/login.php \
  -d "provider=naver&token=TEST_TOKEN"
```

예상 결과:
```json
{
  "success": false,
  "error": {
    "message": "API authentication required. Provide X-API-Key and X-API-Secret headers.",
    "code": "AUTH_REQUIRED"
  }
}
```

### 7.2 잘못된 API 키 테스트
```bash
curl -X POST http://your-domain.com/api/sns/login.php \
  -H "X-API-Key: INVALID_KEY" \
  -H "X-API-Secret: INVALID_SECRET" \
  -d "provider=naver&token=TEST_TOKEN"
```

예상 결과:
```json
{
  "success": false,
  "error": {
    "message": "Invalid API key or secret",
    "code": "INVALID_API_KEY"
  }
}
```

### 7.3 잘못된 요청 파라미터 테스트
```bash
curl -X POST http://your-domain.com/api/sns/login.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d "provider=invalid_provider&token=TEST_TOKEN"
```

예상 결과:
```json
{
  "success": false,
  "error": {
    "message": "Validation Error",
    "code": "VALIDATION_ERROR",
    "details": {
      "provider": "Invalid provider. Supported: naver, kakao, google, facebook, twitter, payco"
    }
  }
}
```

## 8. 관리자 페이지 테스트

### 8.1 API 키 관리 테스트
1. 관리자 페이지 접속: http://your-domain.com/adm/api_admin/keylist.php
2. API 키 생성 테스트
3. API 키 수정 테스트
4. API 키 삭제 테스트
5. 시크릿 재발급 테스트

### 8.2 IP 화이트리스트 관리 테스트
1. 관리자 페이지 접속: http://your-domain.com/adm/api_admin/iplist.php?ak_id=1
2. IP 추가 테스트
3. IP 삭제 테스트

### 8.3 로그 조회 테스트
1. API 로그 테스트: http://your-domain.com/adm/api_admin/loglist.php
2. SNS 로그인 로그 테스트: http://your-domain.com/adm/api_admin/sns/loginlog.php
3. SNS 오류 로그 테스트: http://your-domain.com/adm/api_admin/sns/errorlog.php
4. SNS 통계 테스트: http://your-domain.com/adm/api_admin/sns/statistics.php
5. PG 주문 로그 테스트: http://your-domain.com/adm/api_admin/pg/orderlist.php
6. PG 결제 로그 테스트: http://your-domain.com/adm/api_admin/pg/paymentlog.php
7. PG 통계 테스트: http://your-domain.com/adm/api_admin/pg/statistics.php

### 8.4 필드 관리 테스트
1. 필드 정의 생성 테스트: http://your-domain.com/adm/api_admin/fieldlist.php
2. 필드 정의 수정 테스트
3. 필드 정의 삭제 테스트
4. 필드 값 조회 테스트: http://your-domain.com/adm/api_admin/fieldvaluelist.php

## 9. 통합 테스트

### 9.1 SNS 로그인 전체 흐름 테스트
1. 외부 사이트에서 SNS 로그인 API 호출
2. SNS 토큰으로 사용자 프로필 가져오기
3. 기존 회원: 로그인 처리
4. 신규 회원: 자동 가입 처리
5. JWT 토큰 발급
6. 로그 기록 확인

### 9.2 PG 결제 전체 흐름 테스트
1. 상품 목록 조회
2. 상품 상세 조회
3. 장바구니에 상품 추가
4. 주문 생성
5. 결제 요청
6. 웹훅 알림
7. 주문 상태 확인

## 10. 보안 테스트

### 10.1 SQL 인젝션 테스트
```bash
curl -X POST http://your-domain.com/api/sns/login.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d "provider=naver' OR '1'='1&token=TEST_TOKEN"
```

예상 결과: SQL 인젝션 차되

### 10.2 XSS 테스트
```bash
curl -X POST http://your-domain.com/api/sns/login.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET" \
  -d "provider=naver&token=<script>alert('XSS')</script>&custom_fields[field1]=<script>alert('XSS')</script>"
```

예상 결과: XSS 차되

## 11. 성능 테스트

### 11.1 부하 테스트 (Apache Bench)
```bash
ab -n 1000 -c 10 -H "X-API-Key: YOUR_API_KEY" -H "X-API-Secret: YOUR_API_SECRET" http://your-domain.com/api/pg/item.php?action=list
```

### 11.2 응답 시간 측정
```bash
time curl -X GET http://your-domain.com/api/pg/item.php?action=list \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "X-API-Secret: YOUR_API_SECRET"
```

## 12. 테스트 결과 체크리스트

### 데이터베이스
- [ ] 모든 테이블 생성됨
- [ ] 인덱스 정확하게 생성됨
- [ ] 테이블 상태 정상

### API 인증
- [ ] API 키 생성/검증 정상 작동
- [ ] IP 화이트리스트 정상 작동
- [ ] API 시크릿 재발급 정상 작동

### SNS 로그인 API
- [ ] 네이버 로그인 작동
- [ ] 카카오 로그인 작동
- [ ] 구글 로그인 작동
- [ ] 토큰 발급 작동
- [ ] 콜백 처리 작동

### PG 결제 API
- [ ] 상품 조회 작동
- [ ] 장바구니 작동
- [ ] 주문 생성 작동
- [ ] 결제 요청 작동
- [ ] 웹훅 작동

### 관리자 페이지
- [ ] API 키 관리 작동
- [ ] IP 화이트리스트 관리 작동
- [ ] 로그 조회 작동
- [ ] 통계 작동
- [ ] 필드 관리 작동

### 보안
- [ ] SQL 인젝션 방지
- [ ] XSS 방지
- [ ] CSRF 방지
- [ ] 인증 체크 정상

### 성능
- [ ] 응답 시간 적절 (< 2초)
- [ ] 부하 테스트 통과
- [ ] 데이터베이스 쿼리 최적화

---

**작성일**: 2026-01-19
**버전**: 1.0
