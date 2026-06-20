//===============================================================================
// #region Fabric.js 캔버스 텍스트 및 이미지 삽입 관련 이벤트 및 함수
//===============================================================================

/** 이미지 업로드 변경 이벤트: 선택한 이미지를 Fabric 캔버스에 추가 */
$('#canvas-image-upload').change(function (event) {
    const files = event.target.files;
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function (e) {
                fabric.Image.fromURL(e.target.result, function (img) {
                    // 이미지 중앙 배치 및 캔버스에 추가
                    img.set({
                        left: fabricCanvas.width / 2 - img.width / 2,
                        top: fabricCanvas.height / 2 - img.height / 2,
                        selectable: true
                    });
                    fabricCanvas.add(img);
                    fabricCanvas.renderAll();
                });
            }
            reader.readAsDataURL(file);
        } else {
            alert('이미지 파일을 선택해주세요.');
        }
    }
    // 파일 입력 초기화
    $('#canvas-image-upload').val('');
});

/** 텍스트 삽입 버튼 클릭 이벤트: 사용자 입력 텍스트를 Fabric 캔버스에 IText 객체로 추가 */
$('#canvas-insert-text-btn').click(function () {
    const text = prompt("추가할 텍스트를 입력하세요:", "새 텍스트");
    if (text) {
        const itext = new fabric.IText(text, {
            left: fabricCanvas.width / 2,
            top: fabricCanvas.height / 2,
            fontSize: 20,
            fill: '#000000',
            selectable: true,
            objectCaching: false
        });
        fabricCanvas.add(itext);
        fabricCanvas.setActiveObject(itext);
    }
});

/** 공용 함수: 부분 선택이 있으면 부분만, 없으면 전체 텍스트에 스타일 토글 */
function toggleCanvasTextStyle(styleName, toggleValues) {
    const activeObject = fabricCanvas.getActiveObject();
    if (!activeObject || activeObject.type !== 'i-text') return;

    // 선택 범위 가져오기
    const start = activeObject.selectionStart;
    const end = activeObject.selectionEnd;

    // 전체 텍스트에 적용하는 경우
    if (start === end) {
        const current = activeObject[styleName];
        // 현재 스타일값을 토글
        const newVal = (current === toggleValues[0]) ? toggleValues[1] : toggleValues[0];
        activeObject.set(styleName, newVal);
    } else {
        // 부분 선택이 있는 경우
        const currentStyles = activeObject.getSelectionStyles(start, end);
        let allSame = true;
        for (let i = 0; i < currentStyles.length; i++) {
            if (currentStyles[i][styleName] !== toggleValues[0]) {
                allSame = false;
                break;
            }
        }
        // 스타일 토글 값 결정
        const newVal = allSame ? toggleValues[1] : toggleValues[0];
        activeObject.setSelectionStyles({
            [styleName]: newVal
        }, start, end);
    }
    fabricCanvas.renderAll();
}

/** 캔버스 굵게 버튼 클릭 이벤트 */
$('#canvas-bold-btn').click(function () {
    // fontWeight를 'bold' <-> 'normal' 로 토글
    toggleCanvasTextStyle('fontWeight', ['bold', 'normal']);
});

/** 캔버스 기울임 버튼 클릭 이벤트 */
$('#canvas-italic-btn').click(function () {
    // fontStyle을 'italic' <-> 'normal' 로 토글
    toggleCanvasTextStyle('fontStyle', ['italic', 'normal']);
});

/** 캔버스 밑줄 버튼 클릭 이벤트 */
$('#canvas-underline-btn').click(function () {
    const activeObject = fabricCanvas.getActiveObject();
    if (!activeObject || activeObject.type !== 'i-text') return;

    const start = activeObject.selectionStart;
    const end = activeObject.selectionEnd;
    if (start === end) {
        // 전체 텍스트 밑줄 토글
        activeObject.set('underline', !activeObject.get('underline'));
    } else {
        // 부분 선택 영역에 대해 밑줄 토글
        const currentStyles = activeObject.getSelectionStyles(start, end);
        let allUnderlined = true;
        for (let i = 0; i < currentStyles.length; i++) {
            if (!currentStyles[i].underline) {
                allUnderlined = false;
                break;
            }
        }
        const newVal = !allUnderlined;
        activeObject.setSelectionStyles({
            underline: newVal
        }, start, end);
    }
    fabricCanvas.renderAll();
});

/** 캔버스 텍스트 색상 변경 이벤트 */
$('#canvas-text-color-picker').on('input', function () {
    const color = $(this).val();
    applyCanvasTextColor(color);
});

/** 캔버스 글자 크기 변경 이벤트: 직접 입력된 값 적용 */
$('#canvas-font-size-btn').change(function () {
    const fontSize = parseInt($(this).val(), 10);
    const activeObject = fabricCanvas.getActiveObject();
    if (activeObject && activeObject.type === 'i-text') {
        activeObject.set('fontSize', fontSize);
        fabricCanvas.renderAll();
    }
});

/** 펜 설정 토글 버튼 클릭 이벤트 (옵션 예시) */
$('#pen-settings-btn').click(function () {
    $('#pen-settings').toggle();
});

/** 펜 색상 변경 이벤트 */
$('#pen-color-picker').change(function () {
    if (fabricCanvas.isDrawingMode) {
        fabricCanvas.freeDrawingBrush.color = $(this).val();
    }
});

/** 펜 두께 변경 이벤트 */
$('#pen-thickness').change(function () {
    if (fabricCanvas.isDrawingMode) {
        fabricCanvas.freeDrawingBrush.width = parseInt($(this).val(), 10) || 2;
    }
});

/** 드로잉 모드 토글 버튼 클릭 이벤트 */
$('#toggle-draw-btn').click(function () {
    if (fabricCanvas) {
        isDrawingMode = !isDrawingMode;
        fabricCanvas.isDrawingMode = isDrawingMode;
        if (isDrawingMode) {
            $(this).addClass('on');
            $('#pen-settings').show();
            // 새로운 PencilBrush 설정 및 기본 속성 적용
            fabricCanvas.freeDrawingBrush = new fabric.PencilBrush(fabricCanvas);
            fabricCanvas.freeDrawingBrush.color = $('#pen-color-picker').val();
            fabricCanvas.freeDrawingBrush.width = parseInt($('#pen-thickness').val(), 10) || 2;
            fabricCanvas.freeDrawingCursor = 'url("image/svg/pen-btn.svg") 3 15, auto';
            console.log('드로잉 모드 활성');
        } else {
            $(this).removeClass('on');
            $('#pen-settings').hide();
            fabricCanvas.freeDrawingCursor = 'default';
            console.log('드로잉 비활성');
        }
    }
});

/** 캔버스 이미지 삽입 버튼 클릭 이벤트: 이미지 업로드 창 트리거 */
$('#canvas-insert-image-btn').click(function () {
    $('#canvas-image-upload').click();
});

/** 캔버스 텍스트 색상 적용 함수 */
function applyCanvasTextColor(color) {
    const obj = fabricCanvas.getActiveObject();
    if (!obj || obj.type !== 'i-text') return;

    const s = obj.selectionStart;
    const e = obj.selectionEnd;

    if (s < e) {
        // 부분 선택 구간만 색상 변경
        obj.setSelectionStyles({
            fill: color
        }, s, e);
    } else {
        // 전체 텍스트 색상 변경
        obj.set('fill', color);
    }

    // 즉시 반영
    obj.dirty = true;
    obj.setCoords();
    fabricCanvas.renderAll();
}

/** 캔버스 텍스트 배경색 변경 함수 */
function applyCanvasBackgroundColor(color) {
    const obj = fabricCanvas.getActiveObject();
    if (!obj || obj.type !== 'i-text') return;

    const s = obj.selectionStart;
    const e = obj.selectionEnd;

    if (s < e) {
        // 부분 선택 구간만 배경색 변경
        obj.setSelectionStyles({
            backgroundColor: color
        }, s, e);
    } else {
        // 전체 텍스트 배경색 변경
        obj.set('backgroundColor', color);
    }

    // 즉시 반영
    obj.dirty = true;
    obj.setCoords();
    fabricCanvas.renderAll();
}

/** 캔버스 폰트 크기 적용 함수 */
function applyCanvasFontSize(fontSize) {
    const obj = fabricCanvas.getActiveObject();
    if (!obj || obj.type !== 'i-text') return;

    const s = obj.selectionStart;
    const e = obj.selectionEnd;

    if (s < e) {
        // 부분 선택 구간만 폰트 크기 변경
        obj.setSelectionStyles({
            fontSize: fontSize
        }, s, e);
    } else {
        // 전체 텍스트 폰트 크기 변경
        obj.set('fontSize', fontSize);
    }

    // 즉시 반영
    obj.dirty = true;
    obj.setCoords();
    fabricCanvas.renderAll();
}

//===============================================================================
// #endregion Fabric.js 캔버스 텍스트 및 이미지 삽입 관련
//===============================================================================
