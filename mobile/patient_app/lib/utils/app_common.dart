import 'package:get/get.dart';
import 'package:kivicare_patient/screens/auth/model/user_wallet_model.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/screens/home/model/system_service_res.dart';
import '../configs.dart';
import '../screens/auth/model/about_page_res.dart';
import '../screens/auth/model/app_configuration_res.dart';
import '../screens/auth/model/login_response.dart';
import '../screens/booking/model/save_booking_res.dart';
import '../screens/clinic/model/clinic_detail_model.dart';
import '../screens/clinic/model/clinics_res_model.dart';
import '../screens/doctor/model/doctor_list_res.dart';
import '../screens/service/model/service_list_model.dart';
import 'constants.dart';

bool isIqonicProduct = DOMAIN_URL.contains("apps.iqonic.design") || DOMAIN_URL.contains("iqonic.design") || DOMAIN_URL.contains("innoquad.in") || DOMAIN_URL.contains("192.168");

RxString selectedLanguageCode = DEFAULT_LANGUAGE.obs;
RxBool isLoggedIn = false.obs;
Rx<UserData> loginUserData = UserData().obs;
Rx<UserWalletData> userWalletData = UserWalletData().obs;
RxBool isDarkMode = false.obs;
RxInt unreadNotificationCount = 0.obs;

// Global language list variable
RxList<LanguageDataModel> appLanguageList = RxList<LanguageDataModel>();

// Firebase Constants
String get appNameTopic => ANDROID_BROADCAST_TOPIC;
//endregion

Rx<Currency> appCurrency = Currency().obs;
Rx<ConfigurationResponse> appConfigs = ConfigurationResponse(
  patientAppUrl: PatientAppUrl(),
  clinicadminAppUrl: ClinicadminAppUrl(),
  razorPay: RazorPay(),
  stripePay: StripePay(),
  paystackPay: PaystackPay(),
  paypalPay: PaypalPay(),
  flutterwavePay: FlutterwavePay(),
  airtelMoney: AirtelMoney(),
  midtransPay: MidtransPay(),
  cinetPay: CinetPay(),
  phonepe: Phonepe(),
  sadadPay: SadadPay(),
  currency: Currency(),
).obs;

Rx<SystemService> selectedSysService = SystemService().obs;

//
Rx<PackageInfoData> currentPackageinfo = PackageInfoData().obs;

// Currency position common
bool get isCurrencyPositionLeft => appCurrency.value.currencyPosition == CurrencyPosition.CURRENCY_POSITION_LEFT;

bool get isCurrencyPositionRight => appCurrency.value.currencyPosition == CurrencyPosition.CURRENCY_POSITION_RIGHT;

bool get isCurrencyPositionLeftWithSpace => appCurrency.value.currencyPosition == CurrencyPosition.CURRENCY_POSITION_LEFT_WITH_SPACE;

bool get isCurrencyPositionRightWithSpace => appCurrency.value.currencyPosition == CurrencyPosition.CURRENCY_POSITION_RIGHT_WITH_SPACE;
//endregion

Rx<ServiceElement> currentSelectedService = ServiceElement().obs;
Rx<Clinic> currentSelectedClinic = Clinic(clinicSession: ClinicSession()).obs;
Rx<Doctor> currentSelectedDoctor = Doctor().obs;

RxList<AboutDataModel> aboutPages = RxList();

//Booking Success
RxString bookingSuccessDate = "".obs;
Rx<SaveBookingRes> saveBookingRes = SaveBookingRes(saveBookingResData: SaveBookingResData()).obs;
//

bool canLaunchVideoCall({required String status}) => status.toLowerCase().contains(StatusConst.confirmed) || status.toLowerCase().contains(StatusConst.checkIn);
