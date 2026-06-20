document.addEventListener("DOMContentLoaded", function () {
    const resizableDivs = document.querySelectorAll("div.resizable");

    // 각 요소의 원래 비율을 data 속성에서 계산합니다.
    resizableDivs.forEach(div => {
        const originalWidth = div.getAttribute('data-original-width');
        const originalHeight = div.getAttribute('data-original-height');
        if (originalWidth && originalHeight) {
            div.dataset.ratio = originalHeight / originalWidth;
        } else {
            // 만약 data 속성이 없다면 현재 크기로 비율 계산 (이 경우는 새로고침 시 문제가 발생할 수 있음)
            const currentWidth = div.offsetWidth;
            const currentHeight = div.offsetHeight;
            if (currentWidth) {
                div.dataset.ratio = currentHeight / currentWidth;
            }
        }
    });

    function updateHeights() {
        resizableDivs.forEach(div => {
            const ratio = parseFloat(div.dataset.ratio);
            if (!isNaN(ratio)) {
                div.style.height = (div.offsetWidth * ratio) + "px";
            }
        });
    }

    // 페이지 로드 시와 창 크기 변경 시에 높이 업데이트
    updateHeights();
    window.addEventListener("resize", updateHeights);
});

$(document).ready(function () {
    const $anchors = $('.rb_anchor');

    if ($anchors.length > 0) {
        const $boCon = $('#bo_v_con');

        const $dropdown = $(`
    <div class="dropdown-wrap">
      <div class="dropdown" id="curationDropdown">
        <div class="dropdown-header">
          <div class="dropdown-title">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M3.33333 3.33325C3.11232 3.33325 2.90036 3.42105 2.74408 3.57733C2.5878 3.73361 2.5 3.94557 2.5 4.16659C2.5 4.3876 2.5878 4.59956 2.74408 4.75584C2.90036 4.91212 3.11232 4.99992 3.33333 4.99992H16.6667C16.8877 4.99992 17.0996 4.91212 17.2559 4.75584C17.4122 4.59956 17.5 4.3876 17.5 4.16659C17.5 3.94557 17.4122 3.73361 17.2559 3.57733C17.0996 3.42105 16.8877 3.33325 16.6667 3.33325H3.33333ZM2.5 9.99992C2.5 9.7789 2.5878 9.56694 2.74408 9.41066C2.90036 9.25438 3.11232 9.16659 3.33333 9.16659H5.83333C6.05435 9.16659 6.26631 9.25438 6.42259 9.41066C6.57887 9.56694 6.66667 9.7789 6.66667 9.99992C6.66667 10.2209 6.57887 10.4329 6.42259 10.5892C6.26631 10.7455 6.05435 10.8333 5.83333 10.8333H3.33333C3.11232 10.8333 2.90036 10.7455 2.74408 10.5892C2.5878 10.4329 2.5 10.2209 2.5 9.99992ZM2.5 15.8333C2.5 15.6122 2.5878 15.4003 2.74408 15.244C2.90036 15.0877 3.11232 14.9999 3.33333 14.9999H6.66667C6.88768 14.9999 7.09964 15.0877 7.25592 15.244C7.4122 15.4003 7.5 15.6122 7.5 15.8333C7.5 16.0543 7.4122 16.2662 7.25592 16.4225C7.09964 16.5788 6.88768 16.6666 6.66667 16.6666H3.33333C3.11232 16.6666 2.90036 16.5788 2.74408 16.4225C2.5878 16.2662 2.5 16.0543 2.5 15.8333ZM8.33333 11.6666C8.33349 11.0169 8.48556 10.3763 8.77739 9.79589C9.06922 9.21547 9.49273 8.71133 10.0141 8.32373C10.5355 7.93613 11.1402 7.67582 11.7801 7.56358C12.42 7.45134 13.0773 7.49028 13.6994 7.67729C14.3216 7.86431 14.8914 8.19421 15.3634 8.64066C15.8353 9.08711 16.1963 9.63773 16.4176 10.2485C16.6388 10.8594 16.7142 11.5135 16.6376 12.1586C16.5611 12.8037 16.3347 13.422 15.9767 13.9641L17.2558 15.2441C17.4076 15.4013 17.4916 15.6118 17.4897 15.8303C17.4878 16.0487 17.4002 16.2578 17.2457 16.4123C17.0912 16.5668 16.8822 16.6544 16.6637 16.6563C16.4452 16.6582 16.2347 16.5742 16.0775 16.4224L14.7975 15.1433C14.1695 15.5584 13.4407 15.7956 12.6887 15.8296C11.9366 15.8637 11.1894 15.6934 10.5264 15.3368C9.86335 14.9802 9.30934 14.4507 8.9232 13.8044C8.53706 13.1582 8.33321 12.4194 8.33333 11.6666ZM12.5 9.16659C12.1717 9.16659 11.8466 9.23125 11.5433 9.35689C11.24 9.48252 10.9644 9.66667 10.7322 9.89882C10.5001 10.131 10.3159 10.4066 10.1903 10.7099C10.0647 11.0132 10 11.3383 10 11.6666C10 11.9949 10.0647 12.32 10.1903 12.6233C10.3159 12.9266 10.5001 13.2022 10.7322 13.4344C10.9644 13.6665 11.24 13.8506 11.5433 13.9763C11.8466 14.1019 12.1717 14.1666 12.5 14.1666C13.163 14.1666 13.7989 13.9032 14.2678 13.4344C14.7366 12.9655 15 12.3296 15 11.6666C15 11.0035 14.7366 10.3677 14.2678 9.89882C13.7989 9.42998 13.163 9.16659 12.5 9.16659Z" fill="#09244B"></path>
              </svg>
            <div class="dropdown-title-text">콘텐츠 목차</div>
          </div>
          <div class="dropdown-arrow">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-chevron-down"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </div>
        </div>
        <ul class="dropdown-content"></ul>
      </div>
    </div>
    `);

        const $list = $dropdown.find('.dropdown-content');

        $anchors.each(function (i) {
            const $el = $(this);
            const id = $el.attr('id');
            const text = $el.text().trim();
            const num = String(i + 1).padStart(2, '0');

            const $item = $(`
        <button type="button" class="dropdown-item" data-target="#${id}">
          <span>${num}.</span>
          <div class="text">${text}</div>
        </button>
      `);

            $list.append($item); // 이벤트는 따로 위임 방식으로 처리
        });


        let lastTouched = 0;

        $list.on('click', '.dropdown-item', function (e) {
             const $editorss = $('.rb_editor_data');
            const $blocker = $editorss.find('.editor-blocker');
            if (Date.now() - lastTouched < 500) return;

            handleDropdownItemClick($(this));
            $blocker.hide();
        });

        $list.on('touchstart', '.dropdown-item', function (e) {
            lastTouched = Date.now(); // touch 시각 기록
            handleDropdownItemClick($(this));
        });

        function handleDropdownItemClick($item) {
            const $target = $($item.data('target'));
            if ($target.length) {
                const headerHeight = $(parent.document).find('#header').outerHeight() || 0;
                
                $('html, body').animate({
                    scrollTop: $target.offset().top - headerHeight - 230
                }, 300);

                $('.dropdown-item').removeClass('active');
                $item.addClass('active');

                const selectedText = $item.find('.text').text().trim();
                $dropdown.find('.dropdown-title-text').text(selectedText);

                $dropdown.removeClass('open');
            }
        }

        $dropdown.find('.dropdown-header').on('click', function () {
            const $editorss = $('.rb_editor_data');

            if (!$editorss.find('.editor-blocker').length) {
                $editorss.append('<div class="editor-blocker"></div>');
            }

            const $blocker = $editorss.find('.editor-blocker');

            if ($dropdown.hasClass('open')) {
                $dropdown.removeClass('open');
                $blocker.hide();

            } else {
                $dropdown.addClass('open');
                const contentHeight = $dropdown.find('.dropdown-content').outerHeight(true);

                $blocker.show(); // 클릭 막기
            }
        });

        $boCon.prepend($dropdown);
        
        // 부모의 #header 높이를 가져와서 top 위치 조정
        try {
            const headerHeight = $(parent.document).find('#header').outerHeight() || 0;
            const topValue = headerHeight + 10;

            $dropdown.css('top', topValue + 'px');
        } catch (e) {
            console.warn('부모 프레임의 #header 높이를 가져올 수 없습니다.', e);
        }


    }
});