<?php
?>
<div id="bulkBar" class="alert alert-primary d-flex align-items-center gap-3 mb-2" style="display:none !important;">
    <span><strong id="bulkCount">0</strong>개 선택</span>
    <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()"><i class="bi bi-trash me-1"></i>선택 삭제</button>
    <button type="button" class="btn btn-success btn-sm" onclick="bulkApprove()"><i class="bi bi-check-lg me-1"></i>선택 승인</button>
    <button type="button" class="btn btn-warning btn-sm" onclick="bulkReject()"><i class="bi bi-x-lg me-1"></i>선택 반려</button>
    <button type="button" class="btn btn-outline-secondary btn-sm ms-auto" onclick="uncheckAll()"><i class="bi bi-x me-1"></i>선택 해제</button>
</div>

<div class="table-responsive">
    <table class="table table-hover table-bordered admin-table mb-0" id="hospitalTable">
        <thead class="table-light">
            <tr>
                <th style="width:36px;"><input type="checkbox" class="form-check-input" id="checkAll" title="전체 선택"></th>
                <th style="width:40px;">#</th>
                <th style="width:120px;">이름</th>
                <th style="width:140px;">주소</th>
                <th style="width:150px;">전화</th>
                <th style="min-width:200px; max-width:260px;">카테고리</th>
                <th style="width:70px;">상태</th>
                <th style="width:70px;">광고</th>
                <th style="width:60px;">활성</th>
                <th style="width:70px;">평점</th>
                <th style="width:90px;">등록일</th>
                <th style="width:90px;">관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($hospitals)): ?>
            <tr><td colspan="12" class="empty-state"><i class="bi bi-building"></i>등록된 병원이 없습니다.</td></tr>
            <?php else: ?>
            <?php foreach ($hospitals as $h): ?>
            <tr>
                <td class="text-center"><input type="checkbox" class="form-check-input row-check" value="<?php echo $h['id']; ?>"></td>
                <td class="text-center"><?php echo $h['id']; ?></td>
                <td>
                    <div class="cell-clamp-2" title="<?php echo htmlspecialchars($h['name']); ?>">
                        <a href="?view=<?php echo $h['id']; ?>&edit=1" class="text-decoration-none fw-semibold">
                            <?php echo htmlspecialchars($h['name']); ?>
                        </a>
                        <?php if (!empty($h['has_unreviewed_changes'])): ?><span class="badge bg-info ms-1" title="신청자가 정보를 수정함">수정됨</span><?php endif; ?>
                    </div>
                </td>
                <td class="small">
                    <div class="cell-clamp-2" title="<?php echo htmlspecialchars($h['address'] ?? ''); ?>">
                        <?php echo htmlspecialchars($h['address'] ?? '-'); ?>
                    </div>
                </td>
                <td class="small">
                    <div class="cell-clamp-2" title="<?php echo htmlspecialchars($h['phone'] ?? ''); ?>"><?php echo htmlspecialchars($h['phone'] ?? '-'); ?></div>
                </td>
                <td>
                    <?php
                    $cat_raw = $h['categories'] ?? '';
                    $cat_list = json_decode($cat_raw, true);
                    if (!is_array($cat_list)) $cat_list = array_filter(array_map('trim', explode(',', $cat_raw)));
                    ?>
                    <div class="cell-clamp-2">
                    <?php if (!empty($cat_list)):
                        foreach ($cat_list as $c): ?>
                            <span class="badge bg-primary category-badge"><?php echo htmlspecialchars($c); ?></span>
                        <?php endforeach;
                    else: ?>
                        <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                    </div>
                </td>
                <td><?php echo statusBadge($h['status'] ?? 'pending'); ?></td>
                <td class="text-center">
                    <?php
                    $lg = $h['ad_grade'] ?? 'none';
                    $lg_map = ['S'=>'bg-danger','A'=>'bg-danger','B'=>'bg-primary','C'=>'bg-primary','D'=>'bg-secondary','E'=>'bg-secondary'];
                    if ($lg !== 'none' && isset($lg_map[$lg])): ?>
                        <span class="badge <?php echo $lg_map[$lg]; ?>"><?php echo $lg; ?></span>
                    <?php else: ?>
                        <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <?php if ($h['is_active']): ?>
                        <span class="badge bg-success">ON</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">OFF</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (($h['rating'] ?? 0) > 0): ?>
                    <span class="text-warning">&#9733;</span>
                    <span class="small"><?php echo number_format($h['rating'], 1); ?></span>
                    <span class="text-body-tertiary small">(<?php echo $h['review_count'] ?? 0; ?>)</span>
                    <?php else: ?>
                    <span class="text-body-tertiary">-</span>
                    <?php endif; ?>
                </td>
                <td class="small"><?php echo $h['created_at'] ? date('Y.m.d', strtotime($h['created_at'])) : '-'; ?></td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="?view=<?php echo $h['id']; ?>" class="btn btn-outline-info" title="상세보기">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="?view=<?php echo $h['id']; ?>&edit=1" class="btn btn-outline-primary" title="수정">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if (($h['status'] ?? 'pending') === 'pending'): ?>
                        <form method="POST" class="d-inline" onsubmit="return confirm('승인하시겠습니까?')">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="hospital_id" value="<?php echo $h['id']; ?>">
                            <button type="submit" class="btn btn-outline-success" title="승인">
                                <i class="bi bi-check-lg"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>


<?php if ($total_pages > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php if ($page > 1): ?>
        <li class="page-item">
            <a class="page-link" href="<?php echo buildHospitalUrl(['page' => $page - 1]); ?>">&laquo;</a>
        </li>
        <?php endif; ?>
        <?php
        $window_size = 10;
        $start = max(1, $page - 4);
        $end = min($total_pages, $start + $window_size - 1);
        if ($end - $start + 1 < $window_size) {
            $start = max(1, $end - $window_size + 1);
        }
        ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
            <a class="page-link" href="<?php echo buildHospitalUrl(['page' => $i]); ?>"><?php echo $i; ?></a>
        </li>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?>
        <li class="page-item">
            <a class="page-link" href="<?php echo buildHospitalUrl(['page' => $page + 1]); ?>">&raquo;</a>
        </li>
        <?php endif; ?>
    </ul>
</nav>
<?php endif; ?>
