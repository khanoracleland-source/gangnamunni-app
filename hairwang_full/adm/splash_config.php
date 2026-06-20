<?php
$sub_menu = "100590";
include_once('./_common.php');
include_once('./admin.head.php');

// 스플래시 설정 테이블 생성 (Lottie 및 카운트다운 표시 필드 추가)
$sql = "CREATE TABLE IF NOT EXISTS `g5_splash_config` (
    `sp_id` int(11) NOT NULL AUTO_INCREMENT,
    `sp_use` tinyint(1) NOT NULL DEFAULT '0' COMMENT '사용여부',
    `sp_duration` int(11) NOT NULL DEFAULT '3' COMMENT '표시시간(초)',
    `sp_type` varchar(10) NOT NULL DEFAULT 'image' COMMENT '타입(image/lottie)',
    `sp_image_pc` varchar(255) NOT NULL DEFAULT '' COMMENT 'PC 이미지',
    `sp_image_mobile` varchar(255) NOT NULL DEFAULT '' COMMENT '모바일 이미지',
    `sp_lottie_pc` varchar(255) NOT NULL DEFAULT '' COMMENT 'PC Lottie 파일',
    `sp_lottie_mobile` varchar(255) NOT NULL DEFAULT '' COMMENT '모바일 Lottie 파일',
    `sp_link_url` varchar(255) NOT NULL DEFAULT '' COMMENT '클릭 시 이동 URL',
    `sp_link_target` varchar(10) NOT NULL DEFAULT '_self' COMMENT '링크 타겟',
    `sp_start_date` date DEFAULT NULL COMMENT '시작일',
    `sp_end_date` date DEFAULT NULL COMMENT '종료일',
    `sp_skip_today` tinyint(1) NOT NULL DEFAULT '1' COMMENT '오늘 하루 보지 않기 사용',
    `sp_show_countdown` tinyint(1) NOT NULL DEFAULT '1' COMMENT '남은 시간 표시',
    `sp_bgcolor` varchar(7) NOT NULL DEFAULT '#ffffff' COMMENT '배경색',
    `sp_position` varchar(20) NOT NULL DEFAULT 'center' COMMENT '이미지 위치',
    `sp_pc_width` varchar(10) NOT NULL DEFAULT 'auto' COMMENT 'PC 이미지 너비',
    `sp_pc_height` varchar(10) NOT NULL DEFAULT 'auto' COMMENT 'PC 이미지 높이',
    `sp_mobile_width` varchar(10) NOT NULL DEFAULT 'auto' COMMENT '모바일 이미지 너비',
    `sp_mobile_height` varchar(10) NOT NULL DEFAULT 'auto' COMMENT '모바일 이미지 높이',
    `sp_pc_top` varchar(10) NOT NULL DEFAULT '50' COMMENT 'PC 상단 위치(%)',
    `sp_pc_left` varchar(10) NOT NULL DEFAULT '50' COMMENT 'PC 좌측 위치(%)',
    `sp_mobile_top` varchar(10) NOT NULL DEFAULT '50' COMMENT '모바일 상단 위치(%)',
    `sp_mobile_left` varchar(10) NOT NULL DEFAULT '50' COMMENT '모바일 좌측 위치(%)',
    `sp_datetime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    PRIMARY KEY (`sp_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8";
sql_query($sql, false);

// 기존 테이블에 새 컬럼 추가
$columns = sql_fetch("SHOW COLUMNS FROM g5_splash_config LIKE 'sp_type'");
if(!$columns) {
    sql_query("ALTER TABLE g5_splash_config ADD `sp_type` varchar(10) NOT NULL DEFAULT 'image' AFTER `sp_duration`", false);
    sql_query("ALTER TABLE g5_splash_config ADD `sp_lottie_pc` varchar(255) NOT NULL DEFAULT '' AFTER `sp_image_mobile`", false);
    sql_query("ALTER TABLE g5_splash_config ADD `sp_lottie_mobile` varchar(255) NOT NULL DEFAULT '' AFTER `sp_lottie_pc`", false);
    sql_query("ALTER TABLE g5_splash_config ADD `sp_show_countdown` tinyint(1) NOT NULL DEFAULT '1' AFTER `sp_skip_today`", false);
}

// 기본 설정 가져오기
$sql = "SELECT * FROM g5_splash_config LIMIT 1";
$splash = sql_fetch($sql);

if (!$splash) {
    // 기본 설정 생성
    $sql = "INSERT INTO g5_splash_config SET sp_datetime = '".G5_TIME_YMDHIS."'";
    sql_query($sql);
    $splash = sql_fetch("SELECT * FROM g5_splash_config LIMIT 1");
}

// 업로드 디렉토리
$splash_dir = G5_DATA_PATH.'/splash';
$splash_url = G5_DATA_URL.'/splash';

// 디렉토리 생성
@mkdir($splash_dir, G5_DIR_PERMISSION);
@chmod($splash_dir, G5_DIR_PERMISSION);
?>

<style>
#container{margin-top:30px;}
.frm_tbl th { width: 200px; padding: 10px; background: #f8f9fa; border: 1px solid #ddd; text-align: left; }
.frm_tbl td { padding: 10px; border: 1px solid #ddd; }
.frm_input { padding: 5px; border: 1px solid #ddd; }
.frm_info { font-size: 12px; color: #666; margin-top: 5px; }
.preview_box { margin-top: 10px; padding: 10px; border: 1px solid #ddd; background: #f5f5f5; }
.preview_box img { max-width: 200px; height: auto; }
.btn_submit { padding: 10px 20px; background: #337ab7; color: white; border: none; cursor: pointer; }
.btn_submit:hover { background: #286090; }

/* 위치 설정 관련 스타일 */
.position_grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 10px 0; }
.position_btn { 
    padding: 10px; 
    text-align: center; 
    border: 2px solid #ddd; 
    cursor: pointer; 
    background: #f5f5f5;
    transition: all 0.3s;
}
.position_btn:hover { background: #e0e0e0; }
.position_btn.active { 
    background: #337ab7; 
    color: white; 
    border-color: #337ab7; 
}
.size_inputs { display: flex; gap: 10px; align-items: center; margin: 10px 0; }
.custom_position { display: none; margin-top: 10px; }
.custom_position.active { display: block; }

/* 타입 선택 스타일 */
.type_selector { margin: 10px 0; }
.type_selector label { 
    margin-right: 20px; 
    cursor: pointer; 
    padding: 5px 10px;
    border-radius: 5px;
    transition: all 0.3s;
}
.type_selector input[type="radio"]:checked + span {
    background: #337ab7;
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
}

/* Lottie 미리보기 */
.lottie_preview {
    width: 200px;
    height: 200px;
    background: #f5f5f5;
    border: 1px solid #ddd;
    margin-top: 10px;
}

/* 파일 타입별 표시/숨김 */
.type_image { display: none; }
.type_lottie { display: none; }
body[data-type="image"] .type_image { display: table-row; }
body[data-type="lottie"] .type_lottie { display: table-row; }
</style>

<form name="fsplash" id="fsplash" action="./splash_config_update.php" onsubmit="return fsplash_submit(this);" method="post" enctype="multipart/form-data">
<input type="hidden" name="token" value="<?php echo get_admin_token(); ?>">

<section>
    <h2 class="h2_frm">스플래시 화면 설정</h2>
    
    <div class="tbl_frm01 tbl_wrap">
        <table class="frm_tbl">
            <tbody>
                <tr>
                    <th scope="row">사용여부</th>
                    <td>
                        <label><input type="radio" name="sp_use" value="1" <?php echo $splash['sp_use'] ? 'checked' : ''; ?>> 사용</label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="sp_use" value="0" <?php echo !$splash['sp_use'] ? 'checked' : ''; ?>> 사용안함</label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">표시 시간</th>
                    <td>
                        <input type="number" name="sp_duration" value="<?php echo $splash['sp_duration']; ?>" class="frm_input" size="5" min="1" max="10"> 초
                        <p class="frm_info">스플래시 화면이 자동으로 닫히는 시간 (1~10초)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">남은 시간 표시</th>
                    <td>
                        <label><input type="radio" name="sp_show_countdown" value="1" <?php echo $splash['sp_show_countdown'] ? 'checked' : ''; ?>> 표시</label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="sp_show_countdown" value="0" <?php echo !$splash['sp_show_countdown'] ? 'checked' : ''; ?>> 표시안함</label>
                        <p class="frm_info">"X초 후 자동으로 닫힙니다" 표시 여부</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">스플래시 타입</th>
                    <td>
                        <div class="type_selector">
                            <label>
                                <input type="radio" name="sp_type" value="image" <?php echo (!$splash['sp_type'] || $splash['sp_type'] == 'image') ? 'checked' : ''; ?> onchange="changeType(this.value)">
                                <span>이미지</span>
                            </label>
                            <label>
                                <input type="radio" name="sp_type" value="lottie" <?php echo $splash['sp_type'] == 'lottie' ? 'checked' : ''; ?> onchange="changeType(this.value)">
                                <span>Lottie 애니메이션</span>
                            </label>
                        </div>
                        <p class="frm_info">이미지(JPG, PNG, GIF) 또는 Lottie 애니메이션(.lottie, .json) 선택</p>
                    </td>
                </tr>
                
                <!-- 이미지 타입 필드 -->
                <tr class="type_image">
                    <th scope="row">PC 이미지</th>
                    <td>
                        <input type="file" name="sp_image_pc" class="frm_input" accept="image/*">
                        <p class="frm_info">권장 크기: 1920 x 1080 px (JPG, PNG, GIF)</p>
                        <?php if ($splash['sp_image_pc']) { ?>
                        <div class="preview_box">
                            <img src="<?php echo $splash_url.'/'.$splash['sp_image_pc']; ?>" alt="PC 이미지">
                            <br>
                            <label><input type="checkbox" name="del_sp_image_pc" value="1"> 삭제</label>
                        </div>
                        <?php } ?>
                    </td>
                </tr>
                
                <tr class="type_image">
                    <th scope="row">모바일 이미지</th>
                    <td>
                        <input type="file" name="sp_image_mobile" class="frm_input" accept="image/*">
                        <p class="frm_info">권장 크기: 750 x 1334 px (JPG, PNG, GIF)</p>
                        <?php if ($splash['sp_image_mobile']) { ?>
                        <div class="preview_box">
                            <img src="<?php echo $splash_url.'/'.$splash['sp_image_mobile']; ?>" alt="모바일 이미지">
                            <br>
                            <label><input type="checkbox" name="del_sp_image_mobile" value="1"> 삭제</label>
                        </div>
                        <?php } ?>
                    </td>
                </tr>
                
                <!-- Lottie 타입 필드 -->
                <tr class="type_lottie">
                    <th scope="row">PC Lottie 파일</th>
                    <td>
                        <input type="file" name="sp_lottie_pc" class="frm_input" accept=".lottie,.json">
                        <p class="frm_info">Lottie 애니메이션 파일 (.lottie 또는 .json)</p>
                        <?php if ($splash['sp_lottie_pc']) { ?>
                        <div class="preview_box">
                            <div id="lottie_pc_preview" class="lottie_preview"></div>
                            <p><?php echo $splash['sp_lottie_pc']; ?></p>
                            <label><input type="checkbox" name="del_sp_lottie_pc" value="1"> 삭제</label>
                        </div>
                        <?php } ?>
                    </td>
                </tr>
                
                <tr class="type_lottie">
                    <th scope="row">모바일 Lottie 파일</th>
                    <td>
                        <input type="file" name="sp_lottie_mobile" class="frm_input" accept=".lottie,.json">
                        <p class="frm_info">Lottie 애니메이션 파일 (.lottie 또는 .json)</p>
                        <?php if ($splash['sp_lottie_mobile']) { ?>
                        <div class="preview_box">
                            <div id="lottie_mobile_preview" class="lottie_preview"></div>
                            <p><?php echo $splash['sp_lottie_mobile']; ?></p>
                            <label><input type="checkbox" name="del_sp_lottie_mobile" value="1"> 삭제</label>
                        </div>
                        <?php } ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">콘텐츠 위치</th>
                    <td>
                        <input type="hidden" name="sp_position" id="sp_position" value="<?php echo $splash['sp_position']; ?>">
                        <div class="position_grid">
                            <div class="position_btn" data-position="top-left">↖ 왼쪽 상단</div>
                            <div class="position_btn" data-position="top-center">↑ 중앙 상단</div>
                            <div class="position_btn" data-position="top-right">↗ 오른쪽 상단</div>
                            <div class="position_btn" data-position="center-left">← 왼쪽 중앙</div>
                            <div class="position_btn" data-position="center">● 정중앙</div>
                            <div class="position_btn" data-position="center-right">→ 오른쪽 중앙</div>
                            <div class="position_btn" data-position="bottom-left">↙ 왼쪽 하단</div>
                            <div class="position_btn" data-position="bottom-center">↓ 중앙 하단</div>
                            <div class="position_btn" data-position="bottom-right">↘ 오른쪽 하단</div>
                        </div>
                        <div style="margin-top: 10px;">
                            <label><input type="checkbox" id="custom_position_check"> 사용자 정의 위치</label>
                        </div>
                        <div class="custom_position" id="custom_position_div">
                            <h4>PC 위치 설정</h4>
                            <div class="size_inputs">
                                <label>상단 위치: <input type="number" name="sp_pc_top" value="<?php echo $splash['sp_pc_top']; ?>" class="frm_input" style="width:60px" min="0" max="100">%</label>
                                <label>좌측 위치: <input type="number" name="sp_pc_left" value="<?php echo $splash['sp_pc_left']; ?>" class="frm_input" style="width:60px" min="0" max="100">%</label>
                            </div>
                            <h4>모바일 위치 설정</h4>
                            <div class="size_inputs">
                                <label>상단 위치: <input type="number" name="sp_mobile_top" value="<?php echo $splash['sp_mobile_top']; ?>" class="frm_input" style="width:60px" min="0" max="100">%</label>
                                <label>좌측 위치: <input type="number" name="sp_mobile_left" value="<?php echo $splash['sp_mobile_left']; ?>" class="frm_input" style="width:60px" min="0" max="100">%</label>
                            </div>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">콘텐츠 크기</th>
                    <td>
                        <h4>PC 크기</h4>
                        <div style="margin-bottom: 10px;">
                            <label style="margin-right: 10px;"><input type="radio" name="pc_size_preset" value="small" onclick="setPCSize('400px', '400px')"> 작게 (400x400)</label>
                            <label style="margin-right: 10px;"><input type="radio" name="pc_size_preset" value="medium" onclick="setPCSize('600px', '600px')"> 중간 (600x600)</label>
                            <label style="margin-right: 10px;"><input type="radio" name="pc_size_preset" value="large" onclick="setPCSize('800px', '800px')"> 크게 (800x800)</label>
                            <label style="margin-right: 10px;"><input type="radio" name="pc_size_preset" value="xlarge" onclick="setPCSize('1000px', '700px')"> 매우 크게 (1000x700)</label>
                            <label style="margin-right: 10px;"><input type="radio" name="pc_size_preset" value="full" onclick="setPCSize('100%', '100%')"> 전체화면</label>
                            <label><input type="radio" name="pc_size_preset" value="custom" checked> 사용자 정의</label>
                        </div>
                        <div class="size_inputs">
                            <label>너비: <input type="text" name="sp_pc_width" id="sp_pc_width" value="<?php echo $splash['sp_pc_width']; ?>" class="frm_input" style="width:100px" placeholder="auto"></label>
                            <label>높이: <input type="text" name="sp_pc_height" id="sp_pc_height" value="<?php echo $splash['sp_pc_height']; ?>" class="frm_input" style="width:100px" placeholder="auto"></label>
                            <span class="frm_info">px, %, vw, vh, auto 사용 가능</span>
                        </div>
                        
                        <h4 style="margin-top: 20px;">모바일 크기</h4>
                        <div style="margin-bottom: 10px;">
                            <label style="margin-right: 10px;"><input type="radio" name="mobile_size_preset" value="small" onclick="setMobileSize('250px', '250px')"> 작게 (250x250)</label>
                            <label style="margin-right: 10px;"><input type="radio" name="mobile_size_preset" value="medium" onclick="setMobileSize('350px', '350px')"> 중간 (350x350)</label>
                            <label style="margin-right: 10px;"><input type="radio" name="mobile_size_preset" value="large" onclick="setMobileSize('90%', '500px')"> 크게 (90%x500)</label>
                            <label style="margin-right: 10px;"><input type="radio" name="mobile_size_preset" value="full" onclick="setMobileSize('100%', '100%')"> 전체화면</label>
                            <label><input type="radio" name="mobile_size_preset" value="custom" checked> 사용자 정의</label>
                        </div>
                        <div class="size_inputs">
                            <label>너비: <input type="text" name="sp_mobile_width" id="sp_mobile_width" value="<?php echo $splash['sp_mobile_width']; ?>" class="frm_input" style="width:100px" placeholder="auto"></label>
                            <label>높이: <input type="text" name="sp_mobile_height" id="sp_mobile_height" value="<?php echo $splash['sp_mobile_height']; ?>" class="frm_input" style="width:100px" placeholder="auto"></label>
                            <span class="frm_info">px, %, vw, vh, auto 사용 가능</span>
                        </div>
                        
                        <div style="margin-top: 15px; padding: 10px; background: #f0f0f0; border-radius: 5px;">
                            <p class="frm_info" style="margin: 0;"><strong>💡 크기 설정 팁:</strong></p>
                            <p class="frm_info">• Lottie 애니메이션이 작게 보인다면 "크게" 또는 "매우 크게" 선택</p>
                            <p class="frm_info">• 반응형 크기: 너비 80%, 높이 auto</p>
                            <p class="frm_info">• 뷰포트 단위: 80vw (화면 너비의 80%), 60vh (화면 높이의 60%)</p>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">클릭 시 이동 URL</th>
                    <td>
                        <input type="text" name="sp_link_url" value="<?php echo $splash['sp_link_url']; ?>" class="frm_input" size="50">
                        <select name="sp_link_target" class="frm_input">
                            <option value="_self" <?php echo $splash['sp_link_target'] == '_self' ? 'selected' : ''; ?>>현재창</option>
                            <option value="_blank" <?php echo $splash['sp_link_target'] == '_blank' ? 'selected' : ''; ?>>새창</option>
                        </select>
                        <p class="frm_info">클릭 시 이동할 URL (비워두면 클릭 시 닫힘)</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">표시 기간</th>
                    <td>
                        시작일: <input type="date" name="sp_start_date" value="<?php echo $splash['sp_start_date']; ?>" class="frm_input">
                        ~
                        종료일: <input type="date" name="sp_end_date" value="<?php echo $splash['sp_end_date']; ?>" class="frm_input">
                        <p class="frm_info">비워두면 항상 표시됩니다.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">오늘 하루 보지 않기</th>
                    <td>
                        <label><input type="radio" name="sp_skip_today" value="1" <?php echo $splash['sp_skip_today'] ? 'checked' : ''; ?>> 사용</label>
                        &nbsp;&nbsp;
                        <label><input type="radio" name="sp_skip_today" value="0" <?php echo !$splash['sp_skip_today'] ? 'checked' : ''; ?>> 사용안함</label>
                        <p class="frm_info">"오늘 하루 보지 않기" 버튼 표시 여부</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">배경색</th>
                    <td>
                        <input type="color" name="sp_bgcolor" value="<?php echo $splash['sp_bgcolor']; ?>" class="frm_input">
                        <p class="frm_info">콘텐츠 외 영역의 배경색</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="btn_fixed_top">
        <input type="submit" value="확인" class="btn_submit btn" accesskey="s">
    </div>
</section>
</form>

<!-- Lottie Player -->
<script src="https://unpkg.com/@dotlottie/player-component@latest/dist/dotlottie-player.mjs" type="module"></script>

<script>
// 초기 타입 설정
var currentType = '<?php echo $splash['sp_type'] ?: 'image'; ?>';
document.body.setAttribute('data-type', currentType);

// 타입 변경
function changeType(type) {
    document.body.setAttribute('data-type', type);
}

// 위치 버튼 클릭 이벤트
document.querySelectorAll('.position_btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.position_btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('sp_position').value = this.dataset.position;
        document.getElementById('custom_position_check').checked = false;
        document.getElementById('custom_position_div').classList.remove('active');
    });
});

// 초기 위치 설정
const currentPosition = document.getElementById('sp_position').value || 'center';
const positionBtn = document.querySelector(`.position_btn[data-position="${currentPosition}"]`);
if (positionBtn) {
    positionBtn.classList.add('active');
} else {
    document.getElementById('custom_position_check').checked = true;
    document.getElementById('custom_position_div').classList.add('active');
    document.getElementById('sp_position').value = 'custom';
}

// 사용자 정의 위치 체크박스
document.getElementById('custom_position_check').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('custom_position_div').classList.add('active');
        document.getElementById('sp_position').value = 'custom';
        document.querySelectorAll('.position_btn').forEach(b => b.classList.remove('active'));
    } else {
        document.getElementById('custom_position_div').classList.remove('active');
        document.getElementById('sp_position').value = 'center';
        document.querySelector('.position_btn[data-position="center"]').classList.add('active');
    }
});

// Lottie 미리보기
<?php if ($splash['sp_lottie_pc']) { ?>
document.addEventListener('DOMContentLoaded', function() {
    var pcPreview = document.getElementById('lottie_pc_preview');
    if (pcPreview) {
        pcPreview.innerHTML = '<dotlottie-player src="<?php echo $splash_url.'/'.$splash['sp_lottie_pc']; ?>" background="transparent" speed="1" style="width: 100%; height: 100%;" loop autoplay></dotlottie-player>';
    }
});
<?php } ?>

<?php if ($splash['sp_lottie_mobile']) { ?>
document.addEventListener('DOMContentLoaded', function() {
    var mobilePreview = document.getElementById('lottie_mobile_preview');
    if (mobilePreview) {
        mobilePreview.innerHTML = '<dotlottie-player src="<?php echo $splash_url.'/'.$splash['sp_lottie_mobile']; ?>" background="transparent" speed="1" style="width: 100%; height: 100%;" loop autoplay></dotlottie-player>';
    }
});
<?php } ?>

// 크기 프리셋 함수
function setPCSize(width, height) {
    document.getElementById('sp_pc_width').value = width;
    document.getElementById('sp_pc_height').value = height;
}

function setMobileSize(width, height) {
    document.getElementById('sp_mobile_width').value = width;
    document.getElementById('sp_mobile_height').value = height;
}

// 현재 크기값에 따라 프리셋 라디오 버튼 체크
function checkSizePreset() {
    var pcWidth = document.getElementById('sp_pc_width').value;
    var pcHeight = document.getElementById('sp_pc_height').value;
    var mobileWidth = document.getElementById('sp_mobile_width').value;
    var mobileHeight = document.getElementById('sp_mobile_height').value;
    
    // PC 프리셋 체크
    var pcPresets = {
        '400px,400px': 'small',
        '600px,600px': 'medium',
        '800px,800px': 'large',
        '1000px,700px': 'xlarge',
        '100%,100%': 'full'
    };
    
    var pcKey = pcWidth + ',' + pcHeight;
    var pcPreset = pcPresets[pcKey] || 'custom';
    document.querySelector('input[name="pc_size_preset"][value="' + pcPreset + '"]').checked = true;
    
    // 모바일 프리셋 체크
    var mobilePresets = {
        '250px,250px': 'small',
        '350px,350px': 'medium',
        '90%,500px': 'large',
        '100%,100%': 'full'
    };
    
    var mobileKey = mobileWidth + ',' + mobileHeight;
    var mobilePreset = mobilePresets[mobileKey] || 'custom';
    document.querySelector('input[name="mobile_size_preset"][value="' + mobilePreset + '"]').checked = true;
}

// 페이지 로드 시 프리셋 체크
document.addEventListener('DOMContentLoaded', function() {
    checkSizePreset();
});

// 크기 입력 필드 변경 시 커스텀으로 변경
document.getElementById('sp_pc_width').addEventListener('input', function() {
    document.querySelector('input[name="pc_size_preset"][value="custom"]').checked = true;
});
document.getElementById('sp_pc_height').addEventListener('input', function() {
    document.querySelector('input[name="pc_size_preset"][value="custom"]').checked = true;
});
document.getElementById('sp_mobile_width').addEventListener('input', function() {
    document.querySelector('input[name="mobile_size_preset"][value="custom"]').checked = true;
});
document.getElementById('sp_mobile_height').addEventListener('input', function() {
    document.querySelector('input[name="mobile_size_preset"][value="custom"]').checked = true;
});

function fsplash_submit(f) {
    if (f.sp_use[0].checked) {
        var type = f.sp_type.value;
        
        if (type == 'image') {
            // 이미지 타입일 때
            <?php if (!$splash['sp_image_pc'] && !$splash['sp_image_mobile']) { ?>
            if (!f.sp_image_pc.value && !f.sp_image_mobile.value) {
                alert('PC 또는 모바일 이미지를 하나 이상 등록해주세요.');
                return false;
            }
            <?php } ?>
        } else if (type == 'lottie') {
            // Lottie 타입일 때
            <?php if (!$splash['sp_lottie_pc'] && !$splash['sp_lottie_mobile']) { ?>
            if (!f.sp_lottie_pc.value && !f.sp_lottie_mobile.value) {
                alert('PC 또는 모바일 Lottie 파일을 하나 이상 등록해주세요.');
                return false;
            }
            <?php } ?>
        }
    }
    
    return true;
}
</script>

<?php
include_once('./admin.tail.php');
?>