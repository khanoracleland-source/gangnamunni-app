import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../../../components/cached_image_widget.dart';
import '../../../../../components/loader_widget.dart';
import '../../../../../generated/assets.dart';
import '../../../../../main.dart';
import '../../../../../utils/colors.dart';
import '../../../../../utils/common_base.dart';
import '../../../../../utils/empty_error_state_widget.dart';
import '../../../../doctor/model/doctor_list_res.dart';
import '../../filter_controller.dart';
import '../clinic_filter/filter_search_clinic_component.dart';

class FilterDoctor extends StatelessWidget {
  final FilterController filterCont;

  const FilterDoctor({super.key, required this.filterCont});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        FilterSearchClinicComponent(
          filterClinicController: filterCont,
          onFieldSubmitted: (p0) {
            hideKeyboard(context);
          },
        ).paddingSymmetric(horizontal: 16),
        12.height,
        Obx(
          () => SnapHelperWidget(
            future: filterCont.doctorListFuture.value,
            errorBuilder: (error) {
              return AnimatedScrollView(
                padding: const EdgeInsets.all(16),
                children: [
                  NoDataWidget(
                    title: error,
                    retryText: locale.value.reload,
                    imageWidget: const ErrorStateWidget(),
                    onRetry: () {
                      filterCont.doctorPage(1);
                      filterCont.getDoctors();
                    },
                  ),
                ],
              ).paddingSymmetric(horizontal: 32);
            },
            loadingWidget: const LoaderWidget(),
            onSuccess: (data) {
              if (filterCont.doctorList.isEmpty) {
                return AnimatedScrollView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    NoDataWidget(
                      title: locale.value.noDoctorsFoundAtAMoment,
                      subTitle: locale.value.looksLikeThereIsNoDoctorsForThisClinicWellKee,
                      retryText: locale.value.reload,
                      imageWidget: const EmptyStateWidget(),
                      onRetry: () async {
                        filterCont.doctorPage(1);
                        filterCont.getDoctors();
                      },
                    ),
                  ],
                ).paddingSymmetric(horizontal: 32).visible(!filterCont.isDoctorLoading.value);
              } else {
                return Obx(
                  () => Stack(
                    children: [
                      AnimatedScrollView(
                        children: [
                          AnimatedWrap(
                            children: List.generate(filterCont.doctorList.length, (index) {
                              final Doctor doctor = filterCont.doctorList[index];
                              return InkWell(
                                onTap: () {
                                  filterCont.selectedDoctorDataFunc(doctor);
                                },
                                child: Stack(
                                  children: [
                                    Container(
                                      margin: const EdgeInsets.all(6),
                                      decoration: boxDecorationDefault(
                                        color: context.cardColor,
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Row(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        children: [
                                          CachedImageWidget(
                                            url: doctor.profileImage,
                                            height: 75,
                                            width: 75,
                                            fit: BoxFit.cover,
                                            topLeftRadius: 6,
                                            bottomLeftRadius: 6,
                                          ),
                                          8.width,
                                          Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              16.height,
                                              Text(
                                                '${doctor.firstName} ${doctor.lastName}',
                                                maxLines: 1,
                                                overflow: TextOverflow.ellipsis,
                                                style: primaryTextStyle(
                                                  size: 12,
                                                ),
                                              ),
                                              8.height,
                                              Row(
                                                children: [
                                                  const CachedImageWidget(url: Assets.iconsIcLocation, color: iconColor, width: 12, height: 12),
                                                  8.width,
                                                  Text(
                                                    doctor.address,
                                                    style: secondaryTextStyle(),
                                                    maxLines: 1,
                                                    overflow: TextOverflow.ellipsis,
                                                  ).expand(),
                                                ],
                                              ),
                                              6.height,
                                            ],
                                          ).expand(),
                                          8.width,
                                        ],
                                      ),
                                    ),
                                    Positioned(
                                      top: 0,
                                      right: 0,
                                      child: commonLeadingWid(
                                        imgPath: Assets.imagesConfirm,
                                        color: whiteTextColor,
                                        size: 8,
                                      ).circularLightPrimaryBg(color: appColorPrimary, padding: 6),
                                    ).visible(filterCont.selectedDoctorDate.value.id == doctor.id),
                                  ],
                                ),
                              );
                            }),
                          ),
                        ],
                        onNextPage: () async {
                          if (!filterCont.isDoctorLoading.value) {
                            filterCont.doctorPage(filterCont.doctorPage.value + 1);
                            filterCont.getDoctors();
                          }
                        },
                        onSwipeRefresh: () async {
                          filterCont.doctorPage(1);
                          return filterCont.getDoctors();
                        },
                      ),
                      if (filterCont.isDoctorLoading.isTrue) const LoaderWidget(),
                    ],
                  ),
                );
              }
            },
          ),
        ).paddingOnly(bottom: 16, left: 16, right: 16).expand(),
      ],
    );
  }
}
