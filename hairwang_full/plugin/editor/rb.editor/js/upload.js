let uploadedImages = [];

// 업로드 시 Nonce 값이 없으면 iframe에서 요청 후 대기
async function uploadImage(file, options = { insert: true }) {
    if (typeof file === 'string') {
        // SVG 파일은 변환하지 않고 바로 업로드
        if (file.endsWith(".svg")) {
            //console.log("SVG 파일 감지:", file);
        } else {
            file = await convertImageUrlToFile(file);
        }
    }
    
    if (!(file instanceof File)) {
        //console.error("올바른 이미지 파일이 아닙니다:", file);
        alert("이미지 파일을 선택해주세요.");
        return;
    }

    if (!ed_nonce) {
        ed_nonce = await requestNonceFromParent();
    }

    if (!ed_nonce) {
        alert("보안 토큰이 유효하지 않습니다. 새로고침 후 다시 시도해주세요.");
        return;
    }

    // ✅ 로딩 오버레이 표시
    const loadingOverlay = document.querySelector('.loadingOverlay.loadingOverlay_ai');
    if (loadingOverlay) {
        loadingOverlay.style.display = 'block';
    }

    const formData = new FormData();
    formData.append("file", file);
    formData.append("editor_nonce", ed_nonce);

    return new Promise((resolve, reject) => {
        $.ajax({
            url: g5Config.g5_editor_url + "/php/rb.upload.php",
            type: "POST",
            data: formData,
            contentType: false,
            processData: false,
            dataType: "json",
            success: function (response) {
                if (response.files && response.files[0] && response.files[0].url) {
                    let imageUrl = response.files[0].url;
                    
                    // 업로드 성공 후 로딩 숨김
                    if (loadingOverlay) {
                        loadingOverlay.style.display = 'none';
                    }

                    // SVG 업로드 후 처리
                    //console.log("업로드 성공:", imageUrl);

                    // 일반 첨부용일 때만 insertImage() 호출
                    if (options.insert) {
                        insertImage(imageUrl);
                    }
                    resolve(imageUrl);
                } else {
                    alert("이미지 업로드에 실패 하였습니다.");
                    reject("이미지 업로드에 실패 하였습니다");
                }
            },
            error: function (error) {
                console.error("이미지 업로드 실패:", error);
                reject(error);
            },
            complete: function () {
                // ✅ 업로드가 끝나면 무조건 로딩 숨김
                if (loadingOverlay) {
                    loadingOverlay.style.display = 'none';
                }
            }
        });
    });
}




$('#editor').on('dragover', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).addClass('dragover');
});

$('#editor').on('dragleave', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('dragover');
});

$('#editor').on('drop', function (e) {
    e.preventDefault();
    e.stopPropagation();
    $(this).removeClass('dragover');

    const files = e.originalEvent.dataTransfer.files;
    if (!files.length) return;

    for (let i = 0; i < files.length; i++) {
        uploadImage(files[i]);
    }
});

// 파일 선택 이미지 업로드 (input[type="file"])
$('#image-upload').change(function (event) {
    const files = event.target.files;
    if (!files.length) return;

    for (let i = 0; i < files.length; i++) {
        uploadImage(files[i]);
    }

    $('#image-upload').val('');
});

function insertImage(imageUrl) {
    const img = document.createElement('img');
    img.src = imageUrl;
    img.alt = "Uploaded Image";
    img.crossOrigin = "anonymous";
    img.draggable = false; // 드래그 방지
    img.style.width = '100%'; // 가로는 부모 요소에 맞춤

    img.onload = function () {
        // 이미지의 원본 크기
        const imgWidth = img.naturalWidth;
        const imgHeight = img.naturalHeight;
        // data‑ratio는 이미지의 원본 높이/원본 너비
        const ratio = imgHeight / imgWidth;

        const editor = document.getElementById('editor');
        const editorWidth = editor.offsetWidth; // 에디터의 가로 크기

        // .resizable_wrap 생성
        const wrap = document.createElement('div');
        wrap.classList.add('resizable_wrap');

        // .resizable div 생성
        const wrapper = document.createElement('div');
        wrapper.classList.add('resizable');

        // 에디터보다 클 경우 100%, 작으면 원본 크기 사용하여 width 설정
        if (imgWidth > editorWidth) {
            wrapper.style.width = '100%';
        } else {
            wrapper.style.width = `${imgWidth}px`;
        }
        // 원본 크기를 data 속성에 저장 (항상 갱신)
        wrapper.dataset.originalWidth = imgWidth;
        wrapper.dataset.originalHeight = imgHeight;
        wrapper.dataset.ratio = ratio;

        // 초기 높이 계산 (현재 width에 data‑ratio를 곱함)
        wrapper.style.height = `${wrapper.offsetWidth * ratio}px`;

        // 크기 조절 핸들 추가
        const resizeHandle = document.createElement('div');
        resizeHandle.classList.add('resize-handle');

        // 이미지와 핸들 추가
        wrapper.appendChild(img);
        wrapper.appendChild(resizeHandle);
        wrap.appendChild(wrapper);

        // 줄바꿈용 <p><br></p> 추가
        const paragraph = document.createElement('p');
        paragraph.appendChild(document.createElement('br'));

        // 현재 커서 위치 확인 후 에디터 내 삽입
        const selection = window.getSelection();
        const range = selection.rangeCount ? selection.getRangeAt(0) : null;

        if (range) {
            let container = range.commonAncestorContainer;
            if (container.nodeType === Node.TEXT_NODE) {
                container = container.parentElement;
            }
            if ($(container).closest('#editor').length) {
                range.deleteContents();
                range.insertNode(wrap);
                wrap.parentNode.insertBefore(paragraph, wrap.nextSibling);
                range.setStart(paragraph, 0);
                range.collapse(true);
                selection.removeAllRanges();
                selection.addRange(range);
            } else {
                editor.appendChild(wrap);
                editor.appendChild(paragraph);
            }
        } else {
            editor.appendChild(wrap);
            editor.appendChild(paragraph);
        }

        // 갱신: 현재 wrapper의 높이를 data‑ratio에 따라 재계산
        wrapper.style.height = `${wrapper.offsetWidth * ratio}px`;

        // 창 크기가 변경될 때 높이 업데이트
        function updateHeight() {
            const currentWidth = wrapper.offsetWidth;
            const originalWidth = parseFloat(wrapper.dataset.originalWidth) || currentWidth;
            const originalHeight = parseFloat(wrapper.dataset.originalHeight) || currentWidth * ratio;
            const originalRatio = parseFloat(wrapper.dataset.ratio) || (originalHeight / originalWidth);

            wrapper.style.height = `${currentWidth * originalRatio}px`;
        }
        window.addEventListener("resize", updateHeight);

        // 크기 조절 기능 활성화 (이 함수 내에서 리사이즈 후에도 data‑속성이 갱신되도록 구현 가능)
        makeImageResizableWithObserver($(wrapper));
    };
}
