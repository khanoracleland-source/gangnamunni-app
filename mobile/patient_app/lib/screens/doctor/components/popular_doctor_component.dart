import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../main.dart';
import '../../../utils/view_all_label_component.dart';
import '../../home/home_controller.dart';
import 'popular_doctor_card.dart';
import '../../home/components/doctor_list_screen.dart';

class PopularDoctorComponent extends StatelessWidget {
  PopularDoctorComponent({super.key});

  final HomeController homeController = Get.find();

  @override
  Widget build(BuildContext context) {
    if (homeController.dashboardData.value.topDoctor.selectedDoctor.isEmpty) {
      return const Offstage();
    }

    return SizedBox(
      width: Get.width,
      child: Column(
        children: [
          16.height,
          ViewAllLabel(
            label:homeController.dashboardData.value.topDoctor.subTitle,
            onTap: () {
              Get.to(() => DoctorViewListScreen(title:homeController.dashboardData.value.topDoctor.subTitle, isFromDashboard: true),    arguments: {"isPopular": 1},);
            },
            trailingText: locale.value.viewAll,
          ).paddingOnly(left: 16, right: 8),
          Obx(
            () => HorizontalList(
              spacing: 16,
              runSpacing: 16,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: homeController.dashboardData.value.topDoctor.selectedDoctor.length,
              itemBuilder: (context, index) {
                return PopularDoctorCard(doctorElement: homeController.dashboardData.value.topDoctor.selectedDoctor[index]);
              },
            ),
          ),
        ],
      ),
    );
  }
}
