import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:kivicare_patient/api/core_apis.dart';
import 'package:kivicare_patient/utils/app_common.dart';
import '../../components/bottom_selection_widget.dart';
import '../../main.dart';
import 'package:kivicare_patient/utils/common_base.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../utils/constants.dart';
import '../auth/model/login_response.dart';
import '../booking/model/booking_req.dart';
import '../clinic/model/clinic_detail_model.dart';
import '../clinic/model/clinics_res_model.dart';
import '../doctor/model/doctor_list_res.dart';
import '../other_patient/manage_other_patient_controller.dart';
import '../service/model/service_list_model.dart';
import 'components/appointment_summary_comp.dart';
import 'components/service_selection_card_widget.dart';

class BookingFormController extends GetxController {
  Rx<Future<RxList<String>>> slotsFuture = Future(() => RxList<String>()).obs;
  RxBool isLoading = false.obs;
  RxBool nextBtnVisible = false.obs;
  RxList<String> slots = RxList();
  RxBool timeSlotsIsHoliday = false.obs;
  RxString timeSlotsMessage = ''.obs;
  RxString selectedDate = DateTime.now().formatDateYYYYmmdd().obs;
  RxString selectedSlot = "".obs;
  final Set<String> holidayDates = <String>{};

  final ManageOtherPatientController manageOtherPatientController = ManageOtherPatientController();

  Rx<UserData> selectedMember = UserData().obs;

  RxList<PlatformFile> medicalReportFiles = RxList();

  BookingReq bookingReq = BookingReq();

  RxBool isLastPage = false.obs;
  RxInt servicePage = 1.obs;
  RxInt clinicPage = 1.obs;
  RxInt doctorPage = 1.obs;

  //Service
  Rx<ServiceElement> selectedService = ServiceElement().obs;
  RxList<ServiceElement> serviceList = RxList();

  //Error Service
  RxBool hasErrorFetchingService = false.obs;
  RxString errorMessageService = "".obs;

  //Clinic
  Rx<Clinic> selectedClinic = Clinic(clinicSession: ClinicSession()).obs;
  RxList<Clinic> clinicList = RxList();

  //Error Clinic
  RxBool hasErrorFetchingClinic = false.obs;
  RxString errorMessageClinic = "".obs;

  //Doctor
  Rx<Doctor> selectedDoctor = Doctor().obs;
  RxList<Doctor> doctorList = RxList();

  //Error Clinic
  RxBool hasErrorFetchingDoctor = false.obs;
  RxString errorMessageDoctor = "".obs;

  RxString serviceNameText = locale.value.pleaseSelectService.obs;
  RxString clinicNameText = locale.value.pleaseSelectClinic.obs;
  RxString doctorNameText = locale.value.pleaseSelectDoctor.obs;
  TextEditingController medicalReportCont = TextEditingController();

  /// UI validation errors
  RxString serviceValidationError = "".obs;
  RxString clinicValidationError = "".obs;
  RxString doctorValidationError = "".obs;

  @override
  void onInit() {
    if (!currentSelectedService.value.id.isNegative) {
      log('currentSelectedService.value.name==> ${currentSelectedService.value.name}');
      log('currentSelectedService.value.id==> ${currentSelectedService.value.id}');
      selectedService(currentSelectedService.value);
      serviceNameText(currentSelectedService.value.name);
      getClinicList();
    }

    if (!currentSelectedClinic.value.id.isNegative) {
      log('currentSelectedClinic.value.name==> ${currentSelectedClinic.value.name}');
      log('currentSelectedClinic.value.id==> ${currentSelectedClinic.value.id}');
      selectedClinic(currentSelectedClinic.value);
      clinicNameText(currentSelectedClinic.value.name);
      getDoctorList();
    }

    if (!currentSelectedDoctor.value.doctorId.isNegative) {
      log('currentSelectedDoctor.value.name==> ${currentSelectedDoctor.value.fullName}');
      log('currentSelectedDoctor.value.doctorId==> ${currentSelectedDoctor.value.doctorId}');
      selectedDoctor(currentSelectedDoctor.value);
      doctorNameText(currentSelectedDoctor.value.fullName);
      getTimeSlot();
    }

    init();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (selectedService.value.id.isNegative && currentSelectedService.value.id.isNegative) {
        isShowBottomSheet();
      }
    });
    super.onInit();
  }

  Future<void> isShowBottomSheet() async {
    await getServiceList();
    serviceCommonBottomSheet(
      getContext,
      child: BottomSelectionSheet(
        title: locale.value.chooseService,
        hintText: locale.value.searchForService,
        hasError: hasErrorFetchingService.value,
        isEmpty: !isLoading.value && serviceList.isEmpty,
        errorText: errorMessageService.value,
        noDataTitle: locale.value.serviceListIsEmpty,
        noDataSubTitle: locale.value.thereAreNoServicesListedAtTheMomentStayTunedF,
        isLoading: isLoading,
        searchApiCall: (p0) => getServiceList(searchText: p0),
        onRetry: () {
          servicePage(1);
          getServiceList();
        },
        listWidget: Obx(() => serviceListWid(serviceList).expand()),
      ),
    );
  }

  Future<void> init({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }

    getServiceList();
    await manageOtherPatientController.getOtherPatientList();
  }

  Future<void> handleFilesPickerClick() async {
    isLoading(true);

    try {
      final pickedFiles = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'doc', 'docx', 'pdf'],
        allowMultiple: true,
      );

      if (pickedFiles != null && pickedFiles.files.isNotEmpty) {
        Set<String> existingFiles = medicalReportFiles.map((file) => file.name.trim().toLowerCase()).toSet();

        for (final file in pickedFiles.files) {
          if (file.path != null) {
            final fileSize = file.size;
            if (fileSize > 5 * 1024 * 1024) {
              toast(locale.value.selectedFileShouldBeLessThan5MB);
              continue;
            }

            if (!existingFiles.contains(file.name.trim().toLowerCase())) {
              medicalReportFiles.add(file);
            }
          }
        }
      } else {
        toast(locale.value.noFileSelected);
      }
    } catch (e) {
      toast('File picking failed: $e');
    } finally {
      isLoading(false);
    }
  }

  ///Get Service List
  Future<void> getServiceList({String searchText = "", int serviceId = -1}) async {
    isLoading(true);
    CoreServiceApis.getServiceList(
      serviceId: serviceId,
      page: servicePage.value,
      serviceList: serviceList,
      categoryId: currentSelectedService.value.categoryId,
      systemServiceId: currentSelectedService.value.systemServiceId,
      clinicId: currentSelectedClinic.value.id,
      doctorId: currentSelectedDoctor.value.doctorId,
      search: searchText.trim(),
      lastPageCallBack: (p) {
        isLastPage(p);
      },
    ).then((value) {
      isLoading(false);
      hasErrorFetchingService(false);
    }).onError((error, stackTrace) {
      hasErrorFetchingService(true);
      errorMessageService(error.toString());
      isLoading(false);
    });
  }

  ///Get Clinic List
  Future<void> getClinicList({String searchText = ""}) async {
    isLoading(true);
    await CoreServiceApis.getClinics(
      page: clinicPage.value,
      clinics: clinicList,
      serviceId: selectedService.value.id,
      search: searchText.trim(),
      lastPageCallBack: (p) {
        isLastPage(p);
      },
    ).then((value) {
      isLoading(false);
      hasErrorFetchingClinic(false);

      // Auto-select clinic if only one available
      if (clinicList.length == 1 && selectedClinic.value.id.isNegative) {
        selectedClinic(clinicList[0]);
        clinicNameText(clinicList[0].name);
        log('Auto-selected clinic: ${clinicList[0].name}');

        // Get doctor list for this clinic
        getDoctorList();
      }
    }).onError((error, stackTrace) {
      hasErrorFetchingClinic(true);
      errorMessageClinic(error.toString());
      isLoading(false);
    });
  }

  void clearDoctorSelection() {
    doctorNameText("");

    /// Clear selected doctor in doctor name field
    selectedDoctor(Doctor());
  }

  ///Get Doctor List
  void getDoctorList({String searchText = ""}) {
    isLoading(true);
    CoreServiceApis.getDoctors(
      page: doctorPage.value,
      doctors: doctorList,
      clinicId: selectedClinic.value.id,
      serviceId: selectedService.value.id,
      search: searchText.trim(),
      lastPageCallBack: (p) {
        isLastPage(p);
      },
    ).then((value) async {
      isLoading(false);
      hasErrorFetchingDoctor(false);

      // Auto-select doctor if only one available
      if (doctorList.length == 1 && selectedDoctor.value.doctorId.isNegative) {
        selectedDoctor(doctorList[0]);
        doctorNameText(doctorList[0].fullName);
        log('Auto-selected doctor: ${doctorList[0].fullName}');

        // Get time slots for the selected doctor
        getTimeSlot();
      }
    }).onError((error, stackTrace) {
      hasErrorFetchingDoctor(true);
      errorMessageDoctor(error.toString());
      isLoading(false);
    });
  }

  Future<void> getTimeSlot({bool showLoader = true}) async {
    if (showLoader) {
      isLoading(true);
    }

    /// Get Time Slots Api Call
    await slotsFuture(
      CoreServiceApis.getTimeSlots(
        slots: slots,
        date: selectedDate.value,
        serviceId: selectedService.value.id,
        clinicId: selectedClinic.value.id,
        doctorId: selectedDoctor.value.doctorId,
        messageHolder: timeSlotsMessage,
        isHolidayHolder: timeSlotsIsHoliday,
      ),
    ).then((value) {
      log('value.length ==> ${value.length}');
    }).catchError((e) {
      isLoading(false);
      log("getTimeSlots error $e");
    }).whenComplete(() => isLoading(false));
  }

  void onDateTimeChange() {
    final appointmentDateTime = "${selectedDate.value} ${selectedSlot.value}";
    if (appointmentDateTime.isValidDateTime) {
      nextBtnVisible(true);
    } else {
      nextBtnVisible(false);
    }
  }

  bool isHoliday(DateTime day) {
    return holidayDates.contains(day.formatDateYYYYmmdd());
  }

  void handleNextClick(BuildContext context) {
    // Reset errors
    serviceValidationError("");
    clinicValidationError("");
    doctorValidationError("");

    // Validate required selections
    if (selectedService.value.id.isNegative) {
      serviceValidationError(locale.value.chooseService);
    }
    if (selectedClinic.value.id.isNegative) {
      clinicValidationError(locale.value.chooseClinic);
    }
    if (selectedDoctor.value.doctorId.isNegative) {
      doctorValidationError(locale.value.chooseDoctor);
    }

    if (serviceValidationError.value.isNotEmpty || clinicValidationError.value.isNotEmpty || doctorValidationError.value.isNotEmpty) {
      return;
    }
    //BookingReq
    bookingReq.files = medicalReportFiles;
    bookingReq.clinicId = selectedClinic.value.id.toString();
    bookingReq.serviceId = selectedService.value.id.toString();
    bookingReq.appointmentDate = selectedDate.value;
    bookingReq.userId = loginUserData.value.id.toString();
    bookingReq.status = StatusConst.pending;
    bookingReq.doctorId = selectedDoctor.value.doctorId.toString();
    bookingReq.appointmentTime = selectedSlot.value;
    bookingReq.description = medicalReportCont.text;
    //
    bookingReq.serviceName = selectedService.value.name;
    bookingReq.doctorName = selectedDoctor.value.fullName;
    bookingReq.clinicName = selectedClinic.value.name;
    bookingReq.location = selectedClinic.value.address;
    bookingReq.totalAmount = totalAmount.toStringAsFixed(2).toDouble();
    bookingReq.isEnableAdvancePayment = selectedService.value.isEnableAdvancePayment;
    bookingReq.advancePayableAmount = advancePayableAmount;
    bookingReq.isOnlineService = selectedService.value.type.toLowerCase() == ServiceTypeConst.online;
    if (selectedMember.value.id > 0) {
      bookingReq.otherPatientId = selectedMember.value.id.toString();
    }
    showInDialog(
      context,
      contentPadding: EdgeInsets.zero,
      builder: (_) {
        return AppointmentSummaryWidget(bookingData: bookingReq);
      },
    );
  }

  //----------------------------------------Price Calculation-----------------------------------
  AssignDoctor get finalAssignDoctor => selectedService.value.assignDoctor.firstWhere(
        (element) => element.doctorId == selectedDoctor.value.doctorId,
        orElse: () => AssignDoctor(
          priceDetail: PriceDetail(
            servicePrice: selectedService.value.charges,
            serviceAmount: selectedService.value.charges,
            discountAmount: selectedService.value.discountAmount,
            discountType: selectedService.value.discountType,
            discountValue: selectedService.value.discountValue,
            totalAmount: selectedService.value.payableAmount,
            duration: selectedService.value.duration,
          ),
        ),
      );

  double get fixedExclusiveTaxAmount => appConfigs.value.taxData.where((element) => (element.taxScope == TaxType.exclusiveTax) && (element.type.toLowerCase().contains(TaxType.FIXED.toLowerCase()))).sumByDouble((p0) => p0.value.validate());

  double get percentExclusiveTaxAmount => appConfigs.value.taxData.where((element) {
        return (element.taxScope == TaxType.exclusiveTax) && (element.type.toLowerCase().contains(TaxType.PERCENT.toLowerCase()));
      }).sumByDouble((p0) {
        return (selectedService.value.assignDoctor.isNotEmpty ? finalAssignDoctor.priceDetail.serviceAmount * p0.value.validate() : selectedService.value.payableAmount * p0.value.validate()) / 100;
      });

  num get totalExclusiveTax => (fixedExclusiveTaxAmount + percentExclusiveTaxAmount).toStringAsFixed(Constants.DECIMAL_POINT).toDouble();

  num get totalAmount => (selectedService.value.assignDoctor.isNotEmpty ? (finalAssignDoctor.priceDetail.totalAmount) : (selectedService.value.payableAmount + totalExclusiveTax));

  num get advancePayableAmount => (totalAmount * selectedService.value.advancePaymentAmount) / 100;

  num get remainingAmountAfterService => totalAmount - advancePayableAmount;

  Widget serviceListWid(List<ServiceElement> list) {
    final List<ServiceElement> displayList = List.from(list);

    if (selectedService.value.id > 0) {
      displayList.removeWhere((e) => e.id == selectedService.value.id);
      displayList.insert(0, selectedService.value);
    }

    return AnimatedListView(
      itemCount: displayList.length,
      shrinkWrap: true,
      listAnimationType: ListAnimationType.FadeIn,
      itemBuilder: (context, index) {
        return Obx(() {
          return ServiceSelectionCardWidget(
            serviceElement: displayList[index],
            isSelected: displayList[index].id == selectedService.value.id,
            onTap: () {
              selectedService(displayList[index]);
              serviceNameText(selectedService.value.name);
              serviceValidationError("");
              currentSelectedService.value.payableAmount = selectedService.value.payableAmount.toDouble();

              // Only clear clinic and doctor if they are not pre-selected values
              if (currentSelectedClinic.value.id.isNegative) {
                clinicNameText("");
                selectedClinic = Clinic(clinicSession: ClinicSession()).obs;
              }
              if (currentSelectedDoctor.value.doctorId.isNegative) {
                doctorNameText("");
                selectedDoctor = Doctor().obs;
              }

              doctorList.clear();
              selectedSlot("");
              slots.clear();
              nextBtnVisible(false);
              getClinicList();
              getServiceList();
              Get.back();
            },
          ).paddingBottom(16);
        });
      },
      onNextPage: () {
        if (isLastPage.value) {
          servicePage(servicePage.value + 1);
          getServiceList();
        }
      },
      onSwipeRefresh: () async {
        servicePage(1);
        return getServiceList();
      },
    );
  }
}
