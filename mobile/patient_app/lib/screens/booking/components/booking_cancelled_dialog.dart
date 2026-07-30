import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/generated/assets.dart';
import 'package:kivicare_patient/main.dart';
import 'package:kivicare_patient/screens/booking/appointment_detail_screen.dart';
import 'package:kivicare_patient/screens/booking/model/appointments_res_model.dart';
import 'package:kivicare_patient/screens/booking/appointments_controller.dart';
import 'package:kivicare_patient/screens/dashboard/dashboard_controller.dart';
import 'package:kivicare_patient/screens/dashboard/components/menu.dart';
import 'package:kivicare_patient/utils/app_common.dart';
import 'package:kivicare_patient/utils/colors.dart';
import 'package:kivicare_patient/utils/common_base.dart';
import 'package:nb_utils/nb_utils.dart';

class BookingCancelledDialog extends StatelessWidget {
  final AppointmentData status;
  final String? currentStatus;

  const BookingCancelledDialog({
    super.key,
    required this.status,
    this.currentStatus,
  });

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Stack(
        alignment: Alignment.center,
        children: [
          SizedBox(
            width: ContextExtensions(context).width(),
            child: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Column(
                    children: [
                      16.height,
                      Image.asset(Assets.iconsIcCheck, height: 62),
                      32.height,
                      Text(locale.value.yourAppointmentHasBeenSuccessfullyCancelled, style: boldTextStyle(size: 16)),
                      4.height,
                      Text(locale.value.appointmentRefundWillBeProcessedWithingHoursIfApplicable, textAlign: TextAlign.center, style: primaryTextStyle(size: 12, color: textSecondaryColor)),
                      32.height,
                      Container(
                        padding: const EdgeInsets.all(14),
                        decoration: boxDecorationDefault(
                          color: appColorSecondary.withValues(alpha: 0.1),
                          border: Border.all(color: appColorSecondary),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          locale.value.noteCheckYourAppointmentHistoryForRefundDetailsIfApplicable,
                          style: boldTextStyle(color: appColorSecondary, size: 12),
                        ),
                      ),
                      24.height,
      
                      // Buttons in Row
                      Row(
                        children: [
                          // Cancel Button - Goes to appointments list
                          Expanded(
                            child: AppButton(
                              shapeBorder: RoundedRectangleBorder(borderRadius: radius(4)),
                              color: isDarkMode.value ? Colors.grey.withValues(alpha: 0.1) : extraLightPrimaryColor,
                              height: 40,
                              text: locale.value.cancel,
                              textStyle: appButtonPrimaryColorText,
                              onTap: () {
                                // Close the bottom sheet
                                Get.back();
                                // Navigate to appointments tab (index 1) in dashboard
                                try {
                                  final DashboardController dashboardController = Get.find();
                                  dashboardController.currentIndex(1);
                                  dashboardController.selectedBottomNav(bottomNavItems[1]);
                                  // Refresh appointments list
                                  final AppointmentsController appointmentsController = Get.find();
                                  appointmentsController.getAppointmentList();
                                } catch (e) {
                                  log('Error navigating to appointments: $e');
                                }
                              },
                            ),
                          ),
                          16.width,
                          // Go to History Button - Goes to appointment details
                          Expanded(
                            child: AppButton(
                              shapeBorder: RoundedRectangleBorder(borderRadius: radius(4)),
                              color: appColorSecondary,
                              height: 40,
                              text: "Go to History",
                              textStyle: boldTextStyle(),
                              onTap: () {
                                // Close the bottom sheet
                                Get.back();
                                // Navigate to appointment details
                                try {
                                  Get.to(() => AppointmentDetail(), arguments: status);
                                } catch (e) {
                                  log('Error navigating to appointment details: $e');
                                }
                              },
                            ),
                          ),
                        ],
                      ).paddingSymmetric(horizontal: 16),
                      8.height,
                    ],
                  ).paddingAll(16),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
