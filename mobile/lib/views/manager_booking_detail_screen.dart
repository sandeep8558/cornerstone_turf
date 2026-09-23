import 'package:flutter/material.dart';
import '../utils/app_colors.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:animate_do/animate_do.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';
import '../controllers/booking_controller.dart';

class ManagerBookingDetailScreen extends StatelessWidget {
  final Map<String, dynamic> initialBooking;
  ManagerBookingDetailScreen({super.key, required this.initialBooking});

  final BookingController _bookingController = Get.find<BookingController>();

  double _getSlotPrice(dynamic slot, String dateStr) {
    final date = DateTime.tryParse(dateStr) ?? DateTime.now();
    final weekday = DateFormat('E').format(date).toLowerCase(); // mon, tue, wed, thu, fri, sat, sun
    final key = '${weekday}_amount';
    return double.tryParse(slot[key]?.toString() ?? '0') ?? 0;
  }

  String _formatTime(String? timeStr) {
    if (timeStr == null) return '';
    try {
      final time = DateFormat('HH:mm:ss').parse(timeStr);
      return DateFormat('hh:mm a').format(time);
    } catch (e) {
      return timeStr;
    }
  }

  Future<void> _makeCall(String phoneNumber) async {
    final Uri launchUri = Uri(
      scheme: 'tel',
      path: phoneNumber,
    );
    if (await canLaunchUrl(launchUri)) {
      await launchUrl(launchUri);
    } else {
      Get.snackbar('Error', 'Could not place a call to $phoneNumber');
    }
  }

  Future<void> _openWhatsapp(String phoneNumber) async {
    String cleanedNumber = phoneNumber.replaceAll(RegExp(r'\D'), '');
    if (!cleanedNumber.startsWith('91') && cleanedNumber.length == 10) {
      cleanedNumber = '91$cleanedNumber';
    }
    final Uri whatsappUri = Uri.parse("https://wa.me/$cleanedNumber");
    if (await canLaunchUrl(whatsappUri)) {
      await launchUrl(whatsappUri, mode: LaunchMode.externalApplication);
    } else {
      Get.snackbar('Error', 'Could not open WhatsApp');
    }
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
      double additionalDiscount = double.tryParse(booking['additional_discount']?.toString() ?? '0') ?? 0;
      double totalAmount = double.tryParse(booking['amount'].toString()) ?? 0;
      
      double paidAmount = 0;
      if (booking['booking_payments'] != null) {
        for (var payment in booking['booking_payments']) {
          paidAmount += double.tryParse(payment['amount'].toString()) ?? 0;
        }
      }
      double balanceDue = totalAmount - paidAmount;

      String name = booking['user']?['name'] ?? 'N/A';
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
Coupon Discount : ₹ ${discount.toStringAsFixed(0)}
Additional Discount : ₹ ${additionalDiscount.toStringAsFixed(0)}
Total Amount : ₹ ${totalAmount.toStringAsFixed(0)}
Advance Paid : ₹ ${paidAmount.toStringAsFixed(0)}
Due Amount : ₹ ${balanceDue.toStringAsFixed(2)}
 
Thank you for your booking! 

Regards,
Cornerstone Turf''';

      SharePlus.instance.share(ShareParams(text: message));
    } catch (e) {
      Get.snackbar('Error', 'Failed to share booking details: $e');
    }
  }

  void _showCollectDialog(dynamic booking, double balance) {
    final TextEditingController amountController = TextEditingController(
      text: balance.toStringAsFixed(0),
    );
    String paymentType = 'Cash';

    Get.dialog(
      AlertDialog(
        backgroundColor: Colors.white,
        title: const Text(
          'Collect Payment',
          style: TextStyle(color: AppColors.textMain, fontWeight: FontWeight.bold),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: amountController,
              keyboardType: TextInputType.number,
              style: const TextStyle(color: AppColors.textMain),
              decoration: const InputDecoration(
                labelText: 'Amount',
                labelStyle: TextStyle(color: AppColors.textSecondary),
                enabledBorder: UnderlineInputBorder(
                  borderSide: BorderSide(color: AppColors.green200),
                ),
              ),
            ),
            const SizedBox(height: 20),
            DropdownButtonFormField<String>(
              value: paymentType,
              dropdownColor: Colors.white,
              style: const TextStyle(color: AppColors.textMain),
              decoration: const InputDecoration(
                labelText: 'Payment Method',
                labelStyle: TextStyle(color: AppColors.textSecondary),
              ),
              items: ['Cash', 'UPI']
                  .map(
                    (type) => DropdownMenuItem(value: type, child: Text(type)),
                  )
                  .toList(),
              onChanged: (val) => paymentType = val!,
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Get.back(), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              double amt = double.tryParse(amountController.text) ?? 0;
              if (amt > 0) {
                Get.back(); // Close dialog immediately
                await _bookingController.collectPayment(
                  booking['id'],
                  amt,
                  paymentType,
                );
              }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
            ),
            child: const Text('Collect'),
          ),
        ],
      ),
    );
  }

  void _showStatusDialog(dynamic booking) {
    String currentStatus = booking['status'] ?? 'Pending';
    final TextEditingController playersController = TextEditingController(
      text: booking['players']?.toString() ?? '',
    );
    String? cameStatus = booking['came']; // "Yes", "No" or null
    bool isCameSwitchedOn = cameStatus == 'Yes';

    Get.dialog(
      StatefulBuilder(
        builder: (context, setDialogState) {
          return AlertDialog(
            backgroundColor: Colors.white,
            title: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Update Booking',
                  style: TextStyle(
                    color: AppColors.textMain,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '#${booking['id']} - ${booking['user']?['name'] ?? 'User'}',
                  style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                ),
              ],
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.green50,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Column(
                      children: [
                        _buildDialogInfoRow(
                          'Turf',
                          booking['turf']?['name'] ?? 'N/A',
                        ),
                        const SizedBox(height: 8),
                        _buildDialogInfoRow(
                          'Date',
                          DateFormat(
                            'dd MMM yyyy',
                          ).format(DateTime.parse(booking['date'])),
                        ),
                        const SizedBox(height: 8),
                        _buildDialogInfoRow(
                          'Current Status',
                          booking['status'] ?? 'Pending',
                          color: _getStatusColor(booking['status']),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                  const Text(
                    'Update Details',
                    style: TextStyle(
                      color: AppColors.textSecondary,
                      fontSize: 12,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<String>(
                    value: currentStatus,
                    dropdownColor: Colors.white,
                    style: const TextStyle(color: AppColors.textMain),
                    decoration: const InputDecoration(
                      labelText: 'Booking Status',
                      labelStyle: TextStyle(color: AppColors.textSecondary),
                    ),
                    items: ['Success', 'Pending', 'Failed']
                        .map(
                          (status) => DropdownMenuItem(value: status, child: Text(status)),
                        )
                        .toList(),
                    onChanged: (val) {
                      setDialogState(() {
                        currentStatus = val!;
                      });
                    },
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: playersController,
                    keyboardType: TextInputType.number,
                    style: const TextStyle(color: AppColors.textMain),
                    decoration: const InputDecoration(
                      labelText: 'Number of Players',
                      labelStyle: TextStyle(color: AppColors.textSecondary),
                      enabledBorder: UnderlineInputBorder(
                        borderSide: BorderSide(color: AppColors.green200),
                      ),
                    ),
                  ),
                  const SizedBox(height: 15),
                  SwitchListTile(
                    title: Text(
                      'Customer Came? (${isCameSwitchedOn ? 'Yes' : 'No'})',
                      style: const TextStyle(color: AppColors.textMain, fontSize: 14),
                    ),
                    value: isCameSwitchedOn,
                    activeColor: AppColors.primary,
                    onChanged: (val) {
                      setDialogState(() {
                        isCameSwitchedOn = val;
                        cameStatus = val ? 'Yes' : 'No';
                      });
                    },
                    contentPadding: EdgeInsets.zero,
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Get.back(),
                child: const Text('Cancel'),
              ),
              ElevatedButton(
                onPressed: () async {
                  int? players = int.tryParse(playersController.text);
                  Get.back(); // Close immediately
                  await _bookingController.updateManagerFields(
                    booking['id'],
                    status: currentStatus,
                    players: players,
                    came: cameStatus,
                  );
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.green800,
                  foregroundColor: Colors.white,
                ),
                child: const Text('Update'),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildDialogInfoRow(String label, String value, {Color? color}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
        ),
        Text(
          value,
          style: TextStyle(
            color: color ?? AppColors.textMain,
            fontSize: 12,
            fontWeight: FontWeight.bold,
          ),
        ),
      ],
    );
  }

  Color _getStatusColor(String? status) {
    switch (status?.toLowerCase()) {
      case 'success':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'failed':
        return Colors.red;
      default:
        return AppColors.textSecondary;
    }
  }

  @override
  Widget build(BuildContext context) {
    final int bookingId = initialBooking['id'];

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Booking Details',
          style: TextStyle(
            color: AppColors.textMain,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textMain),
          onPressed: () => Navigator.pop(context),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.share_outlined, color: AppColors.textMain),
            onPressed: () {
              final booking = _bookingController.managerBookings.firstWhere(
                (b) => b['id'] == bookingId,
                orElse: () => initialBooking,
              );
              _shareBookingDetails(booking);
            },
          ),
        ],
      ),
      body: Obx(() {
        final booking = _bookingController.managerBookings.firstWhere(
          (b) => b['id'] == bookingId,
          orElse: () => initialBooking,
        );

        final user = booking['user'] ?? {};
        final turf = booking['turf'] ?? {};
        final slots = booking['slots'] as List? ?? [];
        final amountStr = booking['amount']?.toString() ?? '0';
        final payments = booking['booking_payments'] as List? ?? [];

        double paid = 0;
        for (var p in payments) {
          paid += double.tryParse(p['amount']?.toString() ?? '0') ?? 0;
        }

        double totalAmount = double.tryParse(amountStr) ?? 0.0;
        double balance = totalAmount - paid;

        // Slot calculations
        double subTotal = 0;
        int totalMinutes = 0;
        DateTime bookingDate = DateTime.parse(booking['date'].toString());
        String dayOfWeek = DateFormat('E').format(bookingDate).toLowerCase();
        String amountField = '${dayOfWeek}_amount';
        
        for (var slot in slots) {
          subTotal += double.tryParse(slot[amountField]?.toString() ?? '0') ?? 0;
          totalMinutes += int.tryParse(slot['minutes']?.toString() ?? '0') ?? 0;
        }

        double couponDiscount = double.tryParse(booking['coupon_usage']?['discount_applied']?.toString() ?? '0') ?? 0.0;
        double additionalDiscount = double.tryParse(booking['additional_discount']?.toString() ?? '0') ?? 0.0;

        return SingleChildScrollView(
          padding: const EdgeInsets.only(left: 20, right: 20, bottom: 100),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Status & Overview Header Card
              FadeInDown(
                duration: const Duration(milliseconds: 300),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: AppColors.cardBg,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppColors.green200),
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.textMain.withOpacity(0.04),
                        blurRadius: 10,
                        offset: const Offset(0, 5),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Booking #${booking['id']}',
                                style: const TextStyle(
                                  color: AppColors.textMain,
                                  fontSize: 18,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                DateFormat('EEEE, dd MMMM yyyy').format(bookingDate),
                                style: const TextStyle(
                                  color: AppColors.textSecondary,
                                  fontSize: 13,
                                ),
                              ),
                            ],
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                            decoration: BoxDecoration(
                              color: _getStatusColor(booking['status']).withOpacity(0.1),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              booking['status'] ?? 'Pending',
                              style: TextStyle(
                                color: _getStatusColor(booking['status']),
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      const Divider(color: AppColors.green200, height: 1),
                      const SizedBox(height: 16),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceAround,
                        children: [
                          _buildHeaderMetric(
                            Icons.sports_soccer,
                            turf['name'] ?? 'Turf',
                            'Venue',
                          ),
                          _buildHeaderMetric(
                            Icons.check_circle_outline,
                            booking['came'] == 'Yes' ? 'Came' : 'No Show',
                            'Attendance',
                            color: booking['came'] == 'Yes' ? AppColors.primary : Colors.grey,
                          ),
                          _buildHeaderMetric(
                            Icons.people_outline,
                            booking['players'] != null ? '${booking['players']} Players' : 'Not Set',
                            'Capacity',
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // 2. Customer details card
              FadeInLeft(
                duration: const Duration(milliseconds: 300),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: AppColors.cardBg,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppColors.green200),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Customer details',
                        style: TextStyle(
                          color: AppColors.textMain,
                          fontSize: 15,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 16),
                      Row(
                        children: [
                          CircleAvatar(
                            backgroundColor: AppColors.primary.withOpacity(0.1),
                            radius: 25,
                            child: Text(
                              user['name']?.toString().substring(0, 1).toUpperCase() ?? 'U',
                              style: const TextStyle(
                                color: AppColors.primary,
                                fontWeight: FontWeight.bold,
                                fontSize: 18,
                              ),
                            ),
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  user['name'] ?? 'Unknown User',
                                  style: const TextStyle(
                                    color: AppColors.textMain,
                                    fontSize: 15,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  user['mobile_number'] ?? 'No Mobile Number',
                                  style: const TextStyle(
                                    color: AppColors.textSecondary,
                                    fontSize: 13,
                                  ),
                                ),
                                if (user['email'] != null) ...[
                                  const SizedBox(height: 2),
                                  Text(
                                    user['email'],
                                    style: const TextStyle(
                                      color: AppColors.textSecondary,
                                      fontSize: 12,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      const Divider(color: AppColors.green200, height: 1),
                      const SizedBox(height: 12),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                        children: [
                          ElevatedButton.icon(
                            onPressed: user['mobile_number'] != null
                                ? () => _makeCall(user['mobile_number'])
                                : null,
                            icon: const Icon(Icons.call, size: 16),
                            label: const Text('Call Client', style: TextStyle(fontSize: 12)),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            ),
                          ),
                          OutlinedButton.icon(
                            onPressed: user['mobile_number'] != null
                                ? () => _openWhatsapp(user['mobile_number'])
                                : null,
                            icon: const Icon(Icons.chat_bubble_outline, size: 16),
                            label: const Text('WhatsApp', style: TextStyle(fontSize: 12)),
                            style: OutlinedButton.styleFrom(
                              foregroundColor: AppColors.primary,
                              side: const BorderSide(color: AppColors.primary),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // 3. Slots details card
              FadeInLeft(
                duration: const Duration(milliseconds: 300),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: AppColors.cardBg,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppColors.green200),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Booked Slots',
                            style: TextStyle(
                              color: AppColors.textMain,
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            '${slots.length} Slots (${totalMinutes} mins)',
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 16),
                      ListView.separated(
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        itemCount: slots.length,
                        separatorBuilder: (context, index) => const SizedBox(height: 10),
                        itemBuilder: (context, index) {
                          final slot = slots[index];
                          final slotPrice = _getSlotPrice(slot, booking['date']);
                          return Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: AppColors.green50.withOpacity(0.5),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: AppColors.green100),
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Row(
                                  children: [
                                    const Icon(Icons.schedule, color: AppColors.primary, size: 18),
                                    const SizedBox(width: 8),
                                    Text(
                                      '${_formatTime(slot['from'])} - ${_formatTime(slot['to'])}',
                                      style: const TextStyle(
                                        color: AppColors.textMain,
                                        fontSize: 14,
                                        fontWeight: FontWeight.w600,
                                      ),
                                    ),
                                  ],
                                ),
                                Text(
                                  '₹${slotPrice.toStringAsFixed(0)}',
                                  style: const TextStyle(
                                    color: AppColors.textMain,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // 4. Detailed Bill Breakup Card
              FadeInRight(
                duration: const Duration(milliseconds: 300),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: AppColors.cardBg,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: AppColors.green200),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Payment Summary',
                        style: TextStyle(
                          color: AppColors.textMain,
                          fontSize: 15,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 16),
                      _buildBillRow('Sub Total', '₹${subTotal.toStringAsFixed(2)}'),
                      if (couponDiscount > 0) ...[
                        const SizedBox(height: 8),
                        _buildBillRow(
                          'Coupon Discount (${booking['coupon_usage']?['coupon']?['code'] ?? 'Coupon'})',
                          '-₹${couponDiscount.toStringAsFixed(2)}',
                          color: Colors.green,
                        ),
                      ],
                      if (additionalDiscount > 0) ...[
                        const SizedBox(height: 8),
                        _buildBillRow(
                          'Additional Discount',
                          '-₹${additionalDiscount.toStringAsFixed(2)}',
                          color: Colors.green,
                        ),
                      ],
                      const SizedBox(height: 12),
                      const Divider(color: AppColors.green200, height: 1),
                      const SizedBox(height: 12),
                      _buildBillRow(
                        'Total Bill',
                        '₹${totalAmount.toStringAsFixed(2)}',
                        isBold: true,
                        fontSize: 16,
                      ),
                      const SizedBox(height: 8),
                      _buildBillRow(
                        'Paid Amount',
                        '₹${paid.toStringAsFixed(2)}',
                        color: AppColors.primary,
                        isBold: true,
                      ),
                      const SizedBox(height: 8),
                      _buildBillRow(
                        'Balance Due',
                        '₹${balance.toStringAsFixed(2)}',
                        color: balance > 0 ? Colors.redAccent : Colors.grey,
                        isBold: true,
                      ),
                      const SizedBox(height: 12),
                      const Divider(color: AppColors.green200, height: 1),
                      const SizedBox(height: 12),
                      _buildBillRow(
                        'Payment Option Mode',
                        booking['payment_type'] ?? 'PayAtLocation',
                        color: AppColors.textSecondary,
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // 5. Payment Transaction History
              if (payments.isNotEmpty) ...[
                FadeInUp(
                  duration: const Duration(milliseconds: 300),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: AppColors.cardBg,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: AppColors.green200),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Transactions Received',
                          style: TextStyle(
                            color: AppColors.textMain,
                            fontSize: 15,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        const SizedBox(height: 16),
                        ListView.separated(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          itemCount: payments.length,
                          separatorBuilder: (context, index) => const Divider(color: AppColors.green100, height: 20),
                          itemBuilder: (context, index) {
                            final payment = payments[index];
                            final payDate = DateTime.tryParse(payment['created_at']?.toString() ?? '') ?? DateTime.now();
                            final payAmount = double.tryParse(payment['amount']?.toString() ?? '0') ?? 0.0;
                            return Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Offline ${payment['type'] ?? 'Cash'} Receipt',
                                      style: const TextStyle(
                                        color: AppColors.textMain,
                                        fontWeight: FontWeight.w600,
                                        fontSize: 14,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      DateFormat('dd MMM yyyy, hh:mm a').format(payDate),
                                      style: const TextStyle(
                                        color: AppColors.textSecondary,
                                        fontSize: 11,
                                      ),
                                    ),
                                  ],
                                ),
                                Text(
                                  '+₹${payAmount.toStringAsFixed(0)}',
                                  style: const TextStyle(
                                    color: AppColors.primary,
                                    fontWeight: FontWeight.bold,
                                    fontSize: 15,
                                  ),
                                ),
                              ],
                            );
                          },
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ],
          ),
        );
      }),
      bottomSheet: Obx(() {
        final booking = _bookingController.managerBookings.firstWhere(
          (b) => b['id'] == bookingId,
          orElse: () => initialBooking,
        );
        final amountStr = booking['amount']?.toString() ?? '0';
        final payments = booking['booking_payments'] as List? ?? [];
        double paid = 0;
        for (var p in payments) {
          paid += double.tryParse(p['amount']?.toString() ?? '0') ?? 0;
        }
        double totalAmount = double.tryParse(amountStr) ?? 0.0;
        double balance = totalAmount - paid;

        final double systemBottom = MediaQuery.of(context).padding.bottom;
        final double safeBottom = systemBottom > 0 ? systemBottom + 8 : 28.0;

        return Container(
          color: Colors.white,
          padding: EdgeInsets.only(
            left: 20,
            right: 20,
            top: 12,
            bottom: safeBottom,
          ),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: balance <= 0
                      ? null
                      : () => _showCollectDialog(booking, balance),
                  icon: const Icon(
                    Icons.account_balance_wallet_outlined,
                    size: 16,
                  ),
                  label: const Text(
                    'Collect',
                    style: TextStyle(fontSize: 12),
                  ),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.primary,
                    side: const BorderSide(color: AppColors.primary),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () => _showStatusDialog(booking),
                  icon: const Icon(
                    Icons.edit_calendar_outlined,
                    size: 16,
                  ),
                  label: const Text(
                    'Status',
                    style: TextStyle(fontSize: 12),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.green800,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton.icon(
                  onPressed: () => _shareBookingDetails(booking),
                  icon: const Icon(
                    Icons.share,
                    size: 16,
                  ),
                  label: const Text(
                    'Share',
                    style: TextStyle(fontSize: 12),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      }),
    );
  }

  Widget _buildHeaderMetric(IconData icon, String value, String label, {Color? color}) {
    return Column(
      children: [
        Icon(icon, color: color ?? AppColors.primary, size: 20),
        const SizedBox(height: 6),
        Text(
          value,
          style: const TextStyle(
            color: AppColors.textMain,
            fontWeight: FontWeight.bold,
            fontSize: 13,
          ),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: const TextStyle(
            color: AppColors.textSecondary,
            fontSize: 10,
          ),
        ),
      ],
    );
  }

  Widget _buildBillRow(String label, String value, {Color? color, bool isBold = false, double fontSize = 14}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            color: isBold ? AppColors.textMain : AppColors.textSecondary,
            fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
            fontSize: fontSize,
          ),
        ),
        Text(
          value,
          style: TextStyle(
            color: color ?? AppColors.textMain,
            fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
            fontSize: fontSize,
          ),
        ),
      ],
    );
  }
}
