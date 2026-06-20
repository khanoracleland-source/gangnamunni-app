<?php
/**
 * SNS/앱스토어 심사 현황 패널
 * dashboard.php에서 include
 * 필요 변수: $sns_reviews (배열), $sns_stats (배열)
 */
if (!isset($sns_reviews)) return;
?>
<!-- SNS 심사 관리 대시보드 -->
<div class="card mb-4 border-0" style="border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,0.08);">
    <div class="card-header py-3" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border:none;">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0 text-white fw-bold"><i class="bi bi-phone me-2"></i>SNS / 앱스토어 심사 현황</h6>
            <button class="btn btn-sm btn-light" onclick="document.getElementById('snsAddForm').classList.toggle('d-none')">
                <i class="bi bi-plus-lg me-1"></i>신규 등록
            </button>
        </div>
    </div>
    <div class="card-body p-3">
        <!-- 상태 요약 뱃지 -->
        <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="badge bg-secondary px-3 py-2">전체 <?php echo $sns_stats['total']; ?></span>
            <?php if ($sns_stats['draft'] > 0): ?><span class="badge bg-light text-dark px-3 py-2">준비 <?php echo $sns_stats['draft']; ?></span><?php endif; ?>
            <?php if ($sns_stats['submitted'] > 0): ?><span class="badge bg-info px-3 py-2">제출됨 <?php echo $sns_stats['submitted']; ?></span><?php endif; ?>
            <?php if ($sns_stats['in_review'] > 0): ?><span class="badge bg-warning text-dark px-3 py-2">심사중 <?php echo $sns_stats['in_review']; ?></span><?php endif; ?>
            <?php if ($sns_stats['approved'] > 0): ?><span class="badge bg-success px-3 py-2">승인 <?php echo $sns_stats['approved']; ?></span><?php endif; ?>
            <?php if ($sns_stats['rejected'] > 0): ?><span class="badge bg-danger px-3 py-2">거절 <?php echo $sns_stats['rejected']; ?></span><?php endif; ?>
            <?php if ($sns_stats['live'] > 0): ?><span class="badge bg-primary px-3 py-2">라이브 <?php echo $sns_stats['live']; ?></span><?php endif; ?>
        </div>

        <!-- 신규 등록 폼 (접힘) -->
        <div id="snsAddForm" class="d-none mb-3 p-3 rounded" style="background:var(--bs-tertiary-bg);">
            <div class="row g-2">
                <div class="col-md-3">
                    <select id="sns_platform" class="form-select form-select-sm">
                        <option value="google_play">Google Play</option>
                        <option value="app_store">App Store</option>
                        <option value="instagram">Instagram</option>
                        <option value="facebook">Facebook</option>
                        <option value="youtube">YouTube</option>
                        <option value="tiktok">TikTok</option>
                        <option value="naver">네이버</option>
                        <option value="kakao">카카오</option>
                        <option value="other">기타</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select id="sns_app_type" class="form-select form-select-sm">
                        <option value="patient">환자앱</option>
                        <option value="admin">관리자앱</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" id="sns_title" class="form-control form-control-sm" placeholder="제목 (예: v1.0.0 최초 출시)">
                </div>
                <div class="col-md-2">
                    <input type="text" id="sns_version" class="form-control form-control-sm" placeholder="버전">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm w-100" onclick="snsCreate()">등록</button>
                </div>
            </div>
            <div class="row g-2 mt-1">
                <div class="col-md-8">
                    <input type="text" id="sns_notes" class="form-control form-control-sm" placeholder="메모">
                </div>
                <div class="col-md-4">
                    <input type="text" id="sns_store_url" class="form-control form-control-sm" placeholder="스토어 URL">
                </div>
            </div>
        </div>

        <!-- 심사 항목 리스트 -->
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 align-middle" style="font-size:0.85rem;">
                <thead>
                    <tr class="text-body-secondary">
                        <th style="width:30px;">#</th>
                        <th>플랫폼</th>
                        <th>앱</th>
                        <th>제목</th>
                        <th>버전</th>
                        <th>상태</th>
                        <th>제출일</th>
                        <th>메모</th>
                        <th style="width:160px;">액션</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $platform_icons = [
                    'google_play' => '<i class="bi bi-google text-success"></i> Play',
                    'app_store'   => '<i class="bi bi-apple"></i> iOS',
                    'instagram'   => '<i class="bi bi-instagram text-danger"></i> Insta',
                    'facebook'    => '<i class="bi bi-facebook text-primary"></i> FB',
                    'youtube'     => '<i class="bi bi-youtube text-danger"></i> YT',
                    'tiktok'      => '<i class="bi bi-tiktok"></i> TikTok',
                    'naver'       => '<span style="color:#03C75A;font-weight:bold;">N</span> 네이버',
                    'kakao'       => '<i class="bi bi-chat-fill text-warning"></i> 카카오',
                    'other'       => '<i class="bi bi-three-dots"></i> 기타',
                ];
                $status_labels = [
                    'draft'     => ['준비', 'bg-light text-dark'],
                    'submitted' => ['제출됨', 'bg-info'],
                    'in_review' => ['심사중', 'bg-warning text-dark'],
                    'approved'  => ['승인', 'bg-success'],
                    'rejected'  => ['거절', 'bg-danger'],
                    'live'      => ['라이브', 'bg-primary'],
                    'suspended' => ['정지', 'bg-dark'],
                ];
                $status_options = ['draft'=>'준비','submitted'=>'제출','in_review'=>'심사중','approved'=>'승인','rejected'=>'거절','live'=>'라이브','suspended'=>'정지'];

                foreach ($sns_reviews as $i => $sr):
                    $icon = $platform_icons[$sr['platform']] ?? $sr['platform'];
                    $st = $status_labels[$sr['status']] ?? [$sr['status'], 'bg-secondary'];
                ?>
                    <tr id="sns-row-<?php echo $sr['id']; ?>">
                        <td class="text-body-secondary"><?php echo $i+1; ?></td>
                        <td><?php echo $icon; ?></td>
                        <td><span class="badge <?php echo $sr['app_type']==='admin' ? 'bg-dark' : 'bg-info'; ?>"><?php echo $sr['app_type']==='admin' ? '관리자' : '환자'; ?></span></td>
                        <td class="fw-semibold"><?php echo htmlspecialchars($sr['title']); ?></td>
                        <td><?php echo htmlspecialchars($sr['version'] ?: '-'); ?></td>
                        <td><span class="badge <?php echo $st[1]; ?>"><?php echo $st[0]; ?></span></td>
                        <td class="text-body-secondary"><?php echo $sr['submit_date'] ? date('m/d', strtotime($sr['submit_date'])) : '-'; ?></td>
                        <td class="text-body-secondary small"><?php echo htmlspecialchars(mb_substr($sr['notes'] ?? '', 0, 30)); ?></td>
                        <td>
                            <div class="d-flex gap-1 align-items-center">
                                <select class="form-select form-select-sm" style="font-size:0.75rem;width:85px;" onchange="snsUpdateStatus(<?php echo $sr['id']; ?>, this.value)">
                                    <?php foreach ($status_options as $k => $v): ?>
                                        <option value="<?php echo $k; ?>" <?php echo $sr['status']===$k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn btn-outline-danger btn-sm px-1" onclick="snsDelete(<?php echo $sr['id']; ?>)" title="삭제"><i class="bi bi-trash"></i></button>
                                <?php if ($sr['store_url']): ?>
                                    <a href="<?php echo htmlspecialchars($sr['store_url']); ?>" target="_blank" class="btn btn-outline-primary btn-sm px-1" title="스토어"><i class="bi bi-box-arrow-up-right"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php if ($sr['status'] === 'rejected' && $sr['reject_reason']): ?>
                    <tr>
                        <td></td>
                        <td colspan="8" class="py-1"><small class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>거절 사유: <?php echo htmlspecialchars($sr['reject_reason']); ?></small></td>
                    </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if (empty($sns_reviews)): ?>
                    <tr><td colspan="9" class="text-center text-body-secondary py-3">등록된 심사 항목이 없습니다.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function snsCreate() {
    const data = new FormData();
    data.append('action', 'create');
    ['platform','app_type','title','version','notes','store_url'].forEach(f => {
        const el = document.getElementById('sns_' + f);
        if (el) data.append(f, el.value);
    });
    if (!document.getElementById('sns_title').value) { alert('제목을 입력해주세요.'); return; }
    fetch('api/sns_reviews.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => { if (d.success) location.reload(); else alert(d.error || '오류'); });
}
function snsUpdateStatus(id, status) {
    const data = new FormData();
    data.append('action', 'update_status');
    data.append('id', id);
    data.append('status', status);
    fetch('api/sns_reviews.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => { if (!d.success) alert('오류'); });
}
function snsDelete(id) {
    if (!confirm('삭제하시겠습니까?')) return;
    const data = new FormData();
    data.append('action', 'delete');
    data.append('id', id);
    fetch('api/sns_reviews.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(d => { if (d.success) document.getElementById('sns-row-'+id)?.remove(); });
}
</script>