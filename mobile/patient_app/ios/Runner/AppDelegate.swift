import UIKit
import Flutter
import Firebase

// iOS 27 SDK(Xcode 27) 이상은 UIScene 라이프사이클이 필수입니다.
// 플러그인 등록은 FlutterImplicitEngineDelegate 콜백으로 옮겼고,
// 푸시 알림 delegate / Firebase 초기화는 launch 완료 전에 설정해야 하므로 그대로 둡니다.
@main
@objc class AppDelegate: FlutterAppDelegate, FlutterImplicitEngineDelegate {
  override func application(
    _ application: UIApplication,
    didFinishLaunchingWithOptions launchOptions: [UIApplication.LaunchOptionsKey: Any]?
  ) -> Bool {

    if #available(iOS 10.0, *) {
      UNUserNotificationCenter.current().delegate = self as? UNUserNotificationCenterDelegate
    }
    FirebaseApp.configure()
    return super.application(application, didFinishLaunchingWithOptions: launchOptions)
  }

  func didInitializeImplicitFlutterEngine(_ engineBridge: FlutterImplicitEngineBridge) {
    GeneratedPluginRegistrant.register(with: engineBridge.pluginRegistry)
  }
}
