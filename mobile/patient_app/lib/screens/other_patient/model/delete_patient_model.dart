class DeletePatientResponseModel {
  final bool status;
  final String message;

  DeletePatientResponseModel({required this.status, required this.message});

  factory DeletePatientResponseModel.fromJson(Map<String, dynamic> json) {
    return DeletePatientResponseModel(
      status: json['status'] ?? false,
      message: json['message'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'status': status,
      'message': message,
    };
  }
}
