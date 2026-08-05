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

let messaging = null;
try {
    if (firebase.app().options.projectId) {
        messaging = firebase.messaging();
    }
} catch (error) {
    console.error('[firebase-messaging-sw.js] Firebase initialization failed');
}

// 백그라운드 메시지 수신
if (messaging) messaging.onBackgroundMessage(function(payload) {
    console.log('[firebase-messaging-sw.js] 백그라운드 메시지 수신 ', payload);
    
    const notification = payload.notification || {};
    const data = payload.data || {};
    const notificationTitle = notification.title || data.title || '새로운 알림';
    const notificationOptions = {
        body: notification.body || data.message || data.body || '내용을 확인하세요.',
        icon: notification.icon || data.icon || '/favicon.ico',
        badge: '/favicon.ico',
        data: data,
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
    
    const notificationData = event.notification.data || {};
    let clickAction = notificationData.click_action || notificationData.url || '/';

    try {
        const target = new URL(clickAction, self.location.origin);
        if (target.origin !== self.location.origin) {
            clickAction = '/';
        } else {
            clickAction = target.href;
        }
    } catch (error) {
        clickAction = '/';
    }
    
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
