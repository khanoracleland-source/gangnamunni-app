
    var urlRegex = /(https?:\/\/[^\s]+)/g;
    var imageRegex = /\.(jpeg|jpg|gif|png|webp|svg)$/i;
    var pendingRequests = {};

    function extractVideoId(url) {
        let videoId = null;
        let timeParam = '';

        if (url.includes('youtube.com') || url.includes('youtu.be')) {
            let match = url.match(/(?:v=|youtu\.be\/|embed\/|shorts\/)([\w-]+)/);
            videoId = match ? match[1] : null;
            let timeMatch = url.match(/[?&]t=(\d+)/);
            timeParam = timeMatch ? `?start=${timeMatch[1]}` : '';
            return videoId ? `https://www.youtube.com/embed/${videoId}${timeParam}` : null;
        }

        if (url.includes('vimeo.com')) {
            let match = url.match(/vimeo\.com\/(\d+)/);
            videoId = match ? match[1] : null;
            return videoId ? `https://player.vimeo.com/video/${videoId}` : null;
        }

        return null;
    }

    function fetchMetadataAndDisplay(url, range) {
        // <pre> 내부에서 실행되지 않도록 차단
        if ($(range.commonAncestorContainer).closest('pre').length > 0) {
            console.warn("🚫 <pre> 내부에서는 미디어 또는 메타데이터를 삽입할 수 없습니다.");
            return;
        }

        let embedUrl = extractVideoId(url);

        if ($(range.commonAncestorContainer).closest('table, td, th').length) {
            return;
        }

        if (embedUrl) {
            displayVideoPreview(url, embedUrl, range);
            return;
        }

        $.ajax({
            url: g5Config.g5_editor_url + '/php/rb.metadata.php',
            method: 'GET',
            data: { url: url },
            dataType: 'json',
            success: function (response) {
                if (response.error) {
                    return;
                }

                var meta = response.meta;
                var title = response.title || '제목 없음';
                var description = meta['description'] || meta['og:description'] || '';
                var image = meta['og:image'] || '';

                var wrapper = $('<div class="url-preview-meta"></div>'); // 메타데이터 감싸는 div
                var html = $('<div class="url-preview" data-url="' + url + '" contenteditable="false"></div>');

                var imageWrapper = $('<li class="proxyImg"></li>'); // 이미지 감싸는 요소
                var metaData = $('<li class="metaData"></li>');

                function validateImageUrl(imageUrl, callback) {
                    var img = new Image();
                    img.src = imageUrl;
                    img.onload = function () {
                        callback(true); // 이미지 로드 성공
                    };
                    img.onerror = function () {
                        callback(false); // 이미지 로드 실패
                    };
                }

                if (image) {
                    var imageProxyUrl = g5Config.g5_editor_url + '/php/rb.image_proxy.php?url=' + encodeURIComponent(image);
                    var imageElement = $('<a href="' + url + '" target="_blank"><img src="' + imageProxyUrl + '" alt="Preview Image"></a>');

                    validateImageUrl(imageProxyUrl, function (isValid) {
                        if (isValid) {
                            imageWrapper.append(imageElement);
                            html.append(imageWrapper);
                        } else {
                            imageWrapper.remove(); // 엑박 방지: 이미지가 없으면 제거
                            metaData.css("padding-left", "0"); // 자동으로 padding-left 제거
                        }
                    });

                    imageElement.on('error', function () {
                        imageWrapper.remove(); // 이미지가 깨지면 자동 제거
                        metaData.css("padding-left", "0"); // padding-left: 0 적용
                    });
                } else {
                    metaData.css("padding-left", "0"); // 이미지가 없으면 자동 적용
                }

                metaData.append('<h3>' + title + '</h3>');
                metaData.append('<p>' + description + '</p>');
                metaData.append('<a href="' + url + '" target="_blank" style="padding-top:5px;">' + url + '</a>');

                html.append(metaData);
                wrapper.append(html);

                insertMediaAfterUrl(wrapper, range);
            }
        });
    }

    function displayVideoPreview(url, embedUrl, range) {
        // <pre> 내부에서는 동영상 미리보기를 삽입하지 않도록 제한
        if ($(range.commonAncestorContainer).closest('pre').length > 0) {
            //console.warn("<pre> 내부에서는 동영상 미리보기를 삽입할 수 없습니다.");
            return;
        }

        if ($(range.commonAncestorContainer).closest('table, td, th').length) {
            return;
        }

        var wrapper = $('<div class="url-preview-video"></div>'); // ✅ 동영상 감싸는 div
        var html = $('<div class="url-preview resizable" data-url="' + url + '" contenteditable="false"></div>');
        html.append('<div class="rb-video-container"><iframe id="meta-iframe-video" src="' + embedUrl + '" frameborder="0" allowfullscreen></iframe></div>');

        wrapper.append(html); // 감싸는 div 추가
        insertMediaAfterUrl(wrapper, range);
    }


    function insertMediaAfterUrl(node, range) {
        range.collapse(false);
        range.insertNode(node[0]);
        range.collapse(false);

        var selection = window.getSelection();
        selection.removeAllRanges();
        var newRange = document.createRange();
        newRange.setStartAfter(node[0]);
        newRange.collapse(true);
        selection.addRange(newRange);

        // 미디어 노드 내의 .resizable 요소에 대해 현재 크기를 data 속성으로 업데이트
        node.find('.resizable').each(function(){
            var $this = $(this);
            var currentWidth = $this.width();
            var currentHeight = $this.height();
            $this.attr('data-original-width', currentWidth);
            $this.attr('data-original-height', currentHeight);
            // 필요시 data-ratio도 갱신 (세로/가로)
            $this.attr('data-ratio', currentHeight / currentWidth);
        });
    }

    

    function insertImageDirectly(url, range) {
        if ($(range.commonAncestorContainer).closest('table, td, th').length) {
            return;
        }

        var wrapper = $('<div class="url-preview-img"></div>'); // ✅ 이미지 감싸는 div
        var html = $('<div class="url-preview resizable" data-url="' + url + '" contenteditable="false"></div>');
        var img = $('<img src="' + url + '" alt="Embedded Image">');

        img.on('load', function () {
            // ✅ 이미지 원본 크기 저장
            var imgWidth = this.naturalWidth;
            var imgHeight = this.naturalHeight;
            var ratio = imgHeight / imgWidth;

            const editorWidth = $('#editor').width(); // 에디터의 가로 크기

            // ✅ 에디터보다 크면 width: 100%, 작으면 원본 크기 유지
            if (imgWidth > editorWidth) {
                html.css('width', '100%');
            } else {
                html.css('width', imgWidth + 'px');
            }

            // ✅ 원본 크기 저장 (비율 유지용)
            html.attr('data-original-width', imgWidth);
            html.attr('data-original-height', imgHeight);
            html.attr('data-ratio', ratio);

            // ✅ 높이 설정 (현재 width에 따라 비율 유지)
            html.css('height', (html.width() * ratio) + 'px');

            // ✅ 창 크기 변경 시 높이 자동 조정
            function updateHeight() {
                var currentWidth = html.width();
                var originalWidth = parseFloat(html.attr('data-original-width')) || currentWidth;
                var originalHeight = parseFloat(html.attr('data-original-height')) || (currentWidth * ratio);
                var originalRatio = parseFloat(html.attr('data-ratio')) || (originalHeight / originalWidth);

                html.css('height', (currentWidth * originalRatio) + 'px');
            }
            $(window).on('resize', updateHeight);

            // ✅ 크기 조절 기능 적용 (비율 유지)
            makeImageResizableWithObserver(html);
        });

        html.append(img);
        html.append('<button type="button" class="delete-preview"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" clip-rule="evenodd" d="M11.9998 13.414L17.6568 19.071C17.8454 19.2532 18.098 19.3539 18.3602 19.3517C18.6224 19.3494 18.8732 19.2442 19.0586 19.0588C19.2441 18.8734 19.3492 18.6226 19.3515 18.3604C19.3538 18.0982 19.253 17.8456 19.0708 17.657L13.4138 12L19.0708 6.343C19.253 6.15439 19.3538 5.90179 19.3515 5.6396C19.3492 5.3774 19.2441 5.12659 19.0586 4.94118C18.8732 4.75577 18.6224 4.6506 18.3602 4.64832C18.098 4.64604 17.8454 4.74684 17.6568 4.929L11.9998 10.586L6.34282 4.929C6.15337 4.75134 5.90224 4.65436 5.64255 4.65858C5.38287 4.6628 5.13502 4.76788 4.95143 4.95159C4.76785 5.1353 4.66294 5.38323 4.65891 5.64292C4.65488 5.9026 4.75203 6.15367 4.92982 6.343L10.5858 12L4.92882 17.657C4.83331 17.7492 4.75713 17.8596 4.70472 17.9816C4.65231 18.1036 4.62473 18.2348 4.62357 18.3676C4.62242 18.5004 4.64772 18.6321 4.698 18.755C4.74828 18.8778 4.82254 18.9895 4.91643 19.0834C5.01032 19.1773 5.12197 19.2515 5.24487 19.3018C5.36777 19.3521 5.49944 19.3774 5.63222 19.3762C5.765 19.3751 5.89622 19.3475 6.01823 19.2951C6.14023 19.2427 6.25058 19.1665 6.34282 19.071L11.9998 13.414Z" fill="#09244B"/></svg></button>');

        wrapper.append(html);
        insertMediaAfterUrl(wrapper, range);
    }


    $('#editor').on('click', '.delete-preview', function (e) {
        e.preventDefault();
        e.stopPropagation(); // 부모로의 이벤트 버블링 방지
        $(this).closest('.url-preview-img, .url-preview-meta, .url-preview-video').remove();
        $('#image-toolbar').fadeOut(0);
        selectedImage = null;
    });

