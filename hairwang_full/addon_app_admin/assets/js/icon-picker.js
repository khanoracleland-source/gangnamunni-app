/**
 * Font Awesome Icon Picker
 * @package AddonAppAdmin
 * @version 1.0.0
 */

// Font Awesome 4.7 아이콘 목록 (자주 사용하는 아이콘)
var faIcons = [
    // 일반 아이콘
    'fa-th-large', 'fa-th', 'fa-th-list', 'fa-list', 'fa-list-ul', 'fa-list-ol',
    'fa-square', 'fa-square-o', 'fa-circle', 'fa-circle-o', 'fa-circle-thin',

    // 화살표
    'fa-arrow-up', 'fa-arrow-down', 'fa-arrow-left', 'fa-arrow-right',
    'fa-arrow-circle-up', 'fa-arrow-circle-down', 'fa-arrow-circle-left', 'fa-arrow-circle-right',
    'fa-caret-up', 'fa-caret-down', 'fa-caret-left', 'fa-caret-right',
    'fa-angle-up', 'fa-angle-down', 'fa-angle-left', 'fa-angle-right',
    'fa-chevron-up', 'fa-chevron-down', 'fa-chevron-left', 'fa-chevron-right',

    // 파일 및 문서
    'fa-file', 'fa-file-o', 'fa-file-text', 'fa-file-text-o', 'fa-file-image-o',
    'fa-file-pdf-o', 'fa-file-word-o', 'fa-file-excel-o', 'fa-file-powerpoint-o',
    'fa-folder', 'fa-folder-o', 'fa-folder-open', 'fa-folder-open-o',

    // 커뮤니케이션
    'fa-comment', 'fa-comment-o', 'fa-comments', 'fa-comments-o',
    'fa-envelope', 'fa-envelope-o', 'fa-inbox', 'fa-phone', 'fa-phone-square',
    'fa-bullhorn', 'fa-bell', 'fa-bell-o', 'fa-bell-slash', 'fa-bell-slash-o',

    // 사용자
    'fa-user', 'fa-user-o', 'fa-users', 'fa-user-plus', 'fa-user-times',
    'fa-user-circle', 'fa-user-circle-o', 'fa-user-md', 'fa-user-secret',

    // 미디어
    'fa-image', 'fa-picture-o', 'fa-camera', 'fa-camera-retro', 'fa-video-camera',
    'fa-film', 'fa-music', 'fa-play', 'fa-pause', 'fa-stop', 'fa-microphone',

    // 쇼핑
    'fa-shopping-cart', 'fa-shopping-bag', 'fa-shopping-basket', 'fa-credit-card',
    'fa-money', 'fa-usd', 'fa-krw', 'fa-gift', 'fa-ticket',

    // 위치 및 지도
    'fa-map', 'fa-map-o', 'fa-map-marker', 'fa-map-pin', 'fa-globe', 'fa-location-arrow',
    'fa-compass', 'fa-street-view',

    // 시간
    'fa-calendar', 'fa-calendar-o', 'fa-calendar-check-o', 'fa-calendar-times-o',
    'fa-clock-o', 'fa-hourglass', 'fa-hourglass-half',

    // 액션
    'fa-plus', 'fa-plus-circle', 'fa-plus-square', 'fa-plus-square-o',
    'fa-minus', 'fa-minus-circle', 'fa-minus-square', 'fa-minus-square-o',
    'fa-times', 'fa-times-circle', 'fa-times-circle-o',
    'fa-check', 'fa-check-circle', 'fa-check-circle-o', 'fa-check-square', 'fa-check-square-o',
    'fa-edit', 'fa-pencil', 'fa-pencil-square', 'fa-pencil-square-o',
    'fa-trash', 'fa-trash-o', 'fa-save', 'fa-download', 'fa-upload',
    'fa-search', 'fa-search-plus', 'fa-search-minus',
    'fa-refresh', 'fa-sync', 'fa-cog', 'fa-cogs',

    // 상태
    'fa-star', 'fa-star-o', 'fa-star-half', 'fa-star-half-o',
    'fa-heart', 'fa-heart-o', 'fa-thumbs-up', 'fa-thumbs-o-up', 'fa-thumbs-down', 'fa-thumbs-o-down',
    'fa-flag', 'fa-flag-o', 'fa-flag-checkered', 'fa-bookmark', 'fa-bookmark-o',
    'fa-exclamation', 'fa-exclamation-circle', 'fa-exclamation-triangle',
    'fa-question', 'fa-question-circle', 'fa-question-circle-o',
    'fa-info', 'fa-info-circle',

    // 네비게이션
    'fa-home', 'fa-bars', 'fa-navicon', 'fa-reorder', 'fa-ellipsis-h', 'fa-ellipsis-v',
    'fa-sliders', 'fa-dashboard', 'fa-tachometer',

    // 소셜
    'fa-facebook', 'fa-facebook-square', 'fa-facebook-official',
    'fa-twitter', 'fa-twitter-square', 'fa-instagram', 'fa-youtube', 'fa-youtube-play',
    'fa-linkedin', 'fa-linkedin-square', 'fa-google', 'fa-google-plus',

    // 기타
    'fa-coffee', 'fa-beer', 'fa-glass', 'fa-cutlery', 'fa-birthday-cake',
    'fa-graduation-cap', 'fa-book', 'fa-bookmark', 'fa-briefcase', 'fa-suitcase',
    'fa-building', 'fa-building-o', 'fa-hospital-o', 'fa-medkit',
    'fa-car', 'fa-taxi', 'fa-bus', 'fa-plane', 'fa-rocket', 'fa-ship',
    'fa-tree', 'fa-leaf', 'fa-fire', 'fa-bolt', 'fa-lightbulb-o',
    'fa-wrench', 'fa-gavel', 'fa-key', 'fa-lock', 'fa-unlock', 'fa-unlock-alt',
    'fa-shield', 'fa-trophy', 'fa-tag', 'fa-tags', 'fa-barcode', 'fa-qrcode'
];

// 아이콘 피커 모달 생성
function createIconPickerModal() {
    if (document.getElementById('icon-picker-modal')) {
        return; // 이미 존재하면 생성하지 않음
    }

    var modal = document.createElement('div');
    modal.id = 'icon-picker-modal';
    modal.style.cssText = 'display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);';

    var modalContent = document.createElement('div');
    modalContent.style.cssText = 'background-color: white; margin: 5% auto; padding: 0; border-radius: 12px; width: 90%; max-width: 900px; max-height: 80vh; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.3);';

    modalContent.innerHTML = `
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; color: white; border-radius: 12px 12px 0 0;">
            <h2 style="margin: 0; font-size: 24px; display: flex; align-items: center; justify-content: space-between;">
                <span><i class="fa fa-icons"></i> 아이콘 선택</span>
                <button onclick="closeIconPicker()" style="background: rgba(255,255,255,0.2); border: none; color: white; font-size: 24px; cursor: pointer; width: 35px; height: 35px; border-radius: 50%; transition: all 0.3s;">
                    <i class="fa fa-times"></i>
                </button>
            </h2>
        </div>

        <div style="padding: 20px;">
            <div style="margin-bottom: 20px;">
                <input type="text" id="icon-search" placeholder="아이콘 검색... (예: home, user, cart)"
                       style="width: 100%; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 14px;"
                       onkeyup="filterIcons(this.value)">
            </div>

            <div id="icon-grid" style="max-height: 50vh; overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 10px;">
                <!-- 아이콘들이 여기에 동적으로 추가됩니다 -->
            </div>
        </div>
    `;

    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    // 아이콘 그리드 생성
    renderIconGrid();

    // 모달 외부 클릭 시 닫기
    modal.onclick = function(event) {
        if (event.target === modal) {
            closeIconPicker();
        }
    };
}

// 아이콘 그리드 렌더링
function renderIconGrid(filterText = '') {
    var iconGrid = document.getElementById('icon-grid');
    if (!iconGrid) return;

    iconGrid.innerHTML = '';

    var filteredIcons = faIcons.filter(function(icon) {
        if (!filterText) return true;
        return icon.toLowerCase().indexOf(filterText.toLowerCase()) !== -1;
    });

    filteredIcons.forEach(function(iconClass) {
        var iconItem = document.createElement('div');
        iconItem.className = 'icon-picker-item';
        iconItem.style.cssText = 'display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 15px; border: 2px solid #eee; border-radius: 8px; cursor: pointer; transition: all 0.3s; text-align: center;';

        iconItem.innerHTML = `
            <i class="fa ${iconClass}" style="font-size: 28px; color: #aa20ff; margin-bottom: 8px;"></i>
            <span style="font-size: 11px; color: #666; word-break: break-all;">${iconClass.replace('fa-', '')}</span>
        `;

        iconItem.onclick = function() {
            selectIcon(iconClass);
        };

        iconItem.onmouseenter = function() {
            this.style.borderColor = '#aa20ff';
            this.style.backgroundColor = '#f5f0ff';
            this.style.transform = 'translateY(-3px)';
            this.style.boxShadow = '0 4px 12px rgba(170, 32, 255, 0.2)';
        };

        iconItem.onmouseleave = function() {
            this.style.borderColor = '#eee';
            this.style.backgroundColor = 'transparent';
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        };

        iconGrid.appendChild(iconItem);
    });

    if (filteredIcons.length === 0) {
        iconGrid.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">검색 결과가 없습니다.</p>';
    }
}

// 아이콘 필터링
function filterIcons(searchText) {
    renderIconGrid(searchText);
}

// 아이콘 피커 열기
var currentIconInput = null;
var currentIconPreview = null;

function openIconPicker(inputElement, previewElement) {
    createIconPickerModal();

    currentIconInput = inputElement;
    currentIconPreview = previewElement;

    var modal = document.getElementById('icon-picker-modal');
    modal.style.display = 'block';

    // 검색 입력란에 포커스
    setTimeout(function() {
        var searchInput = document.getElementById('icon-search');
        if (searchInput) {
            searchInput.focus();
        }
    }, 100);
}

// 아이콘 선택
function selectIcon(iconClass) {
    if (currentIconInput) {
        currentIconInput.value = iconClass;

        // 입력 이벤트 트리거 (onkeyup 등의 이벤트 핸들러 실행)
        var event = new Event('input', { bubbles: true });
        currentIconInput.dispatchEvent(event);

        // keyup 이벤트도 트리거
        var keyupEvent = new Event('keyup', { bubbles: true });
        currentIconInput.dispatchEvent(keyupEvent);
    }

    if (currentIconPreview) {
        currentIconPreview.className = 'fa ' + iconClass;
    }

    closeIconPicker();
}

// 아이콘 피커 닫기
function closeIconPicker() {
    var modal = document.getElementById('icon-picker-modal');
    if (modal) {
        modal.style.display = 'none';
    }

    currentIconInput = null;
    currentIconPreview = null;
}

// ESC 키로 닫기
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeIconPicker();
    }
});
