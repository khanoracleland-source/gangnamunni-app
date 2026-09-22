import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:get_storage/get_storage.dart';

import '../configs.dart';
import '../utils/app_common.dart';

class CachedAppData<T> {
  const CachedAppData(this.data, {required this.isStale});

  final T data;
  final bool isStale;
}

/// Small local cache for safe GET responses.
///
/// This intentionally uses the existing GetStorage box so logout/account erase
/// also clears cached account data.
class AppCacheService {
  AppCacheService(this.storage, {DateTime Function()? now})
      : _now = now ?? DateTime.now;

  static const prefix = 'sb_page_data_v1:';

  static const clinicListTtl = Duration(minutes: 15);
  static const clinicListMaxStale = Duration(days: 7);
  static const clinicDetailTtl = Duration(minutes: 20);
  static const clinicDetailMaxStale = Duration(days: 7);
  static const serviceListTtl = Duration(minutes: 15);
  static const serviceListMaxStale = Duration(days: 7);
  static const appointmentsTtl = Duration(seconds: 60);
  static const appointmentsMaxStale = Duration(minutes: 30);
  static const dashboardTtl = Duration(minutes: 15);
  static const dashboardMaxStale = Duration(days: 1);

  final GetStorage storage;
  final DateTime Function() _now;

  static String buildKey({
    required String endpoint,
    Map<String, dynamic> queryParameters = const {},
    bool includeUser = true,
    bool includeLanguage = true,
  }) {
    return jsonEncode([
      BASE_URL,
      endpoint,
      if (includeUser)
        isLoggedIn.value ? loginUserData.value.id.toString() : 'guest',
      if (includeLanguage) selectedLanguageCode.value,
      _normalize(queryParameters),
    ]);
  }

  static dynamic _normalize(dynamic value) {
    if (value is Map) {
      final sorted = Map<String, dynamic>.from(value)
        ..removeWhere((_, value) => value == null);
      final keys = sorted.keys.toList()..sort();
      return Map.fromEntries(
        keys.map((key) => MapEntry(key, _normalize(sorted[key]))),
      );
    }
    if (value is Iterable) {
      return value.map(_normalize).toList();
    }
    return value;
  }

  CachedAppData<T>? read<T>(
    String key,
    T Function(Map<String, dynamic>) decode,
  ) {
    try {
      final raw = storage.read<dynamic>('$prefix$key');
      if (raw == null) return null;

      // BUG FIX #4: Null check if raw is not a Map (corrupted cache)
      if (raw is! Map) {
        unawaited(remove(key));
        return null;
      }

      final entry = Map<String, dynamic>.from(raw as Map);
      // BUG FIX #4: Handle missing timestamp fields with nullsafety
      final savedAt = (entry['savedAt'] as int?) ?? 0;
      final freshUntil = (entry['freshUntil'] as int?) ?? 0;
      final staleUntil = (entry['staleUntil'] as int?) ?? 0;

      // BUG FIX #4: Validate timestamp logic
      if (freshUntil <= savedAt || staleUntil <= freshUntil) {
        unawaited(remove(key));
        return null;
      }

      final now = _now().millisecondsSinceEpoch;
      if (now < savedAt ||
          freshUntil < savedAt ||
          staleUntil < freshUntil ||
          now >= staleUntil) {
        unawaited(remove(key));
        return null;
      }
      return CachedAppData(
        decode(Map<String, dynamic>.from(entry['data'] as Map)),
        isStale: now >= freshUntil,
      );
    } catch (_) {
      // A corrupt entry or an old model must never prevent a network load.
      unawaited(remove(key));
      return null;
    }
  }

  /// CRITICAL FIX: Clear all user-specific cache on login/logout
  static Future<void> clearAllUserCache(GetStorage storage) async {
    try {
      final keys = storage.getKeys();
      final keysToDelete = keys
          .where((k) => k.startsWith(prefix))
          .toList();

      for (final key in keysToDelete) {
        await storage.remove(key);
      }
    } catch (e) {
      debugPrint('Cache clear error: $e');
    }
  }

  Future<void> write(
    String key,
    Map<String, dynamic> data, {
    required Duration ttl,
    required Duration maxStale,
  }) async {
    final now = _now().millisecondsSinceEpoch;
    try {
      await storage.write('$prefix$key', {
        'savedAt': now,
        'freshUntil': now + ttl.inMilliseconds,
        'staleUntil': now + ttl.inMilliseconds + maxStale.inMilliseconds,
        'data': data,
      });
    } catch (_) {
      // Fresh data remains usable even when disk persistence is unavailable.
    }
  }

  Future<void> remove(String key) async {
    try {
      await storage.remove('$prefix$key');
    } catch (_) {
      // Cache cleanup is best effort.
    }
  }
}
