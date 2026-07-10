<?php
if (!defined('_GNUBOARD_')) exit; // 개별 페이지 접근 불가

// ========== 추천인 처리 시작 (가장 먼저 실행) ==========
$mb_recommend = '';

// 1. URL 파라미터로 추천인 ID 받기
if (isset($_GET['mb_recommend']) && !empty($_GET['mb_recommend'])) {
    $mb_recommend = trim($_GET['mb_recommend']);
    // 쿠키에 저장 (24시간 유지)
    set_cookie('reg_mb_recommend', $mb_recommend, 86400);
    // 세션에도 저장
    set_session('ss_mb_recommend', $mb_recommend);
}
// 2. 쿠키에서 확인
elseif (isset($_COOKIE['reg_mb_recommend']) && !empty($_COOKIE['reg_mb_recommend'])) {
    $mb_recommend = trim($_COOKIE['reg_mb_recommend']);
}
// 3. 세션에서 확인
elseif (get_session('ss_mb_recommend')) {
    $mb_recommend = get_session('ss_mb_recommend');
}

// 추천인 ID 정제
if($mb_recommend) {
    $mb_recommend = preg_replace("/[^a-zA-Z0-9_]/", "", $mb_recommend);
}
// ========== 추천인 처리 끝 ==========

// add_stylesheet('css 구문', 출력순서); 숫자가 작을 수록 먼저 출력됨
add_stylesheet('<link rel="stylesheet" href="'.$member_skin_url.'/style.css">', 0);

// 폼 action URL 설정 - 추천인 파라미터 포함
$register_action_url = G5_BBS_URL.'/register_form.php';
if($mb_recommend) {
    $register_action_url .= '?mb_recommend=' . urlencode($mb_recommend);
}
?>

<style>
    body, html {background-color: #f9fafb;}
    main {background-color: #f9fafb;}
    #container_title {display: none;}
    #header {display: none;}
    .contents_wrap {padding: 0px !important;}
    .sub {padding-top: 0px;}
    #rb_topvisual {display: none;}
    
    /* 회원유형 선택 스타일 추가 */
    .member_type_box {
        display: flex;
        gap: 20px;
        margin: 20px 0;
    }
    
    .member_type_item {
        flex: 1;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background: #fff;
        position: relative;
    }
    
    .member_type_item:hover {
        border-color: #7a4efe;
        box-shadow: 0 4px 12px rgba(122, 78, 254, 0.15);
    }
    
    .member_type_item input[type="radio"] {
        display: none;
    }
    
    .member_type_item input[type="radio"]:checked + .type_content {
        border-color: #7a4efe;
        background: #f8f9ff;
    }
    
    .type_content {
        border: 2px solid transparent;
        border-radius: 8px;
        padding: 30px 20px;
        transition: all 0.3s;
        min-height: 180px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }
    
    .type_icon {
        font-size: 48px;
        margin-bottom: 15px;
        color: #666;
    }
    
    .type_title {
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 10px;
        color: #333;
    }
    
    .type_desc {
        font-size: 14px;
        color: #666;
        line-height: 1.6;
    }
    
    .member_type_item input[type="radio"]:checked + .type_content .type_icon,
    .member_type_item input[type="radio"]:checked + .type_content .type_title {
        color: #7a4efe;
    }
    
    /* 추천인 안내 스타일 */
    .recommend_notice {
        background: linear-gradient(135deg, #f0e6ff 0%, #e6f7ff 100%);
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        text-align: center;
        animation: fadeIn 0.5s ease-in;
    }
    
    .recommend_notice strong {
        color: #7a4efe;
        font-size: 16px;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* 모바일 반응형 스타일 */
    @media (max-width: 768px) {
        .member_type_box {
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .member_type_item {
            flex: 0 0 calc(50% - 5px);
            max-width: calc(50% - 5px);
        }
        
        .type_content {
            min-height: 150px;
            padding: 20px 10px;
        }
        
        .type_icon {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .type_title {
            font-size: 16px;
            margin-bottom: 8px;
        }
        
        .type_desc {
            font-size: 12px;
        }
        
        /* 입점사회원이 있을 경우 3번째 버튼은 전체 너비 */
        .member_type_item:nth-child(3):last-child {
            flex: 0 0 100%;
            max-width: 100%;
        }
    }
</style>

<div class="rb_member">
    <div class="rb_login rb_reg">
       
        <form name="fregister" id="fregister" action="<?php echo $register_action_url ?>" onsubmit="return fregister_submit(this);" method="POST" autocomplete="off">
        <input type="hidden" name="w" value="">
        <input type="hidden" name="url" value="<?php echo $urlencode ?>">
        <input type="hidden" name="agree" value="<?php echo $agree ?>">
        <input type="hidden" name="agree2" value="<?php echo $agree2 ?>">
        
        <!-- 추천인 hidden 필드 추가 -->
        <?php if($mb_recommend) { ?>
        <input type="hidden" name="mb_recommend" value="<?php echo htmlspecialchars($mb_recommend); ?>">
        <?php } ?>
        
        <ul class="rb_login_box">
          
            <li class="rb_login_logo">
                <?php if (!empty($rb_builder['bu_logo_pc'])) { ?>
                    <a href="<?php echo G5_URL; ?>/gangnamunni/"><img src="<?php echo G5_URL; ?>/data/logos/pc?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>" id="logo_img"></a>
                <?php } else { ?>
                    <a href="<?php echo G5_URL; ?>/gangnamunni/"><img src="<?php echo G5_THEME_URL ?>/rb.img/logos/pc.png?ver=<?php echo G5_SERVER_TIME ?>" alt="<?php echo $config['cf_title']; ?>" id="logo_img"></a>
                <?php } ?>
            </li>
            
            <?php if($mb_recommend) { ?>
            <!-- 추천인이 있을 때 안내 메시지 -->
            <li class="recommend_notice">
                🎉 <strong><?php echo htmlspecialchars($mb_recommend); ?>님</strong>의 추천으로 가입을 진행합니다!
            </li>
            <?php } ?>
            
            <li class="rb_reg_sub_title">안녕하세요! <?php echo $config['cf_title'] ?> 에 오신것을 진심으로 환영해요!<br>회원 유형을 선택해주세요.</li>
           
            <?php if($config['cf_social_login_use'] == 1) { ?>
            <li class="sns_reg_wrap">
                <span class="sns_titles">SNS로 간편하게 가입하기</span>
                <?php
                // 소셜로그인 사용시 소셜로그인 버튼
                @include_once(get_social_skin_path().'/social_register.skin.php');
                ?>
            </li>
            <?php } ?>
           
            <li>
                <span style="font-size: 18px; font-weight: bold; margin-bottom: 20px; display: block;">회원유형 선택</span>
                
                <div class="member_type_box">
                    <label class="member_type_item">
                        <input type="radio" name="mb_type" value="general" id="mem_general" required checked>
                        <div class="type_content">
                            <div class="type_icon">👤</div>
                            <div class="type_title">일반사용자</div>
                            <div class="type_desc">
                                성형/피부 시술에 관심있는 분<br>
                                누구나 가입 가능
                            </div>
                        </div>
                    </label>

                    <label class="member_type_item">
                        <input type="radio" name="mb_type" value="hospital" id="mem_hospital" required>
                        <div class="type_content">
                            <div class="type_icon">🏥</div>
                            <div class="type_title">병원관계자</div>
                            <div class="type_desc">
                                성형외과/피부과 관계자<br>
                                병원 인증 필요
                            </div>
                        </div>
                    </label>
                    
                    <?php if(isset($pa['pa_is']) && $pa['pa_is'] == 1 && isset($pa['pa_use']) && $pa['pa_use'] == 1) { ?>
                    <label class="member_type_item">
                        <input type="radio" name="mb_type" value="partner" id="mem_partner" required>
                        <div class="type_content">
                            <div class="type_icon">🏪</div>
                            <div class="type_title">입점사회원</div>
                            <div class="type_desc">
                                판매자 등록<br>
                                사업자 정보 필요
                            </div>
                        </div>
                    </label>
                    <?php } ?>
                </div>
            </li>
            
            <li>
                <span>회원가입약관</span>
                <textarea readonly class="textarea"><?php echo get_text($config['cf_stipulation']) ?></textarea>
                <div class="mt-10">
                    <input type="checkbox" name="agree11" value="1" id="agree11">
                    <label for="agree11">회원가입약관의 내용에 동의합니다.</label>
                </div>
            </li>
            <li>
                <span>개인정보 수집 및 이용정책</span>
                <textarea readonly class="textarea"><?php echo get_text($config['cf_privacy']) ?></textarea>
                <div class="mt-10">
                    <input type="checkbox" name="agree21" value="1" id="agree21">
                    <label for="agree21">개인정보 수집 및 이용정책의 내용에 동의합니다.</label>
                </div>
            </li>
            
            <li>
                <div id="fregister_chkall" class="chk_all">
                    <input type="checkbox" name="chk_all" id="chk_all">
                    <label for="chk_all">회원가입 약관에 모두 동의합니다</label>
                </div>
            </li>
            
            <li>
            <div class="btn_confirm">
                <button type="submit" class="btn_submit font-B" onclick="return regNextStep();">다음 단계로</button>
            </div>
            </li>
            
            <li class="join_links">
                나중에 가입할래요.　<a href="<?php echo G5_URL; ?>/gangnamunni/" class="font-B">회원가입 취소</a>
            </li>
            
        </ul>
        </form>
        
    </div>
</div>

<script>
    // 추천인 정보 전달을 위한 JavaScript
    $(document).ready(function() {
        var mbRecommend = '<?php echo $mb_recommend; ?>';
        
        if(mbRecommend) {
            console.log('추천인 확인 (약관 페이지): ' + mbRecommend);
            
            // 쿠키 설정 함수
            function setCookie(name, value, days) {
                var expires = "";
                if (days) {
                    var date = new Date();
                    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                    expires = "; expires=" + date.toUTCString();
                }
                document.cookie = name + "=" + (value || "") + expires + "; path=/";
            }
            
            // 쿠키에 저장
            setCookie('reg_mb_recommend', mbRecommend, 1);
            
            // 폼 제출 시 처리
            $('#fregister').on('submit', function() {
                var action = $(this).attr('action');
                var memberType = $('input[name="mb_type"]:checked').val();
                
                // URL 파라미터 구성
                var params = [];
                
                // 추천인 파라미터 추가
                if(action.indexOf('mb_recommend=') === -1 && mbRecommend) {
                    params.push('mb_recommend=' + encodeURIComponent(mbRecommend));
                }
                
                // 회원유형 파라미터 추가
                if(memberType) {
                    params.push('mb_type=' + encodeURIComponent(memberType));
                }
                
                // URL에 파라미터 추가
                if(params.length > 0) {
                    var separator = action.indexOf('?') === -1 ? '?' : '&';
                    $(this).attr('action', action + separator + params.join('&'));
                }
                
                console.log('폼 제출 - 최종 Action URL: ' + $(this).attr('action'));
            });
        }
    });

    // '다음 단계로' 버튼 클릭 시 가장 먼저 실행 — 병원관계자면 폼 제출(register_form.php) 대신
    // 병원 등록 신청 페이지로 즉시 이동한다. onsubmit 체인·약관 검증에 의존하지 않아 가장 안정적이다.
    function regNextStep() {
        var checked = document.querySelector('input[name="mb_type"]:checked');
        if (!checked) {
            alert("회원 유형을 선택해주세요.");
            return false;
        }
        if (checked.value === 'hospital') {
            location.href = '<?php echo G5_URL; ?>/gangnamunni/hospital_apply_public.php?app=1';
            return false; // 폼 제출 취소
        }
        return true; // 일반 회원 → 기존 제출 로직(fregister_submit) 진행
    }

    function fregister_submit(f)
    {
        // 회원유형 선택 확인
        if (!f.mb_type.value) {
            alert("회원 유형을 선택해주세요.");
            return false;
        }

        // 병원관계자: 일반 회원가입 대신 병원 등록 신청(앱 친화) 페이지로 이동. (버튼 onclick 이 우선 처리)
        if (f.mb_type.value === 'hospital') {
            location.href = '<?php echo G5_URL; ?>/gangnamunni/hospital_apply_public.php?app=1';
            return false;
        }

        if (!f.agree11.checked) {
            alert("회원가입약관의 내용에 동의하셔야 회원가입 하실 수 있습니다.");
            f.agree11.focus();
            return false;
        }

        if (!f.agree21.checked) {
            alert("개인정보 수집 및 이용의 내용에 동의하셔야 회원가입 하실 수 있습니다.");
            f.agree21.focus();
            return false;
        }
        
        // 동의 값 설정
        f.agree.value = '1';
        f.agree2.value = '1';

        return true;
    }
    
    jQuery(function($){
        // 모두선택
        $("input[name=chk_all]").click(function() {
            if ($(this).prop('checked')) {
                $("input[name=agree11]").prop('checked', true);
                $("input[name=agree21]").prop('checked', true);
            } else {
                $("input[name=agree11]").prop("checked", false);
                $("input[name=agree21]").prop("checked", false);
            }
        });
        
        // 회원유형 선택 시 시각적 피드백
        $("input[name=mb_type]").change(function() {
            $(".member_type_item").removeClass("selected");
            if ($(this).is(":checked")) {
                $(this).closest(".member_type_item").addClass("selected");
            }
        });
        
        // 기본값으로 일반사용자 선택
        $("#mem_general").trigger('change');
    });
</script>