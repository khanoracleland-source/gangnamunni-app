// firebase-messaging-sw.js
// 루트 디렉토리에 위치해야 함

importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging-compat.js');

// Firebase 설정 (PHP에서 동적으로 생성하거나 하드코딩)
// 실제 운영시에는 별도의 설정 파일에서 가져오는 것을 권장
firebase.initializeApp({
    apiKey: "YOUR_API_KEY",
    authDomain: "YOUR_AUTH_DOMAIN",
    projectId: "YOUR_PROJECT_ID",
    storageBucket: "YOUR_STORAGE_BUCKET",
    messagingSenderId: "YOUR_SENDER_ID",
    appId: "YOUR_APP_ID"
});

const messaging = firebase.messaging();

// 백그라운드 메시지 수신
messaging.onBackgroundMessage(function(payload) {
    console.log('[firebase-messaging-sw.js] 백그라운드 메시지 수신 ', payload);
    
    const notificationTitle = payload.notification.title || '새로운 알림';
    const notificationOptions = {
        body: payload.notification.body || '내용을 확인하세요.',
        icon: payload.notification.icon || '/favicon.ico',
        badge: '/favicon.ico',
        data: payload.data || {},
        requireInteraction: true,
        actions: [
            {
                action: 'open',
                title: '열기'
            },
            {
                action: 'close',
                title: '닫기'
            }
        ]
    };

    self.registration.showNotification(notificationTitle, notificationOptions);
});

// 알림 클릭 이벤트 처리
self.addEventListener('notificationclick', function(event) {
    console.log('[firebase-messaging-sw.js] 알림 클릭:', event);
    
    event.notification.close();
    
    let clickAction = event.notification.data.click_action || '/';
    
    if (event.action === 'open') {
        // 열기 액션
        event.waitUntil(
            clients.openWindow(clickAction)
        );
    } else if (event.action === 'close') {
        // 닫기 액션
        event.notification.close();
    } else {
        // 기본 클릭
        event.waitUntil(
            clients.matchAll({
                type: 'window',
                includeUncontrolled: true
            }).then(function(clientList) {
                // 이미 열려있는 창이 있으면 포커스
                for (let i = 0; i < clientList.length; i++) {
                    const client = clientList[i];
                    if (client.url === clickAction && 'focus' in client) {
                        return client.focus();
                    }
                }
                // 열려있는 창이 없으면 새 창 열기
                if (clients.openWindow) {
                    return clients.openWindow(clickAction);
                }
            })
        );
    }
});

// 서비스 워커 설치
self.addEventListener('install', function(event) {
    console.log('[firebase-messaging-sw.js] 설치됨');
    self.skipWaiting();
});

// 서비스 워커 활성화
self.addEventListener('activate', function(event) {
    console.log('[firebase-messaging-sw.js] 활성화됨');
    event.waitUntil(self.clients.claim());
});