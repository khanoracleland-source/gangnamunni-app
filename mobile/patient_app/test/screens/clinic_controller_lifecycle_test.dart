import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:kivicare_patient/screens/clinic/clinic_detail_controller.dart';
import 'package:kivicare_patient/screens/clinic/clinic_list_controller.dart';
import 'package:kivicare_patient/screens/clinic/model/clinic_detail_model.dart';
import 'package:kivicare_patient/screens/clinic/model/clinics_res_model.dart';
import 'package:kivicare_patient/utils/app_common.dart';

class _SearchController extends ClinicListController {
  int requests = 0;

  @override
  Future<void> getClinicList({bool showLoader = true}) async {
    requests++;
  }
}

class _DetailController extends ClinicDetailController {
  _DetailController(Future<ClinicDetailModel> response)
      : super(detailLoader: (_) => response);

  int childRequests = 0;

  @override
  Future<void> getServiceList({bool showLoader = true}) async {
    childRequests++;
  }

  @override
  Future<void> getDoctors({bool showLoader = true}) async {
    childRequests++;
  }
}

void main() {
  testWidgets('scope change rejects an in-flight detail response',
      (tester) async {
    final originalLogin = isLoggedIn.value;
    addTearDown(() => isLoggedIn(originalLogin));
    isLoggedIn(true);
    final response = Completer<ClinicDetailModel>();
    final controller = _DetailController(response.future);
    controller.clinicData(
        Clinic(id: 1, name: 'Original', clinicSession: ClinicSession()));
    final request = controller.init(forceRefresh: true);
    isLoggedIn(false);
    response.complete(ClinicDetailModel(
        status: true,
        data: Clinic(id: 1, name: 'Old account', clinicSession: ClinicSession())));
    await request;
    await tester.pump(const Duration(milliseconds: 1));
    expect(controller.clinicData.value.name, 'Original');
    expect(controller.childRequests, 0);
    controller.onDelete();
  });

  testWidgets('closing cancels pending search and disposes text controller',
      (tester) async {
    final controller = _SearchController();
    controller.onInit();
    controller.searchClinicStream.add('clinic');
    await tester.pump();
    final beforeClose = controller.requests;
    controller.onDelete();
    await tester.pump(const Duration(seconds: 2));
    expect(controller.requests, beforeClose);
    expect(controller.searchClinicStream.isClosed, isTrue);
    expect(() => controller.searchClinicCont.addListener(() {}),
        throwsFlutterError);
  });

  testWidgets('late detail response cannot update a closed controller',
      (tester) async {
    final response = Completer<ClinicDetailModel>();
    final controller = _DetailController(response.future);
    controller.clinicData(
        Clinic(id: 1, name: 'Original', clinicSession: ClinicSession()));
    final request = controller.init(forceRefresh: true);
    controller.onDelete();
    response.complete(ClinicDetailModel(
        status: true,
        data: Clinic(id: 1, name: 'Late', clinicSession: ClinicSession())));
    await request;
    await tester.pump(const Duration(milliseconds: 1));
    expect(controller.clinicData.value.name, 'Original');
    expect(controller.childRequests, 0);
  });
}
