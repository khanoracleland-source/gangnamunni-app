<?php
include_once __DIR__ . '/common.php';

header('Content-Type: application/javascript; charset=UTF-8');
header('Service-Worker-Allowed: /');
header('Cache-Control: no-store, max-age=0');

$firebase_config = [
    'apiKey' => (string) ($config['cf_fcm_api_key'] ?? ''),
    'authDomain' => (string) ($config['cf_fcm_auth_domain'] ?? ''),
    'projectId' => (string) ($config['cf_fcm_project_id'] ?? ''),
    'storageBucket' => (string) ($config['cf_fcm_storage_bucket'] ?? ''),
    'messagingSenderId' => (string) ($config['cf_fcm_sender_id'] ?? ''),
    'appId' => (string) ($config['cf_fcm_app_id'] ?? ''),
];
?>
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.7.1/firebase-messaging-compat.js');

const firebaseConfig = <?php echo json_encode($firebase_config, JSON_UNESCAPED_SLASHES); ?>;
let messaging = null;

if (firebaseConfig.apiKey && firebaseConfig.projectId && firebaseConfig.messagingSenderId && firebaseConfig.appId) {
    firebase.initializeApp(firebaseConfig);
    messaging = firebase.messaging();
    messaging.onBackgroundMessage(function(payload) {
        const notification = payload.notification || {};
        const data = payload.data || {};
        return self.registration.showNotification(
            notification.title || data.title || '새로운 알림',
            {
                body: notification.body || data.message || data.body || '내용을 확인하세요.',
                icon: notification.icon || data.icon || '/favicon.ico',
                badge: '/favicon.ico',
                data: data
            }
        );
    });
}

self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    const data = event.notification.data || {};
    let destination = data.click_action || data.url || '/';

    try {
        const target = new URL(destination, self.location.origin);
        destination = target.origin === self.location.origin ? target.href : '/';
    } catch (error) {
        destination = '/';
    }

    event.waitUntil(clients.matchAll({type: 'window', includeUncontrolled: true}).then(function(clientList) {
        for (const client of clientList) {
            if (client.url === destination && 'focus' in client) return client.focus();
        }
        return clients.openWindow ? clients.openWindow(destination) : undefined;
    }));
});

self.addEventListener('install', function() { self.skipWaiting(); });
self.addEventListener('activate', function(event) { event.waitUntil(self.clients.claim()); });
