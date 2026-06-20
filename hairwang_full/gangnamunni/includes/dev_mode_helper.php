<?php
/**
 * 개발모드 헬퍼 — 테스트 데이터 자동 입력 시스템
 * dev_mode='on' 일 때만 테스트 버튼 노출
 * @created 2026-04-05
 */

// 개발모드 여부 확인 (캐싱)
function isDevMode($connect) {
    static $cached = null;
    if ($cached !== null) return $cached;

    $stmt = mysqli_prepare($connect, "SELECT setting_value FROM gangnamunni_settings WHERE setting_key='dev_mode' LIMIT 1");
    if (!$stmt) return false;
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? $row = mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    $cached = ($row && $row['setting_value'] === 'on');
    return $cached;
}

// 테스트 데이터 채우기 버튼 렌더링
function renderDevModeButton($type, $label = null) {
    if (!$label) {
        $labels = [
            'hospital_apply' => '테스트 병원 채우기',
            'booking' => '테스트 예약 채우기',
            'review' => '테스트 후기 채우기',
            'ad_wizard' => '테스트 광고 채우기',
            'event' => '테스트 이벤트 채우기',
        ];
        $label = $labels[$type] ?? '테스트 데이터 채우기';
    }

    echo '<div class="dev-mode-bar mb-3 p-2 rounded" style="background:linear-gradient(135deg,#ff6b35,#f7931e);border:2px dashed #fff;">';
    echo '<div class="d-flex align-items-center justify-content-between">';
    echo '<span class="text-white fw-bold"><i class="bi bi-gear-fill me-1"></i> DEV MODE</span>';
    echo '<button type="button" class="btn btn-light btn-sm fw-bold" onclick="fillTestData_' . $type . '()">';
    echo '<i class="bi bi-lightning-fill text-warning me-1"></i>' . htmlspecialchars($label);
    echo '</button>';
    echo '</div></div>';
}

// 병원 신청 폼 테스트 데이터 JS
function getDevModeJS_hospital_apply() {
    return <<<'JS'
<script>
function fillTestData_hospital_apply() {
    const surnames = ['김','이','박','최','정','강','조','윤','장','임'];
    const names = ['민수','서연','지훈','하은','준혁','수빈','도윤','예은','시우','다은'];
    const areas = ['강남구 역삼동','강남구 삼성동','서초구 서초동','송파구 잠실동','마포구 합정동','강남구 청담동','강남구 논현동','서초구 반포동'];
    const specialties = ['성형외과','피부과','안과','치과','산부인과','정형외과','내과','비뇨기과'];
    const treatments = ['쌍꺼풀','코성형','리프팅','보톡스','필러','레이저','지방흡입','가슴성형','눈밑지방','턱보톡스','피부관리','여드름'];

    const rnd = (arr) => arr[Math.floor(Math.random()*arr.length)];
    const rndNum = (min,max) => Math.floor(Math.random()*(max-min+1))+min;
    const idx = rndNum(1,999);

    const specialty = rnd(specialties);
    const ownerName = rnd(surnames) + rnd(names);
    const contactName = rnd(surnames) + rnd(names);

    // 필드 채우기
    const fields = {
        'hospital_name': '데모 ' + rnd(areas).split(' ')[1].replace('동','') + specialty + ' #' + idx,
        'owner_name': ownerName,
        'business_number': rndNum(100,999)+'-'+String(rndNum(10,99))+'-'+String(rndNum(10000,99999)),
        'phone': '02-'+rndNum(1000,9999)+'-'+rndNum(1000,9999),
        'email': 'demo_hospital_'+idx+'@test.com',
        'contact_name': contactName,
        'contact_phone': '010-'+rndNum(1000,9999)+'-'+rndNum(1000,9999),
        'address': '서울특별시 '+rnd(areas)+' '+rndNum(1,300)+'번지',
        'website': 'https://demo-hospital-'+idx+'.example.com',
        'sns_url': 'https://instagram.com/demo_hospital_'+idx,
        'description': '안녕하세요, 데모 테스트 병원입니다. '+specialty+' 전문으로 '+rndNum(5,30)+'년 경력의 의료진이 최신 시설에서 진료합니다. 상담 예약을 환영합니다.'
    };

    for (const [name, val] of Object.entries(fields)) {
        const el = document.querySelector('[name="'+name+'"]');
        if (el) { el.value = val; el.dispatchEvent(new Event('input', {bubbles:true})); }
    }

    // 카테고리 체크박스 (랜덤 2~3개)
    const catBoxes = document.querySelectorAll('input[name="categories[]"], .category-checkbox, [data-category]');
    if (catBoxes.length) {
        const picks = new Set();
        while(picks.size < Math.min(3, catBoxes.length)) picks.add(rndNum(0, catBoxes.length-1));
        picks.forEach(i => { catBoxes[i].checked = true; catBoxes[i].dispatchEvent(new Event('change',{bubbles:true})); });
    }

    // 시술 태그 (랜덤 3~5개)
    const tagInput = document.querySelector('[name="treatment_tags"]');
    if (tagInput) {
        const picked = [];
        const cnt = rndNum(3,5);
        for(let i=0;i<cnt;i++) picked.push(rnd(treatments));
        tagInput.value = [...new Set(picked)].join(',');
        tagInput.dispatchEvent(new Event('input',{bubbles:true}));
    }

    // 진료시간
    const bhInput = document.querySelector('[name="business_hours"]');
    if (bhInput) {
        bhInput.value = JSON.stringify({mon:'09:00-18:00',tue:'09:00-18:00',wed:'09:00-18:00',thu:'09:00-18:00',fri:'09:00-18:00',sat:'09:00-13:00',sun:'휴진'});
        bhInput.dispatchEvent(new Event('input',{bubbles:true}));
    }

    // 약관 동의 체크
    document.querySelectorAll('[name^="agree"]').forEach(cb => { cb.checked = true; cb.dispatchEvent(new Event('change',{bubbles:true})); });

    alert('테스트 데이터가 입력되었습니다. 확인 후 신청 버튼을 클릭하세요.');
}
</script>
JS;
}

// 예약 폼 테스트 데이터 JS
function getDevModeJS_booking() {
    return <<<'JS'
<script>
function fillTestData_booking() {
    const surnames = ['김','이','박','최','정','강','조','윤'];
    const names = ['민수','서연','지훈','하은','준혁','수빈','도윤','예은'];
    const treatments = ['쌍꺼풀 수술','코성형 상담','보톡스 시술','필러 상담','리프팅 상담','레이저 토닝','피부관리'];
    const rnd = (arr) => arr[Math.floor(Math.random()*arr.length)];
    const rndNum = (min,max) => Math.floor(Math.random()*(max-min+1))+min;

    // 내일 날짜
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate()+1);
    const dateStr = tomorrow.toISOString().split('T')[0];
    const timeStr = String(rndNum(9,17)).padStart(2,'0')+':00';

    const fields = {
        'name': rnd(surnames)+rnd(names),
        'phone': '010-'+rndNum(1000,9999)+'-'+rndNum(1000,9999),
        'date': dateStr,
        'time': timeStr,
        'memo': '데모 테스트 예약입니다. '+rnd(treatments)+' 관련 상담 희망합니다.'
    };

    for (const [name, val] of Object.entries(fields)) {
        const el = document.querySelector('[name="'+name+'"]');
        if (el) { el.value = val; el.dispatchEvent(new Event('input',{bubbles:true})); }
    }

    // 시술 선택
    const treatInput = document.querySelector('[name="treatment"]');
    if (treatInput) { treatInput.value = rnd(treatments); treatInput.dispatchEvent(new Event('change',{bubbles:true})); }

    // 병원 select
    const hospSelect = document.querySelector('[name="hospital_id"], select[name="hospital"]');
    if (hospSelect && hospSelect.options.length > 1) {
        hospSelect.selectedIndex = rndNum(1, Math.min(hospSelect.options.length-1, 5));
        hospSelect.dispatchEvent(new Event('change',{bubbles:true}));
    }

    alert('테스트 예약 데이터가 입력되었습니다.');
}
</script>
JS;
}

// 후기 작성 테스트 데이터 JS
function getDevModeJS_review() {
    return <<<'JS'
<script>
function fillTestData_review() {
    const templates = [
        {title:'시술 후기 - 매우 만족', content:'처음 방문했는데 의료진이 매우 친절하고 시술 결과도 만족스럽습니다. 상담부터 시술까지 꼼꼼하게 설명해주셔서 불안감 없이 진행했습니다. 재방문 의사 있습니다.', rating:5},
        {title:'상담 후기 - 꼼꼼한 설명', content:'상담만 받았는데 원장님이 정말 자세하게 설명해주셨습니다. 부작용이나 주의사항까지 솔직하게 말씀해주셔서 신뢰가 갔습니다. 시술 결정 전 꼭 방문해보세요.', rating:4},
        {title:'재방문 후기 - 변함없는 실력', content:'두 번째 방문입니다. 이전에도 만족했었는데 이번에도 역시 좋았습니다. 시설도 깨끗하고 대기 시간도 짧아서 좋습니다.', rating:5},
        {title:'첫 방문 후기', content:'지인 추천으로 방문했습니다. 전반적으로 만족스러운 경험이었고, 특히 시술 후 관리 안내가 상세해서 좋았습니다. 가격도 합리적이었습니다.', rating:4},
        {title:'솔직 후기 - 추천합니다', content:'여러 병원을 비교하다가 선택했는데 결과적으로 잘한 선택이었습니다. 원장님 경력이 풍부하시고 시술도 빠르고 정확했습니다. 지인에게도 추천했습니다.', rating:5}
    ];
    const tpl = templates[Math.floor(Math.random()*templates.length)];

    // 제목/내용
    const titleEl = document.querySelector('[name="title"]');
    const contentEl = document.querySelector('[name="content"], textarea[name="content"]');
    if (titleEl) { titleEl.value = tpl.title; titleEl.dispatchEvent(new Event('input',{bubbles:true})); }
    if (contentEl) { contentEl.value = tpl.content; contentEl.dispatchEvent(new Event('input',{bubbles:true})); }

    // 별점
    const ratingInputs = document.querySelectorAll('[name="rating"]');
    ratingInputs.forEach(r => { if (parseInt(r.value) === tpl.rating) { r.checked = true; r.dispatchEvent(new Event('change',{bubbles:true})); }});
    // 별점 클릭 방식
    const starEls = document.querySelectorAll('.star-rating .star, .rating-star');
    if (starEls.length >= tpl.rating) starEls[tpl.rating-1].click();

    // 병원 select
    const hospSelect = document.querySelector('[name="hospital_id"], select[name="hospital_id"]');
    if (hospSelect && hospSelect.options.length > 1) {
        const rndIdx = Math.floor(Math.random()*Math.min(hospSelect.options.length-1,5))+1;
        hospSelect.selectedIndex = rndIdx;
        hospSelect.dispatchEvent(new Event('change',{bubbles:true}));
    }

    // 약관
    document.querySelectorAll('[name^="agree"]').forEach(cb => { cb.checked = true; cb.dispatchEvent(new Event('change',{bubbles:true})); });

    alert('테스트 후기 데이터가 입력되었습니다. (별점: '+tpl.rating+'점)');
}
</script>
JS;
}

// 광고 마법사 테스트 데이터 JS
function getDevModeJS_ad_wizard() {
    return <<<'JS'
<script>
function fillTestData_ad_wizard() {
    const rndNum = (min,max) => Math.floor(Math.random()*(max-min+1))+min;
    const idx = rndNum(1,999);

    const fields = {
        'advertiser_name': '데모 광고주 #'+idx,
        'contact_name': '담당자 '+idx,
        'contact_email': 'demo_ad_'+idx+'@test.com',
        'contact_phone': '010-'+rndNum(1000,9999)+'-'+rndNum(1000,9999),
        'business_number': rndNum(100,999)+'-'+rndNum(10,99)+'-'+rndNum(10000,99999),
        'title': '봄맞이 특별 할인 이벤트 #'+idx,
        'subtitle': '최대 50% 할인',
        'link_url': 'https://demo-ad-'+idx+'.example.com'
    };

    for (const [name, val] of Object.entries(fields)) {
        const el = document.querySelector('[name="'+name+'"]');
        if (el) { el.value = val; el.dispatchEvent(new Event('input',{bubbles:true})); }
    }

    // 병원 select
    const hospSel = document.querySelector('[name="ad_hospital"], select[name="hospital_id"]');
    if (hospSel && hospSel.options.length > 1) {
        hospSel.selectedIndex = rndNum(1, Math.min(hospSel.options.length-1,5));
        hospSel.dispatchEvent(new Event('change',{bubbles:true}));
    }

    alert('테스트 광고 데이터가 입력되었습니다.');
}
</script>
JS;
}

// 이벤트 테스트 데이터 JS
function getDevModeJS_event() {
    return <<<'JS'
<script>
function fillTestData_event() {
    const rndNum = (min,max) => Math.floor(Math.random()*(max-min+1))+min;
    const idx = rndNum(1,999);
    const eventTypes = ['봄맞이 특가','여름 할인','가을 이벤트','겨울 프로모션','신규 개원 기념','1주년 이벤트','리뉴얼 기념'];
    const rnd = (arr) => arr[Math.floor(Math.random()*arr.length)];

    // 시작일: 오늘, 종료일: 30일 후
    const today = new Date();
    const endDate = new Date(); endDate.setDate(today.getDate()+30);
    const fmt = d => d.toISOString().split('T')[0];

    const fields = {
        'title': rnd(eventTypes)+' #'+idx,
        'subtitle': '최대 '+rndNum(20,70)+'% 할인 혜택',
        'description': '데모 테스트 이벤트입니다. 기간 한정 특별 할인 혜택을 제공합니다. 선착순 '+rndNum(10,100)+'명에게 추가 혜택이 있습니다.',
        'original_price': String(rndNum(10,100)*10000),
        'discount_price': String(rndNum(5,50)*10000),
        'discount_rate': String(rndNum(10,70)),
        'start_date': fmt(today),
        'end_date': fmt(endDate),
        'max_entries': String(rndNum(10,200)),
        'raffle_prize': '시술권 '+rndNum(10,50)+'만원',
        'raffle_winner_count': String(rndNum(1,10)),
        'display_order': String(rndNum(1,100))
    };

    for (const [name, val] of Object.entries(fields)) {
        const el = document.querySelector('[name="'+name+'"]');
        if (el) { el.value = val; el.dispatchEvent(new Event('input',{bubbles:true})); }
    }

    // 병원 select
    const hospSel = document.querySelector('[name="hospital_id"], select[name="hospital_id"]');
    if (hospSel && hospSel.options.length > 1) {
        hospSel.selectedIndex = rndNum(1, Math.min(hospSel.options.length-1,5));
        hospSel.dispatchEvent(new Event('change',{bubbles:true}));
    }

    // is_active 체크
    const activeEl = document.querySelector('[name="is_active"]');
    if (activeEl) { activeEl.checked = true; activeEl.dispatchEvent(new Event('change',{bubbles:true})); }

    alert('테스트 이벤트 데이터가 입력되었습니다.');
}
</script>
JS;
}
