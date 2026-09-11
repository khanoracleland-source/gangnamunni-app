import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../components/app_scaffold.dart';
import '../../generated/assets.dart';
import '../../main.dart';
import '../../utils/app_common.dart';
import '../../utils/colors.dart';
import '../../utils/common_base.dart';
import '../../utils/constants.dart';
import '../../utils/price_widget.dart';
import 'payment_controller.dart';

class PaymentScreen extends StatelessWidget {
  final bool isQuickBook;

  const PaymentScreen({super.key, this.isQuickBook = false});

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.payment,
      isLoading: paymentController.isLoading,
      appBarVerticalSize: Get.height * 0.12,
      body: SingleChildScrollView(
        padding: const EdgeInsets.only(bottom: 80),
        physics: const AlwaysScrollableScrollPhysics(),
        child: Obx(
          () => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                "* ${locale.value.noteForCashPaymentPurposesDontUseThePayNowBut}",
                style: secondaryTextStyle(color: appColorSecondary, size: 11, fontStyle: FontStyle.italic),
              ).paddingTop(16).visible(paymentController.isFromBookingDetail && !paymentController.isAdvancePaymentFailed),
              16.height,
              Text(locale.value.choosePaymentMethod, style: primaryTextStyle(size: 18)),
              8.height,
              Text(locale.value.chooseOurConvenientPaymentOptionAndUnlockUnli, style: secondaryTextStyle()),
              32.height,
              cashAfterService(context).visible(isQuickBook).paddingOnly(bottom: 8),
              Column(
                //spacing: 8,
                children: [
                  if (!paymentController.bookingData.isOnlineService && !paymentController.isFromBookingDetail && !paymentController.bookingData.isEnableAdvancePayment) cashAfterService(context).paddingOnly(bottom: 8),
                  walletPayment(context).paddingOnly(bottom: 8),
                  stripePaymentWidget(context).paddingOnly(bottom: 8).visible(appConfigs.value.stripePay.stripePublickey.isNotEmpty && appConfigs.value.stripePay.stripeSecretkey.isNotEmpty),
                  razorPaymentWidget(context).paddingOnly(bottom: 8).visible(appConfigs.value.razorPay.razorpaySecretkey.isNotEmpty),
                  phonePayPaymentWidget(context)
                      .visible(appConfigs.value.phonepe.phonepeAppId.isNotEmpty && appConfigs.value.phonepe.phonepeMerchantId.isNotEmpty && appConfigs.value.phonepe.phonepeSaltKey.isNotEmpty && appConfigs.value.phonepe.phonepeSaltIndex.isNotEmpty),
                  payStackPaymentWidget(context).visible(appConfigs.value.paystackPay.paystackPublickey.isNotEmpty && appConfigs.value.paystackPay.paystackSecretkey.isNotEmpty).paddingOnly(bottom: 8),
                  payPalPaymentWidget(context).visible(appConfigs.value.paypalPay.paypalClientid.isNotEmpty && appConfigs.value.paypalPay.paypalSecretkey.isNotEmpty).paddingOnly(bottom: 8),
                  flutterWavePaymentWidget(context).visible(appConfigs.value.flutterwavePay.flutterwaveSecretkey.isNotEmpty && appConfigs.value.flutterwavePay.flutterwavePublickey.isNotEmpty).paddingOnly(bottom: 8),
                  airtelMoneyPaymentWidget(context).visible(appConfigs.value.airtelMoney.airtelSecretkey.isNotEmpty && appConfigs.value.airtelMoney.airtelClientid.isNotEmpty).paddingOnly(bottom: 8),
                  midtransPay(context).visible(appConfigs.value.midtransPay.midtransClientKey.isNotEmpty).paddingOnly(bottom: 8),
                  sadadPay(context).visible(appConfigs.value.sadadPay.sadadSecretKey.isNotEmpty && appConfigs.value.sadadPay.sadadId.isNotEmpty && appConfigs.value.sadadPay.sadadDomain.isNotEmpty).paddingOnly(bottom: 8),
                  cinetPay(context).visible(appConfigs.value.cinetPay.siteId.isNotEmpty && appConfigs.value.cinetPay.cinetPayAPIKey.isNotEmpty),
                  inicisPaymentWidget(context).paddingOnly(bottom: 8),
                ],
              ).visible(!isQuickBook),
            ],
          ).paddingSymmetric(horizontal: 16),
        ),
      ).makeRefreshable,
      widgetsStackedOverBody: [
        Positioned(
          bottom: 16,
          left: 16,
          right: 16,
          child: AppButton(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
            color: appColorSecondary,
            onTap: () {
              paymentController.handleBookNowClick(context, isQuickBook);
            },
            textStyle: appButtonFontColorText,
            child: Text(locale.value.proceed, style: primaryTextStyle(color: Colors.white)),
          ),
        ),
      ],
    );
  }

  Widget stripePaymentWidget(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Obx(
            () => RadioGroup<String>(
              groupValue: paymentController.paymentOption.value,
              onChanged: (String? newMethod) {
                if (newMethod != null) {
                  paymentController.paymentOption(newMethod);
                }
              },
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: const EdgeInsets.symmetric(vertical: 2),
                    tileColor: context.cardColor,
                    controlAffinity: ListTileControlAffinity.trailing,
                    shape: RoundedRectangleBorder(borderRadius: radius()),
                    secondary: const Image(
                      image: AssetImage(Assets.imagesStripeLogo),
                      height: 16,
                      width: 22,
                    ),
                    fillColor: WidgetStateProperty.all(appColorPrimary),
                    title: Text("Stripe", style: primaryTextStyle()),
                    value: PaymentMethods.PAYMENT_METHOD_STRIPE,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget razorPaymentWidget(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Obx(
            () => RadioGroup<String>(
              groupValue: paymentController.paymentOption.value,
              onChanged: (String? newMethod) {
                if (newMethod != null) {
                  paymentController.paymentOption(newMethod);
                }
              },
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: const EdgeInsets.symmetric(vertical: 2),
                    tileColor: context.cardColor,
                    controlAffinity: ListTileControlAffinity.trailing,
                    shape: RoundedRectangleBorder(borderRadius: radius()),
                    secondary: const Image(
                      image: AssetImage(Assets.imagesRazorpayLogo),
                      height: 16,
                      width: 22,
                    ),
                    fillColor: WidgetStateProperty.all(appColorPrimary),
                    title: Text("Razor Pay", style: primaryTextStyle()),
                    value: PaymentMethods.PAYMENT_METHOD_RAZORPAY,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget phonePayPaymentWidget(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Obx(
            () => RadioGroup<String>(
              groupValue: paymentController.paymentOption.value,
              onChanged: (String? newMethod) {
                if (newMethod != null) {
                  paymentController.paymentOption(newMethod);
                }
              },
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: const EdgeInsets.symmetric(vertical: 2),
                    tileColor: context.cardColor,
                    controlAffinity: ListTileControlAffinity.trailing,
                    shape: RoundedRectangleBorder(borderRadius: radius()),
                    secondary: const Image(
                      image: AssetImage(Assets.imagesPhonepeLogo),
                      height: 18,
                      width: 24,
                    ),
                    fillColor: WidgetStateProperty.all(appColorPrimary),
                    title: Text("PhonePe", style: primaryTextStyle()),
                    value: PaymentMethods.PAYMENT_METHOD_PHONEPE,
                    // Note: No groupValue or onChanged here anymore
                  ),
                  // Add more RadioListTile<PaymentMethods> here for other options if needed
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget payStackPaymentWidget(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Obx(
            () => RadioGroup<String>(
              groupValue: paymentController.paymentOption.value,
              onChanged: (String? newMethod) {
                if (newMethod != null) {
                  paymentController.paymentOption(newMethod);
                }
              },
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: const EdgeInsets.symmetric(vertical: 2),
                    tileColor: context.cardColor,
                    controlAffinity: ListTileControlAffinity.trailing,
                    shape: RoundedRectangleBorder(borderRadius: radius()),
                    secondary: const Image(
                      image: AssetImage(Assets.imagesPhonepeLogo),
                      height: 18,
                      width: 24,
                    ),
                    fillColor: WidgetStateProperty.all(appColorPrimary),
                    title: Text("PhonePe", style: primaryTextStyle()),
                    value: PaymentMethods.PAYMENT_METHOD_PHONEPE, // <-- using String
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget payPalPaymentWidget(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Obx(
            () => RadioGroup<String>(
              groupValue: paymentController.paymentOption.value,
              onChanged: (String? newValue) {
                if (newValue != null) {
                  paymentController.paymentOption(newValue);
                }
              },
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: const EdgeInsets.symmetric(vertical: 2),
                    tileColor: context.cardColor,
                    controlAffinity: ListTileControlAffinity.trailing,
                    shape: RoundedRectangleBorder(borderRadius: radius()),
                    secondary: const Image(
                      image: AssetImage(Assets.imagesPaypalLogo),
                      height: 16,
                      width: 22,
                    ),
                    fillColor: WidgetStateProperty.all(appColorPrimary),
                    title: Text("PayPal", style: primaryTextStyle()),
                    value: PaymentMethods.PAYMENT_METHOD_PAYPAL,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget flutterWavePaymentWidget(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Obx(
            () => RadioGroup<String>(
              groupValue: paymentController.paymentOption.value,
              onChanged: (String? newValue) {
                if (newValue != null) {
                  paymentController.paymentOption(newValue);
                }
              },
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: const EdgeInsets.symmetric(vertical: 2),
                    tileColor: context.cardColor,
                    controlAffinity: ListTileControlAffinity.trailing,
                    shape: RoundedRectangleBorder(borderRadius: radius()),
                    secondary: const Image(
                      image: AssetImage(Assets.imagesFlutterWaveLogo),
                      height: 16,
                      width: 22,
                    ),
                    fillColor: WidgetStateProperty.all(appColorPrimary),
                    title: Text("FlutterWave", style: primaryTextStyle()),
                    value: PaymentMethods.PAYMENT_METHOD_FLUTTER_WAVE,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget airtelMoneyPaymentWidget(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Obx(
            () => RadioGroup<String>(
              groupValue: paymentController.paymentOption.value,
              onChanged: (String? newValue) {
                if (newValue != null) {
                  paymentController.paymentOption(newValue);
                }
              },
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: const EdgeInsets.symmetric(vertical: 2),
                    tileColor: context.cardColor,
                    controlAffinity: ListTileControlAffinity.trailing,
                    shape: RoundedRectangleBorder(borderRadius: radius()),
                    secondary: const Image(
                      image: AssetImage(Assets.imagesAirtelLogo),
                      height: 16,
                      width: 22,
                    ),
                    fillColor: WidgetStateProperty.all(appColorPrimary),
                    title: Text("Airtel Money", style: primaryTextStyle()),
                    value: PaymentMethods.PAYMENT_METHOD_AIRTEL,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget midtransPay(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Obx(
            () => RadioGroup<String>(
              groupValue: paymentController.paymentOption.value,
              onChanged: (String? newMethod) {
                if (newMethod != null) {
                  paymentController.paymentOption(newMethod);
                }
              },
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: const EdgeInsets.symmetric(vertical: 2),
                    tileColor: context.cardColor,
                    controlAffinity: ListTileControlAffinity.trailing,
                    shape: RoundedRectangleBorder(borderRadius: radius()),
                    secondary: const Image(
                      image: AssetImage(Assets.imagesMidtransLogo),
                      height: 16,
                      width: 22,
                    ),
                    fillColor: WidgetStateProperty.all(appColorPrimary),
                    title: Text("Midtrans", style: primaryTextStyle()),
                    value: PaymentMethods.PAYMENT_METHOD_MIDTRANS,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget sadadPay(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Obx(
            () => RadioGroup<String>(
              groupValue: paymentController.paymentOption.value,
              onChanged: (String? newValue) {
                if (newValue != null) {
                  paymentController.paymentOption(newValue);
                }
              },
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: const EdgeInsets.symmetric(vertical: 2),
                    tileColor: context.cardColor,
                    controlAffinity: ListTileControlAffinity.trailing,
                    shape: RoundedRectangleBorder(borderRadius: radius()),
                    secondary: Image(
                      color: isDarkMode.value ? white : black,
                      image: const AssetImage(Assets.imagesSadadLogo),
                      height: 16,
                      width: 22,
                    ),
                    fillColor: WidgetStateProperty.all(appColorPrimary),
                    title: Text("Sadad", style: primaryTextStyle()),
                    value: PaymentMethods.PAYMENT_METHOD_SADAD, // using String
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget cinetPay(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Obx(
            () => RadioGroup<String>(
              groupValue: paymentController.paymentOption.value,
              onChanged: (String? newValue) {
                if (newValue != null) {
                  paymentController.paymentOption(newValue);
                }
              },
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: const EdgeInsets.symmetric(vertical: 2),
                    tileColor: context.cardColor,
                    controlAffinity: ListTileControlAffinity.trailing,
                    shape: RoundedRectangleBorder(borderRadius: radius()),
                    secondary: const Image(
                      image: AssetImage(Assets.imagesCinetpayLogo),
                      height: 16,
                      width: 22,
                    ),
                    fillColor: WidgetStateProperty.all(appColorPrimary),
                    title: Text("CinetPay", style: primaryTextStyle()),
                    value: PaymentMethods.PAYMENT_METHOD_CINETPAY,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget cashAfterService(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Obx(
        () => RadioGroup<String>(
          groupValue: paymentController.paymentOption.value,
          onChanged: (String? newValue) {
            if (newValue != null) {
              paymentController.paymentOption(newValue);
            }
          },
          child: Column(
            children: [
              RadioListTile<String>(
                dense: true,
                contentPadding: const EdgeInsets.symmetric(vertical: 2),
                tileColor: context.cardColor,
                controlAffinity: ListTileControlAffinity.trailing,
                shape: RoundedRectangleBorder(borderRadius: radius()),
                secondary: const Image(
                  image: AssetImage(Assets.iconsIcCash),
                  color: appColorPrimary,
                  height: 18,
                  width: 24,
                ),
                fillColor: WidgetStateProperty.all(appColorPrimary),
                title: Text("Cash after service", style: primaryTextStyle()),
                value: PaymentMethods.PAYMENT_METHOD_CASH,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget inicisPaymentWidget(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Column(
        children: [
          Obx(
            () => RadioGroup<String>(
              groupValue: paymentController.paymentOption.value,
              onChanged: (String? newValue) {
                if (newValue != null) {
                  paymentController.paymentOption(newValue);
                }
              },
              child: Column(
                children: [
                  RadioListTile<String>(
                    contentPadding: const EdgeInsets.symmetric(vertical: 2),
                    tileColor: context.cardColor,
                    controlAffinity: ListTileControlAffinity.trailing,
                    shape: RoundedRectangleBorder(borderRadius: radius()),
                    secondary: const Image(
                      image: AssetImage(Assets.imagesInicisLogo),
                      height: 16,
                      width: 22,
                    ),
                    fillColor: WidgetStateProperty.all(appColorPrimary),
                    title: Text("INICIS (이니시스)", style: primaryTextStyle()),
                    value: PaymentMethods.PAYMENT_METHOD_INICIS,
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget walletPayment(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: boxDecorationDefault(color: context.cardColor),
      child: Obx(
        () => RadioGroup<String>(
          groupValue: paymentController.paymentOption.value,
          onChanged: (String? newValue) {
            if (newValue != null) {
              // Ensure the user has sufficient wallet balance
              final walletAmount = userWalletData.value.walletAmount;
              final payAmount = paymentController.payAmount;

              if (walletAmount >= payAmount) {
                paymentController.paymentOption(newValue);
              } else {
                toast(locale.value.youDontHaveEnoughBalanceToCompleteThePaymentU);
              }
            }
          },
          child: Column(
            children: [
              RadioListTile<String>(
                dense: true,
                contentPadding: const EdgeInsets.symmetric(vertical: 2),
                tileColor: context.cardColor,
                controlAffinity: ListTileControlAffinity.trailing,
                shape: RoundedRectangleBorder(borderRadius: radius()),
                secondary: const Image(
                  image: AssetImage(Assets.iconsIcUnFillWallet),
                  color: appColorPrimary,
                  height: 18,
                  width: 24,
                ),
                fillColor: WidgetStateProperty.all(appColorPrimary),
                title: Row(
                  children: [
                    Text("Wallet", style: primaryTextStyle()),
                    8.width,
                    Text("( ", style: primaryTextStyle(color: completedStatusColor)),
                    PriceWidget(
                      price: userWalletData.value.walletAmount,
                      color: completedStatusColor,
                      size: 14,
                    ),
                    Text(" )", style: primaryTextStyle(color: completedStatusColor)),
                  ],
                ),
                value: PaymentMethods.PAYMENT_METHOD_WALLET,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
