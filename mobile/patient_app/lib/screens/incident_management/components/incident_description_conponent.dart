import 'package:flutter/material.dart';
import 'package:nb_utils/nb_utils.dart';

class IncidentDescriptionComponent extends StatelessWidget {
  final String title;
  final String description;

  const IncidentDescriptionComponent({super.key, required this.description, required this.title});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        16.height,
        Text(title, style: boldTextStyle()),
        8.height,
        Text(description, style: secondaryTextStyle()),
      ],
    );
  }
}
