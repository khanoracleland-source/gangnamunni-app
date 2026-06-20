<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

/**
 * REBUILDER 신고 시스템
 * /extend/rb.report.extend.php
 * 
 * @author REBUILDER
 * @version 5.0.0
 */

// boardreport.lib.php 로드
if (file_exists(G5_ADMIN_PATH.'/boardreport.lib.php')) {
    include_once(G5_ADMIN_PATH.'/boardreport.lib.php');
}

// 관리자 메뉴 추가 (menu000에 통합)
add_replace('admin_menu', 'add_admin_bbs_menu_report', 0, 1);

function add_admin_bbs_menu_report($admin_menu) { // 메뉴추가
    
    // 신고 관리 메뉴들을 menu000에 추가
    $admin_menu['menu000'][] = array('000900', '신고 관리', G5_ADMIN_URL . '/boardreport_list.php', 'rb_config');
    $admin_menu['menu000'][] = array('000910', '신고 설정', G5_ADMIN_URL . '/boardreport_config.php', 'rb_config');
    
    // 구분선 추가
    $admin_menu['menu000'][] = array('000000', '　', G5_ADMIN_URL, 'rb_config');
    
    return $admin_menu;
}

// 관리자 페이지에서는 메뉴만 추가하고 종료
if (defined('G5_IS_ADMIN')) {
    return;
}

// ========== 이하 프론트엔드 코드 ==========

// write 테이블 wr_report 필드 자동 추가
if (isset($bo_table) && $bo_table) {
    $write_table = G5_TABLE_PREFIX . 'write_' . $bo_table;
    $table_exists = sql_fetch("SHOW TABLES LIKE '{$write_table}'");
    
    if ($table_exists) {
        $field_exists = sql_fetch("SHOW COLUMNS FROM `{$write_table}` LIKE 'wr_report'");
        if (!$field_exists) {
            sql_query("ALTER TABLE `{$write_table}` ADD `wr_report` VARCHAR(10) DEFAULT NULL AFTER `wr_10`", false);
        }
    }
}

// 신고 시스템 CSS/JS 자동 삽입
add_event('head_sub_after', function() {
    global $bo_table, $board, $is_member;
    
    // 게시판 페이지가 아니면 패스
    if (!$bo_table || !$board) return;
    
    // 신고 설정 확인
    if (function_exists('g5_report_conf')) {
        $conf = g5_report_conf();
        if (!$conf || !$conf['enabled']) return;
    }
    
    ?>
    <style>
    /* 리빌더 신고 시스템 */
    .rb-report-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 6px 12px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        color: #6c757d;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .rb-report-btn:hover {
        background: #dc3545;
        border-color: #dc3545;
        color: white;
    }
    .report-count-badge {
        display: inline-block;
        min-width: 20px;
        padding: 2px 6px;
        background: #dc3545;
        color: white;
        border-radius: 10px;
        font-size: 11px;
        font-weight: bold;
        text-align: center;
        margin-left: 4px;
    }
    .report-admin-link {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
        color: #856404;
        font-size: 12px;
        text-decoration: none;
        margin-left: 8px;
    }
    .report-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 99999;
        display: none;
    }
    .report-modal-bg {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
    }
    .report-modal-box {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: white;
        border-radius: 8px;
        width: 90%;
        max-width: 500px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }
    .report-modal-inner {
        padding: 20px;
    }
    .report-hidden-note,
    .cmt-report-hidden-note {
        background: #fff3cd;
        border: 1px solid #ffc107;
        padding: 15px;
        border-radius: 4px;
        text-align: center;
        color: #856404;
        margin: 20px 0;
    }
    @media (max-width: 768px) {
        .rb-report-btn {
            padding: 4px 8px;
            font-size: 12px;
        }
        .report-modal-box {
            width: 95%;
            max-width: none;
        }
    }
    </style>
    
    <script>
    const G5_BBS_URL = "<?php echo G5_BBS_URL; ?>";
    const g5_is_member = <?php echo $is_member ? 'true' : 'false'; ?>;
    
    function handleReportClick(bo_table, wr_id, comment_id) {
        comment_id = comment_id || 0;
        
        if (!g5_is_member) {
            if (confirm("회원만 신고할 수 있습니다.\n로그인 하시겠습니까?")) {
                location.href = G5_BBS_URL + "/login.php?url=" + encodeURIComponent(location.href);
            }
            return;
        }
        
        openReportForm(bo_table, wr_id, comment_id);
    }
    
    function openReportForm(bo_table, wr_id, comment_id) {
        const url = G5_BBS_URL + "/report_form.php?bo_table=" + bo_table + "&wr_id=" + wr_id + "&comment_id=" + comment_id;
        
        if (!document.getElementById("report_modal")) {
            const modal = document.createElement("div");
            modal.id = "report_modal";
            modal.className = "report-modal";
            modal.innerHTML = `
                <div class="report-modal-bg" onclick="closeReportModal()"></div>
                <div class="report-modal-box">
                    <div id="report_modal_inner" class="report-modal-inner">로딩 중...</div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        const modal = document.getElementById("report_modal");
        const inner = document.getElementById("report_modal_inner");
        
        modal.style.display = "block";
        document.body.style.overflow = "hidden";
        
        fetch(url)
            .then(res => res.text())
            .then(html => {
                inner.innerHTML = html;
            })
            .catch(() => {
                inner.innerHTML = "<p style='color:red;'>신고 폼 로딩 중 오류가 발생했습니다.</p>";
            });
    }
    
    function closeReportModal() {
        const modal = document.getElementById("report_modal");
        if (modal) {
            modal.style.display = "none";
            document.body.style.overflow = "";
        }
    }
    
    document.addEventListener("submit", function(e) {
        if (e.target && e.target.id === "report_form") {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            formData.append("ajax", "1");
            
            fetch(G5_BBS_URL + "/report_update.php", {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.ok) {
                    alert(data.message || "신고가 접수되었습니다.");
                    closeReportModal();
                    if (data.locked) {
                        location.reload();
                    }
                } else {
                    alert(data.message || "신고 처리 중 오류가 발생했습니다.");
                }
            })
            .catch(() => {
                alert("신고 요청에 실패했습니다.");
            });
        }
    });
    </script>
    <?php
}, 999);

// 게시글 보기 페이지 신고 버튼 추가
add_event('view_skin_main_after', function() {
    global $bo_table, $view, $is_admin, $g5;
    
    if (!isset($view['wr_id'])) return;
    
    // 신고 설정 확인
    if (!function_exists('g5_report_conf')) return;
    $conf = g5_report_conf();
    if (!$conf || !$conf['enabled']) return;
    
    $wr_id = $view['wr_id'];
    $report_table = $g5['board_table'] . '_report';
    
    // 신고 횟수 조회
    $report_count = 0;
    $sql = "SELECT COUNT(*) as cnt FROM `{$report_table}` 
            WHERE bo_table = '{$bo_table}' AND wr_id = '{$wr_id}' AND comment_id = 0";
    $row = sql_fetch($sql);
    if ($row) $report_count = $row['cnt'];
    
    // 잠금 상태 확인
    $is_locked = (isset($view['wr_report']) && $view['wr_report'] === '잠금');
    
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        <?php if (!$is_locked): ?>
        const reportBtn = `<button type="button" class="rb-report-btn" onclick="handleReportClick('<?php echo $bo_table; ?>', '<?php echo $wr_id; ?>', 0)">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
            </svg>
            신고
        </button>`;
        <?php endif; ?>
        
        <?php if ($is_admin && $report_count > 0): ?>
        const adminLink = `<a href="<?php echo G5_ADMIN_URL; ?>/boardreport_list.php?bo_table=<?php echo $bo_table; ?>&kw=<?php echo $wr_id; ?>" target="_blank" class="report-admin-link">
            신고내역 <span class="report-count-badge"><?php echo $report_count; ?></span>
        </a>`;
        <?php endif; ?>
        
        // 버튼 삽입
        const targetEls = document.querySelectorAll(".view_btns, .btns_gr, .bo_v_com, .view_is_list");
        if (targetEls.length > 0) {
            <?php if (!$is_locked): ?>
            targetEls[0].insertAdjacentHTML("beforeend", reportBtn);
            <?php endif; ?>
            <?php if ($is_admin && $report_count > 0): ?>
            targetEls[0].insertAdjacentHTML("beforeend", adminLink);
            <?php endif; ?>
        }
    });
    </script>
    <?php
}, 999);

// 댓글 신고 버튼 추가
add_event('view_comment_after', function() {
    global $bo_table, $view, $list;
    
    if (!$bo_table || empty($list)) return;
    
    if (!function_exists('g5_report_conf')) return;
    $conf = g5_report_conf();
    if (!$conf || !$conf['enabled']) return;
    
    $wr_id = isset($view['wr_id']) ? $view['wr_id'] : 0;
    if (!$wr_id) return;
    
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const commentActions = document.querySelectorAll(".bo_vc_act");
        commentActions.forEach(function(actMenu) {
            const article = actMenu.closest("article[id^='c_']");
            if (!article) return;
            
            const commentId = article.id.replace("c_", "");
            if (!commentId) return;
            
            if (!actMenu.querySelector(".report-btn")) {
                const reportLi = document.createElement("li");
                reportLi.innerHTML = `<a href="javascript:void(0);" class="report-btn" onclick="handleReportClick('<?php echo $bo_table; ?>', '<?php echo $wr_id; ?>', ${commentId})">신고</a>`;
                actMenu.appendChild(reportLi);
            }
        });
    });
    </script>
    <?php
}, 999);