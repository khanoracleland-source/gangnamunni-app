import 'package:get/get_rx/src/rx_types/rx_types.dart';

enum IncidentStatus {
  all,
  open,
  closed,
  reject,
}

class IncidentStatusModel {
  IncidentStatus type;
  String name;
  String icon;
  RxBool isFilterSelected = false.obs;

  IncidentStatusModel({
    this.type = IncidentStatus.all,
    this.name = "",
    this.icon = '',
  });
}
