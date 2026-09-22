import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../components/app_scaffold.dart';
import '../../main.dart';
import '../../utils/app_common.dart';
import '../../utils/empty_error_state_widget.dart';

import 'components/choose_category_components.dart';
import 'components/greetings_component.dart';
import 'components/quick_book_component.dart';
import 'components/perfect_clinic_list.dart';
import '../service/components/popular_service_component.dart';
import 'components/slider_component.dart';
import '../doctor/components/popular_doctor_component.dart';
import 'components/upcoming_appointment_components.dart';
import 'home_controller.dart';

class HomeScreen extends StatelessWidget {
  HomeScreen({super.key});

  final HomeController homeScreenController = Get.put(HomeController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      hasLeadingWidget: false,
      isBlurBackgroundinLoader: true,
      isLoading: homeScreenController.isLoading,
      appBarVerticalSize: Get.height * 0.14,
      appBarChild: const GreetingsComponent(),
      body: Obx(
        () {
          if (!homeScreenController.hasData) {
            final error = homeScreenController.loadError.value;
            if (error == null || homeScreenController.isLoading.value) {
              return const SizedBox.shrink();
            }
            return NoDataWidget(
              title: error,
              retryText: locale.value.reload,
              imageWidget: const ErrorStateWidget(),
              onRetry: () {
                homeScreenController.getDashboardDetail();
              },
            ).paddingSymmetric(horizontal: 16);
          }
            return SingleChildScrollView(
              padding: const EdgeInsets.only(bottom: 90),
              physics: const AlwaysScrollableScrollPhysics(),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ChooseCategoryComponents(),
                  const SliderComponent(),
                  if (appConfigs.value.isQuickBookingEnabled.getBoolInt()) QuickBookComponent(),
                  UpcomingAppointmentComponents(),
                  // FeaturedServiceComponent(),
                  PopularServiceComponent(),
                  PerfectClinicComponent(),
                  PopularDoctorComponent(),
                ],
              ),
            );
        },
      ),
    );
  }
}
