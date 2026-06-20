/**
 * 후기 에디터 v2 JS
 * 다축 평가, 의사 선택, 비포/애프터, 카메라 촬영, AI 검수
 */

// 상태
let quill = null;
let autosaveTimer = null;
let isDirty = false;
let selectedDoctorId = 0;
let selectedDoctorName = '';
let selectedTreatments = [];
let uploadedImages = [];
let beforeImages = [];
let afterImages = [];
let axisRatings = { result: 0, consultation: 0, facility: 0, wait: 0, cost: 0, recovery: 0 };
let editorSettings = null;

// 초기화
document.addEventListener('DOMContentLoaded', function() {
    loadEditorSettings();
    initQuill();
    initImageUpload();
    initBAImageUpload();
    initCostInput();
    loadExistingData();
    loadAutosave();
    startAutosave();
    initVisualViewport();
});

// 관리자 에디터 설정 로드
function loadEditorSettings() {
    try {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', CONFIG.baseUrl + '/api/get_editor_settings.php', false); // sync
        xhr.send();
        if (xhr.status === 200) {
            var resp = JSON.parse(xhr.responseText);
            if (resp.success) {
                editorSettings = resp.settings;
                applyEditorSettings();
            }
        }
    } catch (e) {
        console.warn('Editor settings load failed, using defaults');
    }
}

// 에디터 설정 적용
function applyEditorSettings() {
    if (!editorSettings) return;

    // 카메라 전용 모드
    if (editorSettings.camera_only) {
        var fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(function(input) {
            input.setAttribute('capture', 'environment');
            input.removeAttribute('multiple');
        });
        // 카메라 전용 안내 표시
        var photoSections = document.querySelectorAll('.photo-section, .image-section');
        photoSections.forEach(function(sec) {
            var notice = document.createElement('div');
            notice.className = 'camera-only-notice';
            notice.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg><span>직접 촬영한 사진만 업로드할 수 있습니다</span>';
            sec.insertBefore(notice, sec.children[1] || null);
        });
    }

    // 최소 내용 길이
    if (editorSettings.min_content_length) {
        CONFIG.minContentLength = parseInt(editorSettings.min_content_length);
    }

    // 필수 별점
    if (editorSettings.require_rating === false) {
        var ratingSec = document.querySelector('.rating-section');
        if (ratingSec) {
            var label = ratingSec.querySelector('.section-label');
            if (label) {
                var badge = document.createElement('span');
                badge.className = 'optional-badge';
                badge.textContent = '선택';
                label.appendChild(badge);
            }
        }
    }

    // Before/After 사진 숨기기
    if (editorSettings.hide_ba_photos) {
        var photoSec = document.querySelector('.photo-section');
        if (photoSec) photoSec.style.display = 'none';
    }

    // 시술 정보 숨기기
    if (editorSettings.hide_procedure_info) {
        var procSec = document.querySelector('.procedure-section');
        if (procSec) procSec.style.display = 'none';
    }
}

// Quill 에디터 초기화
function initQuill() {
    quill = new Quill('#quillEditor', {
        theme: 'snow',
        placeholder: '시술 경험을 자세히 작성해주세요.\n\n- 어떤 시술을 받으셨나요?\n- 시술 과정은 어땠나요?\n- 회복 기간과 결과는 만족스러우셨나요?\n\n구체적인 후기가 다른 분들에게 큰 도움이 됩니다.',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                ['blockquote'],
                ['clean']
            ],
            clipboard: { matchVisual: false }
        }
    });
    quill.on('text-change', function() { isDirty = true; });
    quill.root.addEventListener('compositionend', function() { quill.update(); });
}

// 다축 별점 설정
function setAxisRating(axis, rating) {
    axisRatings[axis] = rating;
    var stars = document.querySelectorAll('.axis-star[data-axis="' + axis + '"]');
    var texts = ['', '별로예요', '그저 그래요', '보통이에요', '만족해요', '매우 만족해요'];
    stars.forEach(function(star, idx) {
        if (idx < rating) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
    var valueEl = document.getElementById('axisValue_' + axis);
    if (valueEl) valueEl.textContent = rating;
    updateAvgRating();
    isDirty = true;
}

// 평균 별점 업데이트
function updateAvgRating() {
    var keys = Object.keys(axisRatings);
    var rated = keys.filter(function(k) { return axisRatings[k] > 0; });
    var avg = 0;
    if (rated.length > 0) {
        var sum = rated.reduce(function(s, k) { return s + axisRatings[k]; }, 0);
        avg = (sum / rated.length).toFixed(1);
    }
    var avgEl = document.getElementById('ratingAvg');
    if (avgEl) avgEl.textContent = avg;

    // 별 표시
    var starsEl = document.getElementById('ratingAvgStars');
    if (starsEl) {
        var html = '';
        var full = Math.floor(parseFloat(avg));
        var half = parseFloat(avg) - full >= 0.5;
        for (var i = 0; i < 5; i++) {
            if (i < full) {
                html += '<svg viewBox="0 0 24 24" fill="#fbbf24" stroke="#fbbf24" stroke-width="1"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
            } else if (i === full && half) {
                html += '<svg viewBox="0 0 24 24" fill="#fbbf24" stroke="#fbbf24" stroke-width="1" opacity="0.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
            } else {
                html += '<svg viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
            }
        }
        starsEl.innerHTML = html;
    }
}

// 의사 선택
function selectDoctor(btn) {
    document.querySelectorAll('.doctor-card').forEach(function(c) { c.classList.remove('selected'); });
    btn.classList.add('selected');
    selectedDoctorId = parseInt(btn.dataset.doctorId) || 0;
    selectedDoctorName = btn.dataset.doctorName || '';
    isDirty = true;
}

// 시술 태그 토글
function toggleTreatment(btn) {
    var treatment = btn.dataset.treatment;
    btn.classList.toggle('active');
    var idx = selectedTreatments.indexOf(treatment);
    if (idx >= 0) {
        selectedTreatments.splice(idx, 1);
    } else {
        selectedTreatments.push(treatment);
    }
    isDirty = true;
}

// 비용 입력 포맷팅
function initCostInput() {
    var costInput = document.getElementById('procedureCost');
    if (!costInput) return;
    costInput.addEventListener('input', function(e) {
        var val = e.target.value.replace(/[^\d]/g, '');
        if (val) {
            e.target.value = parseInt(val).toLocaleString('ko-KR');
        }
    });
}

// ===== 일반 이미지 업로드 =====
function initImageUpload() {
    var input = document.getElementById('imageInput');
    if (input) input.addEventListener('change', handleImageSelect);
}

async function handleImageSelect(e) {
    var files = Array.from(e.target.files);
    if (!files.length) return;
    var remaining = CONFIG.maxImages - uploadedImages.length;
    if (remaining <= 0) {
        alert('이미지는 최대 ' + CONFIG.maxImages + '장까지 첨부할 수 있습니다.');
        return;
    }
    var filesToUpload = files.slice(0, remaining);
    showLoading(true);
    for (var i = 0; i < filesToUpload.length; i++) {
        var file = filesToUpload[i];
        if (file.size > 10 * 1024 * 1024) {
            alert(file.name + ': 파일 크기가 10MB를 초과합니다.');
            continue;
        }
        var result = await uploadImage(file);
        if (result) uploadedImages.push(result);
    }
    showLoading(false);
    updateImageGrid();
    e.target.value = '';
}

async function uploadImage(file) {
    var formData = new FormData();
    formData.append('image', file);
    formData.append('csrf_token', CONFIG.csrfToken);
    try {
        var res = await fetch(CONFIG.baseUrl + '/api/upload_image.php', { method: 'POST', body: formData });
        var data = await res.json();
        if (data.success) {
            isDirty = true;
            return {
                url: data.url,
                thumbnail_url: data.thumbnail_url || data.url,
                file_size: data.file_size,
                original_name: file.name
            };
        } else {
            alert(data.message || '이미지 업로드에 실패했습니다.');
        }
    } catch (err) {
        console.error('Upload error:', err);
        alert('이미지 업로드 중 오류가 발생했습니다.');
    }
    return null;
}

function updateImageGrid() {
    var grid = document.getElementById('imageGrid');
    var addBtn = document.getElementById('imageAddBtn');
    var countEl = document.getElementById('imageCount');
    if (!grid) return;
    grid.querySelectorAll('.image-item').forEach(function(el) { el.remove(); });
    uploadedImages.forEach(function(img, idx) {
        var item = document.createElement('div');
        item.className = 'image-item';
        item.innerHTML = '<img src="' + (img.thumbnail_url || img.url) + '" alt="이미지 ' + (idx+1) + '" onclick="showPreview(\'' + img.url + '\')">' +
            '<button class="image-remove" onclick="event.stopPropagation(); removeImage(' + idx + ')">×</button>' +
            '<span class="image-order-badge">' + (idx+1) + '</span>';
        grid.insertBefore(item, addBtn);
    });
    if (countEl) countEl.textContent = uploadedImages.length;
    if (addBtn) {
        if (uploadedImages.length >= CONFIG.maxImages) {
            addBtn.classList.add('hidden');
        } else {
            addBtn.classList.remove('hidden');
        }
    }
}

function removeImage(idx) {
    uploadedImages.splice(idx, 1);
    updateImageGrid();
    isDirty = true;
}

// ===== Before/After 이미지 =====
function initBAImageUpload() {
    var baInputs = document.querySelectorAll('.ba-file-input');
    baInputs.forEach(function(input) {
        input.addEventListener('change', handleBAImageSelect);
    });
}

async function handleBAImageSelect(e) {
    var category = e.target.dataset.category; // 'before' or 'after'
    var files = Array.from(e.target.files);
    if (!files.length) return;
    var arr = category === 'before' ? beforeImages : afterImages;
    var remaining = CONFIG.maxBAImages - arr.length;
    if (remaining <= 0) {
        alert('최대 ' + CONFIG.maxBAImages + '장까지 첨부할 수 있습니다.');
        return;
    }
    var filesToUpload = files.slice(0, remaining);
    showLoading(true);
    for (var i = 0; i < filesToUpload.length; i++) {
        var file = filesToUpload[i];
        if (file.size > 10 * 1024 * 1024) {
            alert(file.name + ': 10MB를 초과합니다.');
            continue;
        }
        var result = await uploadImage(file);
        if (result) {
            result.category = category;
            arr.push(result);
        }
    }
    showLoading(false);
    updateBAGrid(category);
    e.target.value = '';
}

function updateBAGrid(category) {
    var arr = category === 'before' ? beforeImages : afterImages;
    var containerId = category === 'before' ? 'beforeImages' : 'afterImages';
    var addBtnId = category === 'before' ? 'beforeAddBtn' : 'afterAddBtn';
    var container = document.getElementById(containerId);
    var addBtn = document.getElementById(addBtnId);
    if (!container) return;

    container.querySelectorAll('.ba-item').forEach(function(el) { el.remove(); });
    arr.forEach(function(img, idx) {
        var item = document.createElement('div');
        item.className = 'ba-item';
        item.innerHTML = '<img src="' + (img.thumbnail_url || img.url) + '" alt="" onclick="showPreview(\'' + img.url + '\')">' +
            '<button class="ba-item-remove" onclick="event.stopPropagation(); removeBAImage(\'' + category + '\',' + idx + ')">×</button>';
        container.insertBefore(item, addBtn);
    });

    // 카운트 업데이트
    var countEl = addBtn ? addBtn.querySelector('.ba-count') : null;
    if (countEl) countEl.textContent = '(' + arr.length + '/' + CONFIG.maxBAImages + ')';
    if (addBtn) {
        if (arr.length >= CONFIG.maxBAImages) {
            addBtn.classList.add('hidden');
        } else {
            addBtn.classList.remove('hidden');
        }
    }
}

function removeBAImage(category, idx) {
    if (category === 'before') {
        beforeImages.splice(idx, 1);
    } else {
        afterImages.splice(idx, 1);
    }
    updateBAGrid(category);
    isDirty = true;
}

// 미리보기
function showPreview(url) {
    document.getElementById('previewImage').src = url;
    document.getElementById('previewModal').classList.add('show');
}

function closePreview() {
    document.getElementById('previewModal').classList.remove('show');
}

function showLoading(show) {
    document.getElementById('loadingOverlay').classList.toggle('show', show);
}

// ===== 자동저장 =====
function startAutosave() {
    autosaveTimer = setInterval(function() {
        if (isDirty) {
            saveToLocal();
            isDirty = false;
        }
    }, CONFIG.autosaveInterval);
}

function saveToLocal() {
    var key = 'review_draft_v2_' + CONFIG.hospitalId;
    var data = {
        axisRatings: axisRatings,
        doctorId: selectedDoctorId,
        doctorName: selectedDoctorName,
        treatments: selectedTreatments,
        title: document.getElementById('reviewTitle').value,
        content: quill.root.innerHTML,
        procedureDate: document.getElementById('procedureDate') ? document.getElementById('procedureDate').value : '',
        procedureCost: document.getElementById('procedureCost') ? document.getElementById('procedureCost').value : '',
        images: uploadedImages,
        beforeImages: beforeImages,
        afterImages: afterImages,
        savedAt: Date.now()
    };
    try {
        localStorage.setItem(key, JSON.stringify(data));
        showAutosaveNotice();
    } catch (e) {
        console.warn('Autosave failed:', e);
    }
}

function loadAutosave() {
    if (CONFIG.mode === 'edit') return;
    var key = 'review_draft_v2_' + CONFIG.hospitalId;
    var saved = localStorage.getItem(key);
    if (!saved) return;
    try {
        var data = JSON.parse(saved);
        if (Date.now() - data.savedAt > 3600000) {
            localStorage.removeItem(key);
            return;
        }
        if (confirm('이전에 작성하던 내용이 있습니다. 복원하시겠습니까?')) {
            if (data.axisRatings) {
                Object.keys(data.axisRatings).forEach(function(k) {
                    if (data.axisRatings[k] > 0) setAxisRating(k, data.axisRatings[k]);
                });
            }
            if (data.title) document.getElementById('reviewTitle').value = data.title;
            if (data.content) quill.root.innerHTML = data.content;
            if (data.procedureDate && document.getElementById('procedureDate')) {
                document.getElementById('procedureDate').value = data.procedureDate;
            }
            if (data.procedureCost && document.getElementById('procedureCost')) {
                document.getElementById('procedureCost').value = data.procedureCost;
            }
            if (data.images) { uploadedImages = data.images; updateImageGrid(); }
            if (data.beforeImages) { beforeImages = data.beforeImages; updateBAGrid('before'); }
            if (data.afterImages) { afterImages = data.afterImages; updateBAGrid('after'); }
            if (data.treatments) {
                data.treatments.forEach(function(t) {
                    var btn = document.querySelector('.treatment-tag[data-treatment="' + t + '"]');
                    if (btn) { btn.classList.add('active'); selectedTreatments.push(t); }
                });
            }
            if (data.doctorId) {
                var docBtn = document.querySelector('.doctor-card[data-doctor-id="' + data.doctorId + '"]');
                if (docBtn) selectDoctor(docBtn);
            }
        } else {
            localStorage.removeItem(key);
        }
    } catch (e) {
        localStorage.removeItem(key);
    }
}

function showAutosaveNotice() {
    var notice = document.getElementById('autosaveNotice');
    notice.classList.add('show');
    setTimeout(function() { notice.classList.remove('show'); }, 2000);
}

// 기존 데이터 로드 (수정 모드)
function loadExistingData() {
    if (CONFIG.mode !== 'edit' || !existingReview) return;
    if (existingReview.title) document.getElementById('reviewTitle').value = existingReview.title;
    if (existingReview.editor_content) {
        quill.root.innerHTML = existingReview.editor_content;
    } else if (existingReview.content) {
        quill.setText(existingReview.content);
    }
    // 다축 별점 복원
    var axisKeys = ['result', 'consultation', 'facility', 'wait', 'cost', 'recovery'];
    axisKeys.forEach(function(k) {
        var val = parseInt(existingReview['rating_' + k]);
        if (val > 0) setAxisRating(k, val);
    });
    // 단일 별점 -> 전체 적용 (구버전 호환)
    if (existingReview.rating && !existingReview.rating_result) {
        var r = parseInt(existingReview.rating);
        if (r > 0) axisKeys.forEach(function(k) { setAxisRating(k, r); });
    }
    // 시술 태그 복원
    if (existingReview.treatments) {
        existingReview.treatments.split(',').forEach(function(t) {
            t = t.trim();
            var btn = document.querySelector('.treatment-tag[data-treatment="' + t + '"]');
            if (btn) { btn.classList.add('active'); selectedTreatments.push(t); }
        });
    }
    // 의사 복원
    if (existingReview.doctor_id) {
        var docBtn = document.querySelector('.doctor-card[data-doctor-id="' + existingReview.doctor_id + '"]');
        if (docBtn) selectDoctor(docBtn);
    }
    // 시술 정보 복원
    if (existingReview.procedure_date && document.getElementById('procedureDate')) {
        document.getElementById('procedureDate').value = existingReview.procedure_date;
    }
    if (existingReview.procedure_cost && document.getElementById('procedureCost')) {
        document.getElementById('procedureCost').value = parseInt(existingReview.procedure_cost).toLocaleString('ko-KR');
    }
    // 이미지 복원
    if (existingImages && existingImages.length > 0) {
        existingImages.forEach(function(img) {
            var obj = {
                id: img.id, url: img.image_url,
                thumbnail_url: img.thumbnail_url || img.image_url,
                file_size: img.file_size, original_name: img.original_name,
                category: img.photo_category || 'general'
            };
            if (obj.category === 'before') beforeImages.push(obj);
            else if (obj.category === 'after') afterImages.push(obj);
            else uploadedImages.push(obj);
        });
        updateImageGrid();
        updateBAGrid('before');
        updateBAGrid('after');
    }
}

// ===== 후기 제출 =====
async function submitReview() {
    // 유효성 검사
    var rated = Object.keys(axisRatings).filter(function(k) { return axisRatings[k] > 0; });
    var requireRating = !editorSettings || editorSettings.require_rating !== false;
    if (requireRating && rated.length === 0) {
        alert('만족도를 1개 이상 평가해주세요.');
        document.querySelector('.rating-section').scrollIntoView({ behavior: 'smooth' });
        return;
    }

    var title = document.getElementById('reviewTitle').value.trim();
    var editorContent = quill.root.innerHTML;
    var textContent = quill.getText().trim();
    var minLen = CONFIG.minContentLength || 10;

    if (textContent.length < minLen) {
        alert('후기 내용을 ' + minLen + '자 이상 작성해주세요.');
        return;
    }

    var cleanContent = DOMPurify.sanitize(editorContent, {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'u', 's', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'blockquote', 'a', 'span'],
        ALLOWED_ATTR: ['href', 'class', 'target']
    });

    // 평균 별점 계산
    var avgRating = 0;
    if (rated.length > 0) {
        var sum = rated.reduce(function(s, k) { return s + axisRatings[k]; }, 0);
        avgRating = Math.round((sum / rated.length) * 10) / 10;
    }

    // 비용 파싱
    var costEl = document.getElementById('procedureCost');
    var cost = costEl ? parseInt(costEl.value.replace(/[^\d]/g, '')) || 0 : 0;
    var dateEl = document.getElementById('procedureDate');
    var procDate = dateEl ? dateEl.value : '';

    var submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    showLoading(true);

    // 모든 이미지 합치기 (카테고리 포함)
    var allImages = [];
    beforeImages.forEach(function(img) { allImages.push({ url: img.url, category: 'before' }); });
    afterImages.forEach(function(img) { allImages.push({ url: img.url, category: 'after' }); });
    uploadedImages.forEach(function(img) { allImages.push({ url: img.url, category: 'general' }); });

    var payload = {
        hospital_id: CONFIG.hospitalId,
        review_id: CONFIG.reviewId || null,
        title: title,
        content: textContent,
        editor_content: cleanContent,
        rating: avgRating,
        rating_result: axisRatings.result,
        rating_consultation: axisRatings.consultation,
        rating_facility: axisRatings.facility,
        rating_wait: axisRatings.wait,
        rating_cost: axisRatings.cost,
        rating_recovery: axisRatings.recovery,
        doctor_id: selectedDoctorId || null,
        doctor_name: selectedDoctorName || null,
        procedure_date: procDate || null,
        procedure_cost: cost || null,
        treatments: selectedTreatments.join(','),
        images: allImages,
        csrf_token: CONFIG.csrfToken
    };

    try {
        var res = await fetch(CONFIG.baseUrl + '/api/save_review_v2.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        var data = await res.json();

        if (data.success) {
            localStorage.removeItem('review_draft_v2_' + CONFIG.hospitalId);

            // AI 검수 결과 표시
            if (data.ai_check) {
                showAICheckResult(data.ai_check);
            }

            if (window.flutter_inappwebview) {
                window.flutter_inappwebview.callHandler('onReviewSubmitted', {
                    reviewId: data.review_id, mode: CONFIG.mode
                });
            }

            if (data.ai_check && data.ai_check.status === 'pending') {
                alert('후기가 등록되었습니다. 검수 후 게시됩니다.');
            } else {
                alert(CONFIG.mode === 'edit' ? '후기가 수정되었습니다.' : '후기가 등록되었습니다.');
            }

            if (CONFIG.hospitalId) {
                window.location.href = CONFIG.baseUrl + '/detail.php?id=' + CONFIG.hospitalId + '&tab=reviews';
            }
        } else {
            alert(data.message || '후기 저장에 실패했습니다.');
        }
    } catch (err) {
        console.error('Submit error:', err);
        alert('후기 저장 중 오류가 발생했습니다.');
    } finally {
        submitBtn.disabled = false;
        showLoading(false);
    }
}

// AI 검수 결과 표시
function showAICheckResult(check) {
    var existing = document.querySelector('.ai-check-result');
    if (existing) existing.remove();

    var div = document.createElement('div');
    div.className = 'ai-check-result show ' + (check.passed ? 'pass' : check.status === 'pending' ? 'warn' : 'fail');

    var title = check.passed ? 'AI 검수 통과' : (check.status === 'pending' ? 'AI 검수 대기' : 'AI 검수 주의');
    var html = '<h4>' + title + '</h4>';
    if (check.messages && check.messages.length > 0) {
        html += '<ul>';
        check.messages.forEach(function(msg) { html += '<li>' + msg + '</li>'; });
        html += '</ul>';
    }
    div.innerHTML = html;
    var container = document.querySelector('.editor-container');
    var header = container.querySelector('.editor-header');
    header.insertAdjacentElement('afterend', div);
}

// 뒤로가기
function handleBack() {
    if (isDirty) {
        if (confirm('작성 중인 내용이 있습니다. 나가시겠습니까?\n임시저장된 내용은 유지됩니다.')) {
            saveToLocal();
            goBack();
        }
    } else {
        goBack();
    }
}

function goBack() {
    if (window.flutter_inappwebview) {
        window.flutter_inappwebview.callHandler('onEditorClose');
    } else if (window.history.length > 1) {
        window.history.back();
    } else if (CONFIG.hospitalId) {
        window.location.href = CONFIG.baseUrl + '/detail.php?id=' + CONFIG.hospitalId;
    }
}

// 모바일 키보드 대응
function initVisualViewport() {
    if (!window.visualViewport) return;
    window.visualViewport.addEventListener('resize', function() {
        document.documentElement.style.setProperty('--viewport-height', window.visualViewport.height + 'px');
        var editor = document.querySelector('.ql-editor');
        if (editor && document.activeElement === editor) {
            setTimeout(function() { editor.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }, 100);
        }
    });
}

// Flutter 연동
function requestNativeImagePicker() {
    if (window.flutter_inappwebview) {
        window.flutter_inappwebview.callHandler('openImagePicker');
    }
}

window.receiveNativeImage = function(imageData) {
    if (imageData && imageData.url) {
        uploadedImages.push({
            url: imageData.url,
            thumbnail_url: imageData.thumbnail_url || imageData.url,
            file_size: imageData.file_size || 0,
            original_name: imageData.original_name || 'native_image.jpg'
        });
        updateImageGrid();
        isDirty = true;
    }
};
