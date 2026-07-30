import 'package:country_picker/country_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../../components/loader_widget.dart';
import '../../../../main.dart';
import '../../../../utils/common_base.dart';
import '../../../components/app_scaffold.dart';
import '../../../generated/assets.dart';
import '../../../utils/colors.dart';
import '../../../utils/constants.dart';
import '../../../utils/empty_error_state_widget.dart';
import 'common_profile_widget.dart';
import 'edit_user_profile_controller.dart';
import '../../../utils/app_common.dart';

class EditUserProfileScreen extends StatelessWidget {
  EditUserProfileScreen({super.key});

  final EditUserProfileController editUserProfileController = Get.put(EditUserProfileController());
  final GlobalKey<FormState> formKey = GlobalKey<FormState>();

  @override
  Widget build(BuildContext context) {
    return AppScaffoldNew(
      appBartitleText: locale.value.editProfile,
      appBarVerticalSize: Get.height * 0.12,
      body: Stack(
        children: [
          SnapHelperWidget(
            errorBuilder: (error) {
              return NoDataWidget(
                title: error,
                retryText: locale.value.reload,
                imageWidget: const ErrorStateWidget(),
                onRetry: () {
                  editUserProfileController.getUserDetails();
                },
              ).paddingSymmetric(horizontal: 16);
            },
            future: editUserProfileController.userDataFuture.value,
            onSuccess: (data) {
              return RefreshIndicator(
                onRefresh: () async {
                  await editUserProfileController.init();
                },
                child: SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  child: Form(
                    key: formKey,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        16.height,
                        Obx(
                          () => ProfilePicWidget(
                            heroTag: editUserProfileController.imageFile.value.path.isNotEmpty ? editUserProfileController.imageFile.value.path : loginUserData.value.profileImage,
                            profileImage: editUserProfileController.imageFile.value.path.isNotEmpty ? editUserProfileController.imageFile.value.path : loginUserData.value.profileImage,
                            firstName: loginUserData.value.firstName,
                            lastName: loginUserData.value.lastName,
                            userName: loginUserData.value.userName,
                            showOnlyPhoto: true,
                            onCameraTap: () {
                              hideKeyboard(context);
                              editUserProfileController.showBottomSheet(context);
                            },
                            onPicTap: () {
                              hideKeyboard(context);
                              editUserProfileController.showBottomSheet(context);
                            },
                          ),
                        ),
                        32.height,
                        AppTextField(
                          textStyle: primaryTextStyle(size: 12),
                          controller: editUserProfileController.fNameCont,
                          focus: editUserProfileController.fNameFocus,
                          nextFocus: editUserProfileController.lNameFocus,
                          textFieldType: TextFieldType.NAME,
                          errorThisFieldRequired: locale.value.thisFieldIsRequired,
                          validator: (value) {
                            if (value == null || value.trim().isEmpty) {
                              return locale.value.thisFieldIsRequired;
                            }
                            return null;
                          },
                          onChanged: (value) {
                            if (value.trim().isNotEmpty) formKey.currentState?.validate();
                          },
                          decoration: inputDecoration(
                            context,
                            labelText: locale.value.firstName,
                            hintText: "${locale.value.eG} ${locale.value.merry}",
                            fillColor: context.cardColor,
                            filled: true,
                          ),
                          suffix: commonLeadingWid(
                            imgPath: Assets.navigationIcUserOutlined,
                            color: secondaryTextColor,
                            size: 12,
                          ).paddingAll(16),
                        ),
                        16.height,
                        AppTextField(
                          textStyle: primaryTextStyle(size: 12),
                          controller: editUserProfileController.lNameCont,
                          focus: editUserProfileController.lNameFocus,
                          nextFocus: editUserProfileController.emailFocus,
                          textFieldType: TextFieldType.NAME,
                          errorThisFieldRequired: locale.value.thisFieldIsRequired,
                          validator: (value) {
                            if (value == null || value.trim().isEmpty) {
                              return locale.value.thisFieldIsRequired;
                            }
                            return null;
                          },
                          onChanged: (value) {
                            if (value.trim().isNotEmpty) formKey.currentState?.validate();
                          },
                          decoration: inputDecoration(
                            context,
                            labelText: locale.value.lastName,
                            hintText: "${locale.value.eG} ${locale.value.doe}",
                            fillColor: context.cardColor,
                            filled: true,
                          ),
                          suffix: commonLeadingWid(
                            imgPath: Assets.navigationIcUserOutlined,
                            color: secondaryTextColor,
                            size: 12,
                          ).paddingAll(16),
                        ),
                        16.height,
                        AppTextField(
                          textStyle: primaryTextStyle(size: 12),
                          controller: editUserProfileController.emailCont,
                          focus: editUserProfileController.emailFocus,
                          nextFocus: editUserProfileController.mobileFocus,
                          readOnly: loginUserData.value.isSocialLoginType,
                          textFieldType: TextFieldType.EMAIL_ENHANCED,
                          errorThisFieldRequired: locale.value.thisFieldIsRequired,
                          errorInvalidEmail: locale.value.pleaseEnterValidEmail,
                          validator: (value) {
                            if (value == null || value.trim().isEmpty) {
                              return locale.value.thisFieldIsRequired;
                            }
                            if (!value.isEmail) {
                              return locale.value.pleaseEnterValidEmail;
                            }
                            return null;
                          },
                          onChanged: (value) {
                            if (value.trim().isNotEmpty && value.isEmail) formKey.currentState?.validate();
                          },
                          decoration: inputDecoration(
                            context,
                            labelText: locale.value.email,
                            fillColor: context.cardColor,
                            filled: true,
                          ),
                          suffix: commonLeadingWid(
                            imgPath: Assets.iconsIcMail,
                            color: secondaryTextColor,
                            size: 12,
                          ).paddingAll(16),
                        ),
                        16.height,
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Obx(
                              () => AppTextField(
                                textStyle: primaryTextStyle(size: 12),
                                textFieldType: TextFieldType.OTHER,
                                controller: TextEditingController(
                                  text: " +${editUserProfileController.pickedPhoneCode.value.phoneCode}",
                                ),
                                focus: editUserProfileController.phoneCodeFocus,
                                nextFocus: editUserProfileController.mobileFocus,
                                readOnly: true,
                                onTap: () {
                                  pickCountry(
                                    context,
                                    onSelect: (Country country) {
                                      editUserProfileController.pickedPhoneCode(country);
                                      editUserProfileController.phoneCodeCont.text = editUserProfileController.pickedPhoneCode.value.phoneCode;
                                    },
                                  );
                                },
                                textAlign: TextAlign.center,
                                decoration: inputDecoration(
                                  context,
                                  prefixIcon: Text(
                                    editUserProfileController.pickedPhoneCode.value.flagEmoji,
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
                              controller: editUserProfileController.mobileCont,
                              focus: editUserProfileController.mobileFocus,
                              errorThisFieldRequired: locale.value.thisFieldIsRequired,
                              keyboardType: TextInputType.phone,
                              inputFormatters: [
                                FilteringTextInputFormatter.digitsOnly,
                              ],
                              validator: (value) {
                                if (value == null || value.trim().isEmpty) {
                                  return locale.value.thisFieldIsRequired;
                                }
                                if (value.length < 10) {
                                  return locale.value.pleaseEnterValidPhoneNumber;
                                }
                                return null;
                              },
                              onChanged: (value) {
                                if (value.trim().isNotEmpty && value.length >= 10) formKey.currentState?.validate();
                              },
                              decoration: inputDecoration(
                                context,
                                labelText: locale.value.contactNumber,
                                fillColor: context.cardColor,
                                filled: true,
                              ),
                              suffix: commonLeadingWid(
                                imgPath: Assets.iconsIcCall,
                                color: secondaryTextColor,
                                size: 12,
                              ).paddingAll(16),
                            ).expand(flex: 8),
                          ],
                        ),
                        16.height,
                        AppTextField(
                          isValidationRequired: false,
                          textStyle: primaryTextStyle(size: 12),
                          textFieldType: TextFieldType.MULTILINE,
                          controller: editUserProfileController.addressCont,
                          focus: editUserProfileController.addressFocus,
                          decoration: inputDecoration(
                            context,
                            labelText: locale.value.address,
                            hintText: "${locale.value.eG} 123, ${locale.value.mainStreet}",
                            fillColor: context.cardColor,
                            filled: true,
                          ),
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
                                        editUserProfileController.selectedGender(genders[index]);
                                        loginUserData.value.gender = editUserProfileController.selectedGender.value.slug;
                                      },
                                      borderRadius: radius(),
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                                        decoration: boxDecorationDefault(
                                          color: editUserProfileController.selectedGender.value.id == genders[index].id ? appColorPrimary : context.cardColor,
                                        ),
                                        child: Text(
                                          genders[index].name,
                                          style: secondaryTextStyle(
                                            color: editUserProfileController.selectedGender.value.id == genders[index].id ? white : null,
                                          ),
                                        ),
                                      ),
                                    ),
                                  );
                                },
                              ),
                            ),
                            16.height,
                            Text(locale.value.dateOfBirth, style: primaryTextStyle()),
                            AppTextField(
                              controller: editUserProfileController.dateOfBirthCont,
                              textStyle: primaryTextStyle(size: 12),
                              textFieldType: TextFieldType.OTHER,
                              errorThisFieldRequired: locale.value.thisFieldIsRequired,
                              validator: (value) {
                                if (value == null || value.trim().isEmpty) {
                                  return locale.value.thisFieldIsRequired;
                                }
                                return null;
                              },
                              readOnly: true,
                              onTap: () async {
                                await editUserProfileController.pickDate(context);
                                formKey.currentState?.validate();
                              },
                              decoration: inputDecoration(
                                context,
                                hintText: DateFormatConst.yyyy_MM_dd,
                                fillColor: context.cardColor,
                                filled: true,
                              ),
                              suffix: const Icon(
                                Icons.calendar_today,
                                color: secondaryTextColor,
                              ).paddingAll(16),
                            ),
                          ],
                        ),
                        32.height,
                        AppButton(
                          width: Get.width,
                          text: locale.value.update,
                          textStyle: appButtonTextStyleWhite,
                          onTap: () async {
                            if (formKey.currentState!.validate()) {
                              editUserProfileController.updateUserProfile();
                            }
                          },
                        ),
                        24.height,
                      ],
                    ),
                  ).paddingSymmetric(horizontal: 16),
                ),
              );
            },
            loadingWidget: const LoaderWidget(),
          ),
          Obx(() => const LoaderWidget().visible(editUserProfileController.isLoading.value)),
        ],
      ),
    );
  }
}
