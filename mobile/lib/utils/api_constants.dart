class ApiConstants {
  static const String baseUrl = 'https://cornerstoneturfs.com/api'; // Combined Project API
  // static const String baseUrl = 'http://10.0.2.2:8000/api'; // For Android Simulator
  //static const String baseUrl = 'http://localhost:8000/api'; // For iOS/Web
  
  static const String imageBaseUrl = 'https://cornerstoneturfs.com'; // Combined Project

  // static const String imageBaseUrl = 'http://localhost:8001'; // Local Webapp/Admin (iOS/Web)
  // static const String imageBaseUrl = 'http://10.0.2.2:8001'; // Local Webapp/Admin (Android)
  
  static const String login = '$baseUrl/login';
  static const String register = '$baseUrl/register';
  static const String logout = '$baseUrl/logout';
  static const String me = '$baseUrl/me';
  static const String forgotPassword = '$baseUrl/forgot-password';
  static const String verifyOtp = '$baseUrl/verify-otp';
  static const String resetPassword = '$baseUrl/reset-password';
  static const String updateProfile = '$baseUrl/profile/update';
  static const String deleteAccount = '$baseUrl/profile/delete';
  static const String offers = '$baseUrl/offers';
  static const String sliders = '$baseUrl/sliders';
  static const String managerClients = '$baseUrl/manager/users';
}
