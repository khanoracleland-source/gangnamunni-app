/* ========== 전역 변수 및 기본 설정 ========== */
// 이미지별 효과 상태를 관리하는 Map
const imageEffectsMap = new Map();

let selectedImage = null;
let savedToolbarPosition = null;

// 초기 효과 상태
const defaultEffects = {
    brightness: 100,
    contrast: 100,
    saturation: 100,
    blur: 0,
    grayscale: 0,
    invert: 0,
    opacity: 100,
    sepia: 0,
    shadowX: 0,
    shadowY: 0,
    shadowBlur: 0,
    shadowColor: 'rgba(0,0,0,0)',
    radius: 0,
    borderWidth: 0,
    borderColor: 'rgba(0,0,0,1)'
};

/* ========== 유틸리티 함수 ========== */
/**
 * 슬라이더 ID에서 효과 키를 추출하는 함수
 * ex) brightness-slider → brightness
 */
function getEffectKeyFromSliderId(sliderId) {
    return sliderId.replace('-slider', '').replace(/-(.)/g, (_, char) => char.toUpperCase());
}

/* ========== 이미지 효과 적용 함수 ========== */
/**
 * 선택된 이미지에 현재 설정된 효과들을 적용하는 함수
 */
function applyImageEffects() {
    if (!selectedImage) return;

    const effects = imageEffectsMap.get(selectedImage[0]);
    const filter = `
        brightness(${effects.brightness}%)
        contrast(${effects.contrast}%)
        saturate(${effects.saturation}%)
        grayscale(${effects.grayscale}%)
        invert(${effects.invert}%)
        opacity(${effects.opacity}%)
        sepia(${effects.sepia}%)
        blur(${effects.blur}px)
        drop-shadow(${effects.shadowX}px ${effects.shadowY}px ${effects.shadowBlur}px ${effects.shadowColor})
    `;

    selectedImage.css({
        filter: filter.trim(),
        'border-radius': `${effects.radius}px`,
        'border-width': effects.borderWidth + 'px',
        'border-style': 'solid',
        'border-color': effects.borderColor
    });
}

function extractEffectsFromInlineStyle($img) {
    const effects = { ...defaultEffects };
    const styleAttr = $img.attr('style'); // ✅ 인라인 스타일 직접 가져오기

    if (styleAttr && styleAttr.includes('filter')) {
        const filterMatch = styleAttr.match(/filter:\s*([^;]+)/);
        if (filterMatch) {
            const filter = filterMatch[1];

            effects.brightness = getFilterValue(filter, 'brightness', '%', 100);
            effects.contrast = getFilterValue(filter, 'contrast', '%', 100);
            effects.saturation = getFilterValue(filter, 'saturate', '%', 100);
            effects.grayscale = getFilterValue(filter, 'grayscale', '%', 0);
            effects.invert = getFilterValue(filter, 'invert', '%', 0);
            effects.opacity = getFilterValue(filter, 'opacity', '%', 100);
            effects.sepia = getFilterValue(filter, 'sepia', '%', 0);
            effects.blur = getFilterValue(filter, 'blur', 'px', 0);

            // ✅ drop-shadow() 값 추출
            const dropShadowMatch = filter.match(/drop-shadow\(rgba?\((.*?)\)\s+([\d.]+)px\s+([\d.]+)px\s+([\d.]+)px\)/);
            if (dropShadowMatch) {
                effects.shadowColor = `rgba(${dropShadowMatch[1]})`;
                effects.shadowX = parseFloat(dropShadowMatch[2]);
                effects.shadowY = parseFloat(dropShadowMatch[3]);
                effects.shadowBlur = parseFloat(dropShadowMatch[4]);
            }
        }
    }

    // ✅ border 관련 속성도 인라인 스타일에서 가져오기
    effects.radius = getInlineStyleValue($img, 'border-radius', 'px', 0);
    effects.borderWidth = getInlineStyleValue($img, 'border-width', 'px', 0);
    effects.borderColor = getInlineBorderColor($img) || 'rgba(0,0,0,1)';

    return effects;
}

function getFilterValue(filterStr, property, unit, defaultValue) {
    const match = filterStr.match(new RegExp(`${property}\\(([-\\d.]+)${unit}\\)`));
    return match ? parseFloat(match[1]) : defaultValue;
}

function getInlineStyleValue($img, property, unit, defaultValue) {
    const style = $img.attr('style');
    if (!style) return defaultValue;
    const match = style.match(new RegExp(`${property}:\\s*([-\\d.]+)${unit}`));
    return match ? parseFloat(match[1]) : defaultValue;
}

function getInlineBorderColor($img) {
    const style = $img.attr('style');
    if (!style) return null;
    const match = style.match(/border-color:\s*(rgb[a]?\([^)]*\))/);
    return match ? match[1] : null;
}


/* ========== 이벤트 핸들러 등록 ========== */

// 이미지 슬라이더 변화에 따른 효과 업데이트
$('.image-slider-ui').on('input', function () {
    if (!selectedImage) return;

    // 현재 선택된 이미지의 효과 상태 가져오기
    const effects = imageEffectsMap.get(selectedImage[0]);
    const effectKey = getEffectKeyFromSliderId($(this).attr('id'));

    // 효과 상태 업데이트
    effects[effectKey] = $(this).val();
    applyImageEffects(); // 이미지에 효과 적용
});

// 드롭섀도우 색상 변경 이벤트 핸들러
$('#shadow-color-slider').on('input', function () {
    if (!selectedImage) return;

    const transparency = $(this).val(); // 0~100 사이의 값
    const effects = imageEffectsMap.get(selectedImage[0]);
    if (!effects) return;

    // alpha 값을 슬라이더 값에 비례하게 계산하여 그림자 컬러 업데이트
    effects.shadowColor = `rgba(0, 0, 0, ${transparency / 100})`;

    applyImageEffects(); // 변경된 효과를 이미지에 적용
});

// 테두리 색상 변경 이벤트 핸들러
$('#border-color-slider').on('input', function () {
    if (!selectedImage) return;

    const transparency = $(this).val(); // 0~100 사이의 값
    const effects = imageEffectsMap.get(selectedImage[0]);
    if (!effects) return;

    // alpha 값을 투명도로 계산하여 borderColor 업데이트
    effects.borderColor = `rgba(0, 0, 0, ${transparency / 100})`;

    applyImageEffects(); // 변경된 효과를 이미지에 적용
});

// 효과 초기화 버튼 클릭 이벤트 핸들러
$('#reset-effects-btn').click(function () {
    if (!selectedImage) return;

    // 선택된 이미지의 효과 상태를 기본값으로 초기화
    const effects = imageEffectsMap.get(selectedImage[0]);
    Object.assign(effects, defaultEffects);

    // 슬라이더와 효과 상태 동기화
    syncSlidersWithEffects(effects);
    applyImageEffects();
});

/* ========== 이미지 선택 및 툴바 관련 함수 ========== */
/**
 * 이미지를 선택했을 때 호출되는 함수
 * 선택된 이미지에 대한 효과 상태를 불러오고, 슬라이더 UI와 동기화합니다.
 */
function selectImage(image) {
    selectedImage = image;

    let effects = imageEffectsMap.get(image[0]);
    if (!effects) {
        effects = extractEffectsFromInlineStyle(image);
        imageEffectsMap.set(image[0], effects);
    }

    syncSlidersWithEffects(effects);
}

function syncSlidersWithEffects(effects) {
    $('#brightness-slider').val(effects.brightness);
    $('#contrast-slider').val(effects.contrast);
    $('#saturation-slider').val(effects.saturation);
    $('#blur-slider').val(effects.blur);
    $('#grayscale-slider').val(effects.grayscale);
    $('#invert-slider').val(effects.invert);
    $('#opacity-slider').val(effects.opacity);
    $('#sepia-slider').val(effects.sepia);
    $('#shadow-x-slider').val(effects.shadowX);
    $('#shadow-y-slider').val(effects.shadowY);
    $('#shadow-blur-slider').val(effects.shadowBlur);
    $('#radius-slider').val(effects.radius);
    $('#border-width-slider').val(effects.borderWidth);

    // ✅ shadowColor (rgba -> alpha 값만 슬라이더에 적용)
    if (effects.shadowColor) {
        const shadowMatch = effects.shadowColor.match(/rgba?\(\d+,\s*\d+,\s*\d+,\s*([\d.]+)\)/);
        if (shadowMatch) {
            $('#shadow-color-slider').val(parseFloat(shadowMatch[1]) * 100); // 0~1 -> 0~100 변환
        }
    }

    // ✅ borderColor (rgba -> alpha 값만 슬라이더에 적용)
    if (effects.borderColor) {
        const borderMatch = effects.borderColor.match(/rgba?\(\d+,\s*\d+,\s*\d+,\s*([\d.]+)\)/);
        if (borderMatch) {
            $('#border-color-slider').val(parseFloat(borderMatch[1]) * 100); // 0~1 -> 0~100 변환
        }
    }
}


/**
 * 슬라이더 조정 시 이미지 툴바를 계속 표시하는 함수
 */
function keepImageToolbarVisible() {
    if (selectedImage) {
        $('#image-toolbar').fadeIn(0); // 슬라이더 조정 시 툴바 표시 유지
    }
}


$('#editor').on('mousedown', '.resizable', function (e) {
    // 크기 조절 핸들이 아닌 영역에서 커서 방지
    const isHandle = $(e.target).hasClass('resize-handle');
    if (!isHandle) {
        e.preventDefault(); // 기본 동작 방지
    }
    
   // `.resize-handle`이 없는 경우 추가
    if ($(this).children('.resize-handle').length === 0) {
        $(this).append('<div class="resize-handle"></div>');
        // 새로 추가된 핸들에 대해 커스텀 리사이징 이벤트를 다시 바인딩
        makeImageResizableWithObserver($(this));
    }
});

$(document).on('mousedown touchstart', '.resizable img', function(e) {
    // 터치 이벤트의 경우, 두 손가락 이상이면 panning 동작 실행하지 않음
    if (e.type === 'touchstart' && e.touches.length > 1) {
        return;
    }
    
    // 리사이즈 핸들 내부에서 발생한 이벤트는 무시하여 충돌 방지
    if ($(e.target).closest('.resize-handle').length > 0) {
        return;
    }
    
    e.preventDefault();
    
    var $img = $(this);
    // 부모 영역에 꽉 차도록 설정 (빈 공간 방지)
    $img.css({
       'width': '100%'
    });
    
    // 시작 좌표 (마우스/터치 구분)
    var startX = e.type === 'mousedown' ? e.clientX : e.touches[0].clientX;
    var startY = e.type === 'mousedown' ? e.clientY : e.touches[0].clientY;
    
    // .resizable (부모) 크기
    var $parent = $img.closest('.resizable');
    var containerWidth = $parent.width();
    var containerHeight = $parent.height();
    
    // 이전 드래그에서 저장된 object-position이 있다면 사용, 없으면 기본값 50% 50%
    var storedPos = $img.data('initialObjPos');
    var initialObjPos = storedPos ? storedPos : window.getComputedStyle($img[0]).objectPosition;
    var initialX = 50, initialY = 50;
    if (initialObjPos && initialObjPos.split(' ').length === 2) {
        var parts = initialObjPos.split(' ');
        var parsedX = parseFloat(parts[0]);
        var parsedY = parseFloat(parts[1]);
        initialX = isNaN(parsedX) ? 50 : parsedX;
        initialY = isNaN(parsedY) ? 50 : parsedY;
    }
    
    // 이미지 자연 크기
    var naturalWidth = $img[0].naturalWidth;
    var naturalHeight = $img[0].naturalHeight;
    
    // object-fit: cover 적용 시, 실제 렌더링되는 이미지 크기 계산
    var scale = Math.max(containerWidth / naturalWidth, containerHeight / naturalHeight);
    var displayedWidth = naturalWidth * scale;
    var displayedHeight = naturalHeight * scale;
    
    // 컨테이너 대비 이미지의 여분 (panning 가능한 범위)
    var extraWidth = displayedWidth - containerWidth;
    var extraHeight = displayedHeight - containerHeight;
    
    var dragData = { startX, startY, initialX, initialY, extraWidth, extraHeight };
    
    function onMove(e) {
        var moveX = e.type === 'mousemove' ? e.clientX : e.touches[0].clientX;
        var moveY = e.type === 'mousemove' ? e.clientY : e.touches[0].clientY;
        var dx = moveX - dragData.startX;
        var dy = moveY - dragData.startY;
        
        var newX = dragData.initialX;
        var newY = dragData.initialY;
        if (dragData.extraWidth > 0) {
            newX = dragData.initialX - (dx * 100 / dragData.extraWidth);
        }
        if (dragData.extraHeight > 0) {
            newY = dragData.initialY - (dy * 100 / dragData.extraHeight);
        }
        
        newX = Math.max(0, Math.min(100, newX));
        newY = Math.max(0, Math.min(100, newY));
        
        $img.css('object-position', newX + '% ' + newY + '%');
    }
    
    function onEnd(e) {
        $(document).off('mousemove touchmove', onMove);
        $(document).off('mouseup touchend', onEnd);
        // 드래그 종료 시 최종 object-position 값을 저장하여 다음 드래그의 초기값으로 사용
        var finalObjPos = $img.css('object-position');
        $img.data('initialObjPos', finalObjPos);
    }
    
    $(document).on('mousemove touchmove', onMove);
    $(document).on('mouseup touchend', onEnd);
});




$('#editor').on('click', '.resizable', function (e) {
    e.stopPropagation();

    // 이전 선택된 이미지 해제
    $('.resizable').removeClass('selected');
    $(this).addClass('selected');

    // 선택된 이미지로 효과 동기화
    selectImage($(this).find('img'));
    showImageToolbar($(this)); // 툴바 표시
    
    // ▼ 추가: 이미지 링크 정보 확인 후 입력창에 반영
    const $img = $(this).find('img');
    const $anchor = $img.parent('a');  // 부모가 <a>인지 검사
    if ($anchor.length) {
        // 링크가 있으면 href, target 정보를 UI에 반영
        const hrefVal = $anchor.attr('href') || '';
        const targetVal = $anchor.attr('target') || '_parent';

        $('#rb-image-link-inp').text(hrefVal);
        $('#rb-image-link-blanks').prop('checked', targetVal === '_blank');
    } else {
        // 링크가 없으면 입력창 초기화
        $('#rb-image-link-inp').text('');
        $('#rb-image-link-blanks').prop('checked', false);
    }
    
    document.activeElement.blur();

});

$('#mini-image-link-del-btn').on('click', function () {
    // 선택된 .resizable 내부의 img
    const $selectedImg = $('.resizable.selected img');
    if (!$selectedImg.length) {
        alert('이미지를 선택하세요.');
        return;
    }

    $selectedImg.each(function () {
        let $img = $(this);
        let $parent = $img.parent();
        if ($parent.is('a')) {
            // <a> 태그 제거, <img>만 남김
            $parent.replaceWith($img);
        }
    });

    // 입력창 초기화
    $('#rb-image-link-inp').text('');
    $('#rb-image-link-blanks').prop('checked', false);
});

$('#image-toolbar').on('click', function (e) {
    e.stopPropagation(); // 이벤트 전파 방지
});

// #image-toolbar 드래그(마우스 & 터치)로 이동 가능하게 하기
$('#image-toolbar #move_handle').on('mousedown touchstart', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    var $handle = $(this);
    var $toolbar = $handle.closest('#image-toolbar');
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
        savedToolbarPosition = { left: newLeft, top: newTop };
    }
    
    function onEnd(e) {
        $(document).off('mousemove touchmove', onMove);
        $(document).off('mouseup touchend', onEnd);
    }
    
    $(document).on('mousemove touchmove', onMove);
    $(document).on('mouseup touchend', onEnd);
});


/* 3. 이미지 리사이징 기능 (커스텀) */
function makeImageResizableWithObserver($resizable) {
  makeImageResizable($resizable);
  observeResizable($resizable);
}


function makeImageResizable($resizable) {

   // #anchor-wrap 안에 있다면 리턴 (핸들 생성 안 함)
  if ($resizable.closest('.anchor-node').length > 0) return;

  $resizable.removeData('resizable');
  $resizable.data('resizable', true);
  $resizable.css('position', 'relative');

  let $handle = $resizable.find('.resize-handle');
  if ($handle.length === 0) {
    $handle = $('<div class="resize-handle"></div>');
    $resizable.append($handle);
  } else {
    $handle.off('mousedown touchstart');
  }

  function startResize(e, isTouch) {
    e.preventDefault();
    e.stopPropagation();

    const $content = $resizable.find('img, iframe'); // ✅ 이미지 또는 iframe
    if ($content.length === 0) return; // ✅ 콘텐츠 없으면 종료

    let startWidth = $resizable.width();
    let startHeight = $resizable.height();

    // ✅ 콘텐츠 크기 가져오기 (이미지: naturalWidth, iframe: getBoundingClientRect)
    let originalWidth = $content.is('img') ? $content[0].naturalWidth : $content[0].getBoundingClientRect().width;
    let originalHeight = $content.is('img') ? $content[0].naturalHeight : $content[0].getBoundingClientRect().height;
    let originalRatio = originalHeight / originalWidth;

    let startX = 0, startY = 0;
    let initialPinchDistance = null;
    let shiftKeyPressed = e.shiftKey;

    if (isTouch && e.touches.length >= 2) {
      initialPinchDistance = getPinchDistance(e);
    } else {
      startX = isTouch ? e.touches[0].clientX : e.clientX;
      startY = isTouch ? e.touches[0].clientY : e.clientY;
    }

    // ✅ `.url-preview-video` 내부일 경우 iframe 포인터 비활성화
    if ($resizable.closest('.url-preview-video').length > 0) {
      $resizable.closest('.url-preview-video').find('iframe').css('pointer-events', 'none');
    }

    function doResize(e) {
      e.preventDefault();

      let newWidth = startWidth;
      let newHeight = startHeight;
      shiftKeyPressed = e.shiftKey;

      if (isTouch && e.touches.length >= 2) {
        let currentPinchDistance = getPinchDistance(e);
        if (initialPinchDistance && currentPinchDistance) {
          let scaleFactor = currentPinchDistance / initialPinchDistance;
          newWidth = startWidth * scaleFactor;
          newHeight = shiftKeyPressed ? newWidth * originalRatio : startHeight * scaleFactor;
        }
        if ($content.is('img')) {
          $content.css('object-fit', ''); // ✅ 이미지 비율 유지
        }
      } else {
        const moveX = isTouch ? e.touches[0].clientX : e.clientX;
        const moveY = isTouch ? e.touches[0].clientY : e.clientY;
        const dx = moveX - startX;
        const dy = moveY - startY;

        if (shiftKeyPressed || $resizable.closest('.url-preview-video').length > 0) {
          // ✅ Shift 키가 눌렸거나 `.url-preview-video` 내부일 경우 원본 비율 유지
          newWidth = startWidth + dx;
          newHeight = newWidth * originalRatio;
          if ($content.is('img')) {
            $content.css('object-fit', ''); // ✅ 이미지 비율 유지
          }
        } else {
          // ✅ 자유 크기 조절 모드 (iframe 포함)
          newWidth = startWidth + dx;
          newHeight = startHeight + dy;
          if ($content.is('img')) {
            $content.css('object-fit', 'cover'); // ✅ 비율 유지 없음
          }
        }
      }

      if (newWidth > 20 && newHeight > 20) {
        $resizable.css({
          width: newWidth + 'px',
          height: newHeight + 'px'
        });

        // ✅ Shift 키, 투핑거 터치, `.url-preview-video` 내부에서는 원본 비율 유지
        if (shiftKeyPressed || (isTouch && e.touches.length >= 2) || $resizable.closest('.url-preview-video').length > 0) {
          newHeight = newWidth * originalRatio;
          $resizable.css({ height: newHeight + 'px' });
        }
      }
    }

    function stopResize() {
      document.removeEventListener(isTouch ? 'touchmove' : 'mousemove', doResize);
      document.removeEventListener(isTouch ? 'touchend' : 'mouseup', stopResize);

      let finalWidth = $resizable.width();
      let finalHeight = $resizable.height();

      // ✅ 최종 크기 저장
      $resizable.attr('data-original-width', finalWidth);
      $resizable.attr('data-original-height', finalHeight);
      $resizable.attr('data-ratio', finalHeight / finalWidth);

      // ✅ `.url-preview-video` 내부일 경우 iframe 포인터 이벤트 복구
      if ($resizable.closest('.url-preview-video').length > 0) {
        $resizable.closest('.url-preview-video').find('iframe').css('pointer-events', '');
      }
    }

    document.addEventListener(isTouch ? 'touchmove' : 'mousemove', doResize, { passive: false });
    document.addEventListener(isTouch ? 'touchend' : 'mouseup', stopResize, { passive: false });
  }

  const handleEl = $handle[0];
  handleEl.addEventListener('mousedown', function(e) {
    startResize(e, false);
  }, { passive: false });
  handleEl.addEventListener('touchstart', function(e) {
    startResize(e, true);
  }, { passive: false });

  $resizable.on('touchstart', function(e) {
    if (e.touches.length >= 2 && $(e.target).closest('.resize-handle').length === 0) {
      startResize(e, true);
    }
  });
}



// 헬퍼: 두 손가락 사이 거리를 계산하는 함수
function getPinchDistance(e) {
  if (e.touches.length >= 2) {
    var dx = e.touches[0].clientX - e.touches[1].clientX;
    var dy = e.touches[0].clientY - e.touches[1].clientY;
    return Math.sqrt(dx * dx + dy * dy);
  }
  return null;
}




/* 4. MutationObserver를 사용하여 'resizable' div의 클래스 변경 감지 */
const observer = new MutationObserver(function (mutationsList) {
  mutationsList.forEach(mutation => {
    if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
      const $target = $(mutation.target);
      if ($target.hasClass('selected')) {
        showImageToolbar($target);
      } else {
        $('#image-toolbar').fadeOut(0);
        selectedImage = null;
      }
    }
  });
});

// 모든 'resizable' div에 대해 MutationObserver 설정
function observeResizable($resizable) {
  observer.observe($resizable[0], { attributes: true });
}

function showImageToolbar($resizable) {
    selectedImage = $resizable.find('img');
    
    $('#anchor-wrap').fadeOut(0);
    $('#anchor-btn').removeClass('on');

    // 저장된 위치가 있으면 그대로 사용
    if (savedToolbarPosition) {
        $('#image-toolbar').css({
            top: savedToolbarPosition.top + 'px',
            left: savedToolbarPosition.left + 'px',
        }).fadeIn(0);
    } else {
        // 기존 로직에 따른 위치 계산
        const imgOffset = $resizable.offset();
        const containerOffset = $('body').offset();
        const toolbarHeight = $('#image-toolbar').outerHeight();
        const toolbarWidth = $('#image-toolbar').outerWidth();
        const containerWidth = $('#editor-container').width();
        const containerHeight = $('#editor-container').height();

        let toolbarTop = imgOffset.top - containerOffset.top + 15;
        let toolbarLeft = selectedImage.width() + 50;
        if (toolbarTop + toolbarHeight > containerHeight) {
            toolbarTop = imgOffset.top - containerOffset.top - toolbarHeight;
        }
        if (toolbarLeft + toolbarWidth > containerWidth) {
            toolbarLeft = containerWidth - toolbarWidth;
        }
        if (toolbarLeft < 0) {
            toolbarLeft = 10;
        }
        if (toolbarTop < 0) {
            toolbarTop = 10;
        }

        $('#image-toolbar').css({
            top: toolbarTop + 'px',
            left: toolbarLeft + 'px',
        }).fadeIn(0);
    }
}

$('#radius-slider').on('input', function () {
    if ($('.resizable.selected').length) {
        const radiusValue = $(this).val();
        $('.resizable.selected img').css('border-radius', radiusValue + 'px');
    }
});

$('#remove-image-btn').click(function () {
    if (!selectedImage) return;

    if (currentMode === 'regular') {
        const $img = $(selectedImage);
        
        // 1) 가장 먼저 .resizable_wrap, .resizable를 찾고
        let $target = $img.closest('.resizable_wrap, .resizable');
        
        // 2) 없다면 <a> 태그가 있는지 확인
        if (!$target.length) {
            $target = $img.closest('a');
        }
        
        // 3) 그래도 없으면 자기 자신(img)만 제거
        if ($target.length) {
            $target.remove();
        } else {
            $img.remove();
        }
    } 
    else if (currentMode === 'canvas') {
        fabricCanvas.remove(selectedImage);
    }

    $('#image-toolbar').fadeOut(0);
    selectedImage = null;
});


/*
$('#remove-server-image-btn').click(function () {
    if (!selectedImage) return;

    let imageUrl = selectedImage.attr('src');

    // 삭제 확인 메시지
    if (!confirm("서버에서 이미지를 완전히 삭제합니다. 삭제된 이미지는 복구되지 않으며, 이미지를 감싸는 영역도 함께 제거됩니다.\n\n이미지를 서버에서 완전히 삭제 하시겠습니까?")) {
        return;
    }
    
    var g5_editor_url = "/plugin/editor/rb.editor"; // 올바른 경로 설정

    // 서버에서 파일 삭제 요청
    $.ajax({
        url: g5_editor_url + "/php/rb.delete.php", // 삭제 처리 파일
        type: "POST",
        data: { file: imageUrl }, // 파일 URL 전송
        dataType: "json",
        success: function (response) {
            console.log("서버 응답 (파일 삭제):", response);

            if (response.success) {
                // ✅ 서버에서 파일 삭제 성공 시 `.resizable` 삭제
                selectedImage.parent('.resizable').remove();

                // 툴바 숨김 및 선택 이미지 초기화
                $('#image-toolbar').fadeOut(0);
                selectedImage = null;
            } else {
                alert("이미지 삭제 실패: " + (response.error || "알 수 없는 오류"));
            }
        },
        error: function (xhr, status, error) {
            console.error("파일 삭제 오류:", error);
            alert("파일 삭제 중 오류가 발생했습니다.");
        }
    });
});
*/

$(document).click(function (e) {
    if (!$(e.target).closest('#image-toolbar').length && !$(e.target).closest('.resizable').length) {
        $('#image-toolbar').fadeOut(0);
        selectedImage = null;
    }
});


// 커서를 박스 외부로 강제 이동하는 함수
function moveCursorOutsideResizable(range) {
    const resizable = $(range.startContainer).closest('.resizable');

    if (resizable.length) {
        // 박스 바로 뒤로 커서를 이동
        const parentNode = resizable[0].parentNode;
        const resizableIndex = Array.from(parentNode.childNodes).indexOf(resizable[0]);

        // 새로운 Range 설정
        const newRange = document.createRange();
        const selection = window.getSelection();

        if (parentNode.childNodes[resizableIndex + 1]) {
            // 박스 뒤로 이동
            newRange.setStart(parentNode.childNodes[resizableIndex + 1], 0);
        } else {
            // 박스가 마지막 노드라면 부모의 끝으로 이동
            newRange.setStartAfter(resizable[0]);
        }
        newRange.collapse(true);

        // 선택된 Range 업데이트
        selection.removeAllRanges();
        selection.addRange(newRange);
    }
}

function wrapImagesWithResizable() {
    $('#editor img').not('.url-preview img').each(function () {
        var $img = $(this);
        var $resizable, $resizableWrap;

        // 이미 `.resizable_wrap`으로 감싸져 있는지 확인
        if ($img.closest('.resizable_wrap').length) {
            $resizableWrap = $img.closest('.resizable_wrap');
            $resizable = $resizableWrap.find('.resizable');
        } else {
            // ✅ `.resizable_wrap` 생성
            $resizableWrap = $('<div class="resizable_wrap"></div>');
            
            // ✅ `.resizable` 생성
            $resizable = $('<div class="resizable" contenteditable="false" draggable="true"></div>');
            
            // ✅ `.resizable` 기본 스타일 적용
            $resizable.css({
                position: 'relative',
                userSelect: 'none',
                caretColor: 'transparent',
                pointerEvents: 'auto'
            });

            // ✅ 크기 조절 핸들 추가
            var $resizeHandle = $('<div class="resize-handle"></div>');
            $resizable.append($img.clone()).append($resizeHandle);

            // ✅ 구조 변경: `.resizable_wrap` → `.resizable` → `<img>`
            $resizableWrap.append($resizable);
            $img.replaceWith($resizableWrap);
            
            // ✅ 크기 조절 기능 활성화
            makeImageResizableWithObserver($resizable);
        }

        // ✅ 현재 `.resizable`의 크기 저장
        var currentWidth = $resizable.width();
        var currentHeight = $resizable.height();
        $resizable.attr('data-original-width', currentWidth);
        $resizable.attr('data-original-height', currentHeight);
    });
}



$(document).ready(function () {
    
    /* 완전삭제를 사용하는 경우 함수교체
    function initResizableElements() {
        $('.resizable').each(function () {
            var $this = $(this);
            var $img = $this.find('img');

            // 이미지가 없거나, 이미지가 로드되지 않으면 .resizable 제거
            if ($img.length === 0) {
                console.warn("이미지 없음 - .resizable 삭제", $this);
                $this.remove();
                return;
            }

            // 이미지가 존재하는지 확인 (비동기 방식)
            checkImageExists($img.attr('src'), function(exists) {
                if (!exists) {
                    console.warn("이미지 로드 실패 - .resizable 삭제", $this);
                    $this.remove();
                }
            });

            observeResizable($this);
            makeImageResizableWithObserver($this);
        });
    }

    // ✅ 이미지 존재 여부 확인 함수 (fetch 사용)
    function checkImageExists(imageUrl, callback) {
        fetch(imageUrl, { method: 'HEAD' })
            .then(response => callback(response.ok))
            .catch(() => callback(false));
    }
    */
    
    function initResizableElements() {
        $('.resizable').each(function () {
            var $this = $(this);
            observeResizable($this);
            makeImageResizableWithObserver($this);
        });
    }
    
   

    // 최초 실행 (초기 로드된 .resizable 요소 감지)
    initResizableElements();
    wrapImagesWithResizable();

    // MutationObserver 설정 (동적 감지)
    const observer = new MutationObserver(() => {
        initResizableElements();
        wrapImagesWithResizable();
    });

    // #editor 내부에서 변경 사항 감지
    observer.observe(document.getElementById('editor'), { childList: true, subtree: true });
});

