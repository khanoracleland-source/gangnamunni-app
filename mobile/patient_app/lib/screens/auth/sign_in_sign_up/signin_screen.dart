import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/utils/app_common.dart';
import '../../../components/app_logo_widget.dart';
import '../../../components/app_scaffold.dart';
import '../../../configs.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/constants.dart';
import 'sign_in_controller.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import '../password/forget_password_screen.dart';
import 'signup_screen.dart';

class SignInScreen extends StatelessWidget {
  SignInScreen({super.key});

  final SignInController signInController = Get.put(SignInController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      isLoading: signInController.isLoading,
      hasLeadingWidget: false,
      clipBehaviorSplitRegion: Clip.none,
      body: Stack(
        clipBehavior: Clip.none,
        children: [
          AnimatedScrollView(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Text(
                '${locale.value.hello} ${signInController.userName.value.isNotEmpty ? signInController.userName.value : locale.value.guest}!',
                style: primaryTextStyle(size: 24),
              ),
              8.height,
              Text(
                '${locale.value.welcomeBackToThe}  $APP_NAME',
                style: secondaryTextStyle(size: 14),
              ),
              SizedBox(height: Get.height * 0.05),
              Form(
                key: signInController.signInformKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    AppTextField(
                      title: locale.value.email,
                      textStyle: primaryTextStyle(size: 12),
                      controller: signInController.emailCont,
                      focus: signInController.emailFocus,
                      nextFocus: signInController.passwordFocus,
                      textFieldType: TextFieldType.EMAIL_ENHANCED,
                      decoration: inputDecoration(
                        context,
                        fillColor: context.cardColor,
                        filled: true,
                        hintText: "${locale.value.eG} merry_456@gmail.com",
                      ),
                      suffix: commonLeadingWid(imgPath: Assets.iconsIcMail, size: 14).paddingAll(14),
                    ),
                    16.height,
                    AppTextField(
                      title: locale.value.password,
                      textStyle: primaryTextStyle(size: 12),
                      controller: signInController.passwordCont,
                      focus: signInController.passwordFocus,
                      // Optional
                      textFieldType: TextFieldType.PASSWORD,
                      obscureText: true,
                      decoration: inputDecoration(
                        context,
                        fillColor: context.cardColor,
                        filled: true,
                        hintText: "••••••••",
                      ),
                      suffixPasswordVisibleWidget: commonLeadingWid(imgPath: Assets.iconsIcEye, size: 14).paddingAll(12),
                      suffixPasswordInvisibleWidget: commonLeadingWid(imgPath: Assets.iconsIcEyeSlash, size: 14).paddingAll(12),
                    ),
                    Column(
                      children: [
                        Obx(
                          () => Visibility(
                            visible: signInController.loginSucessfull.isTrue,
                            child: Column(
                              children: [
                                16.height,
                                AppTextField(
                                  title: signInController.tryToAnother.isTrue ? locale.value.otpFromAuthenticatorApp : locale.value.otp,
                                  textStyle: primaryTextStyle(size: 12),
                                  controller: signInController.otpCont,
                                  focus: signInController.otpFocus,
                                  textFieldType: TextFieldType.NUMBER,
                                  inputFormatters: [
                                    FilteringTextInputFormatter.digitsOnly,
                                    LengthLimitingTextInputFormatter(6),
                                  ],
                                  validator: (value) {
                                    if (value == null || value.length != 6) {
                                      return locale.value.pleaseEnterValid6digitOTP;
                                    }
                                    return null;
                                  },
                                  decoration: inputDecoration(
                                    context,
                                    fillColor: context.cardColor,
                                    filled: true,
                                    hintText: "123456",
                                  ),
                                ),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.end,
                                  children: [
                                    Obx(
                                      () => signInController.remainingSeconds.value > 0
                                          ? Text(
                                              signInController.formattedTime,
                                              style: primaryTextStyle(size: 14, color: context.primaryColor),
                                            )
                                          : TextButton(
                                              onPressed: () {
                                                signInController.callLoginApi();
                                                signInController.startTimer();
                                              },
                                              child: Text(
                                                locale.value.resendOTP,
                                                style: primaryTextStyle(
                                                  size: 14,
                                                  color: context.primaryColor,
                                                ),
                                              ),
                                            ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                    8.height,
                    Obx(
                      () => Row(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          if (signInController.isGoogleAuthentication.value == 1) TextButton(
                                  onPressed: () {
                                    signInController.tryToAnother.value = !signInController.tryToAnother.value;
                                  },
                                  child: Text(
                                    locale.value.tryToAnotherWay,
                                    style: primaryTextStyle(
                                      size: 12,
                                      color: appColorPrimary,
                                      // decoration: TextDecoration.underline,
                                      fontStyle: FontStyle.italic,
                                      decorationColor: appColorPrimary,
                                    ),
                                  ),
                                ) else const SizedBox(),
                        ],
                      ),
                    ),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Obx(
                          () => CheckboxListTile(
                            checkColor: whiteColor,
                            value: signInController.isRememberMe.value,
                            activeColor: appColorPrimary,
                            visualDensity: VisualDensity.compact,
                            dense: true,
                            controlAffinity: ListTileControlAffinity.leading,
                            contentPadding: EdgeInsets.zero,
                            onChanged: (val) async {
                              signInController.toggleSwitch();
                            },
                            checkboxShape: RoundedRectangleBorder(borderRadius: radius(0)),
                            side: const BorderSide(color: secondaryTextColor, width: 1.5),
                            title: Text(
                              locale.value.rememberMe,
                              style: secondaryTextStyle(color: darkGrayGeneral),
                            ),
                          ),
                        ).expand(),
                        TextButton(
                          onPressed: () {
                            Get.to(() => ForgetPassword());
                          },
                          child: Text(
                            locale.value.forgotPassword,
                            style: primaryTextStyle(
                              size: 12,
                              color: appColorPrimary,
                              decoration: TextDecoration.underline,
                              fontStyle: FontStyle.italic,
                              decorationColor: appColorPrimary,
                            ),
                          ),
                        ),
                      ],
                    ),
                    20.height,
                    Row(
                      children: [
                        Obx(
                          () => AppButton(
                            height: 54,
                            text: signInController.loginSucessfull.isTrue ? locale.value.verify : locale.value.signIn,
                            color: appColorSecondary,
                            textStyle: appButtonTextStyleWhite,
                            onTap: () {
                              if (signInController.loginSucessfull.isTrue) {
                                if (signInController.signInformKey.currentState!.validate()) {
                                  if (signInController.tryToAnother.isTrue) {
                                    signInController.verifyUser(authentication: "google2fa");
                                  } else {
                                    signInController.verifyUser(authentication: "email");
                                  }
                                }
                              } else {
                                if (signInController.signInformKey.currentState!.validate()) {
                                  signInController.signInformKey.currentState!.save();
                                  signInController.saveForm();
                                }
                              }
                            },
                          ).expand(),
                        ),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            if (appConfigs.value.googleLoginStatus == 1)
                              GestureDetector(
                                onTap: () {
                                  signInController.googleSignIn();
                                },
                                child: Container(
                                  height: 54,
                                  width: 54,
                                  padding: const EdgeInsets.all(18),
                                  decoration: boxDecorationWithRoundedCorners(
                                    backgroundColor: bodyWhite.withValues(alpha: 0.1),
                                    boxShape: BoxShape.circle,
                                  ),
                                  child: const GoogleLogoWidget(size: 24),
                                ),
                              ),
                            GestureDetector(
                              onTap: () {
                                signInController.appleSignIn();
                              },
                              child: Container(
                                height: 54,
                                width: 54,
                                padding: const EdgeInsets.all(16),
                                decoration: boxDecorationWithRoundedCorners(
                                  backgroundColor: bodyWhite.withValues(alpha: 0.1),
                                  boxShape: BoxShape.circle,
                                ),
                                child: Image.asset(
                                  Assets.imagesAppleLogo,
                                  color: isDarkMode.value ? null : black,
                                ).center(),
                              ).paddingLeft(16).visible(isApple),
                            ),
                          ],
                        ).paddingLeft(16),
                      ],
                    ),
                    8.height,
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(locale.value.notAMember, style: secondaryTextStyle()),
                        4.width,
                        InkWell(
                          onTap: () {
                            Get.to(() => SignUpScreen());
                            hideKeyboard(context);
                            signInController.signInformKey.currentState?.reset();
                          },
                          child: Text(
                            locale.value.signUp,
                            style: boldTextStyle(
                              size: 12,
                              color: appColorSecondary,
                              decorationColor: appColorSecondary,
                            ),
                          ),
                        ),
                      ],
                    ),
                    16.height,
                  ],
                ),
              ).paddingSymmetric(horizontal: 16),
            ],
          ).paddingOnly(top: Get.height * 0.11),
          Positioned(
            width: Get.width,
            top: -Constants.appLogoSize / 2,
            child: const AppLogoWidget(),
          ),
        ],
      ),
    );
  }
}
