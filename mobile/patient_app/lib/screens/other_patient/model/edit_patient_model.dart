class EditPatientResponse {
  final bool success;
  final String message;
  final Patient? patient; // Optional, if you need to return updated patient data

  EditPatientResponse({
    required this.success,
    required this.message,
    this.patient,
  });

  factory EditPatientResponse.fromJson(Map<String, dynamic> json) {
    return EditPatientResponse(
      success: json['success'],
      message: json['message'],
      patient: json['patient'] != null ? Patient.fromJson(json['patient']) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'success': success,
      'message': message,
      'patient': patient?.toJson(),
    };
  }
}

class Patient {
  final int id;
  final String firstName;
  final String lastName;
  final String dob;
  final String gender;
  final String relation;
  final String phone;
  final String profileImage;

  Patient({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.dob,
    required this.gender,
    required this.relation,
    required this.phone,
    required this.profileImage,
  });

  factory Patient.fromJson(Map<String, dynamic> json) {
    return Patient(
      id: json['id'],
      firstName: json['first_name'],
      lastName: json['last_name'],
      dob: json['dob'],
      gender: json['gender'],
      relation: json['relation'],
      phone: json['phone'],
      profileImage: json['profile_image'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'first_name': firstName,
      'last_name': lastName,
      'dob': dob,
      'gender': gender,
      'relation': relation,
      'phone': phone,
      'profile_image': profileImage,
    };
  }
}
