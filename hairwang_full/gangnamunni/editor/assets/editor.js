/**
 * 후기 에디터 JS
 * Quill 초기화, 이미지 업로드, 자동저장, 별점
 */

// 상태
let currentRating = 0;
let selectedTreatments = [];
let uploadedImages = [];
let quill = null;
let autosaveTimer = null;
let isDirty = false;

// 초기화
document.addEventListener('DOMContentLoaded', function() {
    initQuill();
    initImageUpload();
    loadExistingData();
    loadAutosave();
    startAutosave();
    initVisualViewport();
});

// Quill 에디터 초기화
function initQuill() {
    quill = new Quill('#quillEditor', {
        theme: 'snow',
        placeholder: '시술 후기를 자세히 작성해주세요.\n시술 과정, 회복 기간, 만족도 등을 공유하면 다른 분들에게 도움이 됩니다.',
        modules: {
            toolbar: [
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                ['blockquote'],
                ['clean']
            ],
            clipboard: {
                matchVisual: false  // 붙여넣기 시 스타일 제거
            }
        }
    });

    // 에디터 변경 감지
    quill.on('text-change', function() {
        isDirty = true;
    });

    // 한글 입력 이슈 대응
    quill.root.addEventListener('compositionend', function() {
        // compositionend 후 Quill 내부 업데이트 트리거
        quill.update();
    });
}

// 별점 설정
function setRating(rating) {
    currentRating = rating;
    const stars = document.querySelectorAll('.star-btn');
    const ratingTexts = ['', '별로예요', '그저 그래요', '보통이에요', '만족해요', '매우 만족해요'];

    stars.forEach((star, idx) => {
        if (idx < rating) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });

    document.getElementById('ratingText').textContent = ratingTexts[rating] || '';
    isDirty = true;
}

// 시술 태그 토글
function toggleTreatment(btn) {
    const treatment = btn.dataset.treatment;
    btn.classList.toggle('active');

    const idx = selectedTreatments.indexOf(treatment);
    if (idx >= 0) {
        selectedTreatments.splice(idx, 1);
    } else {
        selectedTreatments.push(treatment);
    }
    isDirty = true;
}

// 이미지 업로드 초기화
function initImageUpload() {
    const input = document.getElementById('imageInput');
    input.addEventListener('change', handleImageSelect);
}

// 이미지 파일 선택 핸들러
async function handleImageSelect(e) {
    const files = Array.from(e.target.files);
    if (!files.length) return;

    const remaining = CONFIG.maxImages - uploadedImages.length;
    if (remaining <= 0) {
        alert('이미지는 최대 ' + CONFIG.maxImages + '장까지 첨부할 수 있습니다.');
        return;
    }

    const filesToUpload = files.slice(0, remaining);
    showLoading(true);

    for (const file of filesToUpload) {
        if (file.size > 10 * 1024 * 1024) {
            alert(file.name + ': 파일 크기가 10MB를 초과합니다.');
            continue;
        }
        await uploadImage(file);
    }

    showLoading(false);
    updateImageGrid();
    e.target.value = '';
}

// 이미지 업로드 API 호출
async function uploadImage(file) {
    const formData = new FormData();
    formData.append('image', file);
    formData.append('csrf_token', CONFIG.csrfToken);

    try {
        const res = await fetch(CONFIG.baseUrl + '/api/upload_image.php', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();

        if (data.success) {
            uploadedImages.push({
                url: data.url,
                thumbnail_url: data.thumbnail_url || data.url,
                file_size: data.file_size,
                original_name: file.name
            });
            isDirty = true;
        } else {
            alert(data.message || '이미지 업로드에 실패했습니다.');
        }
    } catch (err) {
        console.error('Upload error:', err);
        alert('이미지 업로드 중 오류가 발생했습니다.');
    }
}

// 이미지 그리드 업데이트
function updateImageGrid() {
    const grid = document.getElementById('imageGrid');
    const addBtn = document.getElementById('imageAddBtn');
    const countEl = document.getElementById('imageCount');

    // 기존 이미지 아이템 제거
    grid.querySelectorAll('.image-item').forEach(el => el.remove());

    // 이미지 아이템 추가 (addBtn 앞에)
    uploadedImages.forEach((img, idx) => {
        const item = document.createElement('div');
        item.className = 'image-item';
        item.innerHTML = `
            <img src="${img.thumbnail_url || img.url}" alt="이미지 ${idx + 1}" onclick="showPreview('${img.url}')">
            <button class="image-remove" onclick="event.stopPropagation(); removeImage(${idx})">×</button>
            <span class="image-order-badge">${idx + 1}</span>
        `;
        grid.insertBefore(item, addBtn);
    });

    // 카운트 업데이트
    countEl.textContent = uploadedImages.length;

    // 최대 개수 도달 시 추가 버튼 숨김
    if (uploadedImages.length >= CONFIG.maxImages) {
        addBtn.classList.add('hidden');
    } else {
        addBtn.classList.remove('hidden');
    }
}

// 이미지 제거
function removeImage(idx) {
    uploadedImages.splice(idx, 1);
    updateImageGrid();
    isDirty = true;
}

// 이미지 미리보기
function showPreview(url) {
    document.getElementById('previewImage').src = url;
    document.getElementById('previewModal').classList.add('show');
}

function closePreview() {
    document.getElementById('previewModal').classList.remove('show');
}

// 로딩 표시
function showLoading(show) {
    document.getElementById('loadingOverlay').classList.toggle('show', show);
}

// 자동저장
function startAutosave() {
    autosaveTimer = setInterval(function() {
        if (isDirty) {
            saveToLocal();
            isDirty = false;
        }
    }, CONFIG.autosaveInterval);
}

function saveToLocal() {
    const key = 'review_draft_' + CONFIG.hospitalId;
    const data = {
        rating: currentRating,
        treatments: selectedTreatments,
        title: document.getElementById('reviewTitle').value,
        content: quill.root.innerHTML,
        images: uploadedImages,
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
    if (CONFIG.mode === 'edit') return; // 수정 모드에서는 자동저장 복원 안 함

    const key = 'review_draft_' + CONFIG.hospitalId;
    const saved = localStorage.getItem(key);
    if (!saved) return;

    try {
        const data = JSON.parse(saved);
        // 1시간 이내 저장분만 복원
        if (Date.now() - data.savedAt > 3600000) {
            localStorage.removeItem(key);
            return;
        }

        if (confirm('이전에 작성하던 내용이 있습니다. 복원하시겠습니까?')) {
            if (data.rating) setRating(data.rating);
            if (data.title) document.getElementById('reviewTitle').value = data.title;
            if (data.content) quill.root.innerHTML = data.content;
            if (data.images) {
                uploadedImages = data.images;
                updateImageGrid();
            }
            if (data.treatments) {
                data.treatments.forEach(function(t) {
                    const btn = document.querySelector('.treatment-tag[data-treatment="' + t + '"]');
                    if (btn) {
                        btn.classList.add('active');
                        selectedTreatments.push(t);
                    }
                });
            }
        } else {
            localStorage.removeItem(key);
        }
    } catch (e) {
        localStorage.removeItem(key);
    }
}

function showAutosaveNotice() {
    const notice = document.getElementById('autosaveNotice');
    notice.classList.add('show');
    setTimeout(() => notice.classList.remove('show'), 2000);
}

// 기존 데이터 로드 (수정 모드)
function loadExistingData() {
    if (CONFIG.mode !== 'edit' || !existingReview) return;

    if (existingReview.rating) setRating(parseInt(existingReview.rating));
    if (existingReview.title) document.getElementById('reviewTitle').value = existingReview.title;

    // Quill 에디터에 기존 콘텐츠 로드
    if (existingReview.editor_content) {
        quill.root.innerHTML = existingReview.editor_content;
    } else if (existingReview.content) {
        quill.setText(existingReview.content);
    }

    // 시술 태그 복원
    if (existingReview.treatments) {
        existingReview.treatments.split(',').forEach(function(t) {
            t = t.trim();
            const btn = document.querySelector('.treatment-tag[data-treatment="' + t + '"]');
            if (btn) {
                btn.classList.add('active');
                selectedTreatments.push(t);
            }
        });
    }

    // 기존 이미지 복원
    if (existingImages && existingImages.length > 0) {
        uploadedImages = existingImages.map(function(img) {
            return {
                id: img.id,
                url: img.image_url,
                thumbnail_url: img.thumbnail_url || img.image_url,
                file_size: img.file_size,
                original_name: img.original_name
            };
        });
        updateImageGrid();
    }
}

// 후기 제출
async function submitReview() {
    // 유효성 검사
    if (currentRating === 0) {
        alert('별점을 선택해주세요.');
        return;
    }

    const title = document.getElementById('reviewTitle').value.trim();
    const editorContent = quill.root.innerHTML;
    const textContent = quill.getText().trim();

    if (textContent.length < 10) {
        alert('후기 내용을 10자 이상 작성해주세요.');
        return;
    }

    // DOMPurify로 HTML 정리
    const cleanContent = DOMPurify.sanitize(editorContent, {
        ALLOWED_TAGS: ['p', 'br', 'strong', 'em', 'u', 's', 'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'blockquote', 'a', 'img', 'span'],
        ALLOWED_ATTR: ['href', 'src', 'alt', 'class', 'target']
    });

    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    showLoading(true);

    const payload = {
        hospital_id: CONFIG.hospitalId,
        review_id: CONFIG.reviewId || null,
        user_id: CONFIG.userId,
        title: title,
        content: textContent,
        editor_content: cleanContent,
        rating: currentRating,
        treatments: selectedTreatments.join(','),
        images: uploadedImages.map(function(img) { return img.url; }),
        csrf_token: CONFIG.csrfToken
    };

    try {
        const res = await fetch(CONFIG.baseUrl + '/api/save_review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await res.json();

        if (data.success) {
            // 자동저장 데이터 삭제
            localStorage.removeItem('review_draft_' + CONFIG.hospitalId);

            // Flutter WebView 콜백
            if (window.flutter_inappwebview) {
                window.flutter_inappwebview.callHandler('onReviewSubmitted', {
                    reviewId: data.review_id,
                    mode: CONFIG.mode
                });
            }

            alert(CONFIG.mode === 'edit' ? '후기가 수정되었습니다.' : '후기가 등록되었습니다.');

            // 병원 상세 페이지로 이동
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

// 모바일 키보드 대응 (visualViewport)
function initVisualViewport() {
    if (!window.visualViewport) return;

    window.visualViewport.addEventListener('resize', function() {
        const viewport = window.visualViewport;
        document.documentElement.style.setProperty('--viewport-height', viewport.height + 'px');

        // 에디터 영역이 키보드에 가려지지 않도록
        const editor = document.querySelector('.ql-editor');
        if (editor && document.activeElement === editor) {
            setTimeout(function() {
                editor.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }, 100);
        }
    });
}

// Flutter 네이티브 이미지 선택 (WebView 연동용)
function requestNativeImagePicker() {
    if (window.flutter_inappwebview) {
        window.flutter_inappwebview.callHandler('openImagePicker');
    }
}

// Flutter에서 이미지 데이터 수신
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
