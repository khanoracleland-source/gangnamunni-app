# 강남언니 사이트 분석 및 개발 계획

> 분석일: 2026-01-28
> 크롤링 데이터: H:\carebaby_project\site_get\output\www.gangnamunni.com.backup
> 총 페이지: 560 HTML files

---

## 📊 페이지 타입 분석 (page_analysis.json 기반)

### 1. Main Landing Page (index) - 149 pages
**파일**: `index.html`
**구조**:
- 카테고리 선택 화면 (시술별 아이콘)
- 리프팅, 보톡스, 필러, 피부, 지방성형, 코성형, 눈성형, 가슴성형, 치아, 제모, 모발이식, 기타, 한방
- 메인 히어로 섹션
- 인기 이벤트 섹션
- 병원 추천 섹션

### 2. Hospital List Page - 5 pages
**구조**:
- 카테고리별 병원 목록 (눈성형, 치아, 코성형, 피부, 한방)
- 병원 카드 (이미지, 이름, 평점, 리뷰 수, 위치, 태그)
- 필터링 (지역, 가격, 평점)
- 정렬 (인기순, 평점순, 리뷰순)

### 3. Hospital Detail Page - 0 pages (hospitals/{id}/index.html에 포함)
**구조**:
- 병원 상세 정보
- 사진 갤러리
- 시술 정보
- 가격 정보
- 리뷰 목록
- 예약 버튼
- 위치 지도

### 4. Event List Page - 157 pages
**파일**:
- `events/index.html` (전체)
- `events_q__{category}.html` (85 categories)
- `events_q__{category}_s_{sort}.html` (72 sorted)

**구조**:
- 이벤트 카드 목록
- 할인율, 원가, 할인가
- 병원명, 시술명
- D-day 표시
- 정렬: 인기순, 평점순, 리뷰순, 할인율순, 가격순, 마감임박순

### 5. Event Detail Page - 194 pages
**파일**: `events/{id}_optionId_{uuid}.html`, `events/{id}/index.html`
**구조**:
- 이벤트 상세 정보
- 시술 설명
- 가격 정보
- 이벤트 기간
- 병원 정보
- 상담 예약 버튼
- 관련 리뷰

### 6. District Filter Page - 10 pages
**파일**: `district-filter_q__{category}.html`
**구조**:
- 지역별 필터 UI
- 서울 (강남, 신사, 압구정, 논현, 역삼, etc.)
- 경기 (분당, 일산, 수원, etc.)
- 부산, 대구, 대전, 광주, 인천

### 7. Category Search Page - 13 pages
**파일**: `search_category__{category}.html`
**구조**:
- 카테고리별 검색 결과
- 병원 + 이벤트 통합 검색

### 8. Community Pages
**파일**:
- `community/index.html`
- `community/medical-tips/index.html`
- `community/medical-tips/{id}/index.html`

**구조**:
- 커뮤니티 게시판
- 의료 팁 목록
- 의료 팁 상세

### 9. Posts/Reviews
**파일**: `posts/{id}/index.html`
**구조**:
- 리뷰 상세 페이지
- 사진, 별점, 후기
- 좋아요, 댓글

---

## 🎯 개발 우선순위

### Phase 1: Core Pages (필수)
1. **Main Landing Page** - 카테고리 선택 화면
2. **Hospital List Page** - 병원 목록
3. **Hospital Detail Page** - 병원 상세
4. **Development Index** - 개발된 페이지 목록

### Phase 2: Event System
5. **Event List Page** - 이벤트 목록
6. **Event Detail Page** - 이벤트 상세
7. **District Filter** - 지역 필터

### Phase 3: Community
8. **Community Index** - 커뮤니티 메인
9. **Medical Tips** - 의료 팁
10. **Posts/Reviews** - 리뷰 상세

---

## 🔧 기술 스택

### Frontend
- HTML5, CSS3, Vanilla JavaScript
- Responsive Design (Mobile-first)
- SPA Architecture

### Backend
- Existing API: http://localhost:8080/api
- Endpoints:
  - GET /api/hospitals
  - GET /api/hospitals/{id}
  - GET /api/categories
  - GET /api/bookings
  - GET /api/reviews

### Database
- MySQL kivicare_laravel database
- 99 hospitals with complete data
- Categories, reviews, bookings tables

---

## 📁 개발 파일 구조

```
h:/carebaby_project/
├── hospital-pages/               # 새로 개발할 페이지들
│   ├── index.html                # Development Index (목록 페이지)
│   ├── main-landing.html         # Main Landing (카테고리 선택)
│   ├── hospital-list.html        # Hospital List
│   ├── hospital-detail.html      # Hospital Detail
│   ├── event-list.html           # Event List
│   ├── event-detail.html         # Event Detail
│   ├── district-filter.html      # District Filter
│   ├── community-index.html      # Community
│   ├── medical-tips.html         # Medical Tips
│   ├── post-detail.html          # Post/Review Detail
│   └── assets/                   # CSS, JS, Images
│       ├── css/
│       ├── js/
│       └── images/
├── hospital-api/                 # Existing API
└── hospital-frontend/            # Existing Frontend (현재는 2번째 화면)
```

---

## 🎨 디자인 가이드

### 색상 (서울미녀 브랜딩)
- Primary: #FF6B9D (핑크)
- Primary Dark: #E85A88
- Accent: #FFE5ED (연핑크)
- Background: #FFFFFF
- Text: #333333
- Border: #F0F0F0

### 타이포그래피
- 제목: 24-32px, Bold
- 본문: 14-16px, Regular
- 캡션: 12px, Regular

### 컴포넌트
- 병원 카드: 그림자, 호버 효과, 배지
- 이벤트 카드: 할인율 배지, D-day 표시
- 카테고리 아이콘: 96x96px, 컬러 아이콘
- 버튼: 둥근 모서리, 그라데이션

---

## 🔗 API 연동 계획

### 1. Main Landing
- GET /api/categories (카테고리 목록)
- GET /api/stats (통계 정보)

### 2. Hospital List
- GET /api/hospitals?category={id}&page={n}
- Filter: rating, location, price

### 3. Hospital Detail
- GET /api/hospitals/{id}
- GET /api/hospitals/{id}/reviews
- GET /api/hospitals/{id}/events

### 4. Event List
- GET /api/events?category={id}&sort={type}
- Sort: popularity, rating, discount, price, deadline

### 5. Event Detail
- GET /api/events/{id}
- GET /api/events/{id}/hospital

---

## 📝 구현 체크리스트

### Main Landing Page
- [ ] Hero 섹션
- [ ] 카테고리 아이콘 그리드 (13개)
- [ ] 인기 병원 슬라이더
- [ ] 인기 이벤트 섹션
- [ ] 통계 정보
- [ ] 반응형 디자인

### Hospital List Page
- [ ] 병원 카드 그리드
- [ ] 필터 사이드바
- [ ] 정렬 드롭다운
- [ ] 페이지네이션
- [ ] 지도 뷰 토글

### Hospital Detail Page
- [ ] 이미지 갤러리
- [ ] 병원 정보 섹션
- [ ] 시술 메뉴 탭
- [ ] 가격표
- [ ] 리뷰 목록
- [ ] 예약 버튼
- [ ] 위치 지도

### Event List Page
- [ ] 이벤트 카드 그리드
- [ ] 카테고리 필터
- [ ] 정렬 버튼
- [ ] 할인율 배지
- [ ] D-day 표시
- [ ] 무한 스크롤

### Event Detail Page
- [ ] 이벤트 헤더
- [ ] 시술 설명
- [ ] 가격 정보
- [ ] 병원 정보 카드
- [ ] 예약 버튼
- [ ] 관련 이벤트

### Development Index Page
- [ ] 페이지 썸네일 그리드
- [ ] 페이지 제목 및 설명
- [ ] 새 탭에서 열기
- [ ] 카테고리별 그룹화
- [ ] 검색 기능

---

## 🚀 배포 계획

### Local Development
1. 각 페이지를 hospital-pages/ 폴더에 생성
2. PHP 서버로 로컬 테스트
3. API 연동 확인

### Port Configuration
- Development Index: http://localhost:3003
- Main Pages: http://localhost:3003/main-landing.html
- API Backend: http://localhost:8080/api (기존 유지)

---

## ⚙️ 다음 단계

1. ✅ 분석 완료
2. 🔄 Development Index 페이지 생성
3. ⏳ Main Landing 페이지 구현
4. ⏳ Hospital List 페이지 구현
5. ⏳ Hospital Detail 페이지 구현
6. ⏳ Event 시스템 구현
7. ⏳ Community 페이지 구현
8. ⏳ 최종 테스트 및 문서화

---

**작성자**: Claude Sonnet 4.5
**날짜**: 2026-01-28
**상태**: 분석 완료, 개발 준비 중
