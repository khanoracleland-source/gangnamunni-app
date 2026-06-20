<?php

if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

include_once(G5_ADMIN_PATH.'/_common.php');

global $sub_menu, $g5;

auth_check_menu($auth, $sub_menu, 'w');

?>
<form name="flocationform" id="flocationform" method="post" action="" onsubmit="return flocationform_submit(this);">

    <section id="anc_cf_basic">
        <div class="local_desc02 local_desc">
            <p>더미 회원 생성 기능입니다. 테스트용 회원 계정을 자동으로 생성합니다.</p>
            <p>생성된 회원의 이름은 한국식 이름으로, 닉네임은 헤어 관련 키워드로 자동 생성됩니다.</p>
        </div>

        <div class="tbl_frm01 tbl_wrap">
            <table>
                <caption>더미 회원 생성 설정</caption>
                <colgroup>
                    <col class="grid_4">
                </colgroup>
                <tbody>

                <tr>
                    <th scope="row"><label for="dm_type">회원 유형<strong class="sound_only">필수</strong></label></th>
                    <td>
                        <select name="dm_type" id="dm_type" required class="required frm_input">
                            <option value="normal">일반 회원</option>
                            <option value="student">학생 회원</option>
                            <option value="designer">헤어디자이너 회원</option>
                        </select>
                        <span class="frm_info">생성할 더미 회원의 유형을 선택하세요.</span>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="dm_count">생성할 회원 수<strong class="sound_only">필수</strong></label></th>
                    <td>
                        <input type="number" name="dm_count" min="1" max="50" value="10" id="dm_count" required class="required frm_input" size="80" oninput="if(this.value > 50) this.value=50;">
                        <span class="frm_info">한 번에 생성할 더미 회원 수 (최대 50명)</span>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="dm_userid">아이디 설정<strong class="sound_only">필수</strong></label></th>
                    <td>
                        <input type="checkbox" name="dm_userid" id="dm_userid" class="frm_input" size="80" checked>
                        <label for="dm_userid">자동 생성</label>
                        <br>
                        <input type="text" name="dm_userid_value" id="dm_userid_value" class="frm_input" size="30" placeholder="수동 입력 시 체크 해제" disabled>
                        <?php echo help('자동 생성: 영문+숫자 조합으로 랜덤 생성됩니다.'); ?>
                        <?php echo help('수동 입력: 입력한 아이디에 _1, _2, _3... 형식으로 순번이 추가됩니다.'); ?>
                        <?php echo help('예시) test 입력 시 → test_1, test_2, test_3...'); ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="dm_password">비밀번호<strong class="sound_only">필수</strong></label></th>
                    <td>
                        <input type="text" name="dm_password" id="dm_password" required class="required frm_input" size="30" value="1234">
                        <?php echo help('생성되는 모든 회원의 공통 비밀번호입니다.'); ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">생성 정보 안내</th>
                    <td>
                        <div style="padding:10px; background:#f5f5f5; border-radius:5px;">
                            <p style="margin:5px 0;"><strong>공통 정보:</strong></p>
                            <p style="margin:5px 0;">• <strong>이름</strong>: 한국식 성명으로 자동 생성 (예: 김민준, 이서연)</p>
                            <p style="margin:5px 0;">• <strong>닉네임</strong>: 헤어 관련 키워드 조합 (예: 레이어드헤어123, 펌전문가456)</p>
                            <p style="margin:5px 0;">• <strong>이메일</strong>: 아이디@도메인 형식 (naver.com, gmail.com 등)</p>
                            <p style="margin:5px 0;">• <strong>휴대폰/전화</strong>: 010-xxxx-xxxx / 02-xxx-xxxx 형식</p>
                            <p style="margin:5px 0;">• <strong>주소</strong>: 서울 강남구 테헤란로 기본 주소</p>
                            <p style="margin:5px 0;">• <strong>인증상태</strong>: 관리자 인증으로 설정</p>
                            
                            <div style="margin-top:15px; padding-top:15px; border-top:1px solid #ddd;">
                                <p style="margin:5px 0;"><strong>학생 회원 추가 정보:</strong></p>
                                <p style="margin:5px 0;">• <strong>학교명</strong>: 서울미용고등학교, 한국미용대학교 등 랜덤 생성</p>
                                <p style="margin:5px 0;">• <strong>학년</strong>: 1~4학년 중 랜덤 선택</p>
                                <p style="margin:5px 0;">• <strong>학생증</strong>: 더미 파일명 생성 (실제 파일 업로드 없음)</p>
                            </div>
                            
                            <div style="margin-top:15px; padding-top:15px; border-top:1px solid #ddd;">
                                <p style="margin:5px 0;"><strong>헤어디자이너 회원 추가 정보:</strong></p>
                                <p style="margin:5px 0;">• <strong>자격증 번호</strong>: 형식에 맞는 번호 자동 생성</p>
                                <p style="margin:5px 0;">• <strong>경력</strong>: 1년미만~10년이상 중 랜덤</p>
                                <p style="margin:5px 0;">• <strong>근무 매장</strong>: 유명 헤어샵 이름 랜덤 생성</p>
                                <p style="margin:5px 0;">• <strong>자격증</strong>: 더미 파일명 생성 (실제 파일 업로드 없음)</p>
                            </div>
                        </div>
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </section>
    <div style="text-align: center; margin-top: 20px;">
        <input type="submit" value="더미 회원 생성" class="btn_submit btn" accesskey="s" style="padding: 10px 30px; font-size: 14px;">
        <a href="<?php echo G5_ADMIN_URL; ?>/member_list.php" class="btn btn_02" style="padding: 10px 30px;">회원 목록</a>
    </div>

</form>

<script>
function flocationform_submit(f)
{
    if(!confirm('더미 회원을 생성하시겠습니까?')) {
        return false;
    }
    
    f.action = "<?php echo G5_PLUGIN_URL; ?>/dummy_member/dummy_member_update.php";
    return true;
}

function handleCheckboxChange(checkboxId, valueId) {
    $('#' + checkboxId).change(function() {
        $('#' + valueId).prop('disabled', $(this).is(':checked'));
        if($(this).is(':checked')) {
            $('#' + valueId).val('');
        }else{
            $('#' + valueId).focus();
        }
    });
}

$(document).ready(function() {
    handleCheckboxChange('dm_userid', 'dm_userid_value');
});
</script>