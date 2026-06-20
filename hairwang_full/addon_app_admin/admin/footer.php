<?php
/**
 * 사이트 푸터 관리
 * @package AddonAppAdmin
 * @version 1.0.0
 */

include_once('./_common.php');

// 권한 체크 제거됨
// if (!addon_app_admin_check_permission()) {
//     addon_app_admin_permission_denied();
// }

// 푸터 항목 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_footer') {
    check_admin_token();

    $footer_key = isset($_POST['footer_key']) ? $_POST['footer_key'] : '';
    $title = isset($_POST['title']) ? $_POST['title'] : '';
    $content = isset($_POST['content']) ? $_POST['content'] : '';
    $link_url = isset($_POST['link_url']) ? $_POST['link_url'] : '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $sort_order = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;

    if ($footer_key && $title) {
        $data = array(
            'footer_key' => $footer_key,
            'title' => $title,
            'content' => $content,
            'link_url' => $link_url,
            'is_active' => $is_active,
            'sort_order' => $sort_order
        );

        if (addon_app_admin_save_footer($data)) {
            addon_app_admin_log('footer_update', $footer_key, '푸터 항목 업데이트: ' . $title);
            alert('푸터 항목이 저장되었습니다.', './footer.php');
        } else {
            alert('저장 중 오류가 발생했습니다.');
        }
    } else {
        alert('필수 항목을 입력해주세요.');
    }
}

// 푸터 항목 삭제
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['footer_key'])) {
    check_admin_token();

    $footer_key = $_GET['footer_key'];

    $sql = " DELETE FROM {$addon_app_admin_table_footer} WHERE footer_key = '{$footer_key}' ";
    sql_query($sql);

    addon_app_admin_log('footer_delete', $footer_key, '푸터 항목 삭제');
    alert('푸터 항목이 삭제되었습니다.', './footer.php');
}

// 회사 정보 저장
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_company') {
    check_admin_token();

    // 회사명
    if (isset($_POST['company_name'])) {
        addon_app_admin_set_config('company_name', $_POST['company_name'], 'text', 'footer');
    }

    // 대표자명
    if (isset($_POST['ceo_name'])) {
        addon_app_admin_set_config('ceo_name', $_POST['ceo_name'], 'text', 'footer');
    }

    // 사업자등록번호
    if (isset($_POST['business_number'])) {
        addon_app_admin_set_config('business_number', $_POST['business_number'], 'text', 'footer');
    }

    // 통신판매업 신고번호
    if (isset($_POST['ecommerce_number'])) {
        addon_app_admin_set_config('ecommerce_number', $_POST['ecommerce_number'], 'text', 'footer');
    }

    // 주소
    if (isset($_POST['address'])) {
        addon_app_admin_set_config('address', $_POST['address'], 'text', 'footer');
    }

    // 전화번호
    if (isset($_POST['phone'])) {
        addon_app_admin_set_config('phone', $_POST['phone'], 'text', 'footer');
    }

    // 팩스
    if (isset($_POST['fax'])) {
        addon_app_admin_set_config('fax', $_POST['fax'], 'text', 'footer');
    }

    // 이메일
    if (isset($_POST['email'])) {
        addon_app_admin_set_config('email', $_POST['email'], 'text', 'footer');
    }

    // 개인정보관리책임자
    if (isset($_POST['privacy_officer'])) {
        addon_app_admin_set_config('privacy_officer', $_POST['privacy_officer'], 'text', 'footer');
    }

    // 저작권 문구
    if (isset($_POST['copyright'])) {
        addon_app_admin_set_config('copyright', $_POST['copyright'], 'text', 'footer');
    }

    // 푸터 배경색
    if (isset($_POST['footer_bg_color'])) {
        addon_app_admin_set_config('footer_bg_color', $_POST['footer_bg_color'], 'color', 'footer');
    }

    // 푸터 텍스트 색상
    if (isset($_POST['footer_text_color'])) {
        addon_app_admin_set_config('footer_text_color', $_POST['footer_text_color'], 'color', 'footer');
    }

    addon_app_admin_log('company_update', 'company_info', '회사 정보 업데이트');
    alert('회사 정보가 저장되었습니다.', './footer.php');
}

// 레이아웃 헤더
$g5['title'] = '사이트 푸터 관리';
include_once('./layout_header.php');

// 현재 설정 가져오기 (데이터베이스에서 실제 값 조회)
try {
    // addon_app_admin_config 테이블에서 푸터 관련 설정 조회
    $config_sql = " SELECT config_key, config_value FROM {$addon_app_admin_table_site_config} WHERE category = 'footer' ";
    $config_result = sql_query($config_sql);

    $footer_config = array();
    while ($row = sql_fetch_array($config_result)) {
        $footer_config[$row['config_key']] = $row['config_value'];
    }

    error_log('[FOOTER] DB에서 가져온 푸터 설정: ' . print_r($footer_config, true));
} catch (Exception $e) {
    error_log('[FOOTER] 설정 조회 오류: ' . $e->getMessage());
    $footer_config = array();
}

// 설정 값 할당 (DB에서 가져온 값 사용, 없으면 기본값)
$company_name = isset($footer_config['company_name']) && !empty($footer_config['company_name'])
    ? $footer_config['company_name']
    : 'HAIR WANG';

$ceo_name = isset($footer_config['ceo_name']) && !empty($footer_config['ceo_name'])
    ? $footer_config['ceo_name']
    : '엄미현';

$business_number = isset($footer_config['business_number']) && !empty($footer_config['business_number'])
    ? $footer_config['business_number']
    : '476-16-02322';

$ecommerce_number = isset($footer_config['ecommerce_number']) && !empty($footer_config['ecommerce_number'])
    ? $footer_config['ecommerce_number']
    : '';

$address = isset($footer_config['address']) && !empty($footer_config['address'])
    ? $footer_config['address']
    : '';

$phone = isset($footer_config['phone']) && !empty($footer_config['phone'])
    ? $footer_config['phone']
    : '';

$fax = isset($footer_config['fax']) && !empty($footer_config['fax'])
    ? $footer_config['fax']
    : '';

$email = isset($footer_config['email']) && !empty($footer_config['email'])
    ? $footer_config['email']
    : '';

$privacy_officer = isset($footer_config['privacy_officer']) && !empty($footer_config['privacy_officer'])
    ? $footer_config['privacy_officer']
    : '';

$copyright = isset($footer_config['copyright']) && !empty($footer_config['copyright'])
    ? $footer_config['copyright']
    : 'ⓒ Hariwang All Rights Reserved.';

$footer_bg_color = isset($footer_config['footer_bg_color']) && !empty($footer_config['footer_bg_color'])
    ? $footer_config['footer_bg_color']
    : '#2c2c2c';

$footer_text_color = isset($footer_config['footer_text_color']) && !empty($footer_config['footer_text_color'])
    ? $footer_config['footer_text_color']
    : '#ffffff';

// 푸터 항목 가져오기
$footer_items = array();
try {
    $footer_sql = " SELECT * FROM {$addon_app_admin_table_footer} ORDER BY sort_order ASC, footer_key ASC ";
    $footer_result = sql_query($footer_sql);

    error_log('[FOOTER] 푸터 항목 쿼리: ' . $footer_sql);

    if ($footer_result) {
        $footer_count = sql_num_rows($footer_result);
        error_log('[FOOTER] 조회된 푸터 항목 수: ' . $footer_count);

        while ($row = sql_fetch_array($footer_result)) {
            $footer_items[] = $row;
        }
    }

    // 푸터 항목이 없으면 기본 항목 자동 추가
    if (count($footer_items) == 0) {
        error_log('[FOOTER] 푸터 항목이 없어 기본 항목 추가 시작');

        $default_items = array(
            array(
                'footer_key' => 'service_terms',
                'title' => '서비스 이용약관',
                'content' => '',
                'link_url' => '/bbs/content.php?co_id=provision',
                'is_active' => 1,
                'sort_order' => 1
            ),
            array(
                'footer_key' => 'privacy_policy',
                'title' => '개인정보 처리방침',
                'content' => '',
                'link_url' => '/bbs/content.php?co_id=privacy',
                'is_active' => 1,
                'sort_order' => 2
            ),
            array(
                'footer_key' => 'notice',
                'title' => '공지사항',
                'content' => '',
                'link_url' => '/bbs/board.php?bo_table=notice',
                'is_active' => 1,
                'sort_order' => 3
            ),
            array(
                'footer_key' => 'qna',
                'title' => '1:1 문의',
                'content' => '',
                'link_url' => '/bbs/qalist.php',
                'is_active' => 1,
                'sort_order' => 4
            ),
            array(
                'footer_key' => 'faq',
                'title' => 'FAQ',
                'content' => '',
                'link_url' => '/bbs/faq.php',
                'is_active' => 1,
                'sort_order' => 5
            ),
            array(
                'footer_key' => 'new_post',
                'title' => '새글',
                'content' => '',
                'link_url' => '/bbs/new.php',
                'is_active' => 1,
                'sort_order' => 6
            )
        );

        foreach ($default_items as $item) {
            $insert_sql = " INSERT INTO {$addon_app_admin_table_footer}
                           (footer_key, title, content, link_url, is_active, sort_order)
                           VALUES (
                               '{$item['footer_key']}',
                               '{$item['title']}',
                               '{$item['content']}',
                               '{$item['link_url']}',
                               {$item['is_active']},
                               {$item['sort_order']}
                           ) ";

            if (sql_query($insert_sql)) {
                $footer_items[] = $item;
                error_log('[FOOTER] 기본 항목 추가 성공: ' . $item['title']);
            }
        }
    }
} catch (Exception $e) {
    error_log('[FOOTER] 푸터 항목 조회 오류: ' . $e->getMessage());
}

// 페이지 디버그 정보
$GLOBALS['page_debug'] = array(
    'page' => '사이트 푸터 관리',
    'sql_query' => isset($footer_sql) ? $footer_sql : 'NOT SET',
    'company_info' => array(
        'company_name' => $company_name,
        'ceo_name' => $ceo_name,
        'business_number' => $business_number,
        'address' => $address,
        'phone' => $phone,
        'email' => $email,
        'copyright' => $copyright
    ),
    'footer_items_count' => count($footer_items),
    'footer_items' => $footer_items,
    'colors' => array(
        'bg_color' => $footer_bg_color,
        'text_color' => $footer_text_color
    ),
    'db_config_count' => isset($footer_config) ? count($footer_config) : 0,
    'db_config_keys' => isset($footer_config) ? array_keys($footer_config) : array()
);

?>

<div class="admin-content-header">
    <h1 class="admin-content-title">사이트 푸터 관리</h1>
    <p class="admin-content-desc">
        <i class="fa fa-info-circle"></i>
        사이트 푸터의 회사 정보, 링크, 색상 등을 관리합니다.
    </p>
</div>

<!-- 회사 정보 -->
<div style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); margin-bottom: 30px;">
            <h2 class="addon-section-title">
                <i class="fa fa-building"></i> 회사 정보
            </h2>

            <form method="post" action="">
                <?php echo get_admin_token_field(); ?>
                <input type="hidden" name="action" value="save_company">

                <div class="addon-form-row">
                    <div class="addon-form-group">
                        <label class="addon-form-label">회사명</label>
                        <input type="text" name="company_name" class="addon-form-input" value="<?php echo htmlspecialchars($company_name); ?>" placeholder="Hair Wang">
                    </div>

                    <div class="addon-form-group">
                        <label class="addon-form-label">대표자명</label>
                        <input type="text" name="ceo_name" class="addon-form-input" value="<?php echo htmlspecialchars($ceo_name); ?>" placeholder="홍길동">
                    </div>
                </div>

                <div class="addon-form-row">
                    <div class="addon-form-group">
                        <label class="addon-form-label">사업자등록번호</label>
                        <input type="text" name="business_number" class="addon-form-input" value="<?php echo htmlspecialchars($business_number); ?>" placeholder="123-45-67890">
                    </div>

                    <div class="addon-form-group">
                        <label class="addon-form-label">통신판매업 신고번호</label>
                        <input type="text" name="ecommerce_number" class="addon-form-input" value="<?php echo htmlspecialchars($ecommerce_number); ?>" placeholder="제2026-서울강남-12345호">
                    </div>
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">주소</label>
                    <input type="text" name="address" class="addon-form-input" value="<?php echo htmlspecialchars($address); ?>" placeholder="서울특별시 강남구 테헤란로 123">
                </div>

                <div class="addon-form-row">
                    <div class="addon-form-group">
                        <label class="addon-form-label">전화번호</label>
                        <input type="text" name="phone" class="addon-form-input" value="<?php echo htmlspecialchars($phone); ?>" placeholder="02-1234-5678">
                    </div>

                    <div class="addon-form-group">
                        <label class="addon-form-label">팩스</label>
                        <input type="text" name="fax" class="addon-form-input" value="<?php echo htmlspecialchars($fax); ?>" placeholder="02-1234-5679">
                    </div>

                    <div class="addon-form-group">
                        <label class="addon-form-label">이메일</label>
                        <input type="email" name="email" class="addon-form-input" value="<?php echo htmlspecialchars($email); ?>" placeholder="info@hairwang.com">
                    </div>
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">개인정보관리책임자</label>
                    <input type="text" name="privacy_officer" class="addon-form-input" value="<?php echo htmlspecialchars($privacy_officer); ?>" placeholder="홍길동 (privacy@hairwang.com)">
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">저작권 문구</label>
                    <input type="text" name="copyright" class="addon-form-input" value="<?php echo htmlspecialchars($copyright); ?>" placeholder="© 2026 Hair Wang. All rights reserved.">
                </div>

                <div class="addon-form-row">
                    <div class="addon-form-group">
                        <label class="addon-form-label">푸터 배경색</label>
                        <input type="color" name="footer_bg_color" class="addon-form-input" value="<?php echo htmlspecialchars($footer_bg_color); ?>" style="height: 60px;">
                        <small class="addon-form-help">푸터 영역의 배경 색상</small>
                    </div>

                    <div class="addon-form-group">
                        <label class="addon-form-label">푸터 텍스트 색상</label>
                        <input type="color" name="footer_text_color" class="addon-form-input" value="<?php echo htmlspecialchars($footer_text_color); ?>" style="height: 60px;">
                        <small class="addon-form-help">푸터 텍스트 및 링크 색상</small>
                    </div>
                </div>

                <button type="submit" class="addon-btn addon-btn-primary">
                    <i class="fa fa-save"></i> 회사 정보 저장
                </button>
            </form>
        </div>

        <!-- 푸터 링크 관리 -->
        <div class="addon-section addon-mt-30">
            <h2 class="addon-section-title">
                <i class="fa fa-link"></i> 푸터 링크 관리
            </h2>

            <div class="footer-items-grid">
                <?php foreach ($footer_items as $item): ?>
                <div class="footer-item-card">
                    <div class="footer-item-header">
                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                        <div class="footer-item-actions">
                            <button type="button" class="addon-btn addon-btn-sm addon-btn-primary" onclick="editFooterItem('<?php echo $item['footer_key']; ?>')">
                                <i class="fa fa-edit"></i> 수정
                            </button>
                            <a href="?action=delete&footer_key=<?php echo $item['footer_key']; ?>&token=<?php echo get_admin_token(); ?>" class="addon-btn addon-btn-sm addon-btn-danger" onclick="return confirm('정말 삭제하시겠습니까?');">
                                <i class="fa fa-trash"></i> 삭제
                            </a>
                        </div>
                    </div>
                    <div class="footer-item-content">
                        <?php if ($item['content']): ?>
                            <p><?php echo nl2br(htmlspecialchars($item['content'])); ?></p>
                        <?php endif; ?>
                        <?php if ($item['link_url']): ?>
                            <p><i class="fa fa-external-link"></i> <a href="<?php echo htmlspecialchars($item['link_url']); ?>" target="_blank"><?php echo htmlspecialchars($item['link_url']); ?></a></p>
                        <?php endif; ?>
                        <p style="margin-top: 10px;">
                            <span class="badge <?php echo $item['is_active'] ? 'badge-success' : 'badge-secondary'; ?>">
                                <?php echo $item['is_active'] ? '활성' : '비활성'; ?>
                            </span>
                            <span class="badge badge-info">정렬순서: <?php echo $item['sort_order']; ?></span>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>

                <!-- 새 항목 추가 카드 -->
                <div class="footer-item-card footer-item-add" onclick="addFooterItem()">
                    <i class="fa fa-plus-circle" style="font-size: 48px; color: var(--primary-color); margin-bottom: 15px;"></i>
                    <h3>새 푸터 항목 추가</h3>
                </div>
            </div>
        </div>

        <!-- 미리보기 -->
        <div class="addon-section addon-mt-30">
            <h2 class="addon-section-title">
                <i class="fa fa-eye"></i> 미리보기
            </h2>
            <div class="footer-preview" style="background-color: <?php echo htmlspecialchars($footer_bg_color); ?>; color: <?php echo htmlspecialchars($footer_text_color); ?>;">
                <div class="footer-preview-content">
                    <div class="footer-preview-info">
                        <h3><?php echo htmlspecialchars($company_name); ?></h3>
                        <?php if ($ceo_name): ?>
                            <p>대표: <?php echo htmlspecialchars($ceo_name); ?></p>
                        <?php endif; ?>
                        <?php if ($business_number): ?>
                            <p>사업자등록번호: <?php echo htmlspecialchars($business_number); ?></p>
                        <?php endif; ?>
                        <?php if ($ecommerce_number): ?>
                            <p>통신판매업: <?php echo htmlspecialchars($ecommerce_number); ?></p>
                        <?php endif; ?>
                        <?php if ($address): ?>
                            <p><i class="fa fa-map-marker"></i> <?php echo htmlspecialchars($address); ?></p>
                        <?php endif; ?>
                        <?php if ($phone): ?>
                            <p><i class="fa fa-phone"></i> <?php echo htmlspecialchars($phone); ?></p>
                        <?php endif; ?>
                        <?php if ($email): ?>
                            <p><i class="fa fa-envelope"></i> <?php echo htmlspecialchars($email); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="footer-preview-links">
                        <?php foreach ($footer_items as $item): ?>
                            <?php if ($item['is_active']): ?>
                                <div class="footer-preview-link">
                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                    <?php if ($item['link_url']): ?>
                                        <a href="<?php echo htmlspecialchars($item['link_url']); ?>"><?php echo htmlspecialchars($item['link_url']); ?></a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="footer-preview-copyright">
                    <?php echo htmlspecialchars($copyright); ?>
                </div>
            </div>
        </div>

    </div>

    <div class="addon-footer">
        <div class="addon-container">
            <div class="addon-footer-content">
                <p>&copy; 2026 Hair Wang App Admin. All rights reserved.</p>
            </div>
        </div>
    </div>

</div>

<!-- 푸터 항목 수정/추가 모달 -->
<div id="footerModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">푸터 항목 편집</h2>
            <span class="modal-close" onclick="closeFooterModal()">&times;</span>
        </div>
        <form method="post" action="" id="footerForm">
            <?php echo get_admin_token_field(); ?>
            <input type="hidden" name="action" value="save_footer">
            <input type="hidden" name="footer_key" id="footer_key">

            <div class="addon-form-group">
                <label class="addon-form-label">항목 제목 *</label>
                <input type="text" name="title" id="footer_title" class="addon-form-input" required placeholder="이용약관">
            </div>

            <div class="addon-form-group">
                <label class="addon-form-label">내용</label>
                <textarea name="content" id="footer_content" class="addon-form-input" rows="4" placeholder="항목에 대한 설명 (선택사항)"></textarea>
            </div>

            <div class="addon-form-group">
                <label class="addon-form-label">링크 URL</label>
                <input type="url" name="link_url" id="footer_link_url" class="addon-form-input" placeholder="https://example.com/terms">
                <small class="addon-form-help">클릭 시 이동할 URL (선택사항)</small>
            </div>

            <div class="addon-form-row">
                <div class="addon-form-group">
                    <label class="addon-form-label">정렬 순서</label>
                    <input type="number" name="sort_order" id="footer_sort_order" class="addon-form-input" value="0" min="0">
                </div>

                <div class="addon-form-group">
                    <label class="addon-form-label">
                        <input type="checkbox" name="is_active" id="footer_is_active" value="1" checked>
                        활성화
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="addon-btn addon-btn-secondary" onclick="closeFooterModal()">취소</button>
                <button type="submit" class="addon-btn addon-btn-primary">
                    <i class="fa fa-save"></i> 저장
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.footer-items-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.footer-item-card {
    background: white;
    border-radius: var(--border-radius);
    padding: 20px;
    box-shadow: var(--shadow-sm);
    transition: all 0.3s ease;
}

.footer-item-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.footer-item-add {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 200px;
    cursor: pointer;
    border: 2px dashed #ddd;
    background: #f9f9f9;
}

.footer-item-add:hover {
    border-color: var(--primary-color);
    background: #f0f0f0;
}

.footer-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.footer-item-header h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.footer-item-actions {
    display: flex;
    gap: 8px;
}

.footer-item-content p {
    margin: 8px 0;
    font-size: 14px;
    color: #666;
    line-height: 1.6;
}

.footer-preview {
    border-radius: var(--border-radius);
    padding: 30px;
    box-shadow: var(--shadow-lg);
}

.footer-preview-content {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-bottom: 30px;
}

.footer-preview-info h3 {
    margin: 0 0 15px 0;
    font-size: 20px;
}

.footer-preview-info p {
    margin: 8px 0;
    font-size: 14px;
    line-height: 1.6;
}

.footer-preview-links {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.footer-preview-link strong {
    display: block;
    margin-bottom: 5px;
}

.footer-preview-link a {
    color: inherit;
    text-decoration: underline;
    font-size: 14px;
}

.footer-preview-copyright {
    text-align: center;
    padding-top: 20px;
    border-top: 1px solid rgba(255,255,255,0.2);
    font-size: 14px;
}

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 0;
    border-radius: var(--border-radius);
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    border-bottom: 1px solid #eee;
}

.modal-header h2 {
    margin: 0;
    font-size: 20px;
}

.modal-close {
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    transition: color 0.3s;
}

.modal-close:hover {
    color: #000;
}

.modal-content form {
    padding: 30px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    margin-right: 8px;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-secondary {
    background-color: #6c757d;
    color: white;
}

.badge-info {
    background-color: #17a2b8;
    color: white;
}

@media (max-width: 768px) {
    .footer-items-grid {
        grid-template-columns: 1fr;
    }

    .footer-preview-content {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// 푸터 항목 수정
function editFooterItem(footerKey) {
    <?php foreach ($footer_items as $item): ?>
    if (footerKey === '<?php echo $item['footer_key']; ?>') {
        document.getElementById('modalTitle').textContent = '푸터 항목 수정';
        document.getElementById('footer_key').value = '<?php echo $item['footer_key']; ?>';
        document.getElementById('footer_title').value = '<?php echo addslashes($item['title']); ?>';
        document.getElementById('footer_content').value = '<?php echo addslashes($item['content']); ?>';
        document.getElementById('footer_link_url').value = '<?php echo addslashes($item['link_url']); ?>';
        document.getElementById('footer_sort_order').value = '<?php echo $item['sort_order']; ?>';
        document.getElementById('footer_is_active').checked = <?php echo $item['is_active'] ? 'true' : 'false'; ?>;
        document.getElementById('footerModal').style.display = 'block';
    }
    <?php endforeach; ?>
}

// 새 푸터 항목 추가
function addFooterItem() {
    // 새 키 생성
    const newKey = 'footer_' + Date.now();

    document.getElementById('modalTitle').textContent = '새 푸터 항목 추가';
    document.getElementById('footer_key').value = newKey;
    document.getElementById('footer_title').value = '';
    document.getElementById('footer_content').value = '';
    document.getElementById('footer_link_url').value = '';
    document.getElementById('footer_sort_order').value = '0';
    document.getElementById('footer_is_active').checked = true;
    document.getElementById('footerModal').style.display = 'block';
}

// 모달 닫기
function closeFooterModal() {
    document.getElementById('footerModal').style.display = 'none';
}

// 모달 외부 클릭 시 닫기
window.onclick = function(event) {
    const modal = document.getElementById('footerModal');
    if (event.target === modal) {
        closeFooterModal();
    }
}
</script>

<?php
include_once('./layout_footer.php');
?>
