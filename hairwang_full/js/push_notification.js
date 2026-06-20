// FCM 토큰 받기
window.receiveFCMToken = function(token) {
    console.log('FCM Token received:', token);
    
    // 로컬스토리지 저장
    localStorage.setItem('fcm_token', token);
    
    // 그누보드 회원 체크
    if(typeof g5_is_member !== 'undefined' && g5_is_member) {
        savePushTokenToGnuboard(token);
    } else {
        console.log('로그인 후 토큰이 저장됩니다');
    }
};

// 그누보드 서버로 전송
function savePushTokenToGnuboard(token) {
    const platform = /android/i.test(navigator.userAgent) ? 'android' : 'ios';
    
    fetch(g5_bbs_url + '/ajax.push_token.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            token: token,
            user_id: g5_member_id || '',
            platform: platform,
            device_info: {
                user_agent: navigator.userAgent,
                app_version: '1.0.0'
            }
        })
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            console.log('토큰 저장 성공');
        } else {
            console.error('토큰 저장 실패:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

// 페이지 로드시 체크
document.addEventListener('DOMContentLoaded', function() {
    // 로그인 상태이고 저장된 토큰이 있으면 서버로 전송
    if(typeof g5_is_member !== 'undefined' && g5_is_member) {
        const savedToken = localStorage.getItem('fcm_token');
        if(savedToken) {
            savePushTokenToGnuboard(savedToken);
        }
    }
});