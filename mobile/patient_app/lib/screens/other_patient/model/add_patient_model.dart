import '../../../utils/app_common.dart';

class AddPatientRequest {
  final String firstName;
  final String lastName;
  final String dob;
  final String gender;
  final String relation;
  final String contactNumber;
  final String subTitle;
  final String profileImage;

  AddPatientRequest({
    required this.firstName,
    required this.lastName,
    required this.dob,
    required this.gender,
    required this.relation,
    required this.contactNumber,
    required this.subTitle,
    required this.profileImage,
  });
  factory AddPatientRequest.fromJson(Map<String, dynamic> json) {
    return AddPatientRequest(
      firstName: json['first_name'],
      lastName: json['last_name'],
      dob: json['dob'],
      gender: json['gender'],
      relation: json['relation'],
      contactNumber: json['contactNumber'],
      subTitle: json['subtitle'],
      profileImage: json['profile_image'] ?? loginUserData.value.profileImage,
    );
  }
  Map<String, dynamic> toJson() {
    return {
      'first_name': firstName,
      'last_name': lastName,
      'dob': dob,
      'gender': gender,
      'relation': relation,
      'contactNumber': contactNumber,
      'subtitle': subTitle,
      'profile_image': profileImage,
    };
  }
}
