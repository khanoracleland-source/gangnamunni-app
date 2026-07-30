import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/screens/service/services_list_screen.dart';
import 'package:kivicare_patient/utils/app_common.dart';

import '../../../../components/cached_image_widget.dart';
import '../../service/system_service_list_screen.dart';
import '../model/category_list_model.dart';

class CategoryCard extends StatelessWidget {
  final CategoryElement category;

  const CategoryCard({super.key, required this.category});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        if (appConfigs.value.isMultiVendor) {
          Get.to(() => SystemServiceListScreen(), arguments: category);
        } else {
          Get.to(() => ServiceListScreen(), arguments: category);
        }
      },
      child: Container(
        width: Get.width / 3 - 24,
        height: Get.width * 0.32,
        alignment: Alignment.center,
        decoration: boxDecorationDefault(color: context.cardColor, borderRadius: radius(8)),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Hero(
              tag: category.categoryImage + category.id.toString(),
              child: CachedImageWidget(
                url: category.categoryImage,
                fit: BoxFit.fitHeight,
                circle: true,
                height: 72,
                width: 72,
              ).paddingSymmetric(horizontal: 8, vertical: 8),
            ),
            Hero(
              tag: category.name + category.id.toString(),
              child: Text(
                category.name,
                textAlign: TextAlign.center,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: primaryTextStyle(size: 12, decoration: TextDecoration.none),
              ),
            ).paddingSymmetric(horizontal: 6),
            8.height,
          ],
        ),
      ),
    );
  }
}
