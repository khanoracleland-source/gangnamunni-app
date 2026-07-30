import 'dart:convert';
import 'dart:io';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/screens/booking/appointment_detail_screen.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:uuid/uuid.dart';

import '../configs.dart';
import '../main.dart';
import '../models/notificationdata_model.dart';
import '../network/network_utils.dart';
import '../screens/booking/model/appointments_res_model.dart';
import '../services/deep_link_service.dart';
import 'api_end_points.dart';
import 'app_common.dart';
import 'common_base.dart';
import 'constants.dart';

class PushNotificationService {
// It is assumed that all messages contain a data field with the key 'type'
  String _hospitalApiUrl(String endpoint) {
    final baseUrl = HOSPITAL_API_BASE_URL.endsWith('/')
        ? HOSPITAL_API_BASE_URL
        : '$HOSPITAL_API_BASE_URL/';
    return '$baseUrl$endpoint';
  }

  Future<void> setupFirebaseMessaging() async {
    await _createAndroidNotificationChannel();
    await initFirebaseMessaging();
    await _subscribeToBroadcastTopic();
    FirebaseMessaging.instance.onTokenRefresh.listen(
      (token) async {
        try {
          await _registerTokenWithBackend(token);
        } catch (error) {
          log('FCM refreshed token registration error: $error');
        }
      },
      onError: (error) => log('FCM token refresh error: $error'),
    );

    await enableIOSNotifications();
  }

  Future<void> _subscribeToBroadcastTopic() async {
    if (!Platform.isAndroid) return;

    try {
      await FirebaseMessaging.instance.subscribeToTopic(appNameTopic);
      log("${FirebaseTopicConst.topicSubscribed}$appNameTopic");
    } catch (error) {
      log('FCM broadcast topic subscription error: $error');
    }
  }

  Future<void> _createAndroidNotificationChannel() async {
    if (!Platform.isAndroid) return;

    const channel = AndroidNotificationChannel(
      FirebaseTopicConst.notificationChannelIdKey,
      FirebaseTopicConst.notificationChannelNameKey,
      description: 'Hospital notifications',
      importance: Importance.high,
      enableLights: true,
      playSound: true,
    );
    await FlutterLocalNotificationsPlugin()
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);
  }

  Future<void> initFirebaseMessaging() async {
    late final NotificationSettings settings;
    try {
      settings = await FirebaseMessaging.instance.requestPermission(
        provisional: true,
      );
    } catch (e) {
      log('------Request Notification Permission ERROR-----------');
      return;
    }

    if (settings.authorizationStatus == AuthorizationStatus.authorized) {
      log('------Request Notification Permission COMPLETED-----------');
      await registerNotificationListeners().then((value) {
        log('------Notification Listener REGISTRATION COMPLETED-----------');
      }).catchError((e) {
        log('------Notification Listener REGISTRATION ERROR-----------');
      });

      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

      await FirebaseMessaging.instance
          .setForegroundNotificationPresentationOptions(
              alert: true, badge: true, sound: true)
          .then((value) {
        log('------setForegroundNotificationPresentationOptions COMPLETED-----------');
      }).catchError((e) {
        log('------setForegroundNotificationPresentationOptions ERROR-----------');
      });
    }
  }

  Future<void> registerFCMAndTopics() async {
    if (isLoggedIn.value) {
      if (Platform.isIOS) {
        String? apnsToken = await FirebaseMessaging.instance.getAPNSToken();
        if (apnsToken != null) {
          subScribeToTopic();
        } else {
          Future.delayed(const Duration(seconds: 3), () async {
            apnsToken = await FirebaseMessaging.instance.getAPNSToken();
            if (apnsToken != null) {
              subScribeToTopic();
            }
          });
        }
        log("===============${FirebaseTopicConst.apnsNotificationTokenKey}===============\n$apnsToken");
      }
      try {
        final token = await FirebaseMessaging.instance.getToken();
        if (token != null && token.isNotEmpty) {
          await _registerTokenWithBackend(token);
        }
      } catch (error) {
        log('FCM token registration error: $error');
      }
      await subScribeToTopic();
    }
  }

  Future<String> _getDeviceId() async {
    var deviceId = getStringAsync(SharedPreferenceConst.FCM_DEVICE_ID);
    if (deviceId.isEmpty) {
      deviceId = const Uuid().v4();
      await setValue(SharedPreferenceConst.FCM_DEVICE_ID, deviceId);
    }
    return deviceId;
  }

  Future<void> _registerTokenWithBackend(String token) async {
    if (!Platform.isAndroid || !isLoggedIn.value || token.isEmpty) return;

    final deviceId = await _getDeviceId();
    await handleResponse(
      await buildHttpResponse(
        _hospitalApiUrl(APIEndPoints.registerFcmToken),
        method: HttpMethodType.POST,
        request: {
          'device_id': deviceId,
          'fcm_token': token,
          if (currentPackageinfo.value.versionName.validate().isNotEmpty)
            'app_version': currentPackageinfo.value.versionName.validate(),
          'os_version': Platform.operatingSystemVersion,
        },
      ),
    );
    log('FCM token registered with hospital-api');
  }

  Future<void> unregisterTokenFromBackend() async {
    if (!Platform.isAndroid || !isLoggedIn.value) return;

    final deviceId = await _getDeviceId();
    await handleResponse(
      await buildHttpResponse(
        _hospitalApiUrl(APIEndPoints.unregisterFcmToken),
        method: HttpMethodType.POST,
        request: {'device_id': deviceId},
      ),
    );
    log('FCM token unregistered from hospital-api');
  }

  Future<void> subScribeToTopic() async {
    await _subscribeToBroadcastTopic();
    await FirebaseMessaging.instance
        .subscribeToTopic(
            "${FirebaseTopicConst.userWithUnderscoreKey}${loginUserData.value.id}")
        .then((value) {
      log("${FirebaseTopicConst.topicSubscribed}${FirebaseTopicConst.userWithUnderscoreKey}${loginUserData.value.id}");
    });
  }

  Future<void> unsubscribeFirebaseTopic() async {
    // The global broadcast topic intentionally remains subscribed after logout.
    await FirebaseMessaging.instance
        .unsubscribeFromTopic(
            '${FirebaseTopicConst.userWithUnderscoreKey}${loginUserData.value.id}')
        .then((value) {
      log("${FirebaseTopicConst.topicUnSubscribed}${FirebaseTopicConst.userWithUnderscoreKey}${loginUserData.value.id}");
    });
  }

  void handleNotificationClick(RemoteMessage message,
      {bool isForeGround = false}) {
    printLogsNotificationData(message);

    // 딥링크 처리: deep_link 필드가 있으면 DeepLinkService로 라우팅
    if (message.data.containsKey('deep_link')) {
      final deepLink = message.data['deep_link'];
      if (deepLink is String && deepLink.isNotEmpty) {
        final uri = Uri.tryParse(deepLink);
        if (uri != null) {
          if (!isForeGround) {
            DeepLinkService.instance.handleDeepLink(uri);
          }
          return;
        }
      }
    }

    // route 필드로 간단한 딥링크 처리 (예: "appointment/123", "clinic/456")
    if (message.data.containsKey('route')) {
      final route = message.data['route'];
      if (route is String && route.isNotEmpty) {
        final uri = Uri.tryParse('wellness://$route');
        if (uri != null) {
          if (!isForeGround) {
            DeepLinkService.instance.handleDeepLink(uri);
          }
          return;
        }
      }
    }

    // WebView 페이지 열기: webview_url 필드 처리
    if (message.data.containsKey('webview_url')) {
      final webviewUrl = message.data['webview_url'];
      if (webviewUrl is String && webviewUrl.isNotEmpty) {
        final encoded = Uri.encodeComponent(webviewUrl);
        final uri = Uri.parse('wellness://webview?url=$encoded');
        if (!isForeGround) {
          DeepLinkService.instance.handleDeepLink(uri);
        }
        return;
      }
    }

    // 기존 URL 외부 브라우저 열기 (하위 호환)
    if (message.data['url'] != null && message.data['url'] is String) {
      commonLaunchUrl(message.data['url'],
          launchMode: LaunchMode.externalApplication);
    }

    final NotificationData notificationData =
        NotificationData.fromJson(message.data);
    log("===============${FirebaseTopicConst.notificationDataKey}===============\n${notificationData.toJson()}");
    if (isForeGround) {
      showNotification(
          currentTimeStamp(),
          message.notification!.title.validate(),
          message.notification!.body.validate(),
          message);
    } else {
      log('======== ELSE PART ===============');
      try {
        final Map<String, dynamic> additionalData =
            jsonDecode(message.data[FirebaseTopicConst.additionalDataKey]) ??
                {};
        if (additionalData.isNotEmpty) {
          final int? notId = additionalData[FirebaseTopicConst.idKey];
          log("notId=== $notId");
          if (notId != null) {
            log("additionalData[FirebaseTopicConst.notificationGroupKey]=== ${additionalData[FirebaseTopicConst.notificationGroupKey]}");
            log("============ IN APPOINTMENT ================");
            Get.to(
              () => AppointmentDetail(),
              arguments: AppointmentData(id: notId),
            );
          }
        }
      } catch (e) {
        log('${FirebaseTopicConst.notificationErrorKey}: $e');
      }
    }
  }

  Future<void> registerNotificationListeners() async {
    FirebaseMessaging.onMessage.listen(
      (RemoteMessage message) {
        handleNotificationClick(message, isForeGround: true);
      },
      onError: (e) {
        log("${FirebaseTopicConst.onMessageListen} $e");
      },
    );

    // replacement for onResume: When the app is in the background and opened directly from the push notification.
    FirebaseMessaging.onMessageOpenedApp.listen(
      (RemoteMessage message) {
        handleNotificationClick(message);
      },
      onError: (e) {
        log("${FirebaseTopicConst.onMessageOpened} $e");
      },
    );

    // workaround for onLaunch: When the app is completely closed (not in the background) and opened directly from the push notification
    FirebaseMessaging.instance.getInitialMessage().then(
      (RemoteMessage? message) {
        if (message != null) {
          handleNotificationClick(message);
        }
      },
      onError: (e) {
        log("${FirebaseTopicConst.onGetInitialMessage} $e");
      },
    );
  }

  Future<void> showNotification(
      int id, String title, String message, RemoteMessage remoteMessage) async {
    final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
        FlutterLocalNotificationsPlugin();

    //code for background notification channel
    const AndroidNotificationChannel channel = AndroidNotificationChannel(
      FirebaseTopicConst.notificationChannelIdKey,
      FirebaseTopicConst.notificationChannelNameKey,
      importance: Importance.high,
      enableLights: true,
    );

    await flutterLocalNotificationsPlugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    const AndroidInitializationSettings initializationSettingsAndroid =
        AndroidInitializationSettings('@drawable/ic_stat_notification');

    const iOS = DarwinInitializationSettings(
      requestSoundPermission: false,
      requestBadgePermission: false,
      requestAlertPermission: false,
    );

    const macOS = iOS;

    const InitializationSettings initializationSettings =
        InitializationSettings(
            android: initializationSettingsAndroid, iOS: iOS, macOS: macOS);
    await flutterLocalNotificationsPlugin.initialize(
      initializationSettings,
      onDidReceiveNotificationResponse: (details) {
        handleNotificationClick(remoteMessage);
      },
    );

    const androidPlatformChannelSpecifics = AndroidNotificationDetails(
      FirebaseTopicConst.notificationChannelIdKey,
      FirebaseTopicConst.notificationChannelNameKey,
      importance: Importance.high,
      visibility: NotificationVisibility.public,
      priority: Priority.high,
      icon: '@drawable/ic_stat_notification',
      colorized: true,
    );

    const darwinPlatformChannelSpecifics = DarwinNotificationDetails(
      presentSound: true,
      presentBanner: true,
      presentBadge: true,
    );

    const platformChannelSpecifics = NotificationDetails(
      android: androidPlatformChannelSpecifics,
      iOS: darwinPlatformChannelSpecifics,
      macOS: darwinPlatformChannelSpecifics,
    );

    flutterLocalNotificationsPlugin.show(
        id, title, message, platformChannelSpecifics);
  }

  Future<void> enableIOSNotifications() async {
    await FirebaseMessaging.instance
        .setForegroundNotificationPresentationOptions(
      alert: true, // Required to display a heads up notification
      badge: true,
      sound: true,
    );
  }

  void printLogsNotificationData(RemoteMessage message) {
    log("====================");
    log('${FirebaseTopicConst.notificationDataKey} : ${message.data}');
    log('${FirebaseTopicConst.notificationTitleKey} : ${message.notification?.title}');
    log('${FirebaseTopicConst.notificationBodyKey} : ${message.notification?.body}');
    log('${FirebaseTopicConst.messageDataCollapseKey} : ${message.collapseKey}');
    log('${FirebaseTopicConst.messageDataMessageIdKey} : ${message.messageId}');
    log('${FirebaseTopicConst.messageDataMessageTypeKey} : ${message.messageType}');
  }
}
