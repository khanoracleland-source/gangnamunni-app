import 'dart:io';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import 'package:nb_utils/nb_utils.dart';
import '../../../utils/constants.dart';
import 'package:country_picker/country_picker.dart';

import '../../api/core_apis.dart';
import '../../main.dart';
import '../../utils/common_base.dart';
import 'model/incident_response_model.dart';
import 'model/incident_status_model.dart';
import 'package:path/path.dart' as path;

class IncidentManagement extends GetxController {
  // Form controllers
  TextEditingController titleCont = TextEditingController();
  TextEditingController desCont = TextEditingController();
  TextEditingController emailCont = TextEditingController();
  TextEditingController phoneCodeCont = TextEditingController();
  TextEditingController mobileCont = TextEditingController();
  TextEditingController imageTitleCont = TextEditingController();

  FocusNode titleFocus = FocusNode();
  FocusNode desFocus = FocusNode();
  FocusNode emailFocus = FocusNode();
  FocusNode phoneCodeFocus = FocusNode();
  FocusNode mobileFocus = FocusNode();
  FocusNode imageTitleFocus = FocusNode();

  RxBool isLoading = false.obs;
  Rx<Country> pickedPhoneCode = defaultCountry.obs;
  Rx<File> imageFile = File("").obs;
  XFile? pickedFile;

  // Incident list state
  Rx<Future<RxList<Incident>>> incidenceFuture = Future(() => RxList<Incident>()).obs;
  RxList<Incident> incidents = RxList<Incident>();
  RxBool isIncidenceLastPage = false.obs;
  RxInt incidencePage = 1.obs;

  RxBool isExpanded = false.obs;

  // Filters
  RxSet<String> selectedStatus = RxSet();
  RxSet<String> selectedService = RxSet();

  // Tabs
  RxList<IncidentStatusModel> filterStatus = RxList();
  Rx<IncidentStatusModel> selectedTab = IncidentStatusModel().obs;

  @override
  void onInit() {
    filterStatus = [
      IncidentStatusModel(name: locale.value.all),
      IncidentStatusModel(type: IncidentStatus.open, name: locale.value.open),
      IncidentStatusModel(type: IncidentStatus.closed, name: locale.value.closed.toLowerCase().capitalizeFirstLetter()),
      IncidentStatusModel(type: IncidentStatus.reject, name: locale.value.rejected),
    ].obs;

    if (filterStatus.isNotEmpty) {
      selectedTab(filterStatus.first);
    }

    getIncidents(); // Initial call
    super.onInit();
  }

  @override
  void dispose() {
    clearTextFields();
    super.dispose();
  }

  void clearTextFields() {
    titleCont.clear();
    desCont.clear();
    emailCont.clear();
    phoneCodeCont.clear();
    mobileCont.clear();
    imageTitleCont.clear();
  }

  Future<void> getIncidents({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }

    await incidenceFuture(
      CoreServiceApis.getIncidentList(
        page: incidencePage.value,
        incidents: incidents,
        lastPageCallBack: (isLast) => isIncidenceLastPage(isLast),
      ),
    ).then((value) {
      log('Incidents fetched: ${value.length}');
    }).catchError((e) {
      log("getIncidents error $e");
    }).whenComplete(() => isLoading(false));
  }

  Future<void> pickImage() async {
    pickedFile = await ImagePicker().pickImage(
      source: ImageSource.gallery,
    );

    if (pickedFile != null) {
      final file = File(pickedFile!.path);

      final fileSize = await file.length();
      final sizeInMB = fileSize / (1024 * 1024);


      if (fileSize <= 2 * 1024 * 1024) {
        imageFile(file);
        imageTitleCont.text = path.basename(pickedFile!.path);
      } else {
        toast(locale.value.errorImageTooLarge(sizeInMB.toStringAsFixed(2)));
      }
    }
  }

  Future<void> submitAPI({
    required String title,
    required String description,
    required String phoneCode,
    required String mobileNumber,
    required String email,
    required File imageFile,
  }) async {
    isLoading(true);
    hideKeyBoardWithoutContext();
    log('Submit Request: title: $title,description: $description,email: $email,mobileNumber: $mobileNumber, phoneCode: $phoneCode,imageFile: $imageFile');
    await CoreServiceApis.addIncident(title: title, description: description, email: email, mobileNumber: mobileNumber, phoneCode: phoneCode, imageFile: imageFile).then((value) async {
      log('Incident Submitted: ${value.toJson()}');
      toast(value.message);
      isLoading(false);
    }).then((data) {
      toast(locale.value.successfullyAdded);
    }).catchError((e) {
      isLoading(false);
      log(e.toString());
    });
  }
}
