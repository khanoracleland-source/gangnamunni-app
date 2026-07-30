import 'package:flutter/material.dart';
import 'package:kivicare_patient/utils/app_common.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/components/cached_image_widget.dart';
import 'package:kivicare_patient/generated/assets.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/utils/colors.dart';

import '../../../api/auth_apis.dart';
import '../../../main.dart';
import '../../../utils/common_base.dart';
import '../../../utils/constants.dart';
import '../../booking/model/booking_req.dart';
import '../../payment/payment_controller.dart';
import '../../payment/payment_screen.dart';

class AppointmentSummaryWidget extends StatelessWidget {
  final BookingReq bookingData;
  final bool isQuickBook;

  const AppointmentSummaryWidget({super.key, required this.bookingData, this.isQuickBook = false});

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: Get.width,
          alignment: Alignment.center,
          padding: const EdgeInsets.only(top: 16, bottom: 16),
          decoration: boxDecorationDefault(color: context.cardColor, borderRadius: BorderRadius.circular(8)),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const CachedImageWidget(
                url: Assets.iconsIcBookingSummary,
                height: 50,
                width: 50,
                fit: BoxFit.contain,
              ),
              16.height,
              Text(locale.value.appointmentsSummary, style: boldTextStyle(size: 16)),
              8.height,
              Container(
                width: Get.width,
                alignment: Alignment.center,
                padding: const EdgeInsets.all(16),
                margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                decoration: boxDecorationDefault(color: isDarkMode.value ? appScreenBackgroundDark : appScreenGreyBackground, borderRadius: BorderRadius.circular(8)),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text("${locale.value.date}:", style: secondaryTextStyle(size: 12)).expand(),
                        16.width,
                        Text(
                          bookingData.appointmentDate.dateInDMMMMyyyyFormat,
                          maxLines: 1,
                          textAlign: TextAlign.end,
                          overflow: TextOverflow.ellipsis,
                          style: primaryTextStyle(size: 12),
                        ).expand(flex: 3),
                      ],
                    ),
                    8.height,
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text("${locale.value.time}:", style: secondaryTextStyle(size: 12)).expand(),
                        16.width,
                        Text(
                          bookingData.appointmentTime.format24HourtoAMPM,
                          maxLines: 1,
                          textAlign: TextAlign.end,
                          overflow: TextOverflow.ellipsis,
                          style: primaryTextStyle(size: 12),
                        ).expand(flex: 3),
                      ],
                    ),
                    8.height,
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text("${locale.value.service}:", style: secondaryTextStyle(size: 12)).expand(),
                        16.width,
                        Text(
                          bookingData.serviceName,
                          maxLines: 2,
                          textAlign: TextAlign.end,
                          overflow: TextOverflow.ellipsis,
                          style: primaryTextStyle(size: 12),
                        ).expand(flex: 3),
                      ],
                    ),
                    8.height,
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text("${locale.value.doctor}:", style: secondaryTextStyle(size: 12)).expand(),
                        16.width,
                        Text(
                          bookingData.doctorName,
                          maxLines: 2,
                          textAlign: TextAlign.end,
                          overflow: TextOverflow.ellipsis,
                          style: primaryTextStyle(size: 12),
                        ).expand(flex: 3),
                      ],
                    ),
                    8.height,
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text("${locale.value.clinic}:", style: secondaryTextStyle(size: 12)).expand(),
                        16.width,
                        Text(
                          bookingData.clinicName,
                          maxLines: 2,
                          textAlign: TextAlign.end,
                          overflow: TextOverflow.ellipsis,
                          style: primaryTextStyle(size: 12),
                        ).expand(flex: 3),
                      ],
                    ),
                    8.height,
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text("${locale.value.location}:", style: secondaryTextStyle(size: 12)).expand(),
                        16.width,
                        Text(
                          bookingData.location,
                          maxLines: 2,
                          textAlign: TextAlign.end,
                          overflow: TextOverflow.ellipsis,
                          style: primaryTextStyle(size: 12),
                        ).expand(flex: 3),
                      ],
                    ),
                  ],
                ),
              ),
              16.height,
              AppButton(
                margin: const EdgeInsets.symmetric(horizontal: 16),
                width: Get.width,
                text: locale.value.proceed,
                color: appColorSecondary,
                textStyle: appButtonTextStyleWhite,
                shapeBorder: RoundedRectangleBorder(borderRadius: radius(defaultAppButtonRadius / 2)),
                onTap: () {
                  Get.back(result: true);
                  paymentController = PaymentController();
                  paymentController.bookingData = bookingData;
                  if (isQuickBook) {
                    paymentController.paymentOption(PaymentMethods.PAYMENT_METHOD_CASH);
                  }
                  if (bookingData.isEnableAdvancePayment) {
                    paymentController.paymentOption(PaymentMethods.PAYMENT_METHOD_STRIPE);
                  }
                  if (!isQuickBook) AuthServiceApis.getUserWallet();
                  Get.to(() => PaymentScreen(isQuickBook: isQuickBook));
                },
              ),
            ],
          ),
        ),
      ],
    );
  }
}
