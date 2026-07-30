import 'package:get/get.dart';

import 'package:flutter/material.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../../components/app_scaffold.dart';
import '../../../generated/assets.dart';
import '../../../main.dart';
import '../sign_in_sign_up/password_rule_item.dart';
import 'change_password_controller.dart';

import '../../../utils/colors.dart';
import '../../../utils/common_base.dart';

class ChangePassword extends StatelessWidget {
  ChangePassword({super.key});
  final ChangePassController changePassController = Get.put(ChangePassController());
  final GlobalKey<FormState> _changePassformKey = GlobalKey();

  @override
  Widget build(BuildContext context) {
    return AppScaffold(
      isCenterTitle: true,
      appBartitleText: locale.value.changePassword,
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _changePassformKey,
          autovalidateMode: AutovalidateMode.onUserInteraction,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              32.height,
              SizedBox(
                width: Get.width * 0.8,
                child: Text(
                  locale.value.yourNewPasswordMust,
                  style: secondaryTextStyle(),
                  textAlign: TextAlign.center,
                ),
              ),
              64.height,
              AppTextField(
                title: locale.value.oldPassword,
                textStyle: primaryTextStyle(size: 12),
                controller: changePassController.oldPasswordCont, // Optional
                textFieldType: TextFieldType.PASSWORD, obscureText: true,
                decoration: inputDecoration(context, fillColor: context.cardColor, filled: true, hintText: "${locale.value.eG} #123@156"),
                suffixPasswordVisibleWidget: const Icon(Icons.remove_red_eye_outlined, color: borderColor),
                suffixPasswordInvisibleWidget: commonLeadingWid(imgPath: Assets.iconsIcEyeSlash, color: borderColor).paddingAll(12),
              ),
              16.height,
              Focus(
                onFocusChange: (value) {
                  changePassController.newPasshasFocus(value);
                },
                child: AppTextField(
                  title: locale.value.newPassword,
                  textStyle: primaryTextStyle(size: 12),
                  controller: changePassController.newpasswordCont, // Optional
                  textFieldType: TextFieldType.PASSWORD, obscureText: true,
                  onChanged: (val) => changePassController.checkPasswordRules(val),
                  decoration: inputDecoration(context, fillColor: context.cardColor, filled: true, hintText: "${locale.value.eG}  #123@156"),
                  suffixPasswordVisibleWidget: const Icon(Icons.remove_red_eye_outlined, color: borderColor),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return locale.value.passwordIsRequired;
                    } else if (value.length < 8) {
                      return locale.value.passwordTooShort;
                    } else if (!changePassController.hasSpecial.value || !changePassController.hasNumber.value || !changePassController.hasUppercase.value || !changePassController.hasLetter.value) {
                      return locale.value.passwordDoesNotMeetRequirements;
                    }
                    return null;
                  },
                  suffixPasswordInvisibleWidget: commonLeadingWid(imgPath: Assets.iconsIcEyeSlash, color: borderColor).paddingAll(12),
                ),
              ),
              6.height,
              Obx(
                () => Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    PasswordRuleItem(
                      isValid: changePassController.hasUppercase.value,
                      text: locale.value.passwordMustIncludeAtLeastOneCapitalCharacter,
                    ),
                    PasswordRuleItem(
                      isValid: changePassController.hasLetter.value,
                      text: locale.value.passwordMustIncludeAtLeastOneLowercaseCharacter,
                    ),
                    PasswordRuleItem(
                      isValid: changePassController.hasNumber.value,
                      text: locale.value.passwordMustIncludeAtLeastOneNumber,
                    ),
                    PasswordRuleItem(
                      isValid: changePassController.hasSpecial.value,
                      text: locale.value.passwordMustIncludeSpacialCharacter,
                    ),
                  ],
                ).visible(changePassController.newPasshasFocus.value),
              ),
              16.height,
              AppTextField(
                title: locale.value.confirmNewPassword,
                textStyle: primaryTextStyle(size: 12),
                controller: changePassController.confirmPasswordCont, // Optional
                textFieldType: TextFieldType.PASSWORD, obscureText: true,
                decoration: inputDecoration(context, fillColor: context.cardColor, filled: true, hintText: "${locale.value.eG}  #123@156"),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return locale.value.passwordIsRequired;
                  } else if (value != changePassController.newpasswordCont.text) {
                    return locale.value.yourNewPasswordDoesnT;
                  }
                  return null;
                },
                suffixPasswordVisibleWidget: const Icon(Icons.remove_red_eye_outlined, color: borderColor),
                suffixPasswordInvisibleWidget: commonLeadingWid(imgPath: Assets.iconsIcEyeSlash, color: borderColor).paddingAll(12),
              ),
              64.height,
              AppButton(
                width: Get.width,
                text: locale.value.submit,
                textStyle: appButtonTextStyleWhite,
                onTap: () async {
                  ifNotTester(() async {
                    if (await isNetworkAvailable()) {
                      if (_changePassformKey.currentState!.validate()) {
                        _changePassformKey.currentState!.save();
                        changePassController.saveForm();
                      }
                    } else {
                      toast(locale.value.yourInternetIsNotWorking);
                    }
                  });
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}
