import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/screens/home/components/popular_service_list.dart';
import 'package:kivicare_patient/screens/service/components/popular_service_card.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../main.dart';
import '../../../utils/view_all_label_component.dart';
import '../../home/home_controller.dart';

class PopularServiceComponent extends StatelessWidget {
  PopularServiceComponent({super.key});

  final HomeController homeController = Get.find();

  @override
  Widget build(BuildContext context) {
    if (homeController.dashboardData.value.popularService.selectedService.isEmpty) {
      return const Offstage();
    }

    return SizedBox(
      width: Get.width,
      child: Column(
        children: [
          16.height,
          ViewAllLabel(
            label: homeController.dashboardData.value.popularService.subTitle,
            onTap: () {
              Get.to(() => PopularServiceListScreen(title: locale.value.ourPopularSevices, isFromDashboard: true),arguments: {"isPopular":1});
            },
            trailingText: locale.value.viewAll,
          ).paddingOnly(left: 16, right: 8),
          Obx(
            () => HorizontalList(
              spacing: 16,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: homeController.dashboardData.value.popularService.selectedService.length,
              itemBuilder: (context, index) {
                return PopularServiceCard(serviceElement: homeController.dashboardData.value.popularService.selectedService[index]);
              },
            ),
          ),
        ],
      ),
    );
  }
}
