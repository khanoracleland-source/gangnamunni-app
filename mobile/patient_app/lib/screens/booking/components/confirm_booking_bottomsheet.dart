// ignore_for_file: must_be_immutable

import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/components/cached_image_widget.dart';
import 'package:kivicare_patient/configs.dart';
import 'package:kivicare_patient/generated/assets.dart';
import 'package:kivicare_patient/main.dart';
import 'package:kivicare_patient/utils/app_common.dart';
import 'package:kivicare_patient/utils/colors.dart';
import 'package:kivicare_patient/utils/constants.dart';
import 'package:kivicare_patient/utils/price_widget.dart';
import 'package:nb_utils/nb_utils.dart';

class ConfirmBookingBottomSheet extends StatelessWidget {
  final VoidCallback onConfirm;
  final String? titleText;
  final String? subTitleText;
  final String? confirmText;
  final String? changeToastMessage;
  final bool hideAgree;
  final String serviceName;
  final String dateTime;
  final num price;
  final bool? isQuickBook;

  ConfirmBookingBottomSheet({
    super.key,
    required this.onConfirm,
    required this.serviceName,
    required this.dateTime,
    required this.price,
    this.titleText,
    this.subTitleText,
    this.confirmText,
    this.hideAgree = false,
    this.changeToastMessage,
    this.isQuickBook,
  });

  RxBool isAgree = false.obs;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: SingleChildScrollView(
        child: Container(
          width: Get.width,
          padding: const EdgeInsets.only(top: 12, bottom: 30),
          decoration: boxDecorationDefault(color: context.scaffoldBackgroundColor),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              24.height,
              const CachedImageWidget(url: Assets.iconsIcConfirmation, height: 50, width: 50, fit: BoxFit.contain),
              16.height,
              Column(
                children: [
                  Text(
                    titleText ?? locale.value.confirmAppointment,
                    style: boldTextStyle(),
                    textAlign: TextAlign.center,
                  ),
                  16.height,
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: boxDecorationDefault(color: context.cardColor, borderRadius: radius(defaultRadius)),
                    child: Column(
                      spacing: 8,
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              locale.value.serviceName.suffixText(value: ": "),
                              style: secondaryTextStyle(),
                            ).expand(),
                            Text(serviceName, style: primaryTextStyle(size: 12)).expand(flex: 3),
                          ],
                        ),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              locale.value.dateTime.suffixText(value: ":"),
                              style: secondaryTextStyle(),
                            ).expand(),
                            Text(
                              dateTime,
                              style: primaryTextStyle(size: 12),
                              textAlign: TextAlign.start,
                            ).expand(flex: 3),
                          ],
                        ),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              locale.value.price.suffixText(value: ': '),
                              style: secondaryTextStyle(),
                            ).expand(),
                            PriceWidget(
                              price: price,
                              isSemiBoldText: true,
                              size: 12,
                            ).expand(flex: 3),
                          ],
                        ),
                      ],
                    ),
                  ),
                  16.height,
                  if (appConfigs.value.isCancellationChargeEnabled && isQuickBook != true) ...[
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                      width: double.infinity,
                      decoration: boxDecorationDefault(
                        color: appColorSecondary.withValues(alpha: 0.2),
                        borderRadius: radius(6),
                        border: Border.all(color: appColorSecondary, width: 1.5),
                      ),
                      child: Text(
                        locale.value.cancellationChargesWillBeAppliedForCancellationWithin(
                          appConfigs.value.cancellationType == TaxType.PERCENTAGE
                              ? appConfigs.value.isCancellationChargesAvailable
                                  ? '${appConfigs.value.cancellationCharge}%'
                                  : ''
                              : appConfigs.value.isCancellationChargesAvailable
                                  ? '${leftCurrencyFormat()}${appConfigs.value.cancellationCharge.toStringAsFixed(appCurrency.value.noOfDecimal).formatNumberWithComma(seperator: appCurrency.value.thousandSeparator)}'
                                  : '',
                          appConfigs.value.isCancellationHoursAvailable ? '${appConfigs.value.cancellationChargeHours}' : '',
                        ),
                        style: boldTextStyle(color: appColorSecondary, size: 12),
                      ),
                    ),
                    16.height,
                  ],
                  Obx(
                    () => CheckboxListTile(
                      checkColor: whiteColor,
                      value: isAgree.value,
                      activeColor: appColorPrimary,
                      onChanged: (val) async {
                        isAgree.value = !isAgree.value;
                      },
                      checkboxShape: const RoundedRectangleBorder(
                        borderRadius: BorderRadius.all(Radius.circular(5)),
                      ),
                      side: const BorderSide(color: secondaryTextColor, width: 1.5),
                      title: Text("${confirmText ?? locale.value.iHaveReadAll} $APP_NAME.", style: secondaryTextStyle()),
                      controlAffinity: ListTileControlAffinity.leading,
                      contentPadding: EdgeInsets.zero,
                    ).visible(!hideAgree),
                  ),
                ],
              ).paddingSymmetric(horizontal: 16),
              16.height,
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  AppButton(
                    text: locale.value.cancel,
                    textColor: isDarkMode.value ? white : blackColor,
                    color: context.cardColor,
                    onTap: () {
                      Get.back();
                    },
                  ).expand(),
                  32.width,
                  AppButton(
                    color: appColorSecondary,
                    text: locale.value.continueText,
                    onTap: () {
                      if (hideAgree) {
                        onConfirm.call();
                      } else {
                        if (isAgree.value) {
                          onConfirm.call();
                        } else {
                          toast(changeToastMessage ?? locale.value.pleaseAcceptTermsAnd);
                        }
                      }
                    },
                  ).expand(),
                ],
              ).paddingSymmetric(horizontal: 32),
            ],
          ),
        ),
      ),
    );
  }
}
