# Claude CLI 작업 프롬프트: 바비톡 스타일 병원 플랫폼 (PHP + MySQL + KiviCare)

## 🎯 프로젝트 목표

바비톡(babitalk.com) 스타일의 **병원 시술 플랫폼**을 PHP와 MySQL을 사용하여 동적으로 구현한다.
- 모든 데이터는 하드코딩이 아닌 MySQL DB에서 동적으로 가져온다
- KiviCare 기존 테이블 구조를 활용하고, 필요시 테이블을 확장한다
- **전역 설정(Config)**으로 색상, 폰트, 버튼 스타일 등을 중앙 관리한다
- **공통 헤더 모듈**을 만들어 모든 페이지에서 재사용한다

---

## 🎨 전역 설정 시스템 (Config)

### config/settings.php - 전역 설정 파일

```php
<?php
/**
 * 전역 설정 파일
 * 모든 페이지에서 사용되는 색상, 폰트, 스타일 등을 중앙 관리
 */

return [
    // ========================================
    // 사이트 기본 정보
    // ========================================
    'site' => [
        'name' => '서울미녀',
        'logo_url' => '/assets/images/logo.svg',
        'logo_text' => '서울미녀',
        'favicon' => '/assets/images/favicon.ico',
    ],

    // ========================================
    // 색상 설정
    // ========================================
    'colors' => [
        // 메인 컬러
        'primary' => '#7c3aed',           // 보라색 (메인)
        'primary_light' => '#f3e8ff',     // 연한 보라
        'primary_dark' => '#5b21b6',      // 진한 보라
        
        // 보조 컬러
        'secondary' => '#ff6b35',         // 오렌지 (강조)
        'secondary_light' => '#fff5f0',
        
        // 텍스트 컬러
        'text_primary' => '#333333',      // 기본 텍스트
        'text_secondary' => '#666666',    // 보조 텍스트
        'text_muted' => '#999999',        // 흐린 텍스트
        'text_white' => '#ffffff',
        
        // 배경 컬러
        'bg_white' => '#ffffff',
        'bg_light' => '#f8f8f8',
        'bg_gray' => '#eeeeee',
        
        // 테두리 컬러
        'border' => '#eeeeee',
        'border_dark' => '#dddddd',
        
        // 상태 컬러
        'success' => '#10b981',
        'warning' => '#f59e0b',
        'error' => '#ef4444',
        'info' => '#3b82f6',
        
        // 별점 컬러
        'star' => '#fbbf24',
        
        // 카카오톡
        'kakao' => '#fee500',
        'kakao_text' => '#000000',
    ],

    // ========================================
    // 폰트 설정
    // ========================================
    'fonts' => [
        // 폰트 패밀리
        'family_primary' => "'Pretendard', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
        'family_secondary' => "'Noto Sans KR', sans-serif",
        
        // 폰트 사이즈
        'size_xs' => '11px',
        'size_sm' => '12px',
        'size_base' => '14px',
        'size_md' => '16px',
        'size_lg' => '18px',
        'size_xl' => '20px',
        'size_2xl' => '24px',
        'size_3xl' => '28px',
        
        // 폰트 굵기
        'weight_normal' => '400',
        'weight_medium' => '500',
        'weight_semibold' => '600',
        'weight_bold' => '700',
        
        // 줄 높이
        'line_height_tight' => '1.25',
        'line_height_normal' => '1.5',
        'line_height_relaxed' => '1.75',
    ],

    // ========================================
    // 버튼 설정
    // ========================================
    'buttons' => [
        // 기본 버튼
        'primary' => [
            'bg' => '#7c3aed',
            'text' => '#ffffff',
            'border' => '#7c3aed',
            'hover_bg' => '#5b21b6',
            'border_radius' => '8px',
            'padding' => '12px 24px',
            'font_size' => '16px',
            'font_weight' => '600',
        ],
        
        // 보조 버튼 (outline)
        'secondary' => [
            'bg' => '#ffffff',
            'text' => '#7c3aed',
            'border' => '#7c3aed',
            'hover_bg' => '#f3e8ff',
            'border_radius' => '8px',
            'padding' => '12px 24px',
            'font_size' => '16px',
            'font_weight' => '600',
        ],
        
        // 아웃라인 버튼
        'outline' => [
            'bg' => '#ffffff',
            'text' => '#333333',
            'border' => '#eeeeee',
            'hover_bg' => '#f8f8f8',
            'border_radius' => '6px',
            'padding' => '8px 16px',
            'font_size' => '13px',
            'font_weight' => '500',
        ],
        
        // 카카오 상담 버튼
        'kakao' => [
            'bg' => '#fee500',
            'text' => '#000000',
            'border' => '#fee500',
            'border_radius' => '20px',
            'padding' => '6px 12px',
            'font_size' => '12px',
            'font_weight' => '600',
        ],
        
        // 탭 버튼 (활성화)
        'tab_active' => [
            'bg' => '#7c3aed',
            'text' => '#ffffff',
            'border_radius' => '20px',
            'padding' => '8px 16px',
            'font_size' => '14px',
            'font_weight' => '500',
        ],
        
        // 탭 버튼 (비활성화)
        'tab_inactive' => [
            'bg' => 'transparent',
            'text' => '#666666',
            'border_radius' => '20px',
            'padding' => '8px 16px',
            'font_size' => '14px',
            'font_weight' => '400',
        ],
    ],

    // ========================================
    // 카드 설정
    // ========================================
    'cards' => [
        'border_radius' => '12px',
        'shadow' => '0 2px 8px rgba(0,0,0,0.08)',
        'padding' => '16px',
        'bg' => '#ffffff',
    ],

    // ========================================
    // 레이아웃 설정
    // ========================================
    'layout' => [
        'max_width' => '1200px',
        'mobile_max_width' => '640px',
        'header_height' => '60px',
        'bottom_bar_height' => '70px',
        'sidebar_width' => '280px',
        'content_padding' => '20px',
        'section_gap' => '24px',
    ],

    // ========================================
    // 메뉴 설정
    // ========================================
    'menu' => [
        ['name' => '홈', 'url' => '/', 'icon' => 'home'],
        ['name' => '후기', 'url' => '/reviews', 'icon' => 'review'],
        ['name' => '이벤트', 'url' => '/events', 'icon' => 'event'],
        ['name' => '의사/병원', 'url' => '/hospitals', 'icon' => 'hospital'],
        ['name' => '커뮤니티', 'url' => '/community', 'icon' => 'community'],
        ['name' => '뷰티랩', 'url' => '/beauty-lab', 'icon' => 'lab'],
    ],

    // ========================================
    // 기타 설정
    // ========================================
    'misc' => [
        'items_per_page' => 20,
        'search_min_length' => 2,
        'image_lazy_load' => true,
        'enable_kakao_chat' => true,
    ],
];
```

### config/database.php - DB 연결 설정

```php
<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        // 환경변수 또는 설정 파일에서 로드
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->db_name = getenv('DB_NAME') ?: 'kivicare_db';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = getenv('DB_PASS') ?: '';
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch(PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            throw new Exception("데이터베이스 연결 실패");
        }
        return $this->conn;
    }
}
```

### includes/functions.php - 설정 로드 함수

```php
<?php
/**
 * 전역 설정 로드
 */
function getConfig($key = null) {
    static $config = null;
    
    if ($config === null) {
        $config = require __DIR__ . '/../config/settings.php';
    }
    
    if ($key === null) {
        return $config;
    }
    
    // 점 표기법 지원 (예: 'colors.primary')
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return null;
        }
        $value = $value[$k];
    }
    
    return $value;
}

/**
 * CSS 변수 생성
 */
function generateCssVariables() {
    $config = getConfig();
    $css = ":root {\n";
    
    // 색상 변수
    foreach ($config['colors'] as $name => $value) {
        $css .= "    --color-{$name}: {$value};\n";
    }
    
    // 폰트 변수
    foreach ($config['fonts'] as $name => $value) {
        $css .= "    --font-{$name}: {$value};\n";
    }
    
    // 레이아웃 변수
    foreach ($config['layout'] as $name => $value) {
        $css .= "    --layout-{$name}: {$value};\n";
    }
    
    $css .= "}\n";
    
    return $css;
}

/**
 * 숫자 포맷
 */
function formatNumber($num) {
    return number_format($num);
}

/**
 * 가격 포맷
 */
function formatPrice($price) {
    return number_format($price) . '원';
}

/**
 * 시간 경과 표시
 */
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . '년 전';
    if ($diff->m > 0) return $diff->m . '개월 전';
    if ($diff->d > 0) return $diff->d . '일 전';
    if ($diff->h > 0) return $diff->h . '시간 전';
    if ($diff->i > 0) return $diff->i . '분 전';
    return '방금 전';
}
```

---

## 🔝 공통 헤더 모듈

### includes/header.php - 모든 페이지에서 사용하는 공통 헤더

```php
<?php
require_once __DIR__ . '/functions.php';
$config = getConfig();
$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = $isLoggedIn ? $_SESSION['user'] : null;
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo $config['site']['name']; ?></title>
    <link rel="icon" href="<?php echo $config['site']['favicon']; ?>">
    
    <!-- Pretendard 폰트 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/static/pretendard.css">
    
    <!-- 동적 CSS 변수 -->
    <style>
        <?php echo generateCssVariables(); ?>
    </style>
    
    <!-- 공통 스타일 -->
    <link rel="stylesheet" href="/assets/css/common.css">
    <link rel="stylesheet" href="/assets/css/header.css">
    
    <!-- 페이지별 스타일 -->
    <?php if (isset($pageStyles)): ?>
        <?php foreach ($pageStyles as $style): ?>
        <link rel="stylesheet" href="<?php echo $style; ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <!-- ========================================
         공통 헤더 (모든 페이지 상단)
    ======================================== -->
    <header class="main-header">
        <div class="header-container">
            <!-- 로고 -->
            <a href="/" class="logo">
                <img src="<?php echo $config['site']['logo_url']; ?>" alt="<?php echo $config['site']['name']; ?>">
                <span class="logo-text"><?php echo $config['site']['logo_text']; ?></span>
            </a>
            
            <!-- 메인 네비게이션 (PC) -->
            <nav class="main-nav">
                <?php foreach ($config['menu'] as $menu): ?>
                <a href="<?php echo $menu['url']; ?>" 
                   class="nav-item <?php echo strpos($_SERVER['REQUEST_URI'], $menu['url']) === 0 && $menu['url'] !== '/' ? 'active' : ''; ?>
                          <?php echo $_SERVER['REQUEST_URI'] === '/' && $menu['url'] === '/' ? 'active' : ''; ?>">
                    <?php echo $menu['name']; ?>
                </a>
                <?php endforeach; ?>
            </nav>
            
            <!-- 우측 액션 버튼들 -->
            <div class="header-actions">
                <!-- 검색 버튼 -->
                <button class="search-btn" onclick="openSearchModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
                        <path d="M16 16L20 20" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                
                <?php if ($isLoggedIn): ?>
                <!-- 로그인 상태 -->
                <div class="user-menu">
                    <button class="user-btn" onclick="toggleUserDropdown()">
                        <img src="<?php echo $currentUser['avatar'] ?? '/assets/images/default-avatar.png'; ?>" 
                             alt="프로필" class="user-avatar">
                    </button>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="/mypage">마이페이지</a>
                        <a href="/mypage/consultations">내 상담</a>
                        <a href="/mypage/reviews">내 후기</a>
                        <a href="/mypage/likes">찜 목록</a>
                        <hr>
                        <a href="/logout">로그아웃</a>
                    </div>
                </div>
                <?php else: ?>
                <!-- 비로그인 상태 -->
                <a href="/login" class="login-btn">
                    로그인 및 회원가입
                </a>
                <?php endif; ?>
            </div>
            
            <!-- 모바일 메뉴 버튼 -->
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <span></span>
                <span></span>
                <span></span>
            </button>
        </div>
    </header>
    
    <!-- 모바일 네비게이션 -->
    <nav class="mobile-nav" id="mobileNav">
        <div class="mobile-nav-header">
            <?php if ($isLoggedIn): ?>
            <div class="mobile-user-info">
                <img src="<?php echo $currentUser['avatar'] ?? '/assets/images/default-avatar.png'; ?>" alt="프로필">
                <span><?php echo htmlspecialchars($currentUser['name']); ?>님</span>
            </div>
            <?php else: ?>
            <a href="/login" class="mobile-login-btn">로그인 및 회원가입</a>
            <?php endif; ?>
        </div>
        <div class="mobile-nav-menu">
            <?php foreach ($config['menu'] as $menu): ?>
            <a href="<?php echo $menu['url']; ?>" class="mobile-nav-item">
                <?php echo $menu['name']; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </nav>
    
    <!-- 검색 모달 -->
    <div class="search-modal" id="searchModal">
        <div class="search-modal-content">
            <div class="search-modal-header">
                <button class="back-btn" onclick="closeSearchModal()">
                    <svg width="24" height="24" viewBox="0 0 24 24">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" fill="none"/>
                    </svg>
                </button>
                <div class="search-input-wrapper">
                    <input type="text" id="searchInput" placeholder="질문, 고민 등 무엇이든 검색해 보세요." 
                           oninput="handleSearchInput(this.value)">
                    <button class="search-submit-btn" onclick="submitSearch()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/>
                            <path d="M16 16L20 20" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- 추천 검색어 태그 -->
            <div class="search-tags" id="searchTags">
                <div class="tags-scroll">
                    <!-- DB에서 동적 로드 -->
                </div>
            </div>
            
            <!-- 인기 검색어 -->
            <div class="popular-search" id="popularSearch">
                <div class="popular-header">
                    <h3>인기 검색어</h3>
                    <span class="update-time" id="updateTime">6:00 기준</span>
                </div>
                
                <!-- 카테고리 탭 -->
                <div class="popular-category-tabs">
                    <button class="cat-tab active" data-category="plastic">성형</button>
                    <button class="cat-tab" data-category="skin">쁘띠/피부</button>
                </div>
                
                <!-- 서브 탭 -->
                <div class="popular-sub-tabs">
                    <button class="sub-tab active" data-type="all">전체</button>
                    <button class="sub-tab" data-type="treatment">시술</button>
                    <button class="sub-tab" data-type="concern">뷰티고민</button>
                    <button class="sub-tab" data-type="hospital">병원</button>
                    <button class="sub-tab" data-type="doctor">의사</button>
                </div>
                
                <!-- 인기 검색어 리스트 -->
                <ol class="popular-list" id="popularList">
                    <!-- DB에서 동적 로드 -->
                </ol>
            </div>
            
            <!-- 검색 결과 (입력 시 표시) -->
            <div class="search-results" id="searchResults" style="display: none;">
                <!-- 동적 로드 -->
            </div>
        </div>
    </div>
    
    <!-- 헤더 높이만큼 여백 -->
    <div class="header-spacer"></div>
    
    <!-- 메인 컨텐츠 시작 -->
    <main class="main-content">
```

### includes/footer.php - 공통 푸터

```php
    </main>
    <!-- 메인 컨텐츠 끝 -->
    
    <!-- 푸터 -->
    <footer class="main-footer">
        <div class="footer-container">
            <div class="footer-logo">
                <img src="<?php echo $config['site']['logo_url']; ?>" alt="<?php echo $config['site']['name']; ?>">
            </div>
            <div class="footer-info">
                <p>주식회사 <?php echo $config['site']['name']; ?></p>
                <p>대표이사: 홍길동 | 사업자등록번호: 000-00-00000</p>
                <p>주소: 서울특별시 강남구 테헤란로 123</p>
                <p>이메일: contact@example.com</p>
            </div>
            <div class="footer-links">
                <a href="/terms">이용약관</a>
                <a href="/privacy">개인정보처리방침</a>
                <a href="/contact">문의하기</a>
            </div>
            <p class="copyright">© <?php echo date('Y'); ?> <?php echo $config['site']['name']; ?>. All rights reserved.</p>
        </div>
    </footer>
    
    <!-- 공통 스크립트 -->
    <script src="/assets/js/common.js"></script>
    <script src="/assets/js/header.js"></script>
    
    <!-- 페이지별 스크립트 -->
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
        <script src="<?php echo $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
```

### assets/css/header.css - 헤더 스타일

```css
/* ========================================
   공통 헤더 스타일
======================================== */

.main-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: var(--layout-header_height);
    background: var(--color-bg_white);
    border-bottom: 1px solid var(--color-border);
    z-index: 1000;
}

.header-container {
    max-width: var(--layout-max_width);
    height: 100%;
    margin: 0 auto;
    padding: 0 var(--layout-content_padding);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* 로고 */
.logo {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.logo img {
    height: 32px;
}

.logo-text {
    font-size: var(--font-size_lg);
    font-weight: var(--font-weight_bold);
    color: var(--color-primary);
}

/* 메인 네비게이션 */
.main-nav {
    display: flex;
    align-items: center;
    gap: 32px;
}

.nav-item {
    font-size: var(--font-size_base);
    font-weight: var(--font-weight_medium);
    color: var(--color-text_secondary);
    text-decoration: none;
    padding: 8px 0;
    position: relative;
    transition: color 0.2s;
}

.nav-item:hover,
.nav-item.active {
    color: var(--color-text_primary);
}

.nav-item.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--color-primary);
}

/* 우측 액션 버튼 */
.header-actions {
    display: flex;
    align-items: center;
    gap: 16px;
}

.search-btn {
    width: 40px;
    height: 40px;
    border: none;
    background: var(--color-bg_light);
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text_secondary);
    transition: background 0.2s;
}

.search-btn:hover {
    background: var(--color-bg_gray);
}

.login-btn {
    padding: 10px 20px;
    background: var(--color-primary);
    color: var(--color-text_white);
    border-radius: 8px;
    font-size: var(--font-size_sm);
    font-weight: var(--font-weight_semibold);
    text-decoration: none;
    transition: background 0.2s;
}

.login-btn:hover {
    background: var(--color-primary_dark);
}

/* 사용자 메뉴 */
.user-menu {
    position: relative;
}

.user-btn {
    width: 40px;
    height: 40px;
    border: none;
    background: none;
    padding: 0;
    cursor: pointer;
}

.user-avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    object-fit: cover;
}

.user-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 8px;
    min-width: 160px;
    background: var(--color-bg_white);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    display: none;
}

.user-dropdown.show {
    display: block;
}

.user-dropdown a {
    display: block;
    padding: 12px 16px;
    color: var(--color-text_primary);
    text-decoration: none;
    font-size: var(--font-size_sm);
}

.user-dropdown a:hover {
    background: var(--color-bg_light);
}

.user-dropdown hr {
    margin: 0;
    border: none;
    border-top: 1px solid var(--color-border);
}

/* 모바일 메뉴 버튼 */
.mobile-menu-btn {
    display: none;
    flex-direction: column;
    gap: 5px;
    padding: 10px;
    border: none;
    background: none;
    cursor: pointer;
}

.mobile-menu-btn span {
    display: block;
    width: 20px;
    height: 2px;
    background: var(--color-text_primary);
    transition: all 0.3s;
}

/* 헤더 여백 */
.header-spacer {
    height: var(--layout-header_height);
}

/* ========================================
   검색 모달
======================================== */

.search-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--color-bg_white);
    z-index: 2000;
    display: none;
    flex-direction: column;
}

.search-modal.show {
    display: flex;
}

.search-modal-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px var(--layout-content_padding);
    border-bottom: 1px solid var(--color-border);
}

.search-modal .back-btn {
    width: 40px;
    height: 40px;
    border: none;
    background: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-input-wrapper {
    flex: 1;
    display: flex;
    align-items: center;
    background: var(--color-bg_light);
    border-radius: 24px;
    padding: 0 16px;
}

.search-input-wrapper input {
    flex: 1;
    border: none;
    background: none;
    padding: 12px 0;
    font-size: var(--font-size_base);
    outline: none;
}

.search-submit-btn {
    border: none;
    background: none;
    cursor: pointer;
    color: var(--color-text_muted);
}

.search-modal-content {
    flex: 1;
    overflow-y: auto;
    padding: var(--layout-content_padding);
}

/* 추천 검색어 태그 */
.search-tags {
    margin-bottom: 24px;
}

.tags-scroll {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.search-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 8px 14px;
    background: var(--color-bg_light);
    border-radius: 20px;
    font-size: var(--font-size_sm);
    color: var(--color-text_secondary);
    text-decoration: none;
    transition: all 0.2s;
}

.search-tag:hover {
    background: var(--color-primary_light);
    color: var(--color-primary);
}

.search-tag .tag-icon {
    color: var(--color-primary);
}

/* 인기 검색어 */
.popular-search {
    
}

.popular-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.popular-header h3 {
    font-size: var(--font-size_lg);
    font-weight: var(--font-weight_semibold);
}

.update-time {
    font-size: var(--font-size_sm);
    color: var(--color-text_muted);
}

/* 카테고리 탭 */
.popular-category-tabs {
    display: flex;
    border: 1px solid var(--color-border);
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 16px;
}

.cat-tab {
    flex: 1;
    padding: 12px;
    border: none;
    background: var(--color-bg_white);
    font-size: var(--font-size_base);
    cursor: pointer;
    transition: all 0.2s;
}

.cat-tab.active {
    background: var(--color-text_primary);
    color: var(--color-text_white);
}

/* 서브 탭 */
.popular-sub-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    border-bottom: 1px solid var(--color-border);
    padding-bottom: 12px;
}

.sub-tab {
    padding: 8px 16px;
    border: none;
    background: none;
    font-size: var(--font-size_base);
    color: var(--color-text_muted);
    cursor: pointer;
    position: relative;
}

.sub-tab.active {
    color: var(--color-text_primary);
    font-weight: var(--font-weight_semibold);
}

.sub-tab.active::after {
    content: '';
    position: absolute;
    bottom: -13px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--color-primary);
}

/* 인기 검색어 리스트 */
.popular-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.popular-item {
    display: flex;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--color-border);
}

.popular-item:last-child {
    border-bottom: none;
}

.rank-number {
    width: 24px;
    font-size: var(--font-size_base);
    font-weight: var(--font-weight_semibold);
    color: var(--color-text_primary);
}

.rank-info {
    flex: 1;
    margin-left: 16px;
}

.rank-keyword {
    font-size: var(--font-size_base);
    font-weight: var(--font-weight_medium);
    display: block;
}

.rank-hospital {
    font-size: var(--font-size_sm);
    color: var(--color-text_muted);
}

.rank-change {
    font-size: var(--font-size_sm);
    font-weight: var(--font-weight_medium);
}

.rank-change.up {
    color: #ef4444;
}

.rank-change.down {
    color: #3b82f6;
}

.rank-change.same {
    color: var(--color-text_muted);
}

/* ========================================
   반응형
======================================== */

@media (max-width: 768px) {
    .main-nav {
        display: none;
    }
    
    .login-btn {
        display: none;
    }
    
    .mobile-menu-btn {
        display: flex;
    }
    
    .mobile-nav {
        position: fixed;
        top: var(--layout-header_height);
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--color-bg_white);
        z-index: 999;
        display: none;
        flex-direction: column;
    }
    
    .mobile-nav.show {
        display: flex;
    }
    
    .mobile-nav-header {
        padding: 20px;
        border-bottom: 1px solid var(--color-border);
    }
    
    .mobile-login-btn {
        display: block;
        width: 100%;
        padding: 14px;
        background: var(--color-primary);
        color: var(--color-text_white);
        border-radius: 8px;
        text-align: center;
        text-decoration: none;
        font-weight: var(--font-weight_semibold);
    }
    
    .mobile-nav-item {
        display: block;
        padding: 16px 20px;
        color: var(--color-text_primary);
        text-decoration: none;
        font-size: var(--font-size_md);
        border-bottom: 1px solid var(--color-border);
    }
}
```

### assets/js/header.js - 헤더 스크립트

```javascript
// 검색 모달
function openSearchModal() {
    document.getElementById('searchModal').classList.add('show');
    document.getElementById('searchInput').focus();
    loadRecommendedTags();
    loadPopularSearches();
    document.body.style.overflow = 'hidden';
}

function closeSearchModal() {
    document.getElementById('searchModal').classList.remove('show');
    document.body.style.overflow = '';
}

// 추천 검색어 로드
async function loadRecommendedTags() {
    try {
        const response = await fetch('/api/search/recommended-tags.php');
        const tags = await response.json();
        
        const container = document.querySelector('.tags-scroll');
        container.innerHTML = tags.map(tag => `
            <a href="/search?q=${encodeURIComponent(tag.keyword)}" class="search-tag">
                <span class="tag-icon">✦</span>
                ${escapeHtml(tag.keyword)}
            </a>
        `).join('');
    } catch (error) {
        console.error('추천 검색어 로드 실패:', error);
    }
}

// 인기 검색어 로드
async function loadPopularSearches(category = 'plastic', type = 'all') {
    try {
        const response = await fetch(`/api/search/popular.php?category=${category}&type=${type}`);
        const data = await response.json();
        
        document.getElementById('updateTime').textContent = data.update_time + ' 기준';
        
        const list = document.getElementById('popularList');
        list.innerHTML = data.items.map((item, index) => `
            <li class="popular-item">
                <span class="rank-number">${index + 1}</span>
                <div class="rank-info">
                    <a href="/search?q=${encodeURIComponent(item.keyword)}" class="rank-keyword">
                        ${escapeHtml(item.keyword)}
                    </a>
                    ${item.hospital ? `<span class="rank-hospital">${escapeHtml(item.hospital)}</span>` : ''}
                </div>
                <span class="rank-change ${item.change > 0 ? 'up' : item.change < 0 ? 'down' : 'same'}">
                    ${item.change > 0 ? '▲' + item.change : item.change < 0 ? '▼' + Math.abs(item.change) : '-'}
                </span>
            </li>
        `).join('');
    } catch (error) {
        console.error('인기 검색어 로드 실패:', error);
    }
}

// 탭 이벤트
document.querySelectorAll('.cat-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.cat-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        loadPopularSearches(this.dataset.category, document.querySelector('.sub-tab.active').dataset.type);
    });
});

document.querySelectorAll('.sub-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.sub-tab').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        loadPopularSearches(document.querySelector('.cat-tab.active').dataset.category, this.dataset.type);
    });
});

// 검색 입력 핸들러
let searchTimeout;
function handleSearchInput(value) {
    clearTimeout(searchTimeout);
    
    if (value.length < 2) {
        document.getElementById('searchResults').style.display = 'none';
        document.getElementById('popularSearch').style.display = 'block';
        return;
    }
    
    searchTimeout = setTimeout(() => {
        performSearch(value);
    }, 300);
}

async function performSearch(query) {
    try {
        const response = await fetch(`/api/search/autocomplete.php?q=${encodeURIComponent(query)}`);
        const results = await response.json();
        
        document.getElementById('popularSearch').style.display = 'none';
        document.getElementById('searchResults').style.display = 'block';
        
        // 검색 결과 렌더링
        renderSearchResults(results);
    } catch (error) {
        console.error('검색 실패:', error);
    }
}

function submitSearch() {
    const query = document.getElementById('searchInput').value.trim();
    if (query.length >= 2) {
        location.href = '/search?q=' + encodeURIComponent(query);
    }
}

// 사용자 드롭다운
function toggleUserDropdown() {
    document.getElementById('userDropdown').classList.toggle('show');
}

// 모바일 메뉴
function toggleMobileMenu() {
    document.getElementById('mobileNav').classList.toggle('show');
    document.querySelector('.mobile-menu-btn').classList.toggle('active');
}

// 유틸리티
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// 클릭 외부 닫기
document.addEventListener('click', function(e) {
    // 사용자 드롭다운
    if (!e.target.closest('.user-menu')) {
        document.getElementById('userDropdown')?.classList.remove('show');
    }
});

// ESC 키로 모달 닫기
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeSearchModal();
    }
});
```

---

## 📋 탭별 페이지 구현

### 1. 이벤트 탭 (tabs/events.php)

```php
<?php
/**
 * 이벤트 탭
 * - 정렬: 인기순, 후기 많은순, 저가순, 고가순, 별점 높은순, 최신순
 * - 이벤트 리스트 카드
 */

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'popular';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// 정렬 옵션
$sortOptions = [
    'popular' => '인기순',
    'review' => '후기 많은순',
    'low_price' => '저가순',
    'high_price' => '고가순',
    'rating' => '별점 높은순',
    'latest' => '최신순'
];

// 이벤트 목록 조회
$eventModel = new Event($db);
$events = $eventModel->getEventsByClinic($clinic_id, $sort, $category, $page);
$totalEvents = $eventModel->getEventCount($clinic_id, $category);
?>

<section class="events-tab">
    <!-- 정렬 드롭다운 -->
    <div class="sort-filter">
        <div class="sort-dropdown">
            <button class="sort-btn" onclick="toggleSortDropdown()">
                <span class="sort-icon">↕</span>
                <span id="currentSort"><?php echo $sortOptions[$sort]; ?></span>
            </button>
            <div class="sort-options" id="sortOptions">
                <?php foreach ($sortOptions as $key => $label): ?>
                <button class="sort-option <?php echo $sort === $key ? 'active' : ''; ?>" 
                        onclick="changeSort('<?php echo $key; ?>')">
                    <?php echo $label; ?>
                    <?php if ($sort === $key): ?><span class="check">✓</span><?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- 이벤트 리스트 -->
    <div class="event-list">
        <?php foreach ($events as $event): ?>
        <article class="event-card">
            <a href="/event/<?php echo $event['id']; ?>" class="event-card-link">
                <!-- 썸네일 -->
                <div class="event-thumbnail">
                    <img src="<?php echo htmlspecialchars($event['thumbnail_url']); ?>" 
                         alt="<?php echo htmlspecialchars($event['title']); ?>"
                         loading="lazy">
                    <?php if ($event['badge']): ?>
                    <span class="event-badge"><?php echo htmlspecialchars($event['badge']); ?></span>
                    <?php endif; ?>
                    <?php if ($event['discount_rate']): ?>
                    <span class="discount-badge"><?php echo $event['discount_rate']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- 정보 -->
                <div class="event-info">
                    <h3 class="event-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                    <p class="event-tags"><?php echo htmlspecialchars($event['tags']); ?></p>
                    <p class="event-location">
                        <?php echo htmlspecialchars($event['region']); ?> | 
                        <?php echo htmlspecialchars($event['clinic_name']); ?>
                    </p>
                    
                    <div class="event-price-row">
                        <span class="event-price"><?php echo formatPrice($event['min_price']); ?></span>
                        <span class="discount-rate"><?php echo $event['discount_rate']; ?>%</span>
                        <span class="vat-info">VAT 포함</span>
                    </div>
                    
                    <div class="event-rating">
                        <span class="star">★</span>
                        <span class="rating"><?php echo number_format($event['rating'], 1); ?></span>
                        <span class="review-count">(<?php echo formatNumber($event['review_count']); ?>)</span>
                    </div>
                </div>
            </a>
            
            <!-- 카카오 상담 버튼 -->
            <button class="kakao-chat-btn" onclick="openKakaoChat(<?php echo $event['clinic_id']; ?>)">
                <span class="kakao-icon">💬</span> 카톡상담
            </button>
        </article>
        <?php endforeach; ?>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($totalEvents > getConfig('misc.items_per_page')): ?>
    <div class="pagination">
        <?php
        $totalPages = ceil($totalEvents / getConfig('misc.items_per_page'));
        for ($i = 1; $i <= $totalPages; $i++):
        ?>
        <a href="?id=<?php echo $clinic_id; ?>&tab=events&sort=<?php echo $sort; ?>&page=<?php echo $i; ?>" 
           class="page-btn <?php echo $page === $i ? 'active' : ''; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</section>

<!-- 상담 신청 배너 -->
<div class="consultation-banner">
    <span class="banner-icon">✨</span>
    <span class="banner-text">
        <strong><?php echo formatNumber($clinic['consultation_count']); ?>명</strong>이 상담 신청한 병원이에요!
    </span>
</div>
```

### 2. 후기 탭 (tabs/reviews.php)

```php
<?php
/**
 * 후기 탭
 * - 필터: 인기순, 부위/시술, 뷰티고민
 * - Before/After 이미지
 * - 연관 이벤트
 */

$reviewSort = isset($_GET['review_sort']) ? $_GET['review_sort'] : 'popular';
$treatmentFilter = isset($_GET['treatment']) ? (int)$_GET['treatment'] : 0;

$reviewModel = new Review($db);
$reviews = $reviewModel->getReviewsByClinic($clinic_id, $reviewSort, $treatmentFilter, $page);
?>

<section class="reviews-tab">
    <!-- 필터 버튼들 -->
    <div class="review-filters">
        <button class="filter-btn <?php echo $reviewSort === 'popular' ? 'active' : ''; ?>"
                onclick="setReviewFilter('popular')">
            <span class="filter-icon">↕</span> 인기순
        </button>
        <button class="filter-btn" onclick="openTreatmentFilter()">
            <span class="filter-icon">😊</span> 부위/시술
        </button>
        <button class="filter-btn" onclick="openConcernFilter()">
            <span class="filter-icon">❓</span> 뷰티고민
        </button>
    </div>

    <!-- 후기 리스트 -->
    <div class="review-list">
        <?php foreach ($reviews as $review): ?>
        <article class="review-card">
            <!-- 작성자 정보 -->
            <div class="review-header">
                <img src="<?php echo $review['user_avatar'] ?: '/assets/images/default-avatar.png'; ?>" 
                     alt="" class="reviewer-avatar">
                <span class="reviewer-name"><?php echo htmlspecialchars($review['user_nickname']); ?></span>
                <span class="review-time"><?php echo timeAgo($review['created_at']); ?></span>
            </div>
            
            <!-- Before/After 이미지 -->
            <div class="review-images">
                <?php 
                $images = json_decode($review['images'], true) ?: [];
                foreach ($images as $img): 
                ?>
                <div class="review-image-item <?php echo $img['is_locked'] ? 'locked' : ''; ?>">
                    <span class="ba-label"><?php echo $img['type'] === 'before' ? '전' : '후'; ?></span>
                    <?php if ($img['is_locked']): ?>
                    <div class="lock-overlay">
                        <span class="lock-icon">🔒</span>
                    </div>
                    <img src="<?php echo $img['blur_url']; ?>" alt="">
                    <?php else: ?>
                    <img src="<?php echo $img['url']; ?>" alt="">
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- 연관 이벤트 -->
            <?php if ($review['event_id']): ?>
            <a href="/event/<?php echo $review['event_id']; ?>" class="related-event">
                <img src="<?php echo $review['event_thumbnail']; ?>" alt="" class="event-thumb">
                <div class="event-info">
                    <span class="event-name"><?php echo htmlspecialchars($review['event_title']); ?></span>
                    <span class="event-price"><?php echo formatPrice($review['event_price']); ?> VAT 포함</span>
                </div>
                <span class="arrow">›</span>
            </a>
            <?php endif; ?>
            
            <!-- 시술 태그 -->
            <div class="treatment-tags">
                <?php 
                $treatments = explode(',', $review['treatments']);
                foreach ($treatments as $treatment): 
                ?>
                <span class="treatment-tag"><?php echo htmlspecialchars(trim($treatment)); ?></span>
                <?php endforeach; ?>
            </div>
            
            <!-- 별점 -->
            <div class="review-rating">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <span class="star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>">★</span>
                <?php endfor; ?>
            </div>
            
            <!-- 후기 내용 -->
            <div class="review-content">
                <p class="review-text">
                    <?php 
                    $text = htmlspecialchars($review['content']);
                    if (strlen($text) > 200) {
                        echo substr($text, 0, 200) . '...';
                    } else {
                        echo $text;
                    }
                    ?>
                </p>
                <?php if (strlen($review['content']) > 200): ?>
                <button class="more-btn" onclick="showFullReview(<?php echo $review['id']; ?>)">..더보기</button>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
</section>
```

### 3. 의사 탭 (tabs/doctors.php)

```php
<?php
/**
 * 의사 탭
 * - 의사 목록
 * - 프로필, 전문분야, 시술후기 수, 상담 수
 */

$doctorModel = new Doctor($db);
$doctors = $doctorModel->getDoctorsByClinic($clinic_id);
?>

<section class="doctors-tab">
    <div class="doctor-list">
        <?php foreach ($doctors as $doctor): ?>
        <article class="doctor-card">
            <a href="/doctor/<?php echo $doctor['id']; ?>" class="doctor-card-link">
                <!-- 프로필 사진 -->
                <div class="doctor-photo">
                    <img src="<?php echo $doctor['photo_url'] ?: '/assets/images/default-doctor.png'; ?>" 
                         alt="<?php echo htmlspecialchars($doctor['name']); ?>">
                </div>
                
                <!-- 의사 정보 -->
                <div class="doctor-info">
                    <h3 class="doctor-name">
                        <?php echo htmlspecialchars($doctor['name']); ?> 
                        <span class="doctor-position"><?php echo htmlspecialchars($doctor['position']); ?></span>
                    </h3>
                    <p class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                    <p class="doctor-clinic"><?php echo htmlspecialchars($doctor['clinic_name']); ?></p>
                    
                    <!-- 시술 카테고리 태그 -->
                    <div class="doctor-categories">
                        <?php 
                        $categories = explode(',', $doctor['categories']);
                        foreach (array_slice($categories, 0, 3) as $cat): 
                        ?>
                        <span class="category-tag"><?php echo htmlspecialchars(trim($cat)); ?></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- 통계 -->
                    <div class="doctor-stats">
                        <span class="stat">시술후기 <strong><?php echo formatNumber($doctor['review_count']); ?></strong></span>
                        <span class="divider">|</span>
                        <span class="stat">상담 <strong><?php echo formatNumber($doctor['consultation_count']); ?></strong></span>
                    </div>
                </div>
            </a>
            
            <!-- 의사 상담 버튼 -->
            <button class="doctor-consult-btn" onclick="consultDoctor(<?php echo $doctor['id']; ?>)">
                <span class="btn-icon">👤</span> 의사상담
            </button>
        </article>
        <?php endforeach; ?>
    </div>
</section>
```

### 4. 유튜브 탭 (tabs/youtube.php)

```php
<?php
/**
 * 유튜브 탭
 * - 유튜브 영상 그리드
 */

$youtubeModel = new Youtube($db);
$videos = $youtubeModel->getVideosByClinic($clinic_id);
?>

<section class="youtube-tab">
    <div class="youtube-grid">
        <?php foreach ($videos as $video): ?>
        <article class="youtube-card">
            <a href="<?php echo $video['video_url']; ?>" target="_blank" class="youtube-link" 
               onclick="trackYoutubeClick(<?php echo $video['id']; ?>)">
                <!-- 썸네일 -->
                <div class="youtube-thumbnail">
                    <img src="<?php echo $video['thumbnail_url']; ?>" 
                         alt="<?php echo htmlspecialchars($video['title']); ?>"
                         loading="lazy">
                    <div class="play-overlay">
                        <span class="play-icon">▶</span>
                    </div>
                    <?php if ($video['duration']): ?>
                    <span class="video-duration"><?php echo $video['duration']; ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- 영상 정보 -->
                <div class="youtube-info">
                    <h3 class="youtube-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                    <p class="youtube-description"><?php echo htmlspecialchars($video['description']); ?></p>
                </div>
            </a>
        </article>
        <?php endforeach; ?>
    </div>
</section>
```

---

## 🗄️ 데이터베이스 테이블 (추가)

```sql
-- =============================================
-- 검색 관련 테이블
-- =============================================

-- 추천 검색어
CREATE TABLE IF NOT EXISTS `recommended_keywords` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `keyword` VARCHAR(100) NOT NULL,
    `category` ENUM('plastic', 'skin') DEFAULT 'plastic',
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='추천 검색어';

-- 인기 검색어
CREATE TABLE IF NOT EXISTS `popular_keywords` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `keyword` VARCHAR(100) NOT NULL,
    `category` ENUM('plastic', 'skin') DEFAULT 'plastic',
    `type` ENUM('all', 'treatment', 'concern', 'hospital', 'doctor') DEFAULT 'all',
    `hospital_name` VARCHAR(100) COMMENT '관련 병원명',
    `search_count` INT DEFAULT 0,
    `rank` INT DEFAULT 0,
    `prev_rank` INT DEFAULT 0,
    `rank_change` INT DEFAULT 0 COMMENT '순위 변동 (+상승, -하락, 0유지)',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_category_type_rank` (`category`, `type`, `rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='인기 검색어';

-- 검색 로그
CREATE TABLE IF NOT EXISTS `search_logs` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `keyword` VARCHAR(200) NOT NULL,
    `results_count` INT DEFAULT 0,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_keyword` (`keyword`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='검색 로그';

-- =============================================
-- 의사 테이블
-- =============================================
CREATE TABLE IF NOT EXISTS `doctors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `clinic_id` INT NOT NULL,
    `name` VARCHAR(50) NOT NULL COMMENT '이름',
    `position` VARCHAR(50) COMMENT '직위 (원장, 부원장 등)',
    `specialty` VARCHAR(100) COMMENT '전문분야 (성형외과 전문의)',
    `photo_url` VARCHAR(500) COMMENT '프로필 사진',
    `introduction` TEXT COMMENT '소개',
    `categories` VARCHAR(255) COMMENT '시술 카테고리 (쉼표 구분)',
    `review_count` INT DEFAULT 0 COMMENT '시술후기 수',
    `consultation_count` INT DEFAULT 0 COMMENT '상담 수',
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_clinic` (`clinic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='의사';

-- =============================================
-- 유튜브 영상 테이블
-- =============================================
CREATE TABLE IF NOT EXISTS `clinic_youtube` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `clinic_id` INT NOT NULL,
    `video_id` VARCHAR(20) COMMENT 'YouTube Video ID',
    `video_url` VARCHAR(500) NOT NULL,
    `thumbnail_url` VARCHAR(500),
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT,
    `duration` VARCHAR(10) COMMENT '재생시간 (mm:ss)',
    `view_count` INT DEFAULT 0,
    `click_count` INT DEFAULT 0 COMMENT '클릭 수',
    `published_at` DATETIME,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_clinic` (`clinic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='병원 유튜브 영상';

-- =============================================
-- 후기 테이블
-- =============================================
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `clinic_id` INT NOT NULL,
    `doctor_id` INT,
    `event_id` INT,
    `user_id` INT NOT NULL,
    `rating` TINYINT DEFAULT 5 COMMENT '별점 (1-5)',
    `content` TEXT COMMENT '후기 내용',
    `treatments` VARCHAR(255) COMMENT '시술 목록 (쉼표 구분)',
    `images` JSON COMMENT '이미지 배열 [{type, url, blur_url, is_locked}]',
    `like_count` INT DEFAULT 0,
    `comment_count` INT DEFAULT 0,
    `is_verified` TINYINT(1) DEFAULT 0 COMMENT '영수증 인증 여부',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_clinic` (`clinic_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='후기';

-- =============================================
-- 상담 신청 테이블
-- =============================================
CREATE TABLE IF NOT EXISTS `consultations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `clinic_id` INT NOT NULL,
    `doctor_id` INT,
    `event_id` INT,
    `user_id` INT NOT NULL,
    `consultation_type` ENUM('hospital', 'doctor') DEFAULT 'hospital',
    `name` VARCHAR(50),
    `phone` VARCHAR(20),
    `message` TEXT,
    `status` ENUM('pending', 'contacted', 'completed', 'cancelled') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_clinic` (`clinic_id`),
    INDEX `idx_user` (`user_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='상담 신청';
```

---

## 📁 최종 파일 구조

```
/project-root/
├── config/
│   ├── settings.php           # 전역 설정 (색상, 폰트, 버튼 등)
│   └── database.php           # DB 연결
├── includes/
│   ├── header.php             # 공통 헤더 모듈 ⭐
│   ├── footer.php             # 공통 푸터
│   └── functions.php          # 유틸리티 함수
├── models/
│   ├── Clinic.php
│   ├── Event.php
│   ├── Review.php
│   ├── Doctor.php
│   ├── Youtube.php
│   └── Search.php
├── pages/
│   ├── clinic/
│   │   ├── index.php          # 병원 상세 메인
│   │   └── tabs/
│   │       ├── home.php       # 홈 탭
│   │       ├── events.php     # 이벤트 탭
│   │       ├── reviews.php    # 후기 탭
│   │       ├── doctors.php    # 의사 탭
│   │       └── youtube.php    # 유튜브 탭
│   ├── event/
│   │   └── detail.php         # 이벤트 상세
│   ├── search/
│   │   └── index.php          # 검색 결과 페이지
│   └── index.php              # 메인 홈
├── api/
│   ├── search/
│   │   ├── recommended-tags.php
│   │   ├── popular.php
│   │   └── autocomplete.php
│   ├── consultation.php
│   └── ...
├── assets/
│   ├── css/
│   │   ├── common.css         # 공통 스타일
│   │   ├── header.css         # 헤더 스타일
│   │   ├── clinic-detail.css
│   │   ├── events-tab.css
│   │   ├── reviews-tab.css
│   │   ├── doctors-tab.css
│   │   └── youtube-tab.css
│   ├── js/
│   │   ├── common.js
│   │   ├── header.js          # 헤더/검색 스크립트
│   │   ├── clinic-detail.js
│   │   └── ...
│   └── images/
│       ├── logo.svg
│       ├── icons/
│       └── ...
└── admin/
    ├── settings.php           # 전역 설정 관리 UI
    ├── clinics/
    ├── events/
    ├── doctors/
    └── ...
```

---

## ✅ 작업 체크리스트

### 전역 설정 (Config)
- [ ] config/settings.php 생성 (색상, 폰트, 버튼, 레이아웃)
- [ ] CSS 변수 자동 생성 함수 구현
- [ ] 관리자에서 설정 변경 UI (선택사항)

### 공통 헤더 모듈
- [ ] includes/header.php 구현
- [ ] 로고 + 메인 네비게이션
- [ ] 로그인/비로그인 상태 처리
- [ ] 검색 버튼 + 검색 모달
- [ ] 모바일 반응형 메뉴

### 검색 기능
- [ ] 검색 모달 UI
- [ ] 추천 검색어 태그
- [ ] 인기 검색어 (성형/쁘띠피부, 전체/시술/뷰티고민/병원/의사)
- [ ] 순위 변동 표시 (▲▼-)
- [ ] 자동완성 API
- [ ] 검색 로그 저장

### 탭 페이지
- [ ] 이벤트 탭 (정렬 드롭다운, 카드 리스트, 카톡상담 버튼)
- [ ] 후기 탭 (필터, Before/After 이미지, 잠금 처리, 연관 이벤트)
- [ ] 의사 탭 (프로필, 전문분야, 통계, 의사상담 버튼)
- [ ] 유튜브 탭 (썸네일 그리드, 재생 버튼 오버레이)

### 하단 고정 바
- [ ] 상담 신청 배너 (N명이 상담 신청한 병원이에요!)
- [ ] 병원 상담신청 버튼 (흰색/outline)
- [ ] 의사 상담신청 버튼 (보라색/primary)

### DB 테이블
- [ ] recommended_keywords (추천 검색어)
- [ ] popular_keywords (인기 검색어)
- [ ] search_logs (검색 로그)
- [ ] doctors (의사)
- [ ] clinic_youtube (유튜브 영상)
- [ ] reviews (후기)
- [ ] consultations (상담 신청)

---

## 📌 핵심 포인트

1. **전역 설정**: 모든 색상, 폰트, 버튼 스타일은 `config/settings.php`에서 중앙 관리
2. **공통 헤더**: `includes/header.php` 하나로 모든 페이지에서 동일한 헤더 사용
3. **CSS 변수**: PHP에서 설정을 읽어 `:root` CSS 변수로 자동 생성
4. **검색 모달**: 전체 화면 모달, 추천/인기 검색어, 실시간 자동완성
5. **탭 구조**: 병원 상세 페이지 내 탭으로 이벤트/후기/의사/유튜브 구분
