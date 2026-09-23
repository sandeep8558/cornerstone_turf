import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:animate_do/animate_do.dart';
import 'package:intl/intl.dart';
import 'package:share_plus/share_plus.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../controllers/booking_controller.dart';
import '../controllers/auth_controller.dart';
import '../utils/app_colors.dart';
import 'turf_detail_screen.dart';

class BookingsScreen extends StatelessWidget {
  const BookingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final BookingController controller = Get.put(BookingController());

    return Scaffold(
      backgroundColor: Colors.transparent, // Background handled by MainNavigationScreen
      body: RefreshIndicator(
        onRefresh: () => controller.fetchMyBookings(),
        child: Obx(() {
          if (controller.isLoading.value) {
            return const Center(child: CircularProgressIndicator(color: AppColors.primary));
          }

          if (controller.myBookings.isEmpty) {
            return Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.calendar_today_outlined, size: 80, color: AppColors.primary.withOpacity(0.15)),
                  const SizedBox(height: 16),
                  const Text('No bookings yet!', style: TextStyle(color: AppColors.textSecondary, fontSize: 18, fontWeight: FontWeight.bold)),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () {
                      // Navigate back to Home
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.primary,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Text('Find a Turf', style: TextStyle(fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
            );
          }

          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: controller.myBookings.length,
            itemBuilder: (context, index) {
              final booking = controller.myBookings[index];
              return FadeInUp(
                delay: Duration(milliseconds: 100 * index),
                child: _buildBookingCard(context, booking),
              );
            },
          );
        }),
      ),
    );
  }

  void _shareBookingDetails(dynamic booking) {
    try {
      DateTime bookingDate = DateTime.parse(booking['date'].toString());
      String dayOfWeek = DateFormat('E').format(bookingDate).toLowerCase();
      String amountField = '${dayOfWeek}_amount';

      double subTotal = 0;
      int totalMinutes = 0;
      if (booking['slots'] != null) {
        for (var slot in booking['slots']) {
          subTotal += double.tryParse(slot[amountField]?.toString() ?? '0') ?? 0;
          totalMinutes += int.tryParse(slot['minutes']?.toString() ?? '0') ?? 0;
        }
      }

      double discount = double.tryParse(booking['coupon_usage']?['discount_applied']?.toString() ?? '0') ?? 0;
      double totalAmount = double.tryParse(booking['amount'].toString()) ?? 0;
      
      double paidAmount = 0;
      if (booking['booking_payments'] != null) {
        for (var payment in booking['booking_payments']) {
          paidAmount += double.tryParse(payment['amount'].toString()) ?? 0;
        }
      }
      double balanceDue = totalAmount - paidAmount;

      String name = booking['user']?['name'] ?? Get.find<AuthController>().user['name'] ?? 'N/A';
      String dateStr = DateFormat('EEEE, dd MMM yyyy').format(bookingDate);

      String slotTimings = '';
      if (booking['slots'] != null && booking['slots'] is List) {
        final slots = booking['slots'] as List;
        slotTimings = slots.map((s) {
          try {
            final fromTime = DateFormat('HH:mm:ss').parse(s['from'].toString());
            final toTime = DateFormat('HH:mm:ss').parse(s['to'].toString());
            return '${DateFormat('hh:mm a').format(fromTime)} to ${DateFormat('hh:mm a').format(toTime)}';
          } catch (e) {
            return '${s['from']} to ${s['to']}';
          }
        }).join(', ');
      }

      String durationStr = '';
      if (totalMinutes > 0) {
        double hrs = totalMinutes / 60.0;
        if (hrs % 1 == 0) {
          durationStr = '${hrs.toInt()} hrs';
        } else {
          durationStr = '${hrs.toStringAsFixed(1)} hrs';
        }
      } else {
        durationStr = 'N/A';
      }

      String boxName = booking['turf']?['name'] ?? 'Cornerstone';

      final message = '''📋 Booking Details 
━━━━━━━━━━━━━━━
👤 Name : $name
📅 Date : $dateStr
⏰ Time : $slotTimings
⏱️ Duration : $durationStr
🟩 Box : $boxName

💳 Payment Summary
Sub Total : ₹ ${subTotal.toStringAsFixed(0)}
Discount : ₹ ${discount.toStringAsFixed(0)}
Total Amount : ₹ ${totalAmount.toStringAsFixed(0)}
Advance Paid : ₹ ${paidAmount.toStringAsFixed(0)}
Due Amount : ₹ ${balanceDue.toStringAsFixed(2)}
 
Thank you for your booking! 

Regards,
Cornerstone Turf 

⚠️ Notes : 
✦ ✦ Once a transaction is confirmed, it cannot be Cancelled & Amount are non-refundable.''';

      SharePlus.instance.share(ShareParams(text: message));
    } catch (e) {
      Get.snackbar('Error', 'Failed to share booking details: $e');
    }
  }

  Widget _buildBookingCard(BuildContext context, dynamic booking) {
    Color statusColor = AppColors.primary; // Confirmed
    if (booking['status'] == 'Pending') statusColor = Colors.orange;
    if (booking['status'] == 'Cancelled') statusColor = Colors.red;

    double paidAmount = 0;
    if (booking['booking_payments'] != null) {
      for (var payment in booking['booking_payments']) {
        paidAmount += double.tryParse(payment['amount'].toString()) ?? 0;
      }
    }
    double totalAmount = double.tryParse(booking['amount'].toString()) ?? 0;
    double balanceDue = totalAmount - paidAmount;

    String slotTimings = '';
    if (booking['slots'] != null && booking['slots'] is List && (booking['slots'] as List).isNotEmpty) {
      final slots = booking['slots'] as List;
      slotTimings = slots.map((s) {
        try {
          final fromTime = DateFormat('HH:mm:ss').parse(s['from'].toString());
          final toTime = DateFormat('HH:mm:ss').parse(s['to'].toString());
          return '${DateFormat('h:mm a').format(fromTime)} - ${DateFormat('h:mm a').format(toTime)}';
        } catch (e) {
          return '${s['from']} - ${s['to']}';
        }
      }).join(', ');
    } else {
      slotTimings = 'No slots';
    }

    return GestureDetector(
      onTap: () {
        if (booking['turf'] != null) {
          Get.to(() => TurfDetailScreen(turf: booking['turf']));
        }
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: AppColors.green200.withOpacity(0.6)),
          boxShadow: [
            BoxShadow(
              color: AppColors.green800.withOpacity(0.04),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(12),
                    child: (booking['turf']?['photos'] as List?)?.isNotEmpty == true
                        ? Image.network(
                            booking['turf']['photos'][0]['photo_url'],
                            width: 70,
                            height: 70,
                            fit: BoxFit.cover,
                            errorBuilder: (context, error, stackTrace) => Container(
                              width: 70,
                              height: 70,
                              color: AppColors.green50,
                              child: const Icon(Icons.sports_soccer, color: AppColors.primary),
                            ),
                          )
                        : Container(
                            width: 70,
                            height: 70,
                            color: AppColors.green50,
                            child: const Icon(Icons.sports_soccer, color: AppColors.primary),
                          ),
                  ),
                  const SizedBox(width: 15),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              booking['turf']?['name'] ?? 'Unknown Turf',
                              style: const TextStyle(color: AppColors.textMain, fontWeight: FontWeight.bold, fontSize: 16),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: statusColor.withOpacity(0.1),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Text(
                                booking['status'] ?? 'Unknown',
                                style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.bold),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 4),
                        Text(
                          booking['date'] != null 
                              ? DateFormat('EEE, dd MMM yyyy').format(DateTime.parse(booking['date']))
                              : 'Date not available',
                          style: const TextStyle(color: AppColors.textSecondary, fontSize: 13),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Padding(
                              padding: EdgeInsets.only(top: 2),
                              child: Icon(Icons.access_time, color: AppColors.primary, size: 14),
                            ),
                            const SizedBox(width: 4),
                            Expanded(
                              child: Text(
                                slotTimings,
                                style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
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
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 16),
              child: Divider(color: AppColors.green200),
            ),
            Padding(
              padding: const EdgeInsets.all(16.0),
              child: Column(
                children: [
                  _buildPaymentSummaryRow('Payment Method', booking['payment_type'] ?? 'N/A'),
                  const SizedBox(height: 8),
                  _buildPaymentSummaryRow('Paid Amount', '₹${paidAmount.toStringAsFixed(0)}'),
                  const SizedBox(height: 8),
                  _buildPaymentSummaryRow(
                    'Balance Due', 
                    '₹${balanceDue.toStringAsFixed(0)}',
                    valueColor: balanceDue > 0 ? Colors.orange : AppColors.primary,
                  ),
                ],
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: const BoxDecoration(
                color: AppColors.green50,
                border: Border(top: BorderSide(color: AppColors.green200)),
                borderRadius: BorderRadius.vertical(bottom: Radius.circular(20)),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text('Total Amount', style: TextStyle(color: AppColors.textSecondary, fontSize: 11)),
                      const SizedBox(height: 2),
                      Text(
                        '₹${totalAmount.toStringAsFixed(0)}',
                        style: const TextStyle(color: AppColors.textMain, fontWeight: FontWeight.bold, fontSize: 16),
                      ),
                    ],
                  ),
                  Row(
                    children: [
                      ElevatedButton.icon(
                        onPressed: () => _showQRCodeDialog(booking),
                        icon: const Icon(Icons.qr_code, size: 16),
                        label: const Text('QR Code', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      OutlinedButton.icon(
                        onPressed: () => _shareBookingDetails(booking),
                        icon: const Icon(Icons.share, size: 16),
                        label: const Text('Share', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.primary,
                          side: const BorderSide(color: AppColors.primary),
                          elevation: 0,
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
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

  Widget _buildPaymentSummaryRow(String label, String value, {Color? valueColor}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: AppColors.textSecondary, fontSize: 12)),
        Text(
          value,
          style: TextStyle(
            color: valueColor ?? AppColors.textMain,
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }

  void _showQRCodeDialog(dynamic booking) {
    String formattedDate = 'Date not available';
    if (booking['date'] != null) {
      try {
        formattedDate = DateFormat('EEE, dd MMM yyyy').format(DateTime.parse(booking['date'].toString()));
      } catch (e) {
        formattedDate = booking['date'].toString();
      }
    }

    Get.dialog(
      Dialog(
        backgroundColor: Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        child: Container(
          width: 320,
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                booking['turf']?['name'] ?? 'Booking QR Code',
                style: const TextStyle(
                  color: AppColors.textMain,
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 10),
              const Text(
                'Show this QR code to the manager at the turf',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
              ),
              const SizedBox(height: 20),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppColors.green50,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: AppColors.green200),
                ),
                child: SizedBox(
                  width: 200,
                  height: 200,
                  child: QrImageView(
                    data: 'cornerstone_booking:${booking['id']}',
                    version: QrVersions.auto,
                    size: 200.0,
                    foregroundColor: AppColors.textMain,
                  ),
                ),
              ),
              const SizedBox(height: 15),
              Text(
                'Booking ID: #${booking['id']}',
                style: const TextStyle(
                  color: AppColors.textMain,
                  fontWeight: FontWeight.bold,
                  fontSize: 14,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                formattedDate,
                style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
              ),
              const SizedBox(height: 20),
              Center(
                child: TextButton(
                  onPressed: () => Get.back(),
                  child: const Text(
                    'Close',
                    style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.bold),
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
