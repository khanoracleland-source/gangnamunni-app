// ignore_for_file: depend_on_referenced_packages

import 'package:flutter/material.dart';
import 'package:country_picker/country_picker.dart';
import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../main.dart';
import '../../../utils/common_base.dart';
import '../../../utils/constants.dart';
import '../../../api/auth_apis.dart';
import '../model/common_model.dart';
import 'sign_in_controller.dart';

class SignUpController extends GetxController {
  RxBool isLoading = false.obs;
  final GlobalKey<FormState> signUpformKey = GlobalKey();

  RxBool agree = false.obs;
  RxBool isAcceptedTc = false.obs;
  Rx<CMNModel> selectedGender = CMNModel().obs;
  TextEditingController emailCont = TextEditingController();
  TextEditingController firstNameCont = TextEditingController();
  TextEditingController lastNameCont = TextEditingController();
  TextEditingController passwordCont = TextEditingController();
  TextEditingController dateCont = TextEditingController();
  TextEditingController phoneCont = TextEditingController();

  FocusNode emailFocus = FocusNode();
  FocusNode fisrtNameFocus = FocusNode();
  FocusNode lastNameFocus = FocusNode();
  FocusNode passwordFocus = FocusNode();
  FocusNode dateFocus = FocusNode();
  FocusNode phoneFocus = FocusNode();
  FocusNode mobileFocus = FocusNode();

  Rx<Country> pickedPhoneCode = defaultCountry.obs;

  RxBool passContHasFocus = false.obs;

  RxBool hasUppercase = false.obs;
  RxBool hasNumber = false.obs;
  RxBool hasSpecial = false.obs;
  RxBool hasLetter = false.obs;

  void checkPasswordRules(String password) {
    hasUppercase.value = RegExp('[A-Z]').hasMatch(password);
    hasNumber.value = RegExp('[0-9]').hasMatch(password);
    hasSpecial.value = RegExp(r'[!@#$%^&*(),.?":{}|<>]').hasMatch(password);
    hasLetter.value = RegExp('[a-z]').hasMatch(password);
  }

  Future<void> saveForm() async {
    if (isAcceptedTc.value) {
      hideKeyBoardWithoutContext();
      final Map<String, dynamic> req = {
        "email": emailCont.text.trim(),
        "first_name": firstNameCont.text.trim(),
        "last_name": lastNameCont.text.trim(),
        "password": passwordCont.text.trim(),
        "google_authentication_type": "Email",
        "date_of_birth" : dateCont.text.trim(),
        "mobile" : '+${pickedPhoneCode.value.phoneCode.trim()} ${phoneCont.text.trim()}',
        "gender" : selectedGender.value.slug,
        UserKeys.userType: LoginTypeConst.LOGIN_TYPE_USER,
      };

      isLoading(true);
      await AuthServiceApis.createUser(request: req).then((value) async {
        toast(value.message, print: true);
        try {
          final SignInController sCont = Get.find();
          sCont.emailCont.text = emailCont.text.trim();
          sCont.passwordCont.text = passwordCont.text.trim();
          sCont.isNavigateToDashboard(true);
          sCont.userName("${firstNameCont.text.trim()} ${lastNameCont.text.trim()}");
          Get.back();
          // isLoading(true);
          // sCont.saveForm().whenComplete(() => isLoading(false));
        } catch (e) {
          log('E: $e');
          toast(e.toString(), print: true);
        }
      }).catchError((e) {
        isLoading(false);
        toast(e.toString(), print: true);
      }).whenComplete(() => isLoading(false));
    } else {
      toast(locale.value.pleaseAcceptTermsAnd);
    }
  }
}
