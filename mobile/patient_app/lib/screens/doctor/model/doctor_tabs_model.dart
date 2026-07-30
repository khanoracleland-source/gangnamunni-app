import 'package:flutter/material.dart';

enum DoctorTabs {
  about,
  services,
  reviews,
  qualification,
}

class DoctorTabElement {
  DoctorTabs type;
  String name;
  Widget component;

  DoctorTabElement({
    this.type = DoctorTabs.about,
    this.name = "",
    this.component = const SizedBox(),
  });
}
