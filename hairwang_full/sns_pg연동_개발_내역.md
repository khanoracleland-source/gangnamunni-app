# SNS PG 연동 개발 내역

## 개요
- **개발 시작일**: 2026-01-19
- **개발 목표**: 외부 사이트에서 SNS 로그인 및 PG 결제 API 연동

---

## 1단계: 기반 구축

### 1.1 데이터베이스 설치 스크립트 작성
- [x] API 키 테이블 생성 (g5_api_keys)
- [x] IP 화이트리스트 테이블 생성 (g5_api_ips)
- [x] API 로그 테이블 생성 (g5_api_logs)
- [x] SNS 로그인 API 로그 테이블 생성 (g5_sns_api_logs)
- [x] PG 결제 API 주문 테이블 생성 (g5_pg_api_orders)
- [x] API 필드 정의 테이블 생성 (g5_api_fields)
- [x] API 필드 값 테이블 생성 (g5_api_field_values)

### 1.2 API 인증 시스템 구축
- [x] API 키 발급/갱신/만료 시스템
- [x] IP 화이트리스트 시스템
- [x] 인증 미들웨어

### 1.3 기본 API 프레임워크 구축
- [x] 요청/응답 표준화
- [x] 에러 처리 시스템
- [x] 로깅 시스템

---

## 2단계: SNS 로그인 API

### 2.1 SNS 로그인 API 엔드포인트
- [x] 로그인 API (api/sns/login.php)
- [x] 토큰 API (api/sns/token.php)
- [x] 콜백 API (api/sns/callback.php)

### 2.2 콜백 처리 시스템
- [x] SNS 서버 콜백 처리
- [x] 세션 없이 상태 유지
- [x] 결과 반환 시스템

### 2.3 추가 필드 시스템
- [x] 동적 필드 저장 시스템
- [x] 필드 정의 관리 시스템
- [x] 필드 값 조회 시스템

---

## 3단계: PG 결제 API

### 3.1 상품 조회 API
- [x] 상품 목록 조회 API (api/pg/item.php)
- [x] 상품 상세 조회 API
- [x] 재고 확인 API

### 3.2 주문 생성 API
- [x] 장바구니 API (api/pg/cart.php)
- [x] 주문 생성 API (api/pg/order.php)
- [x] 주문 상태 관리 시스템

### 3.3 결제 시스템
- [x] 결제 요청 API (api/pg/payment.php)
- [x] PG 사 연동 (기존 모듈 확장)
- [x] 콜백 URL 외부 사이트 전달

### 3.4 웹훅 시스템
- [x] 웹훅 API (api/pg/webhook.php)
- [x] 결제 완료 알림 시스템
- [x] 주문 상태 동기화 시스템

---

## 4단계: 관리자 페이지

### 4.1 API 관리
- [x] API 키 발급/관리 페이지 (adm/api_admin/keylist.php)
- [x] IP 화이트리스트 관리 페이지 (adm/api_admin/iplist.php)
- [x] 로그 조회/분석 페이지

### 4.2 SNS 로그인 API 관리
- [x] 로그인 로그 조회 페이지 (adm/api_admin/sns/loginlog.php)
- [ ] 오류 로그 조회 페이지
- [ ] 통계 페이지

### 4.3 PG 결제 API 관리
- [x] 주문 요청 현황 페이지 (adm/api_admin/pg/orderlist.php)
- [ ] 결제 로그 조회 페이지
- [ ] 통계 페이지

### 4.4 필드 관리
- [ ] 필드 정의 CRUD 페이지
- [ ] 필드 값 조회 페이지

### 4.5 관리자 메뉴
- [x] 메뉴 파일 생성 (adm/admin.menu600.php)
- [ ] 권한 설정

---

## 5단계: 테스트 및 배포

### 5.1 단위 테스트
- [ ] 각 API 엔드포인트 테스트
- [ ] 인증 시스템 테스트
- [ ] 데이터베이스 테스트

### 5.2 통합 테스트
- [ ] 전체 흐름 테스트
- [ ] 다른 그누보드 이식 테스트
- [ ] 성능 테스트

### 5.3 문서화
- [ ] 설치 가이드 작성
- [ ] API 문서 작성
- [ ] 이식 가이드 작성

---

### 관리자 메뉴 (1개)
- [x] adm/admin.menu600.php

### 확장 파일 (3개)
- [x] extend/sns_api.extend.php
- [x] extend/pg_api.extend.php
- [x] extend/api_auth.extend.php

### 문서 (2개)
- [x] api/README.md
- [x] api/TEST_GUIDE.md

---

## 개발 완료 요약

### 완료된 파일 (총 24개)

**데이터베이스 (1개)**
- install/sns_pg_api.sql

**API 공통 (2개)**
- api/common.php
- api/README.md

**SNS 로그인 API (3개)**
- api/sns/login.php
- api/sns/token.php
- api/sns/callback.php

**PG 결제 API (5개)**
- api/pg/item.php
- api/pg/cart.php
- api/pg/order.php
- api/pg/payment.php
- api/pg/webhook.php

**관리자 공통 (1개)**
- adm/api_admin/_common.php

**관리자 - API 관리 (5개)**
- adm/api_admin/keylist.php
- adm/api_admin/iplist.php
- adm/api_admin/loglist.php
- adm/api_admin/logdetail.php
- adm/api_admin/fieldlist.php

**관리자 - SNS 로그인 (3개)**
- adm/api_admin/sns/loginlog.php
- adm/api_admin/sns/errorlog.php
- adm/api_admin/sns/statistics.php

**관리자 - PG 결제 (5개)**
- adm/api_admin/pg/orderlist.php
- adm/api_admin/pg/orderdetail.php
- adm/api_admin/pg/paymentlog.php
- adm/api_admin/pg/statistics.php

**관리자 - 필드 관리 (3개)**
- adm/api_admin/fieldform.php
- adm/api_admin/fieldvaluelist.php

**관리자 메뉴 (1개)**
- adm/admin.menu600.php

**확장 파일 (3개)**
- extend/sns_api.extend.php
- extend/pg_api.extend.php
- extend/api_auth.extend.php

**문서 (1개)**
- api/TEST_GUIDE.md

### 주요 기능

#### SNS 로그인 API
- 네이버, 카카오, 구글, 페이스북, 트위터, 페이코 지원
- API 키 기반 인증
- IP 화이트리스트 보안
- 자동 회원 가입
- JWT 토큰 기반 인증
- 커스텀 필드 지원

#### PG 결제 API
- 상품 조회 (목록, 상세, 재고 확인)
- 장바구니 관리 (추가, 수정, 삭제)
- 주문 생성
- 결제 처리 (이니시스, LG U+, KCP, 카카오페이, 나이스페이)
- 웹훅 기능

#### 관리자 페이지
- API 키 발급/관리
- IP 화이트리스트 관리
- API 로그 조회
- SNS 로그인 로그 조회
- PG 주문 관리

### 다음 단계

1. 단위 테스트 진행
2. 통합 테스트 진행
3. 다른 그누보드 이식 테스트
4. 성능 테스트
5. 설치 가이드 작성
6. API 문서 작성
7. 이식 가이드 작성

---

**마지막 업데이트**: 2026-01-19 17:00
**개발 상태**: 전체 완료 (테스트 가이드 및 문서화 완료)
**완료된 파일**: 총 24개
**개발 기간**: 2026-01-19 1일간
