// ignore_for_file: constant_identifier_names
import 'package:country_picker/country_picker.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../main.dart';
import '../screens/auth/model/common_model.dart';

//region DateFormats
class Constants {
  static const perPageItem = 20;
  static var labelTextSize = 16;
  static const mapLinkForIOS = 'http://maps.apple.com/?q=';
  static var googleMapPrefix =
      'https://www.google.com/maps/search/?api=1&query=';
  static const DEFAULT_EMAIL = 'john@gmail.com';
  static const DEFAULT_PASS = '12345678';
  static const appLogoSize = 120.0;
  static const DECIMAL_POINT = 2;
}
//endregion

//region DateFormats
class DateFormatConst {
  static const DD_MM_YY = "dd-MM-yy"; //Use to show only in UI
  static const DD_MM_YYYY = "dd-MM-yyyy"; //Use to show only in UI
  static const MMMM_D_yyyy = "MMMM d, y"; //Use to show only in UI
  static const D_MMMM_yyyy = "d MMMM, y"; //Use to show only in UI
  static const MMMM_D_yyyy_At_HH_mm_a =
      "MMMM d, y @ hh:mm a"; //Use to show only in UI
  static const EEEE_D_MMMM_At_HH_mm_a =
      "EEEE d MMMM @ hh:mm a"; //Use to show only in UI
  static const EEE_MMM_d_yyyy_At_HH_mm =
      "EEE, MMM d, yyyy 'at' HH:mm"; //Use to show only in UI
  static const dd_MMM_yyyy_HH_mm_a =
      "dd MMM y, hh:mm a"; //Use to show only in UI
  static const yyyy_MM_dd_HH_mm = 'yyyy-MM-dd HH:mm';
  static const yyyy_MM_dd = 'yyyy-MM-dd';
  static const HH_mm12Hour = 'hh:mm a';
  static const HH_mm24Hour = 'HH:mm';
  static const DATE_FORMAT_1 = 'dd-MMM-yyyy hh:mm a';
  static const BOOKING_SAVE_FORMAT = "yyyy-MM-dd kk:mm:ss";
}
//endregion

//region THEME MODE TYPE
const THEME_MODE_LIGHT = 0;
const THEME_MODE_DARK = 1;
const THEME_MODE_SYSTEM = 2;
//endregion

//region SharedPreferences Keys
const TOKEN = 'TOKEN';
//endregion

//region UserKeys
class UserKeys {
  static String firstName = 'first_name';
  static String lastName = 'last_name';
  static String userType = 'user_type';
  static String username = 'username';
  static String email = 'email';
  static String password = 'password';
  static String mobile = 'mobile';
  static String address = 'address';
  static String gender = 'gender';
  static String displayName = 'display_name';
  static String profileImage = 'profile_image';
  static String oldPassword = 'old_password';
  static String newPassword = 'new_password';
  static String loginType = 'login_type';
  static String contactNumber = 'contact_number';
  static String dateOfBirth = 'date_of_birth';
  static String userId = 'user_id';
}
//endregion

class OtherPatientConst {
  static String idKey = 'id';
  static String relation = 'relation';
  static String dob = 'dob';
  static String contactNumber = 'contactNumber';
}

//region LOGIN TYPE
class LoginTypeConst {
  static const LOGIN_TYPE_USER = 'user';
  static const LOGIN_TYPE_GOOGLE = 'google';
  static const LOGIN_TYPE_APPLE = 'apple';
}
//endregion

//region SharedPreference Keys
class SharedPreferenceConst {
  static const IS_LOGGED_IN = 'IS_LOGGED_IN';
  static const FCM_DEVICE_ID = 'FCM_DEVICE_ID';
  static const USER_DATA = 'USER_LOGIN_DATA';
  static const USER_EMAIL = 'USER_EMAIL';
  static const USER_PASSWORD = 'USER_PASSWORD';
  static const FIRST_TIME = 'FIRST_TIME';
  static const IS_REMEMBER_ME = 'IS_REMEMBER_ME';
  static const USER_NAME = 'USER_NAME';
  static const LOGIN_SUCCESSFULL = 'LOGIN_SUCCESSFULL';
  static const USER_ID = 'USER_ID';
  // static const ONE_TIME_PASSWORD = 'ONE_TIME_PASSWORD';
  static const IS_GOOGLE_AUTHENTICATION = 'IS_GOOGLE_AUTHENTICATION';
  static const GOOGLE_AUTHENTICATION_TYPE = 'IS_GOOGLE_AUTHENTICATION_TYPE';
}
//endregion

//region SettingsLocalConst
class SettingsLocalConst {
  static const THEME_MODE = 'THEME_MODE';
}
//endregion

//region defaultCountry
Country get defaultCountry => Country(
      phoneCode: '82',
      countryCode: 'KR',
      e164Sc: 82,
      geographic: true,
      level: 1,
      name: 'South Korea',
      example: '1012345678',
      displayName: 'South Korea (KR) [+82]',
      displayNameNoCountryCode: 'South Korea (KR)',
      e164Key: '82-KR-0',
      fullExampleWithPlusSign: '+821012345678',
    );
//endregion

//region LocatinKeys
class LocatinKeys {
  static const LATITUDE = 'LATITUDE';
  static const LONGITUDE = 'LONGITUDE';
  static const CURRENT_ADDRESS = 'CURRENT_ADDRESS';
  static const ZIP_CODE = 'ZIP_CODE';
}
//endregion

//region Currency position
class CurrencyPosition {
  static const CURRENCY_POSITION_LEFT = 'left';
  static const CURRENCY_POSITION_RIGHT = 'right';
  static const CURRENCY_POSITION_LEFT_WITH_SPACE = 'left_with_space';
  static const CURRENCY_POSITION_RIGHT_WITH_SPACE = 'right_with_space';
}
//endregion

//region Gender TYPE
class GenderTypeConst {
  static const MALE = 'male';
  static const FEMALE = 'female';
  static const OTHER = 'other';
}
//endregion

//region Status
class StatusConst {
  static const pending = 'pending';
  static const upcoming = 'upcoming';
  static const confirmed = 'confirmed';
  static const checkOut = 'checkout';
  static const cancel = 'cancel';
  static const checkIn = 'check_in';
  static const completed = 'completed';
  static const hold = 'hold';
  static const accepted = 'accept';
}

//region PaymentStatus
class PaymentStatus {
  static const PAID = 'paid';
  static const ADVANCE_PAID = 'advance_paid';
  static const pending = 'pending';
  static const failed = 'failed';
  static const ADVANCE_REFUNDED = 'advance_refunded';
  static const REFUNDED = 'refunded';
  static const UNPAID = 'unpaid';
}
//endregion

//region BOOKING STATUS
class BookingStatusConst {
  static const PENDING = 'pending';
  static const UPCOMING = 'upcoming';
  static const CONFIRMED = 'confirmed';
  static const CHECK_IN = 'check_in';
  static const CHECKOUT = 'checkout';
  static const CANCELLED = 'cancelled';
}
//endregion

class BookingUpdateKeys {
  static String reason = 'reason';
  static String startAt = 'start_at';
  static String endAt = 'end_at';
  static String date = 'date';

  static String durationDiff = 'duration_diff';
  static String paymentStatus = 'payment_status';

  static String serviceAddon = 'service_addon';

  static String type = 'type';
}

//region ClinicStatus
class ClinicStatus {
  static const OPEN = 'open';
  static const CLOSE = 'close';
}
//endregion

//region EncounterStatus Keys
class EncounterStatus {
  static const ACTIVE = 'active';
  static const CLOSED = 'closed';
}
//endregion

//region Banner Type
class BannerType {
  static const CATEGORY = 'category';
  static const SERVICE = 'service';
}
//endregion

//region ServicesKeyConst
class ServicesKeyConst {
  static const clinic = 'clinic';
  static const therapist = 'therapist';
  static const yoga = 'yoga';
}
//endregion

//region ServiceTypeConst Keys
class ServiceTypeConst {
  static const inClinic = 'in_clinic';
  static const online = 'online';
}
//endregion

//region TaxType Keys
class TaxType {
  static const FIXED = 'fixed';
  static const PERCENT = 'percent';
  static const PERCENTAGE = 'percentage';
  static const exclusiveTax = 'exclusive';
  static const inclusiveTax = 'inclusive';
}
//endregion

//region Payment Methods
class PaymentMethods {
  static const PAYMENT_METHOD_WALLET = 'wallet';
  static const PAYMENT_METHOD_CASH = 'cash';
  static const PAYMENT_METHOD_STRIPE = 'stripe';
  static const PAYMENT_METHOD_RAZORPAY = 'razorpay';
  static const PAYMENT_METHOD_PAYPAL = 'paypal';
  static const PAYMENT_METHOD_PAYSTACK = 'paystack';
  static const PAYMENT_METHOD_FLUTTER_WAVE = 'flutterwave';
  static const PAYMENT_METHOD_AIRTEL = 'airtel';
  static const PAYMENT_METHOD_PHONEPE = 'phonepe';
  static const PAYMENT_METHOD_MIDTRANS = 'midtrans';
  static const PAYMENT_METHOD_SADAD = 'sadad';
  static const PAYMENT_METHOD_CINETPAY = 'cinetpay';
  static const PAYMENT_METHOD_INICIS = 'inicis';
}
//endregion

//region FilterStatusConst
class ServiceFilterStatusConst {
  static const all = 'all';
  static const addedByMe = 'added_by_me';
  static const assignByAdmin = 'assign_by_admin';
  static const createdByAdmin = 'created_by_admin';
}
//endregion

//region Genders Consts
RxList<CMNModel> get genders => [
      CMNModel(id: 1, name: locale.value.male, slug: "male"),
      CMNModel(id: 2, name: locale.value.female, slug: "female"),
      CMNModel(id: 3, name: locale.value.other, slug: "other"),
    ].obs; //endregion

//region relation Consts List
RxList<CMNModel> relation = [
  CMNModel(
    id: 1,
    name: RelationConstant.parents.capitalizeFirstLetter(),
    slug: RelationConstant.parents,
  ),
  CMNModel(
    id: 3,
    name: RelationConstant.siblings.capitalizeFirstLetter(),
    slug: RelationConstant.siblings,
  ),
  CMNModel(
    id: 4,
    name: RelationConstant.spouse.capitalizeFirstLetter(),
    slug: RelationConstant.spouse,
  ),
  CMNModel(
    id: 5,
    name: RelationConstant.others.capitalizeFirstLetter(),
    slug: RelationConstant.others,
  ),
].obs;
//end

// incident status consts
RxList<CMNModel> get incidentStatuses => [
      CMNModel(id: 1, name: locale.value.open, slug: "open"),
      CMNModel(id: 2, name: locale.value.closed, slug: "closed"),
      CMNModel(id: 3, name: locale.value.rejected, slug: "reject"),
    ].obs;

// incident status consts
RxList<CMNModel> get incidentStatusesDropDown => [
      CMNModel(id: 1, name: locale.value.open, slug: "open"),
      CMNModel(id: 2, name: locale.value.closed, slug: "closed"),
    ].obs;

//region relation Consts
class RelationConstant {
  static const parents = 'parents';
  static const brother = 'brother';
  static const siblings = 'siblings';
  static const spouse = 'spouse';
  static const others = 'others';
}
//end

//region FilterStatusConst
class BookingFor {
  static const bookingForId = 'booking_for_id';
}
//endregion

//region NotificationConst
class NotificationConst {
  static const newAppointment = 'new_appointment';
  static const checkoutAppointment = 'checkout_appointment';
  static const rejectAppointment = 'reject_appointment';
  static const acceptAppointment = 'accept_appointment';
  static const cancelAppointment = 'cancel_appointment';
  static const rescheduleAppointment = 'reschedule_appointment';
  static const quickAppointment = 'quick_appointment';
  static const changePassword = 'change_password';
  static const forgetEmailPassword = 'forget_email_password';
  static const incidence_reply = 'incidence_reply';
}
//endregion

//region Firebase Notification
class FirebaseTopicConst {
  static const additionalDataKey = 'additional_data';
  static const notificationGroupKey = 'notification_group';
  static const shopKey = 'shop';
  static const orderCodeKey = 'order_code';
  static const bookingServicesNameKey = 'booking_services_names';
  static const idKey = 'id';
  static const itemIdKey = 'item_id';

  static const notificationKey = 'Notification';

  static const onMessageListen = "Error On Message Listen";
  static const onMessageOpened = "Error On Message Opened App";
  static const onGetInitialMessage = 'Error On Get Initial Message';

  static const messageDataCollapseKey = 'MessageData Collapse Key';

  static const messageDataMessageIdKey = 'MessageData Message Id';

  static const messageDataMessageTypeKey = 'MessageData Type';
  static const userWithUnderscoreKey = 'user_';
  static const notificationDataKey = 'Notification Data';

  static const fcmNotificationTokenKey = 'FCM Notification Token';
  static const apnsNotificationTokenKey = 'APNS Notification Token';
  static const notificationErrorKey = 'Notification Error';
  static const notificationTitleKey = 'Notification Title';
  static const notificationBodyKey = 'Notification Body';
  static const backgroundChannelIdKey = 'background_channel';
  static const backgroundChannelNameKey = 'background_channel';

  static const notificationChannelIdKey = 'notification';
  static const notificationChannelNameKey = 'Notification';

  static const topicSubscribed = 'topic-----subscribed---->';

  static const topicUnSubscribed = 'topic-----UnSubscribed---->';
}

//region Cancellation
class CancellationStatusKeys {
  static num id = 0;
  static String status = 'status';
  static String cancellationChargeAmount = 'cancellation_charge_amount';
  static String reason = 'reason';
  static String cancellationCharge = 'cancellation_charge';
  static String cancellationType = 'cancellation_type';
  static String advancePaidAmount = "advance_paid_amount";
  static String refundAmount = "refund_amount";
}
//endregion

const SERVICE_PAYMENT_STATUS_ADVANCE_PAID = 'advanced_paid';

class AutoUpdateConst {
  static String isAutoUpdateOn = 'isAutoUpdateOn';
}
