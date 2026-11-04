import 'package:flutter/material.dart';
import 'package:kivicare_patient/screens/webview/startup_webview_screen.dart';
import 'package:kivicare_patient/services/next_pilot.dart';

/// Every native entry point to booking (deep link wellness://booking, doctor / service / clinic screens)
/// lands here and continues on the website: the booking is created and paid there.
///
/// The website books by its own hospital id. Native screens use the HMS clinic ids, a different id
/// space, so they must not pass them on; without [hospitalId] the user lands on the hospital list
/// and starts the booking from a hospital's page instead of getting an error page.
class BookingFormScreen extends StatelessWidget {
  const BookingFormScreen({super.key, this.hospitalId, this.treatmentId});

  /// The website's hospital id (only a deep link `wellness://booking?id=N` carries one).
  final int? hospitalId;
  final int? treatmentId;

  Uri get targetUri {
    final id = hospitalId;
    if (id == null || id <= 0) return NextPilot.hospitalsUri;
    return NextPilot.bookingUri(hospitalId: id, treatmentId: treatmentId);
  }

  @override
  Widget build(BuildContext context) {
    return StartupWebViewScreen(url: targetUri.toString());
  }
}
