import 'package:get/get.dart';
import 'package:intl/intl.dart';
import '../controllers/booking_controller.dart';

class BookingHelper {
  static const String supportPhone = '7447867273 / 7447437273';
  static const String supportAddress =
      'Near Sarvodaya Vidyalaya, Sarvodaya Nagar, Datta Nagar, Ambernath West 421505';

  static String _formatTimeStr(String? timeStr, {bool isEnd = false}) {
    if (timeStr == null || timeStr.isEmpty) return '';
    try {
      if (isEnd && (timeStr.startsWith('23:59') || timeStr.startsWith('24:00'))) {
        return '12:00 AM';
      }
      final parsed = DateFormat('HH:mm:ss').parse(timeStr);
      if (isEnd && parsed.hour == 23 && parsed.minute >= 59) {
        return '12:00 AM';
      }
      return DateFormat('hh:mm a').format(parsed);
    } catch (e) {
      return timeStr;
    }
  }

  /// Generates a complete, formatted booking summary message for WhatsApp and sharing.
  /// If a custom template is configured in Admin Settings, it interpolates dynamic tags.
  static String generateBookingMessage(
    dynamic booking, {
    Map<String, dynamic>? customSettings,
  }) {
    String currentSupportPhone = supportPhone;
    String? configuredTemplate;

    try {
      Map<dynamic, dynamic>? settings = customSettings;
      if (settings == null && Get.isRegistered<BookingController>()) {
        settings = Get.find<BookingController>().systemSettings;
      }

      if (settings != null) {
        if (settings['support_phone'] != null &&
            settings['support_phone'].toString().trim().isNotEmpty) {
          currentSupportPhone = settings['support_phone'].toString().trim();
        }
        if (settings['whatsapp_booking_message'] != null &&
            settings['whatsapp_booking_message'].toString().trim().isNotEmpty) {
          configuredTemplate = settings['whatsapp_booking_message'].toString();
        }
      }
    } catch (_) {}

    try {
      DateTime bookingDate = DateTime.parse(booking['date'].toString());
      String dayOfWeek = DateFormat('E').format(bookingDate).toLowerCase();
      String amountField = '${dayOfWeek}_amount';

      double subTotal = 0;
      int totalMinutes = 0;
      if (booking['slots'] != null && booking['slots'] is List) {
        for (var slot in booking['slots']) {
          double slotPrice = double.tryParse(
            slot[amountField]?.toString() ??
                slot['pivot']?[amountField]?.toString() ??
                slot['amount']?.toString() ??
                '0',
          ) ??
              0;
          subTotal += slotPrice;
          totalMinutes += int.tryParse(slot['minutes']?.toString() ?? '0') ?? 0;
        }
      }

      double totalAmount = double.tryParse(booking['amount']?.toString() ?? '0') ?? 0;
      if (subTotal == 0 && totalAmount > 0) {
        subTotal = totalAmount;
      }

      final couponUsage = booking['coupon_usage'] ?? booking['couponUsage'];
      double couponDiscount = double.tryParse(
        couponUsage?['discount_applied']?.toString() ??
            booking['discount']?.toString() ??
            booking['coupon_discount']?.toString() ??
            '0',
      ) ??
          0;

      double additionalDiscount = double.tryParse(
        booking['additional_discount']?.toString() ?? '0',
      ) ??
          0;

      // If discounts are not directly in payload but subtotal exceeds total amount:
      double calculatedDiff = subTotal - totalAmount - additionalDiscount;
      if (calculatedDiff > 0 && couponDiscount == 0) {
        couponDiscount = calculatedDiff;
      }

      // Ensure subTotal matches totalAmount + discounts
      if (subTotal < totalAmount + couponDiscount + additionalDiscount) {
        subTotal = totalAmount + couponDiscount + additionalDiscount;
      }

      double paidAmount = 0;
      final payments = booking['booking_payments'] ??
          booking['bookingPayments'] ??
          booking['payments'];
      if (payments != null && payments is List) {
        for (var payment in payments) {
          paidAmount += double.tryParse(payment['amount']?.toString() ?? '0') ?? 0;
        }
      } else if (booking['total_received'] != null) {
        paidAmount = double.tryParse(booking['total_received']?.toString() ?? '0') ?? 0;
      }

      double balanceDue = totalAmount - paidAmount;
      if (balanceDue < 0) balanceDue = 0;

      String name = booking['user']?['name']?.toString() ?? 'N/A';
      String dateStr = DateFormat('EEEE, dd MMM yyyy').format(bookingDate);

      String slotsStr = '';
      String timeStr = '';
      if (booking['slots'] != null && booking['slots'] is List) {
        final rawSlots = List<dynamic>.from(booking['slots']);
        // Sort slots by from time
        rawSlots.sort((a, b) =>
            (a['from']?.toString() ?? '').compareTo(b['from']?.toString() ?? ''));

        // 1. slotsStr (all individual 30-min slots)
        slotsStr = rawSlots.map((s) {
          final from = _formatTimeStr(s['from']?.toString());
          final to = _formatTimeStr(s['to']?.toString(), isEnd: true);
          return '$from to $to';
        }).join(', ');

        // 2. timeStr (start time and end time of all consecutive slots)
        List<Map<String, String>> ranges = [];
        Map<String, String>? currentRange;

        for (var slot in rawSlots) {
          String from = slot['from']?.toString() ?? '';
          String to = slot['to']?.toString() ?? '';
          if (from.isEmpty || to.isEmpty) continue;

          if (currentRange == null) {
            currentRange = {'from': from, 'to': to};
          } else {
            String prevTo = currentRange['to'] ?? '';
            String prevToHhMm = prevTo.length >= 5 ? prevTo.substring(0, 5) : prevTo;
            String curFromHhMm = from.length >= 5 ? from.substring(0, 5) : from;

            if (prevToHhMm == curFromHhMm || (prevTo.startsWith('23:59') && from.startsWith('00:00'))) {
              currentRange['to'] = to;
            } else {
              ranges.add(currentRange);
              currentRange = {'from': from, 'to': to};
            }
          }
        }
        if (currentRange != null) {
          ranges.add(currentRange);
        }

        timeStr = ranges.map((r) {
          final from = _formatTimeStr(r['from']);
          final to = _formatTimeStr(r['to'], isEnd: true);
          return '$from to $to';
        }).join(', ');
      }

      String durationStr = '';
      if (totalMinutes > 0) {
        double hrs = totalMinutes / 60.0;
        if (hrs % 1 == 0) {
          int h = hrs.toInt();
          durationStr = '$h ${h == 1 ? 'hr' : 'hrs'}';
        } else {
          durationStr = '${hrs.toStringAsFixed(1)} hrs';
        }
      } else {
        durationStr = 'N/A';
      }

      String boxName = booking['turf']?['name']?.toString() ?? 'Cornerstone';

      if (configuredTemplate != null && configuredTemplate.trim().isNotEmpty) {
        return configuredTemplate
            .replaceAll('{name}', name)
            .replaceAll('{date}', dateStr)
            .replaceAll('{time}', timeStr)
            .replaceAll('{slots}', slotsStr)
            .replaceAll('{duration}', durationStr)
            .replaceAll('{box}', boxName)
            .replaceAll('{sub_total}', subTotal.toStringAsFixed(0))
            .replaceAll('{coupon_discount}', couponDiscount.toStringAsFixed(0))
            .replaceAll('{additional_discount}', additionalDiscount.toStringAsFixed(0))
            .replaceAll('{total_amount}', totalAmount.toStringAsFixed(0))
            .replaceAll('{paid_amount}', paidAmount.toStringAsFixed(0))
            .replaceAll('{due_amount}', balanceDue.toStringAsFixed(2))
            .replaceAll('{support_contact}', currentSupportPhone);
      }

      final buffer = StringBuffer();
      buffer.writeln('📋 Booking Details');
      buffer.writeln('━━━━━━━━━━━━━━━');
      buffer.writeln('👤 Name : $name');
      buffer.writeln('📅 Date : $dateStr');
      buffer.writeln('⏰ Time : $timeStr');
      buffer.writeln('⏱️ Duration : $durationStr');
      buffer.writeln('🟩 Box : $boxName');
      buffer.writeln();
      buffer.writeln('💳 Payment Summary');
      buffer.writeln('Sub Total : ₹ ${subTotal.toStringAsFixed(0)}');
      buffer.writeln('Coupon Discount : ₹ ${couponDiscount.toStringAsFixed(0)}');
      buffer.writeln('Additional Discount : ₹ ${additionalDiscount.toStringAsFixed(0)}');
      buffer.writeln('Total Amount : ₹ ${totalAmount.toStringAsFixed(0)}');
      buffer.writeln('Advance Paid : ₹ ${paidAmount.toStringAsFixed(0)}');
      buffer.writeln('Due Amount : ₹ ${balanceDue.toStringAsFixed(2)}');
      buffer.writeln();
      buffer.writeln('📞 Support Contact : $currentSupportPhone');
      buffer.writeln();
      buffer.writeln('Thank you for your booking!');
      buffer.writeln();
      buffer.writeln('Regards,');
      buffer.writeln('Cornerstone Turf');
      buffer.writeln();
      buffer.writeln('⚠️ Notes :');
      buffer.write('✦ Once a transaction is confirmed, it cannot be Cancelled & Amounts are non-refundable.');

      return buffer.toString();
    } catch (e) {
      return '📋 Booking Details\n👤 Name : ${booking['user']?['name'] ?? 'N/A'}\nTotal Amount : ₹ ${booking['amount'] ?? '0'}\n📞 Support Contact : $currentSupportPhone';
    }
  }
}
