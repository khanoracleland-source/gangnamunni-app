// 선택된 텍스트에 스타일 적용
function applyStyleToSelection(styleName, styleValue) {
    const selection = window.getSelection();
    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0);
        const fragment = range.cloneContents();
        const wrapper = document.createDocumentFragment();

        Array.from(fragment.childNodes).forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) {
                node.style[styleName] = styleValue;
                wrapper.appendChild(node);
            } else if (node.nodeType === Node.TEXT_NODE) {
                const span = document.createElement('span');
                span.style[styleName] = styleValue;
                span.textContent = node.nodeValue;
                wrapper.appendChild(span);
            }
        });

        range.deleteContents();
        range.insertNode(wrapper);

        // 한글 초성 분리 방지를 위해 normalize() 실행
        document.getElementById('editor').normalize();
    }
}


function toggleStyleToSelection(styleName, styleValue) {
    const selection = window.getSelection();

    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0);
        const fragment = range.extractContents();

        const wrapper = document.createDocumentFragment();

        Array.from(fragment.childNodes).forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) {
                // 기존 요소에 스타일이 이미 있으면 제거, 없으면 추가
                if (node.style[styleName] === styleValue) {
                    node.style[styleName] = ''; // 스타일 제거
                } else {
                    node.style[styleName] = styleValue; // 스타일 추가
                }
                wrapper.appendChild(node);
            } else if (node.nodeType === Node.TEXT_NODE) {
                // 텍스트 노드라면 새 <span> 태그로 감싸고 스타일 적용
                const span = document.createElement("span");
                span.style[styleName] = styleValue;
                span.textContent = node.textContent;
                wrapper.appendChild(span);
            }
        });

        range.deleteContents();
        range.insertNode(wrapper);
    }
}

function normalizeEditorContent() {
    const editor = document.getElementById('editor');

    // ✅ 중첩된 <span> 병합 방지
    editor.querySelectorAll('span').forEach((span) => {
        const parent = span.parentElement;

        // 부모가 <span>이고 스타일이 동일하면 병합
        if (parent.tagName === 'SPAN') {
            const sameStyle = [...span.style].every(prop => parent.style[prop] === span.style[prop]);
            if (sameStyle) {
                while (span.firstChild) {
                    parent.insertBefore(span.firstChild, span);
                }
                parent.removeChild(span);
            }
        }

        // 빈 <span> 제거
        if (!span.textContent.trim()) {
            span.remove();
        }
    });

    // ⚠️ <div> 요소 정리할 때도 기존 스타일 유지
    editor.querySelectorAll('div').forEach((div) => {
        if (div.childNodes.length === 1 && div.firstChild.tagName === 'SPAN') {
            div.replaceWith(...div.childNodes);
        }
    });

    // ⚠️ 한글 조합이 분리되지 않도록 normalize() 적용하되 스타일 병합 방지
    setTimeout(() => {
        editor.normalize();
    }, 100);
}



function toggleFontSizeToSelection(fontSize) {
    const selection = window.getSelection();

    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0);
        const fragment = range.extractContents(); // 선택된 내용을 추출

        const wrapper = document.createDocumentFragment();

        Array.from(fragment.childNodes).forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) {
                // 기존 요소라면 폰트 크기 스타일 추가
                node.style.fontSize = fontSize;
                wrapper.appendChild(node);
            } else if (node.nodeType === Node.TEXT_NODE) {
                // 텍스트 노드라면 새 <span> 태그로 감싸고 폰트 크기 적용
                const span = document.createElement("span");
                span.style.fontSize = fontSize;
                span.textContent = node.textContent;
                wrapper.appendChild(span);
            }
        });

        // 선택된 영역에 새 내용 삽입
        range.deleteContents();
        range.insertNode(wrapper);
    }
}

function applyColorWithoutAffectingStyles(color) {
    const selection = window.getSelection();

    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0); // 선택된 범위 가져오기
        const ancestor = range.commonAncestorContainer;

        // 선택된 범위 내의 텍스트 노드를 탐색
        const walker = document.createTreeWalker(
            ancestor,
            NodeFilter.SHOW_TEXT, {
                acceptNode: (node) => {
                    return range.intersectsNode(node) ?
                        NodeFilter.FILTER_ACCEPT :
                        NodeFilter.FILTER_REJECT;
                },
            }
        );

        let currentNode;
        while ((currentNode = walker.nextNode())) {
            wrapTextNodeInFont(currentNode, color);
        }
    }
}

// <font> 태그 대신 <span> 태그를 사용하도록 변경
function wrapTextNodeInFont(textNode, color) {
    const parent = textNode.parentNode;
    // 이미 동일한 컬러가 적용된 span이 있으면 중복 방지
    if (parent.tagName === 'SPAN' && parent.style.color === color) {
        return;
    }
    const span = document.createElement('span');
    span.style.color = color;
    span.textContent = textNode.nodeValue;
    parent.replaceChild(span, textNode);
}

// 텍스트 노드를 <font> 태그로 감싸고 컬러 적용
function wrapTextNodeInFontTag(textNode, color) {
    wrapTextNodeInFont(textNode, color);
}


// 컬러를 적용하는 함수 (기존 스타일 처리와 동일한 방식)
function applyStyleWithColor(styleProperty, value) {
    const selection = window.getSelection();

    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0);
        const commonAncestor = range.commonAncestorContainer;

        // 선택된 텍스트에만 스타일 적용
        const walker = document.createTreeWalker(
            commonAncestor,
            NodeFilter.SHOW_TEXT, {
                acceptNode: (node) => {
                    return range.intersectsNode(node) ?
                        NodeFilter.FILTER_ACCEPT :
                        NodeFilter.FILTER_REJECT;
                },
            }
        );

        let currentNode;
        while ((currentNode = walker.nextNode())) {
            wrapTextInStyle(currentNode, styleProperty, value);
        }
    }
}

function wrapTextInStyle(textNode, styleProperty, value) {
    const parent = textNode.parentNode;

    // 이미 동일한 스타일이 적용된 경우 처리하지 않음
    if (parent.tagName === 'SPAN' && parent.style[styleProperty] === value) {
        return;
    }

    const span = document.createElement('span');
    span.style[styleProperty] = value;
    span.textContent = textNode.nodeValue;

    parent.replaceChild(span, textNode);
}

function applyColorWithoutDiv(color) {
    const selection = window.getSelection();

    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0); // 선택된 범위 가져오기
        const commonAncestor = range.commonAncestorContainer;

        // 모든 텍스트 노드를 포함한 범위 처리
        const walker = document.createTreeWalker(
            commonAncestor,
            NodeFilter.SHOW_TEXT, {
                acceptNode: (node) => {
                    // 선택된 범위에 포함된 텍스트 노드만 처리
                    return range.intersectsNode(node) ?
                        NodeFilter.FILTER_ACCEPT :
                        NodeFilter.FILTER_REJECT;
                },
            }
        );

        const nodesToWrap = [];
        let currentNode;

        while ((currentNode = walker.nextNode())) {
            nodesToWrap.push(currentNode);
        }

        nodesToWrap.forEach((node) => {
            wrapTextNodeInSpanWithoutDiv(node, color);
        });

        // 선택 영역 삭제 및 새로운 노드 삽입
        const newRange = document.createRange();
        newRange.setStart(nodesToWrap[0], 0);
        newRange.setEnd(nodesToWrap[nodesToWrap.length - 1], nodesToWrap[nodesToWrap.length - 1].length);

        selection.removeAllRanges();
        selection.addRange(newRange);
    }
}

// 텍스트 노드를 <span>으로 감싸고 div 생성을 방지
function wrapTextNodeInSpanWithoutDiv(textNode, color) {
    const parent = textNode.parentNode;

    // 이미 동일한 <span>이 적용되어 있는 경우 처리하지 않음
    if (parent.tagName === 'SPAN' && parent.style.color === color) {
        return;
    }

    const span = document.createElement('span');
    span.style.color = color;

    // 텍스트 노드를 <span>으로 감싸기
    span.textContent = textNode.nodeValue;
    parent.replaceChild(span, textNode);
}

function applyColorToExactSelection(color) {
    const selection = window.getSelection();

    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0); // 선택된 범위 가져오기

        // 선택한 범위를 분리
        const startContainer = range.startContainer;
        const endContainer = range.endContainer;

        if (startContainer === endContainer && startContainer.nodeType === Node.TEXT_NODE) {
            // 단일 텍스트 노드 선택인 경우
            wrapPartialTextInSpan(range, color);
        } else {
            // 여러 노드가 선택된 경우
            splitAndWrapRange(range, color);
        }
    }
}

function splitAndWrapRange(range, color) {
    const commonAncestor = range.commonAncestorContainer;

    // 선택된 범위 내의 노드를 추출하여 처리
    const fragment = range.cloneContents();
    const walker = document.createTreeWalker(
        fragment,
        NodeFilter.SHOW_TEXT,
        null,
        false
    );

    let currentNode;
    const nodesToWrap = [];
    while ((currentNode = walker.nextNode())) {
        nodesToWrap.push(currentNode);
    }

    // 선택된 텍스트를 각각 <span>으로 감싸기
    nodesToWrap.forEach((node) => {
        const span = document.createElement('span');
        span.style.color = color;
        span.textContent = node.textContent;

        const parent = node.parentNode;
        if (parent) {
            parent.replaceChild(span, node);
        }
    });

    // 선택 영역 삭제 및 새로운 노드 삽입
    range.deleteContents();
    range.insertNode(fragment);
}

// 단일 텍스트 노드의 선택된 부분을 <span>으로 감싸기
function wrapPartialTextInSpan(range, color) {
    const text = range.toString();
    if (!text.trim()) return;

    const span = document.createElement('span');
    span.style.color = color;
    span.textContent = text;

    range.deleteContents(); // 선택된 텍스트 삭제
    range.insertNode(span); // <span> 삽입
}

// 텍스트 노드를 <span>으로 감싸고 컬러 스타일 적용
function wrapTextNodeInSpan(textNode, color) {
    const parent = textNode.parentNode;

    // 이미 <span>으로 감싸져 있고 동일한 컬러인 경우 처리하지 않음
    if (parent.tagName === 'SPAN' && parent.style.color === color) {
        return;
    }

    const span = document.createElement('span');
    span.style.color = color;
    span.textContent = textNode.textContent;

    parent.replaceChild(span, textNode);
}

function applyColorToSelectionSafely(color) {
    const selection = window.getSelection();

    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0); // 선택된 범위 가져오기
        const ancestor = range.commonAncestorContainer;

        // 순회하며 모든 텍스트 노드에 컬러 적용
        const walker = document.createTreeWalker(
            ancestor,
            NodeFilter.SHOW_TEXT, {
                acceptNode: (node) => {
                    return range.intersectsNode(node) ?
                        NodeFilter.FILTER_ACCEPT :
                        NodeFilter.FILTER_REJECT;
                },
            }
        );

        let currentNode;
        const nodesToWrap = [];
        while ((currentNode = walker.nextNode())) {
            nodesToWrap.push(currentNode);
        }

        nodesToWrap.forEach((node) => {
            wrapTextNodeInSpan(node, color);
        });
    }
}

function applyColorToMultipleBlocks(color) {
    const selection = window.getSelection();

    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0); // 선택된 범위 가져오기
        const startContainer = range.startContainer;
        const endContainer = range.endContainer;

        if (startContainer !== endContainer) {
            // 선택 영역이 여러 블록에 걸쳐 있는 경우 처리
            handleMultiBlockSelection(range, color);
        } else {
            // 단일 블록 내에서 처리
            applyColorToSelection(color);
        }
    }
}

function handleMultiBlockSelection(range, color) {
    const startContainer = range.startContainer;
    const endContainer = range.endContainer;

    const ancestor = range.commonAncestorContainer;

    // 순회하며 모든 텍스트 노드에 컬러 적용
    const walker = document.createTreeWalker(
        ancestor,
        NodeFilter.SHOW_TEXT, {
            acceptNode: (node) => {
                const nodeRange = document.createRange();
                nodeRange.selectNodeContents(node);

                // 텍스트 노드가 선택 범위에 포함된 경우만 처리
                return range.intersectsNode(node) ?
                    NodeFilter.FILTER_ACCEPT :
                    NodeFilter.FILTER_REJECT;
            },
        }
    );

    let currentNode;
    while ((currentNode = walker.nextNode())) {
        wrapTextInSpan(currentNode, color);
    }

    // 선택 영역 정리
    range.deleteContents();
}

// 텍스트 노드를 <span>으로 감싸고 컬러 스타일 적용
function wrapTextInSpan(textNode, color) {
    const span = document.createElement('span');
    span.style.color = color;
    span.textContent = textNode.textContent;

    const parent = textNode.parentNode;
    parent.replaceChild(span, textNode);
}



function applyColorWithSpan(color) {
    const selection = window.getSelection();

    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0); // 선택된 범위 가져오기
        const fragment = range.extractContents(); // 선택된 내용을 추출

        const wrapper = document.createDocumentFragment();

        Array.from(fragment.childNodes).forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) {
                // 기존 요소를 <span>으로 감싸기
                const span = document.createElement('span');
                span.style.color = color;
                span.appendChild(node.cloneNode(true));
                wrapper.appendChild(span);
            } else if (node.nodeType === Node.TEXT_NODE) {
                // 텍스트 노드를 <span>으로 감싸기
                const span = document.createElement('span');
                span.style.color = color;
                span.textContent = node.textContent;
                wrapper.appendChild(span);
            }
        });

        range.deleteContents(); // 기존 내용을 삭제
        range.insertNode(wrapper); // 새로 생성한 내용 삽입
    }
}

// 선택된 텍스트에 컬러를 적용하는 함수
function applyColorToSelection(color) {
    const selection = window.getSelection();

    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0); // 선택된 범위 가져오기
        const commonAncestor = range.commonAncestorContainer;

        // 모든 텍스트 노드를 포함한 범위 처리
        const walker = document.createTreeWalker(
            commonAncestor,
            NodeFilter.SHOW_TEXT, {
                acceptNode: (node) => {
                    // 선택된 범위에 포함된 텍스트 노드만 처리
                    return range.intersectsNode(node) ?
                        NodeFilter.FILTER_ACCEPT :
                        NodeFilter.FILTER_REJECT;
                },
            }
        );

        let currentNode;
        const nodesToWrap = [];
        while ((currentNode = walker.nextNode())) {
            nodesToWrap.push(currentNode);
        }

        // 모든 텍스트 노드에 <span> 태그 추가
        nodesToWrap.forEach((node) => {
            wrapNodeInSpan(node, color);
        });

        // 선택 영역 복원
        restoreSelection();
    }
}

function wrapNodeInSpan(node, color) {
    const parent = node.parentNode;

    // 이미 동일한 <span>이 적용되어 있는 경우 처리하지 않음
    if (parent.tagName === 'SPAN' && parent.style.color === color) {
        return;
    }

    const span = document.createElement('span');
    span.style.color = color;

    if (node.nodeType === Node.TEXT_NODE) {
        span.textContent = node.textContent; // 텍스트 노드 복사
    } else {
        span.innerHTML = node.outerHTML; // 요소 노드 복사
    }

    parent.replaceChild(span, node);
}

function normalizeSpans() {
    $('#editor span').each(function () {
        const $this = $(this);
        const parent = $this.parent();

        // 부모가 <span>이고, 스타일이 동일하면 병합
        if (parent.is('span') && parent.css('color') === $this.css('color')) {
            $this.contents().unwrap(); // 현재 <span>을 제거하고 부모로 병합
        }
    });
}

function applyFontTagWithColor(color) {
    document.execCommand('styleWithCSS', false, false); // CSS 스타일 대신 태그 사용
    document.execCommand('foreColor', false, color); // 컬러 적용
}
function applyColorWithExecCommand(color) {
    // 컬러 변경 명령 실행
    document.execCommand('foreColor', false, color);

    // #editor 영역 내의 모든 <font> 태그를 찾아 <span>으로 변경
    $('#editor font').each(function () {
        const $font = $(this);
        // <font> 태그의 color 속성을 읽음
        let fontColor = $font.attr('color') || $font.css('color');

        // 새 <span> 태그 생성 후 스타일과 내용을 복사
        const $span = $('<span>').css('color', fontColor).html($font.html());

        // <font> 태그를 새 <span> 태그로 교체
        $font.replaceWith($span);
    });
}

// 컬러 선택 후 텍스트에 색상 적용
$('#text-color-picker').on('input', function () {
    const selectedColor = $(this).val(); // 선택된 색상 값
    $('#color-btn .color-btn-svg path.path2_color1').attr('fill', selectedColor); // SVG의 fill 속성 업데이트
    $('#color-btn .color-btn-svg path.path2_color2').attr('fill', selectedColor); // SVG의 fill 속성 업데이트
    restoreSelection(); // 선택 영역 복원
    applyTextColor(selectedColor); // 컬러 변경
    saveSelection(); // 선택 영역 다시 저장
});

$('#background-color-picker').on('input', function () {
    const selectedBgColor = $(this).val();
    restoreSelection();
    //applyBackgroundColor(selectedBgColor);
    applyBackgroundColorNoBreaking(selectedBgColor);

    // 선택된 컬러를 SVG 등에 업데이트
    $('#background-color-btn .background-color-btn-svg path').attr('fill', selectedBgColor);
    saveSelection();
});


// 컬러 버튼 클릭 시 선택 영역 저장
$('#color-btn').on('mousedown', function (e) {
    saveSelection(); // 선택 영역 저장
    e.preventDefault(); // 기본 동작 방지
});

// 텍스트 선택 후 컬러 버튼 클릭 시 Coloris 활성화
$('#color-btn').click(function (e) {
    e.preventDefault(); // 기본 동작 방지
    saveSelection(); // 선택 영역 저장
    $('#text-color-picker').click(); // 컬러 선택기 활성화
});

// 텍스트 선택 후 백그라운드 버튼 클릭 시 Coloris 활성화
$('#background-color-btn').click(function (e) {
    e.preventDefault(); // 기본 동작 방지
    saveSelection(); // 선택 영역 저장
    $('#background-color-picker').click(); // 컬러 선택기 활성화
});


function applyTextColor(color) {
    if (currentMode === 'regular') {
        if (savedSelection) {
            const sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(savedSelection);
        }
        document.execCommand('foreColor', false, color);
        updateMiniToolbar();
        // #mini-toolbar는 숨기지 않음
    } else if (currentMode === 'canvas') {
        applyCanvasTextColor(color);
    }
}

function applyBackgroundColorNoBreaking(color) {
    const selection = window.getSelection();
    if (!selection.rangeCount || selection.isCollapsed) return;

    // 현재 선택 범위 가져오기
    const range = selection.getRangeAt(0);
    const startContainer = range.startContainer;
    const endContainer = range.endContainer;

    // ✅ 두 줄 이상 선택한 경우 기존 로직 사용
    if (startContainer !== endContainer) {
        const textNodes = getTextNodesInRange(range);
        textNodes.forEach(node => highlightTextNodePartially(node, range, color));
        return;
    }

    // ✅ 한 줄 부분 선택인 경우 일반적인 스타일 적용 방식 사용
    applyStyleToSelection("backgroundColor", color);
}

function highlightTextNodePartially(textNode, selectionRange, color) {
    if (!textNode || textNode.nodeType !== Node.TEXT_NODE) return;

    const nodeRange = document.createRange();
    nodeRange.selectNodeContents(textNode);

    const intersectionRange = nodeRange.cloneRange();

    if (selectionRange.compareBoundaryPoints(Range.START_TO_START, nodeRange) > 0) {
        intersectionRange.setStart(selectionRange.startContainer, selectionRange.startOffset);
    }
    if (selectionRange.compareBoundaryPoints(Range.END_TO_END, nodeRange) < 0) {
        intersectionRange.setEnd(selectionRange.endContainer, selectionRange.endOffset);
    }

    const selectedText = intersectionRange.toString();
    if (!selectedText.trim()) return;

    const startOffsetInNode = intersectionRange.startOffset - nodeRange.startOffset;
    const endOffsetInNode = intersectionRange.endOffset - nodeRange.startOffset;

    const originalText = textNode.nodeValue;
    const beforeText = originalText.slice(0, startOffsetInNode);
    const middleText = originalText.slice(startOffsetInNode, endOffsetInNode);
    const afterText = originalText.slice(endOffsetInNode);

    let highlightSpan;
    if (textNode.parentNode.tagName === 'SPAN') {
        highlightSpan = textNode.parentNode.cloneNode();
        highlightSpan.style.backgroundColor = color;
    } else {
        highlightSpan = document.createElement('span');
        highlightSpan.style.backgroundColor = color;
    }

    highlightSpan.textContent = middleText;

    const parent = textNode.parentNode;
    if (!parent) return;

    const fragment = document.createDocumentFragment();

    if (beforeText) {
        fragment.appendChild(document.createTextNode(beforeText));
    }
    fragment.appendChild(highlightSpan);
    if (afterText) {
        fragment.appendChild(document.createTextNode(afterText));
    }

    parent.replaceChild(fragment, textNode);
}


function getTextNodesInRange(range) {
    const container = range.commonAncestorContainer;
    const textNodes = [];

    const walker = document.createTreeWalker(
        container,
        NodeFilter.SHOW_TEXT,
        {
            acceptNode: (node) => {
                return range.intersectsNode(node)
                    ? NodeFilter.FILTER_ACCEPT
                    : NodeFilter.FILTER_REJECT;
            }
        }
    );

    while (walker.nextNode()) {
        textNodes.push(walker.currentNode);
    }
    return textNodes;
}

function mergeBackgroundSpans(editor) {
    $(editor).find('span').each(function () {
        const $span = $(this);

        // 부모가 span이고 스타일이 같으면 병합
        const parent = $span.parent();
        if (parent.is('span') && parent.css('background-color') === $span.css('background-color')) {
            $span.contents().unwrap(); // span 병합
        }

        // 빈 span 제거
        if ($span.text().trim() === '' && $span.children().length === 0) {
            $span.remove();
        }
    });
}

function applyBackgroundColor(color) {
    if (savedSelection) {
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(savedSelection);
    }

    const selection = window.getSelection();
    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0);
        const commonAncestor = range.commonAncestorContainer;

        // ✅ 드래그로 여러 개의 `<td>`, `<th>`가 선택된 경우 처리
        const selectedCells = getSelectedCells(range);

        if (selectedCells.length > 0) {
            selectedCells.forEach(cell => {
                applyBackgroundToCell(cell, color);
            });
        } else {
            // ✅ 단일 선택일 경우 기존 로직 사용
            let targetElement = commonAncestor.nodeType === Node.ELEMENT_NODE ? commonAncestor : commonAncestor.parentElement;

            if (targetElement.tagName === 'TD' || targetElement.tagName === 'TH') {
                applyBackgroundToCell(targetElement, color);
            } else {
                wrapTextWithSpan(range, color);
            }
        }
    }
    updateMiniToolbar();
}

// ✅ 선택된 `<td>`, `<th>` 목록 가져오기
function getSelectedCells(range) {
    const selectedCells = [];
    const ancestor = range.commonAncestorContainer;
    const table = ancestor.closest ? ancestor.closest('table') : null;

    if (table) {
        const allCells = table.querySelectorAll('td, th');
        allCells.forEach(cell => {
            if (range.intersectsNode(cell)) {
                selectedCells.push(cell);
            }
        });
    }
    return selectedCells;
}

// ✅ 개별 `<td>` 또는 `<th>` 내부의 텍스트에만 배경색 적용
function applyBackgroundToCell(cell, color) {
    // 기존 `span`이 있는지 확인하고 스타일 변경
    let existingSpan = cell.querySelector('span');

    if (existingSpan) {
        existingSpan.style.backgroundColor = color;
    } else {
        // 기존 `span`이 없으면 내부 텍스트를 `span`으로 감싸기
        cell.childNodes.forEach((node) => {
            if (node.nodeType === Node.TEXT_NODE && node.nodeValue.trim() !== '') {
                const span = document.createElement("span");
                span.style.backgroundColor = color;
                span.textContent = node.nodeValue;
                node.replaceWith(span);
            }
        });
    }
}


// 선택한 텍스트를 개별적으로 감싸서 스타일 적용하는 함수
function wrapTextWithSpan(range, color) {
    const span = document.createElement("span");
    span.style.backgroundColor = color;

    // 선택한 내용을 가져옴
    const selectedContents = range.extractContents();

    // 선택한 텍스트를 감싸서 배경색 적용
    span.appendChild(selectedContents);

    // 기존 내용을 삭제하고 새로 추가
    range.deleteContents();
    range.insertNode(span);
}



function applyFontSize(fontSize) {
  const selection = window.getSelection();
  if (!selection.rangeCount || selection.isCollapsed) return;

  const range = selection.getRangeAt(0);

  // 시작/끝 컨테이너가 텍스트 노드라면 부모 요소로 변경
  const startContainer =
    range.startContainer.nodeType === Node.TEXT_NODE
      ? range.startContainer.parentElement
      : range.startContainer;
  const endContainer =
    range.endContainer.nodeType === Node.TEXT_NODE
      ? range.endContainer.parentElement
      : range.endContainer;

  // 시작과 끝이 각각 td 또는 th 내부에 있는지 확인
  const startCell =
    startContainer.closest && startContainer.closest("td, th");
  const endCell =
    endContainer.closest && endContainer.closest("td, th");

  // 두 셀 모두 존재하고, 같은 테이블에 속해 있다면
  if (
    startCell &&
    endCell &&
    startCell.closest("table") === endCell.closest("table")
  ) {
    // 테이블 내 모든 셀 중 선택 영역과 교차하는 셀에 대해 스타일 적용
    const table = startCell.closest("table");
    const cells = table.querySelectorAll("td, th");

    cells.forEach((cell) => {
      // Range API의 intersectsNode 메서드를 사용하여
      // 해당 셀과 선택 영역이 겹치는지 검사
      if (range.intersectsNode(cell)) {
        cell.style.fontSize = fontSize;
      }
    });
    return;
  }

  // 테이블 셀 내부가 아닌 경우 – 기존 로직대로 선택 영역 내부의 텍스트/요소에 span 등을 삽입하여 폰트 크기 적용
  const extractedContents = range.extractContents();
  const wrapper = document.createDocumentFragment();

  Array.from(extractedContents.childNodes).forEach((node) => {
    if (node.nodeType === Node.TEXT_NODE) {
      // 텍스트 노드인 경우 새로운 span으로 감싸서 적용
      const span = document.createElement("span");
      span.style.fontSize = fontSize;
      span.textContent = node.textContent;
      wrapper.appendChild(span);
    } else if (node.nodeType === Node.ELEMENT_NODE) {
      // 요소 노드인 경우 style만 변경
      node.style.fontSize = fontSize;
      wrapper.appendChild(node);
    }
  });

  range.insertNode(wrapper);
  // 편집 영역(normalize)에서 불필요한 텍스트 노드 분리(한글 초성 분리 방지)
  document.getElementById("editor").normalize();
}


// ✅ `td`, `th` 내부에서 폰트 크기 변경 (새로운 `<td>`, `<th>` 생성 방지)
function applyFontSizeInsideTable(fontSize, $currentCell) {
    const selection = window.getSelection();
    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0);
        const selectedNodes = [];

        const walker = document.createTreeWalker(range.commonAncestorContainer, NodeFilter.SHOW_TEXT, null);
        let currentNode;

        while ((currentNode = walker.nextNode())) {
            if (selection.containsNode(currentNode, true)) {
                selectedNodes.push(currentNode);
            }
        }

        function applyStyleToSelection(node) {
            if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
                let parent = node.parentNode;

                // ✅ `td`, `th` 자체가 아니라 내부 텍스트에만 스타일 적용
                if ($currentCell[0] === parent) {
                    const span = document.createElement("span");
                    span.style.fontSize = fontSize;
                    span.textContent = node.textContent;
                    parent.replaceChild(span, node);
                } else if (parent.tagName === "SPAN") {
                    parent.style.fontSize = fontSize;
                } else {
                    const span = document.createElement("span");
                    span.style.fontSize = fontSize;
                    span.appendChild(node.cloneNode(true));
                    parent.replaceChild(span, node);
                }
            }
        }

        selectedNodes.forEach(applyStyleToSelection);
    }
}

// ✅ `td`, `th` 외부 또는 `td`, `th` 내부의 텍스트에서 폰트 크기 변경 (새로운 `<span>` 추가)
function applyFontSizeOutsideTable(fontSize) {
    const selection = window.getSelection();

    if (selection.rangeCount > 0 && !selection.isCollapsed) {
        const range = selection.getRangeAt(0);
        const fragment = range.cloneContents(); // ✅ 기존 구조 유지

        const wrapper = document.createDocumentFragment();

        Array.from(fragment.childNodes).forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) {
                node.style.fontSize = fontSize;
                wrapper.appendChild(node);
            } else if (node.nodeType === Node.TEXT_NODE) {
                const span = document.createElement("span");
                span.style.fontSize = fontSize;
                span.textContent = node.textContent;
                wrapper.appendChild(span);
            }
        });

        range.deleteContents();
        range.insertNode(wrapper);
    } else {
        if (!$('#editor').text().trim()) {
            $('#editor').css('font-size', fontSize);
        }
    }
}

function alignText(alignment) {
    const selection = window.getSelection();
    const range = selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

    if (currentMode === 'regular') {
        const $currentCell = $(selection.anchorNode).closest('td, th');

        if ($currentCell.length) {
            // 커서가 표 안에 있는 경우 해당 셀에만 정렬 적용
            $currentCell.css('text-align', alignment);
        } else {
            // 커서가 표 밖에 있으면 전체 문서에 정렬 적용 (표는 제외)
            $('#editor').children(':not(table)').css('text-align', alignment);
        }
    } else if (currentMode === 'canvas') {
        const activeObject = fabricCanvas.getActiveObject();
        if (!activeObject) return;

        if (activeObject.type === 'i-text') {
            activeObject.set('textAlign', alignment);
            fabricCanvas.renderAll();
        }
    }

    updateMiniToolbar();
}