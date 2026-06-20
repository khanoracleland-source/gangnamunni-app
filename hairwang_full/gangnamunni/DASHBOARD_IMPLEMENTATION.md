# 강남언니 관리자 대시보드 구현 문서

## 작업 일시
- **날짜**: 2026-02-02
- **파일**: `gangnamunni/dashboard.php`

---

## 개요

강남언니 플랫폼의 모든 관리 페이지를 한곳에서 접근할 수 있는 **관리자 대시보드 메인 페이지**를 구현했습니다.

---

## 기술 스택

| 기술 | 용도 |
|------|------|
| **Tailwind CSS** (CDN) | 유틸리티 기반 반응형 레이아웃 및 스타일링 |
| **Bootstrap 5.3.3** (CDN) | 그리드 시스템 보완 및 JavaScript 컴포넌트 |
| **Font Awesome 6.5** | 아이콘 |
| **Google Fonts** (Noto Sans KR) | 한글 웹폰트 |
| **PHP** | 서버사이드 DB 통계 조회 |

---

## 주요 기능

### 1. 관리 페이지 허브 (8개 페이지)
모든 관리 페이지를 카드 형태로 표시하며, 클릭 시 **새 탭(`target="_blank"`)** 으로 열립니다.

| # | 페이지 | 파일명 | 설명 |
|---|--------|--------|------|
| 1 | 테마 관리 | `admin_theme.php` | 사이트 색상, 폰트, 섹션 제목 등 기본 테마 설정 |
| 2 | 고급 테마 관리 | `admin_theme_advanced.php` | 로그인 기반 고급 테마 설정, 관리자 계정 관리 |
| 3 | 카테고리 관리 | `admin_categories.php` | 시술 카테고리 CRUD 및 표시 순서 관리 |
| 4 | 네비게이션 관리 | `admin_navigation.php` | 하단 네비게이션 메뉴 아이템 관리 |
| 5 | 광고 배너 관리 | `admin_ads.php` | 메인 페이지 광고 배너 CRUD |
| 6 | 병원 이미지 관리 | `admin_hospital_images.php` | 병원별 이미지 추가/삭제/순서 변경 |
| 7 | 테마 헬퍼 | `theme_helper.php` | 테마 설정 로드 헬퍼 함수 모듈 |
| 8 | 환경 설정 | `config.php` | 페이지당 표시 수, 카테고리 목록, 데모 모드 설정 |

### 2. 실시간 통계 대시보드
DB 연결 후 다음 항목의 실시간 카운트를 표시합니다:
- 병원 수, 카테고리 수, 광고 배너 수, 꿀팁 수, 이벤트 수, 네비게이션 항목 수
- 숫자 카운트업 애니메이션 적용

### 3. 프론트엔드 페이지 바로가기
사용자 대면 페이지(메인, 병원 목록, 꿀팁, 이벤트, 검색, 예약, 상담, 칼럼, 크롤링 뷰어)로의 빠른 접근 제공

### 4. 시스템 정보
- PHP 버전
- DB 연결 상태 (초록색/빨간색 인디케이터)
- 서버 시간
- 파일 경로

---

## 디자인 특징

- **Glassmorphism (글래스모피즘)**: 반투명 배경 + 블러 효과 카드
- **다크 테마**: 그라데이션 애니메이션 배경 (`#0f0c29` ~ `#1a1a2e` ~ `#16213e`)
- **플로팅 파티클**: 배경에 떠다니는 컬러 입자 애니메이션
- **호버 효과**: 카드 확대 + 상승 + 글로우 효과
- **반응형**: 모바일(1열) / 태블릿(2열) / 데스크톱(3~4열) 자동 조정
- **카운트업 애니메이션**: 통계 숫자가 0에서 실제 값까지 올라가는 애니메이션
- **DB 상태 인디케이터**: 연결 상태를 펄스 애니메이션으로 실시간 표시

---

## 접근 방법

```
https://[도메인]/gangnamunni/dashboard.php
```

---

## 파일 구조 (변경 사항)

```
gangnamunni/
├── dashboard.php                    ← 신규 생성
├── DASHBOARD_IMPLEMENTATION.md      ← 신규 생성 (본 문서)
├── admin_theme.php                  (기존)
├── admin_theme_advanced.php         (기존)
├── admin_categories.php             (기존)
├── admin_navigation.php             (기존)
├── admin_ads.php                    (기존)
├── admin_hospital_images.php        (기존)
├── theme_helper.php                 (기존)
└── config.php                       (기존)
```

---

## 의존성

- 외부 CDN: Tailwind CSS, Bootstrap 5.3.3, Font Awesome 6.5.1, Google Fonts
- 내부: `dbconfig.php` (상위 디렉토리)
- DB 테이블: `gangnamunni_hospitals`, `gangnamunni_categories`, `gangnamunni_ads`, `gangnamunni_tips`, `gangnamunni_events`, `navigation_menu`
