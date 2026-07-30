import 'package:get/get.dart';
import 'package:flutter/material.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:kivicare_patient/utils/colors.dart';
import '../../../components/app_scaffold.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../dashboard/dashboard_screen.dart';
import '../../home/home_controller.dart';
import '../sign_in_sign_up/signin_screen.dart';
import 'welcome_controller.dart';

class WelcomeScreen extends StatelessWidget {
  WelcomeScreen({super.key});

  final WelcomeScreenController optionScreenController = Get.put(WelcomeScreenController());

  @override
  Widget build(BuildContext context) {
    return AppScaffold(
      hideAppBar: true,
      isLoading: optionScreenController.isLoading,
      body: Stack(
        // fit: StackFit.expand,
        children: [
          SizedBox(
            height: Get.height,
            width: Get.width,
          ),
          Positioned(
            width: Get.width,
            top: 0,
            child: Image.asset(
              Assets.imagesWelcomeBg,
              width: Get.width,
              fit: BoxFit.fitWidth,
            ),
          ),
          Positioned(
            bottom: 0,
            child: Stack(
              alignment: Alignment.center,
              clipBehavior: Clip.none,
              children: [
                Container(
                  padding: const EdgeInsets.all(16),
                  width: MediaQuery.of(context).size.width,
                  height: optionScreenController.bottomWidgetHeight,
                  decoration: boxDecorationDefault(
                    color: context.cardColor,
                    borderRadius: BorderRadius.only(topRight: radiusCircular(30), topLeft: const Radius.circular(30)),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Spacer(),
                      Text(
                        locale.value.exploreTopClinicsWithAdvancedServicesTailored,
                        textAlign: TextAlign.center,
                        style: primaryTextStyle(size: 20),
                      ),
                      16.height,
                      Text(
                        locale.value.discoverYourIdealClinicWithOurPersonalizedSea,
                        style: secondaryTextStyle(size: 14),
                        textAlign: TextAlign.center,
                      ).paddingSymmetric(horizontal: 24),
                      const Spacer(),
                      Row(
                        children: [
                          AppButton(
                            height: 52,
                            elevation: 0,
                            color: lightPrimaryColor,
                            onTap: () {
                              Get.to(
                                () => SignInScreen(),
                                arguments: true,
                                binding: BindingsBuilder(() {}),
                              );
                            },
                            child: Text(
                              locale.value.signIn,
                              style: boldTextStyle(
                                size: 12,
                                color: blackTextColor,
                              ),
                            ),
                          ).expand(),
                          24.width,
                          AppButton(
                            height: 52,
                            elevation: 0,
                            color: appColorSecondary,
                            onTap: () {
                              Get.offAll(
                                () => DashboardScreen(),
                                binding: BindingsBuilder(() {
                                  Get.put(HomeController());
                                }),
                              );
                            },
                            child: Text(
                              locale.value.explore,
                              style: boldTextStyle(
                                size: 12,
                                color: whiteTextColor,
                                weight: FontWeight.w400,
                              ),
                            ),
                          ).expand(),
                        ],
                      ).paddingSymmetric(horizontal: 24).paddingBottom(30),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
