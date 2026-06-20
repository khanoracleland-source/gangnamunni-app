// firebase-messaging-sw.js
// 이 파일은 자동으로 생성되었습니다.
// 생성일시: 2025-08-31 18:07:46

importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging-compat.js');

// Firebase 설정
firebase.initializeApp({
    apiKey: "",
    authDomain: "",
    projectId: "",
    storageBucket: "",
    messagingSenderId: "",
    appId: ""
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
        event.waitUntil(
            clients.openWindow(clickAction)
        );
    } else if (event.action === 'close') {
        event.notification.close();
    } else {
        event.waitUntil(
            clients.matchAll({
                type: 'window',
                includeUncontrolled: true
            }).then(function(clientList) {
                for (let i = 0; i < clientList.length; i++) {
                    const client = clientList[i];
                    if (client.url === clickAction && 'focus' in client) {
                        return client.focus();
                    }
                }
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