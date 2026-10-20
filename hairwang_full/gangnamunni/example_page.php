<?php
/**
 * 공통 헤더/푸터 사용 예제 페이지
 */

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';

// 헤더 설정
$page_title = '예제 페이지 | 서울미녀';
$show_back_btn = true;
$back_url = 'index.php';
$header_title = '예제 페이지';

// 페이지별 스타일 파일 (선택사항)
$page_styles = [
    // 'css/custom.css'
];

// 공통 헤더 포함
include __DIR__ . '/includes/header_common.php';
?>

<!-- 페이지별 추가 스타일 -->
<style>
    .example-content {
        padding: 20px;
    }

    .example-box {
        background: var(--color-bg-light);
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 16px;
    }

    .example-title {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 12px;
        color: var(--color-primary);
    }

    .example-text {
        font-size: 14px;
        line-height: 1.6;
        color: var(--color-text-secondary);
    }
</style>

<!-- 페이지 컨텐츠 -->
<div class="example-content">
    <h1 style="font-size: 24px; margin-bottom: 20px;">공통 헤더/푸터 사용 예제</h1>

    <div class="example-box">
        <div class="example-title">✅ 사용 방법</div>
        <div class="example-text">
            1. 페이지 상단에 헤더 설정 변수 선언<br>
            2. includes/header_common.php 포함<br>
            3. 페이지 컨텐츠 작성<br>
            4. includes/footer_common.php 포함
        </div>
    </div>

    <div class="example-box">
        <div class="example-title">📝 헤더 설정 변수</div>
        <div class="example-text">
            • $page_title: 페이지 타이틀<br>
            • $show_back_btn: 뒤로가기 버튼 표시 여부<br>
            • $back_url: 뒤로가기 URL<br>
            • $header_title: 헤더에 표시할 제목<br>
            • $page_styles: 추가 CSS 파일 배열<br>
            • $page_scripts: 추가 JS 파일 배열
        </div>
    </div>

    <div class="example-box">
        <div class="example-title">🎨 CSS 변수 사용 가능</div>
        <div class="example-text">
            --color-primary: #AC1830 (메인 색상)<br>
            --color-text-primary: #333333<br>
            --color-bg-white: #ffffff<br>
            --color-bg-light: #f8f8f8<br>
            --color-border: #f0f0f0<br>
            등 다양한 CSS 변수를 사용할 수 있습니다.
        </div>
    </div>

    <div class="example-box">
        <div class="example-title">💡 장점</div>
        <div class="example-text">
            • 헤더 수정 시 모든 페이지에 자동 반영<br>
            • 코드 중복 제거<br>
            • 유지보수 편리<br>
            • 일관된 디자인 유지
        </div>
    </div>
</div>

<!-- 페이지별 스크립트 -->
<script>
    console.log('예제 페이지 로드됨');
</script>

<?php
// 공통 푸터 포함
include __DIR__ . '/includes/footer_common.php';
?>
