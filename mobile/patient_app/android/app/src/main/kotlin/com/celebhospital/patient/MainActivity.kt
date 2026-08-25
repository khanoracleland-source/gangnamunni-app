package com.celebhospital.patient

import android.webkit.CookieManager
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import io.flutter.embedding.android.FlutterFragmentActivity

class MainActivity: FlutterFragmentActivity() {
    private val cookieChannel = "seoulbeauty.app/webview_cookie"

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            cookieChannel
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "flush" -> {
                    CookieManager.getInstance().flush()
                    result.success(null)
                }
                else -> result.notImplemented()
            }
        }
    }

    override fun onStop() {
        CookieManager.getInstance().flush()
        super.onStop()
    }
}
