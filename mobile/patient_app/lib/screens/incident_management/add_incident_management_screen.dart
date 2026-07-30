import 'package:country_picker/country_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/utils/app_common.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../components/app_scaffold.dart';
import '../../components/loader_widget.dart';
import '../../generated/assets.dart';
import '../../main.dart';
import '../../utils/colors.dart';
import '../../utils/common_base.dart';
import 'incident_management_controller.dart';

class AddIncidentManagement extends StatelessWidget {
  AddIncidentManagement({super.key});

  final IncidentManagement controller = Get.put(IncidentManagement());
  final GlobalKey<FormState> formKey = GlobalKey<FormState>();

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.incidentManagement,
      appBarVerticalSize: Get.mediaQuery.size.height * 0.12,
      body: Stack(
        children: [
          Column(
            children: [
              Expanded(
                child: SingleChildScrollView(
                  child: Form(
                    key: formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        16.height,
                        AppTextField(
                          textStyle: primaryTextStyle(size: 12),
                          controller: controller.titleCont,
                          focus: controller.titleFocus,
                          nextFocus: controller.desFocus,
                          textFieldType: TextFieldType.NAME,
                          errorThisFieldRequired: locale.value.thisFieldIsRequired,
                          decoration: inputDecoration(
                            context,
                            hintText: locale.value.title,
                            fillColor: context.cardColor,
                            filled: true,
                          ),
                          suffix: commonLeadingWid(imgPath: Assets.iconsIcNotebook, color: secondaryTextColor, size: 12).paddingAll(16),
                        ),
                        16.height,
                        AppTextField(
                          textStyle: primaryTextStyle(size: 12),
                          textFieldType: TextFieldType.MULTILINE,
                          controller: controller.desCont,
                          maxLength: 250,
                          minLines: 5,
                          focus: controller.desFocus,
                          errorThisFieldRequired: locale.value.thisFieldIsRequired,
                          decoration: inputDecoration(
                            context,
                            hintText: locale.value.enterYourDetailDescriptionForYourComplaint,
                            fillColor: context.cardColor,
                            filled: true,
                          ),
                        ),
                        16.height,
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Obx(
                              () => AppTextField(
                                textStyle: primaryTextStyle(size: 12),
                                textFieldType: TextFieldType.OTHER,
                                controller: TextEditingController(text: " +${controller.pickedPhoneCode.value.phoneCode}"),
                                focus: controller.phoneCodeFocus,
                                nextFocus: controller.mobileFocus,
                                errorThisFieldRequired: locale.value.thisFieldIsRequired,
                                readOnly: true,
                                onTap: () {
                                  pickCountry(
                                    context,
                                    onSelect: (Country country) {
                                      controller.pickedPhoneCode(country);
                                      controller.phoneCodeCont.text = controller.pickedPhoneCode.value.phoneCode;
                                    },
                                  );
                                },
                                textAlign: TextAlign.center,
                                decoration: inputDecoration(
                                  context,
                                  hintText: "",
                                  prefixIcon: Text(
                                    controller.pickedPhoneCode.value.flagEmoji,
                                  ).paddingOnly(top: 2, left: 8),
                                  prefixIconConstraints: BoxConstraints.tight(const Size(24, 24)),
                                  suffixIcon: const Icon(
                                    Icons.keyboard_arrow_down_rounded,
                                    color: dividerColor,
                                    size: 22,
                                  ).paddingOnly(right: 32),
                                  suffixIconConstraints: BoxConstraints.tight(const Size(24, 24)),
                                  fillColor: context.cardColor,
                                  filled: true,
                                ),
                              ),
                            ).expand(flex: 3),
                            16.width,
                            AppTextField(
                              textStyle: primaryTextStyle(size: 12),
                              textFieldType: TextFieldType.PHONE,
                              controller: controller.mobileCont,
                              focus: controller.mobileFocus,
                              errorThisFieldRequired: locale.value.thisFieldIsRequired,
                              keyboardType: TextInputType.phone,
                              inputFormatters: [
                                FilteringTextInputFormatter.allow(RegExp('[0-9]')),
                              ],
                              decoration: inputDecoration(
                                context,
                                hintText: locale.value.phoneNumber,
                                fillColor: context.cardColor,
                                filled: true,
                              ),
                              suffix: commonLeadingWid(imgPath: Assets.iconsIcCall, color: secondaryTextColor, size: 12).paddingAll(16),
                            ).expand(flex: 8),
                          ],
                        ),
                        16.height,
                        AppTextField(
                          textStyle: primaryTextStyle(size: 12),
                          controller: controller.emailCont,
                          focus: controller.emailFocus,
                          nextFocus: controller.mobileFocus,
                          textFieldType: TextFieldType.EMAIL_ENHANCED,
                          errorThisFieldRequired: locale.value.thisFieldIsRequired,
                          errorInvalidEmail: locale.value.pleaseEnterValidEmail,
                          decoration: inputDecoration(
                            context,
                            hintText: locale.value.email,
                            fillColor: context.cardColor,
                            filled: true,
                          ),
                          suffix: commonLeadingWid(imgPath: Assets.iconsIcMail, color: secondaryTextColor, size: 12).paddingAll(16),
                        ),
                        16.height,
                        AppTextField(
                          textStyle: primaryTextStyle(size: 12),
                          textFieldType: TextFieldType.USERNAME,
                          controller: controller.imageTitleCont,
                          focus: controller.imageTitleFocus,
                          onTap: () => controller.pickImage(),
                          readOnly: true,
                          errorThisFieldRequired: locale.value.thisFieldIsRequired,
                          decoration: inputDecoration(
                            suffixIcon: Container(
                              decoration: BoxDecoration(
                                borderRadius: const BorderRadius.only(bottomRight: Radius.circular(5), topRight: Radius.circular(5)),
                                color: isDarkMode.value ? Colors.grey[600] : Colors.grey[300],
                              ),
                              width: 75,
                              child: Center(
                                child: Text(
                                  locale.value.browse,
                                  style: secondaryTextStyle(),
                                ),
                              ),
                            ),
                            context,
                            hintText: locale.value.chooseImage,
                            fillColor: context.cardColor,
                            filled: true,
                          ),
                        ),
                        16.height,
                      ],
                    ).paddingSymmetric(horizontal: 16),
                  ),
                ),
              ),
              Align(
                alignment: Alignment.bottomCenter,
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: AppButton(
                    width: Get.width,
                    color: appColorSecondary,
                    text: locale.value.submit,
                    textStyle: appButtonTextStyleWhite,
                    onTap: () async {
                      if (formKey.currentState!.validate()) {
                        controller
                            .submitAPI(
                          title: controller.titleCont.text,
                          description: controller.desCont.text,
                          phoneCode: controller.phoneCodeCont.text,
                          mobileNumber: controller.mobileCont.text,
                          email: controller.emailCont.text,
                          imageFile: controller.imageFile.value,
                        )
                            .then(
                          (value) async {
                            controller.clearTextFields();
                            await controller.getIncidents();
                            Get.back();
                          },
                        ); //image:  controller.imageTitleCont.text,
                      }
                    },
                  ),
                ),
              ),
            ],
          ),
          Obx(() => const LoaderWidget().visible(controller.isLoading.value)),
        ],
      ),
    );
  }
}
