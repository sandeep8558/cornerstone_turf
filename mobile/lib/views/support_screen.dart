import 'package:flutter/material.dart';
import 'package:animate_do/animate_do.dart';
import 'package:url_launcher/url_launcher.dart';
import '../utils/app_colors.dart';
import 'app_drawer.dart';

class SupportScreen extends StatelessWidget {
  const SupportScreen({super.key});

  Future<void> _makeCall(String phoneNumber) async {
    final Uri launchUri = Uri(
      scheme: 'tel',
      path: phoneNumber,
    );
    if (await canLaunchUrl(launchUri)) {
      await launchUrl(launchUri);
    }
  }

  Future<void> _sendEmail(String email) async {
    final Uri launchUri = Uri(
      scheme: 'mailto',
      path: email,
    );
    if (await canLaunchUrl(launchUri)) {
      await launchUrl(launchUri);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      drawer: const AppDrawer(currentRoute: 'support'),
      appBar: AppBar(
        title: const Text('Support', style: TextStyle(color: AppColors.textMain, fontWeight: FontWeight.bold)),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Builder(
          builder: (context) => IconButton(
            icon: const Icon(Icons.menu, color: AppColors.textMain),
            onPressed: () => Scaffold.of(context).openDrawer(),
          ),
        ),
        centerTitle: true,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            FadeInDown(
              child: const Center(
                child: Column(
                  children: [
                    CircleAvatar(
                      radius: 50,
                      backgroundColor: AppColors.green100,
                      child: Icon(Icons.support_agent, size: 60, color: AppColors.primary),
                    ),
                    SizedBox(height: 20),
                    Text(
                      'How can we help you?',
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textMain,
                      ),
                    ),
                    SizedBox(height: 10),
                    Text(
                      'We are available 24/7 to assist you',
                      style: TextStyle(color: AppColors.textSecondary, fontSize: 14, fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 40),
            
            // Contact Information
            FadeInUp(
              delay: const Duration(milliseconds: 100),
              child: _buildContactCard(
                icon: Icons.location_on_outlined,
                title: 'Address',
                content: 'Near Sarvodaya Vidyalaya, Sarvodaya Nagar, Datta Nagar, Ambernath West 421505',
                color: Colors.orangeAccent,
              ),
            ),
            const SizedBox(height: 20),
            
            FadeInUp(
              delay: const Duration(milliseconds: 200),
              child: _buildContactCard(
                icon: Icons.phone_outlined,
                title: 'Phone',
                content: '7447867273 / 7447437273',
                color: AppColors.primary,
                action: () => _makeCall('7447867273'),
                actionLabel: 'Call Now',
              ),
            ),
            const SizedBox(height: 20),
            
            FadeInUp(
              delay: const Duration(milliseconds: 300),
              child: _buildContactCard(
                icon: Icons.email_outlined,
                title: 'Email',
                content: 'turfcornerstone@gmail.com',
                color: Colors.blueAccent,
                action: () => _sendEmail('turfcornerstone@gmail.com'),
                actionLabel: 'Email Us',
              ),
            ),
            
            const SizedBox(height: 40),
            FadeInUp(
              delay: const Duration(milliseconds: 400),
              child: Center(
                child: Text(
                  'Cornerstone Turf v1.0.0',
                  style: TextStyle(color: AppColors.textSecondary.withOpacity(0.4), fontSize: 12, fontWeight: FontWeight.bold),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildContactCard({
    required IconData icon,
    required String title,
    required String content,
    required Color color,
    VoidCallback? action,
    String? actionLabel,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.green200),
        boxShadow: [
          BoxShadow(
            color: AppColors.green800.withOpacity(0.04),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: color.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(icon, color: color, size: 24),
              ),
              const SizedBox(width: 15),
              Text(
                title,
                style: const TextStyle(
                  color: AppColors.textMain,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ],
          ),
          const SizedBox(height: 15),
          Text(
            content,
            style: const TextStyle(color: AppColors.textSecondary, fontSize: 14, height: 1.5),
          ),
          if (action != null && actionLabel != null) ...[
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: action,
                icon: Icon(Icons.arrow_forward, size: 18, color: color),
                label: Text(actionLabel, style: TextStyle(fontWeight: FontWeight.bold, color: color)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: color.withOpacity(0.12),
                  foregroundColor: color,
                  elevation: 0,
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                    side: BorderSide(color: color.withOpacity(0.3)),
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
