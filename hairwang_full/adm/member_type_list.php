
<?php
$sub_menu = "200150";
include_once('./_common.php');

// auth 배열 키 존재 확인 후 권한 체크
if (isset($auth[$sub_menu])) {
    auth_check($auth[$sub_menu], 'r');
} else {
    // 권한 정보가 없을 경우 기본 처리 (관리자만 접근 가능하도록)
    if ($member['mb_level'] < 10) {
        alert('접근 권한이 없습니다.');
    }
}

$sql_common = " from {$g5['member_table']} ";

$sql_search = " where (1) ";

// GET 변수 초기화
$sfl = isset($_GET['sfl']) ? $_GET['sfl'] : '';
$stx = isset($_GET['stx']) ? $_GET['stx'] : '';
$sst = isset($_GET['sst']) ? $_GET['sst'] : '';
$sod = isset($_GET['sod']) ? $_GET['sod'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$mb_type = isset($_GET['mb_type']) ? $_GET['mb_type'] : '';
$mb_status = isset($_GET['mb_status']) ? $_GET['mb_status'] : '';
$mb_level = isset($_GET['mb_level']) ? $_GET['mb_level'] : '';  // 등급 필터 추가

if ($stx) {
    $sql_search .= " and ( ";
    switch ($sfl) {
        case 'mb_id' :
            $sql_search .= " ({$sfl} = '{$stx}') ";
            break;
        default :
            $sql_search .= " ({$sfl} like '%{$stx}%') ";
            break;
    }
    $sql_search .= " ) ";
}

// 회원 유형별 검색
if($mb_type) {
    $sql_search .= " and mb_1 = '{$mb_type}' ";
}

// 승인 상태별 검색
if($mb_status != '') {
    if($mb_status == 'pending') {
        $sql_search .= " and mb_9 = '' and mb_1 != '' ";
    } else if($mb_status == 'approved') {
        $sql_search .= " and mb_9 = '1' ";
    } else if($mb_status == 'rejected') {
        $sql_search .= " and mb_9 = '2' ";
    }
}

// 등급별 검색 추가
if($mb_level != '') {
    $sql_search .= " and mb_level = '{$mb_level}' ";
}

if (!$sst) {
    $sst = "mb_datetime";
    $sod = "desc";
}

$sql_order = " order by {$sst} {$sod} ";

$sql = " select count(*) as cnt {$sql_common} {$sql_search} {$sql_order} ";
$row = sql_fetch($sql);
$total_count = $row['cnt'];

// 승인대기 회원수 구하기
$sql_pending = " select count(*) as cnt {$sql_common} where mb_9 = '' and mb_1 != '' ";
$row_pending = sql_fetch($sql_pending);
$pending_count = $row_pending['cnt'];

$rows = $config['cf_page_rows'];
$total_page  = ceil($total_count / $rows);  // 전체 페이지 계산
if ($page < 1) $page = 1; // 페이지가 없으면 첫 페이지 (1 페이지)
$from_record = ($page - 1) * $rows; // 시작 열을 구함

// 쿼리스트링 생성
$qstr = '';
$qstr .= 'sfl=' . urlencode($sfl) . '&amp;';
$qstr .= 'stx=' . urlencode($stx) . '&amp;';
$qstr .= 'sst=' . urlencode($sst) . '&amp;';
$qstr .= 'sod=' . urlencode($sod) . '&amp;';
$qstr .= 'mb_type=' . urlencode($mb_type) . '&amp;';
$qstr .= 'mb_status=' . urlencode($mb_status) . '&amp;';
$qstr .= 'mb_level=' . urlencode($mb_level) . '&amp;';

$listall = '<a href="'.$_SERVER['SCRIPT_NAME'].'" class="ov_listall">전체목록</a>';

$g5['title'] = '회원유형 관리';
include_once('./admin.head.php');

$sql = " select * {$sql_common} {$sql_search} {$sql_order} limit {$from_record}, {$rows} ";
$result = sql_query($sql);

$colspan = 12;  // 컬럼 수 증가

// 레벨별 설정 가져오기
$level_names = array(
    1 => '헤린이',
    2 => '루키 스타',
    3 => '슈퍼 스타',
    4 => '빡고수',
    5 => '신의손',
    6 => '특별회원',
    7 => '명예회원',
    8 => '골드회원',
    9 => '다이아회원',
    10 => '최고관리자'
);

// 레벨별 색상 정의
$level_colors = array(
    1 => array('bg' => '#F0F9FF', 'border' => '#BAE6FD', 'text' => '#0369A1'),
    2 => array('bg' => '#F0FDF4', 'border' => '#BBF7D0', 'text' => '#15803D'),
    3 => array('bg' => '#FEF3C7', 'border' => '#FCD34D', 'text' => '#B45309'),
    4 => array('bg' => '#FEE2E2', 'border' => '#FCA5A5', 'text' => '#DC2626'),
    5 => array('bg' => '#EDE9FE', 'border' => '#C4B5FD', 'text' => '#7C3AED'),
    6 => array('bg' => '#E0E7FF', 'border' => '#A5B4FC', 'text' => '#4F46E5'),
    7 => array('bg' => '#FECACA', 'border' => '#F87171', 'text' => '#DC2626'),
    8 => array('bg' => '#FED7AA', 'border' => '#FB923C', 'text' => '#EA580C'),
    9 => array('bg' => '#DDD6FE', 'border' => '#A78BFA', 'text' => '#7C3AED'),
    10 => array('bg' => '#1E293B', 'border' => '#475569', 'text' => '#FFFFFF')
);
?>

<style>
.cert_img { max-width: 100px; cursor: pointer; }
.cert_modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.8); }
.cert_modal img { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 90%; max-height: 90%; }
.cert_modal .close { position: absolute; top: 20px; right: 40px; color: #fff; font-size: 40px; cursor: pointer; }
.status_badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; }
.status_pending { background: #ffc107; color: #000; }
.status_approved { background: #28a745; color: #fff; }
.status_rejected { background: #dc3545; color: #fff; }

/* 레벨 배지 스타일 - 참고 코드 기반 */
.level-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 12px;
    font-weight: 700;
    font-size: 14px;
    letter-spacing: -0.2px;
    white-space: nowrap;
    border: 2px solid;
    transition: all 0.3s ease;
}

.level-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.level-badge i {
    font-size: 12px;
}

/* 등급변경일 스타일 */
.td_level_date { 
    min-width: 100px; 
    white-space: nowrap; 
}

/* 테이블 hover 효과 */
.tbl_head01 tbody tr:hover {
    background-color: #f8f9fa;
}

/* 회원유형 스타일 개선 */
.member-type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
}

.type-student { background: #E6F4FF; color: #0066CC; }
.type-designer { background: #FFF0E6; color: #CC6600; }
.type-partner { background: #FFE6F0; color: #CC0066; }
</style>

<div class="local_ov01 local_ov">
    <?php echo $listall ?>
    <span class="btn_ov01"><span class="ov_txt">전체회원</span><span class="ov_num"> <?php echo number_format($total_count) ?>명 </span></span>
    <span class="btn_ov01"><span class="ov_txt">승인대기</span><span class="ov_num" style="color:#ff6600;"> <?php echo number_format($pending_count) ?>명 </span></span>
</div>

<form id="fsearch" name="fsearch" class="local_sch01 local_sch" method="get">
<label for="sfl" class="sound_only">검색대상</label>
<select name="sfl" id="sfl">
    <option value="mb_id"<?php echo get_selected($sfl, "mb_id"); ?>>회원아이디</option>
    <option value="mb_name"<?php echo get_selected($sfl, "mb_name"); ?>>이름</option>
    <option value="mb_nick"<?php echo get_selected($sfl, "mb_nick"); ?>>닉네임</option>
    <option value="mb_email"<?php echo get_selected($sfl, "mb_email"); ?>>E-MAIL</option>
</select>

<label for="mb_type" class="sound_only">회원유형</label>
<select name="mb_type" id="mb_type">
    <option value="">전체유형</option>
    <option value="student"<?php echo get_selected($mb_type, "student"); ?>>학생회원</option>
    <option value="designer"<?php echo get_selected($mb_type, "designer"); ?>>헤어디자이너</option>
    <option value="partner"<?php echo get_selected($mb_type, "partner"); ?>>입점사</option>
</select>

<label for="mb_status" class="sound_only">승인상태</label>
<select name="mb_status" id="mb_status">
    <option value="">전체상태</option>
    <option value="pending"<?php echo get_selected($mb_status, "pending"); ?>>승인대기</option>
    <option value="approved"<?php echo get_selected($mb_status, "approved"); ?>>승인완료</option>
    <option value="rejected"<?php echo get_selected($mb_status, "rejected"); ?>>승인거부</option>
</select>

<label for="mb_level" class="sound_only">회원등급</label>
<select name="mb_level" id="mb_level">
    <option value="">전체등급</option>
    <?php for($i=1; $i<=10; $i++) { ?>
    <option value="<?php echo $i; ?>"<?php echo get_selected($mb_level, $i); ?>>Lv.<?php echo $i; ?> <?php echo $level_names[$i]; ?></option>
    <?php } ?>
</select>

<label for="stx" class="sound_only">검색어<strong class="sound_only"> 필수</strong></label>
<input type="text" name="stx" value="<?php echo $stx ?>" id="stx" class="frm_input">
<input type="submit" class="btn_submit" value="검색">
</form>

<form name="fmemberlist" id="fmemberlist" action="./member_type_update.php" onsubmit="return fmemberlist_submit(this);" method="post">
<input type="hidden" name="sst" value="<?php echo $sst ?>">
<input type="hidden" name="sod" value="<?php echo $sod ?>">
<input type="hidden" name="sfl" value="<?php echo $sfl ?>">
<input type="hidden" name="stx" value="<?php echo $stx ?>">
<input type="hidden" name="page" value="<?php echo $page ?>">
<input type="hidden" name="mb_type" value="<?php echo $mb_type ?>">
<input type="hidden" name="mb_status" value="<?php echo $mb_status ?>">
<input type="hidden" name="mb_level" value="<?php echo $mb_level ?>">
<input type="hidden" name="token" value="">

<div class="tbl_head01 tbl_wrap">
    <table>
    <caption><?php echo $g5['title']; ?> 목록</caption>
    <thead>
    <tr>
        <th scope="col" id="mb_list_chk">
            <label for="chkall" class="sound_only">회원 전체</label>
            <input type="checkbox" name="chkall" value="1" id="chkall" onclick="check_all(this.form)">
        </th>
        <th scope="col" id="mb_list_id"><?php echo subject_sort_link('mb_id') ?>아이디</a></th>
        <th scope="col" id="mb_list_name"><?php echo subject_sort_link('mb_name') ?>이름</a></th>
        <th scope="col" id="mb_list_nick"><?php echo subject_sort_link('mb_nick') ?>닉네임</a></th>
        <th scope="col" id="mb_list_type">회원유형</th>
        <th scope="col" id="mb_list_cert">인증서류</th>
        <th scope="col" id="mb_list_info">추가정보</th>
        <th scope="col" id="mb_list_status">승인상태</th>
        <th scope="col" id="mb_list_level"><?php echo subject_sort_link('mb_level') ?>등급</a></th>
        <th scope="col" id="mb_list_level_date" style="min-width:100px;">등급변경일</th>
        <th scope="col" id="mb_list_join"><?php echo subject_sort_link('mb_datetime') ?>가입일</a></th>
        <th scope="col" id="mb_list_mng">관리</th>
    </tr>
    </thead>
    <tbody>
    <?php
    for ($i=0; $row=sql_fetch_array($result); $i++) {
        $mb_id = $row['mb_id'];
        $mb_nick = get_sideview($row['mb_id'], get_text($row['mb_nick']), $row['mb_email'], $row['mb_homepage']);
        
        // 회원유형 표시
        $mb_type_str = '';
        $type_class = '';
        switch($row['mb_1']) {
            case 'student':
                $mb_type_str = '학생회원';
                $type_class = 'type-student';
                break;
            case 'designer':
                $mb_type_str = '헤어디자이너';
                $type_class = 'type-designer';
                break;
            case 'partner':
                $mb_type_str = '입점사';
                $type_class = 'type-partner';
                break;
            default:
                $mb_type_str = '일반회원';
                $type_class = '';
        }
        
        // 승인상태 표시
        $status_str = '';
        if($row['mb_1']) {
            if($row['mb_9'] == '1') {
                $status_str = '<span class="status_badge status_approved">승인완료</span>';
            } else if($row['mb_9'] == '2') {
                $status_str = '<span class="status_badge status_rejected">승인거부</span>';
            } else {
                $status_str = '<span class="status_badge status_pending">승인대기</span>';
            }
        }
        
        // 레벨 정보 설정
        $level = $row['mb_level'];
        $level_name = isset($level_names[$level]) ? $level_names[$level] : '레벨'.$level;
        $colors = isset($level_colors[$level]) ? $level_colors[$level] : $level_colors[1];
        
        // 등급 변경일 표시 (mb_10 필드 활용)
        $level_date = '';
        if($row['mb_10']) {
            $level_date = substr($row['mb_10'], 0, 19);  // YYYY-MM-DD HH:MM:SS 형식
        }
        
        $bg = 'bg'.($i%2);
    ?>
    <tr class="<?php echo $bg; ?>">
        <td headers="mb_list_chk" class="td_chk">
            <input type="hidden" name="mb_id[<?php echo $i ?>]" value="<?php echo $row['mb_id'] ?>" id="mb_id_<?php echo $i ?>">
            <label for="chk_<?php echo $i; ?>" class="sound_only"><?php echo get_text($row['mb_name']); ?> <?php echo get_text($row['mb_nick']); ?>님</label>
            <input type="checkbox" name="chk[]" value="<?php echo $i ?>" id="chk_<?php echo $i ?>">
        </td>
        <td headers="mb_list_id" class="td_name sv_use">
            <?php echo $mb_id ?>
        </td>
        <td headers="mb_list_name" class="td_mbname"><?php echo get_text($row['mb_name']); ?></td>
        <td headers="mb_list_nick" class="td_name sv_use"><div><?php echo $mb_nick ?></div></td>
        <td headers="mb_list_type" class="td_mbtype">
            <?php if($type_class) { ?>
                <span class="member-type-badge <?php echo $type_class; ?>"><?php echo $mb_type_str; ?></span>
            <?php } else { ?>
                <?php echo $mb_type_str; ?>
            <?php } ?>
        </td>
        <td headers="mb_list_cert" class="td_mbcert">
            <?php 
            if($row['mb_1'] == 'student' && $row['mb_4']) {
                $cert_path = G5_DATA_URL.'/member_cert/'.$row['mb_id'].'/'.$row['mb_4'];
                echo '<img src="'.$cert_path.'" class="cert_img" onclick="viewCert(\''.$cert_path.'\')" alt="학생증">';
            } else if($row['mb_1'] == 'designer' && $row['mb_8']) {
                $cert_path = G5_DATA_URL.'/member_cert/'.$row['mb_id'].'/'.$row['mb_8'];
                echo '<img src="'.$cert_path.'" class="cert_img" onclick="viewCert(\''.$cert_path.'\')" alt="자격증">';
            }
            ?>
        </td>
        <td headers="mb_list_info" class="td_mbinfo">
            <?php 
            if($row['mb_1'] == 'student') {
                echo '학교: '.get_text($row['mb_2']).'<br>';
                echo '학년: '.get_text($row['mb_3']);
            } else if($row['mb_1'] == 'designer') {
                echo '자격증: '.get_text($row['mb_5']).'<br>';
                echo '경력: '.get_text($row['mb_6']).'<br>';
                echo '매장: '.get_text($row['mb_7']);
            } else if($row['mb_1'] == 'partner') {
                echo '계좌: '.get_text($row['mb_bank']);
            }
            ?>
        </td>
        <td headers="mb_list_status" class="td_mbstatus"><?php echo $status_str; ?></td>
        <td headers="mb_list_level" class="td_num">
            <div class="level-badge" style="
                background: <?php echo $colors['bg'] ?>; 
                color: <?php echo $colors['text'] ?>; 
                border-color: <?php echo $colors['border'] ?>;">
                <i class="fas fa-star"></i> 
                Lv.<?php echo $level ?> <?php echo $level_name ?>
            </div>
        </td>
        <td headers="mb_list_level_date" class="td_date td_level_date"><?php echo $level_date; ?></td>
        <td headers="mb_list_join" class="td_date"><?php echo substr($row['mb_datetime'],2,8); ?></td>
        <td headers="mb_list_mng" class="td_mng td_mng_s">
            <a href="./member_form.php?w=u&amp;mb_id=<?php echo $row['mb_id'] ?>" class="btn btn_03">수정</a>
        </td>
    </tr>
    <?php
    }
    if ($i == 0)
        echo "<tr><td colspan=\"".$colspan."\" class=\"empty_table\">자료가 없습니다.</td></tr>";
    ?>
    </tbody>
    </table>
</div>

<div class="btn_fixed_top">
    <input type="submit" name="act_button" value="선택승인" onclick="document.pressed=this.value" class="btn btn_02">
    <input type="submit" name="act_button" value="선택거부" onclick="document.pressed=this.value" class="btn btn_02">
    <input type="submit" name="act_button" value="선택삭제" onclick="document.pressed=this.value" class="btn btn_02">
    <?php if($pending_count > 0) { ?>
    <input type="submit" name="act_button" value="승인대기 전체삭제" onclick="document.pressed=this.value" class="btn btn_01" style="background:#ff3333;">
    <?php } ?>
</div>

</form>

<?php echo get_paging(G5_IS_MOBILE ? $config['cf_mobile_pages'] : $config['cf_write_pages'], $page, $total_page, '?'.$qstr.'&amp;page='); ?>

<!-- 인증서류 모달 -->
<div id="certModal" class="cert_modal" onclick="closeCert()">
    <span class="close">&times;</span>
    <img id="certImg" src="" alt="">
</div>

<!-- Font Awesome 아이콘 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<script>
function fmemberlist_submit(f)
{
    var chk = document.getElementsByName("chk[]");
    var is_checked = false;
    
    for(var i=0; i<chk.length; i++) {
        if(chk[i].checked) {
            is_checked = true;
            break;
        }
    }
    
    if(document.pressed != "승인대기 전체삭제") {
        if (!is_checked) {
            alert(document.pressed+" 하실 항목을 하나 이상 선택하세요.");
            return false;
        }
    }

    if(document.pressed == "선택삭제") {
        if(!confirm("선택한 자료를 정말 삭제하시겠습니까?")) {
            return false;
        }
    }

    if(document.pressed == "선택승인") {
        if(!confirm("선택한 회원을 승인하시겠습니까?")) {
            return false;
        }
    }

    if(document.pressed == "선택거부") {
        if(!confirm("선택한 회원의 승인을 거부하시겠습니까?")) {
            return false;
        }
    }

    if(document.pressed == "승인대기 전체삭제") {
        if(!confirm("정말로 승인대기 중인 모든 회원을 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.")) {
            return false;
        }
    }

    f.action = "./member_type_update.php";
    return true;
}

function viewCert(url) {
    document.getElementById('certImg').src = url;
    document.getElementById('certModal').style.display = 'block';
}

function closeCert() {
    document.getElementById('certModal').style.display = 'none';
}
</script>

<?php
include_once('./admin.tail.php');
?>