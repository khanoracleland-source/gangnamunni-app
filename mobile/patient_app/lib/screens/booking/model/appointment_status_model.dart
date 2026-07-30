import 'package:get/get_rx/src/rx_types/rx_types.dart';
import 'package:kivicare_patient/generated/assets.dart';
import 'package:kivicare_patient/main.dart';

enum AppointmentStatus {
  all,
  upcoming,
  completed,
  pending,
  cancelled,
}

class AppointmentStatusModel {
  final String icon;
  final AppointmentStatus type;
  RxString? name; // Change this to RxString

  AppointmentStatusModel({
    this.type = AppointmentStatus.all,
    this.name,
    this.icon = '',
  });
}

RxList<AppointmentStatusModel> filterStatus = [
  AppointmentStatusModel(icon: Assets.iconsIcMenu, name: locale.value.all.obs),
  AppointmentStatusModel(icon: Assets.iconsIcUpcomingAppoinment, type: AppointmentStatus.upcoming, name: locale.value.upcoming.obs),
  AppointmentStatusModel(icon: Assets.iconsIcChecked, type: AppointmentStatus.completed, name: locale.value.completed.obs),
  AppointmentStatusModel(icon: Assets.iconsIcTimeOutlined, type: AppointmentStatus.pending, name: locale.value.pending.obs),
  AppointmentStatusModel(icon: Assets.iconsIcCancel, type: AppointmentStatus.cancelled, name: locale.value.cancelled.obs),
].obs;
