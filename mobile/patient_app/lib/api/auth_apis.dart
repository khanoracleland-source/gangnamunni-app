import 'dart:io';

import 'package:get/get.dart' hide MultipartFile;
import 'package:google_sign_in/google_sign_in.dart';
import 'package:http/http.dart';
import 'package:nb_utils/nb_utils.dart';
import '../models/base_response_model.dart';
import '../network/network_utils.dart';
import '../screens/auth/model/patient_wallet_history_res.dart';
import '../screens/auth/model/user_wallet_model.dart';
import '../utils/api_end_points.dart';
import '../utils/app_common.dart';
import '../utils/constants.dart';
import '../utils/local_storage.dart';
import '../screens/auth/model/about_page_res.dart';
import '../screens/auth/model/app_configuration_res.dart';
import '../screens/auth/model/change_password_res.dart';
import '../screens/auth/model/login_response.dart';
import '../screens/auth/model/notification_model.dart';
import '../utils/push_notification_service.dart';

class AuthServiceApis {
  static Future<UserResponse> createUser({required Map request}) async {
    return UserResponse.fromJson(await handleResponse(await buildHttpResponse(
        APIEndPoints.register,
        request: request,
        method: HttpMethodType.POST)));
  }

  static Future<UserResponse> loginUser(
      {required Map request, bool isSocialLogin = false}) async {
    return UserResponse.fromJson(await handleResponse(await buildHttpResponse(
        isSocialLogin ? APIEndPoints.socialLogin : APIEndPoints.login,
        request: request,
        method: HttpMethodType.POST)));
  }

  static Future<UserResponse> verifyUser({required Map request}) async {
    return UserResponse.fromJson(await handleResponse(await buildHttpResponse(
        APIEndPoints.verify,
        request: request,
        method: HttpMethodType.POST)));
  }

  static Future<UserResponse> addUserPhoneNumber({required Map request}) async {
    return UserResponse.fromJson(await handleResponse(await buildHttpResponse(
        APIEndPoints.addUserPhoneNumber,
        request: request,
        method: HttpMethodType.POST)));
  }

  static Future<ChangePassRes> changePasswordAPI({required Map request}) async {
    return ChangePassRes.fromJson(await handleResponse(await buildHttpResponse(
        APIEndPoints.changePassword,
        request: request,
        method: HttpMethodType.POST)));
  }

  static Future<BaseResponseModel> forgotPasswordAPI(
      {required Map request}) async {
    return BaseResponseModel.fromJson(await handleResponse(
        await buildHttpResponse(APIEndPoints.forgotPassword,
            request: request, method: HttpMethodType.POST)));
  }

  static Future<List<NotificationData>> getNotificationDetail({
    int page = 1,
    int perPage = 10,
    required List<NotificationData> notifications,
    Function(bool)? lastPageCallBack,
    Function(int)? allUnreadNotification,
    bool isReadAll = false,
  }) async {
    if (isLoggedIn.value) {
      final String readAll = isReadAll ? '&type=mark_as_read' : '';
      final notificationRes = NotificationRes.fromJson(
        await handleResponse(
          await buildHttpResponse(
            "${APIEndPoints.getNotification}?per_page=$perPage&page=$page$readAll",
          ),
        ),
      );
      if (page == 1) notifications.clear();
      notifications.addAll(notificationRes.notificationData);
      lastPageCallBack
          ?.call(notificationRes.notificationData.length != perPage);
      allUnreadNotification?.call(notificationRes.allUnreadCount);
      return notifications;
    } else {
      return [];
    }
  }

  static Future<NotificationData> clearAllNotification() async {
    return NotificationData.fromJson(
      await handleResponse(
        await buildHttpResponse(
          APIEndPoints.clearAllNotification,
        ),
      ),
    );
  }

  static Future<NotificationData> removeNotification(
      {required String notificationId}) async {
    return NotificationData.fromJson(
      await handleResponse(
        await buildHttpResponse(
          '${APIEndPoints.removeNotification}?id=$notificationId',
        ),
      ),
    );
  }

  static Future<void> clearData({bool isFromDeleteAcc = false}) async {
    log("---------------- clear data---------------");
    GoogleSignIn().signOut();
    await PushNotificationService().unsubscribeFirebaseTopic();

    if (isFromDeleteAcc) {
      localStorage.erase();
      isLoggedIn(false);
      loginUserData(UserData());
    } else {
      final tempEmail = loginUserData.value.email;
      final tempPASSWORD =
          getValueFromLocal(SharedPreferenceConst.USER_PASSWORD);
      final tempIsRemeberMe =
          getValueFromLocal(SharedPreferenceConst.IS_REMEMBER_ME);
      final tempUserName = loginUserData.value.userName;

      localStorage.erase();
      isLoggedIn(false);
      loginUserData(UserData());

      setValueToLocal(SharedPreferenceConst.FIRST_TIME, true);
      setValueToLocal(SharedPreferenceConst.USER_EMAIL, tempEmail);
      setValueToLocal(SharedPreferenceConst.USER_NAME, tempUserName);
      setValueToLocal(SharedPreferenceConst.USER_ID, "0");
      setValueToLocal(SharedPreferenceConst.LOGIN_SUCCESSFULL, false);

      if (tempPASSWORD is String) {
        setValueToLocal(SharedPreferenceConst.USER_PASSWORD, tempPASSWORD);
      }
      if (tempIsRemeberMe is bool) {
        setValueToLocal(SharedPreferenceConst.IS_REMEMBER_ME, tempIsRemeberMe);
      }
    }
  }

  static Future<BaseResponseModel> logoutApi() async {
    try {
      await PushNotificationService().unregisterTokenFromBackend();
    } catch (error) {
      log('FCM token unregister failed during logout: $error');
    }
    return BaseResponseModel.fromJson(
        await handleResponse(await buildHttpResponse(APIEndPoints.logout)));
  }

  static Future<BaseResponseModel> deleteAccountCompletely() async {
    return BaseResponseModel.fromJson(await handleResponse(
        await buildHttpResponse(APIEndPoints.deleteUserAccount,
            request: {}, method: HttpMethodType.POST)));
  }

  static Future<ConfigurationResponse> getAppConfigurations() async {
    return ConfigurationResponse.fromJson(
      await handleResponse(
        await buildHttpResponse(
          '${APIEndPoints.appConfiguration}?is_authenticated=${(getValueFromLocal(SharedPreferenceConst.IS_LOGGED_IN) == true).getIntBool()}',
          request: {},
        ),
      ),
    );
  }

  static Future<UserResponse> viewProfile({int? id}) async {
    final res = UserResponse.fromJson(
      await handleResponse(
        await buildHttpResponse(
          '${APIEndPoints.userDetail}?id=${id ?? loginUserData.value.id}',
        ),
      ),
    );
    return res;
  }

  static Future<void> getUserWallet() async {
    try {
      final res = UserWalletData.fromJson(await handleResponse(
          await buildHttpResponse(APIEndPoints.getPatientWallet)));
      userWalletData(res);
    } catch (e) {
      log('Err: $e');
    }
  }

  //Get Wallet History
  static Future<RxList<WalletHistoryElement>> getWalletHistory({
    int page = 1,
    String search = '',
    var perPage = Constants.perPageItem,
    required List<WalletHistoryElement> historyData,
    Function(bool)? lastPageCallBack,
  }) async {
    final String searchData = search.isNotEmpty ? '&search=$search' : '';

    final res = PatientHistoryRes.fromJson(
      await handleResponse(
        await buildHttpResponse(
          "${APIEndPoints.getWalletHistory}?per_page=$perPage&page=$page$searchData",
        ),
      ),
    );
    if (page == 1) historyData.clear();
    historyData.addAll(res.data.validate());
    lastPageCallBack?.call(res.data.validate().length != perPage);
    return historyData.obs;
  }

  static Future<dynamic> updateProfile({
    File? imageFile,
    String firstName = '',
    String lastName = '',
    String mobile = '',
    String address = '',
    String gender = '',
    String email = '',
    String dateOfBirth = '',
    String playerId = '',
    Function(dynamic)? onSuccess,
  }) async {
    if (isLoggedIn.value) {
      final MultipartRequest multiPartRequest =
          await getMultiPartRequest(APIEndPoints.updateProfile);
      if (firstName.trim().isNotEmpty)
        multiPartRequest.fields[UserKeys.firstName] = firstName;
      if (lastName.trim().isNotEmpty)
        multiPartRequest.fields[UserKeys.lastName] = lastName;
      if (mobile.trim().isNotEmpty)
        multiPartRequest.fields[UserKeys.mobile] = mobile;
      if (address.trim().isNotEmpty)
        multiPartRequest.fields[UserKeys.address] = address;
      if (gender.trim().isNotEmpty)
        multiPartRequest.fields[UserKeys.gender] = gender;
      if (email.trim().isNotEmpty)
        multiPartRequest.fields[UserKeys.email] = email;
      if (dateOfBirth.trim().isNotEmpty)
        multiPartRequest.fields[UserKeys.dateOfBirth] = dateOfBirth;

      if (imageFile != null) {
        multiPartRequest.files.add(await MultipartFile.fromPath(
            UserKeys.profileImage, imageFile.path));
      }

      multiPartRequest.headers.addAll(buildHeaderTokens());

      await sendMultiPartRequest(
        multiPartRequest,
        onSuccess: (data) async {
          onSuccess?.call(data);
        },
        onError: (error) {
          throw error;
        },
      ).catchError((error) {
        throw error;
      });
    }
  }

  static Future<AboutPageRes> getAboutPageData() async {
    return AboutPageRes.fromJson(
        await handleResponse(await buildHttpResponse(APIEndPoints.aboutPages)));
  }
}
