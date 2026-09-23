import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:animate_do/animate_do.dart';
import 'package:flutter/services.dart';
import '../controllers/turf_controller.dart';
import '../utils/app_colors.dart';
import 'app_drawer.dart';

class OffersScreen extends StatelessWidget {
  const OffersScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final TurfController controller = Get.find<TurfController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      drawer: const AppDrawer(currentRoute: 'offers'),
      appBar: AppBar(
        title: const Text('Offers & Coupons', style: TextStyle(color: AppColors.textMain, fontWeight: FontWeight.bold)),
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
      body: Obx(() {
        if (controller.isLoading.value && controller.offers.isEmpty) {
          return const Center(child: CircularProgressIndicator(color: AppColors.primary));
        }

        if (controller.offers.isEmpty) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.local_offer_outlined, size: 80, color: AppColors.primary.withOpacity(0.15)),
                const SizedBox(height: 16),
                const Text(
                  'No offers available at the moment',
                  style: TextStyle(color: AppColors.textSecondary, fontWeight: FontWeight.bold, fontSize: 16),
                ),
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: () => controller.fetchOffers(),
          color: AppColors.primary,
          child: ListView.builder(
            padding: const EdgeInsets.all(20),
            itemCount: controller.offers.length,
            itemBuilder: (context, index) {
              final offer = controller.offers[index];
              return FadeInUp(
                delay: Duration(milliseconds: index * 100),
                child: _buildCouponCard(offer, context),
              );
            },
          ),
        );
      }),
    );
  }

  Widget _buildCouponCard(dynamic offer, BuildContext context) {
    bool isPercentage = offer['discount_type'].toString().toLowerCase() == 'percentage';
    String discountText = isPercentage 
      ? '${offer['discount_value']}% OFF' 
      : '₹${offer['discount_value']} OFF';

    List<String> activeDays = [];
    final dayNames = {
      'mon': 'Mon',
      'tue': 'Tue',
      'wed': 'Wed',
      'thu': 'Thu',
      'fri': 'Fri',
      'sat': 'Sat',
      'sun': 'Sun',
    };
    dayNames.forEach((key, label) {
      if (offer[key] == true || offer[key] == 1 || offer[key] == '1') {
        activeDays.add(label);
      }
    });

    String daysText = '';
    if (activeDays.length == 7) {
      daysText = 'All Days';
    } else if (activeDays.isEmpty) {
      daysText = 'No Days';
    } else {
      daysText = activeDays.join(', ');
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 20),
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
      child: ClipRRect(
        borderRadius: BorderRadius.circular(20),
        child: Stack(
          children: [
            // Discount Circle Decoration
            Positioned(
              right: -30,
              top: -30,
              child: Container(
                width: 100,
                height: 100,
                decoration: BoxDecoration(
                  color: AppColors.primary.withOpacity(0.06),
                  shape: BoxShape.circle,
                ),
              ),
            ),
            
            Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: AppColors.primary.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: AppColors.green200),
                        ),
                        child: Text(
                          discountText,
                          style: const TextStyle(
                            color: AppColors.primary,
                            fontWeight: FontWeight.bold,
                            fontSize: 14,
                          ),
                        ),
                      ),
                      if (offer['expires_at'] != null)
                        Text(
                          'Expires: ${DateFormat('dd MMM').format(DateTime.parse(offer['expires_at']))}',
                          style: const TextStyle(color: AppColors.textSecondary, fontSize: 12, fontWeight: FontWeight.w500),
                        ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  Text(
                    offer['code'].toString().toUpperCase(),
                    style: const TextStyle(
                      color: AppColors.textMain,
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 1.5,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    offer['description'] ?? 'Exclusive discount for you',
                    style: const TextStyle(color: AppColors.textSecondary, fontSize: 13),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Padding(
                        padding: EdgeInsets.only(top: 1.0),
                        child: Icon(Icons.calendar_today_outlined, size: 14, color: AppColors.primary),
                      ),
                      const SizedBox(width: 6),
                      Expanded(
                        child: Text(
                          'Valid on: $daysText',
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                          ),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),
                  
                  // Dotted Divider logic
                  Row(
                    children: List.generate(
                      150 ~/ 5,
                      (index) => Expanded(
                        child: Container(
                          color: index % 2 == 0 ? Colors.transparent : AppColors.green200,
                          height: 1,
                        ),
                      ),
                    ),
                  ),
                  
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (offer['minimum_order_value'] != null)
                            Text(
                              'Min Booking: ₹${offer['minimum_order_value']}',
                              style: const TextStyle(color: AppColors.textSecondary, fontSize: 12, fontWeight: FontWeight.w500),
                            ),
                          if (offer['max_discount_amount'] != null)
                            Text(
                              'Max Discount: ₹${offer['max_discount_amount']}',
                              style: const TextStyle(color: AppColors.textSecondary, fontSize: 12, fontWeight: FontWeight.w500),
                            ),
                        ],
                      ),
                      TextButton(
                        onPressed: () {
                          Clipboard.setData(ClipboardData(text: offer['code'].toString()));
                          Get.snackbar(
                            'Copied!',
                            'Code ${offer['code']} copied to clipboard',
                            snackPosition: SnackPosition.BOTTOM,
                            backgroundColor: AppColors.primary,
                            colorText: Colors.white,
                            margin: const EdgeInsets.all(15),
                            duration: const Duration(seconds: 2),
                          );
                        },
                        style: TextButton.styleFrom(
                          backgroundColor: AppColors.green50,
                          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(30),
                            side: const BorderSide(color: AppColors.green200),
                          ),
                        ),
                        child: const Text(
                          'COPY CODE',
                          style: TextStyle(color: AppColors.primary, fontSize: 12, fontWeight: FontWeight.bold),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
