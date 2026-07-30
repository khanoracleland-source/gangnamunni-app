import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../main.dart';
import '../../../utils/view_all_label_component.dart';
import '../../service/components/service_card.dart';
import '../../service/model/service_list_model.dart';
import '../../service/services_list_screen.dart';
import '../home_controller.dart';

class FeaturedServiceComponent extends StatelessWidget {
  FeaturedServiceComponent({super.key});

  final HomeController homeController = Get.find();

  @override
  Widget build(BuildContext context) {
    if (homeController.dashboardData.value.featuredServices.isEmpty) {
      return const Offstage();
    }

    return SizedBox(
      width: Get.width,
      child: Column(
        children: [
          16.height,
          ViewAllLabel(
            label: locale.value.services,
            onTap: () {
              Get.to(() => ServiceListScreen(title: locale.value.services, isFromDashboard: true), arguments: ServiceElement(featured: 1));
            },
            trailingText: locale.value.viewAll,
          ).paddingOnly(left: 16, right: 8),
          Obx(
            () => HorizontalList(
              spacing: 16,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              itemCount: homeController.dashboardData.value.featuredServices.length,
              itemBuilder: (context, index) {
                return ServiceCard(serviceElement: homeController.dashboardData.value.featuredServices[index]);
              },
            ),
          ),
        ],
      ),
    );
  }
}
