<?php
/**
 * 앱 관리자 메인 대시보드
 * @package AddonAppAdmin
 * @version 1.0.0
 * @date 2026-01-19
 */

include_once('./_common.php');

// 레이아웃 헤더
$g5['title'] = '대시보드';
include_once('./layout_header.php');

// 통계 데이터 가져오기
$total_boards = 0;
$visible_boards = 0;
$total_media = 0;
$total_app_sources = 0;
$recent_logs = 0;

try {
    // 그누보드5의 실제 게시판 테이블에서 카운트
    $result = sql_fetch(" SELECT COUNT(*) as cnt FROM " . $g5['board_table'] . " ");
    $total_boards = $result ? $result['cnt'] : 0;

    // addon 설정이 있는 게시판 수 (표시 중)
    $result = sql_fetch(" SELECT COUNT(*) as cnt FROM {$addon_app_admin_table_boards} WHERE is_visible_pc = 1 OR is_visible_mobile = 1 ");
    $visible_boards = $result ? $result['cnt'] : $total_boards;

    $result = sql_fetch(" SELECT COUNT(*) as cnt FROM {$addon_app_admin_table_media} WHERE is_active = 1 ");
    $total_media = $result ? $result['cnt'] : 0;

    $result = sql_fetch(" SELECT COUNT(*) as cnt FROM {$addon_app_admin_table_app_sources} ");
    $total_app_sources = $result ? $result['cnt'] : 0;

    $result = sql_fetch(" SELECT COUNT(*) as cnt FROM {$addon_app_admin_table_logs} WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ");
    $recent_logs = $result ? $result['cnt'] : 0;
} catch (Exception $e) {
    error_log('[ADDON_APP_ADMIN] 통계 데이터 조회 오류: ' . $e->getMessage());
}

// 페이지 디버그 정보
$GLOBALS['page_debug'] = array(
    'page' => '대시보드',
    'stats' => array(
        'total_boards' => $total_boards,
        'visible_boards' => $visible_boards,
        'total_media' => $total_media,
        'total_app_sources' => $total_app_sources,
        'recent_logs' => $recent_logs
    ),
    'features' => array(
        'open_new_tab' => 'enabled with localStorage',
        'default_value' => true
    )
);

?>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.stat-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s;
}
.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.1);
}
.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
}
.stat-icon.primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.stat-icon.success { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.stat-icon.warning { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.stat-icon.info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.stat-content {
    flex: 1;
}
.stat-title {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}
.stat-value {
    font-size: 32px;
    font-weight: bold;
    color: #333;
    margin-bottom: 5px;
}
.stat-desc {
    font-size: 13px;
    color: #999;
}
.menu-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}
.menu-card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    text-align: center;
    transition: all 0.3s;
    position: relative;
    overflow: hidden;
}
.menu-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    transform: scaleX(0);
    transition: transform 0.3s;
}
.menu-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.1);
}
.menu-card:hover::before {
    transform: scaleX(1);
}
.menu-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f5f0ff 0%, #e8daff 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: #aa20ff;
}
.menu-title {
    font-size: 20px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}
.menu-desc {
    font-size: 14px;
    color: #666;
    margin-bottom: 20px;
    line-height: 1.6;
}
.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
}
.btn-primary {
    background: #aa20ff;
    color: white;
}
.btn-primary:hover {
    background: #8919d9;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(170, 32, 255, 0.3);
}
.recent-activity {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.activity-table {
    width: 100%;
    border-collapse: collapse;
}
.activity-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    color: #333;
    border-bottom: 2px solid #e0e0e0;
}
.activity-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    color: #666;
}
.activity-table tr:hover {
    background: #f8f9fa;
}
.new-tab-control {
    background: white;
    padding: 15px 25px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-bottom: 25px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.new-tab-control label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: #333;
    font-weight: 500;
    margin: 0;
}
.new-tab-control input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}
.new-tab-control i {
    color: #aa20ff;
    font-size: 16px;
}
</style>

<div class="admin-content-header">
    <h1 class="admin-content-title">대시보드</h1>
    <p class="admin-content-desc">
        <i class="fa fa-tachometer"></i>
        Hair Wang 앱 관리 시스템 개요
    </p>
</div>

<!-- 새 탭으로 열기 옵션 -->
<div class="new-tab-control">
    <i class="fa fa-external-link"></i>
    <label>
        <input type="checkbox" id="openInNewTab" checked>
        <span>링크를 새 탭에서 열기</span>
    </label>
</div>

<!-- 통계 카드 -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i class="fa fa-list"></i>
        </div>
        <div class="stat-content">
            <div class="stat-title">전체 게시판</div>
            <div class="stat-value"><?php echo $total_boards; ?></div>
            <div class="stat-desc">활성: <?php echo $visible_boards; ?>개</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon success">
            <i class="fa fa-image"></i>
        </div>
        <div class="stat-content">
            <div class="stat-title">미디어 파일</div>
            <div class="stat-value"><?php echo $total_media; ?></div>
            <div class="stat-desc">로고, 아이콘, 배너</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning">
            <i class="fa fa-mobile"></i>
        </div>
        <div class="stat-content">
            <div class="stat-title">앱 소스</div>
            <div class="stat-value"><?php echo $total_app_sources; ?></div>
            <div class="stat-desc">Android / iOS</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon info">
            <i class="fa fa-history"></i>
        </div>
        <div class="stat-content">
            <div class="stat-title">최근 활동</div>
            <div class="stat-value"><?php echo $recent_logs; ?></div>
            <div class="stat-desc">최근 7일</div>
        </div>
    </div>
</div>

<!-- 메뉴 그리드 -->
<div class="menu-grid">
    <div class="menu-card">
        <div class="menu-icon">
            <i class="fa fa-th-list"></i>
        </div>
        <h3 class="menu-title">게시판 관리</h3>
        <p class="menu-desc">게시판 이름, 아이콘, 표시 여부를 관리합니다</p>
        <a href="./boards.php" class="btn btn-primary">
            관리하기 <i class="fa fa-arrow-right"></i>
        </a>
    </div>

    <div class="menu-card">
        <div class="menu-icon">
            <i class="fa fa-header"></i>
        </div>
        <h3 class="menu-title">사이트 헤더</h3>
        <p class="menu-desc">상단 로고, 아이콘, 메뉴를 변경합니다</p>
        <a href="./header.php" class="btn btn-primary">
            설정하기 <i class="fa fa-arrow-right"></i>
        </a>
    </div>

    <div class="menu-card">
        <div class="menu-icon">
            <i class="fa fa-info-circle"></i>
        </div>
        <h3 class="menu-title">사이트 푸터</h3>
        <p class="menu-desc">하단 회사 정보, 링크를 관리합니다</p>
        <a href="./footer.php" class="btn btn-primary">
            관리하기 <i class="fa fa-arrow-right"></i>
        </a>
    </div>

    <div class="menu-card">
        <div class="menu-icon">
            <i class="fa fa-home"></i>
        </div>
        <h3 class="menu-title">메인 화면</h3>
        <p class="menu-desc">메인 화면 배너, 콘텐츠를 편집합니다</p>
        <a href="./main.php" class="btn btn-primary">
            편집하기 <i class="fa fa-arrow-right"></i>
        </a>
    </div>

    <div class="menu-card">
        <div class="menu-icon">
            <i class="fa fa-mobile"></i>
        </div>
        <h3 class="menu-title">앱 패키징</h3>
        <p class="menu-desc">Android/iOS 앱 이미지를 변경합니다</p>
        <a href="./app-package.php" class="btn btn-primary">
            시작하기 <i class="fa fa-arrow-right"></i>
        </a>
    </div>

    <div class="menu-card">
        <div class="menu-icon">
            <i class="fa fa-image"></i>
        </div>
        <h3 class="menu-title">미디어 관리</h3>
        <p class="menu-desc">업로드된 이미지, 파일을 관리합니다</p>
        <a href="./media.php" class="btn btn-primary">
            보기 <i class="fa fa-arrow-right"></i>
        </a>
    </div>

    <div class="menu-card">
        <div class="menu-icon">
            <i class="fa fa-cog"></i>
        </div>
        <h3 class="menu-title">사이트 설정</h3>
        <p class="menu-desc">전반적인 사이트 설정을 관리합니다</p>
        <a href="./config.php" class="btn btn-primary">
            설정하기 <i class="fa fa-arrow-right"></i>
        </a>
    </div>

    <div class="menu-card">
        <div class="menu-icon">
            <i class="fa fa-history"></i>
        </div>
        <h3 class="menu-title">활동 로그</h3>
        <p class="menu-desc">관리자 활동 기록을 확인합니다</p>
        <a href="./logs.php" class="btn btn-primary">
            확인하기 <i class="fa fa-arrow-right"></i>
        </a>
    </div>
</div>

<!-- 최근 활동 -->
<div class="recent-activity">
    <h2 style="font-size: 20px; color: #333; margin-bottom: 20px;">
        <i class="fa fa-clock-o"></i> 최근 활동
    </h2>
    <table class="activity-table">
        <thead>
            <tr>
                <th>시간</th>
                <th>사용자</th>
                <th>액션</th>
                <th>대상</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql = " SELECT * FROM {$addon_app_admin_table_logs} ORDER BY created_at DESC LIMIT 10 ";
            $result = sql_query($sql);

            if ($result && sql_num_rows($result) > 0) {
                while ($row = sql_fetch_array($result)) {
                    echo '<tr>';
                    echo '<td>' . date('Y-m-d H:i', strtotime($row['created_at'])) . '</td>';
                    echo '<td>' . htmlspecialchars($row['user_id']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['action_type']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['action_target']) . '</td>';
                    echo '<td>' . htmlspecialchars($row['ip_address']) . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="5" style="text-align: center; color: #999; padding: 40px;">활동 내역이 없습니다.</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>

<script>
// ============================================
// 새 탭으로 열기 기능
// ============================================
(function() {
    var checkbox = document.getElementById('openInNewTab');
    var storageKey = 'dashboard_open_new_tab';

    // localStorage에서 설정 불러오기 (기본값: true)
    function loadSetting() {
        var saved = localStorage.getItem(storageKey);
        // 저장된 값이 없으면 true (기본값)
        return saved === null ? true : saved === 'true';
    }

    // localStorage에 설정 저장
    function saveSetting(value) {
        localStorage.setItem(storageKey, value);
        console.log('%c💾 새 탭 설정 저장:', 'color: #4caf50; font-weight: bold;', value);
    }

    // 모든 링크에 target 속성 적용
    function applyTargetToLinks(openInNewTab) {
        // 메뉴 카드의 링크들
        var menuLinks = document.querySelectorAll('.menu-card a.btn-primary');

        menuLinks.forEach(function(link) {
            if (openInNewTab) {
                link.setAttribute('target', '_blank');
                link.setAttribute('rel', 'noopener noreferrer');
            } else {
                link.removeAttribute('target');
                link.removeAttribute('rel');
            }
        });

        console.log('%c🔗 링크 target 설정 완료:', 'color: #2196f3; font-weight: bold;',
                    openInNewTab ? 'target="_blank"' : 'target 제거',
                    '(' + menuLinks.length + '개 링크)');
    }

    // 초기 설정 적용
    var initialSetting = loadSetting();
    checkbox.checked = initialSetting;
    applyTargetToLinks(initialSetting);

    console.log('%c✅ 새 탭 설정 초기화:', 'color: #00bcd4; font-weight: bold;', initialSetting);

    // 체크박스 변경 이벤트
    checkbox.addEventListener('change', function() {
        var newValue = this.checked;
        saveSetting(newValue);
        applyTargetToLinks(newValue);
    });
})();
</script>

<?php
include_once('./layout_footer.php');
?>
