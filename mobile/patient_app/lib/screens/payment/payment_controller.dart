import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/api/core_apis.dart';
import 'package:kivicare_patient/main.dart';
import 'package:kivicare_patient/screens/booking/components/confirm_booking_bottomsheet.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../api/auth_apis.dart';
import '../../configs.dart';
import '../../payment_gateways/airtel_money/airtel_money_service.dart';
import '../../payment_gateways/cinet_pay_services.dart';
import '../../payment_gateways/flutter_wave_service.dart';
import '../../payment_gateways/midtrans_service.dart';
import '../../payment_gateways/pay_pal_service.dart';
import '../../payment_gateways/pay_stack_service.dart';
import '../../payment_gateways/phone_pe/phone_pe_service.dart';
import '../../payment_gateways/razor_pay_service.dart';
import '../../payment_gateways/sadad_services.dart';
import '../../payment_gateways/stripe_services.dart';
import '../../modules/payment/inicis/inicis_payment_controller.dart';
import '../../utils/app_common.dart';
import '../../utils/common_base.dart';
import '../../utils/constants.dart';
import '../booking/appointments_controller.dart';
import '../booking/model/booking_req.dart';
import '../booking/model/save_payment_req.dart';
import '../dashboard/dashboard_controller.dart';
import '../dashboard/dashboard_screen.dart';
import 'booking_success_screen.dart';

PaymentController paymentController = PaymentController();

class PaymentController extends GetxController {
  bool isFromBookingDetail;
  bool isAdvancePaymentFailed;
  bool isRemainingPayment;
  num? amount;
  int? bid;

  PaymentController({
    this.isFromBookingDetail = false,
    this.isAdvancePaymentFailed = false,
    this.isRemainingPayment = false,
    this.amount,
    this.bid,
  });

  //
  BookingReq bookingData = BookingReq();
  RxString paymentOption = PaymentMethods.PAYMENT_METHOD_CASH.obs;
  TextEditingController optionalCont = TextEditingController();
  RxBool isLoading = false.obs;

  RazorPayService razorPayService = RazorPayService();
  PayStackService paystackServices = PayStackService();
  FlutterWaveService flutterWaveServices = FlutterWaveService();
  PayPalService payPalService = PayPalService();
  MidtransService midtransPay = MidtransService();

  num get payAmount => isFromBookingDetail && amount.validate() > 0
      ? amount.validate()
      : bookingData.isEnableAdvancePayment
          ? bookingData.advancePayableAmount
          : bookingData.totalAmount;

  int get bookId => isFromBookingDetail && bid.validate() > 0 ? bid.validate() : saveBookingRes.value.saveBookingResData.id;

  void savePaymentApi({
    required int bid,
    required String txnId,
    required String paymentType,
  }) {
    isLoading(true);
    hideKeyBoardWithoutContext();
    CoreServiceApis.savePayment(
      request: SavePaymentReq(
        id: bid,
        externalTransactionId: txnId,
        transactionType: paymentType,
        taxPercentage: appConfigs.value.exclusiveTaxList,
        paymentStatus: paymentType == PaymentMethods.PAYMENT_METHOD_CASH || bookingData.isEnableAdvancePayment || (isFromBookingDetail && isAdvancePaymentFailed) ? 0 : 1,
        advancePaymentAmount: (isFromBookingDetail && isAdvancePaymentFailed) ? payAmount : bookingData.advancePayableAmount,
        advancePaymentStatus: (isFromBookingDetail && isAdvancePaymentFailed) ? 1 : bookingData.isEnableAdvancePayment.getIntBool(),
        remainingPaymentAmount: isRemainingPayment ? payAmount : 0,
      ).toJson(),
    ).then((value) async {
      if (isFromBookingDetail) {
        Get.back(result: true);
      } else {
        onPaymentSuccess();
      }
      isLoading(false);
    }).catchError((e) {
      isLoading(false);
      toast(e.toString(), print: true);
    });
  }

  void handleBookNowClick(BuildContext context, bool isQuickBook) {
    if (isFromBookingDetail) {
      payWithSelectedOption(context, isCashPayment: false);
    } else {
      Get.bottomSheet(
        isScrollControlled: true,
        ConfirmBookingBottomSheet(
          isQuickBook: isQuickBook,
          serviceName: paymentController.bookingData.serviceName.validate(),
          dateTime: "${paymentController.bookingData.appointmentDate.validate()} at ${paymentController.bookingData.appointmentTime.validate()}",
          price: paymentController.payAmount,
          titleText: locale.value.wouldYouLikeToProceedAndConfirmPayment,
          onConfirm: () {
            Get.back();
            if (saveBookingRes.value.saveBookingResData.id.isNegative) {
              saveBooking(context);
            } else {
              payWithSelectedOption(context);
            }
          },
        ),
      );
    }
  }

  void payWithSelectedOption(BuildContext context, {bool isCashPayment = true}) {
    if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_STRIPE) {
      payWithStripe(context);
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_RAZORPAY) {
      payWithRazorPay(context);
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_PHONEPE) {
      payWithPhonepe(context);
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_PAYSTACK) {
      payWithPayStack();
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_FLUTTER_WAVE) {
      payWithFlutterWave(context);
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_PAYPAL) {
      payWithPaypal(context);
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_AIRTEL) {
      payWithAirtelMoney(context);
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_MIDTRANS) {
      payWithMidtrans();
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_SADAD) {
      payWithSadad(context);
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_CINETPAY) {
      payWithCinetPay(context);
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_INICIS) {
      payWithInicis(context);
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_WALLET) {
      payWithWallet(context);
    } else if (paymentOption.value == PaymentMethods.PAYMENT_METHOD_CASH && isCashPayment) {
      payWithCash(context);
    }
  }

  Future<void> payWithStripe(BuildContext context) async {
    await StripeServices.stripePaymentMethod(
      loderOnOFF: (p0) {
        isLoading(p0);
      },
      amount: payAmount,
      onComplete: (res) {
        savePaymentApi(
          bid: bookId,
          paymentType: PaymentMethods.PAYMENT_METHOD_STRIPE,
          txnId: res["transaction_id"],
        );
      },
    );
  }

  Future<void> payWithRazorPay(BuildContext context) async {
    isLoading(true);
    razorPayService.init(
      razorKey: appConfigs.value.razorPay.razorpaySecretkey,
      totalAmount: payAmount,
      onComplete: (res) {
        log("txn id: $res");
        savePaymentApi(
          bid: bookId,
          paymentType: PaymentMethods.PAYMENT_METHOD_RAZORPAY,
          txnId: res["transaction_id"],
        );
      },
    );
    await Future.delayed(const Duration(seconds: 1));
    razorPayService.razorPayCheckout();
    await Future.delayed(const Duration(seconds: 2));
    isLoading(false);
  }

  Future<void> payWithPhonepe(BuildContext context) async {
    log("payWithPhonepe:");
    final PhonePeServices peServices = PhonePeServices(
      totalAmount: payAmount,
      bookingId: bookId,
      onComplete: (res) {
        log("txn id: $res");
        savePaymentApi(
          bid: bookId,
          paymentType: PaymentMethods.PAYMENT_METHOD_PHONEPE,
          txnId: res["transaction_id"],
        );
      },
    );

    peServices.phonePeCheckout(context);
  }

  Future<void> payWithPayStack() async {
    isLoading(true);
    paystackServices.init(
      loderOnOFF: (p0) {
        isLoading(p0);
      },
      totalAmount: payAmount,
      onComplete: (res) {
        log("txn id: $res");
        savePaymentApi(
          bid: bookId,
          paymentType: PaymentMethods.PAYMENT_METHOD_PAYSTACK,
          txnId: res["transaction_id"],
        );
      },
    );
    await Future.delayed(const Duration(seconds: 1));
    isLoading(false);
    if (Get.context != null) {
      paystackServices.checkout();
    } else {
      toast("context not found!!!!");
    }
  }

  Future<void> payWithFlutterWave(BuildContext context) async {
    isLoading(true);
    flutterWaveServices.checkout(
      ctx: context,
      loderOnOFF: (p0) {
        isLoading(p0);
      },
      totalAmount: payAmount,
      isTestMode: appConfigs.value.flutterwavePay.flutterwavePublickey.toLowerCase().contains("test"),
      onComplete: (res) {
        log("txn id: $res");
        savePaymentApi(
          bid: bookId,
          paymentType: PaymentMethods.PAYMENT_METHOD_FLUTTER_WAVE,
          txnId: res["transaction_id"],
        );
      },
    );
    await Future.delayed(const Duration(seconds: 1));
    isLoading(false);
  }

  void payWithPaypal(BuildContext context) {
    isLoading(true);
    payPalService.paypalCheckOut(
      context: context,
      loderOnOFF: (p0) {
        isLoading(p0);
      },
      totalAmount: payAmount,
      onComplete: (res) {
        log("txn id: $res");
        savePaymentApi(
          bid: bookId,
          paymentType: PaymentMethods.PAYMENT_METHOD_PAYPAL,
          txnId: res["transaction_id"],
        );
      },
    );
  }

  Future<void> payWithAirtelMoney(BuildContext context) async {
    isLoading(true);
    showInDialog(
      context,
      contentPadding: EdgeInsets.zero,
      barrierDismissible: false,
      builder: (context) {
        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              padding: const EdgeInsets.fromLTRB(16, 4, 4, 8),
              width: Get.width,
              decoration: boxDecorationDefault(
                color: context.primaryColor,
                borderRadius: radiusOnly(topRight: defaultRadius, topLeft: defaultRadius),
              ),
              child: Row(
                children: [
                  Text("Airtel Money Payment", style: boldTextStyle(color: Colors.white)).expand(),
                  const CloseButton(color: Colors.white),
                ],
              ),
            ),
            16.height,
            AirtelMoneyDialog(
              bookingId: bookId,
              amount: payAmount,
              reference: APP_NAME,
              onComplete: (res) {
                log("txn id: $res");
                savePaymentApi(
                  bid: bookId,
                  paymentType: PaymentMethods.PAYMENT_METHOD_AIRTEL,
                  txnId: res["transaction_id"],
                );
              },
            ),
          ],
        );
      },
    ).then((value) => isLoading(false));
  }

  Future<void> payWithMidtrans() async {
    isLoading(true);
    midtransPay.initialize(
      totalAmount: payAmount,
      onComplete: (res) {
        log("txn id: $res");
        savePaymentApi(
          bid: bookId,
          paymentType: PaymentMethods.PAYMENT_METHOD_MIDTRANS,
          txnId: res["transaction_id"],
        );
      },
      loaderOnOFF: (v) {
        isLoading(v);
      },
    );
    await Future.delayed(const Duration(seconds: 1));
    midtransPay.midtransPaymentCheckout();
    await Future.delayed(const Duration(seconds: 2));
    isLoading(false);
  }

  Future<void> payWithSadad(BuildContext context) async {
    final SadadServices sadadServices = SadadServices(
      totalAmount: payAmount,
      onComplete: (res) {
        log("txn id: $res");
        savePaymentApi(
          bid: bookId,
          paymentType: PaymentMethods.PAYMENT_METHOD_SADAD,
          txnId: res["transaction_id"],
        );
      },
    );
    sadadServices.payWithSadad(context);
  }

  Future<void> payWithCinetPay(BuildContext context) async {
    final CinetPayServices cinetPay = CinetPayServices(
      totalAmount: payAmount,
      onComplete: (res) {
        log("txn id: $res");
        savePaymentApi(
          bid: bookId,
          paymentType: PaymentMethods.PAYMENT_METHOD_CINETPAY,
          txnId: res["transaction_id"],
        );
      },
    );
    cinetPay.payWithCinetPay(context: context);
  }

  Future<void> payWithCash(BuildContext context) async {
    savePaymentApi(
      bid: bookId,
      paymentType: PaymentMethods.PAYMENT_METHOD_CASH,
      txnId: isFromBookingDetail && bid.validate() > 0 ? "#${bid.validate()}" : "",
    );
  }

  Future<void> payWithWallet(BuildContext context) async {
    savePaymentApi(
      bid: bookId,
      paymentType: PaymentMethods.PAYMENT_METHOD_WALLET,
      txnId: isFromBookingDetail && bid.validate() > 0 ? "#${bid.validate()}" : "",
    );
  }

  Future<void> payWithInicis(BuildContext context) async {
    isLoading(true);
    final InicisPaymentController inicisController = Get.put(InicisPaymentController());

    // Generate unique order number
    final orderNumber = inicisController.generateOrderNumber();

    // Get user data from SharedPreferences
    final userName = getStringAsync(SharedPreferenceConst.USER_NAME, defaultValue: 'Guest');
    final userEmail = getStringAsync(SharedPreferenceConst.USER_EMAIL, defaultValue: 'guest@example.com');
    final userPhone = getStringAsync(SharedPreferenceConst.USER_EMAIL, defaultValue: '010-0000-0000');

    final success = await inicisController.startPayment(
      context: context,
      orderNumber: orderNumber,
      amount: payAmount.toDouble(),
      productName: bookingData.serviceName.validate(),
      buyerName: userName,
      buyerEmail: userEmail,
      buyerTel: userPhone,
    );

    isLoading(false);

    if (success) {
      // The tid will be part of the payment verification process
      savePaymentApi(
        bid: bookId,
        paymentType: PaymentMethods.PAYMENT_METHOD_INICIS,
        txnId: orderNumber,
      );
    } else {
      toast(locale.value.somethingWentWrong);
    }
  }

  void saveBooking(BuildContext context, {List<PlatformFile>? files}) {
    isLoading(true);

    CoreServiceApis.bookServiceApi(
      request: bookingData.toJson(),
      files: bookingData.files,
      onSuccess: () async {
        payWithSelectedOption(context);
      },
      loaderOff: () {
        isLoading(false);
      },
    ).then((value) {}).catchError((e) {
      isLoading(false);
      toast(e.toString(), print: true);
    });
  }

  Future<void> onPaymentSuccess() async {
    isLoading(false);
    reLoadBookingsOnDashboard();
    await Future.delayed(const Duration(milliseconds: 300));
    Get.offUntil(
      GetPageRoute(
        page: () => BookingSuccessScreen(),
        binding: BindingsBuilder(() {
          setStatusBarColor(transparentColor, statusBarIconBrightness: Brightness.dark, statusBarBrightness: Brightness.dark);
        }),
      ),
      (route) => route.isFirst || route.settings.name == '/$DashboardScreen',
    );
  }
}

void reLoadBookingsOnDashboard() {
  try {
    final AppointmentsController aCont = Get.find();
    aCont.getAppointmentList();
  } catch (e) {
    log('E: $e');
  }
  try {
    final DashboardController dashboardController = Get.find();
    dashboardController.currentIndex(1);
    dashboardController.reloadBottomTabs();
  } catch (e) {
    log('E: $e');
  }
  AuthServiceApis.getUserWallet();
}
