import 'package:get/get.dart';
import 'package:flutter/material.dart';
import 'package:kivicare_patient/main.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../utils/view_all_label_component.dart';
import '../../clinic/components/clinic_card.dart';
import '../../clinic/clinics_list_screen.dart';
import '../home_controller.dart';

class NearByClinicComponent extends StatelessWidget {
  NearByClinicComponent({super.key});
  final HomeController homeController = Get.find();

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => SizedBox(
        width: Get.width,
        child: Column(
          children: [
            16.height,
            ViewAllLabel(
              label: locale.value.clinicsNearYou,
              onTap: () {
                Get.to(() => ClinicListScreen());
              },
              trailingText: locale.value.viewAll,
            ).paddingOnly(left: 16, right: 8),
            Container(
              alignment: Alignment.center,
              height: Get.height * 0.365,
              child: Obx(
                () => PageView.builder(
                  controller: homeController.pageController,
                  onPageChanged: (int page) {
                    hideKeyboard(context);
                    homeController.currentPage(page);
                  },
                  itemCount: homeController.dashboardData.value.nearByClinic.length,
                  itemBuilder: (context, index) {
                    return ClinicCard(clinicData: homeController.dashboardData.value.nearByClinic[index]);
                  },
                ),
              ),
            ).paddingSymmetric(horizontal: 16),
            Obx(
              () => Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List<Widget>.generate(
                  homeController.dashboardData.value.nearByClinic.length,
                  (index) {
                    return InkWell(
                      onTap: () {
                        homeController.pageController.animateToPage(
                          index,
                          duration: const Duration(milliseconds: 300),
                          curve: Curves.easeIn,
                        );
                      },
                      child: Container(
                        height: 8,
                        width: homeController.currentPage.value == index ? 35 : 8,
                        margin: const EdgeInsets.all(5),
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(20),
                          color: homeController.currentPage.value == index ? const Color(0xFF6E8192) : const Color(0xFF6E8192).withValues(alpha: 0.5),
                        ),
                      ),
                    );
                  },
                ),
              ),
            ).paddingTop(16).paddingSymmetric(horizontal: 16),
          ],
        ),
      ).visible(homeController.dashboardData.value.nearByClinic.isNotEmpty),
    );
  }
}
