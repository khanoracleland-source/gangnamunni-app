import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kivicare_patient/screens/webview/app_auth_gate_screen.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const channel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');

  for (final failedOperation in ['read', 'write']) {
    testWidgets('storage $failedOperation failure allows retry without clearing tokens',
        (tester) async {
      final calls = <String>[];
      TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
          .setMockMethodCallHandler(channel, (call) async {
        calls.add(call.method);
        if (call.method == failedOperation) {
          throw PlatformException(code: 'storage_unavailable');
        }
        return null;
      });
      addTearDown(() {
        TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
            .setMockMethodCallHandler(channel, null);
      });

      await tester.pumpWidget(const MaterialApp(
        home: AppAuthGateScreen(url: 'https://hms.seoulbeauty.app/hairwang/gangnamunni/'),
      ));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      expect(find.byType(CircularProgressIndicator), findsNothing);
      expect(find.widgetWithText(ElevatedButton, '다시 시도'), findsOneWidget);
      final attempts = calls.where((method) => method == failedOperation).length;
      await tester.tap(find.widgetWithText(ElevatedButton, '다시 시도'));
      await tester.pumpAndSettle();
      expect(calls.where((method) => method == failedOperation).length, attempts + 1);
      expect(calls, isNot(contains('deleteAll')));
      expect(calls, isNot(contains('delete')));
      expect(tester.takeException(), isNull);
    });
  }
}
