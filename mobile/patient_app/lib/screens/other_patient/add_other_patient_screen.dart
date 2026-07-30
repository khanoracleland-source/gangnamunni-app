import 'package:country_picker/country_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../components/app_scaffold.dart';
import '../../components/loader_widget.dart';
import '../../generated/assets.dart';
import '../../main.dart';
import '../../utils/colors.dart';
import '../../utils/common_base.dart';
import '../../utils/constants.dart';
import '../auth/model/login_response.dart';
import '../auth/profile/common_profile_widget.dart';
import 'add_other_patient_controller.dart';

class AddOtherPatientScreen extends StatelessWidget {
  final UserData memberData;
  final String titleText;

  AddOtherPatientScreen({super.key, required this.memberData, required this.titleText});

  final AddOtherPatientController addOtherPatientController = Get.put(AddOtherPatientController());

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: titleText,
      appBarVerticalSize: Get.height * 0.12,
      body: Stack(
        children: [
          SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Form(
              key: addOtherPatientController.addMemberFormKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  16.height,
                  Obx(
                    () => ProfilePicWidget(
                      heroTag: addOtherPatientController.imageFile.value.path.isNotEmpty ? addOtherPatientController.imageFile.value.path : memberData.profileImage,
                      profileImage: addOtherPatientController.imageFile.value.path.isNotEmpty ? addOtherPatientController.imageFile.value.path : memberData.profileImage,
                      firstName: memberData.firstName,
                      lastName: memberData.lastName,
                      picSize: 120,
                      showOnlyPhoto: true,
                      onCameraTap: () {
                        addOtherPatientController.showBottomSheet(context);
                      },
                      onPicTap: () {
                        addOtherPatientController.showBottomSheet(context);
                      },
                    ),
                  ),
                  32.height,
                  AppTextField(
                    textStyle: primaryTextStyle(size: 12),
                    controller: addOtherPatientController.fNameCont,
                    focus: addOtherPatientController.fNameFocus,
                    nextFocus: addOtherPatientController.lNameFocus,
                    textFieldType: TextFieldType.NAME,
                    decoration: inputDecoration(
                      context,
                      labelText: locale.value.firstName,
                      hintText: "${locale.value.eG}  ${locale.value.merry}",
                      fillColor: context.cardColor,
                      filled: true,
                    ),
                    suffix: commonLeadingWid(imgPath: Assets.navigationIcUserOutlined, color: secondaryTextColor, size: 12).paddingAll(16),
                  ),
                  16.height,
                  AppTextField(
                    textStyle: primaryTextStyle(size: 12),
                    controller: addOtherPatientController.lNameCont,
                    focus: addOtherPatientController.lNameFocus,
                    nextFocus: addOtherPatientController.mobileFocus,
                    textFieldType: TextFieldType.NAME,
                    decoration: inputDecoration(
                      context,
                      labelText: locale.value.lastName,
                      hintText: "${locale.value.eG}  ${locale.value.doe}",
                      fillColor: context.cardColor,
                      filled: true,
                    ),
                    suffix: commonLeadingWid(imgPath: Assets.navigationIcUserOutlined, color: secondaryTextColor, size: 12).paddingAll(16),
                  ),
                  16.height,
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Obx(
                        () => AppTextField(
                          textStyle: primaryTextStyle(size: 12),
                          textFieldType: TextFieldType.OTHER,
                          controller: TextEditingController(text: "  +${addOtherPatientController.pickedPhoneCode.value.phoneCode}"),
                          focus: addOtherPatientController.phoneCodeFocus,
                          nextFocus: addOtherPatientController.mobileFocus,
                          errorThisFieldRequired: locale.value.thisFieldIsRequired,
                          readOnly: true,
                          onTap: () {
                            pickCountry(
                              context,
                              onSelect: (Country country) {
                                addOtherPatientController.pickedPhoneCode(country);
                                addOtherPatientController.phoneCodeCont.text = addOtherPatientController.pickedPhoneCode.value.phoneCode;
                              },
                            );
                          },
                          textAlign: TextAlign.center,
                          decoration: inputDecoration(
                            context,
                            hintText: "",
                            prefixIcon: Text(
                              addOtherPatientController.pickedPhoneCode.value.flagEmoji,
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
                      ).expand(flex: 4),
                      16.width,
                      AppTextField(
                        textStyle: primaryTextStyle(size: 12),
                        textFieldType: TextFieldType.PHONE,
                        controller: addOtherPatientController.mobileCont,
                        focus: addOtherPatientController.mobileFocus,
                        errorThisFieldRequired: locale.value.thisFieldIsRequired,
                        keyboardType: TextInputType.phone,
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                        ],
                        decoration: inputDecoration(
                          context,
                          labelText: locale.value.contactNumber,
                          fillColor: context.cardColor,
                          filled: true,
                        ),
                        suffix: commonLeadingWid(imgPath: Assets.iconsIcCall, color: secondaryTextColor, size: 12).paddingAll(16),
                      ).expand(flex: 8),
                    ],
                  ),
                  16.height,
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(locale.value.dateOfBirth, style: primaryTextStyle()),
                      8.height,
                      AppTextField(
                        controller: addOtherPatientController.dateOfBirthCont,
                        textStyle: primaryTextStyle(size: 12),
                        textFieldType: TextFieldType.OTHER,
                        errorThisFieldRequired: locale.value.birthdateIsRequired,
                        validator: (value) {
                          if (addOtherPatientController.dateOfBirthCont.text.isEmpty) {
                            return locale.value.birthdateIsRequired;
                          } else {
                            return null;
                          }
                        },
                        onTap: () {
                          addOtherPatientController.pickDate(context);
                        },
                        decoration: inputDecoration(
                          context,
                          hintText: DateFormatConst.yyyy_MM_dd,
                          fillColor: context.cardColor,
                          filled: true,
                        ),
                        suffix: commonLeadingWid(
                          imgPath: Assets.iconsIcCake,
                          color: secondaryTextColor,
                          size: 12,
                        ).paddingAll(16),
                      ),
                    ],
                  ),
                  16.height,
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
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
                                  addOtherPatientController.selectedGender(genders[index]);
                                },
                                borderRadius: radius(),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                                  decoration: boxDecorationDefault(
                                    borderRadius: radius(24),
                                    color: addOtherPatientController.selectedGender.value.id == genders[index].id ? appColorPrimary : context.cardColor,
                                  ),
                                  child: Text(
                                    getOtherPatientGender(gender: genders[index].name),
                                    style: secondaryTextStyle(
                                      color: addOtherPatientController.selectedGender.value.id == genders[index].id ? white : null,
                                    ),
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                    ],
                  ),
                  16.height,
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(locale.value.relation, style: primaryTextStyle()),
                      8.height,
                      Obx(
                        () => AnimatedWrap(
                          itemCount: relation.length,
                          spacing: 16,
                          runSpacing: 16,
                          itemBuilder: (context, index) {
                            return Obx(
                              () => InkWell(
                                onTap: () {
                                  addOtherPatientController.selectedRelation(relation[index]);
                                },
                                borderRadius: radius(),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                                  decoration: boxDecorationDefault(
                                    borderRadius: radius(24),
                                    color: addOtherPatientController.selectedRelation.value.id == relation[index].id ? appColorPrimary : context.cardColor,
                                  ),
                                  child: Text(
                                    getOtherPatientRelation(relation: relation[index].name),
                                    style: secondaryTextStyle(
                                      color: addOtherPatientController.selectedRelation.value.id == relation[index].id ? white : null,
                                    ),
                                  ),
                                ),
                              ),
                            );
                          },
                        ),
                      ),
                    ],
                  ),
                  32.height,
                  AppButton(
                    width: Get.width,
                    text: locale.value.save,
                    textStyle: appButtonTextStyleWhite,
                    onTap: () async {
                      hideKeyboard(context);
                      addOtherPatientController.handleAddOtherPatient();
                    },
                  ),
                  24.height,
                ],
              ),
            ),
          ),
          Obx(() => const LoaderWidget().visible(addOtherPatientController.isLoading.value)),
        ],
      ),
    );
  }
}
