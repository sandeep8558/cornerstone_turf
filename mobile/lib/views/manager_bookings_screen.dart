import 'package:flutter/material.dart';
import '../utils/app_colors.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:animate_do/animate_do.dart';
import 'package:share_plus/share_plus.dart';
import '../controllers/booking_controller.dart';
import 'app_drawer.dart';
import 'manager_booking_detail_screen.dart';
import 'manager_qr_scanner_screen.dart';

class ManagerBookingsScreen extends StatefulWidget {
  const ManagerBookingsScreen({super.key});

  @override
  State<ManagerBookingsScreen> createState() => _ManagerBookingsScreenState();
}

class _ManagerBookingsScreenState extends State<ManagerBookingsScreen> {
  final BookingController _bookingController = Get.find<BookingController>();
  DateTime _selectedDate = DateTime.now();
  String _selectedFilter = 'Upcoming';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _fetchBookings();
    });
  }

  void _fetchBookings() {
    _bookingController.fetchManagerBookings(
      DateFormat('yyyy-MM-dd').format(_selectedDate),
    );
  }

  List<dynamic> _getFilteredBookings() {
    final now = DateTime.now();
    final allBookings = _bookingController.managerBookings;

    if (_selectedFilter == 'All') return allBookings.toList();

    return allBookings.where((booking) {
      final slots = booking['slots'] as List? ?? [];
      if (slots.isEmpty) return false;

      try {
        String firstSlotFrom = slots.first['from'];
        String bookingDateStr = booking['date'];
        DateTime slotDateTime = DateFormat(
          'yyyy-MM-dd HH:mm:ss',
        ).parse('$bookingDateStr $firstSlotFrom');

        if (_selectedFilter == 'Upcoming') {
          return slotDateTime.isAfter(now) ||
              slotDateTime.isAtSameMomentAs(now);
        } else if (_selectedFilter == 'Past') {
          return slotDateTime.isBefore(now);
        }
      } catch (e) {
        return true;
      }
      return true;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      drawer: const AppDrawer(currentRoute: 'manager'),
      appBar: AppBar(
        title: const Text(
          'Manager Dashboard',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: Builder(
          builder: (context) => IconButton(
            icon: const Icon(Icons.menu, color: AppColors.textMain),
            onPressed: () => Scaffold.of(context).openDrawer(),
          ),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.qr_code_scanner, color: AppColors.textMain),
            onPressed: () => Get.to(() => const ManagerQRScannerScreen()),
          ),
        ],
      ),
      body: Column(
        children: [
          _buildDateSelector(),
          _buildFilterSelector(),
          Expanded(
            child: Obx(() {
              if (_bookingController.isLoading.value &&
                  _bookingController.managerBookings.isEmpty) {
                return const Center(
                  child: CircularProgressIndicator(color: AppColors.primary),
                );
              }

              final filteredBookings = _getFilteredBookings();

              if (filteredBookings.isEmpty) {
                return Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.calendar_today_outlined,
                        size: 80,
                        color: AppColors.green200,
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'No $_selectedFilter bookings for this date',
                        style: const TextStyle(color: AppColors.textSecondary),
                      ),
                    ],
                  ),
                );
              }

              return RefreshIndicator(
                onRefresh: () async => _fetchBookings(),
                color: AppColors.primary,
                child: ListView.builder(
                  padding: const EdgeInsets.all(20),
                  itemCount: filteredBookings.length,
                  itemBuilder: (context, index) {
                    final booking = filteredBookings[index];
                    return FadeInUp(
                      delay: Duration(milliseconds: index * 50),
                      child: _buildBookingCard(booking),
                    );
                  },
                ),
              );
            }),
          ),
        ],
      ),
    );
  }

  Widget _buildFilterSelector() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 15),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: ['All', 'Upcoming', 'Past'].map((filter) {
          bool isSelected = _selectedFilter == filter;
          return Expanded(
            child: GestureDetector(
              onTap: () {
                setState(() => _selectedFilter = filter);
              },
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 4),
                padding: const EdgeInsets.symmetric(vertical: 8),
                decoration: BoxDecoration(
                  color: isSelected
                      ? AppColors.primary
                      : AppColors.cardBg,
                  borderRadius: BorderRadius.circular(10),
                  border: isSelected
                      ? null
                      : Border.all(color: AppColors.green200),
                ),
                alignment: Alignment.center,
                child: Text(
                  filter,
                  style: TextStyle(
                    color: isSelected ? Colors.white : AppColors.textMain,
                    fontWeight: isSelected
                        ? FontWeight.bold
                        : FontWeight.normal,
                    fontSize: 13,
                  ),
                ),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildDateSelector() {
    final now = DateTime.now();
    final isToday = DateFormat('yyyy-MM-dd').format(_selectedDate) ==
        DateFormat('yyyy-MM-dd').format(now);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: AppColors.green50,
        border: Border(
          bottom: BorderSide(color: AppColors.green200),
        ),
      ),
      child: Row(
        children: [
          // Previous Date Button (<)
          Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: () {
                setState(() {
                  _selectedDate =
                      _selectedDate.subtract(const Duration(days: 1));
                });
                _fetchBookings();
              },
              child: Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.green200),
                ),
                child: const Icon(
                  Icons.chevron_left_rounded,
                  color: AppColors.primary,
                  size: 28,
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),

          // Center Date Display Card (Clickable to pick date)
          Expanded(
            child: Material(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
              child: InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: () async {
                  final DateTime? picked = await showDatePicker(
                    context: context,
                    initialDate: _selectedDate,
                    firstDate: DateTime(2020),
                    lastDate: DateTime(2035),
                    builder: (context, child) {
                      return Theme(
                        data: Theme.of(context).copyWith(
                          colorScheme: const ColorScheme.light(
                            primary: AppColors.primary,
                            onPrimary: Colors.white,
                            surface: Colors.white,
                            onSurface: AppColors.textMain,
                          ),
                        ),
                        child: child!,
                      );
                    },
                  );
                  if (picked != null && picked != _selectedDate) {
                    setState(() {
                      _selectedDate = picked;
                    });
                    _fetchBookings();
                  }
                },
                child: Container(
                  height: 44,
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: isToday ? AppColors.primary : AppColors.green200,
                      width: isToday ? 1.5 : 1,
                    ),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.calendar_today_rounded,
                        size: 16,
                        color:
                            isToday ? AppColors.primary : AppColors.textSecondary,
                      ),
                      const SizedBox(width: 6),
                      Flexible(
                        child: Text(
                          isToday
                              ? 'Today, ${DateFormat('dd MMM yyyy').format(_selectedDate)}'
                              : DateFormat('EEE, dd MMM yyyy')
                                  .format(_selectedDate),
                          style: TextStyle(
                            color: isToday
                                ? AppColors.primary
                                : AppColors.textMain,
                            fontWeight: FontWeight.bold,
                            fontSize: 13,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: 2),
                      const Icon(
                        Icons.arrow_drop_down,
                        color: AppColors.textSecondary,
                        size: 18,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 8),

          // Next Date Button (>)
          Material(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            child: InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: () {
                setState(() {
                  _selectedDate = _selectedDate.add(const Duration(days: 1));
                });
                _fetchBookings();
              },
              child: Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.green200),
                ),
                child: const Icon(
                  Icons.chevron_right_rounded,
                  color: AppColors.primary,
                  size: 28,
                ),
              ),
            ),
          ),

          // Quick 'Today' Button when selected date is far from today
          if (!isToday) ...[
            const SizedBox(width: 8),
            Material(
              color: AppColors.primary,
              borderRadius: BorderRadius.circular(12),
              elevation: 1,
              child: InkWell(
                borderRadius: BorderRadius.circular(12),
                onTap: () {
                  setState(() {
                    _selectedDate = DateTime.now();
                  });
                  _fetchBookings();
                },
                child: Container(
                  height: 44,
                  padding: const EdgeInsets.symmetric(horizontal: 10),
                  alignment: Alignment.center,
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.today_rounded,
                        color: Colors.white,
                        size: 15,
                      ),
                      SizedBox(width: 4),
                      Text(
                        'Today',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ],
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

  Widget _buildBookingCard(dynamic booking) {
    final user = booking['user'] ?? {};
    final turf = booking['turf'] ?? {};
    final slots = booking['slots'] as List? ?? [];
    final amount = booking['amount']?.toString() ?? '0';
    final payments = booking['booking_payments'] as List? ?? [];
    double paid = 0;
    for (var p in payments) {
      paid += double.tryParse(p['amount']?.toString() ?? '0') ?? 0;
    }
    double balance = (double.tryParse(amount) ?? 0) - paid;

    return InkWell(
      onTap: () {
        Get.to(() => ManagerBookingDetailScreen(initialBooking: booking));
      },
      borderRadius: BorderRadius.circular(20),
      child: Container(
        margin: const EdgeInsets.only(bottom: 16),
        decoration: BoxDecoration(
          color: AppColors.cardBg,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: AppColors.green200),
        ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(
                  backgroundColor: AppColors.primary.withOpacity(0.1),
                  radius: 25,
                  child: Text(
                    user['name']?.toString().substring(0, 1).toUpperCase() ??
                        'U',
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            user['name'] ?? 'Unknown User',
                            style: const TextStyle(
                              color: AppColors.textMain,
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: booking['payment_type'] == 'PayAtLocation'
                                  ? Colors.orange.withOpacity(0.1)
                                  : AppColors.primary.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              booking['payment_type'] ?? 'Standard',
                              style: TextStyle(
                                color:
                                    booking['payment_type'] == 'PayAtLocation'
                                    ? Colors.orange
                                    : AppColors.primary,
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        user['mobile_number'] ?? 'No mobile',
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          const Icon(
                            Icons.sports_soccer,
                            color: AppColors.primary,
                            size: 14,
                          ),
                          const SizedBox(width: 6),
                          Text(
                            turf['name'] ?? 'Turf',
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                      if (booking['players'] != null ||
                          (booking['came'] != null && booking['came'] == 1))
                        Padding(
                          padding: const EdgeInsets.only(top: 8.0),
                          child: Row(
                            children: [
                              if (booking['players'] != null)
                                _buildSmallBadge(
                                  Icons.people_outline,
                                  '${booking['players']} Players',
                                  Colors.blueAccent,
                                ),
                              if (booking['players'] != null &&
                                  booking['came'] == 'Yes')
                                const SizedBox(width: 8),
                              if (booking['came'] == 'Yes')
                                _buildSmallBadge(
                                  Icons.check_circle_outline,
                                  'Came',
                                  AppColors.primary,
                                ),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.green50.withOpacity(0.4),
              borderRadius: const BorderRadius.vertical(
                bottom: Radius.circular(20),
              ),
            ),
            child: Column(
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Time Slots',
                      style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                    ),
                    Text(
                      '${slots.length} Slots',
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: slots
                      .map(
                        (slot) => Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: AppColors.background,
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(
                              color: AppColors.green50,
                            ),
                          ),
                          child: Text(
                            '${_formatTime(slot['from'])} - ${_formatTime(slot['to'])}',
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 10,
                            ),
                          ),
                        ),
                      )
                      .toList(),
                ),
                const Divider(height: 24, color: AppColors.green200),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    _buildPaymentInfo('Total', '₹$amount', AppColors.textMain),
                    _buildPaymentInfo(
                      'Paid',
                      '₹${paid.toStringAsFixed(0)}',
                      AppColors.primary,
                    ),
                    _buildPaymentInfo(
                      'Balance',
                      '₹${balance.toStringAsFixed(0)}',
                      balance > 0 ? Colors.redAccent : AppColors.textSecondary,
                    ),
                  ],
                ),
                const Divider(height: 24, color: AppColors.green200),
                Row(
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
                          padding: const EdgeInsets.symmetric(vertical: 12),
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
                          padding: const EdgeInsets.symmetric(vertical: 12),
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
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
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
    ),);
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
          style: const TextStyle(color: AppColors.textMain),
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
                labelStyle: const TextStyle(color: AppColors.textSecondary),
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
                labelStyle: const TextStyle(color: AppColors.textSecondary),
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
                  TextField(
                    controller: playersController,
                    keyboardType: TextInputType.number,
                    style: const TextStyle(color: AppColors.textMain),
                    decoration: const InputDecoration(
                      labelText: 'Number of Players',
                      labelStyle: const TextStyle(color: AppColors.textSecondary),
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

  Widget _buildSmallBadge(IconData icon, String label, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(4),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 10, color: color),
          const SizedBox(width: 4),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 9,
              fontWeight: FontWeight.bold,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDialogInfoRow(
    String label,
    String value, {
    Color color = Colors.white70,
  }) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(color: AppColors.textSecondary.withOpacity(0.5), fontSize: 11),
        ),
        Text(
          value,
          style: TextStyle(
            color: color,
            fontSize: 11,
            fontWeight: FontWeight.bold,
          ),
        ),
      ],
    );
  }

  Color _getStatusColor(String? status) {
    switch (status) {
      case 'Success':
        return AppColors.primary;
      case 'Pending':
        return Colors.orange;
      case 'Failed':
        return Colors.redAccent;
      case 'Cancelled':
        return Colors.grey;
      default:
        return Colors.white54;
    }
  }

  Widget _buildPaymentInfo(String label, String value, Color valueColor) {
    return Column(
      children: [
        Text(
          label,
          style: TextStyle(color: AppColors.textSecondary.withOpacity(0.5), fontSize: 10),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: TextStyle(
            color: valueColor,
            fontWeight: FontWeight.bold,
            fontSize: 14,
          ),
        ),
      ],
    );
  }

  String _formatTime(String time) {
    try {
      DateTime dt = DateFormat('HH:mm:ss').parse(time);
      return DateFormat('h:mm a').format(dt);
    } catch (e) {
      return time;
    }
  }
}
