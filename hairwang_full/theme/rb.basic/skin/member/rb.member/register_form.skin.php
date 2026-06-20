<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// 추천인 처리를 가장 먼저 실행
$mb_recommend = '';

// 1. 쿠키에서 먼저 확인 (recommend.php를 통해 설정됨)
if (isset($_COOKIE['reg_mb_recommend']) && !empty($_COOKIE['reg_mb_recommend'])) {
    $mb_recommend = trim($_COOKIE['reg_mb_recommend']);
}
// 2. GET 파라미터 확인 (직접 링크)
elseif (isset($_GET['mb_recommend']) && !empty($_GET['mb_recommend'])) {
    $mb_recommend = trim($_GET['mb_recommend']);
    // 쿠키에도 저장
    set_cookie('reg_mb_recommend', $mb_recommend, 86400);
    // 세션에도 저장
    set_session('ss_mb_recommend', $mb_recommend);
}
// 3. POST 파라미터 확인 (폼 재전송시)
elseif (isset($_POST['mb_recommend']) && !empty($_POST['mb_recommend'])) {
    $mb_recommend = trim($_POST['mb_recommend']);
}
// 4. 세션에서 확인 (추가 백업)
elseif (get_session('ss_mb_recommend')) {
    $mb_recommend = get_session('ss_mb_recommend');
}

// SQL 인젝션 방지 - 아이디는 영문,숫자,_ 만 허용
if($mb_recommend) {
    $mb_recommend = preg_replace("/[^a-zA-Z0-9_]/", "", $mb_recommend);
    
    // 추천인이 실제 존재하는 회원인지 확인
    $sql = "SELECT mb_id FROM {$g5['member_table']} WHERE mb_id = '".sql_real_escape_string($mb_recommend)."'";
    $row = sql_fetch($sql);
    if(!$row['mb_id']) {
        $mb_recommend = ''; // 존재하지 않는 회원이면 초기화
    }
}

// 디버깅 출력 (필요시 주석 해제)
/*
echo "<script>
console.log('추천인 ID: " . $mb_recommend . "');
console.log('쿠키: " . $_COOKIE['reg_mb_recommend'] . "');
console.log('GET: " . $_GET['mb_recommend'] . "');
console.log('세션: " . get_session('ss_mb_recommend') . "');
</script>";
*/

if ($w == 'u') { 
    if(isset($pa['pa_is']) && $pa['pa_is'] == 1) {
        $re = isset($_GET['partner']) ? $_GET['partner'] : '';
        
        if($re == "re") { 
            if(isset($pa['pa_add_use']) && $pa['pa_add_use'] == 1) {
                $is_mb_partner = 2;
            } else { 
                $is_mb_partner = 1;
            }
        }
        
    }
}

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css">', 0);
add_javascript('<script src="'.G5_JS_URL.'/jquery.register_form.js"></script>', 0);
if ($config['cf_cert_use'] && ($config['cf_cert_simple'] || $config['cf_cert_ipin'] || $config['cf_cert_hp']))
    add_javascript('<script src="'.G5_JS_URL.'/certify.js?v='.G5_JS_VER.'"></script>', 0);

// 회원 유형 확인 (수정 모드에서)
$member_type = '';
if ($w == 'u' && isset($member['mb_1'])) {
    $member_type = $member['mb_1'];
} elseif ($w == '') {
    // 신규 가입시 URL 파라미터로 받은 회원유형
    $member_type = isset($_GET['mb_type']) ? $_GET['mb_type'] : (isset($_POST['mb_type']) ? $_POST['mb_type'] : 'general');
}
?>

<!-- 회원정보 입력/수정 시작 { -->

<style>
    /* 회원 유형 선택 스타일 추가 */
    .member_type_select {
        margin-bottom: 20px;
        text-align: center;
    }
    .member_type_select h3 {
        font-size: 16px;
        color: #333;
        margin-bottom: 15px;
        font-weight: 500;
    }
    .member_type_select label {
        display: inline-block;
        padding: 10px 20px;
        margin: 0 5px;
        border: 1px solid #ddd;
        border-radius: 5px;
        cursor: pointer;
        background-color: #fff;
        transition: all 0.3s;
    }
    .member_type_select input[type="radio"] {
        margin-right: 5px;
    }
    .member_type_select label:hover {
        background-color: #f9f9f9;
        border-color: #999;
    }
    .member_type_select label.selected {
        background-color: #f4f4f4;
        border-color: #333;
        font-weight: bold;
    }
    
    /* 회원 유형별 필드 숨김 처리 */
    .general_fields {
        display: none;
    }
    .hospital_fields {
        display: none;
    }
    .partner_fields {
        display: none;
    }
    
    /* 파일 업로드 커스텀 스타일 */
    .file_upload_wrap {
        position: relative;
        width: 100%;
        border: 1px solid #e5e5e5;
        border-radius: 8px;
        padding: 12px 15px;
        background-color: #fff;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: border-color 0.3s;
        min-height: 48px;
    }

    .file_upload_wrap:hover {
        border-color: #7a4efe;
    }

    .file_upload_wrap input[type="file"] {
        position: absolute;
        width: 0;
        height: 0;
        opacity: 0;
        overflow: hidden;
    }

    .file_upload_label {
        display: inline-flex;
        align-items: center;
        padding: 8px 20px;
        background-color: #7a4efe;
        color: white;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 14px;
        font-weight: 500;
        white-space: nowrap;
        flex-shrink: 0;
        height: 36px;
    }

    .file_upload_label:hover {
        background-color: #6a3ee8;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(122, 78, 254, 0.2);
    }

    .file_upload_label i {
        margin-right: 8px;
    }

    .file_name {
        flex: 1;
        color: #666;
        font-size: 14px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        line-height: 1;
        display: inline-block !important;
        margin-bottom: 0 !important;
        font-family: inherit !important;
    }

    .file_info {
        display: block;
        margin-top: 8px;
        font-size: 12px;
        color: #999;
        line-height: 1.5;
    }

    .uploaded_file_info {
        display: inline-block;
        margin-top: 10px;
        padding: 8px 15px;
        background-color: #f5f5f5;
        border-radius: 5px;
        font-size: 13px;
        color: #666;
    }

    .uploaded_file_info .file_icon {
        color: #7a4efe;
        margin-right: 5px;
    }

    .file_delete_wrap {
        display: inline-block;
        margin-left: 10px;
    }

    .file_delete_wrap input[type="checkbox"] {
        display: none;
    }

    .file_delete_label {
        display: inline-block;
        padding: 5px 12px;
        background-color: #ff5252;
        color: white;
        border-radius: 3px;
        cursor: pointer;
        font-size: 12px;
        transition: all 0.3s;
    }

    .file_delete_label:hover {
        background-color: #f44336;
    }

    .file_delete_wrap input[type="checkbox"]:checked + .file_delete_label {
        background-color: #666;
    }

    /* 아이콘 스타일 */
    .file_upload_label::before {
        content: "📎";
        margin-right: 8px;
    }
    
    /* 추천인 필드 스타일 */
    input.readonly {
        background-color: #f8f8f8;
        cursor: not-allowed;
    }
    
    .recommend_badge {
        display: inline-block;
        padding: 4px 12px;
        background: linear-gradient(135deg, #7a4efe 0%, #9f5fff 100%);
        color: white;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 8px;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 1; }
        50% { opacity: 0.8; }
        100% { opacity: 1; }
    }
    
    .recommend_info {
        background: #f0e6ff;
        padding: 15px;
        border-radius: 8px;
        margin-top: 10px;
    }
    
    .recommend_info h4 {
        color: #7a4efe;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .recommend_info ul {
        margin: 0;
        padding-left: 20px;
    }
    
    .recommend_info li {
        font-size: 13px;
        color: #666;
        margin-bottom: 4px;
    }
</style>

<div class="rb_member">
    <div class="rb_login rb_reg rb_join">
       
        <form id="fregisterform" name="fregisterform" action="<?php echo $register_action_url ?>" onsubmit="return fregisterform_submit(this);" method="post" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="w" value="<?php echo $w ?>">
        <input type="hidden" name="url" value="<?php echo $urlencode ?>">
        <input type="hidden" name="agree" value="<?php echo $agree ?>">
        <input type="hidden" name="agree2" value="<?php echo $agree2 ?>">
        <input type="hidden" name="cert_type" value="<?php echo $member['mb_certify']; ?>">
        <input type="hidden" name="cert_no" value="">
        <input type="hidden" name="re" value="<?php echo $re ?>">
        <!-- 회원 유형 hidden 필드 추가 -->
        <input type="hidden" name="mb_type" value="<?php echo $member_type; ?>">
        <!-- mb_1 필드 추가 (회원 유형 저장용) -->
        <input type="hidden" name="mb_1" value="<?php echo $member_type; ?>">
        
        <?php if (isset($member['mb_sex'])) {  ?><input type="hidden" name="mb_sex" value="<?php echo $member['mb_sex'] ?>"><?php }  ?>
        <?php if (isset($member['mb_nick_date']) && $member['mb_nick_date'] > date("Y-m-d", G5_SERVER_TIME - ($config['cf_nick_modify'] * 86400))) { // 닉네임수정일이 지나지 않았다면  ?>
        <input type="hidden" name="mb_nick_default" value="<?php echo get_text($member['mb_nick']) ?>">
        <input type="hidden" name="mb_nick" value="<?php echo get_text($member['mb_nick']) ?>">
        <?php }  ?>
        
        <?php if(isset($pa['pa_is']) && $pa['pa_is'] == 1 && isset($pa['pa_use']) && $pa['pa_use'] == 1) { ?>
            <?php if($w == "") { ?>
                <input type="hidden" name="mb_partner" value="<?php echo $_POST['mb_partner'] ?>">
            <?php } else { ?>
                <?php if ($re == "re") { ?>
                <input type="hidden" name="mb_partner" value="<?php echo $is_mb_partner ?>">
                <?php } else { ?>
                <input type="hidden" name="mb_partner" value="<?php echo isset($member['mb_partner']) ? get_text($member['mb_partner']) : ''; ?>">
                <?php } ?>
            <?php } ?>
        <?php } ?>
       
        <ul class="rb_login_box">
          
            <li class="rb_login_logo">
                <?php if (!empty($rb_builder['bu_logo_pc'])) { ?>
                    <a href="<?php echo G5_URL; ?>/gangnamunni/"><img src="<?php echo G5_URL; ?>/data/logos/pc?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>" id="logo_img"></a>
                <?php } else { ?>
                    <a href="<?php echo G5_URL; ?>/gangnamunni/"><img src="<?php echo G5_THEME_URL ?>/rb.img/logos/pc.png?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>" id="logo_img"></a>
                <?php } ?>
            </li>
            
            <?php if($w == '') { ?>
            <!-- 회원 유형별 안내 메시지 -->
            <li class="rb_reg_sub_title" id="type_message">
                <?php if($mb_recommend != '') { ?>
                <div class="recommend_badge">🎉 <?php echo $mb_recommend; ?>님의 추천으로 가입합니다</div>
                <?php } ?>
                <?php if($member_type == 'general') { ?>
                <span>일반 사용자로 가입합니다.</span>
                <?php } elseif($member_type == 'hospital') { ?>
                <span>병원관계자 회원으로 가입합니다.</span>
                <?php } elseif($member_type == 'partner') { ?>
                <span>
                    <?php if(isset($pa['pa_add_use']) && $pa['pa_add_use'] == 1) { ?>
                        입점사 회원으로 가입합니다.
                    <?php } else { ?>
                        입점사 회원으로 가입 신청합니다.<br>관리자 승인 이후 입점사 전용 서비스를 이용하실 수 있습니다.
                    <?php } ?>
                </span>
                <?php } ?>
            </li>
            <?php } else { ?>
            <!-- 수정 모드일 때 안내 메시지 -->
            <li class="rb_reg_sub_title">
                <?php
                $type_str = '회원정보를 수정합니다.';
                if($member_type) {
                    switch($member_type) {
                        case 'general': $type_str = '일반 사용자 정보를 수정합니다.'; break;
                        case 'hospital': $type_str = '병원관계자 회원 정보를 수정합니다.'; break;
                        case 'partner': $type_str = '입점사 회원 정보를 수정합니다.'; break;
                    }
                }
                echo $type_str;
                ?>
            </li>
            <?php } ?>
            
            <li>
                <span>아이디</span>
                <div class="input_wrap">
                    <input type="text" name="mb_id" value="<?php echo $member['mb_id'] ?>" id="reg_mb_id" <?php echo $required ?> <?php echo $readonly ?> class="input full_input <?php echo $required ?> <?php echo $readonly ?>" minlength="3" maxlength="20" placeholder="3글자 이상 (영문, 숫자, _ 입력가능)">
                    <button type="button" class="btn_frmline" onclick="checkDuplicate('id')">중복확인</button>
                </div>
                <span class="result_message main_color font-R" id="msg_mb_id"></span>
            </li>
            <li>
                <span>비밀번호</span>
                <input type="password" name="mb_password" id="reg_mb_password" <?php echo $required ?> class="input full_input <?php echo $required ?>" minlength="3" maxlength="20" placeholder="비밀번호">
                <input type="password" name="mb_password_re" id="reg_mb_password_re" <?php echo $required ?> class="input full_input mt-10 <?php echo $required ?>" minlength="3" maxlength="20" placeholder="비밀번호 확인">
                <span class="result_message main_color font-R" id="msg_mb_password_re"></span>
            </li>
            
            
            <?php if ($config['cf_cert_use']) { ?>
            <li>
                   <span>본인확인</span>
                    <?php 
					$desc_name = '';
					$desc_phone = '';
					if ($config['cf_cert_use']) {
                        $desc_name = '<span class="cert_desc"> 본인확인 시 자동입력</span>';
                        $desc_phone = '<span class="cert_desc"> 본인확인 시 자동입력</span>';
    
                        if (!$config['cf_cert_simple'] && !$config['cf_cert_hp'] && $config['cf_cert_ipin']) {
                            $desc_phone = '';
                        }

	                    if ($config['cf_cert_simple']) {
                            echo '<button type="button" id="win_sa_kakao_cert" class="btn_frmline win_sa_cert" data-type="">간편인증</button>'.PHP_EOL;
						}
						if ($config['cf_cert_hp'])
							echo '<button type="button" id="win_hp_cert" class="btn_frmline">휴대폰 본인확인</button>'.PHP_EOL;
						if ($config['cf_cert_ipin'])
							echo '<button type="button" id="win_ipin_cert" class="btn_frmline">아이핀 본인확인</button>'.PHP_EOL;
	
	                    //echo '<noscript>본인확인을 위해서는 자바스크립트 사용이 가능해야합니다.</noscript>'.PHP_EOL;
	                }
	                ?>
	                <?php
	                if ($config['cf_cert_use'] && $member['mb_certify']) {
						switch  ($member['mb_certify']) {
							case "simple": 
								$mb_cert = "간편인증";
								break;
							case "ipin": 
								$mb_cert = "아이핀";
								break;
							case "hp": 
								$mb_cert = "휴대폰";
								break;
						}                 
	                ?>
	                <div id="msg_certify">
	                    <strong><?php echo $mb_cert; ?> 본인확인</strong><?php if ($member['mb_adult']) { ?> 및 <strong>성인인증</strong><?php } ?> 완료
	                </div>
				<?php } ?>
            </li>
            <?php } ?>
            
            <li>
                <span>이름</span>
                <input type="text" id="reg_mb_name" name="mb_name" value="<?php echo get_text($member['mb_name']) ?>" <?php echo $required ?> <?php echo $readonly; ?> class="input full_input <?php echo $required ?> <?php echo $name_readonly ?>" placeholder="이름 (실명)">
            </li>
            
            <?php if ($req_nick) {  ?>
            <li>
                <span>닉네임</span>
                <input type="hidden" name="mb_nick_default" value="<?php echo isset($member['mb_nick'])?get_text($member['mb_nick']):''; ?>">
                <div class="input_wrap">
                    <input type="text" name="mb_nick" value="<?php echo isset($member['mb_nick'])?get_text($member['mb_nick']):''; ?>" id="reg_mb_nick" required class="input required nospace full_input" size="10" maxlength="20" placeholder="닉네임">
                    <button type="button" class="btn_frmline" onclick="checkDuplicate('nick')">중복확인</button>
                </div>
                <span class="result_message main_color font-R" id="msg_mb_nick"></span>
                <span class="help_text">공백없이 한글, 영문, 숫자만 입력 가능 (한글 2글자, 영문 4글자 이상)<br> 닉네임을 바꾸시면 <?php echo (int)$config['cf_nick_modify'] ?>일 이내에는 변경 할 수 없습니다.</span>
            </li>
            <?php }  ?>
            
            
            <li>
                <span>이메일</span>
                <input type="hidden" name="old_email" value="<?php echo $member['mb_email'] ?>">
                <div class="input_wrap">
                    <input type="text" name="mb_email" value="<?php echo isset($member['mb_email'])?$member['mb_email']:''; ?>" id="reg_mb_email" required class="input email full_input required" maxlength="100" placeholder="이메일">
                    <button type="button" class="btn_frmline" onclick="checkDuplicate('email')">중복확인</button>
                </div>
                <span class="result_message main_color font-R" id="msg_mb_email"></span>
                <?php if ($config['cf_use_email_certify']) { ?>
                    <?php if ($w=='') { echo "<span class='help_text'>이메일 로 발송된 내용을 확인한 후 인증하셔야 회원가입이 완료됩니다.</span>"; }  ?>
                    <?php if ($w=='u') { echo "<span class='help_text'>이메일을 변경하시면 다시 인증하셔야 합니다.</span>"; }  ?>
                <?php } ?>
            </li>
            
            <!-- 일반사용자 전용 필드 (추가 인증 불필요) -->
            <?php if($member_type == 'general') { ?>
            <!-- 일반사용자는 별도의 인증서류가 필요없습니다 -->
            <?php } ?>
            
            <!-- 병원관계자 전용 필드 -->
            <?php if($member_type == 'hospital') { ?>
            <li class="hospital_fields" style="display:block;">
                <span>병원명</span>
                <input type="text" name="mb_5" value="<?php echo isset($member['mb_5']) ? get_text($member['mb_5']) : ''; ?>" id="reg_mb_hospital_name" class="input full_input" placeholder="소속 병원명을 입력하세요">
            </li>
            <li class="hospital_fields" style="display:block;">
                <span>직책/직위</span>
                <select name="mb_6" id="reg_mb_position" class="input full_input">
                    <option value="">직책 선택</option>
                    <option value="원장" <?php echo (isset($member['mb_6']) && $member['mb_6'] == '원장') ? 'selected' : ''; ?>>원장</option>
                    <option value="부원장" <?php echo (isset($member['mb_6']) && $member['mb_6'] == '부원장') ? 'selected' : ''; ?>>부원장</option>
                    <option value="전문의" <?php echo (isset($member['mb_6']) && $member['mb_6'] == '전문의') ? 'selected' : ''; ?>>전문의</option>
                    <option value="간호사" <?php echo (isset($member['mb_6']) && $member['mb_6'] == '간호사') ? 'selected' : ''; ?>>간호사</option>
                    <option value="상담실장" <?php echo (isset($member['mb_6']) && $member['mb_6'] == '상담실장') ? 'selected' : ''; ?>>상담실장</option>
                    <option value="마케팅담당" <?php echo (isset($member['mb_6']) && $member['mb_6'] == '마케팅담당') ? 'selected' : ''; ?>>마케팅담당</option>
                    <option value="기타" <?php echo (isset($member['mb_6']) && $member['mb_6'] == '기타') ? 'selected' : ''; ?>>기타</option>
                </select>
            </li>
            <li class="hospital_fields" style="display:block;">
                <span>병원 주소</span>
                <input type="text" name="mb_7" value="<?php echo isset($member['mb_7']) ? get_text($member['mb_7']) : ''; ?>" id="reg_mb_hospital_addr" class="input full_input" placeholder="병원 주소를 입력하세요">
            </li>
            <li class="hospital_fields" style="display:block;">
                <span>병원 인증서류</span>
                <div class="file_upload_wrap">
                    <input type="file" name="mb_hospital_cert" id="mb_hospital_cert" class="input full_input" accept="image/*,.pdf" onchange="updateFileName(this, 'hospital')">
                    <label for="mb_hospital_cert" class="file_upload_label">
                        파일 선택
                    </label>
                    <span class="file_name" id="hospital_file_name">선택된 파일 없음</span>
                </div>
                <span class="file_info">JPG, PNG, PDF 형식 / 5MB 이하 / 사업자등록증, 재직증명서 등</span>

                <?php if($w == 'u' && isset($member['mb_8']) && $member['mb_8']) { ?>
                <div class="uploaded_file_info">
                    <span class="file_icon">📄</span>
                    업로드된 파일: <?php echo basename($member['mb_8']); ?>
                    <div class="file_delete_wrap">
                        <input type="checkbox" name="del_mb_hospital_cert" id="del_mb_hospital_cert" value="1">
                        <label for="del_mb_hospital_cert" class="file_delete_label">파일 삭제</label>
                    </div>
                </div>
                <?php } ?>
            </li>
            <?php } ?>
            
            <?php if ($config['cf_use_homepage']) {  ?>
            <li>
                <span>운영채널</span>
                <input type="text" name="mb_homepage" value="<?php echo get_text($member['mb_homepage']) ?>" id="reg_mb_homepage" <?php echo $config['cf_req_homepage']?"required":""; ?> class="input full_input <?php echo $config['cf_req_homepage']?"required":""; ?>" maxlength="255" placeholder="http:// 또는 https:// 포함입력">
                <span class="help_text">운영중인 웹사이트, 쇼핑몰, 블로그, 유튜브, SNS 등의 채널이 있다면 입력해주세요.<br>대표채널 1개만 입력할 수 있습니다.</span>
            </li>
            <?php } ?>
            
            <?php if ($config['cf_use_tel']) {  ?>
            <li>
                <span>일반전화</span>
                <input type="text" name="mb_tel" value="<?php echo get_text($member['mb_tel']) ?>" id="reg_mb_tel" <?php echo $config['cf_req_tel']?"required":""; ?> class="input full_input <?php echo $config['cf_req_tel']?"required":""; ?>" maxlength="20" placeholder="일반전화번호">
            </li>
            <?php }  ?>
            
            <?php if ($config['cf_use_hp'] || ($config["cf_cert_use"] && ($config['cf_cert_hp'] || $config['cf_cert_simple']))) {  ?>
            <li>
                <span>휴대전화</span>
                <input type="text" name="mb_hp" value="<?php echo get_text($member['mb_hp']) ?>" id="reg_mb_hp" <?php echo $hp_required; ?> <?php echo $hp_readonly; ?> class="input full_input <?php echo $hp_required; ?> <?php echo $hp_readonly; ?>" maxlength="20" placeholder="휴대전화번호">
                <?php if ($config['cf_cert_use'] && ($config['cf_cert_hp'] || $config['cf_cert_simple'])) { ?>
	                <input type="hidden" name="old_mb_hp" value="<?php echo get_text($member['mb_hp']) ?>">
	           <?php } ?>
            </li>
            <?php }  ?>
            
            <?php if ($config['cf_use_addr']) { ?>
            <li>
                <span>주소</span>
                <div>
                    <input type="text" name="mb_zip" value="<?php echo $member['mb_zip1'].$member['mb_zip2']; ?>" id="reg_mb_zip" <?php echo $config['cf_req_addr']?"required":""; ?> class="input twopart_input <?php echo $config['cf_req_addr']?"required":""; ?>" maxlength="6"  placeholder="우편번호"> 
                    <button type="button" class="btn_frmline" onclick="win_zip('fregisterform', 'mb_zip', 'mb_addr1', 'mb_addr2', 'mb_addr3', 'mb_addr_jibeon');">주소 검색</button>
                </div>
                <div class="mt-5">
                    <input type="text" name="mb_addr1" value="<?php echo get_text($member['mb_addr1']) ?>" id="reg_mb_addr1" <?php echo $config['cf_req_addr']?"required":""; ?> class="input frm_address full_input <?php echo $config['cf_req_addr']?"required":""; ?>"  placeholder="기본주소">
                </div>
                <div class="mt-5">
                    <input type="text" name="mb_addr2" value="<?php echo get_text($member['mb_addr2']) ?>" id="reg_mb_addr2" class="input frm_address full_input" placeholder="상세주소">
                </div>
                <div class="mt-5">
                    <input type="text" name="mb_addr3" value="<?php echo get_text($member['mb_addr3']) ?>" id="reg_mb_addr3" class="input frm_address full_input" readonly="readonly" placeholder="참고항목">
                    <input type="hidden" name="mb_addr_jibeon" value="<?php echo get_text($member['mb_addr_jibeon']); ?>">
                </div>
            </li>
            <?php }  ?>
            
            <!-- 입점사 회원 전용 필드 - 출금계좌 정보 -->
            <?php if($member_type == 'partner') { ?>
            <li class="partner_fields" style="display:block;">
                <span>출금계좌</span>
                <input type="text" name="mb_bank" value="<?php echo isset($member['mb_bank']) ? get_text($member['mb_bank']) : ''; ?>" id="reg_mb_bank" class="input full_input" placeholder="계좌번호/은행명/예금주명">
                <span class="help_text">판매대금을 정산할 수 있는 계좌를 등록해주세요.</span>
            </li>
            <?php } ?>
            

           
            <?php if ($config['cf_use_signature']) {  ?>
            <li>
                <span>서명</span>
	            <textarea name="mb_signature" id="reg_mb_signature" <?php echo $config['cf_req_signature']?"required":""; ?> class="<?php echo $config['cf_req_signature']?"required":""; ?> textarea" placeholder="서명을 입력하세요."><?php echo $member['mb_signature'] ?></textarea>
                <span class="help_text">프로필 페이지 및 게시물 하단 작성자정보에 노출 됩니다.</span>
	       </li>
	       <?php }  ?>
	
	       <?php if ($config['cf_use_profile']) {  ?>
           <li>
                <span>소개글</span>
	            <textarea name="mb_profile" id="reg_mb_profile" <?php echo $config['cf_req_profile']?"required":""; ?> class="<?php echo $config['cf_req_profile']?"required":""; ?> textarea" placeholder="소개글을 입력하세요."><?php echo $member['mb_profile'] ?></textarea>
                <span class="help_text">프로필 페이지에 노출 됩니다.</span>
	       </li>
	       <?php }  ?>
           
           
           <?php if ($config['cf_use_member_icon'] && $member['mb_level'] >= $config['cf_icon_level']) {  ?>
           <li>
               <span>회원아이콘</span>
               
               <div>
               <dd class="mem_imgs_dd1">
                   <?php if ($w == 'u' && file_exists($mb_icon_path)) {  ?>
                   <img src="<?php echo $mb_icon_url ?>" style="width:<?php echo $config['cf_member_icon_width'] ?>px; height:<?php echo $config['cf_member_icon_height'] ?>px;" id="mem_img_icon">
                   <?php } else { ?>
                   <img src="<?php echo G5_URL; ?>/gangnamunni/img/no_profile.gif" style="width:<?php echo $config['cf_member_icon_width'] ?>px; height:<?php echo $config['cf_member_icon_height'] ?>px;" id="mem_img_icon">
                   <?php } ?>

               </dd>
               <dd class="mem_imgs_dd2">
                   <div class="file_upload_wrap">
                       <input type="file" name="mb_icon" id="reg_mb_icon" class="files_inp" onchange="updateFileName(this, 'icon')">
                       <label for="reg_mb_icon" class="file_upload_label">
                           이미지 선택
                       </label>
                       <span class="file_name" id="icon_file_name">선택된 파일 없음</span>
                   </div>
                   <span class="file_info">GIF, JPG, PNG 파일 (<?php echo $config['cf_member_icon_width'] ?>X<?php echo $config['cf_member_icon_height'] ?> / <?php echo byteFormat($config['cf_member_icon_size'], "MB"); ?> 이하)</span>
                   
                   <?php if ($w == 'u' && file_exists($mb_icon_path)) {  ?>
                   <div class="file_delete_wrap" style="margin-top: 10px;">
                       <input type="checkbox" name="del_mb_icon" value="1" id="del_mb_icon">
                       <label for="del_mb_icon" class="file_delete_label">이미지 삭제</label>
                   </div>
                   <?php } ?>
               </dd>
               <div class="cb"></div>

               </div>

           </li>
           <?php } ?>
           
           
           
           <?php if ($member['mb_level'] >= $config['cf_icon_level'] && $config['cf_member_img_size'] && $config['cf_member_img_width'] && $config['cf_member_img_height']) {  ?>
           <li>
               <span>회원이미지</span>
               
               <div>
               <dd class="mem_imgs_dd1">
                   <?php if ($w == 'u' && file_exists($mb_img_path)) {  ?>
                   <img src="<?php echo $mb_img_url ?>" style="width:<?php echo $config['cf_member_img_width'] ?>px; height:<?php echo $config['cf_member_img_height'] ?>px;" id="mem_img_img">
                   <?php } else { ?>
                   <img src="<?php echo G5_URL; ?>/gangnamunni/img/no_profile.gif" style="width:<?php echo $config['cf_member_img_width'] ?>px; height:<?php echo $config['cf_member_img_height'] ?>px;" id="mem_img_img">
                   <?php } ?>

               </dd>
               <dd class="mem_imgs_dd2">
                   <div class="file_upload_wrap">
                       <input type="file" name="mb_img" id="reg_mb_img" class="files_inp" onchange="updateFileName(this, 'img')">
                       <label for="reg_mb_img" class="file_upload_label">
                           이미지 선택
                       </label>
                       <span class="file_name" id="img_file_name">선택된 파일 없음</span>
                   </div>
                   <span class="file_info">GIF, JPG, PNG 파일 (<?php echo $config['cf_member_img_width'] ?>X<?php echo $config['cf_member_img_height'] ?> / <?php echo byteFormat($config['cf_member_img_size'], "MB"); ?> 이하)</span>
                   
                   <?php if ($w == 'u' && file_exists($mb_img_path)) {  ?>
                   <div class="file_delete_wrap" style="margin-top: 10px;">
                       <input type="checkbox" name="del_mb_img" value="1" id="del_mb_img">
                       <label for="del_mb_img" class="file_delete_label">이미지 삭제</label>
                   </div>
                   <?php } ?>
               </dd>
               <div class="cb"></div>

               </div>

           </li>
           <?php } ?>
           
           
           <?php if( $w == 'u' && function_exists('social_member_provider_manage') ){ ?>
               <?php social_member_provider_manage(); ?>
           <?php } ?>
           


            <li>
                <div>
                    <input type="checkbox" name="mb_mailling" value="1" id="reg_mb_mailling" <?php echo ($w=='' || $member['mb_mailling'])?'checked':''; ?>>
		            <label for="reg_mb_mailling">정보 메일 수신동의</label>
                </div>
                
                <?php if ($config['cf_use_hp'] || isset($app['ap_title']) && $app['ap_title'] && isset($app['ap_key']) && $app['ap_key'] && isset($app['ap_pid']) && $app['ap_pid']) { ?>
                <div>
                    <input type="checkbox" name="mb_sms" value="1" id="reg_mb_sms" <?php echo ($w=='' || $member['mb_sms'])?'checked':''; ?>>
		            <label for="reg_mb_sms"><?php if($config['cf_use_hp']) { ?>SMS <?php } ?><?php if (isset($app['ap_title']) && $app['ap_title'] && isset($app['ap_key']) && $app['ap_key'] && isset($app['ap_pid']) && $app['ap_pid']) { ?><?php if($config['cf_use_hp']) { ?>및 <?php } ?>Push 알림 <?php } ?>수신동의</label>
                </div>
                <?php } ?>

                
                <?php if (isset($member['mb_open_date']) && $member['mb_open_date'] <= date("Y-m-d", G5_SERVER_TIME - ($config['cf_open_modify'] * 86400)) || empty($member['mb_open_date'])) { // 정보공개 수정일이 지났다면 수정가능 ?>
                <div>
                    <input type="checkbox" name="mb_open" value="1" id="reg_mb_open" <?php echo ($w=='' || $member['mb_open'])?'checked':''; ?>>
		            <label for="reg_mb_open">프로필 정보공개 / 쪽지수신 동의</label>
                    <input type="hidden" name="mb_open_default" value="<?php echo $member['mb_open'] ?>"> 
                </div>
                
                <?php if($config['cf_open_modify']) { ?>
                <div class="help_t_text">
                    정보공개 항목을 변경 하시면 <?php echo (int)$config['cf_open_modify'] ?>일 이내에는 변경을 할 수 없어요.
                </div>
                <?php } ?>
                
                <?php } else { ?>
                

                <div class="help_t_text">
                    <input type="hidden" name="mb_open" value="<?php echo $member['mb_open'] ?>">
                    정보공개 항목을 최근에 변경하신적이 있어요.<br>
                    정보공개는 변경 후 <?php echo (int)$config['cf_open_modify'] ?>일 이내, <?php echo date("Y년 m월 j일", isset($member['mb_open_date']) ? strtotime("{$member['mb_open_date']} 00:00:00")+$config['cf_open_modify']*86400:G5_SERVER_TIME+$config['cf_open_modify']*86400); ?> 까지는 변경 할 수 없어요.
                </div>

                <?php }  ?>

           
            </li>

	                

	       <?php if ($w == "" && $config['cf_use_recommend']) {  ?>
            <li>
               <span>추천인아이디</span>
               <input type="text" 
                      name="mb_recommend" 
                      id="reg_mb_recommend" 
                      class="input full_input<?php echo ($mb_recommend != '') ? ' readonly' : ''; ?>" 
                      placeholder="추천인아이디" 
                      value="<?php echo htmlspecialchars($mb_recommend); ?>" 
                      <?php echo ($mb_recommend != '') ? 'readonly="readonly"' : ''; ?>>
                
                <?php if($mb_recommend != '') { ?>
                <!-- 추천인이 있을 때 혜택 안내 -->
                <div class="recommend_info">
                    <h4>🎉 추천인 혜택 안내</h4>
                    <ul>
                        <li><strong><?php echo htmlspecialchars($mb_recommend); ?>님</strong>의 추천으로 가입합니다</li>
                        <li>가입 완료시 <strong>500 포인트</strong>가 즉시 지급됩니다</li>
                        <?php if(isset($config['cf_recommend_point']) && $config['cf_recommend_point'] > 0) { ?>
                        <li>추천인에게도 <strong><?php echo number_format($config['cf_recommend_point']) ?> 포인트</strong>가 지급됩니다</li>
                        <?php } ?>
                    </ul>
                </div>
                
                <script>
                // 추천인 값 JavaScript로 강제 설정
                $(document).ready(function() {
                    var recommendValue = '<?php echo addslashes($mb_recommend); ?>';
                    if(recommendValue) {
                        $('#reg_mb_recommend').val(recommendValue);
                        $('#reg_mb_recommend').prop('readonly', true);
                        $('#reg_mb_recommend').css('background-color', '#f8f8f8');
                        console.log('추천인 설정됨: ' + recommendValue);
                    }
                });
                </script>
                <?php } else { ?>
                <!-- 추천인이 없을 때 기본 안내 -->
                <span class="help_text">
                추천인 아이디가 있다면 입력해주세요.
                <?php if(isset($config['cf_recommend_point']) && $config['cf_recommend_point'] > 0) { ?>
                <br>입력하신 회원에게 감사의 표시로 <b class="font-B"><?php echo number_format($config['cf_recommend_point']) ?> 포인트</b>가 지급되요 :D
                <?php } ?>
                </span>
                <?php } ?>
	       </li>
	       <?php }  ?>
           
           
           

            
           <li class="is_captcha_use">
	           <?php echo captcha_html(); ?>
	       </li>
            

            
            <li>
                <div class="btn_confirm">
                    <button type="submit" class="btn_submit font-B" accesskey="s"><?php echo $w==''?'회원가입':'정보수정'; ?></button>
                    
                    <?php if($w == 'u') { ?>
                    <button type="button" class="btn_submit font-B mt-10" onclick="javascript:member_leaves();" style="background-color:#f1f1f1 !important; color:#000;">회원탈퇴</button>
                    
                    <script>
                    function member_leaves() {  // 회원 탈퇴
                        if (confirm("탈퇴시 보유하신 포인트 및 기타 혜택, 개인정보 등\n모든 정보가 삭제 되며 동일 아이디로 재가입이 불가능합니다.\n\n정말 탈퇴 하시겠습니까?"))
                            location.href = '<?php echo G5_BBS_URL ?>/member_confirm.php?url=member_leave.php';
                    }
                    </script>
                    <?php } ?>
                </div>
            </li>
            

            
            <li class="join_links">
                <?php if($w == '') { ?>
                나중에 가입할래요.　<a href="<?php echo G5_URL; ?>/gangnamunni/" class="font-B">회원가입 취소</a>
                <?php } else { ?>
                <a href="<?php echo G5_URL; ?>/gangnamunni/" class="font-B">취소</a>
                <?php } ?>
            </li>
            
        </ul>
        </form>
        
    </div>
</div>

<script>
// 파일명 업데이트 함수
function updateFileName(input, type) {
    const fileName = input.files[0] ? input.files[0].name : '선택된 파일 없음';
    document.getElementById(type + '_file_name').textContent = fileName;
    
    // 이미지 미리보기 (아이콘 및 회원이미지)
    if (type === 'icon' || type === 'img') {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('mem_img_' + type).src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
}

// 파일 삭제 체크박스 상태 변경 시 시각적 피드백
document.querySelectorAll('.file_delete_wrap input[type="checkbox"]').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        const label = this.nextElementSibling;
        if (this.checked) {
            label.textContent = '삭제 예정';
        } else {
            label.textContent = this.id.includes('icon') || this.id.includes('img') ? '이미지 삭제' : '파일 삭제';
        }
    });
});

$(function() {
    $("#reg_zip_find").css("display", "inline-block");
    var pageTypeParam = "pageType=register";

	<?php if($config['cf_cert_use'] && $config['cf_cert_simple']) { ?>
	// 이니시스 간편인증
	var url = "<?php echo G5_INICERT_URL; ?>/ini_request.php";
	var type = "";    
    var params = "";
    var request_url = "";

	$(".win_sa_cert").click(function() {
		if(!cert_confirm()) return false;
		type = $(this).data("type");
		params = "?directAgency=" + type + "&" + pageTypeParam;
        request_url = url + params;
        call_sa(request_url);
	});
    <?php } ?>
    <?php if($config['cf_cert_use'] && $config['cf_cert_ipin']) { ?>
    // 아이핀인증
    var params = "";
    $("#win_ipin_cert").click(function() {
		if(!cert_confirm()) return false;
        params = "?" + pageTypeParam;
        var url = "<?php echo G5_OKNAME_URL; ?>/ipin1.php"+params;
        certify_win_open('kcb-ipin', url);
        return;
    });

    <?php } ?>
    <?php if($config['cf_cert_use'] && $config['cf_cert_hp']) { ?>
    // 휴대폰인증
    var params = "";
    $("#win_hp_cert").click(function() {
		if(!cert_confirm()) return false;
        params = "?" + pageTypeParam;
        <?php     
        switch($config['cf_cert_hp']) {
            case 'kcb':                
                $cert_url = G5_OKNAME_URL.'/hpcert1.php';
                $cert_type = 'kcb-hp';
                break;
            case 'kcp':
                $cert_url = G5_KCPCERT_URL.'/kcpcert_form.php';
                $cert_type = 'kcp-hp';
                break;
            case 'lg':
                $cert_url = G5_LGXPAY_URL.'/AuthOnlyReq.php';
                $cert_type = 'lg-hp';
                break;
            default:
                echo 'alert("기본환경설정에서 휴대폰 본인확인 설정을 해주십시오");';
                echo 'return false;';
                break;
        }
        ?>
        
        certify_win_open("<?php echo $cert_type; ?>", "<?php echo $cert_url; ?>"+params);
        return;
    });
    <?php } ?>
});

// submit 최종 폼체크
function fregisterform_submit(f)
{
    // 회원아이디 검사
    if (f.w.value == "") {
        var msg = reg_mb_id_check();
        if (msg) {
            alert(msg);
            f.mb_id.select();
            return false;
        }
    }

    if (f.w.value == "") {
        if (f.mb_password.value.length < 3) {
            alert("비밀번호를 3글자 이상 입력하십시오.");
            f.mb_password.focus();
            return false;
        }
    }

    if (f.mb_password.value != f.mb_password_re.value) {
        alert("비밀번호가 같지 않습니다.");
        f.mb_password_re.focus();
        return false;
    }

    if (f.mb_password.value.length > 0) {
        if (f.mb_password_re.value.length < 3) {
            alert("비밀번호를 3글자 이상 입력하십시오.");
            f.mb_password_re.focus();
            return false;
        }
    }

    // 이름 검사
    if (f.w.value=="") {
        if (f.mb_name.value.length < 1) {
            alert("이름을 입력하십시오.");
            f.mb_name.focus();
            return false;
        }
    }
    
    // 회원 유형별 필수 입력 검사
    if (f.w.value == "") {  // 신규가입시에만
        var memberType = f.mb_type.value;

        if (memberType === 'general') {
            // 일반사용자는 추가 인증 불필요
        } else if (memberType === 'hospital') {
            if (!f.mb_5.value) {
                alert("병원명을 입력해주세요.");
                f.mb_5.focus();
                return false;
            }
            if (!f.mb_6.value) {
                alert("직책을 선택해주세요.");
                f.mb_6.focus();
                return false;
            }
            if (!f.mb_7.value) {
                alert("병원 주소를 입력해주세요.");
                f.mb_7.focus();
                return false;
            }
            if (!f.mb_hospital_cert.value) {
                alert("병원 인증서류를 업로드해주세요.");
                f.mb_hospital_cert.focus();
                return false;
            }
        }
    }

    <?php if($w == '' && $config['cf_cert_use'] && $config['cf_cert_req']) { ?>
    // 본인확인 체크
    if(f.cert_no.value=="") {
        alert("회원가입을 위해서는 본인확인을 해주셔야 합니다.");
        return false;
    }
    <?php } ?>

    // 닉네임 검사
    if ((f.w.value == "") || (f.w.value == "u" && f.mb_nick.defaultValue != f.mb_nick.value)) {
        var msg = reg_mb_nick_check();
        if (msg) {
            alert(msg);
            f.reg_mb_nick.select();
            return false;
        }
    }

    // E-mail 검사
    if ((f.w.value == "") || (f.w.value == "u" && f.mb_email.defaultValue != f.mb_email.value)) {
        var msg = reg_mb_email_check();
        if (msg) {
            alert(msg);
            f.reg_mb_email.select();
            return false;
        }
    }

    <?php if (($config['cf_use_hp'] || $config['cf_cert_hp']) && $config['cf_req_hp']) {  ?>
    // 휴대폰번호 체크
    var msg = reg_mb_hp_check();
    if (msg) {
        alert(msg);
        f.reg_mb_hp.select();
        return false;
    }
    <?php } ?>

    if (typeof f.mb_icon != "undefined") {
        if (f.mb_icon.value) {
            if (!f.mb_icon.value.toLowerCase().match(/.(gif|jpe?g|png)$/i)) {
                alert("회원아이콘이 이미지 파일이 아닙니다.");
                f.mb_icon.focus();
                return false;
            }
        }
    }

    if (typeof f.mb_img != "undefined") {
        if (f.mb_img.value) {
            if (!f.mb_img.value.toLowerCase().match(/.(gif|jpe?g|png)$/i)) {
                alert("회원이미지가 이미지 파일이 아닙니다.");
                f.mb_img.focus();
                return false;
            }
        }
    }

    if (typeof(f.mb_recommend) != "undefined" && f.mb_recommend.value) {
        if (f.mb_id.value == f.mb_recommend.value) {
            alert("본인을 추천할 수 없습니다.");
            f.mb_recommend.focus();
            return false;
        }

        var msg = reg_mb_recommend_check();
        if (msg) {
            alert(msg);
            f.mb_recommend.select();
            return false;
        }
    }

    <?php echo chk_captcha_js();  ?>

    document.getElementById("btn_submit").disabled = "disabled";

    return true;
}

jQuery(function($){
	//tooltip
    $(document).on("click", ".tooltip_icon", function(e){
        $(this).next(".tooltip").fadeIn(400).css("display","inline-block");
    }).on("mouseout", ".tooltip_icon", function(e){
        $(this).next(".tooltip").fadeOut();
    });
});

// 중복 확인 함수
function checkDuplicate(type) {
        let url;
        let fieldId;
        let msgId;
        let typeName;

        switch (type) {
            case 'id':
                url = "ajax.mb_id.php";
                fieldId = "#reg_mb_id";
                msgId = "#msg_mb_id";
                typeName = "아이디";
                break;
            case 'nick':
                url = "ajax.mb_nick.php";
                fieldId = "#reg_mb_nick";
                msgId = "#msg_mb_nick";
                typeName = "닉네임";
                break;
            case 'email':
                url = "ajax.mb_email.php";
                fieldId = "#reg_mb_email";
                msgId = "#msg_mb_email";
                typeName = "이메일";
                break;
            default:
                return; 
        }

        var fieldValue = $(fieldId).val();
        var data = {};
        data['reg_mb_' + type] = fieldValue;
        if (type !== 'id') { 
            data['checkDuplicate' + type.charAt(0).toUpperCase() + type.slice(1)] = 1;
        }

        $.post(url, data, function(response) {
            $(msgId).html('').removeClass('error success');
            if(response) {
                $(msgId).html(response).addClass('error');
            } else {
                $(msgId).html('사용할 수 있는 ' + typeName + '입니다.').addClass('success');
            }
        });
    }

    $('#reg_mb_password_re').on('input', function() {
        var password = $('#reg_mb_password').val();
        var passwordRe = $(this).val();
        var $msg = $('#msg_mb_password_re');

        $msg.removeClass('error success');

        if (password === '' || passwordRe === '') {
            $msg.html('').removeClass('error success');
            return;
        }

        if (password === passwordRe) {
            $msg.html('비밀번호가 일치합니다.').addClass('success');
        } else {
            $msg.html('비밀번호가 일치하지 않습니다.').addClass('error');
        }
    });

    $('#reg_mb_password').on('input', function() {
        $('#reg_mb_password_re').trigger('input');
    });
    
    // 추천인 URL 파라미터 확인 및 자동 입력
    $(document).ready(function() {
        // URL 파라미터 가져오기
        const urlParams = new URLSearchParams(window.location.search);
        const mbRecommend = urlParams.get('mb_recommend');
        
        if(mbRecommend && mbRecommend.trim() !== '') {
            console.log('URL 추천인 파라미터 감지: ' + mbRecommend);
            
            // 추천인 필드에 값 설정
            $('#reg_mb_recommend').val(mbRecommend);
            $('#reg_mb_recommend').prop('readonly', true);
            $('#reg_mb_recommend').addClass('readonly');
            $('#reg_mb_recommend').css('background-color', '#f8f8f8');
            
            // 추천인 정보가 없으면 동적으로 추가
            if($('#reg_mb_recommend').siblings('.recommend_info').length === 0) {
                var recommendInfo = '<div class="recommend_info">' +
                    '<h4>🎉 추천인 혜택 안내</h4>' +
                    '<ul>' +
                    '<li><strong>' + mbRecommend + '님</strong>의 추천으로 가입합니다</li>' +
                    '<li>가입 완료시 <strong>500 포인트</strong>가 즉시 지급됩니다</li>' +
                    '<li>추천인에게도 포인트가 지급됩니다</li>' +
                    '</ul>' +
                    '</div>';
                
                $('#reg_mb_recommend').after(recommendInfo);
                $('#reg_mb_recommend').siblings('.help_text').hide();
            }
        }
    });
</script>

<!-- } 회원정보 입력/수정 끝 -->