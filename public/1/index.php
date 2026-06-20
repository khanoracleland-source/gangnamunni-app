<?php
// 서울미녀 Landing Page
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>서울미녀 - 미용의료 플랫폼</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;700;900&display=swap');
        * { font-family: 'Noto Sans KR', sans-serif; }
        body {
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
            min-height: 100vh;
        }
        .glass {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-glow {
            background: linear-gradient(135deg, #ec4899 0%, #f43f5e 50%, #ef4444 100%);
            box-shadow: 0 0 30px rgba(236,72,153,0.4);
            transition: all 0.3s ease;
        }
        .btn-glow:hover {
            box-shadow: 0 0 50px rgba(236,72,153,0.7);
            transform: translateY(-2px) scale(1.02);
        }
        .float-anim { animation: float 6s ease-in-out infinite; }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-20px)} }
        .fade-in { animation: fadeIn 1s ease-out forwards; opacity: 0; }
        @keyframes fadeIn { to { opacity: 1; } }
        .delay-1 { animation-delay: 0.2s; }
        .delay-2 { animation-delay: 0.4s; }
        .delay-3 { animation-delay: 0.6s; }
        .delay-4 { animation-delay: 0.8s; }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:hover { transform: translateY(-5px); border-color: rgba(236,72,153,0.5); }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <div class="max-w-4xl w-full">
        <div class="text-center mb-12 fade-in">
            <div class="float-anim inline-block mb-6">
                <div class="w-20 h-20 rounded-2xl btn-glow flex items-center justify-center mx-auto">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
            </div>
            <h1 class="text-5xl font-black text-white mb-4 tracking-tight">서울<span class="text-transparent bg-clip-text bg-gradient-to-r from-pink-400 to-rose-400">미녀</span></h1>
            <p class="text-gray-400 text-lg">미용의료 통합 플랫폼</p>
        </div>

        <div class="text-center mb-12 fade-in delay-1">
            <a href="/hairwang/gangnamunni/" target="_blank" rel="noopener" class="btn-glow inline-flex items-center gap-4 px-10 py-5 rounded-2xl text-white text-xl font-bold no-underline">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                서울미녀 서비스 입장
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                </svg>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12 fade-in delay-2">
            <a href="/hairwang/gangnamunni/" target="_blank" rel="noopener" class="glass rounded-2xl p-6 card-hover block no-underline">
                <div class="text-3xl mb-3">🏥</div>
                <h3 class="text-white font-bold text-lg mb-2">병원 검색</h3>
                <p class="text-gray-400 text-sm">지역별 미용병원 검색 및 상세 정보 확인</p>
                <div class="mt-4 text-pink-400 text-sm font-medium">99개 병원 등록 →</div>
            </a>
            <a href="/hairwang/gangnamunni/dev_dashboard.php" target="_blank" rel="noopener" class="glass rounded-2xl p-6 card-hover block no-underline">
                <div class="text-3xl mb-3">📊</div>
                <h3 class="text-white font-bold text-lg mb-2">개발 대시보드</h3>
                <p class="text-gray-400 text-sm">개발 진행 상황 추적 및 태스크 관리</p>
                <div class="mt-4 text-pink-400 text-sm font-medium">38개 태스크 →</div>
            </a>
            <a href="/" target="_blank" rel="noopener" class="glass rounded-2xl p-6 card-hover block no-underline">
                <div class="text-3xl mb-3">⚕️</div>
                <h3 class="text-white font-bold text-lg mb-2">HMS 관리</h3>
                <p class="text-gray-400 text-sm">InfyHMS 병원 관리 시스템</p>
                <div class="mt-4 text-pink-400 text-sm font-medium">관리자 패널 →</div>
            </a>
        </div>

        <div class="glass rounded-2xl p-6 fade-in delay-3">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div><div class="text-2xl font-black text-white">176</div><div class="text-gray-500 text-xs mt-1">DB 테이블</div></div>
                <div><div class="text-2xl font-black text-white">99</div><div class="text-gray-500 text-xs mt-1">등록 병원</div></div>
                <div><div class="text-2xl font-black text-white">77</div><div class="text-gray-500 text-xs mt-1">회원 수</div></div>
                <div><div class="text-2xl font-black text-white">6</div><div class="text-gray-500 text-xs mt-1">개발 단계</div></div>
            </div>
        </div>

        <div class="text-center mt-8 fade-in delay-4">
            <p class="text-gray-600 text-sm">서울미녀 Platform © 2026 | Powered by InfyHMS + Gnuboard</p>
        </div>
    </div>
</body>
</html>
