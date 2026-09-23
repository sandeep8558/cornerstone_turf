import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../controllers/auth_controller.dart';
import '../utils/api_constants.dart';
import '../utils/app_colors.dart';
import 'main_navigation_screen.dart';
import 'profile_screen.dart';
import 'offers_screen.dart';
import 'support_screen.dart';
import 'manager_bookings_screen.dart';

class AppDrawer extends StatelessWidget {
  final String currentRoute;
  const AppDrawer({super.key, required this.currentRoute});

  @override
  Widget build(BuildContext context) {
    final AuthController _authController = Get.find<AuthController>();

    return Drawer(
      backgroundColor: AppColors.background,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          UserAccountsDrawerHeader(
            decoration: const BoxDecoration(color: AppColors.primary),
            currentAccountPicture: Obx(() {
              final profilePhoto = _authController.user['profile_photo'];
              final fullPhotoUrl = profilePhoto != null
                  ? '${ApiConstants.imageBaseUrl}/storage/$profilePhoto'
                  : null;
              return CircleAvatar(
                backgroundColor: Colors.white24,
                backgroundImage: fullPhotoUrl != null
                    ? NetworkImage(fullPhotoUrl)
                    : null,
                child: fullPhotoUrl == null
                    ? const Icon(Icons.person, color: Colors.white, size: 40)
                    : null,
              );
            }),
            accountName: Obx(
              () => Text(
                _authController.user['name'] ?? 'User',
                style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white),
              ),
            ),
            accountEmail: Obx(
              () => Text(
                _authController.user['email'] ?? '',
                style: const TextStyle(color: Colors.white70),
              ),
            ),
          ),
          const SizedBox(height: 10),
          _buildDrawerItem(
            icon: Icons.home_outlined,
            title: 'Home',
            isActive: currentRoute == 'home',
            onTap: () {
              if (currentRoute == 'home') {
                Get.back();
              } else {
                Get.offAll(() => const MainNavigationScreen(initialIndex: 0));
              }
            },
          ),
          _buildDrawerItem(
            icon: Icons.calendar_today_outlined,
            title: 'My Bookings',
            isActive: currentRoute == 'bookings',
            onTap: () {
              if (currentRoute == 'bookings') {
                Get.back();
              } else {
                Get.offAll(() => const MainNavigationScreen(initialIndex: 1));
              }
            },
          ),
          _buildDrawerItem(
            icon: Icons.person_outline,
            title: 'Profile',
            isActive: currentRoute == 'profile',
            onTap: () {
              if (currentRoute == 'profile') {
                Get.back();
              } else {
                Get.offAll(() => const ProfileScreen());
              }
            },
          ),
          _buildDrawerItem(
            icon: Icons.local_offer_outlined,
            title: 'Offers',
            isActive: currentRoute == 'offers',
            onTap: () {
              if (currentRoute == 'offers') {
                Get.back();
              } else {
                Get.offAll(() => const OffersScreen());
              }
            },
          ),
          _buildDrawerItem(
            icon: Icons.support_agent_outlined,
            title: 'Support',
            isActive: currentRoute == 'support',
            onTap: () {
              if (currentRoute == 'support') {
                Get.back();
              } else {
                Get.offAll(() => const SupportScreen());
              }
            },
          ),
          Obx(() {
            if (_authController.isManager.value) {
              return _buildDrawerItem(
                icon: Icons.admin_panel_settings_outlined,
                title: 'Manager Dashboard',
                isActive: currentRoute == 'manager',
                onTap: () {
                  if (currentRoute == 'manager') {
                    Get.back();
                  } else {
                    Get.offAll(() => const ManagerBookingsScreen());
                  }
                },
              );
            }
            return const SizedBox.shrink();
          }),
          const Padding(
            padding: EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
            child: Divider(color: AppColors.green200, height: 1),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12.0),
            child: ListTile(
              leading: const Icon(Icons.logout, color: Colors.redAccent),
              title: const Text(
                'Logout',
                style: TextStyle(color: Colors.redAccent, fontWeight: FontWeight.bold),
              ),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
              onTap: () {
                _authController.logout();
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDrawerItem({
    required IconData icon,
    required String title,
    required VoidCallback onTap,
    bool isActive = false,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12.0, vertical: 4.0),
      child: ListTile(
        leading: Icon(
          icon,
          color: isActive ? Colors.white : AppColors.primary,
        ),
        title: Text(
          title,
          style: TextStyle(
            color: isActive ? Colors.white : AppColors.textMain,
            fontWeight: isActive ? FontWeight.bold : FontWeight.w500,
          ),
        ),
        tileColor: isActive ? AppColors.primary : Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(10),
        ),
        onTap: onTap,
      ),
    );
  }
}
