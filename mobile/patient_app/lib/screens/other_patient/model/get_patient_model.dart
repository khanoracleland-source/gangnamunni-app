
class Patient {
  final int id;
  final String name;
  final String profileImage;

  Patient({required this.id, required this.name, required this.profileImage});

  factory Patient.fromJson(Map<String, dynamic> json) {
    return Patient(
      id: json['id'],
      name: json['name'],
      profileImage: json['profile_image'],
    );
  }
}

class PatientResponse {
  final List<Patient> patients;

  PatientResponse({required this.patients});

  factory PatientResponse.fromJson(List<dynamic> json) {
    final List<Patient> patientList = json.map((e) => Patient.fromJson(e as Map<String, dynamic>)).toList();
    return PatientResponse(patients: patientList);
  }
}
