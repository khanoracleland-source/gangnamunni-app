<?php
include_once("./_common.php");
include_once("./setup.php");

// 로그인 확인 제거 - 누구나 플레이 가능
// 포인트 시스템 완전 제거

include_once("./_head.php");
?>

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
/* 전체 게임 컨테이너 */
.pig-game {
    max-width: 1200px;
    margin: 0 auto;
    background: linear-gradient(135deg, #5a3ab8, #7a4efe);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(122, 78, 254, 0.3);
    overflow: hidden;
    position: relative;
}

/* 헤더 스타일 */
.pig-header {
    background: linear-gradient(135deg, #7a4efe, #9d7aff, #b39dff);
    padding: 30px;
    text-align: center;
    color: white;
    position: relative;
    z-index: 1;
    box-shadow: 0 4px 20px rgba(122, 78, 254, 0.3);
}

.pig-title {
    font-size: 2.5em;
    font-weight: bold;
    margin: 0 0 15px 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    letter-spacing: 2px;
}

.pig-subtitle {
    font-size: 1.2em;
    opacity: 0.9;
}

/* 돼지 선택 화면 (시작 전) */
.pig-selection {
    padding: 40px 20px;
    background: linear-gradient(180deg, rgba(26,26,26,0.8), rgba(45,45,45,0.8));
    position: relative;
    min-height: 500px;
    display: flex;
    flex-direction: column;
    align-items: center;
    background-image: 
        linear-gradient(180deg, rgba(26,26,26,0.8), rgba(45,45,45,0.8)),
        url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><rect fill="%234a7c59" width="1200" height="600"/><rect fill="%238B4513" x="0" y="100" width="1200" height="80"/><rect fill="%238B4513" x="0" y="220" width="1200" height="80"/><rect fill="%238B4513" x="0" y="340" width="1200" height="80"/><rect fill="%238B4513" x="0" y="460" width="1200" height="80"/><line stroke="%23fff" stroke-width="3" stroke-dasharray="10,5" x1="0" y1="140" x2="1200" y2="140"/><line stroke="%23fff" stroke-width="3" stroke-dasharray="10,5" x1="0" y1="260" x2="1200" y2="260"/><line stroke="%23fff" stroke-width="3" stroke-dasharray="10,5" x1="0" y1="380" x2="1200" y2="380"/><line stroke="%23fff" stroke-width="3" stroke-dasharray="10,5" x1="0" y1="500" x2="1200" y2="500"/></svg>');
    background-size: cover;
    background-position: center;
}

/* 돼지 라인업 컨테이너 */
.pig-lineup {
    width: 100%;
    max-width: 1000px;
    margin: 20px auto;
}

/* 돼지 카드 그리드 - 한 줄에 5개 배치 */
.pig-cards {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 15px;
    padding: 20px;
    max-width: 1100px;
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

.pig-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.4);
}

/* 돼지 번호 */
.pig-number {
    position: absolute;
    top: 10px;
    right: 10px;
    background: #7a4efe;
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
    width: 80px;
    height: 80px;
    margin: 0 auto 15px;
    overflow: hidden;
}

.pig-avatar img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    transform: scaleX(-1); /* 좌우 반전 */
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

/* 이름 입력 */
.pig-name-input {
    margin-top: 15px;
}

.name-input {
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

.name-input:focus {
    outline: none;
    border-color: #7a4efe;
    box-shadow: 0 0 10px rgba(122, 78, 254, 0.3);
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
    background: linear-gradient(135deg, #7a4efe, #9d7aff);
    color: white;
    border: none;
    padding: 25px 60px;
    font-size: 28px;
    font-weight: bold;
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 10px 40px rgba(122, 78, 254, 0.5);
    border: 4px solid rgba(255,255,255,0.3);
    text-transform: uppercase;
    letter-spacing: 2px;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0% { transform: scale(1); box-shadow: 0 10px 40px rgba(122, 78, 254, 0.5); }
    50% { transform: scale(1.05); box-shadow: 0 15px 50px rgba(122, 78, 254, 0.7); }
    100% { transform: scale(1); box-shadow: 0 10px 40px rgba(122, 78, 254, 0.5); }
}

.start-button:hover {
    background: linear-gradient(135deg, #6a3eee, #8d6aff);
    transform: scale(1.1);
    animation: none;
}

.start-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    animation: none;
}

/* 참가자 목록 */
.participants-list {
    background: rgba(255,255,255,0.1);
    padding: 20px;
    border-radius: 15px;
    color: white;
    text-align: center;
    max-width: 100%;
    width: 100%;
}

.participants-list h3 {
    margin: 0 0 15px 0;
    font-size: 20px;
    color: #b39dff;
}

#participantsContainer {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.participant-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    background: rgba(255,255,255,0.05);
    border-radius: 25px;
    border: 1px solid rgba(179, 157, 255, 0.3);
}

.participant-number {
    background: #7a4efe;
    color: white;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.participant-name {
    font-size: 16px;
    color: white;
    font-weight: 500;
}

/* 경주 트랙 (게임 중) */
.pig-tracks {
    padding: 30px 20px;
    background: linear-gradient(180deg, #2d5016, #3d6b2a);
    position: relative;
    z-index: 1;
    display: none;
    overflow: visible;
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
    overflow: visible;
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
    font-size: 14px;
    background: linear-gradient(135deg, #1e3c72, #2a5298);
    color: white;
    padding: 8px 12px;
    border-radius: 20px;
    box-shadow: 0 3px 10px rgba(30, 60, 114, 0.4);
    z-index: 10;
    max-width: 120px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* 돼지 러너 */
.pig-runner {
    position: absolute;
    left: 100px;
    top: 50%;
    transform: translateY(-50%);
    width: 50px;
    height: 50px;
    transition: left 0.8s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    z-index: 10;
    overflow: visible;
}

.pig-runner img {
    width: 100%;
    height: 100%;
    background: transparent;
    transform: scaleX(-1); /* 좌우 반전 */
}

.pig-runner.running img {
    animation: pigRunning 0.4s ease-in-out infinite;
    transform: scaleX(-1); /* 좌우 반전 유지 */
}

.pig-runner.winner img {
    animation: pigWinner 0.8s ease-in-out infinite alternate;
    filter: brightness(1.4) saturate(1.3);
    transform: scaleX(-1); /* 좌우 반전 유지 */
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
}

.pig-runner.backwards img {
    transform: scaleX(1); /* 역주행일 때는 원래 방향으로 */
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
    0%, 100% { transform: scaleX(-1) scale(1) rotate(-5deg); }
    50% { transform: scaleX(-1) scale(1.1) rotate(5deg) translateY(-3px); }
}

@keyframes pigWinner {
    0% { transform: scaleX(-1) scale(1) rotate(0deg); }
    100% { transform: scaleX(-1) scale(1.15) rotate(10deg); }
}

@keyframes sleeping {
    0%, 100% { transform: scaleX(-1) scale(1) rotate(-10deg); }
    50% { transform: scaleX(-1) scale(0.9) rotate(-10deg) translateY(5px); }
}

@keyframes confused {
    0% { transform: scaleX(-1) rotate(0deg); }
    25% { transform: scaleX(-1) rotate(-15deg); }
    75% { transform: scaleX(-1) rotate(15deg); }
    100% { transform: scaleX(-1) rotate(0deg); }
}

@keyframes turbo {
    0%, 100% { transform: scaleX(-1) scale(1) translateX(0); }
    50% { transform: scaleX(-1) scale(1.2) translateX(2px); }
}

@keyframes backwards {
    0%, 100% { transform: scaleX(1) rotate(-5deg); }  /* 역주행은 다시 반전 */
    50% { transform: scaleX(1) rotate(5deg) translateY(-2px); }
}

/* 상태 메시지 */
.status-message {
    position: absolute;
    top: -45px;
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
    z-index: 100;
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
@media (max-width: 1200px) {
    .pig-cards {
        grid-template-columns: repeat(5, 1fr);
        gap: 10px;
        padding: 15px;
    }
    
    .pig-card {
        padding: 12px;
    }
    
    .pig-avatar {
        width: 60px;
        height: 60px;
    }
    
    .name-input {
        font-size: 11px;
        padding: 6px;
    }
    
    #participantsContainer {
        gap: 10px;
    }
    
    .participant-item {
        padding: 8px 12px;
    }
}

@media (max-width: 768px) {
    .pig-cards {
        grid-template-columns: repeat(5, 1fr);
        gap: 8px;
        padding: 10px;
    }
    
    .pig-card {
        padding: 10px;
    }
    
    .pig-avatar {
        width: 50px;
        height: 50px;
    }
    
    .name-input {
        font-size: 10px;
        padding: 5px;
    }
    
    .start-button {
        padding: 20px 40px;
        font-size: 22px;
    }
    
    .pig-number {
        width: 25px;
        height: 25px;
        font-size: 14px;
    }
    
    #participantsContainer {
        gap: 8px;
        overflow-x: auto;
        padding-bottom: 5px;
    }
    
    .participant-item {
        padding: 6px 10px;
        white-space: nowrap;
    }
    
    .participant-name {
        font-size: 14px;
    }
}

@media (max-width: 480px) {
    .pig-cards {
        grid-template-columns: repeat(5, 1fr);
        gap: 5px;
        padding: 8px;
        overflow-x: auto;
        min-width: 400px;
    }
    
    .pig-card {
        padding: 8px;
        min-width: 70px;
    }
    
    .pig-avatar {
        width: 40px;
        height: 40px;
    }
    
    .name-input {
        font-size: 9px;
        padding: 4px;
    }
    
    .pig-selection {
        overflow-x: auto;
    }
    
    #participantsContainer {
        gap: 5px;
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 10px;
    }
    
    .participant-item {
        padding: 5px 8px;
        flex-shrink: 0;
    }
    
    .participant-number {
        width: 20px;
        height: 20px;
        font-size: 12px;
    }
    
    .participant-name {
        font-size: 12px;
    }
    
    .participants-list {
        padding: 15px;
    }
    
    .participants-list h3 {
        font-size: 18px;
        margin-bottom: 10px;
    }
}

/* 올림픽 스타일 시상식 팝업 */
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
    overflow-y: auto;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.olympic-content {
    position: relative;
    margin: 50px auto;
    background: linear-gradient(135deg, #ffffff, #f8f9fa);
    padding: 40px;
    border-radius: 25px;
    text-align: center;
    max-width: 600px;
    width: 90%;
    box-shadow: 0 30px 60px rgba(0,0,0,0.4);
    animation: slideUp 0.4s ease;
    border: 3px solid #ffd700;
    max-height: 90vh;
    overflow-y: auto;
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

.winner-announcement {
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
    transform: scaleX(-1); /* 좌우 반전 */
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
    color: #2c5530;
    font-size: 1.1em;
}

/* 확인 버튼 스타일 */
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

</style>

<div class="pig-game">
    <div class="pig-header">
        <h1 class="pig-title">헤어왕 레이싱 스타디움</h1>
        <div class="pig-subtitle">각 헤어왕에게 이름을 지어주고 레이스를 시작하세요!</div>
    </div>
    
    <!-- 돼지 선택 화면 -->
    <div class="pig-selection" id="selectionScreen">
        <div class="pig-lineup">
            <div class="pig-cards">
                <?php for($i = 1; $i <= 5; $i++): ?>
                <div class="pig-card" id="pigCard<?php echo $i?>">
                    <div class="pig-number"><?php echo $i?></div>
                    <div class="pig-avatar">
                        <img src="./img/<?php echo $i?>.png" alt="헤어왕<?php echo $i?>" 
                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iMzgiIGZpbGw9IiM3YTRlZmUiLz4KPHRleHQgeD0iNDAiIHk9IjQ1IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LXNpemU9IjI0cHgiIGZpbGw9IndoaXRlIj7wn5CKPC90ZXh0Pgo8L3N2Zz4=';">
                    </div>
                    <div class="pig-stamina">
                        <span class="stamina-label">스타일 파워</span>
                        <div class="stamina-bar">
                            <div class="stamina-fill" style="--stamina: <?php echo rand(70, 100)?>%;"></div>
                        </div>
                    </div>
                    <div class="pig-name-input">
                        <input type="text" class="name-input" id="name<?php echo $i?>" 
                               placeholder="이름 입력" maxlength="10">
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        
        <!-- 시작 버튼과 참가자 목록 -->
        <div class="game-controls">
            <div class="participants-list" id="participantsList" style="display: none;">
                <h3>🏃 참가자 명단</h3>
                <div id="participantsContainer"></div>
            </div>
            
            <button class="start-button" onclick="startGame()" id="startBtn">🚀 레이스 시작!</button>
        </div>
    </div>
    
    <!-- 경주 트랙 화면 -->
    <div class="pig-tracks" id="raceScreen">
        <?php for($i = 1; $i <= 5; $i++): ?>
        <div class="pig-track" id="track<?php echo $i?>">
            <div class="finish-line"></div>
            <div class="pig-info" id="pigInfo<?php echo $i?>"><?php echo $i?>번</div>
            <div class="pig-runner" id="runner<?php echo $i?>">
                <div class="runner-stamina">
                    <div class="runner-stamina-fill" id="stamina<?php echo $i?>"></div>
                </div>
                <img src="./img/<?php echo $i?>.png" alt="헤어왕<?php echo $i?>">
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div class="pig-guide">
        <h3>👑 게임 안내</h3>
        <p><strong>게임 방법:</strong> 각 헤어왕에게 이름을 지어주고 레이스를 시작하세요!</p>
        <p><strong>스타일 파워:</strong> 각 헤어왕마다 다른 스타일 파워를 가지고 있어 경주 결과에 영향을 줍니다</p>
        <p><strong>특수 기술:</strong> 💫 헤어 번개, 🌪️ 헤어 토네이도, ✨ 글리터 부스트, 🎨 컬러 체인지 등 다양한 이벤트 발생!</p>
        <p><strong>재미있는 레이싱:</strong> 친구들과 함께 즐기는 스타일리시한 헤어왕 레이싱!</p>
    </div>
</div>

<!-- 올림픽 스타일 시상식 팝업 -->
<div class="olympic-popup" id="olympicPopup">
    <div class="olympic-content">
        <span class="olympic-close" onclick="closePopup()">&times;</span>
        <div id="olympicResult"></div>
        <button class="confirm-button" onclick="confirmResult()">다시 하기</button>
    </div>
</div>

<script>
// 게임 설정
const GAME = {
    pigCount: 5,
    
    // 게임 상태
    isRunning: false,
    positions: {},
    ranks: {},
    stamina: {},
    currentRank: 1,
    interval: null,
    names: {},
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

// 이름 입력 이벤트 처리
document.addEventListener('DOMContentLoaded', function() {
    initStamina();
    initSounds();
    
    // 이름 입력 이벤트
    for (let i = 1; i <= GAME.pigCount; i++) {
        const input = document.getElementById(`name${i}`);
        
        input.addEventListener('input', function() {
            updateParticipantsList();
        });
        
        // 엔터키 입력 시 다음 입력으로 이동
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (i < GAME.pigCount) {
                    document.getElementById(`name${i+1}`).focus();
                } else {
                    startGame();
                }
            }
        });
    }
});

// 참가자 목록 업데이트
function updateParticipantsList() {
    let hasAnyName = false;
    let participantsHTML = '';
    
    for (let i = 1; i <= GAME.pigCount; i++) {
        const name = document.getElementById(`name${i}`).value.trim();
        if (name) {
            hasAnyName = true;
            participantsHTML += `
                <div class="participant-item">
                    <div class="participant-number">${i}</div>
                    <div class="participant-name">${name}</div>
                </div>
            `;
        }
    }
    
    const participantsList = document.getElementById('participantsList');
    const participantsContainer = document.getElementById('participantsContainer');
    
    if (hasAnyName) {
        participantsList.style.display = 'block';
        participantsContainer.innerHTML = participantsHTML;
    } else {
        participantsList.style.display = 'none';
    }
}

// 게임 시작
function startGame() {
    if (GAME.isRunning) return;
    
    // 이름 수집
    GAME.names = {};
    let hasAtLeastOneName = false;
    
    for (let i = 1; i <= GAME.pigCount; i++) {
        const nameInput = document.getElementById(`name${i}`);
        const name = nameInput.value.trim();
        
        if (name) {
            GAME.names[i] = name;
            hasAtLeastOneName = true;
        } else {
            GAME.names[i] = `${i}번 헤어왕`;
        }
    }
    
    if (!hasAtLeastOneName) {
        alert('최소한 하나의 이름은 입력해주세요!');
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
    
    console.log('🏁 경기 시작!', GAME.names);
    
    // 화면 전환
    setTimeout(() => {
        document.getElementById('selectionScreen').style.display = 'none';
        document.getElementById('raceScreen').classList.add('active');
        
        // 트랙에 이름 표시
        for (let i = 1; i <= GAME.pigCount; i++) {
            document.getElementById(`pigInfo${i}`).textContent = GAME.names[i];
        }
        
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
        
        utterance.rate = 1.0;
        utterance.pitch = 0.8;
        utterance.volume = 1.0;
        utterance.lang = 'en-US';
        
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
        if (Math.random() > 0.3) return;
    }
    
    // 이벤트 종류 결정
    if (rand < 0.05) { // 5% - 헤어 꼬임
        GAME.events[pigNum] = {type: 'sleep', duration: 15};
        runner.classList.add('sleeping');
        showStatusMessage(pigNum, '💫 헤어가 꼬였다!');
    } else if (rand < 0.10) { // 5% - 스타일링 체크
        GAME.events[pigNum] = {type: 'confused', duration: 10};
        runner.classList.add('confused');
        showStatusMessage(pigNum, '💆 스타일 체크중!');
    } else if (rand < 0.15) { // 5% - 헤어 번개
        GAME.events[pigNum] = {type: 'turbo', duration: 8};
        runner.classList.add('turbo');
        showStatusMessage(pigNum, '⚡ 헤어 번개 발동!');
    } else if (rand < 0.18) { // 3% - 바람에 날림
        GAME.events[pigNum] = {type: 'backwards', duration: 5};
        runner.classList.add('backwards');
        showStatusMessage(pigNum, '🌪️ 바람에 날려간다!');
    }
}

// 이벤트 처리
function processEvent(pigNum) {
    const event = GAME.events[pigNum];
    if (!event) return 1;
    
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
        case 'sleep': return 0;
        case 'confused': return 0.2;
        case 'turbo': return 3;
        case 'backwards': return -1;
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
        
        runner.classList.add('running');
        runner.style.left = '100px';
        
        // 먼지 효과 추가
        addDustEffect(runner);
        
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
                if (step % 5 === 0) {
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
                
                // 체력 감소
                if (eventMultiplier > 1) {
                    GAME.stamina[i] = Math.max(0, GAME.stamina[i] - (Math.random() * 1.5));
                } else if (eventMultiplier === 0) {
                    GAME.stamina[i] = Math.min(100, GAME.stamina[i] + 0.5);
                } else {
                    GAME.stamina[i] = Math.max(0, GAME.stamina[i] - (Math.random() * 0.5));
                }
                
                // UI 업데이트
                const runner = document.getElementById(`runner${i}`);
                const staminaBar = document.getElementById(`stamina${i}`);
                const track = document.getElementById(`track${i}`);
                
                const trackWidth = track.offsetWidth;
                const finishLinePos = trackWidth - 50;
                const maxRunDistance = finishLinePos - 100;
                
                const progress = Math.min(GAME.positions[i] / 1000, 1);
                const currentPosition = 100 + (progress * maxRunDistance);
                
                runner.style.left = currentPosition + 'px';
                staminaBar.style.setProperty('--stamina', GAME.stamina[i] + '%');
                
                // 완주 체크
                if (currentPosition >= finishLinePos && !GAME.ranks[i]) {
                    GAME.ranks[i] = GAME.currentRank++;
                    runner.classList.remove('running', 'sleeping', 'confused', 'turbo', 'backwards');
                    runner.classList.add('winner');
                    delete GAME.events[i];
                    
                    removeDustEffect(runner);
                    
                    console.log(`🏁 ${GAME.names[i]} ${GAME.ranks[i]}등으로 완주!`);
                    
                    // 등수 표시
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
        
        // 모든 돼지 완주 체크
        if (finished >= GAME.pigCount) {
            console.log('🏁 모든 돼지 완주! 게임 종료');
            endRace();
        } else if (step > 300) {
            console.log('⏰ 시간 초과! 게임 종료');
            
            const unfinished = [];
            for (let i = 1; i <= GAME.pigCount; i++) {
                if (!GAME.ranks[i]) {
                    unfinished.push({pig: i, pos: GAME.positions[i]});
                }
            }
            
            unfinished.sort((a, b) => b.pos - a.pos);
            unfinished.forEach(item => {
                GAME.ranks[item.pig] = GAME.currentRank++;
                console.log(`⏰ ${GAME.names[item.pig]} ${GAME.ranks[item.pig]}등 (시간초과)`);
            });
            
            endRace();
        }
    }, 100);
}

// 먼지 효과 추가 함수
function addDustEffect(runner) {
    const dustContainer = document.createElement('div');
    dustContainer.className = 'dust-container';
    
    for (let i = 0; i < 5; i++) {
        const particle = document.createElement('div');
        particle.className = 'dust-particle';
        dustContainer.appendChild(particle);
    }
    
    runner.appendChild(dustContainer);
}

// 먼지 효과 제거 함수
function removeDustEffect(runner) {
    const dustContainer = runner.querySelector('.dust-container');
    if (dustContainer) runner.removeChild(dustContainer);
}

// 레이스 종료
function endRace() {
    clearInterval(GAME.interval);
    GAME.isRunning = false;
    
    console.log('🏁 레이스 종료');
    
    stopAllSounds();
    
    // 애니메이션 정리
    for (let i = 1; i <= GAME.pigCount; i++) {
        const runner = document.getElementById(`runner${i}`);
        removeDustEffect(runner);
    }

    // 결과 처리
    setTimeout(() => {
        // 환호 사운드
        if (sounds.cheer) {
            sounds.cheer.currentTime = 0;
            sounds.cheer.play().catch(() => console.log('cheer 사운드 재생 실패'));
            setTimeout(() => {
                if (sounds.cheer) {
                    sounds.cheer.pause();
                    sounds.cheer.currentTime = 0;
                }
            }, 5000);
        }
        
        // 시상식 팝업
        showOlympicResult();
    }, 500);
}

// 시상식 결과 표시
function showOlympicResult() {
    console.log('🎊 시상식 시작');
    
    const allRanks = Object.keys(GAME.ranks).map(pig => ({
        pig: parseInt(pig),
        rank: GAME.ranks[pig],
        name: GAME.names[pig]
    })).sort((a, b) => a.rank - b.rank);
    
    console.log('🏆 최종 순위:', allRanks);
    
    // 1등 찾기
    const winner = allRanks.find(r => r.rank === 1);
    
    // 상위 3명 추출
    const top3 = allRanks.slice(0, 3);
    
    let podiumHTML = '';
    
    // 올림픽 순서로 배치 (2-1-3)
    const olympicOrder = [];
    const second = top3.find(r => r.rank === 2);
    const first = top3.find(r => r.rank === 1);
    const third = top3.find(r => r.rank === 3);
    
    if (second) olympicOrder.push(second);
    if (first) olympicOrder.push(first);
    if (third) olympicOrder.push(third);
    
    olympicOrder.forEach(result => {
        const rankClass = result.rank === 1 ? 'first' : result.rank === 2 ? 'second' : 'third';
        const medalEmoji = result.rank === 1 ? '🥇' : result.rank === 2 ? '🥈' : '🥉';
        const medalClass = result.rank === 1 ? 'gold' : result.rank === 2 ? 'silver' : 'bronze';
        
        podiumHTML += `
            <div class="podium-position podium-${rankClass}">
                <div class="podium-pig ${rankClass}">
                    <img src="./img/${result.pig}.png" 
                         alt="${result.name}"
                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iNDAiIGN5PSI0MCIgcj0iMzgiIGZpbGw9IiM3YTRlZmUiLz4KPHRleHQgeD0iNDAiIHk9IjQ1IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LXNpemU9IjI0cHgiIGZpbGw9IndoaXRlIj7wn5CKPC90ZXh0Pgo8L3N2Zz4=';">
                </div>
                <div class="medal ${medalClass}">${medalEmoji}</div>
                <div class="podium-rank ${rankClass}">${result.name}</div>
            </div>
        `;
    });
    
    // 전체 순위 정보
    let rankInfoHTML = `
        <div style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 15px; padding: 20px; margin: 20px 0; border: 2px solid #28a745;">
            <h4 style="color: #2c5530; margin-bottom: 15px; text-align: center;">🏁 최종 순위</h4>
            <div style="display: flex; flex-direction: column; gap: 10px;">
    `;
    
    allRanks.forEach(item => {
        const emoji = item.rank === 1 ? '🥇' : item.rank === 2 ? '🥈' : item.rank === 3 ? '🥉' : '🏃';
        const bgColor = item.rank === 1 ? '#FFD700' : item.rank === 2 ? '#C0C0C0' : item.rank === 3 ? '#CD7F32' : '#f8f9fa';
        const textColor = item.rank <= 3 ? 'white' : '#333';
        
        rankInfoHTML += `
            <div style="background: ${bgColor}; color: ${textColor}; padding: 12px 20px; border-radius: 25px; font-weight: bold; box-shadow: 0 2px 8px rgba(0,0,0,0.2); text-align: center;">
                ${emoji} ${item.rank}등: ${item.name}
            </div>
        `;
    });
    
    rankInfoHTML += '</div></div>';
    
    const html = `
        <div class="celebration-title">🎉 레이싱 시상식 🎉</div>
        <div class="winner-announcement">🏆 우승: ${winner.name}!</div>
        
        ${rankInfoHTML}
        
        <div class="olympic-podium">
            ${podiumHTML}
        </div>
        
        <div style="margin-top: 25px; padding: 18px; background: linear-gradient(135deg, #e8f5e8, #d1f2d1); border-radius: 12px; font-size: 1em; color: #155724; border: 2px solid #28a745; text-align: center;">
            🎊 축하합니다! 멋진 레이스였습니다! 🎊
        </div>
    `;
    
    document.getElementById('olympicResult').innerHTML = html;
    document.getElementById('olympicPopup').style.display = 'block';
    
    console.log('✅ 팝업 표시 완료');
}

// 확인 버튼 클릭
function confirmResult() {
    console.log('🔄 확인 버튼 클릭 - 게임 리셋');
    closePopup();
    resetGame();
}

// 팝업 닫기
function closePopup() {
    document.getElementById('olympicPopup').style.display = 'none';
    stopAllSounds();
}

// 게임 리셋
function resetGame() {
    console.log('🔄 게임 리셋 중...');
    
    GAME.isRunning = false;
    GAME.positions = {};
    GAME.ranks = {};
    GAME.currentRank = 1;
    GAME.events = {};
    GAME.names = {};
    
    stopAllSounds();
    
    if (GAME.interval) {
        clearInterval(GAME.interval);
        GAME.interval = null;
    }

    // 화면 전환
    document.getElementById('raceScreen').classList.remove('active');
    document.getElementById('raceScreen').style.display = 'none';
    document.getElementById('selectionScreen').style.display = 'flex';
    
    // UI 리셋
    document.getElementById('startBtn').disabled = false;
    
    // 체력 재설정
    for (let i = 1; i <= GAME.pigCount; i++) {
        const newStamina = Math.floor(Math.random() * 31) + 70;
        GAME.stamina[i] = newStamina;
        const staminaBar = document.querySelector(`#pigCard${i} .stamina-fill`);
        if (staminaBar) {
            staminaBar.style.setProperty('--stamina', newStamina + '%');
        }
    }
    
    for (let i = 1; i <= GAME.pigCount; i++) {
        const nameInput = document.getElementById(`name${i}`);
        const runner = document.getElementById(`runner${i}`);
        
        // 이름 입력 유지 (사용자가 원하면 다시 사용할 수 있도록)
        // nameInput.value = '';
        
        // 러너 리셋
        if (runner) {
            runner.style.left = '100px';
            runner.classList.remove('running', 'winner', 'sleeping', 'confused', 'turbo', 'backwards');
            
            // 등수 배지 제거
            const rankBadge = runner.querySelector('.rank-badge');
            if (rankBadge) {
                runner.removeChild(rankBadge);
            }
        }
    }
    
    // 참가자 목록 업데이트
    updateParticipantsList();
    
    console.log('✅ 게임 리셋 완료');
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

console.log('👑 헤어왕 레이싱 스타디움 준비 완료!');
</script>

<?php include_once("./_tail.php"); ?>