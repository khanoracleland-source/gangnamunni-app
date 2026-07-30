class DoctorReviewData {
  int id;
  int doctorId;
  String title;
  String reviewMsg;
  num rating;
  int userId;
  int serviceId;
  String serviceName;
  String createdAt;
  String username;
  String profileImage;

  DoctorReviewData({
    this.id = -1,
    this.doctorId = -1,
    this.title = "",
    this.reviewMsg = "",
    this.rating = 0,
    this.userId = -1,
    this.serviceId = -1,
    this.serviceName = "",
    this.createdAt = "",
    this.username = "",
    this.profileImage = "",
  });

  factory DoctorReviewData.fromJson(Map<String, dynamic> json) {
    return DoctorReviewData(
      id: json['id'] is int ? json['id'] : -1,
      doctorId: json['doctor_id'] is int ? json['doctor_id'] : -1,
      title: json['title'] is String ? json['title'] : "",
      reviewMsg: json['review_msg'] is String ? json['review_msg'] : "",
      rating: json['rating'] is num ? json['rating'] : 0,
      userId: json['user_id'] is int ? json['user_id'] : -1,
      serviceId: json['service_id'] is int ? json['service_id'] : -1,
      serviceName: json['service_name'] is String ? json['service_name'] : "",
      createdAt: json['created_at'] is String ? json['created_at'] : "",
      username: json['username'] is String ? json['username'] : "",
      profileImage: json['profile_image'] is String ? json['profile_image'] : "",
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'doctor_id': doctorId,
      'title': title,
      'review_msg': reviewMsg,
      'rating': rating,
      'user_id': userId,
      'service_id': serviceId,
      'service_name': serviceName,
      'created_at': createdAt,
      'username': username,
      'profile_image': profileImage,
    };
  }
}
