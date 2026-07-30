import '../../../utils/constants.dart';
import '../../home/model/dashboard_res_model.dart';

class ConfigurationResponse {
  RazorPay razorPay;
  StripePay stripePay;
  PaystackPay paystackPay;
  PaypalPay paypalPay;
  FlutterwavePay flutterwavePay;
  AirtelMoney airtelMoney;
  Phonepe phonepe;
  MidtransPay midtransPay;
  CinetPay cinetPay;
  SadadPay sadadPay;
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
    required this.razorPay,
    required this.stripePay,
    required this.paystackPay,
    required this.paypalPay,
    required this.flutterwavePay,
    required this.airtelMoney,
    required this.phonepe,
    required this.midtransPay,
    required this.cinetPay,
    required this.sadadPay,
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
      razorPay: json['razor_pay'] is Map ? RazorPay.fromJson(json['razor_pay']) : RazorPay(),
      stripePay: json['stripe_pay'] is Map ? StripePay.fromJson(json['stripe_pay']) : StripePay(),
      paystackPay: json['paystack_pay'] is Map ? PaystackPay.fromJson(json['paystack_pay']) : PaystackPay(),
      paypalPay: json['paypal_pay'] is Map ? PaypalPay.fromJson(json['paypal_pay']) : PaypalPay(),
      flutterwavePay: json['flutterwave_pay'] is Map ? FlutterwavePay.fromJson(json['flutterwave_pay']) : FlutterwavePay(),
      airtelMoney: json['airtel_pay'] is Map ? AirtelMoney.fromJson(json['airtel_pay']) : AirtelMoney(),
      phonepe: json['phonepay_pay'] is Map ? Phonepe.fromJson(json['phonepay_pay']) : Phonepe(),
      midtransPay: json['midtrans_pay'] is Map ? MidtransPay.fromJson(json['midtrans_pay']) : MidtransPay(),
      cinetPay: json['cinet_pay'] is Map ? CinetPay.fromJson(json['cinet_pay']) : CinetPay(),
      sadadPay: json['sadad_pay'] is Map ? SadadPay.fromJson(json['sadad_pay']) : SadadPay(),
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
      'razor_pay': razorPay.toJson(),
      'stripe_pay': stripePay.toJson(),
      'paystack_pay': paystackPay.toJson(),
      'paypal_pay': paypalPay.toJson(),
      'flutterwave_pay': flutterwavePay.toJson(),
      'airtel_pay': airtelMoney.toJson(),
      'phonepay_pay': phonepe.toJson(),
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

class RazorPay {
  String razorpaySecretkey;
  String razorpayPublickey;

  RazorPay({
    this.razorpaySecretkey = "",
    this.razorpayPublickey = "",
  });

  factory RazorPay.fromJson(Map<String, dynamic> json) {
    return RazorPay(
      razorpaySecretkey: json['razorpay_secretkey'] is String ? json['razorpay_secretkey'] : "",
      razorpayPublickey: json['razorpay_publickey'] is String ? json['razorpay_publickey'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'razorpay_secretkey': razorpaySecretkey,
      'razorpay_publickey': razorpayPublickey,
    };
  }
}

class StripePay {
  String stripeSecretkey;
  String stripePublickey;

  StripePay({
    this.stripeSecretkey = "",
    this.stripePublickey = "",
  });

  factory StripePay.fromJson(Map<String, dynamic> json) {
    return StripePay(
      stripeSecretkey: json['stripe_secretkey'] is String ? json['stripe_secretkey'] : "",
      stripePublickey: json['stripe_publickey'] is String ? json['stripe_publickey'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'stripe_secretkey': stripeSecretkey,
      'stripe_publickey': stripePublickey,
    };
  }
}

class PaystackPay {
  String paystackSecretkey;
  String paystackPublickey;

  PaystackPay({
    this.paystackSecretkey = "",
    this.paystackPublickey = "",
  });

  factory PaystackPay.fromJson(Map<String, dynamic> json) {
    return PaystackPay(
      paystackSecretkey: json['paystack_secretkey'] is String ? json['paystack_secretkey'] : "",
      paystackPublickey: json['paystack_publickey'] is String ? json['paystack_publickey'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'paystack_secretkey': paystackSecretkey,
      'paystack_publickey': paystackPublickey,
    };
  }
}

class PaypalPay {
  String paypalSecretkey;
  String paypalClientid;

  PaypalPay({
    this.paypalSecretkey = "",
    this.paypalClientid = "",
  });

  factory PaypalPay.fromJson(Map<String, dynamic> json) {
    return PaypalPay(
      paypalSecretkey: json['paypal_secretkey'] is String ? json['paypal_secretkey'] : "",
      paypalClientid: json['paypal_clientid'] is String ? json['paypal_clientid'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'paypal_secretkey': paypalSecretkey,
      'paypal_clientid': paypalClientid,
    };
  }
}

class FlutterwavePay {
  String flutterwaveSecretkey;
  String flutterwavePublickey;

  FlutterwavePay({
    this.flutterwaveSecretkey = "",
    this.flutterwavePublickey = "",
  });

  factory FlutterwavePay.fromJson(Map<String, dynamic> json) {
    return FlutterwavePay(
      flutterwaveSecretkey: json['flutterwave_secretkey'] is String ? json['flutterwave_secretkey'] : "",
      flutterwavePublickey: json['flutterwave_publickey'] is String ? json['flutterwave_publickey'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'flutterwave_secretkey': flutterwaveSecretkey,
      'flutterwave_publickey': flutterwavePublickey,
    };
  }
}

class AirtelMoney {
  String airtelSecretkey;
  String airtelClientid;

  AirtelMoney({
    this.airtelSecretkey = "",
    this.airtelClientid = "",
  });

  factory AirtelMoney.fromJson(Map<String, dynamic> json) {
    return AirtelMoney(
      airtelSecretkey: json['airtel_secretkey'] is String ? json['airtel_secretkey'] : "",
      airtelClientid: json['airtel_clientid'] is String ? json['airtel_clientid'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'airtel_secretkey': airtelSecretkey,
      'airtel_clientid': airtelClientid,
    };
  }
}

class Phonepe {
  String phonepeAppId;
  String phonepeMerchantId;
  String phonepeSaltKey;
  String phonepeSaltIndex;

  Phonepe({
    this.phonepeAppId = "",
    this.phonepeMerchantId = "",
    this.phonepeSaltKey = "",
    this.phonepeSaltIndex = "",
  });

  factory Phonepe.fromJson(Map<String, dynamic> json) {
    return Phonepe(
      phonepeAppId: json['phonepay_app_id'] is String ? json['phonepay_app_id'] : "",
      phonepeMerchantId: json['phonepay_merchant_id'] is String ? json['phonepay_merchant_id'] : "",
      phonepeSaltKey: json['phonepay_salt_key'] is String ? json['phonepay_salt_key'] : "",
      phonepeSaltIndex: json['phonepay_salt_index'] is String ? json['phonepay_salt_index'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'phonepay_app_id': phonepeAppId,
      'phonepay_merchant_id': phonepeMerchantId,
      'phonepay_salt_key': phonepeSaltKey,
      'phonepay_salt_index': phonepeSaltIndex,
    };
  }
}

class MidtransPay {
  String midtransClientKey;

  MidtransPay({
    this.midtransClientKey = "",
  });

  factory MidtransPay.fromJson(Map<String, dynamic> json) {
    return MidtransPay(
      midtransClientKey: json['midtrans_clientid'] is String ? json['midtrans_clientid'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'midtrans_clientid': midtransClientKey,
    };
  }
}

class CinetPay {
  String cinetPayAPIKey;

  String siteId;

  CinetPay({this.cinetPayAPIKey = "", this.siteId = ''});

  factory CinetPay.fromJson(Map<String, dynamic> json) {
    return CinetPay(
      siteId: json['cinet_siteid'] is String ? json['cinet_siteid'] : "",
      cinetPayAPIKey: json['cinet_apikey'] is String ? json['cinet_apikey'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'cinet_apikey': cinetPayAPIKey,
      'cinet_siteid': siteId,
    };
  }
}

class SadadPay {
  String sadadId;
  String sadadSecretKey;

  String sadadDomain;

  SadadPay({this.sadadId = "", this.sadadSecretKey = '', this.sadadDomain = ''});

  factory SadadPay.fromJson(Map<String, dynamic> json) {
    return SadadPay(
      sadadId: json['sadad_id'] is String ? json['sadad_id'] : "",
      sadadSecretKey: json['sadad_key'] is String ? json['sadad_key'] : "",
      sadadDomain: json['sadad_domain'] is String ? json['sadad_domain'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'sadad_id': sadadId,
      'sadad_key': sadadSecretKey,
      'sadad_domain': sadadDomain,
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
