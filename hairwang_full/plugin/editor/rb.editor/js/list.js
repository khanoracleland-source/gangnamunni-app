let nodeCount = 0;

function addNode(parentEl = document.getElementById('treeRoot')) {
    nodeCount++;
    const node = document.createElement('div');
    node.className = 'anchor-node';
    const nodeId = `anchor-node-${nodeCount}`;
    node.setAttribute('data-node-id', nodeId);
    
    // children 부분 제거, [추가] 버튼 추가
    node.innerHTML = `
      <input type="text" class="title" placeholder="목차">
      <button class="add-to-editor" onclick="insertAnchor(this)" title="앵커"></button>
      <div class="delete-btn" onclick="deleteNode(this)" title="삭제"></div>
    `;
    
    parentEl.appendChild(node);
}

function insertAnchor(btn) {
    const anchorNode = btn.closest('.anchor-node');
    const dataNodeId = anchorNode.getAttribute('data-node-id') || `anchor_${Date.now()}`;
    const editor = document.getElementById('editor');

    // 이미 존재하면 중복 삽입 방지
    if (editor.querySelector(`#${dataNodeId}`)) {
        alert('이미 추가한 앵커입니다.');
        return;
    }

    const input = anchorNode.querySelector('.title');
    let titleText = input?.value?.trim();
    if (!titleText) {
        titleText = input?.getAttribute('placeholder')?.trim() || 'Anchor';
    }

    const anchorSpan = document.createElement('label');
    anchorSpan.className = 'rb_anchor';
    anchorSpan.id = dataNodeId;
    anchorSpan.textContent = titleText;

    const selection = window.getSelection();
    let inserted = false;

    // 1) 커서가 있는지, editor 내부에 있는지 체크
    if (selection && selection.rangeCount > 0) {
        const range = selection.getRangeAt(0);
        if (!editor.contains(range.commonAncestorContainer)) {
            alert('커서가 에디터 영역에 없습니다.');
            return;
        }

        // 삽입
        range.insertNode(anchorSpan);

        // 2) 삽입 직후 바로 다음이 <br>이면 제거
        if (anchorSpan.nextSibling && anchorSpan.nextSibling.nodeName === 'BR') {
            anchorSpan.parentNode.removeChild(anchorSpan.nextSibling);
        }

        // invisibleSpace는 그대로 추가 (커서 이동 편의)
        const invisibleSpace = document.createTextNode('\u200B');
        //range.insertNode(invisibleSpace);

        range.setStartAfter(invisibleSpace);
        range.collapse(true);
        selection.removeAllRanges();
        selection.addRange(range);
        inserted = true;
    }

    // 커서가 없거나 editor 외부에 있을 경우
    if (!inserted) {
        alert('커서가 에디터 영역에 없습니다.');
        return;
    }
}


function deleteNode(btn) {
    const node = btn.closest('.anchor-node');
    //if (node && confirm('이 노드를 삭제할까요?')) {
    node.remove();
    //}
}


function initSortable(el) {
    Sortable.create(el, {
        group: 'tree',
        handle: '.handle',
        animation: 150,
        swapThreshold: 0.65,
        fallbackOnBody: true,
        ghostClass: 'ghost',
        onAdd: evt => {
            if (getNodeDepth(evt.item) > 3) {
                document.getElementById('treeRoot').appendChild(evt.item);
                alert('⚠️ 최대 3단계까지만 허용됩니다.');
            }
        }
    });
}

Sortable.create(document.getElementById('treeRoot'), {
    group: {
        name: 'tree',
        pull: 'clone',  // 'clone'으로 설정 시 복제본이 드래그됨 (원본 유지)
        revertClone: true
    },
    handle: '.handle',
    animation: 150,
    swapThreshold: 0.65,
    fallbackOnBody: true,
    ghostClass: 'ghost',
    onAdd: evt => {
        if (getNodeDepth(evt.item) > 3) {
            document.getElementById('treeRoot').appendChild(evt.item);
            alert('⚠️ 최대 3단계까지만 허용됩니다.');
        }
    }
});

function getNodeDepth(el) {
    let depth = 1;
    while (el && el.parentNode) {
        if (el.parentNode.classList.contains('children')) {
            depth++;
        }
        el = el.parentNode.closest('.anchor-node');
    }
    return depth;
}


function close_anchor() {
    $('#anchor-wrap').fadeOut(0);
    $('#anchor-btn').removeClass('on');
}

/*
Sortable.create(document.getElementById('editor'), {
  group: {
    name: 'tree',
    pull: false,
    put: ['tree']
  },
  animation: 150,
  ghostClass: "sortable-ghost",
  onMove: function (evt, originalEvent) {
    const draggedEl = evt.dragged;
    const anchorWrap = document.getElementById('anchor-wrap');
    if (!anchorWrap || !draggedEl || !originalEvent) return false;
    const wrapRect = anchorWrap.getBoundingClientRect();
    const draggedRect = draggedEl.getBoundingClientRect();
    const pointerX = originalEvent.clientX;
    const pointerY = originalEvent.clientY;
    const pointerOutside =
      pointerX < wrapRect.left ||
      pointerX > wrapRect.right ||
      pointerY < wrapRect.top ||
      pointerY > wrapRect.bottom;
    const draggedOutside =
      draggedRect.right < wrapRect.left ||
      draggedRect.left > wrapRect.right ||
      draggedRect.bottom < wrapRect.top ||
      draggedRect.top > wrapRect.bottom;
    return pointerOutside || draggedOutside;
  },
  onAdd: function(evt) {
    const clonedNode = evt.item;
    const dataNodeId = clonedNode.getAttribute('data-node-id') || `anchor_${Date.now()}`;
    
    // 중복 ID 검사
    const editor = document.getElementById('editor');
    const existingAnchor = editor.querySelector(`#${dataNodeId}`);
    if (existingAnchor) {
      alert('이미 추가한 앵커입니다.');
      clonedNode.remove();
      return false;
    }
    
    const input = clonedNode.querySelector('.title');
    let titleText = input?.value?.trim();
    if (!titleText) {
      titleText = input?.getAttribute('placeholder')?.trim() || 'Anchor';
    }
    
    // Create the anchor span
    const anchorSpan = document.createElement('label');
    anchorSpan.className = 'rb_anchor';
    anchorSpan.id = dataNodeId;
    //anchorSpan.setAttribute('contenteditable', 'false');
    anchorSpan.textContent = titleText;
    
    // Replace the dragged item with the anchor span at the correct position
    clonedNode.parentNode.insertBefore(anchorSpan, clonedNode);
    
    // Create an invisible space after the anchor
    const invisibleSpace = document.createTextNode('\u200B');
    clonedNode.parentNode.insertBefore(invisibleSpace, clonedNode);
    
    // Remove the original dragged element
    clonedNode.remove();
    
    // Set cursor position after the invisible space
    const range = document.createRange();
    const sel = window.getSelection();
    range.setStartAfter(invisibleSpace);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
  }
});
*/

// #image-toolbar 드래그(마우스 & 터치)로 이동 가능하게 하기
$('#anchor-wrap #move_handle').on('mousedown touchstart', function (e) {
    e.preventDefault();
    e.stopPropagation();

    var $handle = $(this);
    var $toolbar = $handle.closest('#anchor-wrap');
    var $container = $('#editor-container');
    var containerOffset = $container.offset();

    // 드래그 시작 시의 좌표 (마우스/터치 구분)
    var startX = e.type === 'mousedown' ? e.clientX : e.touches[0].clientX;
    var startY = e.type === 'mousedown' ? e.clientY : e.touches[0].clientY;

    // 현재 툴바의 상대적 위치 (컨테이너 기준)
    var toolbarOffset = $toolbar.offset();
    var initialLeft = toolbarOffset.left - containerOffset.left;
    var initialTop = toolbarOffset.top - containerOffset.top;

    function onMove(e) {
        var moveX = e.type === 'mousemove' ? e.clientX : e.touches[0].clientX;
        var moveY = e.type === 'mousemove' ? e.clientY : e.touches[0].clientY;
        var dx = moveX - startX;
        var dy = moveY - startY;

        // 새 위치 (컨테이너 기준)
        var newLeft = initialLeft + dx;
        var newTop = initialTop + dy;

        // 경계 체크 (컨테이너 내에 머물도록)
        var containerWidth = $container.width();
        var containerHeight = $container.height();
        var toolbarWidth = $toolbar.outerWidth();
        var toolbarHeight = $toolbar.outerHeight();

        newLeft = Math.max(0, Math.min(newLeft, containerWidth - toolbarWidth));
        newTop = Math.max(0, Math.min(newTop, containerHeight - toolbarHeight));

        $toolbar.css({
            left: newLeft + 'px',
            top: newTop + 'px'
        });

        // 이동한 위치 저장 (컨테이너 기준)
        savedToolbarPosition = {
            left: newLeft,
            top: newTop
        };
    }

    function onEnd(e) {
        $(document).off('mousemove touchmove', onMove);
        $(document).off('mouseup touchend', onEnd);
    }

    $(document).on('mousemove touchmove', onMove);
    $(document).on('mouseup touchend', onEnd);
});
    
    
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    const anchors = document.querySelectorAll('#editor .rb_anchor');
    const treeRoot = document.getElementById('treeRoot');
    
    anchors.forEach(anchor => {
      const node = document.createElement('div');
      node.className = 'anchor-node';
      const nodeId = anchor.id; // 예: "anchor-node-1"
      node.setAttribute('data-node-id', nodeId);
      
      const titleText = anchor.textContent.trim();
      node.innerHTML = `
        <input type="text" class="title" placeholder="${titleText}">
        <button class="add-to-editor" onclick="insertAnchor(this)" title="앵커추가"></button>
        <div class="delete-btn" onclick="deleteNode(this)" title="삭제"></div>
        
      `;
      
      treeRoot.appendChild(node);
      // 1단계만 사용하므로 children 요소와 initSortable 호출은 제거합니다.
    });
    
    // 미리 생성된 노드 개수를 기반으로 nodeCount 업데이트
    nodeCount = anchors.length;
  }, 500); // 500ms 후 실행 (필요에 따라 조절)
});
