import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../main.dart';
import '../../../utils/view_all_label_component.dart';
import '../../booking/components/appointment_card.dart';
import '../home_controller.dart';

class UpcomingAppointmentComponents extends StatelessWidget {
  UpcomingAppointmentComponents({super.key});
  final HomeController homeScreenController = Get.find();

  @override
  Widget build(BuildContext context) {
    if (homeScreenController.dashboardData.value.upcomingAppointment.isEmpty) {
      return const Offstage();
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        16.height,
        ViewAllLabel(label: locale.value.upcomingAppointments, isShowAll: false),
        AppointmentCard(appointment: homeScreenController.dashboardData.value.upcomingAppointment.first),
      ],
    ).paddingSymmetric(horizontal: 16);
  }
}
