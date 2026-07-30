import 'package:flutter/material.dart';

enum ClinicTabs {
  about,
  services,
  doctors,
  gallery,
}

class ClinicTabElement {
  ClinicTabs type;
  String name;
  Widget component;

  ClinicTabElement({
    this.type = ClinicTabs.about,
    this.name = "",
    this.component = const SizedBox(),
  });
}
