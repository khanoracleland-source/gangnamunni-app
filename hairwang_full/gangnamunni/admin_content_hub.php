<?php
/**
 * ============================================================================
 * [관리자 #18 - 2026-05-28] 후기/칼럼/커뮤니티 통합 관리 화면
 * ============================================================================
 * 목적 : 흩어져 있는 콘텐츠 관리(후기/칼럼/커뮤니티) 한 화면에서 통합 조회/이동
 * 신규 : 본 파일 (admin_content_hub.php)
 *        - admin_reviews.php / admin_columns.php / admin_community_posts.php 는 그대로 보존
 *        - 통합 뷰는 각 개별 페이지로 링크하는 허브 역할
 * 영향 범위 : 신규 페이지만 추가. 기존 페이지 영향 없음.
 * ============================================================================
 */
session_start();
$admin_page_title = '콘텐츠 통합 관리';
$admin_current = 'content_hub';
require_once __DIR__ . '/../dbconfig.php';
require_once __DIR__ . '/theme_helper.php';
require_once __DIR__ . '/includes/admin_auth.php';
$connect = mysqli_connect(G5_MYSQL_HOST, G5_MYSQL_USER, G5_MYSQL_PASSWORD, G5_MYSQL_DB, G5_MYSQL_PORT);
if (!$connect) die('DB 연결 실패');
mysqli_set_charset($connect, 'utf8mb4');
loadThemeSettings($connect);
require_admin_login();
require_permission('content');

// 통계
$stats = ['reviews' => 0, 'columns' => 0, 'community' => 0, 'tips' => 0];
$r1 = mysqli_query($connect, "SELECT COUNT(*) c FROM hospital_reviews WHERE is_active=1");
if ($r1) $stats['reviews'] = (int)mysqli_fetch_assoc($r1)['c'];
$r2 = @mysqli_query($connect, "SELECT COUNT(*) c FROM gangnamunni_columns WHERE is_active=1");
if ($r2) $stats['columns'] = (int)mysqli_fetch_assoc($r2)['c'];
$r3 = @mysqli_query($connect, "SELECT COUNT(*) c FROM gangnamunni_community_posts WHERE status='active'");
if ($r3) $stats['community'] = (int)mysqli_fetch_assoc($r3)['c'];
$r4 = @mysqli_query($connect, "SELECT COUNT(*) c FROM gangnamunni_tips WHERE is_active=1");
if ($r4) $stats['tips'] = (int)mysqli_fetch_assoc($r4)['c'];

// 최신 콘텐츠 (각 5개씩 unified)
$latest = [];
$rr = mysqli_query($connect, "SELECT id, content as title, created_at, '후기' as type, hospital_id FROM hospital_reviews ORDER BY created_at DESC LIMIT 5");
if ($rr) while ($r = mysqli_fetch_assoc($rr)) $latest[] = $r;
$rc = @mysqli_query($connect, "SELECT id, title, created_at, '칼럼' as type, NULL as hospital_id FROM gangnamunni_columns ORDER BY created_at DESC LIMIT 5");
if ($rc) while ($r = mysqli_fetch_assoc($rc)) $latest[] = $r;
$rcom = @mysqli_query($connect, "SELECT id, title, created_at, '커뮤니티' as type, NULL as hospital_id FROM gangnamunni_community_posts WHERE status='active' ORDER BY created_at DESC LIMIT 5");
if ($rcom) while ($r = mysqli_fetch_assoc($rcom)) $latest[] = $r;
usort($latest, function($a,$b){ return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });

include __DIR__ . '/includes/admin_header.php';
?>
<div class="container-fluid py-3">
    <h4 class="mb-3"><i class="bi bi-collection-fill"></i> 콘텐츠 통합 관리</h4>
    <div class="alert alert-info py-2 small">후기 · 칼럼 · 커뮤니티 · 꿀팁을 한 화면에서 통합 조회. 각 카드 클릭 시 상세 관리 페이지로 이동합니다.</div>

    <!-- 통계 카드 -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <a href="admin_reviews.php" class="text-decoration-none">
                <div class="card text-white bg-primary"><div class="card-body">
                    <h6>📝 후기 (hospital_reviews)</h6>
                    <h2><?= $stats['reviews'] ?></h2>
                    <small>총 활성 후기 → 관리하기</small>
                </div></div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="admin_columns.php" class="text-decoration-none">
                <div class="card text-white bg-success"><div class="card-body">
                    <h6>📰 칼럼/뉴스</h6>
                    <h2><?= $stats['columns'] ?></h2>
                    <small>총 활성 칼럼 → 관리하기</small>
                </div></div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="admin_community_posts.php" class="text-decoration-none">
                <div class="card text-white bg-warning"><div class="card-body">
                    <h6>💬 커뮤니티</h6>
                    <h2><?= $stats['community'] ?></h2>
                    <small>총 활성 게시글 → 관리하기</small>
                </div></div>
            </a>
        </div>
        <div class="col-md-3">
            <a href="admin_tips.php" class="text-decoration-none">
                <div class="card text-white" style="background:#6610f2;"><div class="card-body">
                    <h6>💡 꿀팁</h6>
                    <h2><?= $stats['tips'] ?></h2>
                    <small>총 활성 꿀팁 → 관리하기</small>
                </div></div>
            </a>
        </div>
    </div>

    <!-- 최신 콘텐츠 통합 -->
    <div class="card">
        <div class="card-header py-2"><strong>📋 최신 콘텐츠 통합 (전체 타입)</strong></div>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>타입</th><th>ID</th><th>제목/내용</th><th>등록일</th><th>작업</th></tr></thead>
                <tbody>
                    <?php foreach (array_slice($latest, 0, 20) as $l): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($l['type']) ?></span></td>
                        <td><?= $l['id'] ?></td>
                        <td><?= htmlspecialchars(mb_substr($l['title'] ?? '', 0, 60)) ?>...</td>
                        <td class="small"><?= $l['created_at'] ?></td>
                        <td>
                            <?php
                            $url = '#';
                            if ($l['type'] === '후기') $url = 'admin_reviews.php?search=' . $l['id'];
                            elseif ($l['type'] === '칼럼') $url = 'admin_columns.php?id=' . $l['id'];
                            elseif ($l['type'] === '커뮤니티') $url = 'admin_community_posts.php?id=' . $l['id'];
                            ?>
                            <a href="<?= $url ?>" class="btn btn-sm btn-outline-primary">관리</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php
mysqli_close($connect);
include __DIR__ . '/includes/admin_footer.php';
?>
