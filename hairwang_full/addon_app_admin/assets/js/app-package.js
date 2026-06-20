/**
 * 앱 패키징 대시보드 JavaScript
 * @package AddonAppAdmin
 * @version 1.0.0
 */

(function() {
    'use strict';

    let selectedFile = null;

    // DOM 요소
    const uploadZone = document.getElementById('app-upload-zone');
    const fileInput = document.getElementById('app-file-input');
    const uploadProgress = document.getElementById('app-upload-progress');
    const progressFill = document.getElementById('progress-fill');
    const progressText = document.getElementById('progress-text');
    const btnStartUpload = document.getElementById('btn-start-upload');

    if (!uploadZone || !fileInput) {
        return;
    }

    // 업로드 영역 클릭
    uploadZone.addEventListener('click', function() {
        fileInput.click();
    });

    // 파일 선택
    fileInput.addEventListener('change', function(e) {
        handleFileSelect(e.target.files);
    });

    // 드래그 앤 드롭
    uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadZone.classList.add('drag-over');
    });

    uploadZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadZone.classList.remove('drag-over');
    });

    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadZone.classList.remove('drag-over');

        const files = e.dataTransfer.files;
        handleFileSelect(files);
    });

    // 파일 선택 처리
    function handleFileSelect(files) {
        if (files.length === 0) {
            return;
        }

        const file = files[0];

        // ZIP 파일 확인
        if (!file.name.toLowerCase().endsWith('.zip')) {
            alert('ZIP 파일만 업로드 가능합니다.');
            return;
        }

        // 파일 크기 확인 (100MB)
        const maxSize = 100 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('파일 크기가 너무 큽니다. (최대 100MB)');
            return;
        }

        selectedFile = file;

        // 업로드 영역 업데이트
        const uploadIcon = uploadZone.querySelector('.app-upload-icon i');
        const uploadText = uploadZone.querySelector('.app-upload-text p');
        const uploadDesc = uploadZone.querySelector('.app-upload-desc');

        if (uploadIcon) {
            uploadIcon.className = 'fa fa-check-circle';
            uploadIcon.style.color = '#28a745';
        }

        if (uploadText) {
            uploadText.innerHTML = '<strong>' + file.name + '</strong> 선택됨';
        }

        if (uploadDesc) {
            uploadDesc.textContent = formatFileSize(file.size);
        }

        // 업로드 버튼 활성화
        if (btnStartUpload) {
            btnStartUpload.disabled = false;
        }
    }

    // 업로드 시작
    if (btnStartUpload) {
        btnStartUpload.addEventListener('click', function() {
            if (!selectedFile) {
                alert('파일을 선택하세요.');
                return;
            }

            const appType = document.querySelector('input[name="app_type"]:checked');
            const appVersion = document.querySelector('input[name="app_version"]');
            const appDescription = document.querySelector('textarea[name="app_description"]');

            if (!appType) {
                alert('앱 타입을 선택하세요.');
                return;
            }

            // FormData 생성
            const formData = new FormData();
            formData.append('app_file', selectedFile);
            formData.append('app_type', appType.value);
            formData.append('app_version', appVersion ? appVersion.value : '');
            formData.append('app_description', appDescription ? appDescription.value : '');
            formData.append('action', 'upload');

            // 업로드 시작
            uploadFile(formData);
        });
    }

    // 파일 업로드
    function uploadFile(formData) {
        // 진행률 표시
        if (uploadProgress) {
            uploadProgress.style.display = 'block';
        }

        // 업로드 버튼 비활성화
        if (btnStartUpload) {
            btnStartUpload.disabled = true;
            btnStartUpload.innerHTML = '<i class="fa fa-spinner fa-spin"></i> 업로드 중...';
        }

        // AJAX 업로드
        const xhr = new XMLHttpRequest();

        // 진행률 업데이트
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                const percentComplete = Math.round((e.loaded / e.total) * 100);

                if (progressFill) {
                    progressFill.style.width = percentComplete + '%';
                }

                if (progressText) {
                    progressText.textContent = '업로드 중... ' + percentComplete + '%';
                }
            }
        });

        // 업로드 완료
        xhr.addEventListener('load', function() {
            if (xhr.status === 200) {
                try {
                    const response = JSON.parse(xhr.responseText);

                    if (response.success) {
                        if (progressText) {
                            progressText.textContent = '업로드 완료! 대시보드를 여는 중...';
                        }

                        // 이미지 편집 대시보드 열기
                        setTimeout(function() {
                            window.open('./app-image-editor.php?source_id=' + response.source_id, '_blank');

                            // 페이지 새로고침
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }, 500);
                    } else {
                        alert('업로드 실패: ' + response.message);
                        resetUploadForm();
                    }
                } catch (e) {
                    alert('응답 처리 중 오류가 발생했습니다.');
                    resetUploadForm();
                }
            } else {
                alert('업로드 실패: ' + xhr.status);
                resetUploadForm();
            }
        });

        // 업로드 오류
        xhr.addEventListener('error', function() {
            alert('업로드 중 오류가 발생했습니다.');
            resetUploadForm();
        });

        // 업로드 취소
        xhr.addEventListener('abort', function() {
            alert('업로드가 취소되었습니다.');
            resetUploadForm();
        });

        // 업로드 시작
        xhr.open('POST', './app-package-upload.php', true);
        xhr.send(formData);
    }

    // 업로드 폼 초기화
    function resetUploadForm() {
        selectedFile = null;

        if (uploadProgress) {
            uploadProgress.style.display = 'none';
        }

        if (progressFill) {
            progressFill.style.width = '0%';
        }

        if (progressText) {
            progressText.textContent = '업로드 중... 0%';
        }

        if (btnStartUpload) {
            btnStartUpload.disabled = true;
            btnStartUpload.innerHTML = '<i class="fa fa-upload"></i> 업로드 및 대시보드 열기';
        }

        // 업로드 영역 초기화
        const uploadIcon = uploadZone.querySelector('.app-upload-icon i');
        const uploadText = uploadZone.querySelector('.app-upload-text p');
        const uploadDesc = uploadZone.querySelector('.app-upload-desc');

        if (uploadIcon) {
            uploadIcon.className = 'fa fa-cloud-upload';
            uploadIcon.style.color = '';
        }

        if (uploadText) {
            uploadText.innerHTML = '<strong>앱 소스 폴더를 여기에 드래그하거나 클릭하여 업로드</strong>';
        }

        if (uploadDesc) {
            uploadDesc.textContent = 'ZIP 파일만 업로드 가능 (최대 100MB)';
        }

        // 파일 입력 초기화
        if (fileInput) {
            fileInput.value = '';
        }
    }

    // 파일 크기 포맷
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';

        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

})();
