import 'package:intl/intl.dart';

class BookingHelper {
  static const String supportPhone = '7447867273 / 7447437273';
  static const String supportAddress =
      'Near Sarvodaya Vidyalaya, Sarvodaya Nagar, Datta Nagar, Ambernath West 421505';

  /// Generates a complete, formatted booking summary message for WhatsApp and sharing.
  static String generateBookingMessage(dynamic booking) {
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

      String boxName = booking['turf']?['name']?.toString() ?? 'Cornerstone';

      final buffer = StringBuffer();
      buffer.writeln('📋 Booking Details');
      buffer.writeln('━━━━━━━━━━━━━━━');
      buffer.writeln('👤 Name : $name');
      buffer.writeln('📅 Date : $dateStr');
      buffer.writeln('⏰ Time : $slotTimings');
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
      buffer.writeln('📞 Support Contact : $supportPhone');
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
      return '📋 Booking Details\n👤 Name : ${booking['user']?['name'] ?? 'N/A'}\nTotal Amount : ₹ ${booking['amount'] ?? '0'}\n📞 Support Contact : $supportPhone';
    }
  }
}
