import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../controllers/auth_controller.dart';
import '../utils/api_constants.dart';
import '../utils/app_colors.dart';
import 'home_screen.dart';
import 'bookings_screen.dart';
import 'app_drawer.dart';

class MainNavigationScreen extends StatefulWidget {
  final int initialIndex;
  const MainNavigationScreen({super.key, this.initialIndex = 0});

  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> {
  int _selectedIndex = 0;

  @override
  void initState() {
    super.initState();
    _selectedIndex = widget.initialIndex;
  }

  final List<Widget> _screens = [const HomeScreen(), const BookingsScreen()];

  void _onItemTapped(int index) {
    setState(() {
      _selectedIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        iconTheme: const IconThemeData(color: AppColors.textMain),
        title: Text(
          _selectedIndex == 0 ? 'CORNERSTONE TURF' : 'MY BOOKINGS',
          style: const TextStyle(
            fontWeight: FontWeight.bold,
            color: AppColors.textMain,
            letterSpacing: 0.5,
          ),
        ),
        centerTitle: false,
      ),
      drawer: AppDrawer(currentRoute: _selectedIndex == 0 ? 'home' : 'bookings'),
      body: _screens[_selectedIndex],
      floatingActionButton: GestureDetector(
        onTap: () => _onItemTapped(0),
        child: Container(
          height: 84,
          width: 84,
          decoration: BoxDecoration(
            color: Colors.white,
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.08),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
              BoxShadow(
                color: AppColors.green800.withOpacity(0.06),
                blurRadius: 4,
                offset: const Offset(0, -2),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.all(4),
            child: Container(
              decoration: BoxDecoration(
                color: AppColors.green50,
                shape: BoxShape.circle,
                border: Border.all(color: AppColors.green200.withOpacity(0.8), width: 1.5),
              ),
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: ClipOval(
                  child: Image.asset(
                    'assets/images/logo.png',
                    fit: BoxFit.contain,
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerDocked,
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          boxShadow: [
            BoxShadow(
              color: AppColors.green800.withOpacity(0.06),
              blurRadius: 10,
              offset: const Offset(0, -3),
            ),
          ],
        ),
        child: BottomAppBar(
          color: Colors.white,
          elevation: 0,
          height: 65,
          padding: EdgeInsets.zero,
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              Expanded(
                child: InkWell(
                  onTap: () => _onItemTapped(0),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        _selectedIndex == 0 ? Icons.home : Icons.home_outlined,
                        color: _selectedIndex == 0 ? AppColors.primary : AppColors.textMain.withOpacity(0.4),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Home',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: _selectedIndex == 0 ? FontWeight.bold : FontWeight.normal,
                          color: _selectedIndex == 0 ? AppColors.primary : AppColors.textMain.withOpacity(0.4),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 84), // space for floating action button matching the 84dp logo
              Expanded(
                child: InkWell(
                  onTap: () => _onItemTapped(1),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        _selectedIndex == 1 ? Icons.calendar_today : Icons.calendar_today_outlined,
                        color: _selectedIndex == 1 ? AppColors.primary : AppColors.textMain.withOpacity(0.4),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Bookings',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: _selectedIndex == 1 ? FontWeight.bold : FontWeight.normal,
                          color: _selectedIndex == 1 ? AppColors.primary : AppColors.textMain.withOpacity(0.4),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
