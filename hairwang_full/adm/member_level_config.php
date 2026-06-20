<?php
$sub_menu = "200400";
include_once('./_common.php');

// auth 배열에 키가 있는지 확인 후 권한 체크
if(isset($auth[$sub_menu])) {
    auth_check($auth[$sub_menu], 'r');
} else {
    // 권한이 정의되지 않은 경우 관리자 권한 확인
    if($member['mb_level'] < 10) {
        alert('접근 권한이 없습니다.');
    }
}

if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

$g5['title'] = '회원 활동 등급 관리';
include_once('./admin.head.php');

// 레벨 설정 테이블 이름
// 기존 member_table의 접두사를 가져와서 사용
$table_prefix = substr($g5['member_table'], 0, strpos($g5['member_table'], 'member'));
$level_table = $table_prefix . 'member_level_config';

// 테이블 생성 확인 및 생성
$table_exists = sql_fetch("SHOW TABLES LIKE '{$level_table}'");
if(!$table_exists) {
    // 테이블 생성
    sql_query("
        CREATE TABLE IF NOT EXISTS `{$level_table}` (
          `level_no` int(11) NOT NULL,
          `level_name` varchar(50) NOT NULL DEFAULT '',
          `level_posts` int(11) NOT NULL DEFAULT '0',
          `level_comments` int(11) NOT NULL DEFAULT '0',
          `level_likes` int(11) NOT NULL DEFAULT '0',
          `level_desc` varchar(255) NOT NULL DEFAULT '',
          PRIMARY KEY (`level_no`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8
    ");
    
    // 기본 데이터 삽입
    $default_data = array(
        1 => array('비회원', 0, 0, 0, '이제 막 시작한 새싹 디자이너'),
        2 => array('헤린이', 10, 20, 20, '첫 활동을 시작한 신입 활동러'),
        3 => array('루키 스타', 20, 40, 50, '활발하게 활동하는 인기 디자이너'),
        4 => array('슈퍼 스타', 30, 60, 70, '커뮤니티 고수, 실력자'),
        5 => array('빡고수', 40, 80, 100, '전설의 활동왕, 모두가 인정'),
        6 => array('신의손', 0, 0, 0, '특별 권한 회원'),
        7 => array('특별회원', 0, 0, 0, '명예 회원'),
        8 => array('골드회원', 0, 0, 0, '골드 등급 회원'),
        9 => array('다이아회원', 0, 0, 0, '다이아몬드 등급 회원'),
        10 => array('최고관리자', 0, 0, 0, '사이트 관리자')
    );
    
    foreach($default_data as $level => $data) {
        sql_query("
            INSERT INTO `{$level_table}` 
            (level_no, level_name, level_posts, level_comments, level_likes, level_desc) 
            VALUES 
            ({$level}, '{$data[0]}', {$data[1]}, {$data[2]}, {$data[3]}, '{$data[4]}')
        ");
    }
}

// 레벨 아이콘 디렉토리 생성
$level_icon_dir = G5_DATA_PATH.'/member_level';
if(!is_dir($level_icon_dir)) {
    @mkdir($level_icon_dir, G5_DIR_PERMISSION);
    @chmod($level_icon_dir, G5_DIR_PERMISSION);
}

// 현재 회원 수 레벨별 통계
$level_stats = array();
for($i=1; $i<=10; $i++) {
    $row = sql_fetch("SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_level = '{$i}'");
    $level_stats[$i] = $row['cnt'];
}

// 기본값 배열 정의
$default_requirements = array(
    1 => array('name' => '비회원', 'posts' => 0, 'comments' => 0, 'likes' => 0, 'desc' => '이제 막 시작한 새싹 디자이너'),
    2 => array('name' => '헤린이', 'posts' => 10, 'comments' => 20, 'likes' => 20, 'desc' => '첫 활동을 시작한 신입 활동러'),
    3 => array('name' => '루키 스타', 'posts' => 20, 'comments' => 40, 'likes' => 50, 'desc' => '활발하게 활동하는 인기 디자이너'),
    4 => array('name' => '슈퍼 스타', 'posts' => 30, 'comments' => 60, 'likes' => 70, 'desc' => '커뮤니티 고수, 실력자'),
    5 => array('name' => '빡고수', 'posts' => 40, 'comments' => 80, 'likes' => 100, 'desc' => '전설의 활동왕, 모두가 인정'),
    6 => array('name' => '신의손', 'posts' => 0, 'comments' => 0, 'likes' => 0, 'desc' => '특별 권한 회원'),
    7 => array('name' => '특별회원', 'posts' => 0, 'comments' => 0, 'likes' => 0, 'desc' => '명예 회원'),
    8 => array('name' => '골드회원', 'posts' => 0, 'comments' => 0, 'likes' => 0, 'desc' => '골드 등급 회원'),
    9 => array('name' => '다이아회원', 'posts' => 0, 'comments' => 0, 'likes' => 0, 'desc' => '다이아몬드 등급 회원'),
    10 => array('name' => '최고관리자', 'posts' => 0, 'comments' => 0, 'likes' => 0, 'desc' => '사이트 관리자')
);

// 레벨별 요구사항 - DB에서 가져오기
$level_requirements = array();
for($i=1; $i<=10; $i++) {
    $sql = "SELECT * FROM {$level_table} WHERE level_no = {$i}";
    $row = sql_fetch($sql);
    
    if($row) {
        $level_requirements[$i] = array(
            'name' => $row['level_name'],
            'posts' => $row['level_posts'],
            'comments' => $row['level_comments'],
            'likes' => $row['level_likes'],
            'desc' => $row['level_desc']
        );
    } else {
        // DB에 데이터가 없으면 기본값 사용하고 삽입
        $level_requirements[$i] = $default_requirements[$i];
        $data = $default_requirements[$i];
        sql_query("
            INSERT INTO `{$level_table}` 
            (level_no, level_name, level_posts, level_comments, level_likes, level_desc) 
            VALUES 
            ({$i}, '{$data['name']}', {$data['posts']}, {$data['comments']}, {$data['likes']}, '{$data['desc']}')
        ");
    }
}

// 설정 저장 처리
if(isset($_POST['act_button']) && $_POST['act_button'] == '설정저장') {
    check_admin_token();
    
    // 레벨별 설정 저장
    for($i=1; $i<=10; $i++) {
        $level_name = isset($_POST['level_name'][$i]) ? trim(strip_tags($_POST['level_name'][$i])) : $default_requirements[$i]['name'];
        $level_posts = isset($_POST['level_posts'][$i]) ? (int)$_POST['level_posts'][$i] : 0;
        $level_comments = isset($_POST['level_comments'][$i]) ? (int)$_POST['level_comments'][$i] : 0;
        $level_likes = isset($_POST['level_likes'][$i]) ? (int)$_POST['level_likes'][$i] : 0;
        $level_desc = isset($_POST['level_desc'][$i]) ? trim(strip_tags($_POST['level_desc'][$i])) : '';
        
        // 이스케이프 처리
        $level_name = sql_real_escape_string($level_name);
        $level_desc = sql_real_escape_string($level_desc);
        
        // UPDATE
        $sql = "UPDATE {$level_table} SET 
                level_name = '{$level_name}',
                level_posts = {$level_posts},
                level_comments = {$level_comments},
                level_likes = {$level_likes},
                level_desc = '{$level_desc}'
                WHERE level_no = {$i}";
        
        sql_query($sql);
    }
    
    // 캐시 삭제
    $cache_file = G5_DATA_PATH.'/cache/latest-*';
    foreach(glob($cache_file) as $file) {
        @unlink($file);
    }
    
    alert('레벨 설정이 저장되었습니다.', './member_level_config.php');
}

// 일괄 업데이트 처리
if(isset($_POST['act_button']) && $_POST['act_button'] == '일괄업데이트') {
    check_admin_token();
    
    include_once(G5_LIB_PATH.'/member_level.lib.php');
    $updated = update_all_member_levels();
    
    alert($updated.'명의 회원 레벨이 업데이트되었습니다.', './member_level_config.php');
}

// 아이콘 삭제 처리
if(isset($_POST['del_icon']) && is_array($_POST['del_icon'])) {
    foreach($_POST['del_icon'] as $level => $val) {
        if($val) {
            $level = (int)$level;
            if($level >= 1 && $level <= 10) {
                $extensions = array('png', 'jpg', 'jpeg', 'gif');
                foreach($extensions as $ext) {
                    $del_file = $level_icon_dir.'/level_'.$level.'.'.$ext;
                    if(file_exists($del_file)) {
                        @unlink($del_file);
                    }
                }
            }
        }
    }
}

// 아이콘 업로드 처리
if(isset($_FILES['level_icon']) && isset($_FILES['level_icon']['name']) && is_array($_FILES['level_icon']['name'])) {
    $allowed_ext = array('jpg', 'jpeg', 'png', 'gif');
    
    foreach($_FILES['level_icon']['name'] as $level => $filename) {
        if(!$filename) continue;
        
        $level = (int)$level;
        if($level < 1 || $level > 10) continue;
        
        if(!isset($_FILES['level_icon']['tmp_name'][$level]) || 
           !isset($_FILES['level_icon']['error'][$level])) {
            continue;
        }
        
        $tmp_name = $_FILES['level_icon']['tmp_name'][$level];
        $error = $_FILES['level_icon']['error'][$level];
        
        if($error != UPLOAD_ERR_OK) continue;
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if(!in_array($ext, $allowed_ext)) continue;
        
        $img_info = @getimagesize($tmp_name);
        if(!$img_info) continue;
        
        // 기존 파일 삭제
        $extensions = array('png', 'jpg', 'jpeg', 'gif');
        foreach($extensions as $old_ext) {
            $old_file = $level_icon_dir.'/level_'.$level.'.'.$old_ext;
            if(file_exists($old_file)) {
                @unlink($old_file);
            }
        }
        
        // 파일 저장
        $save_file = $level_icon_dir.'/level_'.$level.'.png';
        move_uploaded_file($tmp_name, $save_file);
        @chmod($save_file, G5_FILE_PERMISSION);
    }
}

// 모던한 색상 정의
$level_colors = array(
    1 => array('bg' => '#F0F9FF', 'border' => '#BAE6FD', 'text' => '#0369A1'),
    2 => array('bg' => '#F0FDF4', 'border' => '#BBF7D0', 'text' => '#15803D'),
    3 => array('bg' => '#FEF3C7', 'border' => '#FCD34D', 'text' => '#B45309'),
    4 => array('bg' => '#FEE2E2', 'border' => '#FCA5A5', 'text' => '#DC2626'),
    5 => array('bg' => '#EDE9FE', 'border' => '#C4B5FD', 'text' => '#7C3AED'),
    6 => array('bg' => '#FECACA', 'border' => '#F87171', 'text' => '#B91C1C'),
    7 => array('bg' => '#F3F4F6', 'border' => '#9CA3AF', 'text' => '#374151'),
    8 => array('bg' => '#E5E7EB', 'border' => '#6B7280', 'text' => '#1F2937'),
    9 => array('bg' => '#D1D5DB', 'border' => '#4B5563', 'text' => '#111827'),
    10 => array('bg' => '#1F2937', 'border' => '#111827', 'text' => '#FFFFFF')
);

add_stylesheet('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">', 0);
?>

<style>
/* 기존 스타일 유지 */
* {
    box-sizing: border-box;
}

body {
    background-color: #f8fafc;
}

.level-container {
    margin: 0 auto;
    padding: 20px;
}

.hero-section {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    padding: 60px 50px;
    border-radius: 24px;
    margin-bottom: 40px;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 500px;
    height: 500px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.hero-content {
    position: relative;
    z-index: 1;
}

.hero-section h1 {
    color: white;
    font-size: 36px;
    font-weight: 800;
    margin-bottom: 16px;
    letter-spacing: -0.5px;
}

.hero-section p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 18px;
    line-height: 1.6;
    max-width: 700px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 24px;
    text-align: center;
    border: 2px solid transparent;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--gradient-start), var(--gradient-end));
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08);
}

.stat-icon {
    width: 56px;
    height: 56px;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 16px;
    font-size: 24px;
}

.stat-number {
    font-size: 32px;
    font-weight: 800;
    margin-bottom: 4px;
    letter-spacing: -1px;
}

.stat-label {
    font-size: 16px;
    font-weight: 600;
}

.section-card {
    background: white;
    border-radius: 24px;
    overflow: hidden;
    margin-bottom: 32px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
}

.section-header {
    background: #1e293b;
    padding: 24px 32px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-header h2 {
    color: white;
    font-size: 20px;
    font-weight: 700;
    margin: 0;
}

.section-header i {
    color: #94a3b8;
    font-size: 20px;
}

.modern-table {
    width: 100%;
    border-collapse: collapse;
}

.modern-table thead th {
    background: #f8fafc;
    padding: 12px 15px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
    color: #475569;
    border-bottom: 1px solid #e2e8f0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.modern-table tbody td {
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.modern-table tbody tr:hover {
    background: #f8fafc;
}

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
}

.level-icon-box {
    width: 48px;
    height: 48px;
    background: #f1f5f9;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.level-icon-box img {
    max-width: 32px;
    max-height: 32px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(100px, 1fr));
    gap: 12px;
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-label {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-input {
    padding: 10px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s;
    background: #f8fafc;
}

.form-input:focus {
    outline: none;
    border-color: #6366f1;
    background: white;
}

.icon-upload-group {
    display: flex;
    align-items: center;
    gap: 16px;
}

.current-icon-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 12px;
    font-size: 14px;
}

.upload-input {
    position: relative;
    display: inline-block;
}

.upload-input input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    cursor: pointer;
}

.upload-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #f1f5f9;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s;
}

.upload-label:hover {
    background: #e2e8f0;
}

.member-count {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    font-weight: 800;
}

.action-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
    margin-top: 40px;
}

.btn {
    padding: 14px 32px;
    border: none;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(99, 102, 241, 0.3);
}

.btn-secondary {
    background: #e2e8f0;
    color: #475569;
}

.btn-secondary:hover {
    background: #cbd5e1;
}

.info-box {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-radius: 20px;
    padding: 32px;
    margin-top: 40px;
    border: 1px solid #e2e8f0;
}

.info-box h3 {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-box ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-box li {
    padding: 8px 0;
    color: #64748b;
    font-size: 15px;
    line-height: 1.6;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.info-box li::before {
    content: '✓';
    color: #10b981;
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
}

@media (max-width: 1400px) {
    .form-grid {
        grid-template-columns: repeat(3, minmax(80px, 1fr));
        gap: 10px;
    }
    
    .form-label {
        font-size: 12px;
    }
    
    .form-input {
        padding: 8px 10px;
        font-size: 13px;
    }
    
    .modern-table tbody td {
        padding: 14px 16px;
    }
    
    .level-badge {
        font-size: 13px;
        padding: 6px 12px;
    }
}

@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .hero-section {
        padding: 40px 30px;
    }
    
    .hero-section h1 {
        font-size: 28px;
    }
}
</style>

<div class="level-container">
    <div class="hero-section">
        <div class="hero-content">
            <h1>🎯 헤어왕 커뮤니티 활동 등급 시스템</h1>
            <p>
                회원들의 활동을 자동으로 추적하고 보상하는 스마트한 등급 시스템입니다.<br>
                게시글, 댓글, 좋아요 활동에 따라 헤린이부터 신의손까지 자동으로 레벨업됩니다.
            </p>
        </div>
    </div>

    <div class="stats-grid">
        <?php for($i=1; $i<=6; $i++) { 
            $req = $level_requirements[$i];
            $colors = $level_colors[$i];
        ?>
        <div class="stat-card" style="--gradient-start: <?php echo $colors['border'] ?>; --gradient-end: <?php echo $colors['text'] ?>;">
            <div class="stat-icon" style="background: <?php echo $colors['bg'] ?>; color: <?php echo $colors['text'] ?>;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number" style="color: <?php echo $colors['text'] ?>;">
                <?php echo number_format($level_stats[$i]) ?>
            </div>
            <div class="stat-label" style="color: <?php echo $colors['text'] ?>;">
                <?php echo $req['name'] ?>
            </div>
        </div>
        <?php } ?>
    </div>

    <!-- 폼 시작 -->
    <form name="flevelconfig" method="post" action="" enctype="multipart/form-data">
    <input type="hidden" name="token" value="<?php echo get_admin_token(); ?>">

    <div class="section-card">
        <div class="section-header">
            <i class="fas fa-sliders-h"></i>
            <h2>활동 등급 기준 설정</h2>
        </div>
        <table class="modern-table">
            <thead>
                <tr>
                    <th width="140">Level</th>
                    <th width="180">등급명</th>
                    <th width="80">아이콘</th>
                    <th>조건 설정</th>
                    <th width="100">현재 인원</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // 모든 레벨 1-10 표시
                for($i=1; $i<=10; $i++) { 
                    $req = $level_requirements[$i];
                    $colors = $level_colors[$i];
                    $icon_file = $level_icon_dir.'/level_'.$i.'.png';
                    $icon_exists = file_exists($icon_file);
                ?>
                <tr>
                    <td>
                        <div class="level-badge" style="background: <?php echo $colors['bg'] ?>; color: <?php echo $colors['text'] ?>; border: 2px solid <?php echo $colors['border'] ?>;">
                            <i class="fas fa-star"></i> Level <?php echo $i ?>
                        </div>
                    </td>
                    <td>
                        <input type="text" name="level_name[<?php echo $i ?>]" value="<?php echo htmlspecialchars($req['name']) ?>" class="form-input" style="width: 100%;">
                    </td>
                    <td>
                        <div class="level-icon-box">
                            <?php if($icon_exists) { ?>
                                <img src="<?php echo G5_DATA_URL ?>/member_level/level_<?php echo $i ?>.png?v=<?php echo time() ?>">
                            <?php } else { ?>
                                <i class="fas fa-image" style="color: #cbd5e1; font-size: 20px;"></i>
                            <?php } ?>
                        </div>
                    </td>
                    <td>
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-edit"></i> 게시글
                                </label>
                                <input type="number" name="level_posts[<?php echo $i ?>]" value="<?php echo $req['posts'] ?>" min="0" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-comments"></i> 댓글
                                </label>
                                <input type="number" name="level_comments[<?php echo $i ?>]" value="<?php echo $req['comments'] ?>" min="0" class="form-input">
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-heart"></i> 좋아요
                                </label>
                                <input type="number" name="level_likes[<?php echo $i ?>]" value="<?php echo $req['likes'] ?>" min="0" class="form-input">
                            </div>
                            <div class="form-group full-width">
                                <label class="form-label">
                                    <i class="fas fa-info-circle"></i> 설명
                                </label>
                                <input type="text" name="level_desc[<?php echo $i ?>]" value="<?php echo htmlspecialchars($req['desc']) ?>" class="form-input">
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="member-count" style="color: <?php echo $colors['text'] ?>;">
                            <?php echo number_format($level_stats[$i]) ?>명
                        </div>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="section-card">
        <div class="section-header">
            <i class="fas fa-icons"></i>
            <h2>레벨 아이콘 관리</h2>
        </div>
        <table class="modern-table">
            <thead>
                <tr>
                    <th width="200">Level</th>
                    <th>현재 아이콘</th>
                    <th>아이콘 업로드</th>
                </tr>
            </thead>
            <tbody>
                <?php for($i=1; $i<=10; $i++) { 
                    $icon_file = $level_icon_dir.'/level_'.$i.'.png';
                    $icon_exists = file_exists($icon_file);
                    $level_name = $level_requirements[$i]['name'];
                    $colors = isset($level_colors[$i]) ? $level_colors[$i] : array('bg' => '#F3F4F6', 'border' => '#9CA3AF', 'text' => '#374151');
                ?>
                <tr>
                    <td>
                        <div class="level-badge" style="background: <?php echo $colors['bg'] ?>; color: <?php echo $colors['text'] ?>; border: 2px solid <?php echo $colors['border'] ?>;">
                            <i class="fas fa-crown"></i> Lv.<?php echo $i ?> <?php echo $level_name ?>
                        </div>
                    </td>
                    <td>
                        <div class="icon-upload-group">
                            <?php if($icon_exists) { ?>
                                <div class="current-icon-preview">
                                    <img src="<?php echo G5_DATA_URL ?>/member_level/level_<?php echo $i ?>.png?v=<?php echo time() ?>" style="width: 24px; height: 24px;">
                                    <label>
                                        <input type="checkbox" name="del_icon[<?php echo $i ?>]" value="1">
                                        삭제
                                    </label>
                                </div>
                            <?php } else { ?>
                                <span style="color: #94a3b8; font-size: 14px;">아이콘 없음</span>
                            <?php } ?>
                        </div>
                    </td>
                    <td>
                        <div class="upload-input">
                            <input type="file" name="level_icon[<?php echo $i ?>]" accept="image/*" id="icon_<?php echo $i ?>">
                            <label for="icon_<?php echo $i ?>" class="upload-label">
                                <i class="fas fa-upload"></i>
                                파일 선택
                            </label>
                        </div>
                        <span style="color: #94a3b8; font-size: 13px; margin-left: 12px;">PNG 권장 (32x32px)</span>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <div class="action-buttons">
        <button type="submit" name="act_button" value="설정저장" class="btn btn-primary">
            <i class="fas fa-save"></i>
            설정 저장하기
        </button>
        <button type="submit" name="act_button" value="일괄업데이트" class="btn btn-secondary" 
                onclick="return confirm('전체 회원의 레벨을 재계산하시겠습니까?\n\n회원 수가 많을 경우 시간이 걸릴 수 있습니다.');">
            <i class="fas fa-sync-alt"></i>
            레벨 일괄 업데이트
        </button>
    </div>

    </form>

    <div class="info-box">
        <h3><i class="fas fa-lightbulb"></i> 활동 등급 시스템 가이드</h3>
        <ul>
            <li>레벨 1~6는 회원의 활동 조건에 따라 자동으로 변경됩니다.</li>
            <li>레벨 7~10은 관리자가 수동으로 지정하는 특별 등급입니다.</li>
            <li>게시글 작성, 댓글 작성, 좋아요를 받을 때마다 실시간으로 레벨이 체크됩니다.</li>
            <li>일괄 업데이트를 실행하면 전체 회원의 레벨을 한 번에 재계산합니다.</li>
            <li>레벨은 자동으로 올라가기만 하며, 활동이 줄어도 내려가지 않습니다.</li>
            <li>레벨업 시 회원에게 축하 쪽지가 자동으로 발송됩니다.</li>
        </ul>
    </div>
</div>

<?php
include_once('./admin.tail.php');
?>