<?php
/**
 * SNS 로그인 & PG 연동 테스트 페이지
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// 기본 URL
$base_url = 'https://paikpc.aidoum.com/hairwang_dev_sns_shop_2026_01_18';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SNS 로그인 & PG 연동 테스트 페이지</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Malgun Gothic', Arial, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: white; text-align: center; margin-bottom: 30px; font-size: 32px; text-shadow: 2px 2px 4px rgba(0,0,0,0.2); }
        .section { background: white; border-radius: 10px; margin-bottom: 20px; padding: 25px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .section h2 { color: #333; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 3px solid #667eea; font-size: 24px; }
        .section h3 { color: #667eea; margin-top: 25px; margin-bottom: 15px; font-size: 18px; }
        .url-list { display: flex; flex-direction: column; gap: 12px; }
        .url-item { display: flex; align-items: center; justify-content: space-between; padding: 15px; background: #f8f9fa; border-radius: 8px; border-left: 4px solid #667eea; transition: all 0.3s; }
        .url-item:hover { background: #e9ecef; transform: translateX(5px); border-left-color: #764ba2; }
        .url-info { flex: 1; }
        .url-label { font-weight: bold; color: #333; font-size: 16px; margin-bottom: 5px; }
        .url-path { color: #666; font-size: 14px; font-family: monospace; }
        .btn { padding: 10px 25px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: bold; text-decoration: none; color: white; background: #667eea; transition: all 0.3s; margin-left: 15px; white-space: nowrap; }
        .btn:hover { background: #764ba2; transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .btn-primary { background: #667eea; }
        .btn-success { background: #28a745; }
        .btn-success:hover { background: #218838; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-warning:hover { background: #e0a800; }
        .btn-info { background: #17a2b8; }
        .btn-info:hover { background: #138496; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; margin-left: 10px; }
        .badge-admin { background: #dc3545; color: white; }
        .badge-test { background: #ffc107; color: #333; }
        .badge-api { background: #28a745; color: white; }
        .footer { text-align: center; color: white; margin-top: 30px; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 10px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 20px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-card .count { font-size: 32px; font-weight: bold; margin-bottom: 5px; }
        .stat-card .label { font-size: 14px; opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 SNS 로그인 & PG 연동 테스트 페이지</h1>

        <div class="section">
            <h2>📊 통계</h2>
            <div class="stats">
                <div class="stat-card">
                    <div class="count">4</div>
                    <div class="label">관리자 기본 메뉴</div>
                </div>
                <div class="stat-card">
                    <div class="count">4</div>
                    <div class="label">SNS 로그인 메뉴</div>
                </div>
                <div class="stat-card">
                    <div class="count">4</div>
                    <div class="label">PG 결제 메뉴</div>
                </div>
                <div class="stat-card">
                    <div class="count">2</div>
                    <div class="label">테스트 페이지</div>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>🎛️ 관리자 페이지</h2>
            
            <h3>기본 메뉴</h3>
            <div class="url-list">
                <?php
                $admin_urls = array(
                    array('label' => 'API 관리 홈', 'path' => '/adm/api_admin/index.php', 'badge' => 'admin'),
                    array('label' => 'API 키 관리', 'path' => '/adm/api_admin/keylist.php', 'badge' => 'admin'),
                    array('label' => 'IP 화이트리스트', 'path' => '/adm/api_admin/iplist.php', 'badge' => 'admin'),
                    array('label' => 'API 로그', 'path' => '/adm/api_admin/loglist.php', 'badge' => 'admin'),
                    array('label' => '필드 관리', 'path' => '/adm/api_admin/fieldlist.php', 'badge' => 'admin'),
                );

                foreach ($admin_urls as $url) {
                    $full_url = $base_url . $url['path'];
                    ?>
                    <div class="url-item">
                        <div class="url-info">
                            <div class="url-label"><?php echo htmlspecialchars($url['label']); ?> <span class="badge badge-<?php echo $url['badge']; ?>">관리자</span></div>
                            <div class="url-path"><?php echo htmlspecialchars($full_url); ?></div>
                        </div>
                        <a href="<?php echo htmlspecialchars($full_url); ?>" target="_blank" class="btn btn-primary">열기</a>
                    </div>
                    <?php
                }
                ?>
            </div>

            <h3>SNS 로그인 API 관리</h3>
            <div class="url-list">
                <?php
                $sns_urls = array(
                    array('label' => 'SNS 로그인 로그', 'path' => '/adm/api_admin/sns/loginlog.php', 'badge' => 'admin'),
                    array('label' => 'SNS 오류 로그', 'path' => '/adm/api_admin/sns/errorlog.php', 'badge' => 'admin'),
                    array('label' => 'SNS 통계', 'path' => '/adm/api_admin/sns/statistics.php', 'badge' => 'admin'),
                    array('label' => 'SNS 테스트', 'path' => '/adm/api_admin/sns_test.php', 'badge' => 'test'),
                );

                foreach ($sns_urls as $url) {
                    $full_url = $base_url . $url['path'];
                    ?>
                    <div class="url-item">
                        <div class="url-info">
                            <div class="url-label"><?php echo htmlspecialchars($url['label']); ?> <span class="badge badge-<?php echo $url['badge']; ?>"><?php echo $url['badge'] == 'test' ? '테스트' : '관리자'; ?></span></div>
                            <div class="url-path"><?php echo htmlspecialchars($full_url); ?></div>
                        </div>
                        <a href="<?php echo htmlspecialchars($full_url); ?>" target="_blank" class="btn btn-info">열기</a>
                    </div>
                    <?php
                }
                ?>
            </div>

            <h3>PG 결제 API 관리</h3>
            <div class="url-list">
                <?php
                $pg_urls = array(
                    array('label' => 'PG 주문 목록', 'path' => '/adm/api_admin/pg/orderlist.php', 'badge' => 'admin'),
                    array('label' => 'PG 결제 로그', 'path' => '/adm/api_admin/pg/paymentlog.php', 'badge' => 'admin'),
                    array('label' => 'PG 통계', 'path' => '/adm/api_admin/pg/statistics.php', 'badge' => 'admin'),
                    array('label' => 'PG 테스트', 'path' => '/adm/api_admin/pg_test.php', 'badge' => 'test'),
                );

                foreach ($pg_urls as $url) {
                    $full_url = $base_url . $url['path'];
                    ?>
                    <div class="url-item">
                        <div class="url-info">
                            <div class="url-label"><?php echo htmlspecialchars($url['label']); ?> <span class="badge badge-<?php echo $url['badge']; ?>"><?php echo $url['badge'] == 'test' ? '테스트' : '관리자'; ?></span></div>
                            <div class="url-path"><?php echo htmlspecialchars($full_url); ?></div>
                        </div>
                        <a href="<?php echo htmlspecialchars($full_url); ?>" target="_blank" class="btn btn-warning">열기</a>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <div class="section">
            <h2>🧪 테스트 페이지</h2>
            <div class="url-list">
                <?php
                $test_urls = array(
                    array('label' => 'SNS 로그인 테스트', 'path' => '/api/sns/test_login.php', 'description' => '외부 사이트용 SNS 로그인 테스트 페이지'),
                    array('label' => 'PG 결제 테스트', 'path' => '/api/pg/test_payment.php', 'description' => '외부 사이트용 PG 결제 테스트 페이지'),
                );

                foreach ($test_urls as $url) {
                    $full_url = $base_url . $url['path'];
                    ?>
                    <div class="url-item">
                        <div class="url-info">
                            <div class="url-label"><?php echo htmlspecialchars($url['label']); ?> <span class="badge badge-test">테스트</span></div>
                            <div class="url-path"><?php echo htmlspecialchars($full_url); ?></div>
                            <div style="color: #999; font-size: 13px; margin-top: 5px;"><?php echo htmlspecialchars($url['description']); ?></div>
                        </div>
                        <a href="<?php echo htmlspecialchars($full_url); ?>" target="_blank" class="btn btn-success">열기</a>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <div class="section">
            <h2>🔌 API 엔드포인트</h2>
            
            <h3>SNS 로그인 API</h3>
            <div class="url-list">
                <?php
                $sns_api_urls = array(
                    array('label' => '로그인', 'path' => '/api/sns/login.php', 'method' => 'POST'),
                    array('label' => '토큰', 'path' => '/api/sns/token.php', 'method' => 'POST'),
                    array('label' => '콜백', 'path' => '/api/sns/callback.php', 'method' => 'POST'),
                );

                foreach ($sns_api_urls as $url) {
                    $full_url = $base_url . $url['path'];
                    ?>
                    <div class="url-item">
                        <div class="url-info">
                            <div class="url-label"><?php echo htmlspecialchars($url['label']); ?> <span class="badge badge-api">API</span></div>
                            <div class="url-path"><?php echo htmlspecialchars($full_url); ?></div>
                        </div>
                        <a href="<?php echo htmlspecialchars($full_url); ?>" target="_blank" class="btn btn-info">열기</a>
                    </div>
                    <?php
                }
                ?>
            </div>

            <h3>PG 결제 API</h3>
            <div class="url-list">
                <?php
                $pg_api_urls = array(
                    array('label' => '상품', 'path' => '/api/pg/item.php', 'method' => 'GET/POST'),
                    array('label' => '장바구니', 'path' => '/api/pg/cart.php', 'method' => 'POST'),
                    array('label' => '주문', 'path' => '/api/pg/order.php', 'method' => 'POST'),
                    array('label' => '결제', 'path' => '/api/pg/payment.php', 'method' => 'POST'),
                    array('label' => '웹훅', 'path' => '/api/pg/webhook.php', 'method' => 'POST'),
                );

                foreach ($pg_api_urls as $url) {
                    $full_url = $base_url . $url['path'];
                    ?>
                    <div class="url-item">
                        <div class="url-info">
                            <div class="url-label"><?php echo htmlspecialchars($url['label']); ?> <span class="badge badge-api">API</span></div>
                            <div class="url-path"><?php echo htmlspecialchars($full_url); ?></div>
                        </div>
                        <a href="<?php echo htmlspecialchars($full_url); ?>" target="_blank" class="btn btn-warning">열기</a>
                    </div>
                    <?php
                }
                ?>
            </div>
        </div>

        <div class="footer">
            <p>📅 작성일: 2026-01-19 | 🌐 도메인: <?php echo htmlspecialchars($base_url); ?></p>
        </div>
    </div>
</body>
</html>
