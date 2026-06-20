<?php
include_once('./_common.php');

// 페이지 제목 설정
$g5['title'] = '오늘의 타로 운세';

// 헤더 출력
include_once('./_head.php');

// 타로 카드 데이터
$tarot_cards = array(
    array(
        'id' => 'fool',
        'name' => '바보 (The Fool)',
        'emoji' => '🃏',
        'meaning' => '새로운 시작, 순수함, 모험',
        'fortune' => '오늘은 새로운 도전을 시작하기 좋은 날입니다. 두려워하지 말고 첫발을 내딛어보세요!',
        'love' => '설레는 만남이 기다리고 있어요. 열린 마음으로 다가가보세요.',
        'work' => '새로운 프로젝트나 업무에 도전해보세요. 신선한 아이디어가 떠오를 거예요.',
        'money' => '충동적인 소비는 피하고, 새로운 투자 기회를 신중히 검토해보세요.'
    ),
    array(
        'id' => 'magician',
        'name' => '마법사 (The Magician)',
        'emoji' => '🎩',
        'meaning' => '의지력, 창조, 집중',
        'fortune' => '당신의 능력을 최대한 발휘할 수 있는 날입니다. 자신감을 가지세요!',
        'love' => '적극적으로 마음을 표현하면 좋은 결과가 있을 거예요.',
        'work' => '리더십을 발휘하기 좋은 시기입니다. 주도적으로 일을 진행해보세요.',
        'money' => '계획했던 일들이 성과를 거둘 수 있습니다. 집중력을 발휘하세요.'
    ),
    array(
        'id' => 'priestess',
        'name' => '여사제 (The High Priestess)',
        'emoji' => '🔮',
        'meaning' => '직관, 잠재의식, 신비',
        'fortune' => '내면의 목소리에 귀 기울여보세요. 직관이 답을 알려줄 거예요.',
        'love' => '상대방의 진심을 느껴보세요. 말보다 마음이 중요한 시기입니다.',
        'work' => '섣부른 판단보다는 신중하게 상황을 지켜보는 것이 좋습니다.',
        'money' => '투자나 큰 지출은 조금 더 기다려보세요. 때가 아직 무르익지 않았습니다.'
    ),
    array(
        'id' => 'empress',
        'name' => '여황제 (The Empress)',
        'emoji' => '👑',
        'meaning' => '풍요, 창조성, 모성',
        'fortune' => '풍요롭고 행복한 하루가 될 것입니다. 주변에 사랑을 나눠주세요.',
        'love' => '따뜻한 애정이 넘치는 시기입니다. 사랑이 더욱 깊어질 거예요.',
        'work' => '창의적인 아이디어가 샘솟습니다. 예술적 감각을 발휘해보세요.',
        'money' => '안정적인 수입이 기대됩니다. 저축을 늘리기 좋은 시기예요.'
    ),
    array(
        'id' => 'emperor',
        'name' => '황제 (The Emperor)',
        'emoji' => '⚔️',
        'meaning' => '권위, 안정, 리더십',
        'fortune' => '목표를 향해 강하게 나아가세요. 당신의 노력이 결실을 맺을 것입니다.',
        'love' => '관계에서 주도권을 잡되, 상대방을 배려하는 것을 잊지 마세요.',
        'work' => '리더로서의 역량을 발휘할 때입니다. 결단력 있게 행동하세요.',
        'money' => '계획적인 재정 관리가 필요합니다. 장기적인 투자를 고려해보세요.'
    ),
    array(
        'id' => 'hierophant',
        'name' => '교황 (The Hierophant)',
        'emoji' => '⛪',
        'meaning' => '전통, 교육, 영성',
        'fortune' => '멘토나 조언자의 도움을 받을 수 있는 날입니다. 겸손하게 배워보세요.',
        'love' => '진실하고 성실한 관계가 중요합니다. 약속은 꼭 지키세요.',
        'work' => '기본에 충실하면 좋은 성과를 얻을 수 있습니다. 정도를 걸으세요.',
        'money' => '안전한 투자와 저축이 최선입니다. 욕심을 부리지 마세요.'
    ),
    array(
        'id' => 'lovers',
        'name' => '연인 (The Lovers)',
        'emoji' => '💕',
        'meaning' => '사랑, 선택, 조화',
        'fortune' => '중요한 선택의 순간이 올 수 있습니다. 마음의 소리를 따르세요.',
        'love' => '운명적인 사랑이 찾아올 수 있습니다. 진정한 사랑을 만날 거예요.',
        'work' => '팀워크가 중요한 시기입니다. 동료들과 조화롭게 일하세요.',
        'money' => '파트너십을 통한 이익이 기대됩니다. 협력 관계를 잘 유지하세요.'
    ),
    array(
        'id' => 'chariot',
        'name' => '전차 (The Chariot)',
        'emoji' => '🏇',
        'meaning' => '승리, 의지, 전진',
        'fortune' => '목표를 향해 전진하세요! 승리가 당신을 기다리고 있습니다.',
        'love' => '적극적으로 다가가면 좋은 결과가 있을 거예요. 용기를 내세요!',
        'work' => '경쟁에서 승리할 수 있습니다. 자신감을 가지고 도전하세요.',
        'money' => '노력한 만큼의 보상이 따를 것입니다. 계속 전진하세요.'
    ),
    array(
        'id' => 'strength',
        'name' => '힘 (Strength)',
        'emoji' => '🦁',
        'meaning' => '용기, 인내, 내면의 힘',
        'fortune' => '어려움을 극복할 수 있는 내면의 힘이 있습니다. 자신을 믿으세요.',
        'love' => '인내심을 가지고 관계를 발전시켜 나가세요. 사랑은 천천히 자랍니다.',
        'work' => '어려운 과제도 해낼 수 있습니다. 포기하지 말고 끝까지 해보세요.',
        'money' => '꾸준한 노력이 경제적 안정을 가져다줄 것입니다.'
    ),
    array(
        'id' => 'hermit',
        'name' => '은둔자 (The Hermit)',
        'emoji' => '🏔️',
        'meaning' => '성찰, 지혜, 고독',
        'fortune' => '혼자만의 시간이 필요한 날입니다. 내면을 들여다보세요.',
        'love' => '잠시 거리를 두고 관계를 돌아보는 것도 좋습니다.',
        'work' => '독립적으로 일하면 더 좋은 성과를 낼 수 있습니다.',
        'money' => '신중한 판단이 필요합니다. 전문가의 조언을 구해보세요.'
    ),
    array(
        'id' => 'fortune',
        'name' => '운명의 수레바퀴 (Wheel of Fortune)',
        'emoji' => '☸️',
        'meaning' => '변화, 행운, 순환',
        'fortune' => '큰 변화가 찾아올 수 있습니다. 행운이 당신과 함께합니다!',
        'love' => '예상치 못한 만남이나 전환점이 있을 수 있습니다.',
        'work' => '새로운 기회가 찾아옵니다. 변화를 두려워하지 마세요.',
        'money' => '행운이 따르는 시기입니다. 복권이나 투자 운이 좋습니다.'
    ),
    array(
        'id' => 'justice',
        'name' => '정의 (Justice)',
        'emoji' => '⚖️',
        'meaning' => '균형, 공정, 진실',
        'fortune' => '공정하고 균형 잡힌 판단이 필요한 날입니다. 옳은 일을 하세요.',
        'love' => '관계에서 균형을 찾으세요. 주고받는 것이 공평해야 합니다.',
        'work' => '노력한 만큼 정당한 평가를 받을 것입니다.',
        'money' => '정직한 거래가 이익을 가져다줄 것입니다.'
    ),
    array(
        'id' => 'hanged',
        'name' => '매달린 사람 (The Hanged Man)',
        'emoji' => '🙃',
        'meaning' => '희생, 관점 전환, 기다림',
        'fortune' => '다른 관점에서 상황을 바라보세요. 새로운 깨달음을 얻을 수 있습니다.',
        'love' => '상대방의 입장에서 생각해보세요. 이해의 폭이 넓어질 거예요.',
        'work' => '잠시 멈추고 전략을 재검토해보세요. 급할수록 돌아가세요.',
        'money' => '당장의 이익보다 장기적인 관점이 필요합니다.'
    ),
    array(
        'id' => 'death',
        'name' => '죽음 (Death)',
        'emoji' => '🌙',
        'meaning' => '변화, 끝과 시작, 변신',
        'fortune' => '낡은 것을 버리고 새로운 시작을 준비하세요. 변화는 성장의 기회입니다.',
        'love' => '관계의 새로운 국면이 시작됩니다. 과거는 놓아주세요.',
        'work' => '이직이나 전직을 고려해볼 시기입니다. 새로운 도전을 두려워하지 마세요.',
        'money' => '재정 구조를 재편할 때입니다. 불필요한 지출을 정리하세요.'
    ),
    array(
        'id' => 'temperance',
        'name' => '절제 (Temperance)',
        'emoji' => '🏺',
        'meaning' => '조화, 균형, 인내',
        'fortune' => '조급해하지 마세요. 천천히 그러나 꾸준히 나아가는 것이 중요합니다.',
        'love' => '서두르지 말고 천천히 관계를 발전시켜 나가세요.',
        'work' => '균형 잡힌 업무 처리가 필요합니다. 우선순위를 정하세요.',
        'money' => '절약과 적당한 소비의 균형을 찾으세요.'
    ),
    array(
        'id' => 'devil',
        'name' => '악마 (The Devil)',
        'emoji' => '😈',
        'meaning' => '유혹, 속박, 물질주의',
        'fortune' => '유혹에 빠지지 않도록 주의하세요. 자제력이 필요한 시기입니다.',
        'love' => '집착이나 의존은 관계를 해칩니다. 건강한 거리를 유지하세요.',
        'work' => '과도한 욕심은 금물입니다. 현실적인 목표를 세우세요.',
        'money' => '충동적인 소비나 도박은 피하세요. 절제가 필요합니다.'
    ),
    array(
        'id' => 'tower',
        'name' => '탑 (The Tower)',
        'emoji' => '🗼',
        'meaning' => '급변, 충격, 깨달음',
        'fortune' => '예상치 못한 변화가 있을 수 있습니다. 하지만 이는 새로운 기회가 될 것입니다.',
        'love' => '관계에 큰 변화가 있을 수 있습니다. 진실된 마음이 중요합니다.',
        'work' => '갑작스러운 변화에 대비하세요. 유연하게 대처하는 것이 중요합니다.',
        'money' => '예비 자금을 준비해두세요. 예상치 못한 지출이 있을 수 있습니다.'
    ),
    array(
        'id' => 'star',
        'name' => '별 (The Star)',
        'emoji' => '⭐',
        'meaning' => '희망, 영감, 치유',
        'fortune' => '희망을 잃지 마세요. 당신의 꿈은 반드시 이루어질 것입니다.',
        'love' => '진정한 사랑이 찾아올 것입니다. 희망을 가지고 기다리세요.',
        'work' => '영감이 떠오르는 시기입니다. 창의적인 아이디어를 실현해보세요.',
        'money' => '재정 상황이 개선될 것입니다. 긍정적인 마음을 유지하세요.'
    ),
    array(
        'id' => 'moon',
        'name' => '달 (The Moon)',
        'emoji' => '🌕',
        'meaning' => '환상, 불안, 직관',
        'fortune' => '상황이 명확하지 않을 수 있습니다. 직관을 믿되 신중하게 행동하세요.',
        'love' => '오해가 생길 수 있습니다. 솔직한 대화로 풀어나가세요.',
        'work' => '모든 정보를 확인하고 결정하세요. 서두르지 마세요.',
        'money' => '투자는 신중하게 하세요. 너무 좋아 보이는 것은 의심해보세요.'
    ),
    array(
        'id' => 'sun',
        'name' => '태양 (The Sun)',
        'emoji' => '☀️',
        'meaning' => '성공, 활력, 기쁨',
        'fortune' => '모든 일이 잘 풀리는 최고의 날입니다! 자신감을 가지고 행동하세요.',
        'love' => '사랑이 만개하는 시기입니다. 행복한 순간들을 만끽하세요.',
        'work' => '성공이 눈앞에 있습니다. 마지막까지 최선을 다하세요.',
        'money' => '재정적으로 풍요로운 시기입니다. 투자 수익도 기대할 수 있습니다.'
    ),
    array(
        'id' => 'judgement',
        'name' => '심판 (Judgement)',
        'emoji' => '🎺',
        'meaning' => '부활, 각성, 결정',
        'fortune' => '과거를 돌아보고 새로운 시작을 준비하세요. 중요한 결정의 시기입니다.',
        'love' => '과거의 상처를 치유하고 새로운 사랑을 시작할 때입니다.',
        'work' => '그동안의 노력이 평가받을 것입니다. 승진이나 인정을 받을 수 있습니다.',
        'money' => '재정 상황을 재평가하고 새로운 계획을 세울 때입니다.'
    ),
    array(
        'id' => 'world',
        'name' => '세계 (The World)',
        'emoji' => '🌍',
        'meaning' => '완성, 성취, 통합',
        'fortune' => '목표를 달성하고 새로운 사이클을 시작할 준비가 되었습니다. 축하합니다!',
        'love' => '완벽한 조화를 이루는 관계가 될 것입니다. 행복이 가득합니다.',
        'work' => '큰 프로젝트가 성공적으로 마무리됩니다. 성취감을 느껴보세요.',
        'money' => '재정적 목표를 달성할 것입니다. 풍요로움을 즐기세요.'
    )
);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&display=swap');
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&display=swap');

/* container_title ID 숨기기 */
#container_title {
    display: none !important;
}

/* 전체 페이지 배경색 설정 */
body {
    background: #0a0a0a !important;
}

/* 타로 배경 - 전체 화면 고정 */
.tarot-bg-wrapper {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: -1;
    background: #040014;
    pointer-events: none;
}

/* 배경 그라디언트 효과 */
.gradient-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(ellipse at top, rgba(122, 78, 254, 0.25) 0%, transparent 50%),
                radial-gradient(ellipse at bottom, rgba(122, 78, 254, 0.15) 0%, transparent 50%),
                linear-gradient(180deg, #0a0a0a 0%, #1a1a2e 30%, #16213e 70%, #0a0a0a 100%);
    background-size: 200% 200%;
    animation: gradientShift 20s ease infinite;
}

@keyframes gradientShift {
    0%, 100% { background-position: 0% 0%; }
    50% { background-position: 0% 100%; }
}

/* 고급스러운 파티클 효과 */
.deco-shapes {
    position: absolute;
    width: 100%;
    height: 100%;
    pointer-events: none;
}

.shape {
    position: absolute;
    opacity: 0.08;
}

.shape-circle {
    width: 600px;
    height: 600px;
    border-radius: 50%;
    background: radial-gradient(circle, #7a4efe 0%, transparent 70%);
    filter: blur(100px);
    animation: float 25s ease-in-out infinite;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -30px) scale(1.1); }
    66% { transform: translate(-20px, 20px) scale(0.9); }
}

.shape-1 { 
    top: -300px; 
    left: -300px; 
    animation-delay: 0s;
}
.shape-2 { 
    bottom: -300px; 
    right: -300px; 
    animation-delay: 8s;
}
.shape-3 { 
    top: 50%; 
    left: 50%; 
    transform: translate(-50%, -50%);
    width: 800px;
    height: 800px;
    animation-delay: 16s;
}

/* 은하수 배경 추가 스타일 */
body {
    background: #040014 !important;
}

.tarot-bg-wrapper {
    background: #040014;
}

.gradient-bg {
    background: 
        radial-gradient(ellipse at 25% 25%, rgba(122, 78, 254, 0.12) 0%, transparent 35%),
        radial-gradient(ellipse at 75% 75%, rgba(147, 51, 234, 0.08) 0%, transparent 40%),
        radial-gradient(ellipse at 50% 0%, rgba(167, 139, 250, 0.06) 0%, transparent 50%),
        linear-gradient(135deg, #040014 0%, #0c0420 25%, #1a0b2e 50%, #11071f 75%, #040014 100%) !important;
}

/* 별자리 스타일 */
.shape-4, .shape-5, .shape-6, .shape-7, .shape-8, .shape-9, .shape-10,
.shape-11, .shape-12, .shape-13, .shape-14, .shape-15 {
    width: 2px;
    height: 2px;
    background: radial-gradient(circle, rgba(255, 255, 255, 1) 0%, transparent 60%);
    animation: twinkle 4s ease-in-out infinite;
    filter: none;
}

.shape-4 { top: 15%; left: 35%; animation-delay: 0.3s; }
.shape-5 { top: 25%; left: 65%; animation-delay: 0.6s; }
.shape-6 { top: 45%; left: 25%; animation-delay: 0.9s; width: 3px; height: 3px; background: radial-gradient(circle, rgba(122, 78, 254, 1) 0%, transparent 60%); }
.shape-7 { top: 55%; left: 75%; animation-delay: 1.2s; }
.shape-8 { top: 70%; left: 40%; animation-delay: 1.5s; }
.shape-9 { top: 80%; left: 60%; animation-delay: 1.8s; width: 3px; height: 3px; }
.shape-10 { top: 35%; left: 85%; animation-delay: 2.1s; }
.shape-11 { top: 65%; left: 15%; animation-delay: 2.4s; background: radial-gradient(circle, rgba(167, 139, 250, 1) 0%, transparent 60%); }
.shape-12 { top: 20%; left: 50%; animation-delay: 2.7s; }
.shape-13 { top: 85%; left: 30%; animation-delay: 3.0s; }
.shape-14 { top: 40%; left: 90%; animation-delay: 3.3s; width: 4px; height: 4px; }
.shape-15 { top: 75%; left: 85%; animation-delay: 3.6s; }

@keyframes twinkle {
    0%, 100% { 
        opacity: 0.2;
        transform: scale(0.8);
    }
    50% { 
        opacity: 1;
        transform: scale(1.5);
    }
}

/* 유성 효과 - 점만 (꼬리 없음) */
.shooting-star {
    position: absolute;
    width: 4px;
    height: 4px;
    background: white;
    border-radius: 50%;
    opacity: 0;
    box-shadow: 0 0 6px 2px rgba(255, 255, 255, 1),
                0 0 12px 4px rgba(122, 78, 254, 0.6),
                0 0 20px 6px rgba(122, 78, 254, 0.3);
}

@keyframes shootingStarHorizontal {
    0% {
        left: 110%;
        top: 10%;
        opacity: 0;
    }
    5% {
        opacity: 1;
    }
    95% {
        opacity: 1;
    }
    100% {
        left: -15%;
        top: 35%;
        opacity: 0;
    }
}

@keyframes shootingStarHorizontal2 {
    0% {
        left: 110%;
        top: 20%;
        opacity: 0;
    }
    5% {
        opacity: 1;
    }
    95% {
        opacity: 1;
    }
    100% {
        left: -15%;
        top: 50%;
        opacity: 0;
    }
}

@keyframes shootingStarHorizontal3 {
    0% {
        left: 110%;
        top: 5%;
        opacity: 0;
    }
    5% {
        opacity: 1;
    }
    95% {
        opacity: 1;
    }
    100% {
        left: -15%;
        top: 25%;
        opacity: 0;
    }
}

.shooting-star-1 {
    animation: shootingStarHorizontal 6s linear infinite;
    animation-delay: 0s;
}

.shooting-star-2 {
    animation: shootingStarHorizontal2 8s linear infinite;
    animation-delay: 3s;
}

.shooting-star-3 {
    animation: shootingStarHorizontal3 7s linear infinite;
    animation-delay: 5s;
}

/* 별자리 라인 애니메이션 */
.constellation-lines {
    position: absolute;
    width: 100%;
    height: 100%;
    animation: constellationGlow 10s ease-in-out infinite;
}

@keyframes constellationGlow {
    0%, 100% { opacity: 0.05; }
    50% { opacity: 0.15; }
}

/* 추가 별 배경 */
.deco-shapes::before {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(1px 1px at 5% 10%, white, transparent),
        radial-gradient(1px 1px at 15% 20%, white, transparent),
        radial-gradient(2px 2px at 25% 15%, rgba(122, 78, 254, 0.8), transparent),
        radial-gradient(1px 1px at 35% 25%, white, transparent),
        radial-gradient(1px 1px at 45% 35%, white, transparent),
        radial-gradient(1px 1px at 55% 45%, white, transparent),
        radial-gradient(2px 2px at 65% 55%, rgba(167, 139, 250, 0.8), transparent),
        radial-gradient(1px 1px at 75% 65%, white, transparent),
        radial-gradient(1px 1px at 85% 75%, white, transparent),
        radial-gradient(1px 1px at 95% 85%, white, transparent);
    background-size: 100% 100%;
    opacity: 0.7;
}

.deco-shapes::after {
    content: '';
    position: absolute;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(1px 1px at 10% 90%, white, transparent),
        radial-gradient(1px 1px at 20% 80%, white, transparent),
        radial-gradient(1px 1px at 30% 70%, white, transparent),
        radial-gradient(2px 2px at 40% 60%, rgba(147, 51, 234, 0.8), transparent),
        radial-gradient(1px 1px at 50% 50%, white, transparent),
        radial-gradient(1px 1px at 60% 40%, white, transparent),
        radial-gradient(1px 1px at 70% 30%, white, transparent),
        radial-gradient(2px 2px at 80% 20%, rgba(122, 78, 254, 0.8), transparent),
        radial-gradient(1px 1px at 90% 10%, white, transparent);
    background-size: 100% 100%;
    opacity: 0.6;
    animation: starsRotate 120s linear infinite;
}

@keyframes starsRotate {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* 타로 컨텐츠 영역 */
.tarot-content-area {
    position: relative;
    min-height: 100vh;
    background: transparent;
    color: #e0e0e0;
    z-index: 1;
}

/* 메인 컨테이너 */
.tarot-app {
    position: relative;
    max-width: 1400px;
    margin: 0 auto;
    padding: 80px 20px;
    z-index: 1;
    background: transparent;
}

/* 헤더 - 고급스러운 타이포그래피 */
.tarot-header {
    text-align: center;
    margin-bottom: 60px;
    animation: fadeInDown 1s ease-out;
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.main-title {
    font-size: clamp(3rem, 6vw, 5rem);
    font-weight: 900;
    margin-bottom: 30px;
    background: linear-gradient(180deg, #ffffff 0%, #7a4efe 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    position: relative;
    letter-spacing: -1px;
}

.main-title::after {
    content: '';
    position: absolute;
    bottom: -15px;
    left: 50%;
    transform: translateX(-50%);
    width: 150px;
    height: 2px;
    background: linear-gradient(90deg, transparent, #7a4efe, transparent);
    border-radius: 2px;
}

.subtitle {
    font-size: 1.1rem;
    color: #888;
    font-weight: 300;
    margin-bottom: 50px;
    letter-spacing: 0.5px;
}

.instruction {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    padding: 18px 40px;
    background: rgba(122, 78, 254, 0.1);
    border: 1px solid rgba(122, 78, 254, 0.3);
    border-radius: 100px;
    font-size: 1.1rem;
    color: #555555;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    position: relative;
}

.instruction:hover {
    background: rgba(122, 78, 254, 0.15);
    border-color: rgba(122, 78, 254, 0.5);
    transform: translateY(-2px);
}

.instruction-icon {
    font-size: 1.5rem;
    filter: drop-shadow(0 0 10px rgba(122, 78, 254, 0.5));
}

/* 관리자 배지 */
.admin-badge {
    position: absolute;
    top: -10px;
    right: -10px;
    padding: 5px 15px;
    background: #ff4444;
    color: white;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: bold;
}

/* 카드 컨테이너 */
.cards-container {
    margin-bottom: 100px;
}

.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 28px;
    padding: 20px;
    max-width: 1200px;
    margin: 0 auto;
}

/* 카드 스타일 - 고급스러운 디자인 */
.tarot-card {
    aspect-ratio: 2/3;
    position: relative;
    cursor: pointer;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    transform-style: preserve-3d;
}

.tarot-card:hover {
    transform: translateY(-15px) rotateY(5deg) scale(1.05);
    z-index: 10;
}

.card-inner {
    width: 100%;
    height: 100%;
    position: relative;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5),
                0 0 0 1px rgba(122, 78, 254, 0.1);
}

.card-back {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
}

/* 카드 배경 이미지 */
.card-back::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: url('/img/500.png');
    background-size: cover;
    background-position: center;
    opacity: 0.3;
    filter: grayscale(100%) contrast(1.2);
    transition: all 0.3s ease;
}

.tarot-card:hover .card-back::before {
    opacity: 0.5;
    filter: grayscale(50%) contrast(1.3);
}

/* 카드 보라색 프레임 효과 */
.card-back::after {
    content: '';
    position: absolute;
    inset: 8px;
    border: 2px solid rgba(122, 78, 254, 0.4);
    border-radius: 12px;
    opacity: 0.6;
}

.card-symbol {
    font-size: 3rem;
    color: #7a4efe;
    filter: drop-shadow(0 0 30px rgba(122, 78, 254, 0.8));
    z-index: 2;
    animation: glow 3s ease-in-out infinite;
    position: relative;
}

@keyframes glow {
    0%, 100% { 
        transform: scale(1);
        filter: drop-shadow(0 0 30px rgba(122, 78, 254, 0.8));
    }
    50% { 
        transform: scale(1.1);
        filter: drop-shadow(0 0 40px rgba(122, 78, 254, 1));
    }
}

/* 로딩 오버레이 - 고급스러운 디자인 */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(10, 10, 10, 0.98);
    display: none;
    z-index: 9999;
    backdrop-filter: blur(20px);
}

.loading-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.loading-content {
    text-align: center;
}

.mystical-orb {
    width: 150px;
    height: 150px;
    margin: 0 auto 40px;
    position: relative;
}

.orb {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    background: radial-gradient(circle at 30% 30%, #9f6eff, #7a4efe);
    position: relative;
    animation: orbPulse 2s ease-in-out infinite;
    box-shadow: 0 0 80px rgba(122, 78, 254, 0.6),
                inset 0 0 40px rgba(159, 110, 255, 0.4);
}

@keyframes orbPulse {
    0%, 100% { 
        transform: scale(1); 
        box-shadow: 0 0 80px rgba(122, 78, 254, 0.6),
                    inset 0 0 40px rgba(159, 110, 255, 0.4);
    }
    50% { 
        transform: scale(1.1); 
        box-shadow: 0 0 120px rgba(122, 78, 254, 0.8),
                    inset 0 0 60px rgba(159, 110, 255, 0.6);
    }
}

.orb::before,
.orb::after {
    content: '';
    position: absolute;
    border-radius: 50%;
    border: 1px solid rgba(122, 78, 254, 0.3);
    animation: orbRing 3s linear infinite;
}

.orb::before {
    inset: -25px;
}

.orb::after {
    inset: -50px;
    animation-delay: 1.5s;
}

@keyframes orbRing {
    0% { 
        transform: rotate(0deg) scale(1); 
        opacity: 0; 
    }
    50% { 
        opacity: 0.8; 
    }
    100% { 
        transform: rotate(360deg) scale(1.5); 
        opacity: 0; 
    }
}

.loading-text {
    font-size: 1.5rem;
    color: #e0e0e0;
    font-weight: 300;
    letter-spacing: 1px;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.6; }
    50% { opacity: 1; }
}

/* 결과 섹션 - 고급스러운 스타일 */
.result-section {
    display: none;
    animation: fadeIn 1s ease-out;
}

.result-section.active {
    display: block;
}

@keyframes fadeIn {
    from { 
        opacity: 0; 
        transform: translateY(30px); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0); 
    }
}

/* 선택된 카드 표시 - 럭셔리한 디자인 */
.result-card {
    text-align: center;
    margin-bottom: 100px;
    padding: 80px 40px;
    background: linear-gradient(135deg, rgba(26, 26, 46, 0.8) 0%, rgba(22, 33, 62, 0.8) 100%);
    border: 1px solid rgba(122, 78, 254, 0.2);
    border-radius: 30px;
    box-shadow: 0 30px 80px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
    max-width: 700px;
    margin-left: auto;
    margin-right: auto;
    backdrop-filter: blur(20px);
    position: relative;
    overflow: hidden;
}

.result-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, #7a4efe, transparent);
    animation: shimmer 3s linear infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.chosen-card-emoji {
    font-size: 12rem;
    margin-bottom: 40px;
    display: inline-block;
    animation: cardReveal 1.5s cubic-bezier(0.4, 0, 0.2, 1);
    filter: drop-shadow(0 20px 40px rgba(122, 78, 254, 0.4));
}

@keyframes cardReveal {
    0% { 
        transform: rotateY(180deg) scale(0.5); 
        opacity: 0; 
    }
    50% { 
        transform: rotateY(90deg) scale(1.2); 
    }
    100% { 
        transform: rotateY(0deg) scale(1); 
        opacity: 1; 
    }
}

.card-name {
    font-size: 3.2rem;
    font-weight: 900;
    margin-bottom: 20px;
    background: linear-gradient(135deg, #ffffff 0%, #7a4efe 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.5px;
}

.card-meaning {
    font-size: 1.4rem;
    color: #b0b0b0;
    font-weight: 300;
    letter-spacing: 0.5px;
}

/* 운세 카드들 - 프리미엄 디자인 */
.fortune-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 30px;
    margin-bottom: 80px;
}

.fortune-card {
    background: linear-gradient(135deg, rgba(26, 26, 46, 0.6) 0%, rgba(22, 33, 62, 0.6) 100%);
    border: 1px solid rgba(122, 78, 254, 0.15);
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(10px);
}

.fortune-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(122, 78, 254, 0.1) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.4s;
}

.fortune-card:hover {
    transform: translateY(-10px);
    border-color: rgba(122, 78, 254, 0.4);
    box-shadow: 0 20px 60px rgba(122, 78, 254, 0.2);
}

.fortune-card:hover::before {
    opacity: 1;
}

.fortune-header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 25px;
}

.fortune-icon {
    font-size: 3.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #7a4efe 0%, #9f6eff 100%);
    border-radius: 20px;
    box-shadow: 0 8px 20px rgba(122, 78, 254, 0.3);
}

.fortune-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: #ffffff;
    letter-spacing: -0.5px;
}

.fortune-text {
    font-size: 1.2rem;
    line-height: 1.9;
    color: #c0c0c0;
    font-weight: 300;
}

/* 액션 버튼 - 프리미엄 스타일 */
.action-button {
    display: block;
    margin: 60px auto;
    padding: 22px 70px;
    background: linear-gradient(135deg, #7a4efe 0%, #9f6eff 100%);
    border: none;
    border-radius: 100px;
    font-size: 1.4rem;
    font-weight: 700;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 15px 40px rgba(122, 78, 254, 0.3);
    position: relative;
    overflow: hidden;
    letter-spacing: 0.5px;
}

.action-button::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    transform: translate(-50%, -50%);
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    transition: width 0.6s, height 0.6s;
}

.action-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 20px 50px rgba(122, 78, 254, 0.4);
}

.action-button:hover::before {
    width: 300px;
    height: 300px;
}

.action-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* 시간 표시 메시지 */
.time-message {
    margin-top: 30px;
    padding: 15px;
    background: rgba(122, 78, 254, 0.1);
    border: 1px solid rgba(122, 78, 254, 0.3);
    border-radius: 10px;
    color: #7a4efe;
    font-size: 1.1rem;
    text-align: center;
}

/* 관리자 모드 메시지 */
.admin-notice {
    margin-top: 30px;
    padding: 15px;
    background: rgba(255, 68, 68, 0.1);
    border: 1px solid rgba(255, 68, 68, 0.3);
    border-radius: 10px;
    color: #ff4444;
    font-size: 1.1rem;
    font-weight: bold;
    text-align: center;
}

/* 반응형 디자인 */
@media (max-width: 1024px) {
    .cards-grid {
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 20px;
    }
}

@media (max-width: 768px) {
    .tarot-app {
        padding: 50px 16px;
    }
    
    .main-title {
        font-size: 3rem;
    }
    
    .cards-grid {
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 12px;
    }
    
    .card-symbol {
        font-size: 2rem;
    }
    
    .chosen-card-emoji {
        font-size: 8rem;
    }
    
    .fortune-grid {
        grid-template-columns: 1fr;
    }
    
    .fortune-icon {
        width: 60px;
        height: 60px;
        font-size: 3rem;
    }
    
    .result-card {
        padding: 50px 30px;
    }
    
    .card-name {
        font-size: 2.5rem;
    }
}

@media (max-width: 480px) {
    .cards-grid {
        grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
        gap: 8px;
    }
    
    .card-inner {
        border-radius: 12px;
    }
    
    .card-symbol {
        font-size: 1.5rem;
    }
    
    .main-title {
        font-size: 2.2rem;
    }
    
    .action-button {
        padding: 18px 50px;
        font-size: 1.2rem;
    }
}
</style>

<!-- 배경 효과 -->
<div class="tarot-bg-wrapper">
    <div class="gradient-bg"></div>
    <div class="deco-shapes">
        <!-- 개별 별들 -->
        <div class="shape shape-circle shape-1"></div>
        <div class="shape shape-circle shape-2"></div>
        <div class="shape shape-circle shape-3"></div>
        <div class="shape shape-circle shape-4"></div>
        <div class="shape shape-circle shape-5"></div>
        <div class="shape shape-circle shape-6"></div>
        <div class="shape shape-circle shape-7"></div>
        <div class="shape shape-circle shape-8"></div>
        <div class="shape shape-circle shape-9"></div>
        <div class="shape shape-circle shape-10"></div>
        <div class="shape shape-circle shape-11"></div>
        <div class="shape shape-circle shape-12"></div>
        <div class="shape shape-circle shape-13"></div>
        <div class="shape shape-circle shape-14"></div>
        <div class="shape shape-circle shape-15"></div>
        
        <!-- 유성 효과 -->
        <div class="shooting-star shooting-star-1"></div>
        <div class="shooting-star shooting-star-2"></div>
        <div class="shooting-star shooting-star-3"></div>
    </div>
    
    <!-- 별자리 라인 효과 -->
    <div class="constellation-lines">
        <svg width="100%" height="100%" style="position:absolute; opacity:0.1;">
            <line x1="10%" y1="20%" x2="25%" y2="30%" stroke="#7a4efe" stroke-width="0.5"/>
            <line x1="25%" y1="30%" x2="30%" y2="25%" stroke="#7a4efe" stroke-width="0.5"/>
            <line x1="30%" y1="25%" x2="35%" y2="35%" stroke="#7a4efe" stroke-width="0.5"/>
            
            <line x1="70%" y1="60%" x2="75%" y2="55%" stroke="#9f6eff" stroke-width="0.5"/>
            <line x1="75%" y1="55%" x2="85%" y2="65%" stroke="#9f6eff" stroke-width="0.5"/>
            
            <line x1="50%" y1="10%" x2="55%" y2="20%" stroke="#a78bfa" stroke-width="0.5"/>
            <line x1="55%" y1="20%" x2="60%" y2="15%" stroke="#a78bfa" stroke-width="0.5"/>
        </svg>
    </div>
</div>

<!-- 타로 컨텐츠 영역 -->
<div class="tarot-content-area">
    <!-- 로딩 오버레이 -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="mystical-orb">
                <div class="orb"></div>
            </div>
            <div class="loading-text">운명의 카드를 해석하는 중...</div>
        </div>
    </div>

    <!-- 메인 앱 -->
    <div class="tarot-app">
        <!-- 헤더 -->
        <header class="tarot-header">
            <h1 class="main-title">오늘의 타로 운세</h1>
            <p class="subtitle">당신의 운명이 담긴 카드가 기다리고 있습니다</p>
            <div class="instruction">
                <span class="instruction-icon">🔮</span>
                <span id="instruction-text">마음이 끌리는 카드를 선택해주세요</span>
                <?php if($is_admin) { ?>
                <span class="admin-badge">관리자 모드</span>
                <?php } ?>
            </div>
        </header>
        
        <!-- 카드 선택 영역 -->
        <div class="cards-container" id="cardsContainer">
            <div class="cards-grid" id="cardsGrid">
                <?php
                $symbols = array('✨', '🌙', '⭐', '💫', '🌟', '✨');
                for($i = 0; $i < 22; $i++): 
                ?>
                <div class="tarot-card" data-index="<?php echo $i; ?>">
                    <div class="card-inner">
                        <div class="card-back">
                            <div class="card-symbol"><?php echo $symbols[$i % count($symbols)]; ?></div>
                        </div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- 결과 표시 영역 -->
        <div class="result-section" id="resultSection">
            <div class="result-card">
                <div class="chosen-card-emoji" id="chosenEmoji"></div>
                <h2 class="card-name" id="cardName"></h2>
                <p class="card-meaning" id="cardMeaning"></p>
                <div id="statusMessage"></div>
            </div>
            
            <div class="fortune-grid">
                <div class="fortune-card">
                    <div class="fortune-header">
                        <span class="fortune-icon">🔮</span>
                        <h3 class="fortune-title">오늘의 운세</h3>
                    </div>
                    <p class="fortune-text" id="todayFortune"></p>
                </div>
                
                <div class="fortune-card">
                    <div class="fortune-header">
                        <span class="fortune-icon">💕</span>
                        <h3 class="fortune-title">연애운</h3>
                    </div>
                    <p class="fortune-text" id="loveFortune"></p>
                </div>
                
                <div class="fortune-card">
                    <div class="fortune-header">
                        <span class="fortune-icon">💼</span>
                        <h3 class="fortune-title">직장/학업운</h3>
                    </div>
                    <p class="fortune-text" id="workFortune"></p>
                </div>
                
                <div class="fortune-card">
                    <div class="fortune-header">
                        <span class="fortune-icon">💰</span>
                        <h3 class="fortune-title">금전운</h3>
                    </div>
                    <p class="fortune-text" id="moneyFortune"></p>
                </div>
            </div>
            
            <button class="action-button" id="actionButton" onclick="resetTarot()">
                🔄 다시 뽑기
            </button>
        </div>
    </div>
</div>

<script>
// 타로 카드 데이터
const tarotCards = <?php echo json_encode($tarot_cards); ?>;

// 관리자 여부 체크
const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;

// 쿠키 관련 함수들
function setCookie(name, value, hours) {
    const date = new Date();
    // 자정까지 남은 시간 계산
    const midnight = new Date();
    midnight.setHours(24, 0, 0, 0);
    const msUntilMidnight = midnight - date;
    
    date.setTime(date.getTime() + msUntilMidnight);
    const expires = "expires=" + date.toUTCString();
    document.cookie = name + "=" + value + ";" + expires + ";path=/";
}

function getCookie(name) {
    const nameEQ = name + "=";
    const ca = document.cookie.split(';');
    for(let i = 0; i < ca.length; i++) {
        let c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
    }
    return null;
}

// 오늘 날짜 가져오기 (YYYY-MM-DD 형식)
function getTodayDate() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// 타로 결과 저장
function saveTarotResult(card) {
    // 관리자는 저장 안함 (테스트용)
    if (isAdmin) {
        console.log('관리자 모드: 결과 저장 생략');
        return;
    }
    
    const tarotData = {
        date: getTodayDate(),
        card: card
    };
    setCookie('tarot_result', JSON.stringify(tarotData), 24);
}

// 타로 결과 불러오기
function loadTarotResult() {
    // 관리자는 항상 null 반환 (제한 없음)
    if (isAdmin) {
        console.log('관리자 모드: 제한 없음');
        return null;
    }
    
    const saved = getCookie('tarot_result');
    if (saved) {
        try {
            const data = JSON.parse(saved);
            // 오늘 날짜와 저장된 날짜가 같은지 확인
            if (data.date === getTodayDate()) {
                return data.card;
            }
        } catch (e) {
            console.error('Failed to parse saved tarot data');
        }
    }
    return null;
}

// 자정까지 남은 시간 계산
function getTimeUntilMidnight() {
    const now = new Date();
    const midnight = new Date();
    midnight.setHours(24, 0, 0, 0);
    
    const diff = midnight - now;
    const hours = Math.floor(diff / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    
    return { hours, minutes };
}

// 페이지 로드 시 실행
window.addEventListener('DOMContentLoaded', function() {
    // 관리자 모드 처리
    if (isAdmin) {
        console.log('관리자 테스트 모드 활성화');
    } else {
        // 일반 사용자는 저장된 결과 확인
        const savedCard = loadTarotResult();
        if (savedCard) {
            // 저장된 결과가 있으면 바로 표시
            showResult(savedCard, false);
            
            // 안내 메시지 변경
            document.getElementById('instruction-text').textContent = '오늘의 운세를 이미 확인하셨습니다. 내일 다시 방문해주세요!';
        }
    }
});

// 카드 클릭 이벤트
document.querySelectorAll('.tarot-card').forEach(card => {
    card.addEventListener('click', function() {
        // 관리자가 아닌 경우만 제한 체크
        if (!isAdmin) {
            const savedCard = loadTarotResult();
            if (savedCard) {
                alert('오늘은 이미 타로 카드를 뽑으셨습니다.\n매일 자정에 초기화되니 내일 다시 방문해주세요!');
                return;
            }
        }
        
        const index = parseInt(this.getAttribute('data-index'));
        selectCard(index);
    });
});

// 카드 선택 함수
function selectCard(index) {
    const loadingOverlay = document.getElementById('loadingOverlay');
    loadingOverlay.classList.add('active');
    
    // 랜덤 카드 선택
    const selectedCard = tarotCards[Math.floor(Math.random() * tarotCards.length)];
    
    // 선택한 카드 저장 (관리자는 저장 안함)
    if (!isAdmin) {
        saveTarotResult(selectedCard);
    }
    
    setTimeout(() => {
        showResult(selectedCard, true);
        loadingOverlay.classList.remove('active');
    }, 2000);
}

// 결과 표시
function showResult(card, isNewSelection = true) {
    document.getElementById('chosenEmoji').textContent = card.emoji;
    document.getElementById('cardName').textContent = card.name;
    document.getElementById('cardMeaning').textContent = card.meaning;
    document.getElementById('todayFortune').textContent = card.fortune;
    document.getElementById('loveFortune').textContent = card.love;
    document.getElementById('workFortune').textContent = card.work;
    document.getElementById('moneyFortune').textContent = card.money;
    
    const cardsContainer = document.getElementById('cardsContainer');
    const resultSection = document.getElementById('resultSection');
    const actionButton = document.getElementById('actionButton');
    const statusMessage = document.getElementById('statusMessage');
    
    cardsContainer.style.display = 'none';
    resultSection.classList.add('active');
    
    // 상태 메시지 및 버튼 처리
    if (isAdmin) {
        // 관리자 모드
        statusMessage.innerHTML = '<div class="admin-notice">⚠️ 관리자 테스트 모드 - 일일 제한 없음</div>';
        actionButton.innerHTML = '🔄 다시 뽑기 (관리자)';
        actionButton.disabled = false;
        actionButton.style.display = 'block';
    } else if (isNewSelection) {
        // 일반 사용자가 새로 뽑은 경우
        actionButton.style.display = 'none';
    } else {
        // 일반 사용자가 이미 뽑은 경우
        const { hours, minutes } = getTimeUntilMidnight();
        statusMessage.innerHTML = `
            <div class="time-message">
                ⏰ 다음 운세까지: <strong>${hours}시간 ${minutes}분</strong> 남음
            </div>
        `;
        actionButton.innerHTML = '⏰ 내일 다시 방문해주세요';
        actionButton.disabled = true;
        actionButton.style.display = 'block';
    }
    
    // 스크롤
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// 다시 뽑기 함수
function resetTarot() {
    if (isAdmin) {
        // 관리자는 바로 리로드
        location.href = '<?php echo G5_URL; ?>/tarot.php';
    } else {
        alert('오늘의 운세는 하루에 한 번만 확인 가능합니다.');
        return false;
    }
}
</script>

<?php
include_once('./_tail.php');
?>