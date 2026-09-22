import 'package:get/get.dart';

import 'app_cache_service.dart';

/// Restores data synchronously and revalidates on every explicit refresh.
/// Empty results are data too; only null means that a full loader is needed.
class CacheFirstController<T extends Object> extends GetxController {
  CacheFirstController({
    required this.cache,
    required this.cacheKey,
    required this.load,
    required this.decode,
    required this.encode,
    required this.ttl,
    required this.maxStale,
  });

  final AppCacheService cache;
  final String Function() cacheKey;
  final Future<T> Function() load;
  final T Function(Map<String, dynamic>) decode;
  final Map<String, dynamic> Function(T) encode;
  final Duration ttl;
  final Duration maxStale;

  final pageData = Rxn<T>();
  final isLoading = false.obs;
  final isRefreshing = false.obs;
  final loadError = RxnString();
  bool get hasData => pageData.value != null;

  String? _activeKey;
  Future<void>? _inFlight;
  int _generation = 0;

  void onData(T? data) {}

  void _show(T? data) {
    pageData.value = data;
    onData(data);
  }

  @override
  Future<void> refresh() {
    if (isClosed) return Future.value();
    final key = cacheKey();
    if (_activeKey == key && _inFlight != null) return _inFlight!;

    final generation = ++_generation;
    if (_activeKey != key) _show(null);
    _activeKey = key;
    if (!hasData) _show(cache.read(key, decode)?.data);
    loadError.value = null;
    isLoading.value = !hasData;
    isRefreshing.value = true;

    // Schedule after _inFlight is assigned, including synchronous loader errors.
    return _inFlight = Future.microtask(() => _fetch(key, generation));
  }

  bool _isCurrent(String key, int generation) =>
      !isClosed && generation == _generation && cacheKey() == key;

  Future<void> _fetch(String key, int generation) async {
    try {
      if (!_isCurrent(key, generation)) return;
      final fresh = await load();
      if (!_isCurrent(key, generation)) return;
      _show(fresh);
      // Data is already visible; persistence must not hold up rendering.
      isLoading.value = false;
      await cache.write(key, encode(fresh), ttl: ttl, maxStale: maxStale);
    } catch (error) {
      if (_isCurrent(key, generation)) loadError.value = error.toString();
    } finally {
      if (!isClosed && generation == _generation) {
        isLoading.value = false;
        isRefreshing.value = false;
        _inFlight = null;
      }
    }
  }

  @override
  void onClose() {
    _generation++;
    super.onClose();
  }
}
