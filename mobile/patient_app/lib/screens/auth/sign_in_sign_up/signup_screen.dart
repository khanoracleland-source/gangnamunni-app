import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:kivicare_patient/components/app_logo_widget.dart';
import 'package:kivicare_patient/utils/constants.dart';

import '../../../components/app_scaffold.dart';
import '../../../configs.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../../../utils/app_common.dart';
import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';
import 'password_rule_item.dart';
import 'sign_up_controller.dart';
import 'package:country_picker/country_picker.dart';

class SignUpScreen extends StatelessWidget {
  SignUpScreen({super.key});

  final SignUpController signUpController = Get.put(SignUpController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      isLoading: signUpController.isLoading,
      hasLeadingWidget: false,
      clipBehaviorSplitRegion: Clip.none,
      body: Stack(
        clipBehavior: Clip.none,
        children: [
          AnimatedScrollView(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Text(
                locale.value.createYourAccount,
                style: primaryTextStyle(size: 24),
              ),
              8.height,
              Text(
                locale.value.registerYourAccountForBetterExperience,
                style: secondaryTextStyle(size: 14),
              ),
              SizedBox(height: Get.height * 0.05),
              Form(
                key: signUpController.signUpformKey,
                autovalidateMode: AutovalidateMode.onUnfocus,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    16.height,
                    AppTextField(
                      textStyle: primaryTextStyle(size: 12),
                      controller: signUpController.firstNameCont,
                      focus: signUpController.fisrtNameFocus,
                      nextFocus: signUpController.lastNameFocus,
                      textFieldType: TextFieldType.NAME,
                      decoration: inputDecoration(
                        context,
                        labelText: locale.value.firstName,
                        fillColor: context.cardColor,
                        filled: true,
                        hintText: "${locale.value.eG} ${locale.value.merry}",
                      ),
                      suffix: commonLeadingWid(imgPath: Assets.navigationIcUserOutlined, size: 14).paddingAll(14),
                    ),
                    16.height,
                    AppTextField(
                      textStyle: primaryTextStyle(size: 12),
                      controller: signUpController.lastNameCont,
                      focus: signUpController.lastNameFocus,
                      nextFocus: signUpController.emailFocus,
                      textFieldType: TextFieldType.NAME,
                      decoration: inputDecoration(
                        labelText: locale.value.lastName,
                        context,
                        fillColor: context.cardColor,
                        filled: true,
                        hintText: "${locale.value.eG}  ${locale.value.doe}",
                      ),
                      suffix: commonLeadingWid(imgPath: Assets.navigationIcUserOutlined, size: 14).paddingAll(14),
                    ),
                    16.height,
                    AppTextField(
                      textStyle: primaryTextStyle(size: 12),
                      controller: signUpController.emailCont,
                      focus: signUpController.emailFocus,
                      nextFocus: signUpController.dateFocus,
                      textFieldType: TextFieldType.EMAIL_ENHANCED,
                      decoration: inputDecoration(
                        labelText: locale.value.email,
                        context,
                        fillColor: context.cardColor,
                        filled: true,
                        hintText: "${locale.value.eG} merry_456@gmail.com",
                      ),
                      suffix: commonLeadingWid(imgPath: Assets.iconsIcMail, size: 14).paddingAll(14),
                    ),
                    16.height,
                    AppTextField(
                      readOnly: true,
                      onTap: () async {
                        final DateTime? pickedDate = await showDatePicker(
                          context: getContext,
                          firstDate: DateTime(1900),
                          lastDate: DateTime.now(),
                          initialDate: DateTime.now(),
                        );

                        if (pickedDate != null) {
                          signUpController.dateCont.text = pickedDate.formatDateYYYYmmdd();
                        }
                      },
                      textStyle: primaryTextStyle(size: 12),
                      controller: signUpController.dateCont,
                      focus: signUpController.dateFocus,
                      nextFocus: signUpController.mobileFocus,
                      textFieldType: TextFieldType.PHONE,
                      decoration: inputDecoration(
                        labelText: locale.value.dateOfBirth,
                        context,
                        fillColor: context.cardColor,
                        filled: true,
                        hintText: "${locale.value.eG} 30-02-1999",
                      ),
                      suffix: commonLeadingWid(imgPath: Assets.iconsIcCalendar, size: 14).paddingAll(14),
                    ),
                    16.height,
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Obx(
                          () => AppTextField(
                            textStyle: primaryTextStyle(size: 12),
                            textFieldType: TextFieldType.OTHER,
                            controller: TextEditingController(text: "  +${signUpController.pickedPhoneCode.value.phoneCode}"),
                            focus: signUpController.mobileFocus,
                            nextFocus: signUpController.phoneFocus,
                            errorThisFieldRequired: locale.value.thisFieldIsRequired,
                            readOnly: true,
                            onTap: () {
                              pickCountry(
                                context,
                                onSelect: (Country country) {
                                  signUpController.pickedPhoneCode(country);
                                },
                              );
                            },
                            textAlign: TextAlign.center,
                            decoration: inputDecoration(
                              context,
                              hintText: signUpController.pickedPhoneCode.value.phoneCode.isNotEmpty ? "+${signUpController.pickedPhoneCode.value.phoneCode}" : "+91",
                              prefixIcon: Text(
                                signUpController.pickedPhoneCode.value.flagEmoji,
                              ).paddingOnly(top: 2, left: 8),
                              prefixIconConstraints: BoxConstraints.tight(const Size(24, 24)),
                              suffixIcon: const Icon(
                                Icons.keyboard_arrow_down_rounded,
                                color: dividerColor,
                                size: 22,
                              ).paddingOnly(right: 32),
                              suffixIconConstraints: BoxConstraints.tight(const Size(32, 24)),
                              fillColor: context.cardColor,
                              filled: true,
                            ),
                          ),
                        ).expand(flex: 3),
                        16.width,
                        AppTextField(
                          textStyle: primaryTextStyle(size: 12),
                          textFieldType: TextFieldType.PHONE,
                          controller: signUpController.phoneCont,
                          focus: signUpController.mobileFocus,
                          errorThisFieldRequired: locale.value.thisFieldIsRequired,
                          decoration: inputDecoration(
                            labelText: locale.value.phoneNumber,
                            context,
                            hintText: "${locale.value.eG} 1234567890",
                            fillColor: context.cardColor,
                            filled: true,
                          ),
                        ).expand(flex: 8),
                      ],
                    ).paddingTop(8),
                    16.height,
                    Text(locale.value.gender, style: primaryTextStyle()),
                    8.height,
                    Obx(
                      () => HorizontalList(
                        itemCount: genders.length,
                        spacing: 16,
                        runSpacing: 16,
                        padding: EdgeInsets.zero,
                        itemBuilder: (context, index) {
                          return Obx(
                            () => InkWell(
                              onTap: () {
                                signUpController.selectedGender(genders[index]);
                                loginUserData.value.gender = signUpController.selectedGender.value.slug;
                              },
                              borderRadius: radius(),
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                                decoration: boxDecorationDefault(
                                  color: signUpController.selectedGender.value.id == genders[index].id ? appColorPrimary : context.cardColor,
                                ),
                                child: Text(
                                  genders[index].name,
                                  style: secondaryTextStyle(
                                    color: signUpController.selectedGender.value.id == genders[index].id ? white : null,
                                  ),
                                ),
                              ),
                            ),
                          );
                        },
                      ),
                    ),
                    16.height,
                    Focus(
                      onFocusChange: (value) {
                        signUpController.passContHasFocus(value);
                      },
                      child: AppTextField(
                        textStyle: primaryTextStyle(size: 12),
                        controller: signUpController.passwordCont,
                        focus: signUpController.passwordFocus,
                        textFieldType: TextFieldType.PASSWORD,
                        obscureText: true,
                        onChanged: (val) => signUpController.checkPasswordRules(val),
                        decoration: inputDecoration(
                          labelText: locale.value.password,
                          context,
                          fillColor: context.cardColor,
                          filled: true,
                          hintText: "${locale.value.eG} #1234@1567",
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return locale.value.passwordIsRequired;
                          } else if (value.length < 8) {
                            return locale.value.passwordTooShort;
                          } else if (!signUpController.hasSpecial.value || !signUpController.hasNumber.value || !signUpController.hasUppercase.value || !signUpController.hasLetter.value) {
                            return locale.value.passwordDoesNotMeetRequirements;
                          }
                          return null;
                        },
                        suffixPasswordVisibleWidget: commonLeadingWid(imgPath: Assets.iconsIcEye, size: 14).paddingAll(12),
                        suffixPasswordInvisibleWidget: commonLeadingWid(imgPath: Assets.iconsIcEyeSlash, size: 14).paddingAll(12),
                      ),
                    ),
                    6.height,
                    Obx(
                      () => Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          PasswordRuleItem(
                            isValid: signUpController.hasUppercase.value,
                            text: locale.value.passwordMustIncludeAtLeastOneCapitalCharacter,
                          ),
                          PasswordRuleItem(
                            isValid: signUpController.hasLetter.value,
                            text: locale.value.passwordMustIncludeAtLeastOneLowercaseCharacter,
                          ),
                          PasswordRuleItem(
                            isValid: signUpController.hasNumber.value,
                            text: locale.value.passwordMustIncludeAtLeastOneNumber,
                          ),
                          PasswordRuleItem(
                            isValid: signUpController.hasSpecial.value,
                            text: locale.value.passwordMustIncludeSpacialCharacter,
                          ),
                        ],
                      ).visible(
                        signUpController.passContHasFocus.value && (!signUpController.hasUppercase.value || !signUpController.hasLetter.value || !signUpController.hasNumber.value || !signUpController.hasSpecial.value),
                      ),
                    ),
                    16.height,
                    Column(
                      children: [
                        Obx(
                          () => Row(
                            children: [
                              Checkbox(
                                checkColor: whiteColor,
                                value: signUpController.isAcceptedTc.value,
                                activeColor: appColorPrimary,
                                visualDensity: VisualDensity.compact,
                                materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                splashRadius: 0,
                                shape: RoundedRectangleBorder(borderRadius: radius(0)),
                                side: const BorderSide(color: secondaryTextColor, width: 1.5),
                                onChanged: (val) async {
                                  signUpController.isAcceptedTc.value = !signUpController.isAcceptedTc.value;
                                },
                              ),
                              8.width,
                              RichTextWidget(
                                list: [
                                  TextSpan(text: "${locale.value.iAgreeToThe} ", style: secondaryTextStyle()),
                                  TextSpan(
                                    text: locale.value.termsConditions,
                                    style: primaryTextStyle(color: appColorPrimary, size: 12, decoration: TextDecoration.underline, decorationColor: appColorPrimary),
                                    recognizer: TapGestureRecognizer()
                                      ..onTap = () {
                                        commonLaunchUrl(TERMS_CONDITION_URL, launchMode: LaunchMode.externalApplication);
                                      },
                                  ),
                                  TextSpan(text: " ${locale.value.and} ", style: secondaryTextStyle()),
                                  TextSpan(
                                    text: locale.value.privacyPolicy,
                                    style: primaryTextStyle(color: appColorPrimary, size: 12, decoration: TextDecoration.underline, decorationColor: appColorPrimary),
                                    recognizer: TapGestureRecognizer()
                                      ..onTap = () {
                                        commonLaunchUrl(PRIVACY_POLICY_URL, launchMode: LaunchMode.externalApplication);
                                      },
                                  ),
                                ],
                              ).expand(),
                            ],
                          ),
                        ),
                      ],
                    ),
                    16.height,
                    AppButton(
                      width: Get.width,
                      text: locale.value.signUp,
                      color: appColorSecondary,
                      textStyle: appButtonTextStyleWhite,
                      shapeBorder: RoundedRectangleBorder(borderRadius: radius(defaultAppButtonRadius / 2)),
                      onTap: () {
                        if (signUpController.signUpformKey.currentState!.validate()) {
                          signUpController.signUpformKey.currentState!.save();
                          signUpController.saveForm();
                        }
                      },
                    ),
                  ],
                ),
              ).paddingSymmetric(horizontal: 16),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(locale.value.alreadyHaveAnAccount, style: secondaryTextStyle()),
                  4.width,
                  InkWell(
                    onTap: () {
                      Get.back();
                    },
                    child: Text(
                      locale.value.signIn,
                      style: boldTextStyle(
                        size: 12,
                        color: appColorSecondary,
                        decorationColor: appColorSecondary,
                      ),
                    ),
                  ),
                ],
              ).paddingOnly(top: 16),
            ],
          ).paddingOnly(top: Get.height * 0.11, bottom: 16),
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
