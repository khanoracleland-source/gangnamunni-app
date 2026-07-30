import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/components/appointment_detail_applied_tax_list_bottom_sheet.dart';
import 'package:kivicare_patient/screens/booking/components/cancellations_booking_charge_dialog.dart';
import 'package:kivicare_patient/screens/booking/components/rescheduling_component.dart';
import 'package:kivicare_patient/screens/booking/model/appointments_res_model.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/app_scaffold.dart';
import '../../../utils/common_base.dart';
import '../../api/auth_apis.dart';
import '../../components/cached_image_widget.dart';
import '../../components/chat_gpt_loder.dart';
import '../../components/common_file_placeholders.dart';
import '../../components/loader_widget.dart';
import '../../components/zoom_image_screen.dart';
import '../../generated/assets.dart';
import '../../main.dart';
import '../../utils/app_common.dart';
import '../../utils/colors.dart';
import '../../utils/constants.dart';
import '../../utils/empty_error_state_widget.dart';
import '../../utils/price_widget.dart';
import '../../utils/view_all_label_component.dart';
import '../payment/payment_controller.dart';
import '../payment/payment_screen.dart';
import 'appointment_detail_controller.dart';
import 'appointments_controller.dart';
import 'components/service_info_card_widget.dart';
import 'encounter_detail_screen.dart';
import 'model/appointment_detail_res.dart';

class AppointmentDetail extends StatelessWidget {
  AppointmentDetail({super.key});

  final AppointmentDetailController appointmentDetailCont = Get.put(AppointmentDetailController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      isLoading: appointmentDetailCont.isLoading,
      appBartitleText: "${locale.value.appointment} #${appointmentDetailCont.appointmentDetail.value.id}",
      appBarVerticalSize: Get.height * 0.12,
      body: RefreshIndicator(
        onRefresh: () {
          return appointmentDetailCont.init(showLoader: false);
        },
        child: Obx(
          () => SnapHelperWidget(
            future: appointmentDetailCont.getAppointmentDetails.value,
            errorBuilder: (error) {
              return NoDataWidget(
                title: error,
                retryText: locale.value.reload,
                imageWidget: const ErrorStateWidget(),
                onRetry: () {
                  appointmentDetailCont.init();
                },
              ).paddingSymmetric(horizontal: 16);
            },
            loadingWidget: const LoaderWidget(),
            onSuccess: (appointmentDetailRes) {
              return AnimatedScrollView(
                listAnimationType: ListAnimationType.FadeIn,
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.only(bottom: 30),
                children: [
                  16.height,
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      ViewAllLabel(label: locale.value.appointmentDetail, isShowAll: false).flexible(),
                      //  ViewAllLabel(label: locale.value.appointmentDetail, isShowAll: false),
                      if (appointmentDetailCont.appointmentDetail.value.status.contains(BookingStatusConst.PENDING))

                        ///Reschedule Appointment
                        SizedBox(
                          height: 23,
                          child: AppButton(
                            text: locale.value.reschedule,
                            padding: EdgeInsets.zero,
                            textStyle: secondaryTextStyle(color: Colors.white),
                            shapeBorder: RoundedRectangleBorder(borderRadius: radius(4)),
                            onTap: () {
                              appointmentDetailCont.getTimeSlot();
                              handleRescheduleClick(
                                context: context,
                                isLoading: appointmentDetailCont.isLoading,
                                appointmentDetail: appointmentDetailCont.appointmentDetail,
                                appointmentDetailCont: appointmentDetailCont,
                              );
                            },
                          ),
                        )
                      else if (appointmentDetailCont.appointmentDetail.value.status.contains(BookingStatusConst.CHECKOUT))

                        ///Invoice Download
                        SizedBox(
                          height: 23,
                          child: AppButton(
                            padding: EdgeInsets.zero,
                            textStyle: secondaryTextStyle(color: Colors.white),
                            shapeBorder: RoundedRectangleBorder(borderRadius: radius(4)),
                            onTap: () {
                              appointmentDetailCont.getAppointmentInvoice();
                            },
                            child: Row(
                              children: [
                                const CachedImageWidget(
                                  url: Assets.iconsIcDownload,
                                  height: 14,
                                  width: 14,
                                  color: white,
                                ),
                                6.width,
                                Text(locale.value.invoice, style: primaryTextStyle(size: 12, color: white)),
                              ],
                            ),
                          ),
                        ),
                    ],
                  ).paddingSymmetric(horizontal: 16),
                  Container(
                    width: Get.width,
                    padding: const EdgeInsets.all(16),
                    decoration: boxDecorationDefault(color: context.cardColor),
                    child: Column(
                      children: [
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text("${locale.value.dateTime}:", style: secondaryTextStyle(size: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                                6.height,
                                Text(
                                  "${appointmentDetailCont.appointmentDetail.value.appointmentDate.dateInYYYYMMDDFormat} at ${appointmentDetailCont.appointmentDetail.value.appointmentTime.format24HourtoAMPM}",
                                  style: boldTextStyle(size: 12),
                                ),
                              ],
                            ).expand(flex: 3),
                            16.width,
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text("${locale.value.serviceName}:", style: secondaryTextStyle(size: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                                6.height,
                                Text(appointmentDetailCont.appointmentDetail.value.serviceName, style: boldTextStyle(size: 12)),
                              ],
                            ).expand(flex: 2).visible(appointmentDetailCont.appointmentDetail.value.serviceName.isNotEmpty),
                          ],
                        ),
                        commonDivider.paddingSymmetric(vertical: 16),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text("${locale.value.doctor}:", style: secondaryTextStyle(size: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                                6.height,
                                Text(appointmentDetailCont.appointmentDetail.value.doctorName, style: boldTextStyle(size: 12)),
                              ],
                            ).expand(flex: 3),
                            16.width,
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text("${locale.value.clinicName}:", style: secondaryTextStyle(size: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                                6.height,
                                Text(appointmentDetailCont.appointmentDetail.value.clinicName, style: boldTextStyle(size: 12)),
                              ],
                            ).expand(flex: 2),
                          ],
                        ),
                        if (appointmentDetailCont.appointmentDetail.value.bookForName.isNotEmpty) ...[
                          commonDivider.paddingSymmetric(vertical: 16),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                locale.value.bookedForWithColon,
                                style: secondaryTextStyle(),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ).expand(flex: 3),
                              16.width,
                              TextIcon(
                                edgeInsets: EdgeInsets.zero,
                                prefix: CachedImageWidget(
                                  url: appointmentDetailCont.appointmentDetail.value.booForImage,
                                  height: 22,
                                  width: 22,
                                  fit: BoxFit.cover,
                                  circle: true,
                                ).onTap(() {
                                  if (appointmentDetailCont.appointmentDetail.value.booForImage.isNotEmpty) {
                                    ZoomImageScreen(
                                      galleryImages: [appointmentDetailCont.appointmentDetail.value.booForImage],
                                      index: 0,
                                    ).launch(context);
                                  }
                                }),
                                text: appointmentDetailCont.appointmentDetail.value.bookForName,
                                expandedText: true,
                                useMarquee: true,
                                textStyle: boldTextStyle(size: 12),
                              ).expand(flex: 2),
                            ],
                          ),
                        ],
                        commonDivider.paddingSymmetric(vertical: 16),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text("${locale.value.appointmentStatus}:", style: secondaryTextStyle(size: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                                6.height,
                                Text(
                                  getBookingStatus(status: appointmentDetailCont.appointmentDetail.value.status),
                                  style: boldTextStyle(
                                    size: 12,
                                    color: getBookingStatusColor(status: appointmentDetailCont.appointmentDetail.value.status),
                                  ),
                                ),
                              ],
                            ).expand(flex: 3),
                            16.width,
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text("${locale.value.paymentStatus}:", style: secondaryTextStyle(size: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                                6.height,
                                Text(
                                  getBookingPaymentStatus(status: appointmentDetailCont.appointmentDetail.value.paymentStatus),
                                  style: boldTextStyle(
                                    size: 12,
                                    color: getPriceStatusColor(paymentStatus: appointmentDetailCont.appointmentDetail.value.paymentStatus),
                                  ),
                                ),
                              ],
                            ).expand(flex: 2),
                          ],
                        ),
                      ],
                    ),
                  ).paddingSymmetric(horizontal: 16),
                  if (appointmentDetailCont.appointmentDetail.value.medicalReport.isNotEmpty) ...[
                    8.height,
                    medicalReportWidget(),
                  ],
                  if (appointmentDetailCont.appointmentDetail.value.appointmentExtraInfo.isNotEmpty)
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        ViewAllLabel(label: locale.value.medicalHistory, isShowAll: false),
                        Container(
                          width: Get.width,
                          padding: const EdgeInsets.all(16),
                          decoration: boxDecorationDefault(color: context.cardColor),
                          child: ReadMoreText(
                            appointmentDetailCont.appointmentDetail.value.appointmentExtraInfo,
                            trimMode: TrimMode.Line,
                          ),
                        ),
                      ],
                    ).paddingSymmetric(horizontal: 16),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      16.height,
                      Text(locale.value.aboutService, style: boldTextStyle(size: Constants.labelTextSize)),
                      8.height,
                      ServiceInfoCardWidget(appointmentDet: appointmentDetailCont.appointmentDetail.value),
                    ],
                  ).paddingSymmetric(horizontal: 16),
                  if (!appointmentDetailCont.appointmentDetail.value.encounterId.isNegative && appointmentDetailCont.appointmentDetail.value.status.contains(BookingStatusConst.CHECKOUT))
                    Column(
                      children: [
                        16.height,
                        ViewAllLabel(
                          label: locale.value.encounterDetail,
                          trailingText: locale.value.view,
                          onTap: () {
                            Get.to(() => EncounterDetailScreen(), arguments: appointmentDetailCont.appointmentDetail.value.encounterId);
                          },
                        ).paddingOnly(left: 16, right: 8),
                        Container(
                          width: Get.width,
                          padding: const EdgeInsets.all(16),
                          decoration: boxDecorationDefault(color: context.cardColor),
                          child: Column(
                            children: [
                              detailWidget(
                                crossAxisAlignment: CrossAxisAlignment.center,
                                leadingWidget: Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text('${locale.value.doctorName}: ', style: secondaryTextStyle()),
                                    Marquee(
                                      child: Text(
                                        appointmentDetailCont.appointmentDetail.value.doctorName,
                                        style: boldTextStyle(size: 12),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ).expand(),
                                  ],
                                ).expand(flex: 3),
                                trailingWidget: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                                  decoration: boxDecorationDefault(
                                    color: appointmentDetailCont.appointmentDetail.value.encounterStatus
                                        ? isDarkMode.value
                                            ? lightGreenColor.withValues(alpha: 0.1)
                                            : lightGreenColor
                                        : isDarkMode.value
                                            ? lightSecondaryColor.withValues(alpha: 0.1)
                                            : lightSecondaryColor,
                                    borderRadius: radius(22),
                                  ),
                                  child: Text(
                                    appointmentDetailCont.appointmentDetail.value.encounterStatus ? locale.value.active : locale.value.closed,
                                    style: boldTextStyle(
                                      size: 12,
                                      color: appointmentDetailCont.appointmentDetail.value.encounterStatus ? completedStatusColor : pendingStatusColor,
                                    ),
                                  ),
                                ).paddingLeft(16),
                              ),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text('${locale.value.clinicName}: ', style: secondaryTextStyle()),
                                  Text(
                                    appointmentDetailCont.appointmentDetail.value.clinicName,
                                    style: boldTextStyle(size: 12),
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                  ).expand(),
                                ],
                              ),
                              if (appointmentDetailCont.appointmentDetail.value.encounterDescription.isNotEmpty) ...[
                                commonDivider.paddingSymmetric(vertical: 16),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text('${locale.value.description}: ', style: secondaryTextStyle()),
                                    Text(
                                      appointmentDetailCont.appointmentDetail.value.encounterDescription,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ).expand(),
                                  ],
                                ),
                              ],
                            ],
                          ),
                        ).paddingSymmetric(horizontal: 16),
                      ],
                    ),
                  if (appointmentDetailCont.appointmentDetail.value.bedDetails.isNotEmpty) ...[
                    16.height,
                    Text(
                      locale.value.bedDetails,
                      style: boldTextStyle(size: Constants.labelTextSize),
                    ).paddingSymmetric(horizontal: 16),
                    8.height,
                    ListView.builder(
                      itemCount: appointmentDetailCont.appointmentDetail.value.bedDetails.length,
                      shrinkWrap: true,
                      padding: EdgeInsets.zero,
                      physics: NeverScrollableScrollPhysics(),
                      itemBuilder: (context, index) {
                        final bed = appointmentDetailCont.appointmentDetail.value.bedDetails[index];
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 16),
                          child: BedComponent(
                            bedData: bed,
                            isEncounterOpen: appointmentDetailCont.appointmentDetail.value.encounterStatus,
                            patientId: appointmentDetailCont.appointmentDetail.value.userId,
                            patientName: appointmentDetailCont.appointmentDetail.value.userName,
                            encounterId: appointmentDetailCont.appointmentDetail.value.encounterId,
                          ),
                        );
                      },
                    ).paddingSymmetric(horizontal: 16),
                  ],
                  Obx(
                    // () => reviewPart(context).paddingBottom(16).visible(!appointmentDetailCont.isLoading.value && appointmentDetailCont.appointmentDetail.value.status.toLowerCase().contains(StatusConst.checkOut.toLowerCase())),
                    () => reviewPart(context).visible(
                      !appointmentDetailCont.isLoading.value &&
                          appointmentDetailCont.appointmentDetail.value.status.toLowerCase().contains(
                                StatusConst.checkOut.toLowerCase(),
                              ),
                    ),
                  ),
                  paymentDetails(context),
                  Obx(
                    () => SizedBox(
                      //// ADD THIS CONTAINER WITH WIDTH
                      width: Get.width,
                      child: payNowBtn(context).visible(
                        (appointmentDetailCont.appointmentDetail.value.paymentStatus.toLowerCase().contains(PaymentStatus.pending) ||
                                    appointmentDetailCont.appointmentDetail.value.paymentStatus.toLowerCase().contains(PaymentStatus.failed) ||
                                    appointmentDetailCont.appointmentDetail.value.paymentStatus.toLowerCase().contains(PaymentStatus.ADVANCE_PAID)) &&
                                appointmentDetailCont.appointmentDetail.value.status.toLowerCase().contains(StatusConst.checkIn.toLowerCase()) ||
                            appointmentDetailCont.isAdvancePaymentFailed,
                      ),
                    ),
                  ),
                  if (appointmentDetailCont.appointmentDetail.value.status.contains(StatusConst.pending)) ...[
                    24.height,
                    Obx(
                      () {
                        return AppButton(
                          color: appColorPrimary,
                          height: 48,
                          width: Get.width,
                          padding: EdgeInsets.zero,
                          shapeBorder: RoundedRectangleBorder(borderRadius: radius(defaultAppButtonRadius / 2)),
                          onTap: () {
                            Get.bottomSheet(
                              isScrollControlled: true,
                              Padding(
                                padding: EdgeInsets.only(
                                  bottom: MediaQuery.of(context).viewInsets.bottom,
                                ),
                                child: CancellationsBookingChargeDialog(
                                  appointmentData: appointmentDetailCont.appointmentDetail.value,
                                  isDurationMode: checkTimeDifference(inputDateTime: DateTime.parse(appointmentDetailCont.appointmentDetail.value.appointmentDate.validate())),
                                  loaderOnOFF: (p0) {
                                    appointmentDetailCont.isLoading(p0);
                                  },
                                  onCancelBooking: () {
                                    appointmentDetailCont.init();
                                    try {
                                      final AppointmentsController appointmentsController = Get.find();
                                      appointmentsController.getAppointmentList();
                                    } catch (e) {
                                      log('onItemSelected Err: $e');
                                    }
                                  },
                                ),
                              ),
                            );
                          },
                          text: locale.value.cancel,
                          textStyle: appButtonTextStyleWhite,
                        ).paddingSymmetric(horizontal: 16);
                      },
                    ),
                  ],
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  Widget payNowBtn(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          locale.value.noteInCaseYouFailToMakeTheAdvancePaymentYouWi,
          style: secondaryTextStyle(color: appColorSecondary, size: 11, fontStyle: FontStyle.italic),
        ).paddingSymmetric(horizontal: 16).paddingTop(16).visible(appointmentDetailCont.isAdvancePaymentFailed),
        32.height,
        AppButton(
          width: Get.width,
          text: locale.value.payNow,
          textStyle: appButtonTextStyleWhite,
          color: completedStatusColor,
          onTap: () async {
            paymentController = PaymentController(
              isFromBookingDetail: true,
              bid: appointmentDetailCont.appointmentDetail.value.id,
              amount: appointmentDetailCont.payNowAmount,
            );
            paymentController.isAdvancePaymentFailed = appointmentDetailCont.isAdvancePaymentFailed;
            paymentController.isRemainingPayment = appointmentDetailCont.appointmentDetail.value.paymentStatus.toLowerCase().contains(PaymentStatus.ADVANCE_PAID.toLowerCase());
            paymentController.paymentOption(PaymentMethods.PAYMENT_METHOD_STRIPE);
            AuthServiceApis.getUserWallet();
            Get.to(() => const PaymentScreen())?.then((value) {
              if (value == true) {
                appointmentDetailCont.init();

                ///Refresh Appointment List
                try {
                  final AppointmentsController appointmentsCont = Get.find();
                  appointmentsCont.page(1);
                  appointmentsCont.getAppointmentList();
                } catch (e) {
                  log('onItemSelected Err: $e');
                }
              }
            });
          },
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                locale.value.payNow,
                style: appButtonTextStyleWhite,
              ),
              PriceWidget(
                price: appointmentDetailCont.payNowAmount,
                color: white,
                size: 18,
              ),
            ],
          ),
        ).paddingSymmetric(horizontal: 16),
      ],
    );
  }

  Widget medicalReportWidget() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ViewAllLabel(label: locale.value.medicalReport, isShowAll: false),
        AnimatedWrap(
          listAnimationType: ListAnimationType.None,
          spacing: 16,
          runSpacing: 16,
          itemCount: appointmentDetailCont.appointmentDetail.value.medicalReport.length,
          itemBuilder: (ctx, index) {
            final MedicalReport medicalReportData = appointmentDetailCont.appointmentDetail.value.medicalReport[index];
            return Stack(
              clipBehavior: Clip.none,
              alignment: Alignment.center,
              children: [
                const SizedBox(width: 80, height: 80, child: Loader()),
                GestureDetector(
                  onTap: () {
                    viewFiles(medicalReportData.url);
                  },
                  behavior: HitTestBehavior.translucent,
                  child: medicalReportData.url.isImage
                      ? Container(
                          decoration: boxDecorationWithRoundedCorners(backgroundColor: transparentColor),
                          child: CachedImageWidget(
                            url: medicalReportData.url,
                            height: 80,
                            width: 80,
                            fit: BoxFit.cover,
                            radius: defaultRadius,
                          ),
                        )
                      : CommonPdfPlaceHolder(
                          text: medicalReportData.url.split("/").last,
                          height: 80,
                        ),
                ),
              ],
            );
          },
        ),
      ],
    ).paddingSymmetric(horizontal: 16);
  }

  Widget paymentDetails(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ViewAllLabel(label: locale.value.paymentDetail, isShowAll: false).paddingOnly(left: 16, right: 8),
        Container(
          width: Get.width,
          padding: const EdgeInsets.all(16),
          decoration: boxDecorationDefault(color: context.cardColor),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(locale.value.serviceTotal, style: secondaryTextStyle()).expand(),
                  PriceWidget(
                    price: num.parse(appointmentDetailCont.appointmentDetail.value.serviceTotal.toString()).toStringAsFixed(Constants.DECIMAL_POINT).toDouble(),
                    color: isDarkMode.value ? null : darkGrayTextColor,
                    size: 12,
                  ),
                ],
              ),

              10.height,

              /// Subtotal
              detailWidgetPrice(
                title: locale.value.subtotal,
                value: appointmentDetailCont.appointmentDetail.value.subtotal,
              ),
              10.height,

              /// Tax
              if (appointmentDetailCont.appointmentDetail.value.isExclusiveTaxesAvailable)
                detailWidgetPrice(
                  leadingWidget: Row(
                    children: [
                      Text(locale.value.exclusiveTax, style: secondaryTextStyle()).expand(),
                      const Icon(Icons.info_outline_rounded, size: 20, color: appColorPrimary).onTap(
                        () {
                          showModalBottomSheet(
                            context: context,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.only(
                                topLeft: radiusCircular(16),
                                topRight: radiusCircular(16),
                              ),
                            ),
                            builder: (_) {
                              return AppoitmentDetailAppliedTaxListBottomSheet(
                                taxes: appointmentDetailCont.appointmentDetail.value.exclusiveTaxList,
                                title: locale.value.appliedExclusiveTaxes,
                              );
                            },
                          );
                        },
                      ),
                      8.width,
                    ],
                  ).expand(),
                  value: appointmentDetailCont.appointmentDetail.value.totalExclusiveTax,
                  isSemiBoldText: true,
                  textColor: appColorSecondary,
                ),

              /// Bed Price
              if (appointmentDetailCont.appointmentDetail.value.bedDetails.isNotEmpty) ...[
                10.height.visible(appointmentDetailCont.appointmentDetail.value.bedDetails.fold(0, (sum, item) => sum + item.charge.validate()) > 0),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(locale.value.bedPrice, style: secondaryTextStyle()),
                    PriceWidget(
                      price: appointmentDetailCont.appointmentDetail.value.bedDetails.fold(0, (sum, item) => sum + item.charge.validate()),
                      color: isDarkMode.value ? null : darkGrayTextColor,
                      size: 12,
                    ),
                  ],
                ).visible(appointmentDetailCont.appointmentDetail.value.bedDetails.fold(0, (sum, item) => sum + item.charge.validate()) > 0),
              ],

              commonDivider.paddingSymmetric(vertical: 8),
              if (appointmentDetailCont.appointmentDetail.value.enableFinalBillingDiscount) ...[
                detailWidgetPrice(
                  leadingWidget: Row(
                    children: [
                      Text(locale.value.discount, style: secondaryTextStyle()),
                      if (appointmentDetailCont.appointmentDetail.value.billingFinalDiscountType == TaxType.PERCENTAGE)
                        Text(
                          ' (${appointmentDetailCont.appointmentDetail.value.billingFinalDiscountValue}% ${locale.value.off})',
                          style: boldTextStyle(color: Colors.green, size: 12),
                        )
                      else if (appointmentDetailCont.appointmentDetail.value.billingFinalDiscountType == TaxType.FIXED)
                        PriceWidget(
                          price: appointmentDetailCont.appointmentDetail.value.billingFinalDiscountValue,
                          color: Colors.green,
                          size: 12,
                          isDiscountedPrice: true,
                        )
                    ],
                  ),
                  value: appointmentDetailCont.appointmentDetail.value.billingFinalDiscountAmount,
                  textColor: Colors.green,
                ),
              ],

              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(locale.value.total, style: boldTextStyle(size: 14)),
                  PriceWidget(
                    price: (appointmentDetailCont.appointmentDetail.value.enableFinalBillingDiscount ? appointmentDetailCont.appointmentDetail.value.finalTotalAmount : appointmentDetailCont.appointmentDetail.value.totalAmount) +
                        appointmentDetailCont.appointmentDetail.value.bedDetails.fold(0, (sum, item) => sum + item.charge.validate()),
                    color: appColorPrimary,
                  ),
                ],
              ),

              if (appointmentDetailCont.appointmentDetail.value.paymentStatus == PaymentStatus.PAID &&
                  appointmentDetailCont.appointmentDetail.value.isEnableAdvancePayment &&
                  !appointmentDetailCont.isAdvancePaymentFailed &&
                  !appointmentDetailCont.appointmentDetail.value.status.toLowerCase().contains(StatusConst.cancel.toLowerCase()) &&
                  appointmentDetailCont.appointmentDetail.value.remainingPayableAmount > 0)
                detailWidgetPrice(
                  leadingWidget: Text(locale.value.remainingAmount, style: boldTextStyle(size: 14)),
                  value: appointmentDetailCont.appointmentDetail.value.remainingPayableAmount,
                ),

              if (appointmentDetailCont.appointmentDetail.value.paymentStatus != PaymentStatus.PAID &&
                  appointmentDetailCont.appointmentDetail.value.isEnableAdvancePayment &&
                  !appointmentDetailCont.isAdvancePaymentFailed &&
                  appointmentDetailCont.appointmentDetail.value.advancePaidAmount > 0 && appointmentDetailCont.appointmentDetail.value.status.toLowerCase() != BookingStatusConst.CANCELLED) ...[
                ///Advance Paid Amount
                10.height,
                detailWidgetPrice(
                  leadingWidget: Row(
                    children: [
                      Text(locale.value.advancePaidAmount, overflow: TextOverflow.ellipsis, maxLines: 2, style: secondaryTextStyle()),
                      Text(
                        ' (${appointmentDetailCont.appointmentDetail.value.advancePaymentAmount}%)',
                        style: boldTextStyle(color: completedStatusColor, size: 12),
                      ),
                    ],
                  ).flexible(),
                  textColor: completedStatusColor,
                  value: appointmentDetailCont.appointmentDetail.value.advancePaidAmount,
                ),
              ],
              ///show advance payment refundable amount if appointment cancelled
              if (appointmentDetailCont.appointmentDetail.value.status.toLowerCase() == BookingStatusConst.CANCELLED &&
                  appointmentDetailCont.appointmentDetail.value.paymentStatus != PaymentStatus.PAID &&
                  appointmentDetailCont.appointmentDetail.value.isEnableAdvancePayment &&
                  !appointmentDetailCont.isAdvancePaymentFailed &&
                  appointmentDetailCont.appointmentDetail.value.advancePaidAmount > 0) ...[
                10.height,
                detailWidgetPrice(
                  leadingWidget: Text("Refundable Amount (Advance Paid)", overflow: TextOverflow.ellipsis, maxLines: 2, style: primaryTextStyle(color: Colors.red)),
                  textColor: Colors.red,
                  value: appointmentDetailCont.appointmentDetail.value.refundAmount,
                  paddingBottom: 0,
                ),
              ],

              if (appointmentDetailCont.appointmentDetail.value.cancellationCharges > 0 &&
                  appointmentDetailCont.appointmentDetail.value.cancellationChargeAmount > 0 &&
                  appointmentDetailCont.appointmentDetail.value.status.toLowerCase().contains(StatusConst.cancel.toLowerCase()))
                detailWidgetPrice(
                  leadingWidget: Row(
                    children: [
                      Text(locale.value.cancellationFee, style: secondaryTextStyle()),
                      if (appointmentDetailCont.appointmentDetail.value.cancellationType == TaxType.PERCENTAGE)
                        Text(
                          ' (${appointmentDetailCont.appointmentDetail.value.cancellationCharges}%)',
                          style: boldTextStyle(color: Colors.green, size: 12),
                        )
                      else if (appointmentDetailCont.appointmentDetail.value.cancellationType == TaxType.FIXED)
                        PriceWidget(
                          price: appointmentDetailCont.appointmentDetail.value.cancellationCharges,
                          color: appColorSecondary,
                          size: 12,
                          isDiscountedPrice: true,
                        ),
                    ],
                  ),
                  value: appointmentDetailCont.appointmentDetail.value.cancellationChargeAmount,
                  textColor: Colors.green,
                ),

              ///Remaining Payable Amount
              if (appointmentDetailCont.appointmentDetail.value.paymentStatus != PaymentStatus.PAID &&
                  appointmentDetailCont.appointmentDetail.value.isEnableAdvancePayment &&
                  !appointmentDetailCont.isAdvancePaymentFailed &&
                  appointmentDetailCont.appointmentDetail.value.advancePaidAmount > 0)
                if (!appointmentDetailCont.appointmentDetail.value.status.toLowerCase().contains(StatusConst.cancel.toLowerCase()) && appointmentDetailCont.appointmentDetail.value.remainingPayableAmount > 0) ...[
                  10.height,
                  detailWidgetPrice(
                    leadingWidget: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          locale.value.remainingPayableAmount,
                          style: secondaryTextStyle(),
                        ),
                        8.width,
                        Text(
                          '(${locale.value.paid})',
                          style: secondaryTextStyle(color: completedStatusColor),
                        ).visible(appointmentDetailCont.appointmentDetail.value.status.contains(StatusConst.checkOut) && appointmentDetailCont.appointmentDetail.value.paymentStatus.contains(PaymentStatus.PAID)),
                      ],
                    ),
                    isSemiBoldText: true,
                    textColor: pendingStatusColor,
                    value: appointmentDetailCont.appointmentDetail.value.remainingPayableAmount,
                  ),
                ],
            ],
          ),
        ).paddingSymmetric(horizontal: 16),
        if (appointmentDetailCont.appointmentDetail.value.refundAmount > 0 &&
            appointmentDetailCont.appointmentDetail.value.status.toLowerCase() == BookingStatusConst.CANCELLED &&
            appointmentDetailCont.appointmentDetail.value.status.toLowerCase() != PaymentStatus.pending)
          Container(
            width: Get.width,
            padding: const EdgeInsets.all(16),
            decoration: boxDecorationDefault(color: completedStatusColor),
            child: detailWidgetPrice(
              leadingWidget: Text(locale.value.advanceRefunded, overflow: TextOverflow.ellipsis, maxLines: 2, style: primaryTextStyle(color: Colors.white)),
              textColor: Colors.white,
              value: appointmentDetailCont.appointmentDetail.value.refundAmount,
            ),
          ).paddingOnly(left: 16, right: 16, top: 16),
      ],
    );
  }

  Widget reviewPart(BuildContext context) {
    return Obx(
      () => appointmentDetailCont.hasReview.value
          ? appointmentDetailCont.showWriteReview.value
              ? addEditReview(context)
              : yourReview(context)
          : addEditReview(context),
    );
  }

  Widget yourReview(BuildContext context) {
    return Obx(
      () => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              ViewAllLabel(label: locale.value.yourReview, isShowAll: false).flexible(),
              const Spacer(),
              GestureDetector(
                onTap: appointmentDetailCont.handleEditReview,
                child: commonLeadingWid(
                  imgPath: Assets.iconsIcEditReview,
                  color: textSecondaryColorGlobal,
                  icon: Icons.edit_outlined,
                  size: 16,
                ),
              ),
              16.width,
              GestureDetector(
                onTap: () {
                  appointmentDetailCont.deleteReviewHandleClick();
                },
                child: commonLeadingWid(
                  imgPath: Assets.iconsIcDelete,
                  color: appColorSecondary,
                  icon: Icons.delete_outline,
                  size: 16,
                ),
              ),
            ],
          ).paddingSymmetric(horizontal: 16),
          Container(
            padding: const EdgeInsets.all(16),
            width: Get.width,
            decoration: boxDecorationWithRoundedCorners(backgroundColor: context.cardColor),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          decoration: boxDecorationDefault(color: extraLightPrimaryColor, borderRadius: radius(22)),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              CachedImageWidget(
                                url: Assets.iconsIcStarFilled,
                                color: getRatingBarColor(appointmentDetailCont.yourReview.value.rating),
                                height: 12,
                              ),
                              5.width,
                              Text(
                                appointmentDetailCont.yourReview.value.rating.toStringAsFixed(0),
                                style: boldTextStyle(size: 12, color: appColorPrimary),
                              ).paddingTop(2),
                            ],
                          ),
                        ),
                        Row(
                          children: [
                            Text(
                              '${locale.value.by} ${loginUserData.value.userName}',
                              style: primaryTextStyle(),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ).flexible(),
                            4.width,
                            commonLeadingWid(imgPath: Assets.iconsIcVerified, size: 12, color: Colors.green),
                          ],
                        ).paddingLeft(8).expand(),
                        10.width,
                        Text(
                          appointmentDetailCont.yourReview.value.createdAt.dateInyyyyMMddHHmmFormat.timeAgoWithLocalization,
                          style: secondaryTextStyle(),
                        ),
                      ],
                    ).expand(),
                  ],
                ),
                16.height,
                Text(appointmentDetailCont.yourReview.value.reviewMsg, style: secondaryTextStyle()),
              ],
            ),
          ).paddingSymmetric(horizontal: 16),
        ],
      ),
    );
  }

  Widget addEditReview(BuildContext context) {
    return Obx(
      () => Container(
        margin: EdgeInsets.all(16),
        padding: EdgeInsets.symmetric(vertical: 16),
        decoration: boxDecorationDefault(
          borderRadius: radius(defaultRadius),
          color: context.cardColor,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ViewAllLabel(label: locale.value.youHaventRatedYet, isShowAll: false).paddingOnly(right: 8, left: 16),
            Row(
              children: [
                // ViewAllLabel(label: locale.value.youHaventRatedYet, isShowAll: false).paddingOnly(right: 8),
                const Spacer(),
                GestureDetector(
                  onTap: appointmentDetailCont.showReview,
                  child: commonLeadingWid(imgPath: '', icon: Icons.close_outlined),
                ).visible(appointmentDetailCont.showWriteReview.value),
              ],
            ).paddingSymmetric(horizontal: 16),
            Text(locale.value.yourFeedbackWillImproveOurService, style: secondaryTextStyle()).paddingSymmetric(horizontal: 16),
            16.height,
            Row(
              children: [
                RatingBarWidget(
                  size: 24,
                  activeColor: getRatingBarColor(appointmentDetailCont.selectedRating.value),
                  inActiveColor: ratingColor,
                  rating: appointmentDetailCont.selectedRating.value,
                  onRatingChanged: (rating) {
                    appointmentDetailCont.selectedRating(rating);
                  },
                ).expand(),
              ],
            ).paddingSymmetric(horizontal: 16),
            16.height,
            AppTextField(
              controller: appointmentDetailCont.reviewCont,
              textStyle: primaryTextStyle(size: 12),
              textFieldType: TextFieldType.MULTILINE,
              minLines: 5,
              enableChatGPT: appConfigs.value.enableChatGpt,
              promptFieldInputDecorationChatGPT: inputDecoration(context, hintText: locale.value.writeHere, fillColor: context.scaffoldBackgroundColor, filled: true),
              testWithoutKeyChatGPT: appConfigs.value.testWithoutKey,
              loaderWidgetForChatGPT: const ChatGPTLoadingWidget(),
              decoration: inputDecoration(
                context,
                fillColor: context.cardColor,
                filled: true,
                labelText: locale.value.writeYourFeedbackHere,
              ),
            ).paddingSymmetric(horizontal: 16),
            16.height,
            AppButton(
              width: Get.width,
              text: locale.value.submit,
              color: appColorPrimary,
              textStyle: const TextStyle(color: whiteTextColor),
              onTap: () {
                if (appointmentDetailCont.selectedRating.value > 0) {
                  appointmentDetailCont.saveReview();
                } else {
                  toast(locale.value.pleaseSelectRatings);
                }
              },
            ).paddingSymmetric(horizontal: 16),
          ],
        ),
      ),
    );
  }
}

class BedComponent extends StatelessWidget {
  final BedDetails bedData;
  final int? patientId;
  final String? patientName;
  final int? encounterId;
  final bool isEncounterOpen;

  const BedComponent({
    super.key,
    required this.bedData,
    this.patientId,
    this.patientName,
    this.encounterId,
    required this.isEncounterOpen,
  });
  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: Get.width,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          8.height,
          Container(
            decoration: boxDecorationDefault(color: context.cardColor),
            padding: EdgeInsets.all(16),
            child: Column(
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(locale.value.roomNumber, style: secondaryTextStyle()),
                          8.height,
                          Text(bedData.bedName, style: boldTextStyle(size: 12)),
                        ],
                      ),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(locale.value.bedType, style: secondaryTextStyle()),
                          8.height,
                          Text(bedData.bedType, style: boldTextStyle(size: 12)),
                        ],
                      ),
                    )
                  ],
                ),
                8.height,
                commonDivider,
                8.height,
                Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(locale.value.assignDate, style: secondaryTextStyle()),
                          8.height,
                          Text(
                            bedData.assignDate,
                            style: boldTextStyle(size: 12),
                          )
                        ],
                      ),
                    ),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(locale.value.dischargeDate, style: secondaryTextStyle()),
                          8.height,
                          Text(
                            bedData.dischargeDate,
                            style: boldTextStyle(size: 12),
                          )
                        ],
                      ),
                    )
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
