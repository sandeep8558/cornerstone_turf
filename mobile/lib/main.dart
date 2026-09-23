import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:google_fonts/google_fonts.dart';
import 'controllers/auth_controller.dart';
import 'controllers/turf_controller.dart';
import 'controllers/booking_controller.dart';
import 'views/login_screen.dart';
import 'views/main_navigation_screen.dart';
import 'utils/app_colors.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  Get.put(AuthController());
  Get.put(TurfController());
  Get.put(BookingController());
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      title: 'Cornerstone Turf',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        brightness: Brightness.light,
        useMaterial3: true,
        primaryColor: AppColors.primary,
        scaffoldBackgroundColor: AppColors.background,
        colorScheme: ColorScheme.light(
          primary: AppColors.primary,
          secondary: AppColors.secondary,
          surface: AppColors.cardBg,
          background: AppColors.background,
          onPrimary: Colors.white,
          onSecondary: Colors.white,
          onSurface: AppColors.textMain,
          onBackground: AppColors.textMain,
        ),
        appBarTheme: const AppBarTheme(
          backgroundColor: Colors.transparent,
          foregroundColor: AppColors.textMain,
          iconTheme: IconThemeData(color: AppColors.textMain),
          elevation: 0,
        ),
        textTheme: GoogleFonts.interTextTheme(
          ThemeData.light().textTheme.apply(
            bodyColor: AppColors.textMain,
            displayColor: AppColors.textMain,
          ),
        ),
      ),
      initialRoute: '/',
      getPages: [
        GetPage(name: '/', page: () => const RootHandler()),
        GetPage(name: '/login', page: () => LoginScreen()),
        GetPage(name: '/home', page: () => MainNavigationScreen()),
      ],
    );
  }
}
class RootHandler extends StatelessWidget {
  const RootHandler({super.key});

  @override
  Widget build(BuildContext context) {
    final AuthController authController = Get.find<AuthController>();
    
    return FutureBuilder(
      future: authController.checkLoginStatus(),
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Scaffold(
            backgroundColor: AppColors.background,
            body: Center(
              child: Icon(Icons.sports_soccer, size: 80, color: AppColors.primary),
            ),
          );
        }
        
        if (authController.token.value.isNotEmpty) {
          return const MainNavigationScreen();
        } else {
          return LoginScreen();
        }
      },
    );
  }
}
