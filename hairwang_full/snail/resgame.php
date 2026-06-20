<?php
include_once("./_common.php");
include_once("./setup.php");

if (!defined("_GNUBOARD_")) exit;
if (!$is_member) alert("로그인 후 이용하세요.", G5_URL);

$current_point = $member['mb_point'];
if ($current_point < $min_point) {
    alert("보유하신 포인트(".number_format($current_point).")가 모자라 게임이 불가능합니다.", G5_URL);
}

$today_cnt = 0; // 간단하게 처리

include_once("./_head.php");
?>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* 전체 게임 컨테이너 */
.pig-game {
    max-width: 1200px;
    margin: 0 auto;
    background: linear-gradient(135deg, #2c5530, #4a7c59);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.3);
    overflow: hidden;
    position: relative;
}

/* 헤더 스타일 */
.pig-header {
    background: linear-gradient(135deg, #ff6b6b, #4ecdc4, #45b7d1);
    padding: 30px;
    text-align: center;
    color: white;
    position: relative;
    z-index: 1;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.pig-title {
    font-size: 2.5em;
    font-weight: bold;
    margin: 0 0 15px 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    letter-spacing: 2px;
}

.pig-points {
    background: rgba(255,255,255,0.25);
    padding: 15px 30px;
    border-radius: 30px;
    font-size: 1.4em;
    font-weight: bold;
    display: inline-block;
    border: 2px solid rgba(255,255,255,0.3);
    backdrop-filter: blur(10px);
}

/* 돼지 선택 화면 (시작 전) */
.pig-selection {
    padding: 40px 20px;
    background: linear-gradient(180deg, rgba(26,26,26,0.8), rgba(45,45,45,0.8)); /* 반투명 배경 */
    position: relative;
    min-height: 500px;
    display: flex;
    flex-direction: column;
    align-items: center;
    /* 경기장 배경 이미지 추가 */
    background-image: 
        linear-gradient(180deg, rgba(26,26,26,0.8), rgba(45,45,45,0.8)),
        url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%234a7c59" width="1200" height="600"/><rect fill="%238B4513" x="0" y="100" width="1200" height="80"/><rect fill="%238B4513" x="0" y="220" width="1200" height="80"/><rect fill="%238B4513" x="0" y="340" width="1200" height="80"/><rect fill="%238B4513" x="0" y="460" width="1200" height="80"/><line stroke="%23fff" stroke-width="3" stroke-dasharray="10,5" x1="0" y1="140" x2="1200" y2="140"/><line stroke="%23fff" stroke-width="3" stroke-dasharray="10,5" x1="0" y1="260" x2="1200" y2="260"/><line stroke="%23fff" stroke-width="3" stroke-dasharray="10,5" x1="0" y1="380" x2="1200" y2="380"/><line stroke="%23fff" stroke-width="3" stroke-dasharray="10,5" x1="0" y1="500" x2="1200" y2="500"/></svg>');
    background-size: cover;
    background-position: center;
    background-attachment: fixed;
}

/* 돼지 라인업 컨테이너 */
.pig-lineup {
    width: 100%;
    max-width: 1000px;
    margin: 20px auto;
}

/* 돼지 카드 그리드 - 한 줄에 3개씩 배치 */
.pig-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    padding: 20px;
    max-width: 600px;
    margin: 0 auto;
}

/* 각 돼지 카드 */
.pig-card {
    background: linear-gradient(135deg, rgba(58,58,58,0.9), rgba(74,74,74,0.9));
    backdrop-filter: blur(5px);
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    border: 3px solid rgba(85,85,85,0.8);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}

.pig-card.selected {
    border-color: #ffd700;
    box-shadow: 0 0 20px rgba(255, 215, 0, 0.5);
    transform: translateY(-5px);
    background: linear-gradient(135deg, rgba(58,58,58,0.95), rgba(74,74,74,0.95));
}

/* 돼지 번호 */
.pig-number {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #ff6b6b;
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 16px;
}

/* 돼지 이미지 */
.pig-avatar {
    width: 60px; /* 80px에서 줄임 */
    height: 60px; /* 80px에서 줄임 */
    margin: 0 auto 15px;
    overflow: hidden;
}

.pig-avatar img {
    width: 100%;
    height: 100%;
    object-fit: contain; /* cover에서 contain으로 변경 */
}

/* 체력바 */
.pig-stamina {
    margin: 10px 0;
}

.stamina-label {
    color: #ccc;
    font-size: 12px;
    margin-bottom: 5px;
    display: block;
}

.stamina-bar {
    width: 100%;
    height: 10px;
    background: #333;
    border-radius: 5px;
    overflow: hidden;
    position: relative;
}

.stamina-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff6b6b, #ffd93d);
    width: var(--stamina, 100%);
    transition: width 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stamina-fill::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%);
    animation: shine 2s infinite;
}

@keyframes shine {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* 베팅 입력 */
.pig-bet-input {
    margin-top: 15px;
}

.bet-input {
    width: 100%;
    padding: 10px;
    border: 2px solid #555;
    border-radius: 8px;
    background: #2a2a2a;
    color: white;
    text-align: center;
    font-size: 14px;
    font-weight: bold;
    transition: all 0.3s ease;
}

.bet-input:focus {
    outline: none;
    border-color: #ffd700;
    box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
}

.bet-input.has-bet {
    border-color: #28a745;
    background: #1a3a1a;
}

/* 게임 컨트롤 영역 */
.game-controls {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    margin-top: 30px;
}

.start-button {
    background: linear-gradient(135deg, #dc3545, #fd7e14);
    color: white;
    border: none;
    padding: 25px 60px;
    font-size: 28px;
    font-weight: bold;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 10px 40px rgba(220, 53, 69, 0.5);
    border: 4px solid rgba(255,255,255,0.3);
    text-transform: uppercase;
    letter-spacing: 2px;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0% { transform: scale(1); box-shadow: 0 10px 40px rgba(220, 53, 69, 0.5); }
    50% { transform: scale(1.05); box-shadow: 0 15px 50px rgba(220, 53, 69, 0.7); }
    100% { transform: scale(1); box-shadow: 0 10px 40px rgba(220, 53, 69, 0.5); }
}

.start-button:hover {
    background: linear-gradient(135deg, #c82333, #e55100);
    transform: scale(1.1);
    animation: none;
}

.start-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    animation: none;
}

/* 베팅 요약 */
.bet-summary {
    background: rgba(255,255,255,0.1);
    padding: 20px;
    border-radius: 15px;
    color: white;
    text-align: center;
    max-width: 400px;
}

.bet-summary h3 {
    margin: 0 0 15px 0;
    font-size: 20px;
}

.bet-details {
    display: flex;
    justify-content: space-around;
    gap: 20px;
}

.bet-detail-item {
    text-align: center;
}

.bet-detail-label {
    font-size: 14px;
    color: #ccc;
    margin-bottom: 5px;
}

.bet-detail-value {
    font-size: 24px;
    font-weight: bold;
    color: #ffd700;
}

/* 경주 트랙 (게임 중) */
.pig-tracks {
    padding: 30px 20px;
    background: linear-gradient(180deg, #2d5016, #3d6b2a);
    position: relative;
    z-index: 1;
    display: none;
    overflow: visible; /* 말풍선이 트랙 밖에서도 보이도록 추가 */
}

.pig-tracks.active {
    display: block;
}

/* 트랙 스타일 */
.pig-track {
    background: linear-gradient(90deg, #8B4513 0%, #D2691E 2%, #F4A460 5%, #DEB887 95%, #D2691E 98%, #8B4513 100%);
    border: 3px solid #654321;
    margin: 0 0 10px 0;
    padding: 25px;
    position: relative;
    height: 100px;
    overflow: visible; /* hidden에서 visible로 변경 - 말풍선이 보이도록 */
    box-shadow: inset 0 -3px 6px rgba(0,0,0,0.3), 0 2px 8px rgba(0,0,0,0.2);
}

/* 트랙 라인 */
.pig-track::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: repeating-linear-gradient(
        to right,
        transparent 0px,
        transparent 48px,
        rgba(255,255,255,0.3) 48px,
        rgba(255,255,255,0.3) 52px
    );
    pointer-events: none;
}

/* 결승선 */
.finish-line {
    position: absolute;
    right: 50px;
    top: 0;
    bottom: 0;
    width: 6px;
    background: repeating-linear-gradient(
        180deg,
        #000 0px,
        #000 8px,
        #fff 8px,
        #fff 16px
    );
    box-shadow: 2px 0 15px rgba(0,0,0,0.8);
    z-index: 5;
}

/* 레인 정보 */
.pig-info {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    font-weight: bold;
    font-size: 16px;
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    box-shadow: 0 3px 10px rgba(30, 60, 114, 0.4);
    z-index: 10;
}

/* 내가 선택한 돼지의 레인 정보 스타일 */
.pig-info.my-bet {
    background: linear-gradient(135deg, #ff4444, #cc0000);
    box-shadow: 0 3px 10px rgba(255, 0, 0, 0.6);
    animation: glow 1s ease-in-out infinite alternate;
}

@keyframes glow {
    from { box-shadow: 0 3px 10px rgba(255, 0, 0, 0.6); }
    to { box-shadow: 0 3px 20px rgba(255, 0, 0, 0.9); }
}

/* 돼지 러너 */
.pig-runner {
    position: absolute;
    left: 100px;
    top: 50%;
    transform: translateY(-50%);
    width: 40px; /* 50px에서 줄임 */
    height: 40px; /* 50px에서 줄임 */
    transition: left 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    z-index: 10;
    overflow: visible;
}

.pig-runner img {
    width: 100%;
    height: 100%;
    /* border-radius: 50%; 제거 - 원형 모양 제거 */
    border: none; /* 테두리 제거 */
    box-shadow: none; /* 그림자 제거 */
    background: transparent; /* 배경 투명 */
}

.pig-runner.running img {
    animation: pigRunning 0.4s ease-in-out infinite;
    border: none; /* 테두리 제거 */
    box-shadow: none; /* 그림자 제거 */
}

.pig-runner.winner img {
    animation: pigWinner 0.8s ease-in-out infinite alternate;
    filter: brightness(1.4) saturate(1.3);
    border: none; /* 테두리 제거 */
    box-shadow: none; /* 그림자 제거 - 황금빛도 제거 */
}

/* 먼지 효과 컨테이너 */
.dust-container {
    position: absolute;
    left: 0;
    bottom: -10px;
    width: 60px;
    height: 30px;
    pointer-events: none;
    z-index: 8;
}

/* 먼지 파티클 */
.dust-particle {
    position: absolute;
    background: radial-gradient(circle, rgba(139,69,19,0.6) 0%, rgba(160,82,45,0.3) 50%, transparent 70%);
    border-radius: 50%;
    opacity: 0;
    animation: dustFloat 1.5s ease-out infinite;
}

.dust-particle:nth-child(1) {
    width: 12px;
    height: 12px;
    left: 0;
    bottom: 0;
    animation-delay: 0s;
}

.dust-particle:nth-child(2) {
    width: 8px;
    height: 8px;
    left: 5px;
    bottom: 5px;
    animation-delay: 0.2s;
}

.dust-particle:nth-child(3) {
    width: 15px;
    height: 15px;
    left: -5px;
    bottom: 2px;
    animation-delay: 0.4s;
}

.dust-particle:nth-child(4) {
    width: 10px;
    height: 10px;
    left: 10px;
    bottom: 8px;
    animation-delay: 0.6s;
}

.dust-particle:nth-child(5) {
    width: 6px;
    height: 6px;
    left: -3px;
    bottom: 10px;
    animation-delay: 0.8s;
}

/* 먼지 애니메이션 */
@keyframes dustFloat {
    0% {
        transform: translate(0, 0) scale(0);
        opacity: 0;
    }
    20% {
        transform: translate(-5px, -5px) scale(1);
        opacity: 0.8;
    }
    60% {
        transform: translate(-20px, -15px) scale(1.2);
        opacity: 0.5;
    }
    100% {
        transform: translate(-35px, -20px) scale(0.8);
        opacity: 0;
    }
}

/* 터보 모드일 때 더 강한 먼지 효과 */
.pig-runner.turbo .dust-particle {
    animation-duration: 0.8s;
    background: radial-gradient(circle, rgba(255,140,0,0.8) 0%, rgba(255,69,0,0.4) 50%, transparent 70%);
}

/* 모래 바람 효과 */
.sandstorm-effect {
    position: absolute;
    left: -20px;
    top: 50%;
    transform: translateY(-50%);
    width: 80px;
    height: 40px;
    pointer-events: none;
    z-index: 7;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.pig-runner.running .sandstorm-effect {
    opacity: 1;
}

.sandstorm-particle {
    position: absolute;
    background: linear-gradient(90deg, transparent 0%, rgba(210,180,140,0.3) 20%, rgba(222,184,135,0.5) 50%, rgba(210,180,140,0.3) 80%, transparent 100%);
    height: 2px;
    animation: sandstormFlow 1s linear infinite;
}

.sandstorm-particle:nth-child(1) {
    width: 40px;
    top: 10px;
    animation-delay: 0s;
}

.sandstorm-particle:nth-child(2) {
    width: 35px;
    top: 20px;
    animation-delay: 0.3s;
}

.sandstorm-particle:nth-child(3) {
    width: 45px;
    top: 30px;
    animation-delay: 0.6s;
}

@keyframes sandstormFlow {
    0% {
        transform: translateX(0) scaleX(0);
        opacity: 0;
    }
    20% {
        transform: translateX(-10px) scaleX(1);
        opacity: 1;
    }
    80% {
        transform: translateX(-60px) scaleX(1);
        opacity: 1;
    }
    100% {
        transform: translateX(-80px) scaleX(0);
        opacity: 0;
    }
}

/* 선택한 돼지 강조 (레이싱 중) - 삭제 */
/* MY 표시 제거하고 레인 번호로 표시 */

.pig-runner.my-bet::before {
    content: '★';
    position: absolute;
    top: -30px;
    left: 50%;
    transform: translateX(-50%);
    color: #ff0000;
    font-size: 20px;
    animation: bounce 1s ease-in-out infinite;
}

/* 특수 상태 애니메이션 */
.pig-runner.sleeping img {
    animation: sleeping 2s ease-in-out infinite;
    filter: grayscale(0.5);
}

.pig-runner.confused img {
    animation: confused 1s ease-in-out infinite;
}

.pig-runner.turbo img {
    animation: turbo 0.2s ease-in-out infinite;
    filter: hue-rotate(180deg) brightness(1.5);
    /* box-shadow 제거 */
}

.pig-runner.backwards img {
    transform: scaleX(-1);
    animation: backwards 0.5s ease-in-out infinite;
}

/* 등수 표시 */
.rank-badge {
    position: absolute;
    top: -40px;
    right: -10px;
    background: #fff;
    color: #333;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 14px;
    font-weight: bold;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
    z-index: 20;
    opacity: 0;
    animation: rankAppear 0.5s ease forwards;
}

.rank-badge.rank-1 {
    background: linear-gradient(135deg, #FFD700, #FFA500);
    color: white;
}

.rank-badge.rank-2 {
    background: linear-gradient(135deg, #C0C0C0, #999999);
    color: white;
}

.rank-badge.rank-3 {
    background: linear-gradient(135deg, #CD7F32, #8B4513);
    color: white;
}

@keyframes rankAppear {
    from {
        opacity: 0;
        transform: scale(0);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes pigRunning {
    0%, 100% { transform: scale(1) rotate(-5deg); }
    50% { transform: scale(1.1) rotate(5deg) translateY(-3px); }
}

@keyframes pigWinner {
    0% { transform: scale(1) rotate(0deg); }
    100% { transform: scale(1.15) rotate(10deg); }
}

@keyframes sleeping {
    0%, 100% { transform: scale(1) rotate(-10deg); }
    50% { transform: scale(0.9) rotate(-10deg) translateY(5px); }
}

@keyframes confused {
    0% { transform: rotate(0deg); }
    25% { transform: rotate(-15deg); }
    75% { transform: rotate(15deg); }
    100% { transform: rotate(0deg); }
}

@keyframes turbo {
    0%, 100% { transform: scale(1) translateX(0); }
    50% { transform: scale(1.2) translateX(2px); }
}

@keyframes backwards {
    0%, 100% { transform: scaleX(-1) rotate(-5deg); }
    50% { transform: scaleX(-1) rotate(5deg) translateY(-2px); }
}

/* 상태 메시지 */
.status-message {
    position: absolute;
    top: -45px; /* 더 위로 올려서 잘 보이게 */
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0,0,0,0.9);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
    white-space: nowrap;
    opacity: 0;
    animation: fadeInOut 2s ease;
    z-index: 100; /* z-index 높게 설정 */
    box-shadow: 0 4px 12px rgba(0,0,0,0.5);
    border: 2px solid rgba(255,255,255,0.3);
}

@keyframes fadeInOut {
    0% { opacity: 0; transform: translateX(-50%) translateY(5px); }
    20% { opacity: 1; transform: translateX(-50%) translateY(0); }
    80% { opacity: 1; transform: translateX(-50%) translateY(0); }
    100% { opacity: 0; transform: translateX(-50%) translateY(-5px); }
}

/* 체력 표시 (경주 중) */
.runner-stamina {
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 6px;
    background: rgba(0,0,0,0.5);
    border-radius: 3px;
    overflow: hidden;
}

.runner-stamina-fill {
    height: 100%;
    background: linear-gradient(90deg, #ff4444, #ffaa00);
    width: var(--stamina, 100%);
    transition: width 0.3s ease;
}

/* 가이드 */
.pig-guide {
    padding: 25px;
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-top: 3px solid #28a745;
    text-align: center;
}

.pig-guide h3 {
    color: #2c5530;
    margin-bottom: 15px;
    font-size: 1.3em;
}

.pig-guide p {
    margin: 10px 0;
    color: #495057;
    line-height: 1.6;
}

/* 반응형 */
@media (max-width: 768px) {
    .pig-cards {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        padding: 10px;
    }
    
    .pig-card {
        padding: 10px;
    }
    
    .pig-avatar {
        width: 50px;
        height: 50px;
    }
    
    .bet-input {
        font-size: 12px;
        padding: 8px;
    }
    
    .start-button {
        padding: 20px 40px;
        font-size: 22px;
    }
    
    .bet-summary {
        padding: 15px;
        font-size: 14px;
    }
    
    .bet-detail-value {
        font-size: 20px;
    }
}

@media (max-width: 480px) {
    .pig-cards {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* 올림픽 스타일 시상식 팝업 추가 */
.olympic-popup {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.85);
    animation: fadeIn 0.3s ease;
    overflow-y: auto; /* 스크롤 가능하게 */
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.olympic-content {
    position: relative; /* absolute에서 relative로 변경 */
    margin: 50px auto; /* 중앙 정렬 */
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    padding: 40px;
    border-radius: 25px;
    text-align: center;
    max-width: 600px;
    width: 90%;
    box-shadow: 0 30px 60px rgba(0,0,0,0.4);
    animation: slideUp 0.4s ease;
    border: 3px solid #ffd700;
    max-height: 90vh; /* 최대 높이 제한 */
    overflow-y: auto; /* 내용이 많으면 스크롤 */
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.olympic-close {
    position: absolute;
    top: 15px;
    right: 22px;
    font-size: 35px;
    cursor: pointer;
    color: #999;
    transition: color 0.3s ease;
    font-weight: bold;
}

.olympic-close:hover {
    color: #333;
}

.celebration-title {
    font-size: 2em;
    margin: 20px 0;
    animation: celebrate 1s ease-in-out infinite alternate;
    color: #2c5530;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
}

@keyframes celebrate {
    0% { transform: scale(1); }
    100% { transform: scale(1.05); }
}

.prize-info {
    font-size: 1.6em;
    font-weight: bold;
    color: #28a745;
    margin: 20px 0;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
    padding: 15px;
    background: linear-gradient(135deg, #d4edda, #c3e6cb);
    border-radius: 15px;
    border: 2px solid #28a745;
}

.olympic-podium {
    display: flex;
    justify-content: center;
    align-items: flex-end;
    gap: 20px;
    margin: 35px 0;
    height: 280px;
}

.podium-position {
    display: flex;
    flex-direction: column;
    align-items: center;
    border-radius: 12px;
    position: relative;
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    transition: transform 0.3s ease;
    overflow: visible;
}

.podium-position:hover {
    transform: scale(1.05);
}

/* 2등 (왼쪽) */
.podium-second {
    width: 95px;
    height: 140px;
    background: linear-gradient(135deg, #C0C0C0, #E8E8E8);
    order: 1;
    border: 3px solid #C0C0C0;
}

.podium-second::before {
    content: '2등';
    position: absolute;
    top: -50px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #C0C0C0, #999999);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    box-shadow: 0 3px 10px rgba(0,0,0,0.3);
}

/* 1등 (가운데, 가장 높음) */
.podium-first {
    width: 110px;
    height: 170px;
    background: linear-gradient(135deg, #FFD700, #FFA000);
    order: 2;
    border: 4px solid #FFD700;
}

.podium-first::before {
    content: '1등';
    position: absolute;
    top: -55px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #FFD700, #FFA000);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    box-shadow: 0 3px 10px rgba(0,0,0,0.3);
}

/* 3등 (오른쪽) */
.podium-third {
    width: 85px;
    height: 120px;
    background: linear-gradient(135deg, #CD7F32, #8D6E63);
    order: 3;
    border: 3px solid #CD7F32;
}

.podium-third::before {
    content: '3등';
    position: absolute;
    top: -45px;
    left: 50%;
    transform: translateX(-50%);
    background: linear-gradient(135deg, #CD7F32, #8B4513);
    color: white;
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: bold;
    box-shadow: 0 3px 10px rgba(0,0,0,0.3);
}

.podium-pig {
    position: absolute;
    top: -85px;
    left: 50%;
    transform: translateX(-50%);
    width: 75px;
    height: 75px;
    border-radius: 50%;
    overflow: hidden;
    border: 4px solid white;
    box-shadow: 0 8px 25px rgba(0,0,0,0.5);
    animation: bounce-in 0.8s ease-out;
    z-index: 10;
}

.podium-pig.first {
    width: 85px;
    height: 85px;
    top: -95px;
    animation: winner-bounce 1s ease-out infinite alternate;
    border: 5px solid #FFD700;
    box-shadow: 0 8px 25px rgba(255,215,0,0.6);
}

.podium-pig.second {
    width: 70px;
    height: 70px;
    top: -80px;
    border: 4px solid #C0C0C0;
}

.podium-pig.third {
    width: 65px;
    height: 65px;
    top: -75px;
    border: 4px solid #CD7F32;
}

@keyframes bounce-in {
    0% {
        transform: translate(-50%, -60px) scale(0);
        opacity: 0;
    }
    50% {
        transform: translate(-50%, 15px) scale(1.1);
        opacity: 0.8;
    }
    100% {
        transform: translate(-50%, 0) scale(1);
        opacity: 1;
    }
}

@keyframes winner-bounce {
    0% { transform: translate(-50%, 0) scale(1); }
    100% { transform: translate(-50%, -10px) scale(1.05); }
}

.podium-pig img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.podium-rank {
    position: absolute;
    bottom: 20px;
    width: 100%;
    text-align: center;
    font-weight: bold;
    color: white;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.7);
    font-size: 1em;
    padding: 8px;
}

.podium-rank.first {
    font-size: 1.2em;
}

.medal {
    position: absolute;
    top: -30px;
    right: -18px;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8em;
    animation: medal-glow 2s ease-in-out infinite alternate;
    z-index: 15;
    border: 2px solid rgba(255,255,255,0.8);
}

.medal.gold {
    background: linear-gradient(135deg, #FFD700, #FFA000);
    box-shadow: 0 0 30px rgba(255, 215, 0, 0.9);
}

.medal.silver {
    background: linear-gradient(135deg, #C0C0C0, #999999);
    box-shadow: 0 0 30px rgba(192, 192, 192, 0.9);
}

.medal.bronze {
    background: linear-gradient(135deg, #CD7F32, #8B4513);
    box-shadow: 0 0 30px rgba(205, 127, 50, 0.9);
}

@keyframes medal-glow {
    0% { transform: scale(1); }
    100% { transform: scale(1.15); }
}

.race-summary {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-radius: 15px;
    padding: 25px;
    margin: 30px 0;
    border-left: 5px solid #28a745;
    text-align: left;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.summary-item {
    margin: 12px 0;
    padding: 15px;
    background: white;
    border-radius: 12px;
    border-left: 4px solid #28a745;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.summary-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.summary-right {
    font-weight: bold;
    color: #28a745;
    font-size: 1.1em;
}

/* 확인 버튼 스타일 추가 */
.confirm-button {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border: none;
    padding: 15px 40px;
    margin-top: 20px;
    font-size: 18px;
    font-weight: bold;
    border-radius: 30px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    border: 2px solid rgba(255,255,255,0.2);
}

.confirm-button:hover {
    background: linear-gradient(135deg, #218838, #17a2b8);
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

/* 비활성화된 카드 스타일 */
.pig-card.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

.pig-card.disabled .bet-input {
    background: #1a1a1a;
    cursor: not-allowed;
}

</style>

<div class="pig-game">
    <div class="pig-header">
        <h1 class="pig-title">🏟️ 돼지레이싱 스타디움</h1>
        <div class="pig-points" id="currentPoints"><?php echo number_format($current_point); ?>P</div>
    </div>
    
    <!-- 돼지 선택 화면 -->
    <div class="pig-selection" id="selectionScreen">
        <div class="pig-lineup">
            <div class="pig-cards">
                <?php for($i = 1; $i <= $set_number; $i++): ?>
                <div class="pig-card" id="pigCard<?php echo $i?>">
                    <div class="pig-number"><?php echo $i?></div>
                    <div class="pig-avatar">
                        <img src="./img/snail<?php echo $i?>.gif" alt="돼지<?php echo $i?>" 
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iMzgiIGZpbGw9IiNGRjZCNkIiLz4KPHRleHQgeD0iNDAiIHk9IjQ1IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LXNpemU9IjI0cHgiIGZpbGw9IndoaXRlIj7wn5C3PC90ZXh0Pgo8L3N2Zz4=';">
                    </div>
                    <div class="pig-stamina">
                        <span class="stamina-label">체력</span>
                        <div class="stamina-bar">
                            <div class="stamina-fill" style="--stamina: <?php echo rand(70, 100)?>%;"></div>
                        </div>
                    </div>
                    <div class="pig-bet-input">
                        <input type="number" class="bet-input" id="bet<?php echo $i?>" 
                               placeholder="베팅 포인트" min="0" max="50000">
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- 시작 버튼과 베팅 요약 -->
        <div class="game-controls">
            <div class="bet-summary">
                <h3>베팅 현황</h3>
                <div class="bet-details">
                    <div class="bet-detail-item">
                        <div class="bet-detail-label">선택한 돼지</div>
                        <div class="bet-detail-value" id="betCount">0</div>
                    </div>
                    <div class="bet-detail-item">
                        <div class="bet-detail-label">총 베팅액</div>
                        <div class="bet-detail-value" id="betTotal">0P</div>
                    </div>
                </div>
            </div>
            
            <button class="start-button" onclick="startGame()" id="startBtn">🚀 레이스 시작!</button>
        </div>
    </div>
    
    <!-- 경주 트랙 화면 -->
    <div class="pig-tracks" id="raceScreen">
        <?php for($i = 1; $i <= $set_number; $i++): ?>
        <div class="pig-track" id="track<?php echo $i?>">
            <div class="finish-line"></div>
            <div class="pig-info"><?php echo $i?>번</div>
            <div class="pig-runner" id="runner<?php echo $i?>">
                <div class="runner-stamina">
                    <div class="runner-stamina-fill" id="stamina<?php echo $i?>"></div>
                </div>
                <img src="./img/snail<?php echo $i?>.gif" alt="돼지<?php echo $i?>">
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div class="pig-guide">
        <h3>🏆 게임 안내</h3>
        <p><strong>베팅 방법:</strong> 돼지를 클릭하고 베팅 포인트를 입력하세요 (최대 <?php echo $set_maxcnt?>마리)</p>
        <p><strong>체력 시스템:</strong> 각 돼지마다 다른 체력을 가지고 있어 경주 결과에 영향을 줍니다</p>
        <p><strong>배당률:</strong> 🥇1등 <?php echo $setno_point1?>배, 🥈2등 <?php echo $setno_point2?>배, 🥉3등 <?php echo number_format($setno_point3)?>P</p>
        <p><strong>특수 이벤트:</strong> 💤 잠들기, 😵 멍때리기, 🚀 터보, 🔄 역주행 등 다양한 이벤트 발생!</p>
    </div>
</div>

<!-- 올림픽 스타일 시상식 팝업 -->
<div class="olympic-popup" id="olympicPopup">
    <div class="olympic-content">
        <span class="olympic-close" onclick="closePopup()">&times;</span>
        <div id="olympicResult"></div>
        <button class="confirm-button" onclick="confirmResult()">확인</button>
    </div>
</div>

<script>
// 게임 설정
const GAME = {
    money: <?php echo $current_point?>,
    pigCount: <?php echo $set_number?>,
    maxCount: <?php echo $set_maxcnt?>,
    minPoint: <?php echo $set_min_point?>,
    point1: <?php echo $setno_point1?>,
    point2: <?php echo $setno_point2?>,
    point3: <?php echo $setno_point3?>,
    
    // 게임 상태
    isRunning: false,
    positions: {},
    ranks: {},
    stamina: {},
    currentRank: 1,
    interval: null,
    bets: {},
    events: {} // 각 돼지의 이벤트 상태
};

// 사운드 관리
const sounds = {
    start1: null,
    start2: null,
    cheer: null,
    countdown: null
};

// 사운드 초기화
function initSounds() {
    try {
        sounds.start1 = new Audio('./mp3/start-sound.mp3');
        sounds.start2 = new Audio('./mp3/start-sound-1.mp3');
        sounds.cheer = new Audio('./mp3/1.wav');
        
        // 카운트다운 사운드 생성 (웹 오디오 API 사용)
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        const audioContext = new AudioContext();
        
        // 카운트다운 비프음 함수
        window.playCountdownBeep = function(frequency = 800, duration = 200) {
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = frequency;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration / 1000);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + duration / 1000);
        };
        
        // 모든 사운드에 볼륨 설정
        Object.values(sounds).forEach(sound => {
            if (sound) {
                sound.volume = 0.7;
                sound.addEventListener('error', () => {
                    console.log('사운드 파일 로드 실패');
                });
            }
        });
    } catch (e) {
        console.log('사운드 초기화 실패:', e);
    }
}

// 모든 사운드 정지
function stopAllSounds() {
    Object.values(sounds).forEach(sound => {
        if (sound) {
            try {
                sound.pause();
                sound.currentTime = 0;
            } catch (e) {
                console.log('사운드 정지 실패:', e);
            }
        }
    });
    console.log('🔇 모든 사운드 정지됨');
}

// 각 돼지의 초기 체력 설정
function initStamina() {
    for (let i = 1; i <= GAME.pigCount; i++) {
        const staminaBar = document.querySelector(`#pigCard${i} .stamina-fill`);
        const staminaValue = parseInt(staminaBar.style.getPropertyValue('--stamina'));
        GAME.stamina[i] = staminaValue;
    }
}

// 베팅 입력 이벤트
document.addEventListener('DOMContentLoaded', function() {
    initStamina();
    initSounds(); // 사운드 초기화 추가
    
    // 베팅 입력 이벤트
    for (let i = 1; i <= GAME.pigCount; i++) {
        const input = document.getElementById(`bet${i}`);
        const card = document.getElementById(`pigCard${i}`);
        
        input.addEventListener('input', function() {
            const value = parseInt(this.value) || 0;
            
            // 현재 베팅 상황 확인
            let currentBetCount = 0;
            for (let j = 1; j <= GAME.pigCount; j++) {
                const betValue = parseInt(document.getElementById(`bet${j}`).value) || 0;
                if (betValue > 0 && j !== i) {
                    currentBetCount++;
                }
            }
            
            // 이미 최대 마리수를 베팅했고, 현재 입력이 새로운 베팅이면 차단
            if (currentBetCount >= GAME.maxCount && value > 0 && !this.classList.contains('has-bet')) {
                alert(`최대 ${GAME.maxCount}마리까지만 베팅 가능합니다!`);
                this.value = '';
                return;
            }
            
            if (value > 0) {
                this.classList.add('has-bet');
                card.classList.add('selected');
            } else {
                this.classList.remove('has-bet');
                card.classList.remove('selected');
            }
            
            updateBetSummary();
            updateBettingStatus(); // 베팅 가능 상태 업데이트
        });
        
        // 카드 클릭 시 포커스
        card.addEventListener('click', function() {
            const betInput = document.getElementById(`bet${i}`);
            // 이미 최대 마리수를 베팅했으면 알림
            let currentBetCount = 0;
            for (let j = 1; j <= GAME.pigCount; j++) {
                const betValue = parseInt(document.getElementById(`bet${j}`).value) || 0;
                if (betValue > 0) {
                    currentBetCount++;
                }
            }
            
            if (currentBetCount >= GAME.maxCount && !betInput.classList.contains('has-bet')) {
                alert(`최대 ${GAME.maxCount}마리까지만 베팅 가능합니다!`);
                return;
            }
            
            betInput.focus();
        });
    }
});

// 베팅 가능 상태 업데이트
function updateBettingStatus() {
    let betCount = 0;
    
    // 현재 베팅한 돼지 수 계산
    for (let i = 1; i <= GAME.pigCount; i++) {
        const bet = parseInt(document.getElementById(`bet${i}`).value) || 0;
        if (bet > 0) {
            betCount++;
        }
    }
    
    // 최대 베팅 수에 도달하면 베팅하지 않은 돼지들 비활성화
    for (let i = 1; i <= GAME.pigCount; i++) {
        const input = document.getElementById(`bet${i}`);
        const card = document.getElementById(`pigCard${i}`);
        
        if (betCount >= GAME.maxCount && !input.classList.contains('has-bet')) {
            input.disabled = true;
            card.style.opacity = '0.5';
            card.style.cursor = 'not-allowed';
        } else {
            input.disabled = false;
            card.style.opacity = '1';
            card.style.cursor = 'pointer';
        }
    }
}

// 베팅 요약 업데이트
function updateBetSummary() {
    let count = 0;
    let total = 0;
    
    for (let i = 1; i <= GAME.pigCount; i++) {
        const bet = parseInt(document.getElementById(`bet${i}`).value) || 0;
        if (bet > 0) {
            count++;
            total += bet;
        }
    }
    
    document.getElementById('betCount').textContent = count;
    document.getElementById('betTotal').textContent = total.toLocaleString() + 'P';
}

// 게임 시작
function startGame() {
    if (GAME.isRunning) return;
    
    // 베팅 검증
    let totalBet = 0;
    let betCount = 0;
    GAME.bets = {};
    
    for (let i = 1; i <= GAME.pigCount; i++) {
        const bet = parseInt(document.getElementById(`bet${i}`).value) || 0;
        GAME.bets[i] = bet;
        if (bet > 0) {
            totalBet += bet;
            betCount++;
        }
    }
    
    if (betCount === 0) {
        alert('베팅할 돼지를 선택하세요!');
        return;
    }
    
    if (betCount > GAME.maxCount) {
        alert(`최대 ${GAME.maxCount}마리까지 베팅 가능합니다!`);
        return;
    }
    
    if (totalBet < GAME.minPoint) {
        alert(`최소 ${GAME.minPoint.toLocaleString()}P 이상 베팅하세요!`);
        return;
    }
    
    if (totalBet > GAME.money) {
        alert('포인트가 부족합니다!');
        return;
    }
    
    // 모든 사운드 정지
    stopAllSounds();
    
    // 게임 시작
    GAME.isRunning = true;
    GAME.positions = {};
    GAME.ranks = {};
    GAME.currentRank = 1;
    GAME.events = {};
    
    // UI 전환
    document.getElementById('startBtn').disabled = true;
    
    // 포인트 차감 (시각적)
    GAME.money -= totalBet;
    document.getElementById('currentPoints').textContent = GAME.money.toLocaleString() + 'P';
    
    console.log('🏁 경기 시작!', GAME.bets);
    
    // 실제 베팅 전송
    sendBet(totalBet);
    
    // 화면 전환
    setTimeout(() => {
        document.getElementById('selectionScreen').style.display = 'none';
        document.getElementById('raceScreen').classList.add('active');
        
        // 카운트다운 후 레이스 시작
        countdown(() => {
            // 시작 사운드 재생
            if (sounds.start1) {
                sounds.start1.currentTime = 0;
                sounds.start1.play().catch(() => console.log('start1 사운드 재생 실패'));
            }
            if (sounds.start2) {
                sounds.start2.currentTime = 0;
                sounds.start2.play().catch(() => console.log('start2 사운드 재생 실패'));
            }
            
            startRace();
        });
    }, 500);
}

// 카운트다운 (음성 포함)
function countdown(callback) {
    let count = 3;
    const overlay = document.createElement('div');
    overlay.className = 'countdown-overlay';
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 120px;
        color: white;
        font-weight: bold;
        z-index: 10000;
    `;
    document.body.appendChild(overlay);
    
    // 음성 합성 API 사용
    const synth = window.speechSynthesis;
    const voices = synth.getVoices();
    
    function speak(text) {
        if (synth.speaking) {
            synth.cancel();
        }
        
        const utterance = new SpeechSynthesisUtterance(text);
        
        // 남성 음성 찾기 (영어 우선)
        const maleVoice = voices.find(voice => 
            voice.name.toLowerCase().includes('male') || 
            voice.name.toLowerCase().includes('david') ||
            voice.name.toLowerCase().includes('james') ||
            voice.name.toLowerCase().includes('daniel')
        );
        
        if (maleVoice) {
            utterance.voice = maleVoice;
        }
        
        utterance.rate = 1.0; // 정상 속도
        utterance.pitch = 0.8; // 낮은 음높이 (굵은 목소리)
        utterance.volume = 1.0; // 최대 볼륨
        utterance.lang = 'en-US'; // 영어로 설정
        
        synth.speak(utterance);
    }
    
    function tick() {
        if (count > 0) {
            overlay.textContent = count;
            
            // 영어로 카운트다운
            switch(count) {
                case 3:
                    speak('Three!');
                    break;
                case 2:
                    speak('Two!');
                    break;
                case 1:
                    speak('One!');
                    break;
            }
            
            count--;
            setTimeout(tick, 1000);
        } else {
            overlay.textContent = 'GO!';
            speak('GO GO GO!');
            
            setTimeout(() => {
                document.body.removeChild(overlay);
                callback();
            }, 500);
        }
    }
    
    // 음성 목록이 로드된 후 시작
    if (voices.length === 0) {
        synth.addEventListener('voiceschanged', () => {
            tick();
        });
    } else {
        tick();
    }
}

// 상태 메시지 표시
function showStatusMessage(pigNum, message) {
    const runner = document.getElementById(`runner${pigNum}`);
    const msgEl = document.createElement('div');
    msgEl.className = 'status-message';
    msgEl.textContent = message;
    runner.appendChild(msgEl);
    
    setTimeout(() => {
        runner.removeChild(msgEl);
    }, 2000);
}

// 특수 이벤트 발생
function triggerSpecialEvent(pigNum) {
    const rand = Math.random();
    const runner = document.getElementById(`runner${pigNum}`);
    
    // 이미 이벤트가 있으면 확률 낮추기
    if (GAME.events[pigNum]) {
        if (Math.random() > 0.3) return; // 30% 확률로만 새 이벤트
    }
    
    // 이벤트 종류 결정
    if (rand < 0.05) { // 5% - 잠들기
        GAME.events[pigNum] = {type: 'sleep', duration: 15};
        runner.classList.add('sleeping');
        showStatusMessage(pigNum, '💤 잠들었다!');
    } else if (rand < 0.10) { // 5% - 멍때리기
        GAME.events[pigNum] = {type: 'confused', duration: 10};
        runner.classList.add('confused');
        showStatusMessage(pigNum, '😵 멍때린다!');
    } else if (rand < 0.15) { // 5% - 터보
        GAME.events[pigNum] = {type: 'turbo', duration: 8};
        runner.classList.add('turbo');
        showStatusMessage(pigNum, '🚀 터보 발동!');
    } else if (rand < 0.18) { // 3% - 역주행
        GAME.events[pigNum] = {type: 'backwards', duration: 5};
        runner.classList.add('backwards');
        showStatusMessage(pigNum, '🔄 역주행!');
    }
}

// 이벤트 처리
function processEvent(pigNum) {
    const event = GAME.events[pigNum];
    if (!event) return 1; // 이벤트 없으면 정상 속도
    
    const runner = document.getElementById(`runner${pigNum}`);
    
    // 이벤트 지속시간 감소
    event.duration--;
    
    // 이벤트 종료 체크
    if (event.duration <= 0) {
        runner.classList.remove('sleeping', 'confused', 'turbo', 'backwards');
        delete GAME.events[pigNum];
        return 1;
    }
    
    // 이벤트별 속도 배율
    switch(event.type) {
        case 'sleep': return 0; // 완전 정지
        case 'confused': return 0.2; // 매우 느림
        case 'turbo': return 3; // 3배속
        case 'backwards': return -1; // 역방향
        default: return 1;
    }
}

// 레이스 시작
function startRace() {
    // 초기화
    for (let i = 1; i <= GAME.pigCount; i++) {
        GAME.positions[i] = 0;
        const runner = document.getElementById(`runner${i}`);
        const staminaBar = document.getElementById(`stamina${i}`);
        const pigInfo = document.querySelector(`#track${i} .pig-info`);
        
        runner.classList.add('running');
        runner.style.left = '100px';
        
        // 먼지 효과 추가
        addDustEffect(runner);
        
        // 내가 베팅한 돼지의 레인 번호 표시 변경
        if (GAME.bets[i] > 0) {
            pigInfo.classList.add('my-bet');
        }
        
        // 체력바 초기화
        staminaBar.style.setProperty('--stamina', GAME.stamina[i] + '%');
    }
    
    // 레이스 진행
    let step = 0;
    
    GAME.interval = setInterval(() => {
        step++;
        let finished = 0;
        
        for (let i = 1; i <= GAME.pigCount; i++) {
            if (!GAME.ranks[i]) {
                // 특수 이벤트 발생 체크
                if (step % 5 === 0) { // 1초마다 체크
                    triggerSpecialEvent(i);
                }
                
                // 이벤트 처리
                const eventMultiplier = processEvent(i);
                
                // 체력에 따른 속도 계산
                const staminaFactor = GAME.stamina[i] / 100;
                const baseSpeed = Math.random() * 10 + 5;
                const move = baseSpeed * staminaFactor * eventMultiplier;
                
                GAME.positions[i] += move;
                
                // 역주행 시 위치 제한
                if (GAME.positions[i] < 0) GAME.positions[i] = 0;
                
                // 체력 감소 (이벤트에 따라 다르게)
                if (eventMultiplier > 1) { // 터보일 때 체력 더 빨리 감소
                    GAME.stamina[i] = Math.max(0, GAME.stamina[i] - (Math.random() * 1.5));
                } else if (eventMultiplier === 0) { // 잠들었을 때 체력 회복
                    GAME.stamina[i] = Math.min(100, GAME.stamina[i] + 0.5);
                } else {
                    GAME.stamina[i] = Math.max(0, GAME.stamina[i] - (Math.random() * 0.5));
                }
                
                // UI 업데이트
                const runner = document.getElementById(`runner${i}`);
                const staminaBar = document.getElementById(`stamina${i}`);
                const track = document.getElementById(`track${i}`);
                const finishLine = track.querySelector('.finish-line');
                
                // 트랙 너비와 결승선 위치 계산
                const trackWidth = track.offsetWidth;
                const finishLinePos = trackWidth - 50; // CSS에서 right: 50px로 설정됨
                const maxRunDistance = finishLinePos - 100; // 시작점(100px)에서 결승선까지의 거리
                
                // 진행률 계산 (0~1000 범위를 0~1로 변환)
                const progress = Math.min(GAME.positions[i] / 1000, 1);
                const currentPosition = 100 + (progress * maxRunDistance);
                
                runner.style.left = currentPosition + 'px';
                staminaBar.style.setProperty('--stamina', GAME.stamina[i] + '%');
                
                // 완주 체크 - 결승선에 도달했을 때
                if (currentPosition >= finishLinePos && !GAME.ranks[i]) {
                    GAME.ranks[i] = GAME.currentRank++;
                    runner.classList.remove('running', 'sleeping', 'confused', 'turbo', 'backwards');
                    runner.classList.add('winner');
                    delete GAME.events[i]; // 이벤트 제거
                    
                    // 먼지 효과 제거
                    removeDustEffect(runner);
                    
                    console.log(`🏁 ${i}번 돼지 ${GAME.ranks[i]}등으로 완주! (위치: ${currentPosition}px, 결승선: ${finishLinePos}px)`);
                    
                    // 등수 표시 추가
                    if (GAME.ranks[i] <= 3) {
                        const rankBadge = document.createElement('div');
                        rankBadge.className = `rank-badge rank-${GAME.ranks[i]}`;
                        rankBadge.textContent = GAME.ranks[i] + '등';
                        runner.appendChild(rankBadge);
                    }
                }
            }
            
            if (GAME.ranks[i]) finished++;
        }
        
        // 디버그 로그
        if (step % 25 === 0) {
            const leaderPig = Object.keys(GAME.positions).reduce((a, b) => 
                GAME.positions[a] > GAME.positions[b] ? a : b
            );
            console.log(`⏱️ ${step * 0.2}초 경과, 완주: ${finished}/${GAME.pigCount}, 선두: ${leaderPig}번 (진행: ${Math.floor(GAME.positions[leaderPig] / 10)}%)`);
        }
        
        // 모든 돼지 완주 체크
        if (finished >= GAME.pigCount) {
            console.log('🏁 모든 돼지 완주! 게임 종료');
            endRace();
        } else if (step > 300) { // 60초 시간 초과
            console.log('⏰ 시간 초과! 게임 종료');
            // 아직 완주하지 못한 돼지들에게 순위 부여
            const unfinished = [];
            for (let i = 1; i <= GAME.pigCount; i++) {
                if (!GAME.ranks[i]) {
                    unfinished.push({pig: i, pos: GAME.positions[i]});
                }
            }
            
            // 위치 순으로 정렬하여 순위 부여
            unfinished.sort((a, b) => b.pos - a.pos);
            unfinished.forEach(item => {
                GAME.ranks[item.pig] = GAME.currentRank++;
                console.log(`⏰ ${item.pig}번 돼지 ${GAME.ranks[item.pig]}등 (시간초과, 진행: ${Math.floor(item.pos / 10)}%)`);
            });
            
            endRace();
        }
    }, 200);
}

// 먼지 효과 추가 함수
function addDustEffect(runner) {
    // 먼지 컨테이너 생성
    const dustContainer = document.createElement('div');
    dustContainer.className = 'dust-container';
    
    // 먼지 파티클 5개 생성
    for (let i = 0; i < 5; i++) {
        const particle = document.createElement('div');
        particle.className = 'dust-particle';
        dustContainer.appendChild(particle);
    }
    
    // 모래바람 효과 컨테이너
    const sandstorm = document.createElement('div');
    sandstorm.className = 'sandstorm-effect';
    
    // 모래바람 파티클 3개 생성
    for (let i = 0; i < 3; i++) {
        const sandParticle = document.createElement('div');
        sandParticle.className = 'sandstorm-particle';
        sandstorm.appendChild(sandParticle);
    }
    
    runner.appendChild(dustContainer);
    runner.appendChild(sandstorm);
}

// 먼지 효과 제거 함수
function removeDustEffect(runner) {
    const dustContainer = runner.querySelector('.dust-container');
    const sandstorm = runner.querySelector('.sandstorm-effect');
    
    if (dustContainer) runner.removeChild(dustContainer);
    if (sandstorm) runner.removeChild(sandstorm);
}

// 레이스 종료
function endRace() {
    clearInterval(GAME.interval);
    GAME.isRunning = false;
    
    console.log('🏁 레이스 종료 - endRace() 시작');
    console.log('베팅 정보:', GAME.bets);
    console.log('순위 정보:', GAME.ranks);
    
    // 모든 사운드 즉시 정지
    stopAllSounds();
    
    // 애니메이션 정리
    for (let i = 1; i <= GAME.pigCount; i++) {
        const track = document.getElementById(`track${i}`);
        const runner = document.getElementById(`runner${i}`);
        track.classList.remove('racing');
        // 먼지 효과 제거
        removeDustEffect(runner);
    }

    // 상금 계산
    let totalPrize = 0;
    let results = [];

    for (let i = 1; i <= GAME.pigCount; i++) {
        const bet = GAME.bets[i] || 0;
        const rank = GAME.ranks[i] || 999;
        
        if (bet > 0) {
            let prize = 0;
            
            if (rank === 1) {
                prize = bet * GAME.point1;
                console.log(`✅ ${i}번 돼지 1등! 베팅: ${bet}P, 상금: ${prize}P`);
            } else if (rank === 2) {
                prize = bet * GAME.point2;
                console.log(`✅ ${i}번 돼지 2등! 베팅: ${bet}P, 상금: ${prize}P`);
            } else if (rank === 3) {
                prize = GAME.point3;
                console.log(`✅ ${i}번 돼지 3등! 베팅: ${bet}P, 상금: ${prize}P`);
            }
            
            if (prize > 0) {
                totalPrize += prize;
                results.push({pig: i, rank, bet, prize});
            }
        }
    }

    console.log('🏁 경기 종료!', results, '총 상금:', totalPrize);

    // 결과 처리를 지연시켜서 애니메이션이 끝난 후 실행
    setTimeout(() => {
        if (totalPrize > 0) {
            // 상금 지급
            GAME.money += totalPrize;
            document.getElementById('currentPoints').textContent = GAME.money.toLocaleString() + 'P';
            
            // 서버에 상금 전송
            sendWin(totalPrize);
            
            // 환호 사운드 (짧게)
            if (sounds.cheer) {
                sounds.cheer.currentTime = 0;
                sounds.cheer.play().catch(() => console.log('cheer 사운드 재생 실패'));
                // 5초 후 자동 정지
                setTimeout(() => {
                    if (sounds.cheer) {
                        sounds.cheer.pause();
                        sounds.cheer.currentTime = 0;
                    }
                }, 5000);
            }
            
            // 시상식 팝업
            showOlympicResult(results, totalPrize);
        } else {
            // 꽝인 경우도 팝업으로 표시
            showLoseResult();
        }
    }, 500); // 0.5초 후에 팝업 표시
}

// 시상식 결과 표시
function showOlympicResult(results, totalPrize) {
    console.log('🎊 showOlympicResult 함수 호출됨');
    
    const allRanks = Object.keys(GAME.ranks).map(pig => ({
        pig: parseInt(pig),
        rank: GAME.ranks[pig]
    })).sort((a, b) => a.rank - b.rank);
    
    console.log('🏆 최종 순위:', allRanks);
    
    const top3 = results.filter(r => r.rank <= 3).sort((a, b) => a.rank - b.rank);
    
    let podiumHTML = '';
    
    // 올림픽 순서로 배치 (2-1-3)
    const olympicOrder = [];
    const secondResult = top3.find(r => r.rank === 2);
    const firstResult = top3.find(r => r.rank === 1);
    const thirdResult = top3.find(r => r.rank === 3);
    
    if (secondResult) olympicOrder.push(secondResult);
    if (firstResult) olympicOrder.push(firstResult);
    if (thirdResult) olympicOrder.push(thirdResult);
    
    olympicOrder.forEach(result => {
        const rankClass = result.rank === 1 ? 'first' : result.rank === 2 ? 'second' : 'third';
        const medalEmoji = result.rank === 1 ? '🥇' : result.rank === 2 ? '🥈' : '🥉';
        const medalClass = result.rank === 1 ? 'gold' : result.rank === 2 ? 'silver' : 'bronze';
        
        podiumHTML += `
            <div class="podium-position podium-${rankClass}">
                <div class="podium-pig ${rankClass}">
                    <img src="./img/snail${result.pig}.gif" 
                         alt="돼지${result.pig}"
                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iMzgiIGZpbGw9IiNGRjZCNkIiLz4KPHRleHQgeD0iNDAiIHk9IjQ1IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LXNpemU9IjI0cHgiIGZpbGw9IndoaXRlIj7wn5C3PC90ZXh0Pgo8L3N2Zz4=';">
                </div>
                <div class="medal ${medalClass}">${medalEmoji}</div>
                <div class="podium-rank ${rankClass}">${result.pig}번 선수</div>
            </div>
        `;
    });
    
    // 전체 순위 정보 추가
    let rankInfoHTML = `
        <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 15px; padding: 20px; margin: 20px 0; border: 2px solid #28a745;">
            <h4 style="color: #2c5530; margin-bottom: 15px; text-align: center;">🏁 최종 순위</h4>
            <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
    `;
    
    allRanks.forEach(item => {
        const emoji = item.rank === 1 ? '🥇' : item.rank === 2 ? '🥈' : item.rank === 3 ? '🥉' : '🏃';
        const bgColor = item.rank === 1 ? '#FFD700' : item.rank === 2 ? '#C0C0C0' : item.rank === 3 ? '#CD7F32' : '#f8f9fa';
        const textColor = item.rank <= 3 ? 'white' : '#333';
        
        rankInfoHTML += `
            <div style="background: ${bgColor}; color: ${textColor}; padding: 8px 15px; border-radius: 20px; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                ${emoji} ${item.rank}등: ${item.pig}번
            </div>
        `;
    });
    
    rankInfoHTML += '</div></div>';
    
    // 상세 결과 정보
    let summaryHTML = '<div class="race-summary">';
    results.forEach(result => {
        const emoji = result.rank === 1 ? '🥇' : result.rank === 2 ? '🥈' : '🥉';
        summaryHTML += `
            <div class="summary-item">
                <div class="summary-left">
                    <span style="font-size: 1.3em;">${emoji}</span>
                    <strong>${result.pig}번 선수 - ${result.rank}등</strong>
                </div>
                <div class="summary-right">
                    ${result.bet.toLocaleString()}P → ${result.prize.toLocaleString()}P
                </div>
            </div>
        `;
    });
    summaryHTML += '</div>';
    
    const html = `
        <div class="celebration-title">🎉 스타디움 시상식 🎉</div>
        <div class="prize-info">총 ${totalPrize.toLocaleString()}P 획득!</div>
        
        ${rankInfoHTML}
        
        <div class="olympic-podium">
            ${podiumHTML}
        </div>
        
        ${summaryHTML}
        
        <div style="margin-top: 25px; padding: 18px; background: linear-gradient(135deg, #e8f5e8, #d1f2d1); border-radius: 12px; font-size: 1em; color: #155724; border: 2px solid #28a745;">
            🎊 실제 포인트가 지급되었습니다! 🎊
        </div>
    `;
    
    document.getElementById('olympicResult').innerHTML = html;
    document.getElementById('olympicPopup').style.display = 'block';
    
    console.log('✅ 팝업 표시 완료');
}

// 꽝 결과 표시
function showLoseResult() {
    console.log('😢 showLoseResult 함수 호출됨');
    
    const allRanks = Object.keys(GAME.ranks).map(pig => ({
        pig: parseInt(pig),
        rank: GAME.ranks[pig]
    })).sort((a, b) => a.rank - b.rank);
    
    // 전체 순위 정보
    let rankInfoHTML = `
        <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 15px; padding: 20px; margin: 20px 0; border: 2px solid #dc3545;">
            <h4 style="color: #dc3545; margin-bottom: 15px; text-align: center;">🏁 최종 순위</h4>
            <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
    `;
    
    allRanks.forEach(item => {
        const emoji = item.rank === 1 ? '🥇' : item.rank === 2 ? '🥈' : item.rank === 3 ? '🥉' : '🏃';
        const bgColor = item.rank === 1 ? '#FFD700' : item.rank === 2 ? '#C0C0C0' : item.rank === 3 ? '#CD7F32' : '#f8f9fa';
        const textColor = item.rank <= 3 ? 'white' : '#333';
        
        rankInfoHTML += `
            <div style="background: ${bgColor}; color: ${textColor}; padding: 8px 15px; border-radius: 20px; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                ${emoji} ${item.rank}등: ${item.pig}번
            </div>
        `;
    });
    
    rankInfoHTML += '</div></div>';
    
    const html = `
        <div class="celebration-title">😢 아쉽게도 꽝입니다!</div>
        <div class="prize-info" style="color: #dc3545; border-color: #dc3545; background: linear-gradient(135deg, #f8d7da, #f5c6cb);">
            다음 기회에 도전하세요!
        </div>
        
        ${rankInfoHTML}
        
        <div style="margin-top: 25px; padding: 18px; background: linear-gradient(135deg, #f8d7da, #f5c6cb); border-radius: 12px; font-size: 1em; color: #721c24; border: 2px solid #dc3545;">
            💡 다시 도전하여 행운을 잡으세요!
        </div>
    `;
    
    document.getElementById('olympicResult').innerHTML = html;
    document.getElementById('olympicPopup').style.display = 'block';
    
    console.log('✅ 꽝 팝업 표시 완료');
}

// 꽝 결과 표시
function showLoseResult() {
    const allRanks = Object.keys(GAME.ranks).map(pig => ({
        pig: parseInt(pig),
        rank: GAME.ranks[pig]
    })).sort((a, b) => a.rank - b.rank);
    
    // 전체 순위 정보
    let rankInfoHTML = `
        <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 15px; padding: 20px; margin: 20px 0; border: 2px solid #dc3545;">
            <h4 style="color: #dc3545; margin-bottom: 15px; text-align: center;">🏁 최종 순위</h4>
            <div style="display: flex; justify-content: center; gap: 15px; flex-wrap: wrap;">
    `;
    
    allRanks.forEach(item => {
        const emoji = item.rank === 1 ? '🥇' : item.rank === 2 ? '🥈' : item.rank === 3 ? '🥉' : '🏃';
        const bgColor = item.rank === 1 ? '#FFD700' : item.rank === 2 ? '#C0C0C0' : item.rank === 3 ? '#CD7F32' : '#f8f9fa';
        const textColor = item.rank <= 3 ? 'white' : '#333';
        
        rankInfoHTML += `
            <div style="background: ${bgColor}; color: ${textColor}; padding: 8px 15px; border-radius: 20px; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.2);">
                ${emoji} ${item.rank}등: ${item.pig}번
            </div>
        `;
    });
    
    rankInfoHTML += '</div></div>';
    
    const html = `
        <div class="celebration-title">😢 아쉽게도 꽝입니다!</div>
        <div class="prize-info" style="color: #dc3545; border-color: #dc3545; background: linear-gradient(135deg, #f8d7da, #f5c6cb);">
            다음 기회에 도전하세요!
        </div>
        
        ${rankInfoHTML}
        
        <div style="margin-top: 25px; padding: 18px; background: linear-gradient(135deg, #f8d7da, #f5c6cb); border-radius: 12px; font-size: 1em; color: #721c24; border: 2px solid #dc3545;">
            💡 다시 도전하여 행운을 잡으세요!
        </div>
    `;
    
    document.getElementById('olympicResult').innerHTML = html;
    document.getElementById('olympicPopup').style.display = 'block';
}

// 확인 버튼 클릭
function confirmResult() {
    console.log('🔄 확인 버튼 클릭 - 게임 리셋 시작');
    
    // 팝업 닫기
    document.getElementById('olympicPopup').style.display = 'none';
    
    // 게임 리셋 및 배팅 화면으로 전환
    setTimeout(() => {
        resetGame();
    }, 300);
}

// 게임 리셋
function resetGame() {
    console.log('🔄 게임 리셋 중...');
    
    GAME.isRunning = false;
    GAME.positions = {};
    GAME.ranks = {};
    GAME.currentRank = 1;
    GAME.events = {};
    GAME.bets = {};
    
    // 모든 사운드 정지
    stopAllSounds();
    
    if (GAME.interval) {
        clearInterval(GAME.interval);
        GAME.interval = null;
    }

    // 화면 전환 - 배팅 화면으로 돌아가기
    document.getElementById('raceScreen').classList.remove('active');
    document.getElementById('raceScreen').style.display = 'none';
    document.getElementById('selectionScreen').style.display = 'flex';
    
    // UI 리셋
    document.getElementById('startBtn').disabled = false;
    
    // 체력 재설정
    for (let i = 1; i <= GAME.pigCount; i++) {
        const newStamina = Math.floor(Math.random() * 31) + 70; // 70-100 사이의 새로운 체력
        GAME.stamina[i] = newStamina;
        const staminaBar = document.querySelector(`#pigCard${i} .stamina-fill`);
        if (staminaBar) {
            staminaBar.style.setProperty('--stamina', newStamina + '%');
        }
    }
    
    for (let i = 1; i <= GAME.pigCount; i++) {
        const bet = document.getElementById(`bet${i}`);
        const card = document.getElementById(`pigCard${i}`);
        const track = document.getElementById(`track${i}`);
        const runner = document.getElementById(`runner${i}`);
        const pigInfo = document.querySelector(`#track${i} .pig-info`);
        
        // 베팅 입력 리셋
        if (bet) {
            bet.disabled = false;
            bet.value = '';
            bet.classList.remove('has-bet');
        }
        
        // 카드 리셋
        if (card) {
            card.classList.remove('selected');
            card.style.opacity = '1';
            card.style.cursor = 'pointer';
        }
        
        // 트랙 리셋
        if (track) {
            track.classList.remove('racing');
        }
        
        // 러너 리셋
        if (runner) {
            runner.style.left = '100px';
            runner.classList.remove('running', 'winner', 'my-bet', 'sleeping', 'confused', 'turbo', 'backwards');
            
            // 등수 배지 제거
            const rankBadge = runner.querySelector('.rank-badge');
            if (rankBadge) {
                runner.removeChild(rankBadge);
            }
        }
        
        // 레인 정보 리셋
        if (pigInfo) {
            pigInfo.classList.remove('my-bet');
        }
    }
    
    // 베팅 요약 리셋
    updateBetSummary();
    updateBettingStatus();
    
    // 서버에서 현재 포인트 다시 가져오기 위해 페이지 새로고침
    // 실제 포인트와 동기화를 위해 필요할 수 있음
    if (confirm('게임이 종료되었습니다. 페이지를 새로고침하여 포인트를 업데이트하시겠습니까?')) {
        location.reload();
    }
    
    console.log('✅ 게임 리셋 완료 - 배팅 화면으로 전환됨');
}

// 서버 통신
function sendBet(amount) {
    console.log('💰 서버에 베팅 전송:', amount + 'P');
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'resgame_on.php';
    form.target = 'hiddenFrame';
    form.style.display = 'none';
    
    const data = {
        gstc: '1',
        pmpoint: amount,
        tokenkey: GAME.money,  // 차감 후 남은 포인트
        gamekey: <?php echo $current_point?>
    };
    
    Object.keys(data).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = data[key];
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    
    if (!document.querySelector('iframe[name="hiddenFrame"]')) {
        const iframe = document.createElement('iframe');
        iframe.name = 'hiddenFrame';
        iframe.style.display = 'none';
        document.body.appendChild(iframe);
    }
    
    form.submit();
    document.body.removeChild(form);
}

function sendWin(amount) {
    console.log('🎉 서버에 상금 전송:', amount + 'P');
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'resgame_on.php';
    form.target = 'hiddenFrame';
    form.style.display = 'none';
    
    const data = {
        gstc: '2',
        okmoney: amount,
        tokenkey: GAME.money,  // 상금 포함된 현재 포인트
        gamekey: <?php echo $current_point?>
    };
    
    Object.keys(data).forEach(key => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = data[key];
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// 확인 버튼 클릭
function confirmResult() {
    console.log('🔄 확인 버튼 클릭 - 게임 리셋 시작');
    closePopup();
    resetGame();
}

// 팝업 닫기
function closePopup() {
    document.getElementById('olympicPopup').style.display = 'none';
    // 팝업 닫을 때도 사운드 정지
    stopAllSounds();
}

// 팝업 외부 클릭 시 닫기
window.onclick = function(event) {
    const popup = document.getElementById('olympicPopup');
    if (event.target === popup) {
        closePopup();
        resetGame();
    }
};

// 페이지 언로드 시 모든 사운드 정지
window.addEventListener('beforeunload', function() {
    stopAllSounds();
});

console.log('🏟️ 돼지레이싱 스타디움 준비 완료!');
</script>

<iframe name="hiddenFrame" style="display:none;"></iframe>

<?php include_once("./_tail.php"); ?>