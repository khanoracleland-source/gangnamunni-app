import 'dart:convert';
import 'dart:io';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:http/http.dart';
import 'package:image_picker/image_picker.dart';
import 'package:nb_utils/nb_utils.dart';

import '../api/auth_apis.dart';
import '../configs.dart';
import '../main.dart';
import '../utils/api_end_points.dart';
import '../utils/app_common.dart';
import '../utils/common_base.dart';
import '../utils/constants.dart';
import '../utils/local_storage.dart';

Map<String, String> buildHeaderTokens({
  Map? extraKeys,
  String? endPoint,
}) {
  /// Initialize & Handle if key is not present
  if (extraKeys == null) {
    extraKeys = {};
    extraKeys.putIfAbsent('isFlutterWave', () => false);
    extraKeys.putIfAbsent('isAirtelMoney', () => false);
  }
  final Map<String, String> header = {
    HttpHeaders.cacheControlHeader: 'no-cache',
    'Access-Control-Allow-Headers': '*',
    'Access-Control-Allow-Origin': '*',
    'global-localization': selectedLanguageCode.value,
  };

  if (endPoint == APIEndPoints.register) {
    header.putIfAbsent(HttpHeaders.acceptHeader, () => 'application/json');
  }
  header.putIfAbsent(HttpHeaders.contentTypeHeader, () => 'application/json; charset=utf-8');

  if (isLoggedIn.value && extraKeys.containsKey('isFlutterWave') && extraKeys['isFlutterWave']) {
    header.putIfAbsent(HttpHeaders.authorizationHeader, () => "Bearer ${extraKeys!['flutterWaveSecretKey']}");
  } else if (isLoggedIn.value && extraKeys.containsKey('isAirtelMoney') && extraKeys['isAirtelMoney']) {
    header.putIfAbsent(HttpHeaders.contentTypeHeader, () => 'application/json; charset=utf-8');
    header.putIfAbsent(HttpHeaders.authorizationHeader, () => 'Bearer ${extraKeys!['access_token']}');
    header.putIfAbsent('X-Country', () => '${extraKeys!['X-Country']}');
    header.putIfAbsent('X-Currency', () => '${extraKeys!['X-Currency']}');
  } else if (isLoggedIn.value) {
    header.putIfAbsent(HttpHeaders.authorizationHeader, () => 'Bearer ${loginUserData.value.apiToken}');
  }

  // log(jsonEncode(header));
  return header;
}

Uri buildBaseUrl(String endPoint) {
  if (!endPoint.startsWith('http')) {
    return Uri.parse('$BASE_URL$endPoint');
  } else {
    return Uri.parse(endPoint);
  }
}

Future<Response> buildHttpResponse(
  String endPoint, {
  HttpMethodType method = HttpMethodType.GET,
  Map? request,
  Map? extraKeys,
  Map<String, String>? header,
}) async {
  final headers = header ?? buildHeaderTokens(extraKeys: extraKeys, endPoint: endPoint);
  final Uri url = buildBaseUrl(endPoint);

  Response response;
  log('URL (${method.name}): $url');

  try {
    if (method == HttpMethodType.POST) {
      log('Request: ${jsonEncode(request)}');
      response = await http.post(url, body: jsonEncode(request), headers: headers);
    } else if (method == HttpMethodType.DELETE) {
      response = await delete(url, headers: headers);
    } else if (method == HttpMethodType.PUT) {
      response = await put(url, body: jsonEncode(request), headers: headers);
    } else {
      response = await get(url, headers: headers);
    }
    apiPrint(
      url: url.toString(),
      endPoint: endPoint,
      headers: jsonEncode(headers),
      hasRequest: method == HttpMethodType.POST || method == HttpMethodType.PUT,
      request: jsonEncode(request),
      statusCode: response.statusCode,
      responseBody: response.body.trim(),
      methodtype: method.name,
    );
    // log('Response (${method.name}) ${response.statusCode}: ${response.body.trim().trim()}');

    if (isLoggedIn.value && response.statusCode == 401 && !endPoint.startsWith('http')) {
      return await reGenerateToken().then((value) async {
        return buildHttpResponse(endPoint, method: method, request: request, extraKeys: extraKeys);
      }).catchError((e) async {
        if (!await isNetworkAvailable()) {
          throw errorInternetNotAvailable;
        } else {
          throw errorSomethingWentWrong;
        }
      });
    } else {
      return response;
    }
  } on Exception catch (e) {
    log(e);
    throw errorInternetNotAvailable;
  }
}

Future<void> reGenerateToken() async {
  log('Regenerating Token');
  final userPASSWORD = getValueFromLocal(SharedPreferenceConst.USER_PASSWORD);

  final Map req = {
    UserKeys.email: loginUserData.value.email,
    UserKeys.userType: LoginTypeConst.LOGIN_TYPE_USER,
  };
  if (loginUserData.value.isSocialLoginType) {
    log('LOGINUSERDATA.VALUE.ISSOCIALLOGIN: ${loginUserData.value.isSocialLoginType}');
    req[UserKeys.loginType] = loginUserData.value.loginType;
  } else {
    req[UserKeys.password] = userPASSWORD;
  }
  return AuthServiceApis.loginUser(request: req, isSocialLogin: loginUserData.value.isSocialLoginType).then((value) async {
    loginUserData.value.apiToken = value.userData.apiToken;
  }).catchError((e) {
    throw e;
  });
}

Future handleResponse(Response response, {HttpResponseType httpResponseType = HttpResponseType.JSON, bool? avoidTokenError, bool? isFlutterWave}) async {
  if (!await isNetworkAvailable()) {
    throw errorInternetNotAvailable;
  }

  if (response.statusCode.isSuccessful()) {
    if (response.body.trim().isJson()) {
      final Map body = jsonDecode(response.body.trim());

      if (body.containsKey('status')) {
        if (isFlutterWave.validate()) {
          if (body['status'] == 'success') {
            return body;
          } else {
            throw body['message'] ?? errorSomethingWentWrong;
          }
        } else {
          if (body['status'] == true) {
            return body;
          } else {
            if (body.containsKey("is_deleted") && body["is_deleted"] == true) {
              AuthServiceApis.clearData(isFromDeleteAcc: true);
              isLoggedIn(false);
              doIfLoggedIn(() {});
              toast(body['message'] ?? errorSomethingWentWrong);
            } else {
              throw body['message'] ?? errorSomethingWentWrong;
            }
          }
        }
      } else {
        return body;
      }
    } else {
      throw errorSomethingWentWrong;
    }
  } else if (response.statusCode == 400) {
    throw locale.value.badRequest;
  } else if (response.statusCode == 403) {
    throw locale.value.forbidden;
  } else if (response.statusCode == 404) {
    throw locale.value.pageNotFound;
  } else if (response.statusCode == 429) {
    throw locale.value.tooManyRequests;
  } else if (response.statusCode == 500) {
    throw locale.value.internalServerError;
  } else if (response.statusCode == 502) {
    throw locale.value.badGateway;
  } else if (response.statusCode == 503) {
    throw locale.value.serviceUnavailable;
  } else if (response.statusCode == 504) {
    throw locale.value.gatewayTimeout;
  } else {
    final Map body = jsonDecode(response.body.trim());

    if (body.containsKey('status') && body['status']) {
      return body;
    } else {
      throw body['message'] ?? errorSomethingWentWrong;
    }
  }
}

//region CommonFunctions
Future<Map<String, String>> getMultipartFields({required Map<String, dynamic> val}) async {
  final Map<String, String> data = {};

  val.forEach((key, value) {
    data[key] = '$value';
  });

  return data;
}

Future<MultipartRequest> getMultiPartRequest(String endPoint, {String? baseUrl}) async {
  final String url = baseUrl ?? buildBaseUrl(endPoint).toString();
  // log(url);
  return MultipartRequest('POST', Uri.parse(url));
}

Future<void> sendMultiPartRequest(MultipartRequest multiPartRequest, {Function(dynamic)? onSuccess, Function(dynamic)? onError}) async {
  final http.Response response = await http.Response.fromStream(await multiPartRequest.send());
  apiPrint(
    url: multiPartRequest.url.toString(),
    headers: jsonEncode(multiPartRequest.headers),
    request: jsonEncode(multiPartRequest.fields),
    hasRequest: true,
    statusCode: response.statusCode,
    responseBody: response.body.trim(),
    methodtype: "MultiPart",
  );
  // log("Result: ${response.statusCode} - ${multiPartRequest.fields}");
  // log(response.body.trim());
  if (response.statusCode.isSuccessful()) {
    onSuccess?.call(response.body.trim());
  } else {
    if (isLoggedIn.value && response.statusCode == 401) {
      return reGenerateToken().then((value) async {
        try {
          final http.Response response = await http.Response.fromStream(await multiPartRequest.send());
          if (response.statusCode.isSuccessful()) {
            onSuccess?.call(response.body.trim());
          } else {
            onError?.call(response.reasonPhrase);
          }
        } catch (e) {
          onError?.call(response.reasonPhrase);
        }
      }).catchError((e) {
        onError?.call(response.reasonPhrase);
      });
    } else {
      onError?.call(response.reasonPhrase);
    }
  }
}

Future buildMultiPartResponse({
  required String endPoint,
  required Map<String, dynamic> request,
  Map<String, String>? header,
  List<File>? files,
  String? fileKey,
  bool isKeyRequireIndexing = false,
}) async {
  try {
    final MultipartRequest multiPartRequest = await getMultiPartRequest(endPoint);
    multiPartRequest.headers.addAll(buildHeaderTokens());
    multiPartRequest.fields.addAll(await getMultipartFields(val: request));
    if (files != null && files.isNotEmpty) {
      files.removeWhere((element) => element.path.isEmpty);
      if (files.length > 1) {
        files.forEachIndexed(
          (element, index) async {
            multiPartRequest.files.add(await MultipartFile.fromPath('${fileKey}_$index', element.path));
          },
        );
      } else if (files.length == 1) {
        files.forEachIndexed(
          (element, index) async {
            if (isKeyRequireIndexing) {
              multiPartRequest.files.add(await MultipartFile.fromPath('${fileKey}_$index', element.path));
            } else {
              multiPartRequest.files.add(await MultipartFile.fromPath('$fileKey', element.path));
            }
          },
        );
      }
    }

    final Response response = await Response.fromStream(await multiPartRequest.send());

    apiPrint(
      url: multiPartRequest.url.toString(),
      headers: jsonEncode(multiPartRequest.headers),
      request: jsonEncode(multiPartRequest.fields),
      hasRequest: true,
      statusCode: response.statusCode,
      responseBody: response.body,
      methodtype: "MultiPart",
    );
    return await handleResponse(response);
  } on SocketException catch (e) {
    log(e.toString());
  } on Exception catch (e) {
    log(e.toString());
  }
}

Future<List<MultipartFile>> getMultipartImages({required List<PlatformFile> files, required String name}) async {
  final List<MultipartFile> multiPartRequest = [];

  await Future.forEach<PlatformFile>(files, (element) async {
    final int i = files.indexOf(element);

    multiPartRequest.add(await MultipartFile.fromPath('$name[$i]', element.path.validate()));
  });

  return multiPartRequest;
}

Future<List<MultipartFile>> getMultipartImages2({required List<XFile> files, required String name}) async {
  final List<MultipartFile> multiPartRequest = [];

  await Future.forEach<XFile>(files, (element) async {
    final int i = files.indexOf(element);

    multiPartRequest.add(await MultipartFile.fromPath('$name[$i]', element.path.validate()));
    log('MultipartFile: $name[$i]');
  });

  return multiPartRequest;
}

String parseStripeError(String response) {
  try {
    final body = jsonDecode(response);
    return parseHtmlString(body['error']['message']);
  } on Exception catch (e) {
    log(e);
    throw errorSomethingWentWrong;
  }
}

void apiPrint({
  String url = "",
  String endPoint = "",
  String headers = "",
  String request = "",
  int statusCode = 0,
  String responseBody = "",
  String methodtype = "",
  bool hasRequest = false,
  bool fullLog = false,
  String responseHeader = '',
}) {
  if (fullLog) {
    debugPrint("┌───────────────────────────────────────────────────────────────────────────────────────────────────────");
    debugPrint("\u001b[93m Url: \u001B[39m $url");
    debugPrint("\u001b[93m endPoint: \u001B[39m \u001B[1m$endPoint\u001B[22m");
    debugPrint("\u001b[93m header: \u001B[39m \u001b[96m$headers\u001B[39m");
    if (hasRequest) {
      debugPrint('\u001b[93m Request: \u001B[39m \u001b[95m$request\u001B[39m');
    }
    debugPrint(statusCode.isSuccessful() ? "\u001b[32m" : "\u001b[31m");
    debugPrint("\u001b[93m Response header: \u001B[39m \u001b[96m$responseHeader\u001B[39m");
    debugPrint('\u001b[93m MethodType ($methodtype) | StatusCode ($statusCode)\u001B[39m');
    debugPrint('Response : ');
    debugPrint('\x1B[32m${formatJson(responseBody)}\x1B[0m');
    debugPrint("\u001B[0m");
    debugPrint("└───────────────────────────────────────────────────────────────────────────────────────────────────────");
  } else {
    debugPrint("┌───────────────────────────────────────────────────────────────────────────────────────────────────────");
    debugPrint("\u001b[93m Url: \u001B[39m $url");
    debugPrint("\u001b[93m endPoint: \u001B[39m \u001B[1m$endPoint\u001B[22m");
    debugPrint("\u001b[93m header: \u001B[39m \u001b[96m${headers.split(',').join(',\n')}\u001B[39m");
    if (hasRequest) {
      debugPrint('\u001b[93m Request: \u001B[39m \u001b[95m$request\u001B[39m');
    }
    debugPrint(statusCode.isSuccessful() ? "\u001b[32m" : "\u001b[31m");
    debugPrint('\u001b[93m MethodType ($methodtype) | statusCode: ($statusCode)\u001B[39m');
    debugPrint("\u001b[93m Response header: \u001B[39m \u001b[96m$responseHeader\u001B[39m");
    debugPrint('\u001b[93m Response \u001B[39m');
    debugPrint(responseBody);
    debugPrint("\u001B[0m");
    debugPrint("└───────────────────────────────────────────────────────────────────────────────────────────────────────");
  }
}

String formatJson(String jsonStr) {
  try {
    final dynamic parsedJson = jsonDecode(jsonStr);
    const formatter = JsonEncoder.withIndent('  ');
    return formatter.convert(parsedJson);
  } on Exception catch (e) {
    debugPrint("\x1b[31m formatJson error ::-> $e \x1b[0m");
    return jsonStr;
  }
}

Map<String, String> buildHeaderForStripe(String stripeKeyPayment) {
  final Map<String, String> header = defaultHeaders();

  header.putIfAbsent(HttpHeaders.contentTypeHeader, () => 'application/x-www-form-urlencoded');
  header.putIfAbsent(HttpHeaders.authorizationHeader, () => 'Bearer $stripeKeyPayment');

  return header;
}

Map<String, String> buildHeaderForSadad({String? sadadToken}) {
  final Map<String, String> header = defaultHeaders();

  header.putIfAbsent(HttpHeaders.contentTypeHeader, () => 'application/json');
  if (sadadToken != null) {
    header.putIfAbsent(HttpHeaders.authorizationHeader, () => sadadToken);
  }

  return header;
}

Map<String, String> buildHeaderForFlutterWave(String flutterWaveSecretKey) {
  final Map<String, String> header = defaultHeaders();

  header.putIfAbsent(HttpHeaders.authorizationHeader, () => "Bearer $flutterWaveSecretKey");

  return header;
}

Map<String, String> buildHeaderForAirtelMoney(String accessToken, String xCountry, String xCurrency) {
  final Map<String, String> header = defaultHeaders();

  header.putIfAbsent(HttpHeaders.contentTypeHeader, () => 'application/json; charset=utf-8');
  header.putIfAbsent(HttpHeaders.authorizationHeader, () => 'Bearer $accessToken');
  header.putIfAbsent('X-Country', () => xCountry);
  header.putIfAbsent('X-Currency', () => xCurrency);

  return header;
}

Map<String, String> defaultHeaders() {
  final Map<String, String> header = {};

  header.putIfAbsent(HttpHeaders.cacheControlHeader, () => 'no-cache');
  header.putIfAbsent('Access-Control-Allow-Headers', () => '*');
  header.putIfAbsent('Access-Control-Allow-Origin', () => '*');

  return header;
}

Future<Map<String, dynamic>> handleSadadResponse(Response res) async {
  if (res.body.isJson()) {
    final body = jsonDecode(res.body);

    if (res.statusCode.isSuccessful()) {
      return body;
    } else {
      throw parseHtmlString(body['error']['message']);
    }
  } else {
    throw errorSomethingWentWrong;
  }
}
