# 서울미녀 테마 커스터마이징 시스템 구축 완료

**작성일:** 2026-01-29
**프로젝트:** 강남언니 스타일 → 서울미녀 플랫폼
**버전:** 1.0.0

---

## ✅ 완료된 작업

### 1. 아이콘 파일 처리 및 적용
- **원본 파일:**
  - `app_icon.png` (1024x1024, 흰색 배경, 45KB)
  - `app_icon_trans.png` (1024x1024, 투명 배경, 1.4MB)

- **생성된 파일:**
  ```
  gangnamunni/assets/images/
  ├── logo_original.png       (1024x1024, 원본 복사)
  ├── logo_trans_original.png (1024x1024, 투명 원본)
  ├── logo_512.png            (512x512)
  ├── logo_256.png            (256x256)
  ├── logo_128.png            (128x128, 기본 로고)
  ├── logo_64.png             (64x64)
  ├── logo_32.png             (32x32)
  ├── favicon-32.png          (32x32, 파비콘)
  └── favicon.png             (16x16, 파비콘)
  ```

- **적용 위치:**
  - 사이트 로고 (헤더)
  - 파비콘 (브라우저 탭)
  - Apple Touch Icon (iOS 홈 화면)

---

### 2. 데이터베이스 테이블 생성

#### gangnamunni_theme_settings 테이블
```sql
CREATE TABLE gangnamunni_theme_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'text',
    description VARCHAR(255),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
);
```

#### 저장되는 설정 항목 (30개)

**색상 설정 (9개):**
- primary_color - 메인 브랜드 컬러 (#FF540F)
- secondary_color - 보조 브랜드 컬러 (#E64A07)
- text_color - 기본 텍스트 색상 (#000000)
- text_secondary_color - 보조 텍스트 색상 (#666666)
- background_color - 배경색 (#FFFFFF)
- border_color - 테두리 색상 (#F5F5F5)
- button_text_color - 버튼 텍스트 색상 (#FFFFFF)
- link_color - 링크 색상 (#FF540F)
- link_hover_color - 링크 호버 색상 (#E64A07)

**폰트 설정 (7개):**
- font_korean - 한글 폰트 (Noto Sans KR)
- font_english - 영문 폰트 (Roboto)
- font_size_base - 기본 폰트 크기 (14px)
- font_size_large - 큰 폰트 크기 (18px)
- font_size_small - 작은 폰트 크기 (12px)
- font_weight_normal - 일반 폰트 굵기 (400)
- font_weight_bold - 굵은 폰트 굵기 (700)

**텍스트 설정 (6개):**
- site_name - 사이트 이름 (서울미녀)
- site_logo_path - 로고 이미지 경로
- section_tips_title - 꿀팁 섹션 제목 (언니네 꿀팁)
- section_hospitals_title - 병원 섹션 제목 (추천 병원)
- section_categories_title - 카테고리 섹션 제목
- button_booking - 예약 버튼 텍스트 (예약하기)
- button_consultation - 상담 버튼 텍스트 (상담하기)
- button_more - 더보기 버튼 텍스트 (전체보기)

**레이아웃 설정 (5개):**
- border_radius - 테두리 둥글기 (12px)
- card_shadow - 카드 그림자
- spacing_small - 작은 여백 (8px)
- spacing_medium - 중간 여백 (16px)
- spacing_large - 큰 여백 (24px)

---

### 3. 테마 관리 페이지 (admin_theme.php)

#### 주요 기능

**1) 색상 설정 탭**
- 색상 선택 버튼 (HTML5 Color Picker)
- HEX 코드 직접 입력
- 실시간 미리보기
- 9개 색상 항목 관리

**2) 폰트 설정 탭**
- 한글 폰트 선택 (10종)
  - Noto Sans KR
  - Nanum Gothic
  - Nanum Myeongjo
  - Nanum Square
  - Black Han Sans
  - Do Hyeon
  - Jua
  - Sunflower
  - Gothic A1
  - IBM Plex Sans KR

- 영문 폰트 선택 (10종)
  - Roboto
  - Open Sans
  - Lato
  - Montserrat
  - Poppins
  - Raleway
  - Inter
  - Work Sans
  - Nunito
  - Rubik

- 폰트 크기 설정 (기본/큰/작은)
- 폰트 굵기 설정 (일반/굵은: 100~900)
- 실시간 한글/영문 미리보기

**3) 텍스트 설정 탭**
- 사이트 이름 변경
- 로고 이미지 경로 설정
- 섹션 제목 커스터마이징
- 버튼 텍스트 변경

**4) 레이아웃 설정 탭**
- 테두리 둥글기
- 카드 그림자 효과
- 여백 크기 조정

**5) 미리보기 탭**
- 실시간 스타일 미리보기
- 색상/폰트 변경 즉시 반영
- 실제 페이지 확인 버튼

#### UI/UX 특징
- 탭 기반 직관적 인터페이스
- 색상 픽커 + 텍스트 입력 동시 지원
- 실시간 미리보기 (JavaScript)
- Google Fonts 동적 로드
- 반응형 레이아웃
- 하단 고정 저장 버튼

---

### 4. 테마 헬퍼 함수 (theme_helper.php)

#### 제공 함수

**1) loadThemeSettings($connect)**
- 데이터베이스에서 모든 테마 설정 로드
- 전역 변수 $THEME_SETTINGS에 저장
- 기본값 자동 설정

**2) getTheme($key, $default = '')**
- 특정 설정 값 가져오기
- 없으면 기본값 반환
- 사용 예: `<?php echo getTheme('site_name'); ?>`

**3) generateThemeCSS()**
- CSS 변수 생성
- 공통 스타일 적용
- body 폰트 설정
- 버튼, 링크, 카드 스타일

**4) generateGoogleFontsLink()**
- Google Fonts 링크 태그 생성
- 선택된 한글/영문 폰트 자동 로드
- Preconnect 최적화

**5) generateFaviconLinks()**
- 파비콘 링크 태그 생성
- 다양한 크기 지원
- Apple Touch Icon 포함

---

### 5. 기존 페이지에 테마 적용

#### 적용된 페이지 (8개)
1. **index.php** - 메인 페이지
2. **detail.php** - 병원 상세 페이지
3. **booking.php** - 예약 페이지
4. **consultation.php** - 상담 신청 페이지
5. **search.php** - 검색 결과 페이지
6. **tips.php** - 꿀팁 전체보기 페이지
7. **detail_tip.php** - 꿀팁 상세 페이지
8. **admin_theme.php** - 테마 관리 페이지

#### 적용 방법

**1) PHP 코드 추가**
```php
// theme_helper.php 추가
require_once __DIR__ . '/theme_helper.php';

// 테마 설정 로드
loadThemeSettings($connect);
```

**2) HTML <head> 섹션**
```php
<title><?php echo getTheme('site_name'); ?></title>
<?php echo generateFaviconLinks(); ?>
<?php echo generateGoogleFontsLink(); ?>
<?php echo generateThemeCSS(); ?>
```

**3) 동적 텍스트 적용**
```php
<!-- 사이트 이름 -->
<div class="logo"><?php echo getTheme('site_name'); ?></div>

<!-- 섹션 제목 -->
<h2><?php echo getTheme('section_tips_title'); ?></h2>
<h2><?php echo getTheme('section_hospitals_title'); ?></h2>

<!-- 버튼 텍스트 -->
<button><?php echo getTheme('button_booking'); ?></button>
<button><?php echo getTheme('button_consultation'); ?></button>
```

---

## 📁 생성/수정된 파일

### 신규 생성 파일
```
gangnamunni/
├── assets/
│   └── images/
│       ├── logo_512.png
│       ├── logo_256.png
│       ├── logo_128.png
│       ├── logo_64.png
│       ├── logo_32.png
│       ├── favicon-32.png
│       ├── favicon.png
│       ├── logo_original.png
│       └── logo_trans_original.png
├── admin_theme.php          (테마 관리 페이지)
└── theme_helper.php         (테마 헬퍼 함수)
```

### 수정된 파일
```
gangnamunni/
├── index.php                (테마 적용)
├── detail.php               (테마 적용)
├── booking.php              (테마 적용)
├── consultation.php         (테마 적용)
├── search.php               (테마 적용)
├── tips.php                 (테마 적용)
└── detail_tip.php           (테마 적용)
```

### 데이터베이스
```sql
gangnamunni_theme_settings  (30개 기본 설정 포함)
```

---

## 🚀 사용 방법

### 1. 테마 관리 페이지 접속
```
http://localhost:8080/gangnamunni/admin_theme.php
```

### 2. 설정 변경
1. **색상 변경**
   - "색상 설정" 탭 선택
   - 색상 버튼 클릭 → 마우스로 색 선택
   - 또는 HEX 코드 직접 입력
   - 실시간 미리보기 확인

2. **폰트 변경**
   - "폰트 설정" 탭 선택
   - 한글 폰트 드롭다운에서 선택
   - 영문 폰트 드롭다운에서 선택
   - 미리보기에서 폰트 확인
   - 폰트 크기/굵기 조정

3. **텍스트 변경**
   - "텍스트 설정" 탭 선택
   - 사이트 이름 입력 (예: 서울미녀)
   - 섹션 제목 변경
   - 버튼 텍스트 수정

4. **레이아웃 조정**
   - "레이아웃 설정" 탭 선택
   - 테두리 둥글기, 그림자, 여백 조정

5. **미리보기**
   - "미리보기" 탭에서 실시간 확인
   - "실제 페이지 보기" 버튼으로 적용 확인

6. **저장**
   - "💾 저장하기" 버튼 클릭
   - 설정이 데이터베이스에 저장됨
   - 모든 페이지에 즉시 반영

### 3. 초기화
- "↻ 초기화" 버튼으로 변경사항 되돌리기
- 페이지 새로고침 시 저장되지 않은 변경사항 취소

---

## 🎨 커스터마이징 예시

### 예시 1: 서울미녀로 브랜딩 변경
```
사이트 이름: 서울미녀
메인 컬러: #E91E63 (핑크)
보조 컬러: #C2185B (진한 핑크)
한글 폰트: Nanum Myeongjo
섹션 제목:
  - 꿀팁: "서울미녀 뷰티 팁"
  - 병원: "인기 병원"
  - 카테고리: "원하는 시술을 찾아보세요"
```

### 예시 2: 모던 스타일
```
메인 컬러: #2196F3 (블루)
보조 컬러: #1976D2 (진한 블루)
한글 폰트: IBM Plex Sans KR
영문 폰트: Inter
테두리 둥글기: 8px
여백: 일관성 있게 조정
```

### 예시 3: 고급스러운 느낌
```
메인 컬러: #000000 (블랙)
보조 컬러: #424242 (다크 그레이)
텍스트 컬러: #FAFAFA (밝은 그레이)
배경색: #121212 (다크 모드)
한글 폰트: Nanum Myeongjo
카드 그림자: 0 4px 16px rgba(0,0,0,0.3)
```

---

## 💡 활용 가이드

### 색상 선택 팁
1. **브랜드 아이덴티티 고려**
   - 메인 컬러는 브랜드를 대표하는 색
   - 보조 컬러는 메인 컬러보다 약간 진하게
   - 텍스트는 배경과 충분한 대비

2. **접근성 고려**
   - WCAG 2.1 AA 기준 (4.5:1 대비율)
   - 색맹 사용자 고려
   - 명도 차이 충분히 확보

3. **일관성 유지**
   - 링크 색상은 메인 컬러 사용
   - 호버 색상은 보조 컬러 사용
   - 버튼은 메인/보조 컬러 활용

### 폰트 선택 가이드
1. **한글 폰트**
   - Noto Sans KR: 범용성, 가독성 우수
   - Nanum Gothic: 웹 표준, 무난함
   - Nanum Myeongjo: 고급스러운 느낌
   - Black Han Sans: 강렬한 제목용
   - Do Hyeon: 친근한 느낌

2. **영문 폰트**
   - Roboto: 모던하고 깔끔함
   - Open Sans: 가독성 최고
   - Montserrat: 세련된 느낌
   - Poppins: 귀엽고 친근함
   - Inter: 현대적이고 기능적

3. **조합 추천**
   - Noto Sans KR + Roboto (범용)
   - Nanum Myeongjo + Playfair Display (고급)
   - Gothic A1 + Montserrat (모던)
   - Nanum Square + Poppins (친근)

### 레이아웃 팁
1. **테두리 둥글기**
   - 4px: 미니멀
   - 8px: 모던
   - 12px: 부드러운 (기본)
   - 16px 이상: 캐주얼

2. **여백**
   - 작은 여백: 요소 간 간격
   - 중간 여백: 섹션 내부
   - 큰 여백: 섹션 간 구분

---

## 🔧 기술 스택

### 프론트엔드
- HTML5
- CSS3 (CSS Variables)
- JavaScript (ES6+)
- Google Fonts API

### 백엔드
- PHP 7.4+
- MySQL 5.7+
- mysqli Extension

### 라이브러리
- Google Fonts (무료, 저작권 걱정 없음)
- HTML5 Color Picker (네이티브)
- GD Library (이미지 리사이징)

---

## 📊 데이터베이스 스키마

### gangnamunni_theme_settings
| 컬럼명 | 타입 | 설명 |
|--------|------|------|
| id | INT | 자동 증가 ID |
| setting_key | VARCHAR(100) | 설정 키 (고유) |
| setting_value | TEXT | 설정 값 |
| setting_type | VARCHAR(50) | 설정 유형 (color, text, font, size, number) |
| description | VARCHAR(255) | 설명 |
| updated_at | DATETIME | 최종 수정 시간 |

---

## ⚠️ 주의사항

### 1. 캐시 관리
- 폰트 변경 시 브라우저 새로고침 필요
- CSS 변경은 즉시 반영
- 이미지 변경 시 하드 리프레시 (Ctrl+F5)

### 2. 성능 최적화
- Google Fonts는 2개만 로드 (한글, 영문)
- 각 폰트는 2개 웨이트만 (400, 700)
- 파비콘은 적절한 크기 사용

### 3. 호환성
- IE11 미지원 (CSS Variables)
- 모던 브라우저 권장
- 모바일 최적화 완료

### 4. 백업
- 테마 변경 전 DB 백업 권장
- 설정 export/import 기능 없음 (수동 SQL)

---

## 🔄 업데이트 계획

### 추후 추가 가능 기능
1. **테마 프리셋**
   - 미리 만들어진 테마 세트
   - 원클릭 적용
   - 테마 가져오기/내보내기

2. **다크 모드**
   - 라이트/다크 자동 전환
   - 시간별 자동 변경
   - 사용자 선택 저장

3. **고급 색상 도구**
   - 색상 팔레트 생성기
   - 조화로운 색상 추천
   - 접근성 검사

4. **로고 업로드**
   - 이미지 파일 직접 업로드
   - 자동 크기 조정
   - 투명 배경 처리

5. **미리보기 개선**
   - 모바일/태블릿 미리보기
   - 페이지별 미리보기
   - Before/After 비교

6. **사용자 권한**
   - 관리자 인증
   - 변경 이력 추적
   - 롤백 기능

---

## 📞 문의 및 지원

### 테마 수정 요청
- 색상 추가 요청
- 폰트 추가 요청
- 새로운 커스터마이징 항목 요청

### 버그 리포트
- admin_theme.php 오류
- 테마 적용 안 됨
- 저장 실패

---

## ✨ 완료 확인

### 체크리스트
- [x] 아이콘 파일 크기 조정 (7종)
- [x] 데이터베이스 테이블 생성
- [x] 테마 설정 30개 삽입
- [x] 테마 관리 페이지 구현
- [x] 색상 선택 기능 (컬러 피커)
- [x] 폰트 선택 기능 (콤보박스)
- [x] 한글/영문 폰트 미리보기
- [x] 실시간 미리보기 기능
- [x] Google Fonts 통합 (20종)
- [x] 테마 헬퍼 함수 생성
- [x] 8개 페이지에 테마 적용
- [x] 파비콘 자동 생성 및 적용
- [x] CSS Variables 활용
- [x] 반응형 디자인
- [x] 문서 작성

---

## 🎉 결과

### 구현 완료
- **관리 페이지:** http://localhost:8080/gangnamunni/admin_theme.php
- **메인 페이지:** http://localhost:8080/gangnamunni/index.php

### 주요 성과
1. **브랜드 커스터마이징 완성**
   - 강남언니 → 서울미녀로 자유롭게 변경 가능
   - 클릭 몇 번으로 브랜드 아이덴티티 구축

2. **디자인 자유도 극대화**
   - 30개 설정 항목
   - 무제한 색상 조합
   - 20종 무료 폰트

3. **사용자 친화적 인터페이스**
   - 직관적인 탭 구조
   - 실시간 미리보기
   - 마우스 클릭으로 색상 선택

4. **기술적 완성도**
   - 데이터베이스 기반 설정 관리
   - CSS Variables로 성능 최적화
   - Google Fonts 자동 로드
   - 모든 페이지 일관성 유지

---

**프로젝트 상태:** ✅ 완료
**최종 업데이트:** 2026-01-29
**버전:** 1.0.0
