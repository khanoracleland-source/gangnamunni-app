import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../main.dart';
import '../../../utils/view_all_label_component.dart';
import '../../category/category_screen.dart';
import '../../category/components/category_card.dart';
import '../home_controller.dart';

class ChooseCategoryComponents extends StatelessWidget {
  ChooseCategoryComponents({super.key});
  final HomeController homeController = Get.find();

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => Column(
        children: [
          16.height,
          ViewAllLabel(
            label: locale.value.category,
            onTap: () {
              Get.to(() => CategoryScreen(), duration: const Duration(milliseconds: 800));
            },
            trailingText: locale.value.viewAll,
          ).paddingOnly(left: 16, right: 8),
          Obx(
            () => HorizontalList(
              runSpacing: 16,
              spacing: 16,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              wrapAlignment: WrapAlignment.start,
              itemCount: homeController.dashboardData.value.categories.length,
              itemBuilder: (context, index) {
                return CategoryCard(category: homeController.dashboardData.value.categories[index]);
              },
            ),
          ),
        ],
      ).visible(homeController.dashboardData.value.categories.isNotEmpty),
    );
  }
}
