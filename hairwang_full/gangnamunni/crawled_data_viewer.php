<?php
/**
 * 크롤링 데이터 통합 뷰어
 */

require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';

$connect = @mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);

if (!$connect) {
    die("MySQL 연결 실패: " . mysqli_connect_error());
}

mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);

// 통계 데이터 가져오기
$stats = [];

// 사이트별 통계
$sites = [];
$result = mysqli_query($connect, "SELECT * FROM crawled_sites ORDER BY id");
while ($row = mysqli_fetch_assoc($result)) {
    $sites[] = $row;
}

// 전체 통계
$stats['total_hospitals'] = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_hospitals"))['cnt'];
$stats['total_doctors'] = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_doctors"))['cnt'];
$stats['total_events'] = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_events"))['cnt'];
$stats['total_tips'] = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_tips"))['cnt'];

// 사이트별 카운트
foreach ($sites as &$site) {
    $site['hospitals_count'] = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_hospitals WHERE site_id = {$site['id']}"))['cnt'];
    $site['doctors_count'] = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_doctors WHERE site_id = {$site['id']}"))['cnt'];
    $site['events_count'] = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_events WHERE site_id = {$site['id']}"))['cnt'];
    $site['tips_count'] = mysqli_fetch_assoc(mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_tips WHERE site_id = {$site['id']}"))['cnt'];
}

// 데이터 타입 선택
$data_type = isset($_GET['type']) ? $_GET['type'] : 'overview';
$site_filter = isset($_GET['site']) ? intval($_GET['site']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 페이지네이션
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>크롤링 데이터 뷰어 | <?php echo getTheme('site_name'); ?></title>
    <?php echo generateFaviconLinks(); ?>
    <?php echo generateGoogleFontsLink(); ?>
    <?php echo generateThemeCSS(); ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            color: #000000;
        }

        /* 헤더 */
        .header {
            background: #FFFFFF;
            border-bottom: 1px solid #E0E0E0;
            padding: 16px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-title {
            font-size: 20px;
            font-weight: bold;
        }

        .back-btn {
            padding: 8px 16px;
            background: #f5f5f5;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }

        /* 컨테이너 */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        /* 통계 카드 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #AE1932;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
        }

        /* 사이트 카드 */
        .sites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .site-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .site-header {
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f5f5f5;
        }

        .site-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .site-url {
            font-size: 12px;
            color: #999;
        }

        .site-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .site-stat-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 12px;
            background: #f9f9f9;
            border-radius: 6px;
        }

        .site-stat-label {
            font-size: 13px;
            color: #666;
        }

        .site-stat-value {
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }

        /* 필터 바 */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filter-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-select, .filter-input {
            padding: 10px 14px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 14px;
        }

        .filter-btn {
            padding: 10px 20px;
            background: #AE1932;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .filter-btn:hover {
            background: #E04A0D;
        }

        .tab-buttons {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: white;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: #AE1932;
            color: white;
            border-color: #AE1932;
        }

        /* 데이터 테이블 */
        .data-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f5f5f5;
            font-size: 13px;
        }

        th {
            background: #f9f9f9;
            font-weight: 600;
            color: #333;
        }

        tr:hover {
            background: #fafafa;
        }

        .badge {
            padding: 4px 8px;
            background: #E8F5E9;
            color: #2E7D32;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        /* 페이지네이션 */
        .pagination {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-top: 20px;
        }

        .page-btn {
            padding: 8px 12px;
            background: white;
            border: 1px solid #E0E0E0;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .page-btn.active {
            background: #AE1932;
            color: white;
            border-color: #AE1932;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .sites-grid {
                grid-template-columns: 1fr;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <!-- 헤더 -->
    <div class="header">
        <div class="header-content">
            <div class="header-title">📊 크롤링 데이터 뷰어</div>
            <a href="index.php" class="back-btn">← 메인으로</a>
        </div>
    </div>

    <!-- 컨테이너 -->
    <div class="container">
        <?php if ($data_type == 'overview'): ?>
        <!-- 전체 통계 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_hospitals']); ?></div>
                <div class="stat-label">총 병원 데이터</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_doctors']); ?></div>
                <div class="stat-label">총 의사 데이터</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_events']); ?></div>
                <div class="stat-label">총 이벤트 데이터</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($stats['total_tips']); ?></div>
                <div class="stat-label">총 의료팁 데이터</div>
            </div>
        </div>

        <!-- 사이트별 정보 -->
        <h2 style="margin-bottom: 20px; font-size: 20px;">사이트별 데이터</h2>
        <div class="sites-grid">
            <?php foreach ($sites as $site): ?>
            <div class="site-card">
                <div class="site-header">
                    <div class="site-name"><?php echo htmlspecialchars($site['site_name']); ?></div>
                    <div class="site-url"><?php echo htmlspecialchars($site['site_url']); ?></div>
                </div>
                <div class="site-stats">
                    <div class="site-stat-item">
                        <span class="site-stat-label">병원</span>
                        <span class="site-stat-value"><?php echo number_format($site['hospitals_count']); ?>개</span>
                    </div>
                    <div class="site-stat-item">
                        <span class="site-stat-label">의사</span>
                        <span class="site-stat-value"><?php echo number_format($site['doctors_count']); ?>명</span>
                    </div>
                    <div class="site-stat-item">
                        <span class="site-stat-label">이벤트</span>
                        <span class="site-stat-value"><?php echo number_format($site['events_count']); ?>개</span>
                    </div>
                    <div class="site-stat-item">
                        <span class="site-stat-label">의료팁</span>
                        <span class="site-stat-value"><?php echo number_format($site['tips_count']); ?>개</span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 탭 버튼 -->
        <div class="tab-buttons">
            <button class="tab-btn <?php echo $data_type == 'overview' ? 'active' : ''; ?>" onclick="location.href='?type=overview'">📊 전체 현황</button>
            <button class="tab-btn <?php echo $data_type == 'hospitals' ? 'active' : ''; ?>" onclick="location.href='?type=hospitals'">🏥 병원 목록</button>
            <button class="tab-btn <?php echo $data_type == 'doctors' ? 'active' : ''; ?>" onclick="location.href='?type=doctors'">👨‍⚕️ 의사 목록</button>
            <button class="tab-btn <?php echo $data_type == 'events' ? 'active' : ''; ?>" onclick="location.href='?type=events'">🎁 이벤트 목록</button>
            <button class="tab-btn <?php echo $data_type == 'tips' ? 'active' : ''; ?>" onclick="location.href='?type=tips'">💡 의료팁 목록</button>
        </div>

        <?php if ($data_type != 'overview'): ?>
        <!-- 필터 바 -->
        <div class="filter-bar">
            <form method="GET" class="filter-row">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($data_type); ?>">
                <select name="site" class="filter-select">
                    <option value="0">전체 사이트</option>
                    <?php foreach ($sites as $site): ?>
                    <option value="<?php echo $site['id']; ?>" <?php echo $site_filter == $site['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($site['site_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search" class="filter-input" placeholder="검색어 입력" value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="filter-btn">검색</button>
            </form>
        </div>

        <?php
        // 데이터 조회
        $data = [];
        $total_count = 0;

        if ($data_type == 'hospitals') {
            $where = "1=1";
            if ($site_filter > 0) $where .= " AND h.site_id = {$site_filter}";
            if (!empty($search)) $where .= " AND h.hospital_name LIKE '%" . mysqli_real_escape_string($connect, $search) . "%'";

            $count_result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_hospitals h WHERE {$where}");
            $total_count = mysqli_fetch_assoc($count_result)['cnt'];

            $query = "SELECT h.*, s.site_name FROM crawled_hospitals h
                      LEFT JOIN crawled_sites s ON h.site_id = s.id
                      WHERE {$where}
                      ORDER BY h.id DESC
                      LIMIT {$per_page} OFFSET {$offset}";
            $result = mysqli_query($connect, $query);
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        } elseif ($data_type == 'doctors') {
            $where = "1=1";
            if ($site_filter > 0) $where .= " AND d.site_id = {$site_filter}";
            if (!empty($search)) $where .= " AND d.doctor_name LIKE '%" . mysqli_real_escape_string($connect, $search) . "%'";

            $count_result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_doctors d WHERE {$where}");
            $total_count = mysqli_fetch_assoc($count_result)['cnt'];

            $query = "SELECT d.*, s.site_name FROM crawled_doctors d
                      LEFT JOIN crawled_sites s ON d.site_id = s.id
                      WHERE {$where}
                      ORDER BY d.id DESC
                      LIMIT {$per_page} OFFSET {$offset}";
            $result = mysqli_query($connect, $query);
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        } elseif ($data_type == 'events') {
            $where = "1=1";
            if ($site_filter > 0) $where .= " AND e.site_id = {$site_filter}";
            if (!empty($search)) $where .= " AND e.event_name LIKE '%" . mysqli_real_escape_string($connect, $search) . "%'";

            $count_result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_events e WHERE {$where}");
            $total_count = mysqli_fetch_assoc($count_result)['cnt'];

            $query = "SELECT e.*, s.site_name FROM crawled_events e
                      LEFT JOIN crawled_sites s ON e.site_id = s.id
                      WHERE {$where}
                      ORDER BY e.id DESC
                      LIMIT {$per_page} OFFSET {$offset}";
            $result = mysqli_query($connect, $query);
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        } elseif ($data_type == 'tips') {
            $where = "1=1";
            if ($site_filter > 0) $where .= " AND t.site_id = {$site_filter}";
            if (!empty($search)) $where .= " AND t.title LIKE '%" . mysqli_real_escape_string($connect, $search) . "%'";

            $count_result = mysqli_query($connect, "SELECT COUNT(*) as cnt FROM crawled_tips t WHERE {$where}");
            $total_count = mysqli_fetch_assoc($count_result)['cnt'];

            $query = "SELECT t.*, s.site_name FROM crawled_tips t
                      LEFT JOIN crawled_sites s ON t.site_id = s.id
                      WHERE {$where}
                      ORDER BY t.id DESC
                      LIMIT {$per_page} OFFSET {$offset}";
            $result = mysqli_query($connect, $query);
            while ($row = mysqli_fetch_assoc($result)) {
                $data[] = $row;
            }
        }

        $total_pages = ceil($total_count / $per_page);
        ?>

        <!-- 데이터 테이블 -->
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>사이트</th>
                        <?php if ($data_type == 'hospitals'): ?>
                        <th>병원명</th>
                        <th>주소</th>
                        <th>리뷰 수</th>
                        <?php elseif ($data_type == 'doctors'): ?>
                        <th>의사명</th>
                        <th>소속 병원</th>
                        <th>전문 분야</th>
                        <?php elseif ($data_type == 'events'): ?>
                        <th>이벤트명</th>
                        <th>브랜드</th>
                        <th>가격</th>
                        <th>리뷰</th>
                        <?php elseif ($data_type == 'tips'): ?>
                        <th>제목</th>
                        <th>카테고리</th>
                        <th>조회수</th>
                        <?php endif; ?>
                        <th>파일 크기</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($data)): ?>
                        <?php foreach ($data as $row): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><span class="badge"><?php echo htmlspecialchars($row['site_name']); ?></span></td>
                            <?php if ($data_type == 'hospitals'): ?>
                            <td><strong><?php echo htmlspecialchars($row['hospital_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars(mb_substr($row['address'] ?? '', 0, 30)); ?></td>
                            <td><?php echo number_format($row['review_count']); ?></td>
                            <?php elseif ($data_type == 'doctors'): ?>
                            <td><strong><?php echo htmlspecialchars($row['doctor_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['hospital_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['specialty'] ?? '-'); ?></td>
                            <?php elseif ($data_type == 'events'): ?>
                            <td><strong><?php echo htmlspecialchars(mb_substr($row['event_name'], 0, 50)); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['brand_name'] ?? '-'); ?></td>
                            <td><?php echo $row['discount_price'] > 0 ? number_format($row['discount_price']) . '원' : '-'; ?></td>
                            <td><?php echo number_format($row['review_count']); ?></td>
                            <?php elseif ($data_type == 'tips'): ?>
                            <td><strong><?php echo htmlspecialchars(mb_substr($row['title'], 0, 50)); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['category'] ?? '-'); ?></td>
                            <td><?php echo number_format($row['views']); ?></td>
                            <?php endif; ?>
                            <td><?php echo number_format($row['file_size_kb']); ?>KB</td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 40px; color: #999;">데이터가 없습니다</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 페이지네이션 -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= min($total_pages, 10); $i++): ?>
            <a href="?type=<?php echo $data_type; ?>&site=<?php echo $site_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"
               class="page-btn <?php echo $page == $i ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            <?php if ($total_pages > 10): ?>
            <span class="page-btn">...</span>
            <a href="?type=<?php echo $data_type; ?>&site=<?php echo $site_filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $total_pages; ?>"
               class="page-btn">
                <?php echo $total_pages; ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
<?php mysqli_close($connect); ?>
