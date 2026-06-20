$(document).ready(function () {
    // 프리뷰 버튼 클릭 이벤트
    $('#preview-btn').click(function () {
        const editorContent = $('#editor').html();

        // #editor의 인라인 스타일 가져오기
        const editorInlineStyle = document.getElementById('editor').getAttribute('style') || '';

        // #editor의 현재 너비 가져오기
        let editorWidth = $('#editor-container').width() || 
                          document.getElementById('editor-container').getBoundingClientRect().width;

        // 최소 너비 제한
        const minWidth = 400;
        const popupWidth = Math.max(editorWidth, minWidth);

        // 현재 적용된 폰트 CSS 파일 가져오기
        let fontHref = $('#font-stylesheet').attr('href') || '';

        // 팝업창 열기
        const popupWindow = window.open('', 'previewWindow', `width=${popupWidth},height=600,resizable=yes`);

        // 팝업에 HTML 작성 (jQuery 포함 및 adjustOutputResizable 스크립트 추가)
        popupWindow.document.write(`
          <!DOCTYPE html>
          <html lang="ko">
          <head>
              <meta charset="UTF-8">
              <title>미리보기</title>
              <link rel="stylesheet" href="css/preview.css">
              ${fontHref ? `<link rel="stylesheet" href="${fontHref}">` : ''}
              <meta name="viewport" content="width=device-width, initial-scale=1.0">
              <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
          </head>
          <body>
              <div id="rb_editor_preview" class="rb_editor_data" style="${editorInlineStyle}">
                  ${editorContent}
              </div>
              <script>
                document.addEventListener("DOMContentLoaded", function() {
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
              </script>
          </body>
          </html>
        `);

        popupWindow.document.close();

        // 팝업 크기 조정
        popupWindow.resizeTo(popupWidth + 20, 620);
    });
});
