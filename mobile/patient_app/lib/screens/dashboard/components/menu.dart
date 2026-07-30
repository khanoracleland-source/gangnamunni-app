import 'package:get/get.dart';
import 'package:kivicare_patient/generated/assets.dart';

import '../../../main.dart';

enum BottomItem {
  home,
  appointment,
  notification,
  settings,
  profile,
}

class BottomBarItem {
  RxString title;
  final String icon;
  final String activeIcon;
  final String type;

  BottomBarItem({
    required this.title,
    required this.icon,
    required this.activeIcon,
    required this.type,
  });
}
RxList<BottomBarItem> bottomNavItems = [
  BottomBarItem(title: locale.value.home.obs, icon: Assets.navigationIcHomeOutlined, activeIcon: Assets.navigationIcHomeFilled, type: BottomItem.home.name),
  BottomBarItem(title: locale.value.appointment.obs, icon: Assets.navigationIcCalenderOutlined, activeIcon: Assets.navigationIcCalenderFilled, type: BottomItem.appointment.name),
  BottomBarItem(title: locale.value.profile.obs, icon: Assets.navigationIcUserOutlined, activeIcon: Assets.navigationIcUserFilled, type: BottomItem.profile.name),
].obs;
