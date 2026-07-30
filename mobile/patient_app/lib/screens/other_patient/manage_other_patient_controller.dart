import 'dart:io';

import 'package:get/get.dart';
import 'package:nb_utils/nb_utils.dart';

import '../../api/core_apis.dart';
import '../../main.dart';
import '../auth/model/login_response.dart';

class ManageOtherPatientController extends GetxController {
  Rx<Future<RxList<UserData>>> otherPatientListFuture = Future(() => RxList<UserData>()).obs;
  RxBool isLoading = false.obs;
  RxBool isLastPage = false.obs;
  RxInt page = 1.obs;

  //region Objects
  Rx<UserData> selectedMember = UserData().obs;

  RxList<UserData> otherPatientList = <UserData>[].obs;

  Rx<File> imageFile = File("").obs;

  @override
  void onInit() {
    init();
    super.onInit();
  }

  Future<void> init({bool showLoader = true}) async {
    await getOtherPatientList(showLoader: showLoader);
  }

  Future<void> onRefresh() async {
    page(1);
    await init(showLoader: false);
  }

  Future<void> onNextPage() async {
    if (!isLastPage.value) {
      page.value++;
      await init();
    }
  }

  Future<void> getOtherPatientList({bool showLoader = true}) async {
    if (showLoader) isLoading(true);

    await otherPatientListFuture(
      CoreServiceApis.otherMemberPatientList(
        page: page.value,
        memberList: otherPatientList,
        lastPageCallBack: (p0) {
          isLastPage(p0);
        },
      ),
    ).then((value) {
      log('value.length ==> ${value.length}');
    }).catchError((e) {
      isLoading(false);
      log("getOtherPatientList Err : $e");
    }).whenComplete(() => isLoading(false));
  }

  Future<void> handleDeleteMember(int memberId) async {
    isLoading(true);
    await CoreServiceApis.deleteMember(
      memberId: memberId,
    ).then((value) async {
      isLoading(false);
      toast(locale.value.recordDeletedSuccessfully);
      await onRefresh();
    }).catchError((e) async {
      isLoading(false);
      toast(e.toString(), print: true);
      throw e;
    }).whenComplete(() => isLoading(false));
  }
}
