import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/screens/home/components/clinic_list_screen.dart';
import 'package:kivicare_patient/screens/clinic/components/popular_clinic_card.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../main.dart';
import '../../../utils/view_all_label_component.dart';
import '../home_controller.dart';

class PerfectClinicComponent extends StatelessWidget {
  PerfectClinicComponent({super.key});

  final HomeController homeController = Get.find();

  @override
  Widget build(BuildContext context) {
    if (homeController.dashboardData.value.popularClinic.selectedClinic.isEmpty) {
      return const Offstage();
    }

    return SizedBox(
      width: Get.width,
      child: Column(
        children: [
          16.height,
          ViewAllLabel(
            label: homeController.dashboardData.value.popularClinic.subTitle,
            onTap: () {
              Get.to(
                () => ClinicListComponent(
                  title: homeController.dashboardData.value.popularClinic.subTitle,
                  isFromDashboard: true,
                ),
                arguments: {"isPopular": 1},
              );
            },
            trailingText: locale.value.viewAll,
          ).paddingOnly(left: 16, right: 8),
          Obx(
            () => HorizontalList(
              spacing: 16,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: homeController.dashboardData.value.popularClinic.selectedClinic.length,
              itemBuilder: (context, index) {
                return PopularClinicCard(
                  clinicElement: homeController.dashboardData.value.popularClinic.selectedClinic[index],
                  width: Get.width / 1.2,
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
