import '../../../utils/constants.dart';

class UserResponse {
  bool status;
  UserData userData;
  String message;

  UserResponse({
    this.status = false,
    required this.userData,
    this.message = "",
  });

  factory UserResponse.fromJson(Map<String, dynamic> json) {
    return UserResponse(
      status: json['status'] is bool ? json['status'] : false,
      userData: json['data'] is Map ? UserData.fromJson(json['data']) : UserData(),
      message: json['message'] is String ? json['message'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'status': status,
      'data': userData.toJson(),
      'message': message,
    };
  }
}

class UserData {
  int id;
  String firstName;
  String lastName;
  String userName;
  String address;
  String mobile;
  String email;
  String gender;
  String dateOfBirth;
  List<String> userRole;
  String apiToken;
  String profileImage;
  String loginType;
  bool isSocialLogin;
  String userType;
  num walletAmount;

  bool get isSocialLoginType => loginType == LoginTypeConst.LOGIN_TYPE_GOOGLE || loginType == LoginTypeConst.LOGIN_TYPE_APPLE || isSocialLogin;

  //Book for Other Patient
  String relation;
  String birthDate;
  String contactNumber;
  String fullName;
  int isGoogleAuthentication;
  String googleAuthenticationType;

  UserData({
    this.id = -1,
    this.firstName = "",
    this.lastName = "",
    this.userName = "",
    this.address = "",
    this.mobile = "",
    this.email = "",
    this.gender = "",
    this.dateOfBirth = "",
    this.userRole = const <String>[],
    this.apiToken = "",
    this.profileImage = "",
    this.loginType = "",
    this.isSocialLogin = false,
    this.userType = "",
    this.walletAmount = 0,
    this.relation = '',
    this.birthDate = '',
    this.contactNumber = '',
    this.fullName = '',
    this.isGoogleAuthentication = -1,
    this.googleAuthenticationType = '',
  });

  factory UserData.fromJson(Map<String, dynamic> json) {
    return UserData(
      id: json['id'] is int ? json['id'] : -1,
      firstName: json['first_name'] is String ? json['first_name'] : "",
      lastName: json['last_name'] is String ? json['last_name'] : "",
      userName: json['user_name'] is String ? json['user_name'] : "${json['first_name']} ${json['last_name']}",
      mobile: json['mobile'] is String ? json['mobile'] : "",
      address: json['address'] is String ? json['address'] : "",
      email: json['email'] is String ? json['email'] : "",
      gender: json['gender'] is String ? json['gender'] : "",
      dateOfBirth: json['date_of_birth'] is String ? json['date_of_birth'] : "",
      userRole: json['user_role'] is List ? List<String>.from(json['user_role'].map((x) => x)) : [],
      apiToken: json['api_token'] is String ? json['api_token'] : "",
      profileImage: json['profile_image'] is String ? json['profile_image'] : "",
      loginType: json['login_type'] is String ? json['login_type'] : "",
      isSocialLogin: json['is_social_login'] is bool ? json['is_social_login'] : false,
      userType: json['user_type'] is String ? json['user_type'] : "",
      walletAmount: json['wallet_amount'] is num ? json['wallet_amount'] : 0,
      relation: json['relation'] is String ? json['relation'] : "",
      birthDate: json['dob'] is String ? json['dob'] : "",
      contactNumber: json['contactNumber'] is String ? json['contactNumber'] : "",
      fullName: json['full_name'] is String ? json['full_name'] : "",
      isGoogleAuthentication: json['is_google_authentication'] is int ? json['is_google_authentication'] : -1,
      googleAuthenticationType: json['google_authentication_type'] is String ? json['google_authentication_type'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'first_name': firstName,
      'last_name': lastName,
      'user_name': userName,
      'mobile': mobile,
      'address': address,
      'email': email,
      'gender': gender,
      'date_of_birth': dateOfBirth,
      'user_role': userRole.map((e) => e).toList(),
      'api_token': apiToken,
      'profile_image': profileImage,
      'login_type': loginType,
      'is_social_login': isSocialLogin,
      'user_type': userType,
      'wallet_amount': walletAmount,
      'relation': relation,
      'dob': birthDate,
      'contactNumber': contactNumber,
      'full_name': fullName,
      'is_google_authentication': isGoogleAuthentication,
      'google_authentication_type': googleAuthenticationType,
    };
  }
}
