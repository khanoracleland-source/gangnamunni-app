<?php
/**
 * 필드 정의 수정 페이지
 * API 필드 정의 수정 기능 제공
 * 작성일: 2026-01-19
 * 버전: 1.0
 */

// API 관리자 공통 파일 포함
include_once './_common.php';

// 필드 ID
$af_id = isset($_GET['af_id']) ? (int)$_GET['af_id'] : 0;

if ($af_id === 0) {
    alert('필드 ID가 유효하지 않습니다.', './fieldlist.php');
}

// 필드 정보 조회
$field = sql_fetch("SELECT * FROM {$g5['api_fields_table']} WHERE af_id = '" . sql_real_escape_string($af_id) . "'");

if (!$field) {
    alert('필드 정의를 찾을 수 없습니다.', './fieldlist.php');
}

include_once G5_ADMIN_PATH . '/admin.head.php';
?>

<div class="local_ov01 local_ov">
    <a href="./fieldlist.php" class="ov_list">필드 정의 목록</a>
</div>

<form name="ffield" id="ffield" method="post" action="./fieldlist.php">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="af_id" value="<?php echo $af_id; ?>">
    
    <div class="tbl_frm01 tbl_wrap">
        <table>
            <caption>필드 정의 수정</caption>
            <colgroup>
                <col class="grid_4">
                <col>
            </colgroup>
            <tbody>
                <tr>
                    <th scope="row"><label for="name">필드 이름</label></th>
                    <td>
                        <input type="text" name="name" id="name" class="frm_input required" size="50" value="<?php echo htmlspecialchars($field['af_name']); ?>" required>
                        <span class="frm_info">필드 표시 이름</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="code">필드 코드</label></th>
                    <td>
                        <input type="text" name="code" id="code" class="frm_input required" size="50" value="<?php echo htmlspecialchars($field['af_code']); ?>" required>
                        <span class="frm_info">유일한 필드 코드 (영문, 숫자, 언더스코어만 허용)</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="type">필드 타입</label></th>
                    <td>
                        <select name="type" id="type" class="frm_input">
                            <option value="text" <?php echo $field['af_type'] === 'text' ? 'selected' : ''; ?>>Text (텍스트)</option>
                            <option value="number" <?php echo $field['af_type'] === 'number' ? 'selected' : ''; ?>>Number (숫자)</option>
                            <option value="select" <?php echo $field['af_type'] === 'select' ? 'selected' : ''; ?>>Select (선택)</option>
                            <option value="checkbox" <?php echo $field['af_type'] === 'checkbox' ? 'selected' : ''; ?>>Checkbox (체크박스)</option>
                            <option value="textarea" <?php echo $field['af_type'] === 'textarea' ? 'selected' : ''; ?>>Textarea (여러 줄 텍스트)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="category">카테고리</label></th>
                    <td>
                        <select name="category" id="category" class="frm_input">
                            <option value="sns" <?php echo $field['af_category'] === 'sns' ? 'selected' : ''; ?>>SNS 로그인</option>
                            <option value="pg" <?php echo $field['af_category'] === 'pg' ? 'selected' : ''; ?>>PG 결제</option>
                            <option value="common" <?php echo $field['af_category'] === 'common' ? 'selected' : ''; ?>>공통</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="default_value">기본값</label></th>
                    <td>
                        <input type="text" name="default_value" id="default_value" class="frm_input" size="50" value="<?php echo htmlspecialchars($field['af_default_value']); ?>">
                        <span class="frm_info">필드 기본값</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="options">옵션</label></th>
                    <td>
                        <input type="text" name="options" id="options" class="frm_input" size="50" value="<?php echo htmlspecialchars($field['af_options']); ?>">
                        <span class="frm_info">Select, Checkbox 타입일 경우 JSON 형식의 옵션 (예: {"option1":"값1","option2":"값2"})</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="required">필수 여부</label></th>
                    <td>
                        <input type="checkbox" name="required" id="required" value="1" <?php echo $field['af_required'] ? 'checked' : ''; ?>>
                        <span class="frm_info">필수 필드인 경우 체크</span>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="status">상태</label></th>
                    <td>
                        <select name="status" id="status" class="frm_input">
                            <option value="1" <?php echo $field['af_status'] == 1 ? 'selected' : ''; ?>>활성</option>
                            <option value="0" <?php echo $field['af_status'] == 0 ? 'selected' : ''; ?>>비활성</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="order">정렬 순서</label></th>
                    <td>
                        <input type="number" name="order" id="order" class="frm_input" size="10" value="<?php echo $field['af_order']; ?>">
                        <span class="frm_info">작을수록 먼저 표시</span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="btn_fixed_top">
        <a href="./fieldlist.php" class="btn btn_02">목록</a>
        <input type="submit" value="필드 정의 수정" class="btn_submit btn">
    </div>
</form>

<?php
include_once G5_ADMIN_PATH . '/admin.tail.php';
?>
