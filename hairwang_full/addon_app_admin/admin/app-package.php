<?php
/**
 * 앱 패키징 대시보드
 * rebuilder.co.kr/content/app 스타일 참조
 * @package AddonAppAdmin
 * @version 1.0.0
 * @date 2026-01-19
 */

include_once('./_common.php');

// 권한 확인
// 권한 체크 제거됨
// if (!addon_app_admin_check_permission()) {
//     addon_app_admin_permission_denied();
// }

// 레이아웃 헤더
$g5['title'] = '앱 패키징 대시보드';
include_once('./layout_header.php');

// 업로드된 앱 소스 목록
$sql = " SELECT * FROM {$addon_app_admin_table_app_sources} ORDER BY upload_date DESC ";
$result = sql_query($sql);
$app_sources = array();
while ($row = sql_fetch_array($result)) {
    $app_sources[] = $row;
}

// 페이지 디버그 정보
$GLOBALS['page_debug'] = array(
    'page' => '앱 패키징 대시보드',
    'sql_query' => $sql,
    'app_sources_count' => count($app_sources)
);

?>

<link rel="stylesheet" href="<?php echo ADDON_APP_ADMIN_URL; ?>/assets/css/admin.css">
<link rel="stylesheet" href="<?php echo ADDON_APP_ADMIN_URL; ?>/assets/css/app-package.css">

<div class="addon-app-admin-wrap">

    <!-- 헤더 -->
    <div class="addon-header">
        <div class="addon-container">
            <div class="addon-header-inner">
                <h1 class="addon-logo">
                    <i class="fa fa-mobile"></i> 앱 패키징 대시보드
                </h1>
                <div class="addon-header-right">
                    <a href="./index.php" class="addon-btn addon-btn-sm addon-btn-secondary">
                        <i class="fa fa-arrow-left"></i> 대시보드로
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="addon-container">

        <!-- 서비스 소개 -->
        <div class="plf_wrap">
            <div class="plf_sec1">
                <ul class="plf_sec1_tit font-B">
                    <span>
                        <span style="color: #aa20ff;">안드로이드 & iOS 패키징 서비스</span><br>
                        앱 아이콘, 스플래시, 배너 이미지를 손쉽게 변경하세요.
                    </span>
                </ul>

                <ul class="plf_sec1_sub">
                    앱 소스 폴더를 업로드하면 이미지 변경 대시보드가 새 탭으로 열립니다.<br>
                    rebuilder.co.kr 스타일의 직관적인 UI로 쉽게 이미지를 교체할 수 있습니다.<br>
                    변경 후 다운로드 받아 앱스토어에 업로드하세요.
                </ul>
            </div>
        </div>

        <!-- 앱 소스 업로드 -->
        <div class="mbs_list plf_sec1">
            <ul class="plf_sec1_tit font-B">
                <span>앱 소스 업로드하기</span>
            </ul>

            <div class="app_add_wrap">
                <div class="app_add_wrap_ul1">
                    <li>
                        <dl class="app_inp_wrap">
                            <dd class="app_add_info_tit font-B">앱 타입</dd><br>
                            <input type="radio" name="app_type" id="app_type_android" class="app_chk" value="android" checked>
                            <label for="app_type_android" class="font-B">Android</label>
                            <input type="radio" name="app_type" id="app_type_ios" class="app_apk" value="ios">
                            <label for="app_type_ios" class="font-B">iOS</label>
                        </dl>
                        <dl class="app_add_info addon-mt-20">
                            <dd class="app_add_info_sub">
                                Android 또는 iOS 앱 소스를 선택하세요.<br>
                                각 플랫폼에 맞는 이미지 편집 도구가 제공됩니다.
                            </dd>
                        </dl>
                    </li>

                    <li class="addon-mt-30">
                        <dl class="app_add_info">
                            <dd class="app_add_info_tit font-B">버전 정보</dd>
                            <input type="text" name="app_version" class="app_inp" placeholder="예: 1.0.0" value="1.0.0">
                        </dl>
                        <dl class="app_add_info addon-mt-20">
                            <dd class="app_add_info_sub">
                                앱 버전을 입력하세요. (선택사항)
                            </dd>
                        </dl>
                    </li>

                    <li class="addon-mt-30">
                        <dl class="app_add_info">
                            <dd class="app_add_info_tit font-B">설명</dd>
                            <textarea name="app_description" class="app_textarea" placeholder="앱에 대한 간단한 설명 (선택)"></textarea>
                        </dl>
                    </li>

                    <li class="addon-mt-30">
                        <div class="app-upload-zone" id="app-upload-zone">
                            <div class="app-upload-icon">
                                <i class="fa fa-cloud-upload"></i>
                            </div>
                            <div class="app-upload-text">
                                <p class="font-B">앱 소스 폴더를 여기에 드래그하거나 클릭하여 업로드</p>
                                <p class="app-upload-desc">ZIP 파일만 업로드 가능 (최대 100MB)</p>
                            </div>
                            <input type="file" id="app-file-input" accept=".zip" style="display: none;">
                        </div>

                        <div id="app-upload-progress" style="display: none; margin-top: 20px;">
                            <div class="progress-bar">
                                <div class="progress-fill" id="progress-fill"></div>
                            </div>
                            <p id="progress-text" class="addon-text-center addon-mt-10">업로드 중... 0%</p>
                        </div>
                    </li>

                    <li class="addon-mt-30">
                        <button type="button" class="addon-btn addon-btn-primary addon-btn-block" id="btn-start-upload" disabled>
                            <i class="fa fa-upload"></i> 업로드 및 대시보드 열기
                        </button>
                    </li>
                </div>
            </div>
        </div>

        <!-- 업로드된 앱 목록 -->
        <div class="addon-section addon-mt-30">
            <h2 class="addon-section-title">
                <i class="fa fa-history"></i> 업로드된 앱 목록 (<?php echo count($app_sources); ?>개)
            </h2>

            <?php if (count($app_sources) > 0): ?>
                <div class="app-list-grid">
                    <?php foreach ($app_sources as $app): ?>
                        <div class="app-list-item">
                            <div class="app_list_mem">
                                <div class="app-list-icon">
                                    <?php if ($app['app_type'] === 'android'): ?>
                                        <i class="fa fa-android" style="color: #3ddc84;"></i>
                                    <?php else: ?>
                                        <i class="fa fa-apple" style="color: #000;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="app-list-info">
                                    <div class="app_add_info_tit font-B">
                                        <?php echo $app['app_type'] === 'android' ? 'Android 앱' : 'iOS 앱'; ?>
                                        <?php if ($app['version']): ?>
                                            <span class="app-version-badge">v<?php echo htmlspecialchars($app['version']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="app_add_info_sub addon-mt-10">
                                        업로드: <?php echo date('Y-m-d H:i', strtotime($app['upload_date'])); ?>
                                    </div>
                                    <div class="app_add_info_sub">
                                        상태:
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($app['status']) {
                                            case 'ready':
                                                $status_class = 'ap_status_down';
                                                $status_text = '준비완료';
                                                break;
                                            case 'processing':
                                                $status_class = 'ap_status';
                                                $status_text = '처리중';
                                                break;
                                            case 'error':
                                                $status_class = 'ap_status_del';
                                                $status_text = '오류';
                                                break;
                                            default:
                                                $status_class = 'ap_status';
                                                $status_text = '업로드됨';
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </div>
                                    <?php if ($app['description']): ?>
                                        <div class="app_add_info_sub2 addon-mt-10">
                                            <?php echo nl2br(htmlspecialchars($app['description'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="rb_hub_btns addon-mt-20">
                                <button type="button" class="addon-btn addon-btn-primary" onclick="openImageEditor(<?php echo $app['source_id']; ?>)">
                                    <i class="fa fa-edit"></i> 이미지 편집
                                </button>
                                <button type="button" class="addon-btn addon-btn-danger" onclick="deleteAppSource(<?php echo $app['source_id']; ?>)">
                                    <i class="fa fa-trash"></i> 삭제
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="addon-text-center addon-text-muted" style="padding: 40px 0;">
                    업로드된 앱 소스가 없습니다. 위에서 앱 소스를 업로드하세요.
                </p>
            <?php endif; ?>
        </div>

        <!-- 사용 가이드 -->
        <div class="addon-section addon-mt-30">
            <h2 class="addon-section-title">
                <i class="fa fa-question-circle"></i> 사용 가이드
            </h2>
            <div class="guide-steps">
                <div class="guide-step">
                    <div class="guide-step-number">1</div>
                    <div class="guide-step-content">
                        <h4>앱 소스 준비</h4>
                        <p>Android 또는 iOS 앱 소스 폴더를 ZIP 파일로 압축합니다.</p>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-number">2</div>
                    <div class="guide-step-content">
                        <h4>소스 업로드</h4>
                        <p>위의 업로드 영역에 ZIP 파일을 드래그하거나 클릭하여 업로드합니다.</p>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-number">3</div>
                    <div class="guide-step-content">
                        <h4>이미지 편집</h4>
                        <p>업로드 완료 후 자동으로 열리는 대시보드에서 앱 아이콘, 스플래시 등을 변경합니다.</p>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="guide-step-number">4</div>
                    <div class="guide-step-content">
                        <h4>다운로드 및 배포</h4>
                        <p>변경 완료 후 수정된 소스를 다운로드 받아 앱스토어에 업로드합니다.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- 푸터 -->
    <div class="addon-footer">
        <div class="addon-container">
            <div class="addon-footer-content">
                <p>&copy; 2026 Hair Wang App Admin. All rights reserved.</p>
            </div>
        </div>
    </div>

</div>

<script src="<?php echo ADDON_APP_ADMIN_URL; ?>/assets/js/app-package.js"></script>

<script>
// 이미지 편집기 열기
function openImageEditor(sourceId) {
    // 새 탭에서 이미지 편집 대시보드 열기
    window.open('./app-image-editor.php?source_id=' + sourceId, '_blank');
}

// 앱 소스 삭제
function deleteAppSource(sourceId) {
    if (confirm('정말 이 앱 소스를 삭제하시겠습니까?')) {
        location.href = './app-package-delete.php?source_id=' + sourceId + '&token=<?php echo get_admin_token(); ?>';
    }
}
</script>

<?php
include_once('./layout_footer.php');
?>
