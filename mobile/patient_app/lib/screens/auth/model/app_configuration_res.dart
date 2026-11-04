import '../../../utils/constants.dart';
import '../../home/model/dashboard_res_model.dart';

class ConfigurationResponse {
  PatientAppUrl patientAppUrl;
  ClinicadminAppUrl clinicadminAppUrl;
  bool isForceUpdateforAndroid;
  int patientAndroidMinForceUpdateCode;
  int patientAndroidLatestVersionUpdateCode;
  int clinicadminAndroidMinForceUpdateCode;
  int clinicadminAndroidLatestVersionUpdateCode;
  bool isForceUpdateforIos;
  int patientIosMinForceUpdateCode;
  int patientIosLatestVersionUpdateCode;
  int clinicadminIosMinForceUpdateCode;
  int clinicadminIosLatestVersionUpdateCode;
  Currency currency;
  String siteDescription;
  bool isUserPushNotification;
  bool enableChatGpt;
  bool testWithoutKey;
  String chatgptKey;
  String notification;
  String firebaseKey;
  String applicationLanguage;
  bool isMultiVendor;
  bool status;
  String cancellationType;
  bool isCancellationChargeEnabled;
  int cancellationChargeHours;
  num cancellationCharge;
  List<TaxPercentage> taxData;
  bool isPharma;

  List<TaxPercentage> get exclusiveTaxList => taxData.where((element) => element.taxScope == TaxType.exclusiveTax).toList();

  bool get isExclusiveTaxesAvailable => exclusiveTaxList.isNotEmpty;

  bool get isCancellationChargesAvailable => cancellationCharge > 0;

  bool get isCancellationHoursAvailable => cancellationChargeHours > 0;
  int isDummyCredential;
  int googleLoginStatus;
  int appleLoginStatus;
  int isQuickBookingEnabled;

  ConfigurationResponse({
    required this.patientAppUrl,
    required this.clinicadminAppUrl,
    this.isForceUpdateforAndroid = false,
    this.patientAndroidMinForceUpdateCode = 0,
    this.patientAndroidLatestVersionUpdateCode = 0,
    this.clinicadminAndroidMinForceUpdateCode = 0,
    this.clinicadminAndroidLatestVersionUpdateCode = 0,
    this.isForceUpdateforIos = false,
    this.patientIosMinForceUpdateCode = 0,
    this.patientIosLatestVersionUpdateCode = 0,
    this.clinicadminIosMinForceUpdateCode = 0,
    this.clinicadminIosLatestVersionUpdateCode = 0,
    required this.currency,
    this.siteDescription = "",
    this.isUserPushNotification = false,
    this.enableChatGpt = false,
    this.testWithoutKey = false,
    this.chatgptKey = "",
    this.notification = "",
    this.firebaseKey = "",
    this.applicationLanguage = "",
    this.isMultiVendor = false,
    this.status = false,
    this.isCancellationChargeEnabled = false,
    this.cancellationChargeHours = 0,
    this.taxData = const <TaxPercentage>[],
    this.cancellationCharge = 0,
    this.cancellationType = '',
    this.isDummyCredential = 0,
    this.googleLoginStatus = 0,
    this.appleLoginStatus = 0,
    this.isPharma = false,
    this.isQuickBookingEnabled = 0,
  });

  factory ConfigurationResponse.fromJson(Map<String, dynamic> json) {
    return ConfigurationResponse(
      patientAppUrl: json['patient_app_url'] is Map ? PatientAppUrl.fromJson(json['patient_app_url']) : PatientAppUrl(),
      clinicadminAppUrl: json['clinicadmin_app_url'] is Map ? ClinicadminAppUrl.fromJson(json['clinicadmin_app_url']) : ClinicadminAppUrl(),
      isForceUpdateforAndroid: json['isForceUpdateforAndroid'] is bool ? json['isForceUpdateforAndroid'] : json['isForceUpdateforAndroid'] == 1,
      patientAndroidMinForceUpdateCode: json['patient_android_min_force_update_code'] is int ? json['patient_android_min_force_update_code'] : 0,
      patientAndroidLatestVersionUpdateCode: json['patient_android_latest_version_update_code'] is int ? json['patient_android_latest_version_update_code'] : 0,
      clinicadminAndroidMinForceUpdateCode: json['clinicadmin_android_min_force_update_code'] is int ? json['clinicadmin_android_min_force_update_code'] : 0,
      clinicadminAndroidLatestVersionUpdateCode: json['clinicadmin_android_latest_version_update_code'] is int ? json['clinicadmin_android_latest_version_update_code'] : 0,
      isForceUpdateforIos: json['isForceUpdateforIos'] is bool ? json['isForceUpdateforIos'] : json['isForceUpdateforIos'] == 1,
      patientIosMinForceUpdateCode: json['patient_ios_min_force_update_code'] is int ? json['patient_ios_min_force_update_code'] : 0,
      patientIosLatestVersionUpdateCode: json['patient_ios_latest_version_update_code'] is int ? json['patient_ios_latest_version_update_code'] : 0,
      clinicadminIosMinForceUpdateCode: json['clinicadmin_ios_min_force_update_code'] is int ? json['clinicadmin_ios_min_force_update_code'] : 0,
      clinicadminIosLatestVersionUpdateCode: json['clinicadmin_ios_latest_version_update_code'] is int ? json['clinicadmin_ios_latest_version_update_code'] : 0,
      currency: json['currency'] is Map ? Currency.fromJson(json['currency']) : Currency(),
      siteDescription: json['site_description'] is String ? json['site_description'] : "",
      isUserPushNotification: json['is_user_push_notification'] is bool ? json['is_user_push_notification'] : json['is_user_push_notification'] == 1,
      enableChatGpt: json['enable_chat_gpt'] is bool ? json['enable_chat_gpt'] : json['enable_chat_gpt'] == 1,
      testWithoutKey: json['test_without_key'] is bool ? json['test_without_key'] : json['test_without_key'] == 1,
      chatgptKey: json['chatgpt_key'] is String ? json['chatgpt_key'] : "",
      notification: json['notification'] is String ? json['notification'] : "",
      firebaseKey: json['firebase_key'] is String ? json['firebase_key'] : "",
      applicationLanguage: json['application_language'] is String ? json['application_language'] : "",
      isMultiVendor: json['is_multi_vendor'] is bool ? json['is_multi_vendor'] : json['is_multi_vendor'] == 1,
      status: json['status'] is bool ? json['status'] : json['status'] == 1,
      isCancellationChargeEnabled: json['is_cancellation_charge'] is bool ? json['is_cancellation_charge'] : json['is_cancellation_charge'] == 1,
      cancellationChargeHours: json['cancellation_charge_hours'] is int ? json['cancellation_charge_hours'] : 0,
      taxData: json['tax'] is List ? List<TaxPercentage>.from(json['tax'].map((x) => TaxPercentage.fromJson(x))) : [],
      cancellationCharge: json['cancellation_charge'] is num ? json['cancellation_charge'] : 0,
      cancellationType: json['cancellation_type'] is String ? json['cancellation_type'] : "",
      isDummyCredential: json['is_dummy_credentials'] is int ? json['is_dummy_credentials'] : 0,
      googleLoginStatus: json['google_login_status'] is int ? json['google_login_status'] : 0,
      appleLoginStatus: json['apple_login_status'] is int ? json['apple_login_status'] : 0,
      isPharma: json['is_pharma'] is bool ? json['is_pharma'] : json['is_pharma'] == 1,
      isQuickBookingEnabled: json['is_quick_booking_on'] is int ? json['is_quick_booking_on'] : 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'patient_app_url': patientAppUrl.toJson(),
      'clinicadmin_app_url': clinicadminAppUrl.toJson(),
      'isForceUpdateforAndroid': isForceUpdateforAndroid,
      'patient_android_min_force_update_code': patientAndroidMinForceUpdateCode,
      'patient_android_latest_version_update_code': patientAndroidLatestVersionUpdateCode,
      'clinicadmin_android_min_force_update_code': clinicadminAndroidMinForceUpdateCode,
      'clinicadmin_android_latest_version_update_code': clinicadminAndroidLatestVersionUpdateCode,
      'isForceUpdateforIos': isForceUpdateforIos,
      'patient_ios_min_force_update_code': patientIosMinForceUpdateCode,
      'patient_ios_latest_version_update_code': patientIosLatestVersionUpdateCode,
      'clinicadmin_ios_min_force_update_code': clinicadminIosMinForceUpdateCode,
      'clinicadmin_ios_latest_version_update_code': clinicadminIosLatestVersionUpdateCode,
      'currency': currency.toJson(),
      'site_description': siteDescription,
      'is_user_push_notification': isUserPushNotification,
      'enable_chat_gpt': enableChatGpt,
      'test_without_key': testWithoutKey,
      'chatgpt_key': chatgptKey,
      'notification': notification,
      'firebase_key': firebaseKey,
      'application_language': applicationLanguage,
      'is_multi_vendor': isMultiVendor,
      'status': status,
      'tax': taxData.map((e) => e.toJson()).toList(),
      'is_cancellation_charge': isCancellationChargeEnabled,
      'cancellation_charge_hours': cancellationChargeHours,
      'cancellation_charge': cancellationCharge,
      'is_dummy_credentials': isDummyCredential,
      'google_login_status': googleLoginStatus,
      'apple_login_status': appleLoginStatus,
      'is_pharma': isPharma,
      'is_quick_booking_on' : isQuickBookingEnabled,
    };
  }
}

class PatientAppUrl {
  String patientAppPlayStore;
  String patientAppAppStore;

  PatientAppUrl({
    this.patientAppPlayStore = "",
    this.patientAppAppStore = "",
  });

  factory PatientAppUrl.fromJson(Map<String, dynamic> json) {
    return PatientAppUrl(
      patientAppPlayStore: json['patient_app_play_store'] is String ? json['patient_app_play_store'] : "",
      patientAppAppStore: json['patient_app_app_store'] is String ? json['patient_app_app_store'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'patient_app_play_store': patientAppPlayStore,
      'patient_app_app_store': patientAppAppStore,
    };
  }
}

class ClinicadminAppUrl {
  String clinicadminAppPlayStore;
  String clinicadminAppAppStore;

  ClinicadminAppUrl({
    this.clinicadminAppPlayStore = "",
    this.clinicadminAppAppStore = "",
  });

  factory ClinicadminAppUrl.fromJson(Map<String, dynamic> json) {
    return ClinicadminAppUrl(
      clinicadminAppPlayStore: json['clinicadmin_app_play_store'] is String ? json['clinicadmin_app_play_store'] : "",
      clinicadminAppAppStore: json['clinicadmin_app_app_store'] is String ? json['clinicadmin_app_app_store'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'clinicadmin_app_play_store': clinicadminAppPlayStore,
      'clinicadmin_app_app_store': clinicadminAppAppStore,
    };
  }
}

class Currency {
  String currencyName;
  String currencySymbol;
  String currencyCode;
  String currencyPosition;
  int noOfDecimal;
  String thousandSeparator;
  String decimalSeparator;

  Currency({
    this.currencyName = "Doller",
    this.currencySymbol = "\$",
    this.currencyCode = "USD",
    this.currencyPosition = CurrencyPosition.CURRENCY_POSITION_LEFT,
    this.noOfDecimal = 2,
    this.thousandSeparator = ",",
    this.decimalSeparator = ".",
  });

  factory Currency.fromJson(Map<String, dynamic> json) {
    return Currency(
      currencyName: json['currency_name'] is String ? json['currency_name'] : "Doller",
      currencySymbol: json['currency_symbol'] is String ? json['currency_symbol'] : "\$",
      currencyCode: json['currency_code'] is String ? json['currency_code'] : "USD",
      currencyPosition: json['currency_position'] is String ? json['currency_position'] : "left",
      noOfDecimal: json['no_of_decimal'] is int ? json['no_of_decimal'] : 2,
      thousandSeparator: json['thousand_separator'] is String ? json['thousand_separator'] : ",",
      decimalSeparator: json['decimal_separator'] is String ? json['decimal_separator'] : ".",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'currency_name': currencyName,
      'currency_symbol': currencySymbol,
      'currency_code': currencyCode,
      'currency_position': currencyPosition,
      'no_of_decimal': noOfDecimal,
      'thousand_separator': thousandSeparator,
      'decimal_separator': decimalSeparator,
    };
  }
}
