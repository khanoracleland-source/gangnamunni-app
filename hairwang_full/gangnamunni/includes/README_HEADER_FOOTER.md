# 공통 헤더/푸터 사용 가이드

## 📁 파일 구조
```
gangnamunni/
├── includes/
│   ├── header_common.php   # 공통 헤더
│   └── footer_common.php   # 공통 푸터
└── example_page.php        # 사용 예제
```

## 🚀 사용 방법

### 1. 기본 사용법

```php
<?php
// 헤더 설정
$page_title = '페이지 제목 | 서울미녀';
$show_back_btn = true;
$back_url = 'index.php';
$header_title = '헤더에 표시할 제목';

// 공통 헤더 포함
include __DIR__ . '/includes/header_common.php';
?>

<!-- 페이지 컨텐츠 -->
<div class="your-content">
    <h1>페이지 내용</h1>
</div>

<?php
// 공통 푸터 포함
include __DIR__ . '/includes/footer_common.php';
?>
```

### 2. 헤더 설정 변수

| 변수명 | 타입 | 기본값 | 설명 |
|--------|------|--------|------|
| `$page_title` | string | '서울미녀' | 브라우저 탭 타이틀 |
| `$show_back_btn` | boolean | false | 뒤로가기 버튼 표시 여부 |
| `$back_url` | string | 'index.php' | 뒤로가기 URL |
| `$header_title` | string | '' | 헤더 중앙에 표시할 제목 |
| `$page_styles` | array | [] | 추가 CSS 파일 경로 배열 |
| `$page_scripts` | array | [] | 추가 JS 파일 경로 배열 |

### 3. 추가 스타일/스크립트 로드

```php
// CSS 파일 추가
$page_styles = [
    'css/custom.css',
    'css/modal.css'
];

// JS 파일 추가
$page_scripts = [
    'js/custom.js',
    'js/utils.js'
];

include __DIR__ . '/includes/header_common.php';
```

## 🎨 사용 가능한 CSS 변수

공통 헤더에 정의된 CSS 변수들:

```css
/* 메인 컬러 */
--color-primary: #7c3aed;
--color-primary-light: #f3e8ff;
--color-primary-dark: #5b21b6;

/* 텍스트 컬러 */
--color-text-primary: #333333;
--color-text-secondary: #666666;
--color-text-muted: #999999;

/* 배경 컬러 */
--color-bg-white: #ffffff;
--color-bg-light: #f8f8f8;
--color-bg-gray: #eeeeee;
--color-border: #f0f0f0;

/* 기타 */
--color-star: #fbbf24;
--color-tag-bg: #F0F4FF;
--color-tag-text: #4A90E2;
--color-kakao: #fee500;
```

사용 예:
```css
.my-element {
    color: var(--color-primary);
    background: var(--color-bg-light);
    border: 1px solid var(--color-border);
}
```

## 📱 반응형 레이아웃

공통 헤더는 자동으로 반응형 레이아웃을 제공합니다:
- **PC**: 640px 최대 너비로 중앙 정렬, 양쪽 여백
- **모바일**: 전체 너비 사용

`.page-container` 클래스가 자동으로 적용되어 있습니다.

## ✅ 장점

1. **일관성**: 모든 페이지에서 동일한 헤더/푸터 사용
2. **유지보수**: 헤더 수정 시 모든 페이지에 자동 반영
3. **코드 중복 제거**: DRY 원칙 준수
4. **빠른 개발**: 새 페이지 개발 시간 단축

## 📋 예제 페이지

실제 동작 예제는 `example_page.php` 파일을 참고하세요.

```
http://localhost:8080/gangnamunni/example_page.php
```

## 🔧 기존 페이지 변환

기존 detail.php 같은 페이지를 공통 헤더/푸터로 변환하려면:

1. 기존 `<head>` ~ `<body>` 부분을 `header_common.php` include로 교체
2. 페이지별 스타일은 `<style>` 태그로 유지 또는 별도 CSS 파일로 분리
3. `</body></html>` 부분을 `footer_common.php` include로 교체

## 📞 문의

추가 기능이나 수정이 필요하면 언제든지 요청하세요!
