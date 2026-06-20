//#region 셀 선택 및 툴바 표시

let selectedCells = [];

// 셀 클릭 이벤트: Ctrl 키와 함께 클릭 시 셀 선택 토글
$('#editor').on('click', 'td, th', function (e) {
    if (e.ctrlKey) {
        // Ctrl + 클릭: 선택/해제 토글 기능 유지
        const cell = this;
        const isSelected = $(cell).hasClass('selected-cell');

        if (isSelected) {
            // 이미 선택된 셀인 경우 선택 해제
            $(cell).removeClass('selected-cell');
            selectedCells = selectedCells.filter(c => c !== cell);
        } else {
            // 선택되지 않은 셀인 경우 선택
            $(cell).addClass('selected-cell');
            selectedCells.push(cell);
        }

        if (selectedCells.length > 0) {
            // 마지막 클릭 위치(e.pageX, e.pageY)를 기준으로 툴바 표시
            showCellToolbar(e.pageX, e.pageY);
        } else {
            $('#cell-toolbar').fadeOut(0); // 선택된 셀이 없으면 툴바 숨김
        }
    } else {
        // Ctrl 없이 클릭 시 모든 선택된 셀 해제
        $('td, th').removeClass('selected-cell');
        selectedCells = [];
        $('#cell-toolbar').fadeOut(0);
    }
});

// 선택된 셀 강조 및 툴바 위치 계산
function showCellToolbar(x, y) {
    const $cellToolbar = $('#cell-toolbar');
    const $editorContainer = $('#editor-container');

    $cellToolbar.css({
        top: y + 'px',
        left: x + 'px',
        position: 'absolute'
    }).fadeIn(0);

    // 화면을 넘어가지 않도록 조정
    ensureToolbarWithinBounds($cellToolbar, $editorContainer);
}

// 툴바가 컨테이너 내에 있도록 위치 조정하는 함수
function ensureToolbarWithinBounds($toolbar, $container) {
    const toolbarRect = $toolbar[0].getBoundingClientRect();
    const containerRect = $container[0].getBoundingClientRect();

    let newTop = parseInt($toolbar.css('top'));
    let newLeft = parseInt($toolbar.css('left'));

    if (toolbarRect.bottom > containerRect.bottom) {
        newTop -= (toolbarRect.bottom - containerRect.bottom);
    }
    if (toolbarRect.right > containerRect.right) {
        newLeft -= (toolbarRect.right - containerRect.right);
    }
    if (toolbarRect.top < containerRect.top) {
        newTop = containerRect.top;
    }
    if (toolbarRect.left < containerRect.left) {
        newLeft = containerRect.left;
    }

    $toolbar.css({
        top: newTop + 'px',
        left: newLeft + 'px'
    });
}

//#endregion 셀 선택 및 툴바 표시


//#region 테이블 그리드 매핑 함수

/**
 * 테이블을 그리드로 매핑하여 각 셀의 위치를 반환합니다.
 * @param {jQuery} $table - jQuery 객체로 된 테이블 요소
 * @returns {Array} grid - 2차원 배열로 매핑된 셀 위치
 */
function mapTableGrid($table) {
    const grid = [];
    const rows = $table.find('tr');

    rows.each(function (rowIndex, row) {
        if (!grid[rowIndex]) {
            grid[rowIndex] = [];
        }
        let colIndex = 0;
        $(row).children('td, th').each(function (cellIndex, cell) {
            // colspan과 rowspan 속성 가져오기
            const colspan = parseInt($(cell).attr('colspan')) || 1;
            const rowspan = parseInt($(cell).attr('rowspan')) || 1;

            // 그리드에서 빈 위치 찾기
            while (grid[rowIndex][colIndex]) {
                colIndex++;
            }

            // 현재 셀을 그리드에 매핑
            for (let i = 0; i < rowspan; i++) {
                for (let j = 0; j < colspan; j++) {
                    if (!grid[rowIndex + i]) {
                        grid[rowIndex + i] = [];
                    }
                    grid[rowIndex + i][colIndex + j] = cell;
                }
            }
            colIndex += colspan;
        });
    });

    return grid;
}

//#endregion 테이블 그리드 매핑 함수


//#region 셀 병합 기능

// 셀 병합 버튼 클릭 이벤트
$('#merge-cells-btn').click(function () {
    if (selectedCells.length <= 1) {
        alert('병합할 셀을 2개 이상 선택해주세요.');
        return;
    }

    const $table = $(selectedCells[0]).closest('table');
    const grid = mapTableGrid($table);

    // 선택된 셀들의 행과 열 인덱스 추출
    const cellPositions = selectedCells.map(cell => {
        for (let r = 0; r < grid.length; r++) {
            for (let c = 0; c < grid[r].length; c++) {
                if (grid[r][c] === cell) {
                    return {
                        row: r,
                        col: c
                    };
                }
            }
        }
    });

    // 최소 및 최대 행, 열 인덱스 계산
    const rowsIndices = cellPositions.map(pos => pos.row);
    const colsIndices = cellPositions.map(pos => pos.col);

    const minRow = Math.min(...rowsIndices);
    const maxRow = Math.max(...rowsIndices);
    const minCol = Math.min(...colsIndices);
    const maxCol = Math.max(...colsIndices);

    const rowSpan = maxRow - minRow + 1;
    const colSpan = maxCol - minCol + 1;

    // 선택된 영역이 완전한 사각형을 형성하는지 검증
    for (let r = minRow; r <= maxRow; r++) {
        for (let c = minCol; c <= maxCol; c++) {
            const cell = grid[r][c];
            if (!selectedCells.includes(cell)) {
                alert('선택된 셀들은 병합할 수 없습니다.');
                return;
            }

            const $cell = $(cell);
            const existingRowSpan = parseInt($cell.attr('rowspan')) || 1;
            const existingColSpan = parseInt($cell.attr('colspan')) || 1;

            if (existingRowSpan > 1 || existingColSpan > 1) {
                alert('이미 병합된 셀은 병합할 수 없습니다.');
                return;
            }
        }
    }

    // 좌상단 셀 선택 및 rowspan, colspan 설정
    const topLeftCell = grid[minRow][minCol];
    const $topLeftCell = $(topLeftCell);

    if (rowSpan > 1) {
        $topLeftCell.attr('rowspan', rowSpan);
    }
    if (colSpan > 1) {
        $topLeftCell.attr('colspan', colSpan);
    }

    // 나머지 셀들 제거
    for (let r = minRow; r <= maxRow; r++) {
        for (let c = minCol; c <= maxCol; c++) {
            if (r === minRow && c === minCol) continue;
            const cell = grid[r][c];
            $(cell).remove();
        }
    }

    // 선택 초기화 및 툴바 숨김
    selectedCells = [];
    $('td, th').removeClass('selected-cell');
    $('#cell-toolbar').fadeOut(0);
});

//#endregion 셀 병합 기능


//#region 셀 병합 해제 기능

$('#unmerge-cells-btn').click(function () {
    if (selectedCells.length !== 1) {
        alert('병합 해제는 병합된 셀 하나만 선택해야 합니다.');
        return;
    }

    const $table = $(selectedCells[0]).closest('table');
    const grid = mapTableGrid($table);

    // 병합된 셀 정보 가져오기
    const $mergedCell = $(selectedCells[0]);
    const rowspan = parseInt($mergedCell.attr('rowspan')) || 1;
    const colspan = parseInt($mergedCell.attr('colspan')) || 1;

    if (rowspan === 1 && colspan === 1) {
        alert('이 셀은 병합되지 않았습니다.');
        return;
    }

    const mergedCellPosition = (() => {
        for (let r = 0; r < grid.length; r++) {
            for (let c = 0; c < grid[r].length; c++) {
                if (grid[r][c] === $mergedCell[0]) {
                    return {
                        row: r,
                        col: c
                    };
                }
            }
        }
        return null;
    })();

    if (!mergedCellPosition) {
        alert('병합된 셀의 위치를 확인할 수 없습니다.');
        return;
    }

    const {
        row: startRow,
        col: startCol
    } = mergedCellPosition;

    // 병합된 셀의 rowspan 및 colspan에 따라 셀 추가
    for (let r = startRow; r < startRow + rowspan; r++) {
        for (let c = startCol; c < startCol + colspan; c++) {
            if (r === startRow && c === startCol) {
                // 기존 병합된 셀은 rowspan, colspan 제거
                $mergedCell.removeAttr('rowspan').removeAttr('colspan');
            } else {
                // 나머지 셀 복원
                const $newCell = $('<td><div><br></div></td>').css({
                    border: '1px solid #ddd',
                    padding: '5px',
                });

                // 그리드 위치에 따라 셀 삽입
                const $row = $table.find('tr').eq(r);
                // 그리드 내 현재 셀의 위치를 찾기 (colSpan을 고려)
                let insertBefore = null;
                let currentCol = 0;
                $row.children('td, th').each(function () {
                    const colspanAttr = parseInt($(this).attr('colspan')) || 1;
                    if (currentCol === c) {
                        insertBefore = this;
                        return false; // 루프 종료
                    }
                    currentCol += colspanAttr;
                });
                if (insertBefore) {
                    $(insertBefore).before($newCell);
                } else {
                    $row.append($newCell);
                }

                // 그리드 업데이트
                grid[r][c] = $newCell[0];
            }
        }
    }

    // 선택 초기화 및 툴바 숨김
    selectedCells = [];
    $('td, th').removeClass('selected-cell');
    $('#cell-toolbar').fadeOut(0);
});

//#endregion 셀 병합 해제 기능


//#region 셀 배경색 변경

$('#cell-bg-color-btn').on('click', function () {
    $('#cell-bg-color-picker').click();
});

$('#cell-bg-color-picker').on('input', function () {
    const selectedColor = $(this).val();

    // 선택된 셀의 배경 색상 변경
    $('.selected-cell').each(function () {
        $(this).css('background-color', selectedColor);
        $('#cell-bg-color-btn').css('background-color', selectedColor);

        // 선택 후 초기화 (필요 시 주석 해제)
        // selectedCells = [];
        // $('td, th').removeClass('selected-cell');
    });
});

//#endregion 셀 배경색 변경


//#region 문서 외부 클릭 이벤트 처리

// 외부 클릭 시 초기화
$(document).on('click', function (e) {
    if (!$(e.target).closest('#editor').length && !$(e.target).closest('#toolbar').length) {
        savedSelection = null;
    }

    const $grid = $('#table-grid');
    if (!$(e.target).closest('#table-grid').length && !$(e.target).is('#insert-table-btn')) {
        $grid.fadeOut(0);
    }

    // 표 셀 선택 해제
    if (!$(e.target).closest('td, th, #cell-toolbar').length) {
        selectedCells = [];
        $('td, th').removeClass('selected-cell');
        $('#cell-toolbar').fadeOut(0);
    }

    const isInsideEditor = $(e.target).closest('#editor').length > 0; // 클릭한 요소가 #editor 내부인지 확인
    const isResizable = $(e.target).closest('.resizable').length > 0; // 클릭한 요소가 .resizable인지 확인
    const isToolbar = $(e.target).closest('#image-toolbar').length > 0; // 클릭한 요소가 툴바인지 확인

    if (!isResizable && !isToolbar) {
        $('.resizable').removeClass('selected'); // 모든 이미지에서 selected 제거
        $('#image-toolbar').fadeOut(0); // 툴바 숨기기
    }
});

// #cell-toolbar 내부 클릭 시 이벤트 전파 차단
$('#cell-toolbar').on('click', function (e) {
    e.stopPropagation();
});

//#endregion 문서 외부 클릭 이벤트 처리


//#region 정렬 관련 함수

function applyAlignmentToSelectedCells(alignment) {
    const selection = window.getSelection();
    const range = selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

    if (range) {
        const ancestorTable = $(range.commonAncestorContainer).closest('table');

        // 표 내부일 경우
        if (ancestorTable.length) {
            // 드래그로 선택된 셀들 가져오기
            const selectedCells = ancestorTable.find('td, th').filter(function () {
                const cellRange = document.createRange();
                cellRange.selectNodeContents(this);

                // 드래그로 선택된 범위와 셀이 겹치는지 확인
                return (
                    range.compareBoundaryPoints(Range.START_TO_END, cellRange) !== -1 &&
                    range.compareBoundaryPoints(Range.END_TO_START, cellRange) !== 1
                );
            });

            // Ctrl + 클릭으로 선택된 셀은 제외
            const filteredCells = selectedCells.filter(function () {
                return !$(this).hasClass('selected-cell'); // 'selected-cell' 클래스 제외
            });

            // 선택된 셀만 정렬 적용
            filteredCells.each(function () {
                $(this).css('text-align', alignment);
            });

            return true; // 표 정렬 적용 완료
        }
    }

    return false; // 표 정렬 미적용
}

function applyAlignmentToSelectedCellsOrEditor(alignment) {
    const selection = window.getSelection();
    const range = selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

    if (range) {
        const commonAncestor = range.commonAncestorContainer;

        // 선택된 범위 내의 모든 셀(td, th)을 가져옴
        const selectedCells = $(commonAncestor)
            .closest('table')
            .find('td, th')
            .filter(function () {
                const cellRange = document.createRange();
                cellRange.selectNodeContents(this);

                // 셀이 선택된 범위와 겹치는지 확인
                return (
                    range.compareBoundaryPoints(Range.START_TO_END, cellRange) !== -1 &&
                    range.compareBoundaryPoints(Range.END_TO_START, cellRange) !== 1
                );
            });

        if (selectedCells.length > 0) {
            // 선택된 모든 셀에 정렬 적용
            selectedCells.each(function () {
                $(this).css('text-align', alignment);
            });
        } else {
            // 선택된 범위가 없거나 표 외부인 경우 전체 에디터에 정렬 적용
            $('#editor').children(':not(table)').css('text-align', alignment);
        }
    }
}

function applyAlignmentToTableCells(range, alignment) {
    const selectedCells = getSelectedCellsInRange(range);

    if (selectedCells.length > 0) {
        selectedCells.each(function () {
            $(this).css('text-align', alignment);
        });
        return true; // 표 내부 정렬 적용 완료
    }
    return false; // 표 내부 정렬 없음
}

// 범위 내에서 선택된 셀(td, th) 가져오기
function getSelectedCellsInRange(range) {
    const selectedCells = [];
    const ancestor = range.commonAncestorContainer;

    // 범위가 표 내부인지 확인
    const table = $(ancestor).closest('table');
    if (table.length) {
        const startContainer = range.startContainer;
        const endContainer = range.endContainer;

        const startCell = $(startContainer).closest('td, th')[0];
        const endCell = $(endContainer).closest('td, th')[0];

        if (startCell && endCell) {
            let selecting = false;

            table.find('tr').each(function () {
                $(this).children('td, th').each(function () {
                    if (this === startCell) {
                        selecting = true; // 선택 시작
                        selectedCells.push(this);
                    } else if (this === endCell) {
                        selectedCells.push(this); // 선택 끝
                        selecting = false; // 선택 종료
                    } else if (selecting) {
                        selectedCells.push(this); // 선택 중간 셀
                    }
                });

                if (!selecting && selectedCells.length > 0) {
                    return false; // 선택 종료 시 루프 탈출
                }
            });
        }
    }

    return $(selectedCells); // jQuery 객체로 반환
}

//#endregion 정렬 관련 함수


//#region 테이블 삽입 기능

/**
 * 주어진 행과 열 수로 테이블 생성 및 에디터에 삽입
 * @param {number} rows - 테이블 행 수
 * @param {number} cols - 테이블 열 수
 */
function insertTable(rows, cols) {
    if (savedSelection) {
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(savedSelection); // 이전 커서 위치 복원
    }

    const selection = window.getSelection();
    const range = selection.rangeCount ? selection.getRangeAt(0) : null;
    
    if (range) {
        let container = range.commonAncestorContainer;
        if (container.nodeType === Node.TEXT_NODE) container = container.parentElement;

        // ✅ 테이블 내부(td, th)인지 확인 후 생성 제한
        if ($(container).closest('td, th').length) {
            alert("테이블 내부에서는 테이블을 생성할 수 없습니다.");
            return;
        }
    }

    // ✅ 테이블을 정확한 행*열로 생성
    let tableHTML = `
        <div class="resizable-table" style="width: 100%; position: relative;">
            <div class="rb_editor_table_wrap">
                <div class="rb_editor_table_wrap_inner">
                    <table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">

                        <tbody>
                            ${Array(rows).fill(`<tr>${Array(cols).fill('<td style="border: 1px solid #ddd; padding: 10px; height:40px;"><div><br></div></td>').join('')}</tr>`).join('')}
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="resizable-table-handle" style="width: 5px; height: 100%; position: absolute; right: 0; top: 0; cursor: ew-resize; background: rgba(170, 32, 255, 0.3);"></div>
        </div>
        <div><br></div>
    `;

    const $table = $(tableHTML);
    const $editor = $('#editor');

    if (range) {
        let container = range.commonAncestorContainer;
        if (container.nodeType === Node.TEXT_NODE) container = container.parentElement;

        if ($(container).closest('#editor').length) {
            range.deleteContents();
            range.insertNode($table[0]);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
        } else {
            $editor.append($table);
        }
    } else {
        $editor.append($table);
    }

    enableResizableTable($table);
}



/**
 * 테이블 크기 조정 기능 활성화
 * @param {HTMLElement} table - 크기 조정할 테이블 요소
 */
function enableResizableTable(table) {
    // 테이블이 jQuery 객체라면 DOM 요소로 변환
    if (table instanceof jQuery) {
        table = table.get(0);
    }

    // 테이블이 NodeList(배열)라면 첫 번째 요소 선택
    if (NodeList.prototype.isPrototypeOf(table) || Array.isArray(table)) {
        table = table[0];
    }

    // 유효한 DOM 요소인지 확인
    if (!(table instanceof HTMLElement)) {
        //console.error("⚠️ enableResizableTable: 유효한 테이블 요소가 아닙니다.", table);
        return;
    }

    let handle = table.querySelector('.resizable-table-handle');

    // ✅ 핸들이 없으면 자동 추가
    if (!handle) {
        //console.warn('resizable-table-handle 요소가 없습니다. 자동 추가합니다.');
        handle = document.createElement("div");
        handle.classList.add("resizable-table-handle");
        table.appendChild(handle);
    }

    let isResizing = false;
    let startX = 0;
    let startWidth = 0;

    function startResize(event) {
        isResizing = true;
        startX = event.type.includes('touch') ? event.touches[0].clientX : event.clientX;
        startWidth = table.offsetWidth;

        document.addEventListener('mousemove', doResize);
        document.addEventListener('mouseup', stopResize);
        document.addEventListener('touchmove', doResize, { passive: false });
        document.addEventListener('touchend', stopResize, { passive: false });

        event.preventDefault();
    }
    
    function doResize(event) {
        if (!isResizing) return;

        const moveX = event.type.includes('touch') ? event.touches[0].clientX : event.clientX;
        const newWidth = startWidth + (moveX - startX);

        if (newWidth > 100) { // 최소 너비 제한
            table.style.width = `${newWidth}px`;
        }
    }

    function stopResize() {
        isResizing = false;
        document.removeEventListener('mousemove', doResize);
        document.removeEventListener('mouseup', stopResize);
        document.removeEventListener('touchmove', doResize);
        document.removeEventListener('touchend', stopResize);
    }

    // 기존 이벤트 제거 후 다시 바인딩
    handle.removeEventListener('mousedown', startResize);
    handle.removeEventListener('touchstart', startResize);
    handle.addEventListener('mousedown', startResize);
    handle.addEventListener('touchstart', startResize, { passive: false });
}



/**
 * 에디터 내 모든 테이블을 감싸고 핸들 추가
 */
function wrapTablesWithEditorWrap() {
    $('#editor table').each(function () {
        const $table = $(this);

        if ($table.closest('.resizable-table').length) return; // 이미 감싸져 있으면 패스

        // 테이블 스타일 초기화 및 새로운 스타일 적용
        $table.removeAttr('style').css({
            'width': '100%',
            'border-collapse': 'collapse',
            'border': '1px solid rgb(221, 221, 221)',
            'table-layout': 'fixed'
        });

        // 테이블 내부의 td 및 th 스타일 초기화 및 새로운 스타일 적용
        $table.find('td, th').each(function () {
            $(this).removeAttr('style').css({
                'border': '1px solid rgb(221, 221, 221)',
                'padding': '10px',
                'height': '40px',
                'position': 'relative'
            });
        });

        const $wrapper = $('<div class="resizable-table" style="width: 100%; position: relative;"></div>');
        //const $handle = $('<div class="resizable-table-handle" style="width: 5px; height: 100%; position: absolute; right: 0; top: 0; cursor: ew-resize; background: rgba(170, 32, 255, 0.3)"></div>');

        // 기존 래퍼 유지
        const $wrapInner = $('<div class="rb_editor_table_wrap"><div class="rb_editor_table_wrap_inner"></div></div>');

        // 테이블을 감싸기만 함 (이동 X, 제거 X)
        $table.wrap($wrapInner);

        // 최종 래퍼 구성
        $table.parent().parent().wrap($wrapper);
        //$table.parent().parent().parent().append($handle);

        enableResizableTable($table.closest('.resizable-table'));
    });
}





/**
 * 주어진 테이블의 셀 크기 조정 기능 활성화
 * @param {jQuery} $table - 크기 조정할 테이블 요소
 */
function enableResizableTableCells($table) {
    const resizeMargin = 10; // 커서 반경 확대
    let isResizing = false;
    let resizingCell = null;
    const passiveOptions = { passive: true };

    $table.css({
        "table-layout": "fixed", // 테이블 레이아웃 고정
        "width": "100%", // 테이블 전체 폭 100%
    });

    $table.find('td, th').each(function () {
        const $cell = $(this);
        $cell.css({ position: 'relative' });

        // ✅ 마지막 열인지 확인하여 크기 조정 방지
        const isLastColumn = $cell.is(':last-child');
        if (isLastColumn) return; // 마지막 열은 크기 조정 X

        function handleMove(event) {
            const isTouch = event.type.includes('touch');
            const offsetX = isTouch ? event.touches[0].clientX - $cell.offset().left : event.offsetX;
            const offsetY = isTouch ? event.touches[0].clientY - $cell.offset().top : event.offsetY;
            const cellWidth = $cell.outerWidth();
            const cellHeight = $cell.outerHeight();
            const $row = $cell.closest('tr');

            $table.find('tr').removeClass('resize-row-highlight');
            $cell.removeClass('resize-right-highlight resize-bottom-highlight');

            // ✅ 마지막 열은 커서 변경 X
            if (isLastColumn) {
                $cell.css('cursor', 'text');
                return;
            }

            if (offsetX > cellWidth - resizeMargin && offsetY > cellHeight - resizeMargin) {
                $cell.addClass('resize-right-highlight resize-bottom-highlight');
                $row.addClass('resize-row-highlight');
                $cell.css('cursor', 'se-resize');
            } else if (offsetX > cellWidth - resizeMargin) {
                $cell.addClass('resize-right-highlight');
                $cell.css('cursor', 'ew-resize');
            } else if (offsetY > cellHeight - resizeMargin) {
                $row.addClass('resize-row-highlight');
                $cell.css('cursor', 'ns-resize');
            } else {
                $cell.css('cursor', 'text');
            }
        }

        $cell.on('mousemove', handleMove);
        $cell.get(0).addEventListener('touchmove', handleMove, passiveOptions);

        function handleStart(event) {
            const isTouch = event.type.includes('touch');
            const touch = isTouch ? event.touches[0] : null;
            const startX = isTouch ? touch.clientX : event.pageX;
            const startY = isTouch ? touch.clientY : event.pageY;
            const cellWidth = $cell.outerWidth();
            const cellHeight = $cell.outerHeight();
            const $table = $cell.closest('table');

            if (isLastColumn) return; // ✅ 마지막 열이면 크기 조정 시작 X

            const isResizeArea = (
                startX > $cell.offset().left + cellWidth - resizeMargin ||
                startY > $cell.offset().top + cellHeight - resizeMargin
            );

            if (!isResizeArea) return;

            event.preventDefault();
            isResizing = true;
            resizingCell = $cell;

            const startWidth = $cell.outerWidth();
            const startHeight = $cell.outerHeight();
            const cellIndex = $cell.index();
            const $row = $cell.closest('tr');
            const rowIndex = $row.index();

            function doResize(e) {
                const isTouchMove = e.type.includes('touch');
                const moveX = isTouchMove ? e.touches[0].clientX : e.pageX;
                const moveY = isTouchMove ? e.touches[0].clientY : e.pageY;

                if (isResizing && resizingCell) {
                    if (startX > $cell.offset().left + cellWidth - resizeMargin) {
                        const newWidth = startWidth + (moveX - startX);
                        if (newWidth > 30) {
                            $table.find('tr').each(function () {
                                $(this).children().eq(cellIndex).css('width', `${newWidth}px`);
                            });
                        }
                    }
                    if (startY > $cell.offset().top + cellHeight - resizeMargin) {
                        const newHeight = startHeight + (moveY - startY);
                        if (newHeight > 20) {
                            $table.find('tr').eq(rowIndex).children().css('height', `${newHeight}px`);
                        }
                    }
                }
            }

            function stopResize() {
                isResizing = false;
                resizingCell = null;
                $(document).off('mousemove', doResize);
                $(document).off('mouseup', stopResize);
                document.removeEventListener('touchmove', doResize, passiveOptions);
                document.removeEventListener('touchend', stopResize, passiveOptions);
            }

            $(document).on('mousemove', doResize);
            $(document).on('mouseup', stopResize);
            document.addEventListener('touchmove', doResize, passiveOptions);
            document.addEventListener('touchend', stopResize, passiveOptions);
        }

        $cell.on('mousedown', handleStart);
        $cell.get(0).addEventListener('touchstart', handleStart, { passive: false });

        $cell.on('mouseleave touchend', function () {
            if (!isResizing) {
                $table.find('tr').removeClass('resize-row-highlight');
                $cell.removeClass('resize-right-highlight resize-bottom-highlight');
            }
        });
    });
}



//#endregion 셀 크기 조정 기능


//#region 테이블 리사이징 초기화 (기존 테이블에 적용)

function initializeResizableTables() {
    $('#editor table').each(function () {
        enableResizableTableCells($(this));
    });
}

//#endregion 테이블 리사이징 초기화 (기존 테이블에 적용)


//#region MutationObserver 설정

/**
 * MutationObserver를 사용하여 새로운 테이블이 추가될 때 자동으로 크기 조정 기능을 적용합니다.
 */
const tableObserver = new MutationObserver(function (mutationsList) {
    for (let mutation of mutationsList) {
        if (mutation.type === 'childList') {
            mutation.addedNodes.forEach(node => {
                if ($(node).is('table')) {
                    enableResizableTableCells($(node));
                } else {
                    // 만약 노드가 테이블을 포함하고 있다면
                    $(node).find('table').each(function () {
                        enableResizableTableCells($(this));
                    });
                }
            });
        }
    }
});

// #editor 내부의 변경 사항 관찰
tableObserver.observe(document.getElementById('editor'), {
    childList: true,
    subtree: true
});

//#endregion MutationObserver 설정


//#region 이벤트 핸들러 등록

// 정렬 버튼 이벤트 예시 (Assuming you have buttons for alignment)
$('#align-left-btn').click(function () {
    applyAlignmentToSelectedCells('left');
});

$('#align-center-btn').click(function () {
    applyAlignmentToSelectedCells('center');
});

$('#align-right-btn').click(function () {
    applyAlignmentToSelectedCells('right');
});



// 페이지 로드 시 실행
$(document).ready(function () {
    wrapTablesWithEditorWrap();
    initializeResizableTables();

    // MutationObserver로 동적 감지
    const observer = new MutationObserver(() => {
        wrapTablesWithEditorWrap();
    });

    observer.observe(document.getElementById('editor'), { childList: true, subtree: true });
});
