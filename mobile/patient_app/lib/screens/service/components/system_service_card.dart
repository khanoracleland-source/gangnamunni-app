import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/cached_image_widget.dart';
import '../../../main.dart';
import '../../../utils/app_common.dart';
import '../../home/model/system_service_res.dart';
import '../services_list_screen.dart';

class SystemServiceCard extends StatelessWidget {
  final SystemService systemServiceElement;

  const SystemServiceCard({super.key, required this.systemServiceElement});

  @override
  Widget build(BuildContext context) {
    return SettingItemWidget(
      title: systemServiceElement.name,
      subTitle: '${locale.value.total} ${systemServiceElement.totalServices} ${locale.value.servicesAvailable}',
      padding: const EdgeInsets.all(16),
      decoration: boxDecorationDefault(color: context.cardColor, borderRadius: radius(8)),
      leading: CachedImageWidget(
        url: systemServiceElement.systemServiceImage,
        fit: BoxFit.fitHeight,
        circle: true,
        height: 60,
        width: 60,
      ),
      trailing: const Icon(Icons.keyboard_arrow_right),
      onTap: () {
        /// Store select system service in global variable
        selectedSysService(systemServiceElement);
        Get.to(() => ServiceListScreen(), arguments: systemServiceElement);
      },
    ).paddingBottom(16);
  }
}
