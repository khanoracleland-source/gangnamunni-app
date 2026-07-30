import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/configs.dart';
import 'package:kivicare_patient/utils/price_widget.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../components/app_scaffold.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../Encounter/all_encounters_screen.dart';
import '../../bed/bed_history_screen.dart';
import '../../incident_management/incident_management_list_screen.dart';
import '../../other_patient/manage_other_patient_screen.dart';
import 'common_horizontal_profile_widget.dart';
import 'edit_user_profile_controller.dart';
import 'patient_wallet_history_screen.dart';
import 'profile_controller.dart';
import '../../../utils/app_common.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import '../other/settings_screen.dart';
import '../other/about_us_screen.dart';
import 'edit_user_profile.dart';
import 'package:url_launcher/url_launcher.dart';

class ProfileScreen extends StatelessWidget {
  ProfileScreen({super.key});

  final ProfileController profileController = Get.put(ProfileController());

  @override
  Widget build(BuildContext context) {
    return Obx(
      () => AppScaffoldNew(
        appBartitleText: locale.value.profile,
        hasLeadingWidget: false,
        isLoading: profileController.isLoading,
        appBarVerticalSize: Get.height * 0.12,
        body: AnimatedScrollView(
          padding: const EdgeInsets.only(top: 16, bottom: 80),
          children: [
            Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Obx(
                  () => ProfilePicHorizotalWidget(
                    heroTag: loginUserData.value.profileImage,
                    profileImage: loginUserData.value.profileImage,
                    firstName: loginUserData.value.firstName,
                    lastName: loginUserData.value.lastName,
                    userName: loginUserData.value.userName,
                    subInfo: loginUserData.value.email,
                    onCameraTap: () {
                      final EditUserProfileController editUserProfileController = EditUserProfileController(isProfilePhoto: true);
                      editUserProfileController.showBottomSheet(context);
                    },
                  ).onTap(() {
                    Get.to(() => EditUserProfileScreen(), duration: const Duration(milliseconds: 800));
                  }),
                ),
                16.height,
                SettingItemWidget(
                  decoration: boxDecorationDefault(color: context.cardColor),
                  title: locale.value.walletBalance,
                  splashColor: transparentColor,
                  onTap: () {
                    Get.to(() => PatientWalletHistory());
                  },
                  titleTextStyle: boldTextStyle(size: 14),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcUnFillWallet).circularLightPrimaryBg(),
                  trailing: PriceWidget(
                    price: userWalletData.value.walletAmount,
                    color: completedStatusColor,
                    size: 18,
                  ),
                  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                ).paddingTop(16),
                SettingItemWidget(
                  decoration: boxDecorationDefault(color: context.cardColor),
                  title: locale.value.editProfile,
                  subTitle: locale.value.personalizeYourProfile,
                  splashColor: transparentColor,
                  onTap: () {
                    Get.to(() => EditUserProfileScreen(), duration: const Duration(milliseconds: 800));
                  },
                  titleTextStyle: boldTextStyle(size: 14),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcEditprofileOutlined).circularLightPrimaryBg(),
                  trailing: trailing,
                  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                ).paddingTop(16),
                SettingItemWidget(
                  decoration: boxDecorationDefault(color: context.cardColor),
                  title: locale.value.otherPatient,
                  subTitle: locale.value.manageOtherPatient,
                  splashColor: transparentColor,
                  onTap: () {
                    Get.to(() => ManageOtherPatientScreen(), duration: const Duration(milliseconds: 800));
                  },
                  titleTextStyle: boldTextStyle(size: 14),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcUsersThreeprofile).circularLightPrimaryBg(),
                  trailing: trailing,
                  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                ).paddingTop(16),
                SettingItemWidget(
                  decoration: boxDecorationDefault(color: context.cardColor),
                  title: locale.value.encounters,
                  subTitle: locale.value.seeYourEncounterData,
                  splashColor: transparentColor,
                  onTap: () {
                    Get.to(() => AllEncountersScreen());
                  },
                  titleTextStyle: boldTextStyle(size: 14),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcEncounter, color: appColorPrimary).circularLightPrimaryBg(),
                  trailing: trailing,
                  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                ).paddingTop(16),
                SettingItemWidget(
                  decoration: boxDecorationDefault(color: context.cardColor),
                  title: locale.value.bedHistory,
                  subTitle: locale.value.seeYourBedHistory,
                  splashColor: transparentColor,
                  onTap: () {
                    Get.to(() => BedHistoryScreen());
                  },
                  titleTextStyle: boldTextStyle(size: 14),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcBed, color: appColorPrimary).circularLightPrimaryBg(),
                  trailing: trailing,
                  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                ).paddingTop(16),
                SettingItemWidget(
                  decoration: boxDecorationDefault(color: context.cardColor),
                  title: locale.value.incidentManagement,
                  subTitle: locale.value.requestHelpForAnyMistakeHappen,
                  splashColor: transparentColor,
                  onTap: () {
                    Get.to(() => IncidentManagementListScreen());
                  },
                  titleTextStyle: boldTextStyle(size: 14),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcIncidentLock, color: appColorPrimary).circularLightPrimaryBg(),
                  trailing: trailing,
                  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                ).paddingTop(16),
                SettingItemWidget(
                  title: locale.value.settings,
                  decoration: boxDecorationDefault(color: context.cardColor),
                  subTitle: "${locale.value.changePassword},${locale.value.themeAndMore}",
                  splashColor: transparentColor,
                  onTap: () {
                    Get.to(() => SettingScreen());
                  },
                  titleTextStyle: boldTextStyle(size: 14),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcSetting).circularLightPrimaryBg(),
                  trailing: trailing,
                  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                ).paddingTop(16),
                SettingItemWidget(
                  title: locale.value.aboutApp,
                  decoration: boxDecorationDefault(color: context.cardColor),
                  subTitle: locale.value.privacyPolicyTerms,
                  splashColor: transparentColor,
                  onTap: () {
                    Get.to(() => const AboutScreen());
                  },
                  titleTextStyle: boldTextStyle(size: 14),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcInfo).circularLightPrimaryBg(),
                  trailing: trailing,
                  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                ).paddingTop(16),
                SettingItemWidget(
                  title: locale.value.contactUs,
                  decoration: boxDecorationDefault(color: context.cardColor),
                  subTitle: locale.value.getInTouchWithSupport,
                  splashColor: transparentColor,
                  onTap: () {
                    commonLaunchUrl('$DOMAIN_URL/contact-us', launchMode: LaunchMode.externalApplication);
                  },
                  titleTextStyle: boldTextStyle(size: 14),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcContactUs).circularLightPrimaryBg(),
                  trailing: trailing,
                  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                ).paddingTop(16),
                SettingItemWidget(
                  title: locale.value.rateApp,
                  decoration: boxDecorationDefault(color: context.cardColor),
                  subTitle: locale.value.showSomeLoveShare,
                  splashColor: transparentColor,
                  onTap: () async {
                    //In app review
                    /* final InAppReview inAppReview = InAppReview.instance;

                    if (await inAppReview.isAvailable()) {
                      inAppReview.requestReview();
                    } */
                    handleRate();
                  },
                  titleTextStyle: boldTextStyle(size: 14),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcStar).circularLightPrimaryBg(),
                  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                ).paddingTop(16),
                /*SettingItemWidget(
                  title: locale.value.inAppUpdate,
                  decoration: boxDecorationDefault(color: context.cardColor),
                  splashColor: transparentColor,
                  onTap: () {
                    profileController.isAutoUpdateOn(!profileController.isAutoUpdateOn.value);
                    setValueToLocal(AutoUpdateConst.isAutoUpdateOn, !profileController.isAutoUpdateOn.value);
                  },
                  titleTextStyle: primaryTextStyle(),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcDownload,  color: context.primaryColor),
                  trailing: Obx(() {
                    return Transform.scale(
                      scale: .70,
                      child: Switch(
                        value: profileController.isAutoUpdateOn.value,
                        onChanged: (val) {
                          profileController.isAutoUpdateOn(val);
                          setValueToLocal(AutoUpdateConst.isAutoUpdateOn, val);
                        },
                        activeTrackColor: context.primaryColor,
                        activeColor: white,
                      ),
                    );
                  }),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                ).paddingTop(16),*/
                SettingItemWidget(
                  title: locale.value.logout,
                  decoration: boxDecorationDefault(color: context.cardColor),
                  subTitle: locale.value.securelyLogOutOfAccount,
                  splashColor: transparentColor,
                  onTap: () {
                    showConfirmDialogCustom(
                      primaryColor: appColorPrimary,
                      context,
                      negativeText: locale.value.cancel,
                      positiveText: locale.value.logout,
                      onAccept: (_) {
                        profileController.handleLogout();
                      },
                      subTitle: locale.value.doYouWantToLogout,
                      title: locale.value.ohNoYouAreLeaving,
                    );
                  },
                  titleTextStyle: boldTextStyle(size: 14),
                  leading: commonLeadingWid(imgPath: Assets.iconsIcLogout).circularLightPrimaryBg(),
                  padding: const EdgeInsets.symmetric(vertical: 15, horizontal: 16),
                ).paddingTop(16),
                30.height,
                VersionInfoWidget(prefixText: '${locale.value.version}  ', textStyle: primaryTextStyle(color: secondaryTextColor)).center(),
                32.height,
              ],
            ).paddingSymmetric(horizontal: 16),
          ],
        ),
      ),
    );
  }

  Widget get trailing => const Icon(Icons.arrow_forward_ios_rounded, size: 12, color: darkGray);
}
