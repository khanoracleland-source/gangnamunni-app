import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/components/app_scaffold.dart';

import '../../components/cached_image_widget.dart';
import '../../components/common_file_placeholders.dart';
import '../../components/loader_widget.dart';
import '../../main.dart';
import '../../utils/app_common.dart';
import '../../utils/colors.dart';
import '../../utils/common_base.dart';
import '../../utils/empty_error_state_widget.dart';
import '../../utils/view_all_label_component.dart';
import 'components/pharma_prescription_component.dart';
import 'components/prescription_component.dart';
import 'encounter_detail_controller.dart';
import 'model/appointment_detail_res.dart';
import 'model/encounter_detail_model.dart';

class EncounterDetailScreen extends StatelessWidget {
  EncounterDetailScreen({super.key});

  final EncounterDetailController encounterDetailCont = Get.put(EncounterDetailController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      isLoading: encounterDetailCont.isLoading,
      appBartitleText: locale.value.encounter,
      appBarVerticalSize: Get.height * 0.12,
      body: RefreshIndicator(
        onRefresh: () {
          return encounterDetailCont.init(showLoader: false);
        },
        child: Obx(
          () => SnapHelperWidget(
            future: encounterDetailCont.getEncounterDetails.value,
            errorBuilder: (error) {
              return NoDataWidget(
                title: error,
                retryText: locale.value.reload,
                imageWidget: const ErrorStateWidget(),
                onRetry: () {
                  encounterDetailCont.init();
                },
              ).paddingSymmetric(horizontal: 16);
            },
            loadingWidget: const LoaderWidget(),
            onSuccess: (encounterDetailRes) {
              return AnimatedScrollView(
                listAnimationType: ListAnimationType.FadeIn,
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.only(bottom: 30),
                children: [
                  14.height,
                  ViewAllLabel(label: locale.value.basicInformation, isShowAll: false).paddingOnly(left: 16, right: 8),
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
                                  "${encounterDetailCont.encounterDetail.value.doctor!.firstName} ${encounterDetailCont.encounterDetail.value.doctor!.lastName}",
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
                              color: encounterDetailCont.encounterDetail.value.status
                                  ? isDarkMode.value
                                      ? lightGreenColor.withValues(alpha: 0.1)
                                      : lightGreenColor
                                  : isDarkMode.value
                                      ? lightSecondaryColor.withValues(alpha: 0.1)
                                      : lightSecondaryColor,
                              borderRadius: radius(22),
                            ),
                            child: Text(
                              encounterDetailCont.encounterDetail.value.status ? locale.value.active : locale.value.closed,
                              style: boldTextStyle(
                                size: 12,
                                color: encounterDetailCont.encounterDetail.value.status ? completedStatusColor : pendingStatusColor,
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
                              encounterDetailCont.encounterDetail.value.clinic!.name,
                              style: boldTextStyle(size: 12),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ).expand(),
                          ],
                        ),
                        if (encounterDetailCont.encounterDetail.value.description.isNotEmpty) ...[
                          commonDivider.paddingSymmetric(vertical: 16),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text('${locale.value.description}: ', style: secondaryTextStyle()),
                              Text(
                                encounterDetailCont.encounterDetail.value.description.isNotEmpty ? encounterDetailCont.encounterDetail.value.description : 'No Records Found',
                                style: boldTextStyle(size: 12),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ).expand(),
                            ],
                          ),
                        ],
                      ],
                    ),
                  ).paddingSymmetric(horizontal: 16),

                  /// Problems
                  Column(
                    children: [
                      16.height,
                      ViewAllLabel(label: locale.value.problems, isShowAll: false).paddingOnly(left: 16, right: 8),
                      Container(
                        width: Get.width,
                        padding: const EdgeInsets.only(left: 16, right: 16, top: 16, bottom: 14),
                        decoration: boxDecorationDefault(color: context.cardColor),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: List.generate(encounterDetailCont.encounterDetail.value.problems.length, (index) {
                            final String problemsData = encounterDetailCont.encounterDetail.value.problems[index].title;
                            return Text('${index + 1}. ${problemsData.capitalizeFirstLetter()}', style: secondaryTextStyle()).paddingBottom(2);
                          }),
                        ),
                      ).paddingSymmetric(horizontal: 16),
                    ],
                  ).visible(encounterDetailCont.encounterDetail.value.problems.isNotEmpty),

                  /// Observation
                  Column(
                    children: [
                      16.height,
                      ViewAllLabel(label: locale.value.observations, isShowAll: false).paddingOnly(left: 16, right: 8),
                      Container(
                        width: Get.width,
                        padding: const EdgeInsets.all(16),
                        decoration: boxDecorationDefault(color: context.cardColor),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: List.generate(encounterDetailCont.encounterDetail.value.observations.length, (index) {
                            final String observationData = encounterDetailCont.encounterDetail.value.observations[index].title;
                            return Text('${index + 1}. ${observationData.capitalizeFirstLetter()}', style: secondaryTextStyle()).paddingBottom(2);
                          }),
                        ),
                      ).paddingSymmetric(horizontal: 16),
                    ],
                  ).visible(encounterDetailCont.encounterDetail.value.observations.isNotEmpty),

                  /// Notes
                  Column(
                    children: [
                      16.height,
                      ViewAllLabel(label: locale.value.notes, isShowAll: false).paddingOnly(left: 16, right: 8),
                      Container(
                        width: Get.width,
                        padding: const EdgeInsets.all(16),
                        decoration: boxDecorationDefault(color: context.cardColor),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: List.generate(encounterDetailCont.encounterDetail.value.notes.length, (index) {
                            final String notesData = encounterDetailCont.encounterDetail.value.notes[index].title;
                            return Text('${index + 1}. ${notesData.capitalizeFirstLetter()}', style: secondaryTextStyle()).paddingBottom(2);
                          }),
                        ),
                      ).paddingSymmetric(horizontal: 16),
                    ],
                  ).visible(encounterDetailCont.encounterDetail.value.notes.isNotEmpty),

                  /// Medical Report
                  Column(
                    children: [
                      16.height,
                      medicalReportWidget().paddingSymmetric(horizontal: 16),
                    ],
                  ).visible(encounterDetailCont.encounterDetail.value.medicalReport.isNotEmpty),

                  /// Prescription
                  if (appConfigs.value.isPharma)
                    PharmaPrescriptionComponent(encounterDetailCont: encounterDetailCont).visible(encounterDetailCont.encounterDetail.value.prescriptions.isNotEmpty)
                  else
                    PrescriptionComponent(encounterDetailCont: encounterDetailCont).visible(encounterDetailCont.encounterDetail.value.prescriptions.isNotEmpty),

                  /// Other Details
                  Column(
                    children: [
                      16.height,
                      ViewAllLabel(label: locale.value.otherInformation, isShowAll: false).paddingOnly(left: 16, right: 8),
                      Container(
                        width: Get.width,
                        padding: const EdgeInsets.all(16),
                        decoration: boxDecorationDefault(color: context.cardColor),
                        child: Text(encounterDetailCont.encounterDetail.value.otherDetails, style: secondaryTextStyle()),
                      ).paddingSymmetric(horizontal: 16),
                    ],
                  ).visible(encounterDetailCont.encounterDetail.value.otherDetails.isNotEmpty),

                  /// Body Charts
                  Column(
                    children: [
                      16.height,
                      bodyChartWidget().paddingSymmetric(horizontal: 16),
                    ],
                  ).visible(encounterDetailCont.encounterDetail.value.bodyCharts.isNotEmpty),

                  /// SOAP
                  Column(
                    children: [
                      16.height,
                      ViewAllLabel(label: locale.value.patientSoap, isShowAll: false).paddingOnly(left: 16, right: 8),
                      10.height,
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(locale.value.subjective, style: primaryTextStyle()),
                              8.height,
                              Container(
                                width: Get.width,
                                padding: const EdgeInsets.all(16),
                                decoration: boxDecorationDefault(color: context.cardColor),
                                child: Text(encounterDetailCont.encounterDetail.value.soap.subjective, style: secondaryTextStyle()),
                              ),
                            ],
                          ).paddingBottom(16).visible(encounterDetailCont.encounterDetail.value.soap.subjective.isNotEmpty),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(locale.value.objective, style: primaryTextStyle()),
                              8.height,
                              Container(
                                width: Get.width,
                                padding: const EdgeInsets.all(16),
                                decoration: boxDecorationDefault(color: context.cardColor),
                                child: Text(encounterDetailCont.encounterDetail.value.soap.objective, style: secondaryTextStyle()),
                              ),
                            ],
                          ).paddingBottom(16).visible(encounterDetailCont.encounterDetail.value.soap.objective.isNotEmpty),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(locale.value.assessment, style: primaryTextStyle()),
                              8.height,
                              Container(
                                width: Get.width,
                                padding: const EdgeInsets.all(16),
                                decoration: boxDecorationDefault(color: context.cardColor),
                                child: Text(encounterDetailCont.encounterDetail.value.soap.assessment, style: secondaryTextStyle()),
                              ),
                            ],
                          ).paddingBottom(16).visible(encounterDetailCont.encounterDetail.value.soap.assessment.isNotEmpty),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(locale.value.plan, style: primaryTextStyle()),
                              8.height,
                              Container(
                                width: Get.width,
                                padding: const EdgeInsets.all(16),
                                decoration: boxDecorationDefault(color: context.cardColor),
                                child: Text(encounterDetailCont.encounterDetail.value.soap.plan, style: secondaryTextStyle()),
                              ),
                            ],
                          ).visible(encounterDetailCont.encounterDetail.value.soap.plan.isNotEmpty),
                        ],
                      ).paddingSymmetric(horizontal: 16),
                    ],
                  ).visible(!encounterDetailCont.encounterDetail.value.soap.id.isNegative),
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  Widget medicalReportWidget() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ViewAllLabel(label: locale.value.medicalReport, isShowAll: false),
        8.height,
        AnimatedWrap(
          listAnimationType: ListAnimationType.None,
          spacing: 16,
          runSpacing: 16,
          itemCount: encounterDetailCont.encounterDetail.value.medicalReport.length,
          itemBuilder: (ctx, index) {
            final MedicalReport medicalReportData = encounterDetailCont.encounterDetail.value.medicalReport[index];
            return Stack(
              clipBehavior: Clip.none,
              alignment: Alignment.center,
              children: [
                const SizedBox(width: 80, height: 80, child: Loader()),
                GestureDetector(
                  onTap: () {
                    viewFiles(medicalReportData.fileUrl);
                  },
                  behavior: HitTestBehavior.translucent,
                  child: medicalReportData.fileUrl.isImage
                      ? Container(
                          decoration: boxDecorationWithRoundedCorners(backgroundColor: transparentColor),
                          child: CachedImageWidget(
                            url: medicalReportData.fileUrl,
                            height: 80,
                            width: 80,
                            fit: BoxFit.cover,
                            radius: defaultRadius,
                          ),
                        )
                      : CommonPdfPlaceHolder(
                          text: medicalReportData.name,
                          height: 80,
                          fileLink: medicalReportData.fileUrl,
                        ),
                ),
              ],
            );
          },
        ),
      ],
    );
  }

  Widget bodyChartWidget() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ViewAllLabel(label: locale.value.bodyChart, isShowAll: false),
        8.height,
        AnimatedWrap(
          listAnimationType: ListAnimationType.None,
          spacing: 16,
          runSpacing: 16,
          itemCount: encounterDetailCont.encounterDetail.value.bodyCharts.length,
          itemBuilder: (ctx, index) {
            final BodyCharts bodyChartsData = encounterDetailCont.encounterDetail.value.bodyCharts[index];
            return Stack(
              clipBehavior: Clip.none,
              alignment: Alignment.center,
              children: [
                const SizedBox(width: 80, height: 80, child: Loader()),
                GestureDetector(
                  onTap: () {
                    viewFiles(bodyChartsData.fileUrl);
                  },
                  behavior: HitTestBehavior.translucent,
                  child: bodyChartsData.fileUrl.isImage
                      ? Container(
                          decoration: boxDecorationWithRoundedCorners(backgroundColor: transparentColor),
                          child: CachedImageWidget(
                            url: bodyChartsData.fileUrl,
                            height: 80,
                            width: 80,
                            fit: BoxFit.cover,
                            radius: defaultRadius,
                          ),
                        )
                      : CommonPdfPlaceHolder(text: bodyChartsData.name, height: 80),
                ),
              ],
            );
          },
        ),
      ],
    );
  }
}
