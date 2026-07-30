// ignore_for_file: must_be_immutable

import 'package:date_picker_timeline/date_picker_widget.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/utils/common_base.dart';

import '../../../../components/bottom_selection_widget.dart';
import '../../../../main.dart';
import '../../../components/loader_widget.dart';
import '../../../utils/colors.dart';
import '../../../utils/empty_error_state_widget.dart';
import '../../../utils/view_all_label_component.dart';
import '../appointment_detail_controller.dart';
import '../model/appointments_res_model.dart';

class ReschedulingComponent extends StatelessWidget {
  ReschedulingComponent({super.key, required this.bookingDetail});

  Rx<AppointmentData> bookingDetail;

  final AppointmentDetailController appointmentDetailCont = Get.find();

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Column(
          children: [
            10.height,
            Obx(
              () => SnapHelperWidget(
                future: appointmentDetailCont.timeSlotsFuture.value,
                errorBuilder: (error) {
                  return NoDataWidget(
                    title: error,
                    retryText: locale.value.reload,
                    imageWidget: const ErrorStateWidget(),
                    onRetry: () {
                      appointmentDetailCont.getTimeSlot();
                    },
                  ).paddingSymmetric(horizontal: 32);
                },
                loadingWidget: appointmentDetailCont.isLoading.value ? const Offstage() : const LoaderWidget(),
                onSuccess: (p0) {
                  if (appointmentDetailCont.slots.isEmpty) {
                    return NoDataWidget(title: locale.value.noTimeSlotsAvailable).paddingTop(12);
                  }

                  return Obx(
                    () => Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        ViewAllLabel(label: locale.value.chooseTime, isShowAll: false).paddingOnly(right: 8),
                        Container(
                          padding: const EdgeInsets.all(16),
                          decoration: boxDecorationDefault(color: context.cardColor),
                          child: Column(
                            children: [
                              AnimatedWrap(
                                spacing: 12,
                                runSpacing: 12,
                                children: List.generate(
                                  appointmentDetailCont.slots.length,
                                  (i) {
                                    final String slot = appointmentDetailCont.slots[i];
                                    return Obx(
                                      () => GestureDetector(
                                        onTap: () {
                                          appointmentDetailCont.selectedSlot(slot);
                                          appointmentDetailCont.onDateTimeChange();
                                        },
                                        child: Container(
                                          width: Get.width / 4 - 27,
                                          padding: const EdgeInsets.symmetric(vertical: 12),
                                          decoration: boxDecorationWithRoundedCorners(
                                            backgroundColor: appointmentDetailCont.selectedSlot.value == slot ? appColorPrimary : context.scaffoldBackgroundColor,
                                            borderRadius: BorderRadius.circular(defaultRadius / 2),
                                          ),
                                          child: Text(
                                            slot,
                                            textAlign: TextAlign.center,
                                            style: primaryTextStyle(
                                              size: 12,
                                              color: (appointmentDetailCont.selectedSlot.value == slot) ? Colors.white : appColorPrimary,
                                            ),
                                          ),
                                        ),
                                      ),
                                    );
                                  },
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  );
                },
              ),
            ),
            16.height,
          ],
        ),
        Obx(() => const LoaderWidget().visible(appointmentDetailCont.isLoading.value)),
      ],
    );
  }
}

void handleRescheduleClick({required BuildContext context, required RxBool isLoading, required Rx<AppointmentData> appointmentDetail, required AppointmentDetailController appointmentDetailCont}) {
  serviceCommonBottomSheet(
    context,
    child: BottomSelectionSheet(
      heightRatio: 0.6,
      title: locale.value.rescheduleBooking,
      hideSearchBar: true,
      hintText: locale.value.searchForService,
      searchTextCont: TextEditingController(),
      hasError: false,
      isLoading: appointmentDetailCont.isUpdateBookingLoading,
      isEmpty: false,
      noDataTitle: locale.value.statusListIsEmpty,
      noDataSubTitle: locale.value.thereAreNoStatusListedAtTheMomentStayTunedFor,
      listWidget: Stack(
        children: [
          SingleChildScrollView(
            child: Column(
              children: [
                ViewAllLabel(label: locale.value.chooseDate, isShowAll: false).paddingOnly(right: 8),
                Container(
                  decoration: boxDecorationDefault(color: context.cardColor),
                  child: DatePicker(
                    DateTime.now(),
                    initialSelectedDate: DateTime.now(),
                    selectionColor: lightPrimaryColor,
                    selectedTextColor: appColorPrimary,
                    height: 90,
                    onDateChange: (date) {
                      appointmentDetailCont.selectedDate(date.formatDateYYYYmmdd());
                      appointmentDetailCont.selectedSlot("");
                      appointmentDetailCont.getTimeSlot();
                      appointmentDetailCont.onDateTimeChange();
                    },
                  ),
                ),
                ReschedulingComponent(bookingDetail: appointmentDetail),
              ],
            ),
          ).paddingBottom(50),
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: Obx(
              () => AppButton(
                width: Get.width,
                text: locale.value.update,
                textStyle: appButtonTextStyleWhite,
                color: appointmentDetailCont.updateBtnVisible.value ? appColorSecondary : null,
                enabled: appointmentDetailCont.updateBtnVisible.value ? true : false,
                disabledColor: appointmentDetailCont.updateBtnVisible.value ? null : appColorSecondary.withValues(alpha: 0.5),
                shapeBorder: RoundedRectangleBorder(borderRadius: radius(defaultAppButtonRadius / 2)),
                onTap: () {
                  showConfirmDialogCustom(
                    context,
                    title: locale.value.doYouWantToChangeTheTimeSlotOfThisAppointment,
                    positiveText: locale.value.yes,
                    negativeText: locale.value.no,
                    primaryColor: context.primaryColor,
                    onAccept: (ctx) {
                      appointmentDetailCont.handleUpdateClick(context);
                    },
                  );
                },
              ),
            ),
          ),
        ],
      ).expand(),
    ),
    onSheetClose: (p0) {
      appointmentDetailCont.updateBtnVisible(false);
      appointmentDetailCont.selectedDate(DateTime.now().formatDateYYYYmmdd());
      appointmentDetailCont.selectedSlot("");
    },
  );
}
