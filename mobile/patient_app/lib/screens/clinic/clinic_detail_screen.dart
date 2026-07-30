import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/utils/common_base.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../components/app_scaffold.dart';
import '../../components/cached_image_widget.dart';
import '../../components/loader_widget.dart';
import '../../generated/assets.dart';
import '../../main.dart';
import '../../utils/colors.dart';
import '../../utils/empty_error_state_widget.dart';
import 'clinic_detail_controller.dart';
import 'components/clinic_detail_btm_comp.dart';

class ClinicDetailScreen extends StatelessWidget {
  ClinicDetailScreen({super.key});

  final ClinicDetailController clinicDetailCont = Get.put(ClinicDetailController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      isLoading: clinicDetailCont.isLoading,
      appBartitleText: locale.value.clinicDetail,
      appBarVerticalSize: Get.height * 0.12,
      body: RefreshIndicator(
        onRefresh: () {
          return clinicDetailCont.init(showLoader: false);
        },
        child: Obx(
          () => SnapHelperWidget(
            future: clinicDetailCont.getClinicDetail.value,
            errorBuilder: (error) {
              return NoDataWidget(
                title: error,
                retryText: locale.value.reload,
                imageWidget: const ErrorStateWidget(),
                onRetry: () {
                  clinicDetailCont.init();
                },
              ).paddingSymmetric(horizontal: 16);
            },
            loadingWidget: const LoaderWidget(),
            onSuccess: (clinicDetailRes) {
              return AnimatedScrollView(
                listAnimationType: ListAnimationType.FadeIn,
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  Container(
                    color: context.cardColor,
                    child: Column(
                      children: [
                        CachedImageWidget(
                          url: clinicDetailCont.clinicData.value.clinicImage,
                          fit: BoxFit.cover,
                          width: Get.width,
                          height: Get.height * 0.3,
                          topLeftRadius: (defaultRadius * 2).toInt(),
                          topRightRadius: (defaultRadius * 2).toInt(),
                        ),
                        Column(
                          children: [
                            16.height,
                            Row(
                              children: [
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    RichText(
                                      text: TextSpan(
                                        children: [
                                          TextSpan(text: "${locale.value.pincode}: ", style: secondaryTextStyle()),
                                          TextSpan(
                                            text: clinicDetailCont.clinicData.value.pincode,
                                            style: primaryTextStyle(color: appColorSecondary, size: 12),
                                          ),
                                        ],
                                      ),
                                    ).visible(clinicDetailCont.clinicData.value.pincode.isNotEmpty),
                                    Row(
                                      children: [
                                        Text(
                                          clinicDetailCont.clinicData.value.name,
                                          style: boldTextStyle(size: 18),
                                          maxLines: 1,
                                          overflow: TextOverflow.ellipsis,
                                        ).flexible(),
                                      ],
                                    ).paddingTop(8).visible(clinicDetailCont.clinicData.value.name.isNotEmpty),
                                    // Specialty display
                                    Row(
                                      children: [
                                        const CachedImageWidget(url: Assets.iconsIcSpecialization, color: iconColor, height: 14, width: 14),
                                        12.width,
                                        Text(
                                          clinicDetailCont.clinicData.value.specialty,
                                          style: secondaryTextStyle(),
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                        ).flexible(),
                                      ],
                                    ).paddingTop(8).visible(clinicDetailCont.clinicData.value.specialty.isNotEmpty),
                                    GestureDetector(
                                      onTap: () {
                                        launchMap(clinicDetailCont.clinicData.value.address);
                                      },
                                      child: Row(
                                        children: [
                                          const CachedImageWidget(url: Assets.iconsIcLocation, color: iconColor, height: 14, width: 14),
                                          12.width,
                                          Text(
                                            "${clinicDetailCont.clinicData.value.cityName} ${clinicDetailCont.clinicData.value.stateName} ${clinicDetailCont.clinicData.value.countryName}",
                                            maxLines: 4,
                                            overflow: TextOverflow.ellipsis,
                                            style: secondaryTextStyle(),
                                          ).flexible(),
                                        ],
                                      ),
                                    ).paddingTop(16).visible(clinicDetailCont.clinicData.value.address.isNotEmpty),
                                    Row(
                                      children: [
                                        GestureDetector(
                                          onTap: () {
                                            launchCall(clinicDetailCont.clinicData.value.contactNumber);
                                          },
                                          child: Row(
                                            children: [
                                              const CachedImageWidget(url: Assets.iconsIcCall, color: iconColor, height: 14, width: 14),
                                              12.width,
                                              Text(
                                                clinicDetailCont.clinicData.value.contactNumber,
                                                style: primaryTextStyle(color: appColorPrimary),
                                              ),
                                            ],
                                          ),
                                        ).expand().visible(clinicDetailCont.clinicData.value.contactNumber.trim().isNotEmpty),
                                        Container(
                                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                                          decoration: boxDecorationDefault(
                                            color: getClinicStatusLightColor(clinicStatus: clinicDetailCont.clinicData.value.clinicStatus.toLowerCase()),
                                            borderRadius: radius(22),
                                          ),
                                          child: Text(
                                            getClinicStatus(status: clinicDetailCont.clinicData.value.clinicStatus.toLowerCase()),
                                            style: boldTextStyle(size: 10, color: Colors.green.shade600),
                                          ),
                                        ),
                                      ],
                                    ).paddingTop(clinicDetailCont.clinicData.value.contactNumber.trim().isNotEmpty ? 8 : 16),
                                    4.height,
                                    GestureDetector(
                                      onTap: () {
                                        launchMail(clinicDetailCont.clinicData.value.email);
                                      },
                                      child: Row(
                                        children: [
                                          const CachedImageWidget(url: Assets.iconsIcMail, color: iconColor, height: 14, width: 14),
                                          12.width,
                                          Text(
                                            clinicDetailCont.clinicData.value.email,
                                            style: primaryTextStyle(color: appColorPrimary),
                                          ),
                                        ],
                                      ),
                                    ),
                                    16.height,
                                    Row(
                                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                      children: [
                                        Expanded(
                                          child: _buildInfoTile(
                                            context,
                                            title: clinicDetailCont.clinicData.value.totalAppointment.toString(),
                                            subtitle: locale.value.totalAppointmentsDone,
                                            icon: Icons.check_circle_outline,
                                          ),
                                        ),
                                        10.width,
                                        Expanded(
                                          child: _buildInfoTile(
                                            context,
                                            title: clinicDetailCont.clinicData.value.satisfactionPercentage.toString(),
                                            subtitle: locale.value.satisfactionToCustomer,
                                            icon: Icons.emoji_emotions_outlined,
                                          ),
                                        ),
                                        10.width,
                                        Expanded(
                                          child: Obx(
                                            () => _buildInfoTile(
                                              context,
                                              title: clinicDetailCont.activeDoctorsCount.value.toString(),
                                              subtitle: locale.value.totalVerifiedPatients,
                                              icon: Icons.verified_user_sharp,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ).paddingSymmetric(vertical: 16),
                                  ],
                                ).flexible(),
                              ],
                            ),
                            16.height,
                          ],
                        ).paddingSymmetric(horizontal: 16),
                      ],
                    ),
                  ),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ReadMoreText(
                        parseHtmlString("${clinicDetailCont.clinicData.value.description} "),
                        trimLines: 4,
                        style: secondaryTextStyle(size: 14, color: secondaryTextColor),
                        colorClickableText: appColorPrimary,
                        trimMode: TrimMode.Line,
                        trimCollapsedText: " ...${locale.value.readMore}",
                        trimExpandedText: locale.value.readLess,
                        locale: Localizations.localeOf(context),
                      ).paddingTop(16).visible(clinicDetailCont.clinicData.value.description.isNotEmpty),
                      16.height,
                      ClinicDetailBtmComp(clinicDetailCont: clinicDetailCont),
                      24.height,
                    ],
                  ).paddingSymmetric(horizontal: 16),
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _buildInfoTile(BuildContext context, {required String title, required String subtitle, required IconData icon}) {
    return Container(
      width: (Get.width / 3) - 24,
      height: 100,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: boxDecorationDefault(
        color: context.cardColor,
        borderRadius: radius(12),
        border: Border.all(color: Colors.grey.shade300),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          Row(
            children: [
              Icon(icon, color: appColorPrimary, size: 20),
              10.width,
              Text(
                title,
                style: boldTextStyle(size: 16),
                maxLines: 2,
              ),
            ],
          ),
          10.height,
          Text(
            subtitle,
            style: secondaryTextStyle(),
            maxLines: 2,
          ),
        ],
      ),
    );
  }
}
