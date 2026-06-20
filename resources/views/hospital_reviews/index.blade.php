@extends('layouts.app')
@section('title')
    후기 관리
@endsection
@section('page_css')
<style>
    .review-stats .card { border: none; border-radius: 12px; }
    .review-stats .stat-value { font-size: 1.8rem; font-weight: 700; }
    .review-stats .stat-label { font-size: 0.85rem; color: #6c757d; }
    .star-text { color: #fbbf24; letter-spacing: 1px; }
    .review-table th { white-space: nowrap; font-size: 0.85rem; }
    .review-table td { vertical-align: middle; font-size: 0.9rem; }
    .filter-btn.active { font-weight: 600; }
    .review-content-preview { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .edit-star { cursor: pointer; font-size: 1.5rem; transition: color 0.15s; }
    .edit-star.active { color: #fbbf24; }
    .edit-star:not(.active) { color: #ddd; }
</style>
@endsection
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <i class="fas fa-star text-warning me-2"></i>후기 관리
        </h4>
        <a href="{{ url('/hairwang/gangnamunni/') }}" target="_blank" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-external-link-alt me-1"></i>서울미녀 보기
        </a>
    </div>

    <div class="row review-stats g-3 mb-4">
        <div class="col-6 col-md-3 col-xl"><div class="card shadow-sm text-center py-3"><div class="stat-value text-primary" id="statTotal">-</div><div class="stat-label">전체</div></div></div>
        <div class="col-6 col-md-3 col-xl"><div class="card shadow-sm text-center py-3"><div class="stat-value text-success" id="statActive">-</div><div class="stat-label">활성</div></div></div>
        <div class="col-6 col-md-3 col-xl"><div class="card shadow-sm text-center py-3"><div class="stat-value text-secondary" id="statInactive">-</div><div class="stat-label">비활성</div></div></div>
        <div class="col-6 col-md-3 col-xl"><div class="card shadow-sm text-center py-3"><div class="stat-value text-danger" id="statReported">-</div><div class="stat-label">신고</div></div></div>
        <div class="col-6 col-md-3 col-xl"><div class="card shadow-sm text-center py-3"><div class="stat-value text-warning" id="statAvgRating">-</div><div class="stat-label">평균 평점</div></div></div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="fw-semibold small text-body-secondary me-1">필터</span>
                <button class="btn btn-sm btn-primary filter-btn active" data-status="all" onclick="setFilter('all')">전체</button>
                <button class="btn btn-sm btn-outline-secondary filter-btn" data-status="active" onclick="setFilter('active')">활성</button>
                <button class="btn btn-sm btn-outline-secondary filter-btn" data-status="inactive" onclick="setFilter('inactive')">비활성</button>
                <div class="vr mx-1"></div>
                <div class="input-group input-group-sm" style="max-width:300px;">
                    <input type="text" class="form-control" id="searchInput" placeholder="작성자, 내용 검색...">
                    <button class="btn btn-outline-secondary" onclick="loadReviews()"><i class="fas fa-search"></i></button>
                </div>
                <div class="ms-auto text-muted small" id="totalInfo"></div>
            </div>
        </div>
    </div>

    <div class="alert alert-info py-2 align-items-center gap-2 mb-2" id="bulkBar" style="display:none;">
        <span><strong id="selectedCount">0</strong>건 선택</span>
        <button class="btn btn-success btn-sm ms-2" onclick="bulkAction('approve')"><i class="fas fa-check"></i> 승인</button>
        <button class="btn btn-warning btn-sm" onclick="bulkAction('reject')"><i class="fas fa-times"></i> 거부</button>
        <button class="btn btn-danger btn-sm" onclick="bulkAction('delete')"><i class="fas fa-trash"></i> 삭제</button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover review-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:40px;"><input type="checkbox" class="form-check-input" onclick="toggleSelectAll(this)"></th>
                            <th style="width:60px;">ID</th>
                            <th>작성자</th>
                            <th>평점</th>
                            <th>내용</th>
                            <th>상태</th>
                            <th>신고</th>
                            <th>작성일</th>
                            <th style="width:140px;">관리</th>
                        </tr>
                    </thead>
                    <tbody id="reviewBody">
                        <tr><td colspan="9" class="text-center py-5 text-muted">로딩 중...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <nav class="mt-3" id="paginationNav"></nav>
</div>

<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-eye me-2"></i>후기 상세</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2"><label class="fw-bold small">작성자</label><div id="vUser"></div></div>
                <div class="mb-2"><label class="fw-bold small">평점</label><div id="vRating" class="star-text"></div></div>
                <div class="mb-2"><label class="fw-bold small">제목</label><div id="vTitle"></div></div>
                <div class="mb-3"><label class="fw-bold small">내용</label><div id="vContent" class="border rounded p-2 bg-light" style="white-space:pre-wrap;max-height:300px;overflow-y:auto;"></div></div>
                <div class="row">
                    <div class="col-6"><label class="fw-bold small">상태</label><div id="vStatus"></div></div>
                    <div class="col-6"><label class="fw-bold small">작성일</label><div id="vDate"></div></div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">닫기</button>
                <button class="btn btn-primary btn-sm" onclick="openEditFromView()"><i class="fas fa-edit"></i> 편집</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>후기 편집</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">작성자</label>
                        <input type="text" class="form-control" id="editUserName" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">상태</label>
                        <select class="form-select" id="editIsActive">
                            <option value="1">활성</option>
                            <option value="0">비활성</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">평점</label>
                        <div id="editStars" class="d-flex gap-1">
                            <span class="edit-star active" data-r="1" onclick="setEditRating(1)">&#9733;</span>
                            <span class="edit-star active" data-r="2" onclick="setEditRating(2)">&#9733;</span>
                            <span class="edit-star active" data-r="3" onclick="setEditRating(3)">&#9733;</span>
                            <span class="edit-star active" data-r="4" onclick="setEditRating(4)">&#9733;</span>
                            <span class="edit-star active" data-r="5" onclick="setEditRating(5)">&#9733;</span>
                        </div>
                        <input type="hidden" id="editRating" value="5">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">제목</label>
                        <input type="text" class="form-control" id="editTitle" maxlength="200" placeholder="제목 (선택)">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">내용</label>
                        <textarea class="form-control" id="editContent" rows="8" required></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button class="btn btn-primary" onclick="saveEdit()"><i class="fas fa-save me-1"></i>저장</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('page_scripts')
<script>
let currentFilter = 'all', currentPage = 1, reviewCache = {};
const dataUrl = "{{ route('hospital-reviews.data') }}";
const baseUrl = "{{ url('hospital-reviews') }}";
const bulkUrl = "{{ route('hospital-reviews.bulk-action') }}";
const csrfToken = "{{ csrf_token() }}";

document.addEventListener('DOMContentLoaded', function() {
    loadReviews();
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') loadReviews();
    });
});

function loadReviews(page) {
    currentPage = page || 1;
    let search = document.getElementById('searchInput').value;
    fetch(dataUrl + '?status=' + currentFilter + '&page=' + currentPage + '&search=' + encodeURIComponent(search), {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.stats) {
            document.getElementById('statTotal').textContent = data.stats.total;
            document.getElementById('statActive').textContent = data.stats.active;
            document.getElementById('statInactive').textContent = data.stats.inactive;
            document.getElementById('statReported').textContent = data.stats.reported;
            document.getElementById('statAvgRating').textContent = data.stats.avg_rating + ' \u2605';
        }
        document.getElementById('totalInfo').textContent = '\ucd1d ' + data.total + '\uac74';
        reviewCache = {};
        (data.data || []).forEach(function(r) { reviewCache[r.id] = r; });
        renderTable(data.data || []);
        renderPagination(data.current_page, data.last_page);
    })
    .catch(function() {
        document.getElementById('reviewBody').innerHTML = '<tr><td colspan="9" class="text-center py-5 text-danger">\ub370\uc774\ud130\ub97c \ubd88\ub7ec\uc62c \uc218 \uc5c6\uc2b5\ub2c8\ub2e4.</td></tr>';
    });
}

function renderTable(reviews) {
    var body = document.getElementById('reviewBody');
    if (!reviews.length) {
        body.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-muted"><i class="fas fa-comment-slash me-2"></i>\ud6c4\uae30\uac00 \uc5c6\uc2b5\ub2c8\ub2e4.</td></tr>';
        return;
    }
    var html = '';
    reviews.forEach(function(r) {
        var stars = '';
        for (var i = 0; i < 5; i++) stars += i < r.rating ? '\u2605' : '\u2606';
        var statusBadge = r.is_active ? '<span class="badge bg-success">\ud65c\uc131</span>' : '<span class="badge bg-danger">\ube44\ud65c\uc131</span>';
        var reportBadge = r.report_count > 0 ? '<span class="badge bg-warning text-dark">' + r.report_count + '</span>' : '<span class="text-muted">-</span>';
        var content = (r.content || '').substring(0, 60);
        if ((r.content || '').length > 60) content += '...';

        html += '<tr>' +
            '<td><input type="checkbox" class="form-check-input row-check" value="' + r.id + '" onchange="updateBulk()"></td>' +
            '<td class="text-center">' + r.id + '</td>' +
            '<td>' + escHtml(r.user_name) + '</td>' +
            '<td><span class="star-text">' + stars + '</span></td>' +
            '<td class="review-content-preview" title="' + escAttr(r.content) + '">' + escHtml(content) + '</td>' +
            '<td>' + statusBadge + '</td>' +
            '<td class="text-center">' + reportBadge + '</td>' +
            '<td class="small">' + (r.created_at || '-').substring(0, 10) + '</td>' +
            '<td><div class="btn-group btn-group-sm">' +
                '<button class="btn btn-outline-info" onclick="viewReview(' + r.id + ')" title="\ubcf4\uae30"><i class="fas fa-eye"></i></button>' +
                '<button class="btn btn-outline-primary" onclick="editReview(' + r.id + ')" title="\ud3b8\uc9d1"><i class="fas fa-edit"></i></button>' +
                (r.is_active ?
                    '<button class="btn btn-outline-warning" onclick="toggleReview(' + r.id + ','reject')" title="\uac70\ubd80"><i class="fas fa-times"></i></button>' :
                    '<button class="btn btn-outline-success" onclick="toggleReview(' + r.id + ','approve')" title="\uc2b9\uc778"><i class="fas fa-check"></i></button>'
                ) +
            '</div></td></tr>';
    });
    body.innerHTML = html;
}

function renderPagination(current, last) {
    if (last <= 1) { document.getElementById('paginationNav').innerHTML = ''; return; }
    var html = '<ul class="pagination pagination-sm justify-content-center">';
    if (current > 1) html += '<li class="page-item"><a class="page-link" href="#" onclick="loadReviews(' + (current-1) + ');return false;">&laquo;</a></li>';
    var start = Math.max(1, current - 3), end = Math.min(last, current + 3);
    for (var i = start; i <= end; i++) {
        html += '<li class="page-item ' + (i===current?'active':'') + '"><a class="page-link" href="#" onclick="loadReviews(' + i + ');return false;">' + i + '</a></li>';
    }
    if (current < last) html += '<li class="page-item"><a class="page-link" href="#" onclick="loadReviews(' + (current+1) + ');return false;">&raquo;</a></li>';
    html += '</ul>';
    document.getElementById('paginationNav').innerHTML = html;
}

function setFilter(status) {
    currentFilter = status;
    document.querySelectorAll('.filter-btn').forEach(function(b) {
        b.classList.remove('active', 'btn-primary', 'btn-success', 'btn-secondary');
        b.classList.add('btn-outline-secondary');
    });
    var btn = document.querySelector('.filter-btn[data-status="' + status + '"]');
    btn.classList.remove('btn-outline-secondary');
    if (status === 'all') btn.classList.add('btn-primary', 'active');
    else if (status === 'active') btn.classList.add('btn-success', 'active');
    else btn.classList.add('btn-secondary', 'active');
    loadReviews(1);
}

function viewReview(id) {
    var r = reviewCache[id]; if (!r) return;
    document.getElementById('vUser').textContent = r.user_name;
    var stars = ''; for (var i = 0; i < 5; i++) stars += i < r.rating ? '\u2605' : '\u2606';
    document.getElementById('vRating').textContent = stars;
    document.getElementById('vTitle').textContent = r.title || '-';
    document.getElementById('vContent').textContent = r.content || '';
    document.getElementById('vStatus').innerHTML = r.is_active ? '<span class="badge bg-success">\ud65c\uc131</span>' : '<span class="badge bg-danger">\ube44\ud65c\uc131</span>';
    document.getElementById('vDate').textContent = r.created_at || '-';
    document.getElementById('viewModal').dataset.reviewId = id;
    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

function openEditFromView() {
    var id = document.getElementById('viewModal').dataset.reviewId;
    bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();
    setTimeout(function() { editReview(parseInt(id)); }, 300);
}

function editReview(id) {
    var r = reviewCache[id]; if (!r) return;
    document.getElementById('editId').value = r.id;
    document.getElementById('editUserName').value = r.user_name || '';
    document.getElementById('editTitle').value = r.title || '';
    document.getElementById('editContent').value = r.content || '';
    document.getElementById('editIsActive').value = r.is_active ? '1' : '0';
    setEditRating(r.rating || 5);
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function setEditRating(rating) {
    document.getElementById('editRating').value = rating;
    document.querySelectorAll('.edit-star').forEach(function(s) {
        s.classList.toggle('active', parseInt(s.dataset.r) <= rating);
    });
}

function saveEdit() {
    var id = document.getElementById('editId').value;
    fetch(baseUrl + '/' + id, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: JSON.stringify({
            title: document.getElementById('editTitle').value,
            content: document.getElementById('editContent').value,
            rating: parseInt(document.getElementById('editRating').value),
            user_name: document.getElementById('editUserName').value,
            is_active: document.getElementById('editIsActive').value === '1',
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('editModal')).hide();
            loadReviews(currentPage);
            displaySuccessMessage(result.message || 'Saved.');
        } else { displayErrorMessage(result.message || 'Error.'); }
    })
    .catch(function() { displayErrorMessage('Save error.'); });
}

function toggleReview(id, action) {
    if (!confirm(action === 'approve' ? '\uc2b9\uc778\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c?' : '\uac70\ubd80\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c?')) return;
    fetch(baseUrl + '/' + id + '/toggle-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: JSON.stringify({ action: action })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) { loadReviews(currentPage); displaySuccessMessage(result.message); }
        else { displayErrorMessage(result.message); }
    });
}

function toggleSelectAll(el) {
    document.querySelectorAll('.row-check').forEach(function(cb) { cb.checked = el.checked; });
    updateBulk();
}
function updateBulk() {
    var checked = document.querySelectorAll('.row-check:checked');
    document.getElementById('bulkBar').style.display = checked.length > 0 ? 'flex' : 'none';
    document.getElementById('selectedCount').textContent = checked.length;
}
function bulkAction(action) {
    var ids = Array.from(document.querySelectorAll('.row-check:checked')).map(function(cb) { return parseInt(cb.value); });
    if (!ids.length) return;
    if (!confirm(action === 'delete' ? '\uc0ad\uc81c\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c? \ub418\ub3cc\ub9b4 \uc218 \uc5c6\uc2b5\ub2c8\ub2e4.' : action === 'approve' ? '\uc2b9\uc778\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c?' : '\uac70\ubd80\ud558\uc2dc\uaca0\uc2b5\ub2c8\uae4c?')) return;
    fetch(bulkUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        body: JSON.stringify({ ids: ids, action: action })
    })
    .then(function(r) { return r.json(); })
    .then(function(result) {
        if (result.success) { loadReviews(currentPage); displaySuccessMessage(result.message); }
        else { displayErrorMessage(result.message); }
    });
}
function escHtml(str) { var d = document.createElement('div'); d.textContent = str || ''; return d.innerHTML; }
function escAttr(str) { return (str || '').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
</script>
@endsection
