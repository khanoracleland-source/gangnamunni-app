import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/screens/service/service_list_controller.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../components/app_custom_dialog.dart';
import '../../components/app_scaffold.dart';
import '../../components/cached_image_widget.dart';
import '../../components/loader_widget.dart';
import '../../main.dart';
import '../../utils/app_common.dart';
import '../../utils/colors.dart';
import '../../utils/constants.dart';
import '../../utils/empty_error_state_widget.dart';
import '../../utils/price_widget.dart';
import '../clinic/clinic_detail_screen.dart';
import '../clinic/clinics_list_screen.dart';
import '../clinic/model/clinic_detail_model.dart';
import '../clinic/model/clinics_res_model.dart';
import '../doctor/doctor_list_screen.dart';
import '../slots/booking_form_screen.dart';
import 'components/service_detail_clinics_component.dart';
import 'model/service_list_model.dart';
import 'service_detail_controller.dart';
import '../../api/core_apis.dart';

class ServiceDetailScreen extends StatelessWidget {
  final bool isFromClinicDetail;

  ServiceDetailScreen({super.key, this.isFromClinicDetail = false});

  final ServiceDetailController serviceDetailController = Get.put(ServiceDetailController());

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      return AppScaffoldNew(
        isLoading: serviceDetailController.isLoading,
        appBartitleText: serviceDetailController.serviceData.value.name,
        appBarVerticalSize: Get.height * 0.12,
        body: RefreshIndicator(
          onRefresh: () {
            return serviceDetailController.init(showLoader: false);
          },
          child: Obx(
            () => SnapHelperWidget(
              future: serviceDetailController.getServiceDetails.value,
              errorBuilder: (error) {
                return NoDataWidget(
                  title: error,
                  retryText: locale.value.reload,
                  imageWidget: const ErrorStateWidget(),
                  onRetry: () {
                    serviceDetailController.init();
                  },
                ).paddingSymmetric(horizontal: 16);
              },
              loadingWidget: const LoaderWidget(),
              onSuccess: (serviceDetailRes) {
                return AnimatedScrollView(
                  listAnimationType: ListAnimationType.FadeIn,
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.only(top: 16, bottom: 80),
                  children: [
                    Hero(
                      tag: serviceDetailController.serviceData.value.serviceImage.trim().isNotEmpty ? "${serviceDetailController.serviceData.value.id}${serviceDetailController.serviceData.value.serviceImage}" : UniqueKey(),
                      child: CachedImageWidget(
                        url: serviceDetailController.serviceData.value.serviceImage,
                        fit: BoxFit.cover,
                        width: Get.width,
                        height: 230,
                        radius: defaultRadius / 2,
                      ),
                    ).paddingSymmetric(horizontal: 16),
                    16.height,
                    Row(
                      children: [
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              serviceDetailController.serviceData.value.name,
                              style: boldTextStyle(size: 16),
                            ),
                            8.height,
                            Row(
                              children: [
                                Text('${locale.value.category} : ', style: secondaryTextStyle()),
                                Text(
                                  serviceDetailController.serviceData.value.categoryName,
                                  style: boldTextStyle(size: 14, color: appColorSecondary),
                                ),
                              ],
                            ),
                            12.height,
                            Marquee(
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  // Main price: show discounted payableAmount when discount applied, else charges
                                  PriceWidget(
                                    price: serviceDetailController.serviceData.value.isDiscount ? serviceDetailController.serviceData.value.payableAmount : serviceDetailController.serviceData.value.payableAmount,
                                    size: 18,
                                  ).paddingRight(6),
                                  // Original price with strikethrough when discounted
                                  if (serviceDetailController.serviceData.value.isDiscount)
                                    PriceWidget(
                                      price: serviceDetailController.serviceData.value.isInclusiveTaxesAvailable ? serviceDetailController.serviceData.value.charges + serviceDetailController.serviceData.value.totalInclusiveTax : serviceDetailController.serviceData.value.charges,
                                      isLineThroughEnabled: true,
                                      size: 14,
                                      color: textSecondaryColorGlobal,
                                    ),
                                  // Discount indicator text/value
                                  if (serviceDetailController.serviceData.value.isDiscount)
                                    if (serviceDetailController.serviceData.value.discountType == TaxType.PERCENTAGE)
                                      Text(
                                        '${serviceDetailController.serviceData.value.discountValue}%  ${locale.value.off}',
                                        style: boldTextStyle(color: greenColor, size: 14),
                                      ).paddingLeft(8)
                                    else if (serviceDetailController.serviceData.value.discountType == TaxType.FIXED)
                                      PriceWidget(
                                        price: serviceDetailController.serviceData.value.discountValue,
                                        color: greenColor,
                                        size: 14,
                                        isDiscountedPrice: true,
                                      ).paddingLeft(6),
                                ],
                              ),
                            ),
                          ],
                        ).flexible(),
                      ],
                    ).paddingSymmetric(horizontal: 16),
                    if (serviceDetailController.serviceData.value.isInclusiveTaxesAvailable) ...[
                      Text(locale.value.includesInclusiveTax, style: secondaryTextStyle(color: appColorSecondary, size: 12, fontStyle: FontStyle.italic)).paddingSymmetric(horizontal: 16),
                    ],
                    24.height,
                    if (serviceDetailController.serviceData.value.description.isNotEmpty)
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          serviceDetailController.serviceData.value.description,
                          textAlign: TextAlign.justify,
                          style: primaryTextStyle(size: 12, color: secondaryTextColor.withValues(alpha: 0.8)),
                        ),
                      ).paddingSymmetric(horizontal: 16),
                    ServiceDetailClinicsComponent(
                      serviceDetailController: serviceDetailController,
                      onCardTap: (clinicData) {
                        /// Store selected clinic in global variable
                        if (clinicData.id == serviceDetailController.selectedClinic.value.id) {
                          /// Deselect, If again tap on same clinic
                          serviceDetailController.selectedClinic(Clinic(clinicSession: ClinicSession()));
                          currentSelectedClinic(serviceDetailController.selectedClinic.value);
                        } else {
                          serviceDetailController.selectedClinic(clinicData);
                          currentSelectedClinic(serviceDetailController.selectedClinic.value);
                        }
                      },
                      onClickViewDetail: (clinicData) {
                        /// Store selected clinic in global variable
                        currentSelectedClinic(clinicData);
                        Get.delete<ServiceListController>();
                        Get.to(() => ClinicDetailScreen(), arguments: clinicData);
                      },
                    ),
                    32.height,
                  ],
                );
              },
            ),
          ),
        ),
        widgetsStackedOverBody: [
          Positioned(
            bottom: 16,
            width: Get.width,
            child: AppButton(
              margin: EdgeInsets.zero,
              height: 50,
              width: Get.width,
              elevation: 0,
              color: appColorSecondary,
              onTap: () async {
                if (isFromClinicDetail) {
                  showInDialog(
                    context,
                    contentPadding: EdgeInsets.zero,
                    builder: (context) {
                      return AppCustomDialog(
                        title: locale.value.doYouWantToReplaceThePreviousServiceWithTheCu,
                        negativeText: locale.value.no,
                        positiveText: locale.value.yes,
                        onTap: () {
                          currentSelectedService(serviceDetailController.serviceData.value);
                          Get.back();
                          Get.to(() => DoctorsListScreen(), arguments: currentSelectedClinic.value.id);
                        },
                      );
                    },
                  );
                } else {
                  /// Store select service in global variable
                  currentSelectedService(serviceDetailController.serviceData.value);

                  if (!currentSelectedClinic.value.id.isNegative) {
                    Get.to(() => DoctorsListScreen(), arguments: currentSelectedClinic.value.id);
                  } else {
                    // Check if there's only one clinic available for this service
                    await checkAndNavigateToBookingForm(serviceDetailController.serviceData.value);
                  }
                }
              },
              child: Text(locale.value.bookNow, style: boldTextStyle(size: 14, color: whiteTextColor, weight: FontWeight.w400)),
            ).paddingSymmetric(horizontal: 16),
          ),
        ],
      );
    });
  }

  Future<void> checkAndNavigateToBookingForm(ServiceElement serviceElement) async {
    try {
      // Get clinics for this service
      final RxList<Clinic> clinics = RxList();
      await CoreServiceApis.getClinics(
        clinics: clinics,
        serviceId: serviceElement.id,
        lastPageCallBack: (p) {},
      );

      if (clinics.length == 1) {
        // Only one clinic available - auto-select and navigate directly to booking form
        currentSelectedClinic(clinics[0]);
        log('Auto-selected clinic: ${clinics[0].name}');
        Get.to(() => BookingFormScreen());
      } else {
        // Multiple clinics available - show clinic selection screen
        Get.to(() => ClinicListScreen(), arguments: serviceElement);
      }
    } catch (e) {
      log('Error checking clinics: $e');
      // Fallback to clinic selection screen
      Get.to(() => ClinicListScreen(), arguments: serviceElement);
    }
  }
}
