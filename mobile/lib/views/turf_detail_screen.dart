import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../utils/app_colors.dart';
import 'package:get/get.dart';
import 'package:animate_do/animate_do.dart';
import 'package:intl/intl.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';
import '../controllers/booking_controller.dart';
import '../controllers/turf_controller.dart';
import '../controllers/auth_controller.dart';
import '../utils/api_constants.dart';

class TurfDetailScreen extends StatefulWidget {
  final dynamic turf;
  const TurfDetailScreen({super.key, required this.turf});

  @override
  State<TurfDetailScreen> createState() => _TurfDetailScreenState();
}

class _TurfDetailScreenState extends State<TurfDetailScreen> {
  final BookingController _bookingController = Get.find<BookingController>();
  late TurfController _turfController;
  final PageController _pageController = PageController();
  int _currentImageIndex = 0;

  late Razorpay _razorpay;
  bool _isRazorpayInitialised = false;
  DateTime _selectedDate = DateTime.now();

  bool _isLongBooking = false;
  bool _isScatteredBooking = false;
  final List<DateTime> _scatteredDates = [];
  DateTime _calendarMonth = DateTime.now();
  DateTime _longBookingFromDate = DateTime.now();
  DateTime _longBookingToDate = DateTime.now().add(const Duration(days: 1));
  int _longBookingValidSlots = 0;

  final List<int> _selectedSlotIds = [];
  double _totalAmount = 0;
  final TextEditingController _couponController = TextEditingController();
  final TextEditingController _amountPaidController = TextEditingController();
  final TextEditingController _additionalDiscountController = TextEditingController();
  String _selectedPaymentType = 'Full';

  dynamic _currentTurf;
  List<dynamic> _locationTurfs = [];

  @override
  void initState() {
    super.initState();
    _turfController = Get.isRegistered<TurfController>()
        ? Get.find<TurfController>()
        : Get.put(TurfController());

    _currentTurf = widget.turf;
    if (!GetPlatform.isWeb) {
      _razorpay = Razorpay();
      _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handlePaymentSuccess);
      _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handlePaymentError);
      _razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, _handleExternalWallet);
      _isRazorpayInitialised = true;
    }
    _bookingController.resetCoupon();
    _bookingController.resetClientSelection();

    _fetchLocationTurfs();
    _fetchSlots();
    _fetchFullTurfDetails();
  }

  void _fetchFullTurfDetails() async {
    if (_currentTurf == null || _currentTurf['id'] == null) return;

    try {
      final int turfId = int.parse(_currentTurf['id'].toString());
      final fullTurf = await _turfController.fetchTurfById(turfId);
      if (fullTurf != null) {
        setState(() {
          _currentTurf = fullTurf;
        });
      }
    } catch (e) {
      debugPrint('Error fetching full turf details: $e');
    }
  }

  void _fetchLocationTurfs() async {
    int locationId = _currentTurf['location_id'];
    _locationTurfs = _turfController.turfs
        .where((t) => t['location_id'] == locationId)
        .toList();
    setState(() {});
  }

  void _fetchSlots() {
    _bookingController.resetCoupon();
    _couponController.clear();
    if (_currentTurf == null || _currentTurf['id'] == null) {
      debugPrint('Error: Cannot fetch slots because turf ID is null');
      return;
    }

    try {
      final int turfId = int.parse(_currentTurf['id'].toString());
      final fetchDate = _isScatteredBooking
          ? (_scatteredDates.isNotEmpty ? _scatteredDates.first : DateTime.now())
          : (_isLongBooking ? _longBookingFromDate : _selectedDate);
      _bookingController.fetchAvailableSlots(
        turfId,
        DateFormat('yyyy-MM-dd').format(fetchDate),
      );
    } catch (e) {
      debugPrint('Error parsing turf ID: $e');
    }

    setState(() {
      _selectedSlotIds.clear();
      _totalAmount = 0;
    });
  }

  void _handlePaymentSuccess(PaymentSuccessResponse response) {
    _finalizeBooking(response.paymentId);
  }

  void _handlePaymentError(PaymentFailureResponse response) {
    Get.snackbar('Payment Failed', response.message ?? 'Unknown error');
  }

  void _handleExternalWallet(ExternalWalletResponse response) {
    Get.snackbar('External Wallet', response.walletName ?? '');
  }

  void _finalizeBooking(String? transactionId) {
    if (_isLongBooking) {
      final couponCodes = _bookingController.appliedCoupons.map(
        (dateStr, couponData) => MapEntry(dateStr, couponData['code']?.toString() ?? ''),
      );
      _bookingController
          .createLongBooking(
            turfId: _currentTurf['id'],
            fromDate: !_isScatteredBooking ? DateFormat('yyyy-MM-dd').format(_longBookingFromDate) : null,
            toDate: !_isScatteredBooking ? DateFormat('yyyy-MM-dd').format(_longBookingToDate) : null,
            dates: _isScatteredBooking ? _scatteredDates.map((d) => DateFormat('yyyy-MM-dd').format(d)).toList() : null,
            slotIds: _selectedSlotIds,
            paymentType: _selectedPaymentType,
            transactionId: transactionId,
            couponCodes: couponCodes,
          )
          .then((success) {
            if (success) {
              Get.back();
              Get.back(); // May need double back if bottom sheet was open
              Get.snackbar('Success', _isScatteredBooking ? 'Scattered Booking is successful' : 'Long Booking is successful');
            }
          });
    } else {
      _bookingController
          .createBooking(
            turfId: _currentTurf['id'],
            date: DateFormat('yyyy-MM-dd').format(_selectedDate),
            slotIds: _selectedSlotIds,
            paymentType: _selectedPaymentType,
            transactionId: transactionId,
            couponCode: _bookingController.appliedCoupon['code'],
          )
          .then((success) {
            if (success) {
              Get.back();
              Get.snackbar('Success', 'Booking is successful');
            }
          });
    }
  }

  void _processCheckout() async {
    if (_selectedSlotIds.length < 2) {
      Get.snackbar('Error', 'Please select at least 2 consecutive slots (1 hour minimum)');
      return;
    }

    List<dynamic> selectedSlots = _bookingController.availableSlots
        .where((s) => _selectedSlotIds.contains(s['id']))
        .toList();

    selectedSlots.sort((a, b) => a['from'].toString().compareTo(b['from'].toString()));

    bool isConsecutive = true;
    for (int i = 0; i < selectedSlots.length - 1; i++) {
      if (selectedSlots[i]['to'] != selectedSlots[i + 1]['from']) {
        isConsecutive = false;
        break;
      }
    }

    if (!isConsecutive) {
      Get.snackbar('Error', 'Selected slots must be consecutive.');
      return;
    }

    if (_isLongBooking) {
      if (_isScatteredBooking && _scatteredDates.isEmpty) {
        Get.snackbar('Error', 'Please select at least one date.');
        return;
      }
      final res = await _bookingController.calculateLongBooking(
        turfId: _currentTurf['id'],
        fromDate: !_isScatteredBooking ? DateFormat('yyyy-MM-dd').format(_longBookingFromDate) : null,
        toDate: !_isScatteredBooking ? DateFormat('yyyy-MM-dd').format(_longBookingToDate) : null,
        dates: _isScatteredBooking ? _scatteredDates.map((d) => DateFormat('yyyy-MM-dd').format(d)).toList() : null,
        slotIds: _selectedSlotIds,
      );
      if (res != null) {
        _showReviewLongBookingBottomSheet(res);
      }
    } else {
      _showPaymentSelection();
    }
  }

  bool _isSlotPast(dynamic slot) {
    if (_isLongBooking) return false;
    try {
      List<String> parts = slot['from'].toString().split(':');
      if (parts.length >= 2) {
        int hour = int.parse(parts[0]);
        int minute = int.parse(parts[1]);
        DateTime now = DateTime.now();
        DateTime slotTime = DateTime(_selectedDate.year, _selectedDate.month, _selectedDate.day, hour, minute);
        return slotTime.isBefore(now);
      }
    } catch (e) {}
    return false;
  }

  void _handleSlotSelection(dynamic slot, bool isCurrentlySelected) {
    setState(() {
      if (isCurrentlySelected) {
        _selectedSlotIds.remove(slot['id']);
        _totalAmount -= double.tryParse(slot['amount']?.toString() ?? '0') ?? 0;

        List<dynamic> toRemove = [];
        for (var selectedId in _selectedSlotIds) {
          var s = _bookingController.availableSlots.firstWhere(
            (element) => element['id'] == selectedId,
            orElse: () => null,
          );
          if (s != null) {
            bool hasAdjacent = false;
            String cFrom = s['from'].toString();
            String cTo = s['to'].toString();
            
            for (var otherId in _selectedSlotIds) {
              if (otherId == selectedId) continue;
              var otherSlot = _bookingController.availableSlots.firstWhere(
                (element) => element['id'] == otherId,
                orElse: () => null,
              );
              if (otherSlot != null) {
                if (otherSlot['to'].toString() == cFrom || otherSlot['from'].toString() == cTo) {
                  hasAdjacent = true;
                  break;
                }
              }
            }
            if (!hasAdjacent) {
              toRemove.add(selectedId);
            }
          }
        }
        
        for (var id in toRemove) {
          _selectedSlotIds.remove(id);
          var s = _bookingController.availableSlots.firstWhere(
            (element) => element['id'] == id,
            orElse: () => null,
          );
          if (s != null) {
            _totalAmount -= double.tryParse(s['amount']?.toString() ?? '0') ?? 0;
          }
        }
      } else {
        _selectedSlotIds.add(slot['id']);
        _totalAmount += double.tryParse(slot['amount']?.toString() ?? '0') ?? 0;

        bool hasAdjacent = false;
        String currentFrom = slot['from'].toString();
        String currentTo = slot['to'].toString();

        for (var selectedId in _selectedSlotIds) {
          if (selectedId == slot['id']) continue;
          var s = _bookingController.availableSlots.firstWhere(
            (element) => element['id'] == selectedId,
            orElse: () => null,
          );
          if (s != null) {
            if (s['to'].toString() == currentFrom || s['from'].toString() == currentTo) {
              hasAdjacent = true;
              break;
            }
          }
        }

        if (!hasAdjacent) {
          var nextSlot = _bookingController.availableSlots.firstWhere(
            (element) => element['from'].toString() == currentTo && (_isLongBooking || element['is_available'] == true),
            orElse: () => null,
          );

          if (nextSlot != null && !_selectedSlotIds.contains(nextSlot['id'])) {
            if (!_isSlotPast(nextSlot)) {
              _selectedSlotIds.add(nextSlot['id']);
              _totalAmount += double.tryParse(nextSlot['amount']?.toString() ?? '0') ?? 0;
            }
          } else {
            var prevSlot = _bookingController.availableSlots.firstWhere(
              (element) => element['to'].toString() == currentFrom && (_isLongBooking || element['is_available'] == true),
              orElse: () => null,
            );
            if (prevSlot != null && !_selectedSlotIds.contains(prevSlot['id'])) {
              if (!_isSlotPast(prevSlot)) {
                _selectedSlotIds.add(prevSlot['id']);
                _totalAmount += double.tryParse(prevSlot['amount']?.toString() ?? '0') ?? 0;
              }
            }
          }
        }
      }
      _bookingController.updateDiscount(_totalAmount, slotsCount: _selectedSlotIds.length);
    });
  }

  void _showReviewLongBookingBottomSheet(Map<String, dynamic> reviewData) {
    double totalAmt = double.parse(reviewData['total_amount'].toString());
    Map<String, dynamic> unavailable = {};
    if (reviewData['unavailable_slots'] is Map) {
      unavailable = Map<String, dynamic>.from(reviewData['unavailable_slots']);
    }

    Map<String, dynamic> availableSlots = {};
    if (reviewData['available_slots'] is Map) {
      availableSlots = Map<String, dynamic>.from(reviewData['available_slots']);
    }

    final TextEditingController sheetCouponController = TextEditingController();

    int totalDays = _isScatteredBooking
        ? _scatteredDates.length
        : _longBookingToDate.difference(_longBookingFromDate).inDays + 1;
    int unavailableCount = 0;
    for (var list in unavailable.values) {
      unavailableCount += (list as List).length;
    }
    int totalSlotsRequested = totalDays * _selectedSlotIds.length;
    int totalSlotsBooked = totalSlotsRequested - unavailableCount;
    _longBookingValidSlots = totalSlotsBooked;

    Get.bottomSheet(
      Container(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.of(context).size.height * 0.85,
        ),
        padding: const EdgeInsets.all(20),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            child: Obx(() {
              // Recalculate discount reactively when appliedCoupons state changes
              Map<String, dynamic> discountInfo = _bookingController.calculateLongBookingDiscount(reviewData);
              double discount = double.tryParse(discountInfo['discount']?.toString() ?? '0') ?? 0;
              int eligibleDays = int.tryParse(discountInfo['eligible_days']?.toString() ?? '0') ?? 0;
              double payableAmt = totalAmt - discount;
              if (payableAmt < 0) payableAmt = 0;

              return Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _isScatteredBooking ? 'Review Scattered Booking' : 'Review Long Booking',
                    style: const TextStyle(
                      color: AppColors.textMain,
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 15),
                  Text(
                    _isScatteredBooking
                        ? 'Selected Dates:\n${_scatteredDates.map((d) => DateFormat('MMM dd, yyyy').format(d)).join(', ')}'
                        : 'From: ${DateFormat('MMM dd, yyyy').format(_longBookingFromDate)}\nTo: ${DateFormat('MMM dd, yyyy').format(_longBookingToDate)}',
                    style: const TextStyle(color: AppColors.textSecondary, fontSize: 16),
                  ),
                  const SizedBox(height: 15),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Total Days:',
                        style: TextStyle(color: AppColors.textMain, fontSize: 16),
                      ),
                      Text(
                        '$totalDays',
                        style: const TextStyle(
                          color: AppColors.textMain,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Total Slots Booked:',
                        style: TextStyle(color: AppColors.textMain, fontSize: 16),
                      ),
                      Text(
                        '$totalSlotsBooked',
                        style: const TextStyle(
                          color: AppColors.textMain,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                  if (discount > 0) ...[
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Offer Eligible Days:',
                          style: TextStyle(color: AppColors.textMain, fontSize: 16),
                        ),
                        Text(
                          '$eligibleDays',
                          style: const TextStyle(
                            color: AppColors.textMain,
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ],
                  const SizedBox(height: 15),
                  if (unavailable.isNotEmpty) ...[
                    const Text(
                      'Skipped (Already Booked) Slots:',
                      style: TextStyle(
                        color: Colors.orangeAccent,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    ...unavailable.entries.map((entry) {
                      List slots = entry.value;
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Text(
                          '${entry.key}: ${slots.map((s) => "${s['from']} - ${s['to']}").join(', ')}',
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 14,
                          ),
                        ),
                      );
                    }).toList(),
                    const SizedBox(height: 15),
                  ],

                  // Unified Coupon Input Section
                  const Divider(height: 30),
                  const Text(
                    'Apply Coupon Code',
                    style: TextStyle(
                      color: AppColors.textMain,
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Builder(
                    builder: (context) {
                      bool allCovered = true;
                      availableSlots.forEach((dateStr, slotList) {
                        if (_bookingController.appliedCoupons[dateStr] == null) {
                          allCovered = false;
                        }
                      });

                      return Container(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                        decoration: BoxDecoration(
                          color: AppColors.cardBg,
                          borderRadius: BorderRadius.circular(15),
                          border: Border.all(
                            color: allCovered ? Colors.grey[300]! : AppColors.green200,
                          ),
                        ),
                        child: Row(
                          children: [
                            Icon(
                              Icons.local_offer_outlined,
                              color: allCovered ? Colors.grey : AppColors.primary,
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextField(
                                controller: sheetCouponController,
                                enabled: !allCovered && !_bookingController.isCouponLoading.value,
                                textCapitalization: TextCapitalization.characters,
                                inputFormatters: [
                                  TextInputFormatter.withFunction(
                                    (oldValue, newValue) => newValue.copyWith(
                                      text: newValue.text.toUpperCase(),
                                    ),
                                  ),
                                ],
                                style: TextStyle(
                                  color: allCovered ? Colors.grey : AppColors.textMain,
                                ),
                                decoration: InputDecoration(
                                  hintText: allCovered
                                      ? 'All days covered'
                                      : 'Enter Coupon Code',
                                  hintStyle: const TextStyle(color: AppColors.textSecondary),
                                  border: InputBorder.none,
                                ),
                              ),
                            ),
                            if (_bookingController.isCouponLoading.value)
                              const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: AppColors.primary,
                                ),
                              )
                            else
                              TextButton(
                                onPressed: allCovered
                                    ? null
                                    : () async {
                                        String code = sheetCouponController.text.trim();
                                        if (code.isEmpty) {
                                          Get.snackbar('Alert', 'Please enter a coupon code');
                                          return;
                                        }
                                        final applied = await _bookingController.validateAndApplyCouponForMultipleDates(
                                          code: code,
                                          reviewData: reviewData,
                                        );
                                        if (applied.isNotEmpty) {
                                          sheetCouponController.clear();
                                        }
                                      },
                                child: Text(
                                  'APPLY',
                                  style: TextStyle(
                                    color: allCovered ? Colors.grey : AppColors.primary,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      );
                    }
                  ),

                  const SizedBox(height: 25),
                  const Text(
                    'Booking Dates & Coupons',
                    style: TextStyle(
                      color: AppColors.textMain,
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 10),
                  ...availableSlots.entries.map((entry) {
                    String dateStr = entry.key;
                    List slots = entry.value;

                    // Calculate day amount
                    double dayAmount = 0;
                    for (var s in slots) {
                      dayAmount += double.tryParse(s['price']?.toString() ?? '0') ?? 0;
                    }

                    DateTime parsedDate = DateTime.parse(dateStr);
                    String formattedDate = DateFormat('EEEE, dd MMM').format(parsedDate);

                    bool hasDayCoupon = _bookingController.appliedCoupons[dateStr] != null;
                    var dayCoupon = _bookingController.appliedCoupons[dateStr];

                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: AppColors.cardBg,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(
                          color: hasDayCoupon ? AppColors.primary : AppColors.green200,
                          width: 1.2,
                        ),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                formattedDate,
                                style: const TextStyle(
                                  color: AppColors.textMain,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 14,
                                ),
                              ),
                              Text(
                                '₹${dayAmount.toStringAsFixed(0)}',
                                style: const TextStyle(
                                  color: AppColors.primary,
                                  fontWeight: FontWeight.bold,
                                  fontSize: 14,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Slots: ${slots.map((s) => "${_formatTime(s['from'])} - ${_formatTime(s['to'])}").join(', ')}',
                            style: const TextStyle(
                              color: AppColors.textSecondary,
                              fontSize: 12,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Expanded(
                                child: hasDayCoupon
                                    ? Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                        decoration: BoxDecoration(
                                          color: AppColors.primary.withOpacity(0.1),
                                          borderRadius: BorderRadius.circular(6),
                                          border: Border.all(color: AppColors.primary.withOpacity(0.3)),
                                        ),
                                        child: Row(
                                          mainAxisSize: MainAxisSize.min,
                                          children: [
                                            const Icon(
                                              Icons.local_offer,
                                              size: 14,
                                              color: AppColors.primary,
                                            ),
                                            const SizedBox(width: 6),
                                            Flexible(
                                              child: Text(
                                                'Coupon: ${dayCoupon!['code']}',
                                                style: const TextStyle(
                                                  color: AppColors.primary,
                                                  fontWeight: FontWeight.bold,
                                                  fontSize: 12,
                                                ),
                                                overflow: TextOverflow.ellipsis,
                                              ),
                                            ),
                                          ],
                                        ),
                                      )
                                    : const Text(
                                        'No coupon applied',
                                        style: TextStyle(
                                          color: AppColors.textSecondary,
                                          fontSize: 12,
                                          fontStyle: FontStyle.italic,
                                        ),
                                      ),
                              ),
                              if (hasDayCoupon)
                                TextButton(
                                  onPressed: () {
                                    _bookingController.removeCouponForDate(dateStr);
                                  },
                                  style: TextButton.styleFrom(
                                    padding: EdgeInsets.zero,
                                    minimumSize: const Size(50, 30),
                                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                  ),
                                  child: const Text(
                                    'Remove',
                                    style: TextStyle(
                                      color: Colors.redAccent,
                                      fontSize: 12,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                ),
                            ],
                          ),
                        ],
                      ),
                    );
                  }).toList(),
                  const Divider(height: 30),

                  if (discount > 0) ...[
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Original Price:',
                          style: TextStyle(color: AppColors.textMain, fontSize: 16),
                        ),
                        Text(
                          '₹$totalAmt',
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 16,
                            decoration: TextDecoration.lineThrough,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Discount:',
                          style: TextStyle(color: AppColors.textMain, fontSize: 16),
                        ),
                        Text(
                          '-₹$discount',
                          style: const TextStyle(
                            color: Colors.green,
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                  ],
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Final Calculated Price:',
                        style: TextStyle(color: AppColors.textMain, fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                      Text(
                        '₹$payableAmt',
                        style: const TextStyle(
                          color: AppColors.primary,
                          fontSize: 22,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 25),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: () {
                        Get.back(); // close review sheet
                        _totalAmount = payableAmt; // update state for payment
                        _showPaymentSelection();
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        padding: const EdgeInsets.symmetric(vertical: 15),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                      child: const Text(
                        'Confirm & Choose Payment',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 10),
                ],
              );
            }),
          ),
        ), // closes SafeArea
      ),
      isScrollControlled: true,
    ).then((_) {
      Future.delayed(const Duration(milliseconds: 500), () {
        try {
          sheetCouponController.dispose();
        } catch (e) {
          debugPrint('Error disposing sheetCouponController: $e');
        }
      });
    });
  }

  void _showPaymentSelection() {
    if (_selectedSlotIds.isEmpty) {
      Get.snackbar('Error', 'Please select at least one slot');
      return;
    }

    final authController = Get.find<AuthController>();
    final isManager = authController.isManager.value == true;
    if (isManager) {
      double finalAmount = _isLongBooking
          ? _totalAmount
          : _totalAmount - _bookingController.discountAmount.value;
      _amountPaidController.text = finalAmount.toStringAsFixed(0);
    }

    bool isPartPaymentActive =
        _bookingController.systemSettings['is_part_payment_active'] == true;
    bool isPayAtLocationActive =
        _bookingController.systemSettings['is_pay_at_location_active'] == true;

    if (isPartPaymentActive || isPayAtLocationActive || isManager) {
      _showPaymentTypeDialog();
    } else {
      _selectedPaymentType = 'Full';
      _proceedToRazorpay(
        _isLongBooking
            ? _totalAmount
            : _totalAmount - _bookingController.discountAmount.value,
      );
    }
  }

  void _showPaymentTypeDialog() {
    double finalAmount = _isLongBooking
        ? _totalAmount
        : _totalAmount - _bookingController.discountAmount.value;
    double minPartAmount =
        double.tryParse(
          _bookingController.systemSettings['min_part_payment']?.toString() ??
              '0',
        ) ??
        0;

    int totalValidSlots = _isLongBooking
        ? _longBookingValidSlots
        : _selectedSlotIds.length;
    double partPaymentTotal = minPartAmount * totalValidSlots;
    final authController = Get.find<AuthController>();
    final isManager = authController.isManager.value == true;

    if (isManager) {
      _additionalDiscountController.text = '';
      _amountPaidController.text = finalAmount.toStringAsFixed(0);
    }

    Get.bottomSheet(
      Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(Get.context!).viewInsets.bottom),
        child: Container(
          padding: const EdgeInsets.all(20),
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(25)),
          ),
          child: StatefulBuilder(
            builder: (BuildContext context, StateSetter dialogSetState) {
              double additionalDiscount = double.tryParse(_additionalDiscountController.text) ?? 0.0;
              double calculatedFinalAmount = (finalAmount - additionalDiscount).clamp(0.0, finalAmount);

              return SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Center(
                      child: Text(
                        'Choose Payment Method',
                        style: TextStyle(
                          color: AppColors.textMain,
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                    const SizedBox(height: 25),
                    if (isManager) ...[
                      const Text(
                        'Additional Discount (₹)',
                        style: TextStyle(
                          color: AppColors.textMain,
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 10),
                      TextFormField(
                        controller: _additionalDiscountController,
                        keyboardType: TextInputType.number,
                        style: const TextStyle(color: AppColors.textMain),
                        onChanged: (val) {
                          double disc = double.tryParse(val) ?? 0.0;
                          double newFinal = (finalAmount - disc).clamp(0.0, finalAmount);
                          _amountPaidController.text = newFinal.toStringAsFixed(0);
                          dialogSetState(() {});
                        },
                        decoration: InputDecoration(
                          labelText: 'Additional Discount',
                          labelStyle: const TextStyle(color: AppColors.primary),
                          hintText: 'Enter discount amount',
                          prefixIcon: const Icon(Icons.percent, color: AppColors.primary),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(15),
                            borderSide: const BorderSide(color: AppColors.green200),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(15),
                            borderSide: const BorderSide(color: AppColors.primary, width: 2),
                          ),
                          filled: true,
                          fillColor: AppColors.green50,
                        ),
                      ),
                      const SizedBox(height: 15),
                      const Text(
                        'Manager / Admin Cash Collected (₹)',
                        style: TextStyle(
                          color: AppColors.textMain,
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 10),
                      TextFormField(
                        controller: _amountPaidController,
                        keyboardType: TextInputType.number,
                        style: const TextStyle(color: AppColors.textMain),
                        onChanged: (val) {
                          dialogSetState(() {});
                        },
                        decoration: InputDecoration(
                          labelText: 'Amount Paid',
                          labelStyle: const TextStyle(color: AppColors.primary),
                          hintText: 'Enter cash collected offline',
                          prefixIcon: const Icon(Icons.currency_rupee, color: AppColors.primary),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(15),
                            borderSide: const BorderSide(color: AppColors.green200),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(15),
                            borderSide: const BorderSide(color: AppColors.primary, width: 2),
                          ),
                          filled: true,
                          fillColor: AppColors.green50,
                        ),
                      ),
                      const SizedBox(height: 20),
                    ],
                    _buildPaymentOption(
                      icon: Icons.payments_outlined,
                      title: 'Pay Full Amount',
                      subtitle: isManager
                          ? 'Collect ₹${calculatedFinalAmount.toStringAsFixed(0)} offline cash/UPI'
                          : 'Pay ₹${finalAmount.toStringAsFixed(0)} online securely',
                      onTap: () {
                        _selectedPaymentType = 'Full';
                        Get.back();
                        if (isManager) {
                          final customAmount = double.tryParse(_amountPaidController.text) ?? calculatedFinalAmount;
                          final disc = double.tryParse(_additionalDiscountController.text) ?? 0.0;
                          _finalizeManagerBooking(
                            paymentType: 'Full', 
                            amountPaid: customAmount,
                            additionalDiscount: disc,
                          );
                        } else {
                          _proceedToRazorpay(finalAmount);
                        }
                      },
                    ),
                    if (_bookingController.systemSettings['is_part_payment_active'] ==
                        true) ...[
                      const SizedBox(height: 12),
                      _buildPaymentOption(
                        icon: Icons.account_balance_wallet_outlined,
                        title: 'Part Payment',
                        subtitle: isManager
                            ? 'Collect ₹${partPaymentTotal.toStringAsFixed(0)} offline cash now and rest later'
                            : 'Pay ₹${partPaymentTotal.toStringAsFixed(0)} now and rest later',
                        onTap: () {
                          _selectedPaymentType = 'Part';
                          Get.back();
                          if (isManager) {
                            final customAmount = double.tryParse(_amountPaidController.text) ?? partPaymentTotal;
                            final disc = double.tryParse(_additionalDiscountController.text) ?? 0.0;
                            _finalizeManagerBooking(
                              paymentType: 'Part', 
                              amountPaid: customAmount,
                              additionalDiscount: disc,
                            );
                          } else {
                            _proceedToRazorpay(partPaymentTotal);
                          }
                        },
                      ),
                    ],
                    if (_bookingController.systemSettings['is_pay_at_location_active'] ==
                        true || isManager) ...[
                      const SizedBox(height: 12),
                      _buildPaymentOption(
                        icon: Icons.location_on_outlined,
                        title: 'Pay at Location',
                        subtitle: isManager
                            ? 'Book now and collect ₹${calculatedFinalAmount.toStringAsFixed(0)} at venue later'
                            : 'Book now and pay ₹${finalAmount.toStringAsFixed(0)} at venue',
                        onTap: () {
                          _selectedPaymentType = 'PayAtLocation';
                          Get.back();
                          if (isManager) {
                            final customAmount = double.tryParse(_amountPaidController.text) ?? 0.0;
                            final disc = double.tryParse(_additionalDiscountController.text) ?? 0.0;
                            _finalizeManagerBooking(
                              paymentType: 'PayAtLocation', 
                              amountPaid: customAmount,
                              additionalDiscount: disc,
                            );
                          } else {
                            _finalizePayAtLocation();
                          }
                        },
                      ),
                    ],
                    const SizedBox(height: 10),
                  ],
                ),
              );
            },
          ),
        ),
      ),
      isScrollControlled: true,
    );
  }

  Widget _buildPaymentOption({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return ListTile(
      leading: CircleAvatar(
        backgroundColor: Colors.white10,
        child: Icon(icon, color: AppColors.primary),
      ),
      title: Text(
        title,
        style: const TextStyle(
          color: AppColors.textMain,
          fontWeight: FontWeight.bold,
        ),
      ),
      subtitle: Text(
        subtitle,
        style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
      ),
      onTap: onTap,
      tileColor: AppColors.green50,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15), side: const BorderSide(color: AppColors.green200)),
    );
  }

  void _finalizePayAtLocation() {
    if (_isLongBooking) {
      final couponCodes = _bookingController.appliedCoupons.map(
        (dateStr, couponData) => MapEntry(dateStr, couponData['code']?.toString() ?? ''),
      );
      _bookingController
          .createLongBooking(
            turfId: _currentTurf['id'],
            fromDate: !_isScatteredBooking ? DateFormat('yyyy-MM-dd').format(_longBookingFromDate) : null,
            toDate: !_isScatteredBooking ? DateFormat('yyyy-MM-dd').format(_longBookingToDate) : null,
            dates: _isScatteredBooking ? _scatteredDates.map((d) => DateFormat('yyyy-MM-dd').format(d)).toList() : null,
            slotIds: _selectedSlotIds,
            paymentType: 'PayAtLocation',
            couponCodes: couponCodes,
          )
          .then((success) {
            if (success) {
              Get.back();
              Get.snackbar('Success', _isScatteredBooking ? 'Scattered Booking is successful' : 'Long Booking is successful');
            }
          });
    } else {
      _bookingController
          .createBooking(
            turfId: _currentTurf['id'],
            date: DateFormat('yyyy-MM-dd').format(_selectedDate),
            slotIds: _selectedSlotIds,
            paymentType: 'PayAtLocation',
            couponCode: _bookingController.appliedCoupon['code'],
          )
          .then((success) {
            if (success) {
              Get.back();
              Get.snackbar('Success', 'Booking is successful');
            }
          });
    }
  }

  void _finalizeManagerBooking({
    required String paymentType,
    required double amountPaid,
    double? additionalDiscount,
  }) {
    if (_isLongBooking) {
      final couponCodes = _bookingController.appliedCoupons.map(
        (dateStr, couponData) => MapEntry(dateStr, couponData['code']?.toString() ?? ''),
      );
      _bookingController
          .createLongBooking(
            turfId: _currentTurf['id'],
            fromDate: !_isScatteredBooking ? DateFormat('yyyy-MM-dd').format(_longBookingFromDate) : null,
            toDate: !_isScatteredBooking ? DateFormat('yyyy-MM-dd').format(_longBookingToDate) : null,
            dates: _isScatteredBooking ? _scatteredDates.map((d) => DateFormat('yyyy-MM-dd').format(d)).toList() : null,
            slotIds: _selectedSlotIds,
            paymentType: paymentType,
            couponCodes: couponCodes,
            amountPaid: amountPaid,
            additionalDiscount: additionalDiscount,
          )
          .then((success) {
            if (success) {
              Get.back();
              Get.snackbar('Success', _isScatteredBooking ? 'Scattered Booking is successful' : 'Long Booking is successful');
            }
          });
    } else {
      _bookingController
          .createBooking(
            turfId: _currentTurf['id'],
            date: DateFormat('yyyy-MM-dd').format(_selectedDate),
            slotIds: _selectedSlotIds,
            paymentType: paymentType,
            couponCode: _bookingController.appliedCoupon['code'],
            amountPaid: amountPaid,
            additionalDiscount: additionalDiscount,
          )
          .then((success) {
            if (success) {
              Get.back();
              Get.snackbar('Success', 'Booking is successful');
            }
          });
    }
  }

  void _proceedToRazorpay(double amount) {
    if (GetPlatform.isWeb || !_isRazorpayInitialised) {
      Get.snackbar(
        'Platform Not Supported',
        'Online payments are only available on Android and iOS. Please use Pay at Location if available or contact support.',
        backgroundColor: Colors.orangeAccent,
        colorText: Colors.white,
        duration: const Duration(seconds: 5),
      );
      return;
    }

    var options = {
      'key':
          _bookingController.systemSettings['razorpay_key'] ??
          'rzp_test_placeholder',
      'amount': (amount * 100).toInt(),
      'name': 'Cornerstone Turf',
      'description': 'Turf Booking',
      'prefill': {'contact': '', 'email': ''},
      'external': {
        'wallets': ['paytm'],
      },
    };

    try {
      _razorpay.open(options);
    } catch (e) {
      debugPrint('Error: $e');
    }
  }

  List<String> _getTurfImages(dynamic turf) {
    if (turf['photos'] != null && (turf['photos'] as List).isNotEmpty) {
      return (turf['photos'] as List)
          .map((p) {
            final photo = p['photo_url']?.toString() ?? '';
            if (photo.isEmpty) return '';
            if (photo.startsWith('http')) return photo;
            return '${ApiConstants.imageBaseUrl}${photo.startsWith('/') ? '' : '/'}$photo';
          })
          .where((url) => url.isNotEmpty)
          .toList();
    }
    return [];
  }

  String? _getTurfImage(dynamic turf) {
    List<String> images = _getTurfImages(turf);
    return images.isNotEmpty ? images[0] : null;
  }

  String _formatTime(String time) {
    try {
      DateTime dt = DateFormat('HH:mm:ss').parse(time);
      return DateFormat('h:mm a').format(dt);
    } catch (e) {
      return time;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Stack(
        children: [
          CustomScrollView(
            slivers: [
              SliverAppBar(
                expandedHeight: MediaQuery.of(context).size.width * 0.75,
                pinned: true,
                backgroundColor: AppColors.background,
                flexibleSpace: FlexibleSpaceBar(
                  background: Builder(
                    builder: (context) {
                      List<String> images = _getTurfImages(_currentTurf);
                      if (images.isEmpty) {
                        return Container(
                          color: Colors.white,
                          child: const Center(
                            child: Icon(
                              Icons.sports_soccer,
                              color: Colors.white10,
                              size: 80,
                            ),
                          ),
                        );
                      }
                      return Stack(
                        children: [
                          PageView.builder(
                            controller: _pageController,
                            onPageChanged: (index) {
                              setState(() {
                                _currentImageIndex = index;
                              });
                            },
                            itemCount: images.length,
                            itemBuilder: (context, index) {
                              return Image.network(
                                images[index],
                                fit: BoxFit.cover,
                                errorBuilder: (context, error, stackTrace) =>
                                    Container(
                                      color: Colors.grey[800],
                                      child: const Icon(
                                        Icons.broken_image,
                                        color: Colors.white24,
                                        size: 50,
                                      ),
                                    ),
                              );
                            },
                          ),
                          if (images.length > 1)
                            Positioned(
                              bottom: 20,
                              left: 0,
                              right: 0,
                              child: Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: images.asMap().entries.map((entry) {
                                  return AnimatedContainer(
                                    duration: const Duration(milliseconds: 300),
                                    width: _currentImageIndex == entry.key
                                        ? 20
                                        : 8,
                                    height: 8,
                                    margin: const EdgeInsets.symmetric(
                                      horizontal: 4,
                                    ),
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(4),
                                      color: _currentImageIndex == entry.key
                                          ? AppColors.primary
                                          : Colors.white.withOpacity(0.5),
                                    ),
                                  );
                                }).toList(),
                              ),
                            ),
                          // Gradient Overlay for better readability of the back button and top area
                          Positioned.fill(
                            child: IgnorePointer(
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  gradient: LinearGradient(
                                    begin: Alignment.topCenter,
                                    end: Alignment.bottomCenter,
                                    colors: [
                                      Colors.black.withOpacity(0.4),
                                      Colors.transparent,
                                      Colors.transparent,
                                      Colors.black.withOpacity(0.4),
                                    ],
                                    stops: const [0.0, 0.2, 0.8, 1.0],
                                  ),
                                ),
                              ),
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(20.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      FadeInUp(
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _currentTurf['location']?['name'] ??
                                        'No Location',
                                    style: const TextStyle(
                                      color: AppColors.textMain,
                                      fontSize: 24,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    _currentTurf['location']?['address'] ?? '',
                                    style: const TextStyle(
                                      color: AppColors.textSecondary,
                                      fontSize: 13,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 25),

                      // Turf Selector
                      if (_locationTurfs.length > 1)
                        FadeInDown(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Select Turf',
                                style: TextStyle(
                                  color: AppColors.textMain,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              const SizedBox(height: 10),
                              SizedBox(
                                height: 40,
                                child: ListView.builder(
                                  scrollDirection: Axis.horizontal,
                                  itemCount: _locationTurfs.length,
                                  itemBuilder: (context, index) {
                                    final turf = _locationTurfs[index];
                                    bool isSelected =
                                        turf['id'] == _currentTurf['id'];
                                    return Padding(
                                      padding: const EdgeInsets.only(right: 10),
                                      child: ChoiceChip(
                                        label: Text(turf['name']),
                                        selected: isSelected,
                                        onSelected: (selected) {
                                          if (selected) {
                                            setState(() {
                                              _currentTurf = turf;
                                              _currentImageIndex = 0;
                                              if (_pageController.hasClients) {
                                                _pageController.jumpToPage(0);
                                              }
                                              _fetchSlots();
                                            });
                                          }
                                        },
                                        selectedColor: AppColors.primary,
                                        backgroundColor: AppColors.cardBg,
                                        labelStyle: TextStyle(
                                          color: isSelected
                                              ? Colors.white
                                              : AppColors.textMain,
                                        ),
                                        side: const BorderSide(color: AppColors.green200),
                                        shape: RoundedRectangleBorder(
                                          borderRadius: BorderRadius.circular(
                                            20,
                                          ),
                                        ),
                                      ),
                                    );
                                  },
                                ),
                              ),
                              const SizedBox(height: 25),
                            ],
                          ),
                        ),

                      // Metadata
                      FadeInUp(
                        delay: const Duration(milliseconds: 50),
                        child: Container(
                          padding: const EdgeInsets.all(15),
                          decoration: BoxDecoration(
                            color: AppColors.cardBg,
                            borderRadius: BorderRadius.circular(15),
                            border: Border.all(color: AppColors.green200),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceAround,
                            children: [
                              _buildMetadataItem(
                                Icons.aspect_ratio,
                                'Area',
                                _currentTurf['area'] ?? 'N/A',
                              ),
                              _buildMetadataItem(
                                Icons.sports_soccer,
                                'Type',
                                _currentTurf['turf_type'] ?? 'Synthetic',
                              ),
                              _buildMetadataItem(
                                Icons.grid_view,
                                'Size',
                                'Full',
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 25),

                      // Description
                      if (_currentTurf['description'] != null &&
                          _currentTurf['description'].toString().isNotEmpty)
                        FadeInUp(
                          delay: const Duration(milliseconds: 75),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'About Turf',
                                style: TextStyle(
                                  color: AppColors.textMain,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              const SizedBox(height: 10),
                              Text(
                                _currentTurf['description'],
                                style: const TextStyle(
                                  color: AppColors.textSecondary,
                                  fontSize: 13,
                                  height: 1.5,
                                ),
                              ),
                              const SizedBox(height: 25),
                            ],
                          ),
                        ),

                      // Sports
                      if (_currentTurf['sports'] != null &&
                          (_currentTurf['sports'] as List).isNotEmpty)
                        FadeInUp(
                          delay: const Duration(milliseconds: 100),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Sports Available',
                                style: TextStyle(
                                  color: AppColors.textMain,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              const SizedBox(height: 10),
                              Wrap(
                                spacing: 8,
                                runSpacing: 8,
                                children: (_currentTurf['sports'] as List)
                                    .map(
                                      (sport) => Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 12,
                                          vertical: 6,
                                        ),
                                        decoration: BoxDecoration(
                                          color: AppColors.cardBg,
                                          borderRadius: BorderRadius.circular(
                                            10,
                                          ),
                                          border: Border.all(
                                            color: AppColors.green200,
                                          ),
                                        ),
                                        child: Text(
                                          sport['name'],
                                          style: const TextStyle(
                                            color: AppColors.textMain,
                                            fontSize: 12,
                                          ),
                                        ),
                                      ),
                                    )
                                    .toList(),
                              ),
                              const SizedBox(height: 25),
                            ],
                          ),
                        ),

                      // Facilities
                      if (_currentTurf['facilities'] != null &&
                          (_currentTurf['facilities'] as List).isNotEmpty)
                        FadeInUp(
                          delay: const Duration(milliseconds: 150),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Facilities',
                                style: TextStyle(
                                  color: AppColors.textMain,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              const SizedBox(height: 10),
                              Wrap(
                                spacing: 20,
                                runSpacing: 10,
                                children: (_currentTurf['facilities'] as List)
                                    .map(
                                      (facility) => Column(
                                        children: [
                                          const Icon(
                                            Icons.check_circle_outline,
                                            color: AppColors.primary,
                                            size: 20,
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            facility['name'],
                                            style: const TextStyle(
                                              color: AppColors.textSecondary,
                                              fontSize: 10,
                                            ),
                                          ),
                                        ],
                                      ),
                                    )
                                    .toList(),
                              ),
                              const SizedBox(height: 25),
                            ],
                          ),
                        ),

                      // Date Picker
                      FadeInUp(
                        delay: const Duration(milliseconds: 200),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  _isScatteredBooking
                                      ? 'Select Scattered Dates'
                                      : (_isLongBooking
                                          ? 'Select Date Range'
                                          : 'Select Date'),
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                                Container(
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: Row(
                                    children: [
                                      _buildTypeTab('Day', !_isLongBooking, () {
                                        setState(() {
                                          _isLongBooking = false;
                                          _isScatteredBooking = false;
                                          _selectedDate = DateTime.now();
                                          _selectedSlotIds.clear();
                                          _totalAmount = 0;
                                        });
                                        _fetchSlots();
                                      }),
                                      _buildTypeTab('Long', _isLongBooking && !_isScatteredBooking, () {
                                        setState(() {
                                          _isLongBooking = true;
                                          _isScatteredBooking = false;
                                          _selectedSlotIds.clear();
                                          _totalAmount = 0;
                                        });
                                        _fetchSlots();
                                      }),
                                      _buildTypeTab('Scattered', _isScatteredBooking, () {
                                        setState(() {
                                          _isLongBooking = true;
                                          _isScatteredBooking = true;
                                          if (_scatteredDates.isEmpty) {
                                            _scatteredDates.add(DateTime.now());
                                          }
                                          _selectedSlotIds.clear();
                                          _totalAmount = 0;
                                        });
                                        _fetchSlots();
                                      }),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 10),
                            if (_isScatteredBooking)
                              _buildCalendarGrid()
                            else if (_isLongBooking)
                              GestureDetector(
                                onTap: () async {
                                  final picked = await showDateRangePicker(
                                    context: context,
                                    firstDate: DateTime.now(),
                                    lastDate: DateTime.now().add(
                                      const Duration(days: 60),
                                    ),
                                    initialDateRange: DateTimeRange(
                                      start: _longBookingFromDate,
                                      end: _longBookingToDate,
                                    ),
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
                                  if (picked != null) {
                                    setState(() {
                                      _longBookingFromDate = picked.start;
                                      _longBookingToDate = picked.end;
                                      _selectedSlotIds.clear();
                                      _totalAmount = 0;
                                    });
                                    _fetchSlots();
                                  }
                                },
                                child: Container(
                                  padding: const EdgeInsets.all(15),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(12),
                                    border: Border.all(color: AppColors.green200),
                                  ),
                                  child: Row(
                                    children: [
                                      const Icon(
                                        Icons.date_range,
                                        color: AppColors.primary,
                                      ),
                                      const SizedBox(width: 10),
                                      Text(
                                        '${DateFormat('MMM dd, yyyy').format(_longBookingFromDate)} - ${DateFormat('MMM dd, yyyy').format(_longBookingToDate)}',
                                        style: const TextStyle(
                                          color: AppColors.textMain,
                                          fontSize: 14,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              )
                            else
                              SizedBox(
                                height: 80,
                                child: ListView.builder(
                                  scrollDirection: Axis.horizontal,
                                  itemCount:
                                      int.tryParse(
                                        _bookingController
                                                .systemSettings['booking_open_days']
                                                ?.toString() ??
                                            '14',
                                      ) ??
                                      14,
                                  itemBuilder: (context, index) {
                                    DateTime date = DateTime.now().add(
                                      Duration(days: index),
                                    );
                                    bool isSelected =
                                        DateFormat('yyyy-MM-dd').format(date) ==
                                        DateFormat(
                                          'yyyy-MM-dd',
                                        ).format(_selectedDate);

                                    return GestureDetector(
                                      onTap: () {
                                        setState(() {
                                          _selectedDate = date;
                                          _selectedSlotIds.clear();
                                          _totalAmount = 0;
                                        });
                                        _fetchSlots();
                                      },
                                      child: Container(
                                        width: 65,
                                        margin: const EdgeInsets.only(
                                          right: 12,
                                        ),
                                        decoration: BoxDecoration(
                                          color: isSelected
                                              ? AppColors.primary
                                              : AppColors.cardBg,
                                          borderRadius: BorderRadius.circular(
                                            15,
                                          ),
                                          border: isSelected
                                              ? null
                                              : Border.all(
                                                  color: AppColors.green200,
                                                ),
                                        ),
                                        child: Column(
                                          mainAxisAlignment:
                                              MainAxisAlignment.center,
                                          children: [
                                            Text(
                                              DateFormat(
                                                'EEE',
                                              ).format(date).toUpperCase(),
                                              style: TextStyle(
                                                color: isSelected
                                                    ? Colors.white
                                                    : AppColors.textMain,
                                                fontSize: 10,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                            const SizedBox(height: 2),
                                            Text(
                                              DateFormat('dd').format(date),
                                              style: TextStyle(
                                                color: isSelected
                                                    ? Colors.white
                                                    : AppColors.textMain,
                                                fontSize: 18,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                            const SizedBox(height: 2),
                                            Text(
                                              DateFormat(
                                                'MMM',
                                              ).format(date).toUpperCase(),
                                              style: TextStyle(
                                                color: isSelected
                                                    ? Colors.white
                                                    : AppColors.textMain,
                                                fontSize: 10,
                                                fontWeight: FontWeight.bold,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    );
                                  },
                                ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 30),

                      // Slot Selection
                      Obx(() {
                        if (_bookingController.isSlotsLoading.value) {
                          return const Center(
                            child: CircularProgressIndicator(
                              color: AppColors.primary,
                            ),
                          );
                        }

                        if (_bookingController.availableSlots.isEmpty) {
                          return const Center(
                            child: Padding(
                              padding: EdgeInsets.symmetric(vertical: 40),
                              child: Text(
                                'No slots available for this day.',
                                style: TextStyle(color: AppColors.textSecondary),
                              ),
                            ),
                          );
                        }

                        Map<String, List<dynamic>> groupedSlots = {};
                        for (var slot in _bookingController.availableSlots) {
                          String cat = (slot['category'] ?? 'General')
                              .toString()
                              .toUpperCase();
                          if (!groupedSlots.containsKey(cat))
                            groupedSlots[cat] = [];
                          groupedSlots[cat]!.add(slot);
                        }

                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: groupedSlots.entries.map((entry) {
                            return Padding(
                              padding: const EdgeInsets.only(bottom: 25),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    entry.key,
                                    style: const TextStyle(
                                      color: AppColors.textSecondary,
                                      fontSize: 12,
                                      fontWeight: FontWeight.bold,
                                      letterSpacing: 1,
                                    ),
                                  ),
                                  const SizedBox(height: 15),
                                  Wrap(
                                    spacing: 12,
                                    runSpacing: 12,
                                    children: entry.value.map((slot) {
                                      bool isBooked = !_isLongBooking && slot['is_available'] == false;
                                      bool isPast = !_isLongBooking && _isSlotPast(slot);
                                      bool isUnavailable = isBooked || isPast;
                                      bool isSelected = _selectedSlotIds.contains(slot['id']);

                                      return GestureDetector(
                                        onTap: isUnavailable
                                            ? null
                                            : () => _handleSlotSelection(slot, isSelected),
                                        child: Container(
                                          width: (MediaQuery.of(context).size.width - 55) / 2,
                                          padding: const EdgeInsets.all(15),
                                          decoration: BoxDecoration(
                                            color: isBooked
                                                ? const Color(0xFFFEF2F2)
                                                : (isPast
                                                    ? Colors.grey[100]
                                                    : (isSelected
                                                        ? AppColors.primary.withOpacity(0.15)
                                                        : AppColors.cardBg)),
                                            borderRadius: BorderRadius.circular(12),
                                            border: Border.all(
                                              color: isBooked
                                                  ? const Color(0xFFFCA5A5)
                                                  : (isPast
                                                      ? Colors.grey[300]!
                                                      : (isSelected ? AppColors.primary : AppColors.green200)),
                                              width: 1.5,
                                            ),
                                          ),
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Text(
                                                '${_formatTime(slot['from'])} - ${_formatTime(slot['to'])}',
                                                style: TextStyle(
                                                  color: isBooked
                                                      ? const Color(0xFF991B1B)
                                                      : (isPast ? Colors.grey[400] : AppColors.textMain),
                                                  fontWeight: FontWeight.bold,
                                                  fontSize: 13,
                                                ),
                                              ),
                                              const SizedBox(height: 10),
                                              Text(
                                                isBooked ? 'Occupied' : (isPast ? 'Past' : '₹${slot['amount'].toStringAsFixed(0)}'),
                                                style: TextStyle(
                                                  color: isBooked
                                                      ? const Color(0xFFEF4444)
                                                      : (isPast ? Colors.grey[400] : AppColors.primary),
                                                  fontSize: 11,
                                                  fontWeight: FontWeight.bold,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      );
                                    }).toList(),
                                  ),
                                ],
                              ),
                            );
                          }).toList(),
                        );
                      }),

                      const SizedBox(height: 30),

                      // Client Selection Section for Admin/Manager
                      _buildClientSelectionCard(),

                      const SizedBox(height: 30),

                      // Coupon Section
                      _buildCouponSection(),

                      const SizedBox(height: 180), // Space for bottom bar
                    ],
                  ),
                ),
              ),
            ],
          ),

          // Bottom Checkout Bar
          _buildBottomBar(),
        ],
      ),
    );
  }

  Widget _buildCouponSection() {
    if (_isLongBooking) {
      return const SizedBox.shrink();
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Apply Coupon',
          style: TextStyle(
            color: AppColors.textMain,
            fontSize: 18,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 15),
        Obx(() {
          bool hasCoupon = _bookingController.appliedCoupon.isNotEmpty;
          return Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(
              color: AppColors.cardBg,
              borderRadius: BorderRadius.circular(15),
              border: Border.all(
                color: hasCoupon
                    ? AppColors.primary
                    : AppColors.green200,
              ),
            ),
            child: Row(
              children: [
                Icon(
                  Icons.local_offer_outlined,
                  color: hasCoupon ? AppColors.primary : AppColors.textSecondary,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: TextField(
                    controller: _couponController,
                    enabled: !hasCoupon,
                    textCapitalization: TextCapitalization.characters,
                    inputFormatters: [
                      TextInputFormatter.withFunction(
                        (oldValue, newValue) => newValue.copyWith(text: newValue.text.toUpperCase()),
                      ),
                    ],
                    style: const TextStyle(color: AppColors.textMain),
                    decoration: InputDecoration(
                      hintText: hasCoupon
                          ? 'Coupon Applied'
                          : 'Enter Coupon Code',
                      hintStyle: const TextStyle(color: AppColors.textSecondary),
                      border: InputBorder.none,
                    ),
                  ),
                ),
                if (_bookingController.isCouponLoading.value)
                  const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: AppColors.primary,
                    ),
                  )
                else if (hasCoupon)
                  TextButton(
                    onPressed: () {
                      _bookingController.resetCoupon();
                      _couponController.clear();
                    },
                    child: const Text(
                      'REMOVE',
                      style: TextStyle(
                        color: Colors.redAccent,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  )
                else
                  TextButton(
                    onPressed: () {
                      if (_selectedSlotIds.isEmpty) {
                        Get.snackbar('Alert', 'Please select slots first');
                        return;
                      }
                      _bookingController.validateCoupon(
                        _couponController.text,
                        _totalAmount,
                        slotsCount: _selectedSlotIds.length,
                        date: _isLongBooking
                            ? DateFormat('yyyy-MM-dd').format(_longBookingFromDate)
                            : DateFormat('yyyy-MM-dd').format(_selectedDate),
                      );
                    },
                    child: const Text(
                      'APPLY',
                      style: TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
              ],
            ),
          );
        }),
      ],
    );
  }

  Widget _buildBottomBar() {
    return Positioned(
      bottom: 0,
      left: 0,
      right: 0,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 10,
              offset: const Offset(0, -5),
            ),
          ],
        ),
        child: SafeArea(
          child: Row(
            children: [
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${_selectedSlotIds.length} Slots Selected',
                      style: const TextStyle(
                        color: AppColors.textSecondary,
                        fontSize: 13,
                      ),
                    ),
                    Obx(() {
                      double discount = _bookingController.discountAmount.value;
                      double finalAmount = _totalAmount - discount;
                      if (finalAmount < 0) finalAmount = 0;
 
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          if (discount > 0)
                            Text(
                              '₹${_totalAmount.toStringAsFixed(0)}',
                              style: TextStyle(
                                color: AppColors.textSecondary.withOpacity(0.5),
                                fontSize: 14,
                                decoration: TextDecoration.lineThrough,
                              ),
                            ),
                          Text(
                            '₹${finalAmount.toStringAsFixed(0)}',
                            style: const TextStyle(
                              color: AppColors.textMain,
                              fontSize: 24,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      );
                    }),
                  ],
                ),
              ),
              const SizedBox(width: 20),
              Expanded(
                flex: 2,
                child: ElevatedButton(
                  onPressed: _selectedSlotIds.isEmpty ? null : _processCheckout,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: const Text(
                    'Proceed to Checkout',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildMetadataItem(IconData icon, String label, String value) {
    return Column(
      children: [
        Icon(icon, color: AppColors.primary, size: 20),
        const SizedBox(height: 4),
        Text(
          label,
          style: const TextStyle(color: AppColors.textSecondary, fontSize: 10),
        ),
        Text(
          value,
          style: const TextStyle(
            color: AppColors.textMain,
            fontWeight: FontWeight.bold,
            fontSize: 12,
          ),
        ),
      ],
    );
  }

  Widget _buildClientSelectionCard() {
    final authController = Get.find<AuthController>();
    
    // Only display if the logged-in user is a Manager
    if (authController.isManager.value != true) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.green200),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.02),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Book on behalf of Client',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textMain,
                ),
              ),
              if (_bookingController.selectedClient.value != null)
                TextButton(
                  onPressed: () => _bookingController.resetClientSelection(),
                  child: const Text('Clear', style: TextStyle(color: Colors.red)),
                ),
            ],
          ),
          const SizedBox(height: 8),
          Obx(() {
            final client = _bookingController.selectedClient.value;
            return InkWell(
              onTap: () => _openClientSearchBottomSheet(),
              child: Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.green50.withOpacity(0.5),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AppColors.green100),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.person_search, color: AppColors.primary),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            client != null ? client['name'] ?? '' : 'Tap to select client...',
                            style: TextStyle(
                              fontWeight: client != null ? FontWeight.bold : FontWeight.normal,
                              color: client != null ? AppColors.textMain : AppColors.textMain.withOpacity(0.5),
                            ),
                          ),
                          if (client != null)
                            Text(
                              'Mobile: ${client['mobile_number'] ?? ''} • ${client['email'] ?? ''}',
                              style: const TextStyle(fontSize: 12, color: AppColors.textSecondary),
                            ),
                        ],
                      ),
                    ),
                    const Icon(Icons.arrow_drop_down, color: AppColors.textSecondary),
                  ],
                ),
              ),
            );
          }),
        ],
      ),
    );
  }

  void _openClientSearchBottomSheet() {
    _bookingController.clientsList.clear();
    final searchController = TextEditingController();
    final RxString searchQuery = ''.obs;
    final RxBool showAddForm = false.obs;
    final nameController = TextEditingController();
    final mobileController = TextEditingController();
    final emailController = TextEditingController();

    Get.bottomSheet(
      Container(
        height: MediaQuery.of(context).size.height * 0.75,
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Obx(() {
          if (showAddForm.value) {
            return _buildAddClientForm(
              searchQuery.value,
              showAddForm,
              nameController,
              mobileController,
              emailController,
            );
          }
          return Column(
            children: [
              const SizedBox(height: 12),
              Container(
                height: 4,
                width: 40,
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Select target Client',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.textMain),
              ),
              const SizedBox(height: 12),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: TextField(
                  controller: searchController,
                  onChanged: (val) {
                    searchQuery.value = val;
                    _bookingController.fetchClients(search: val);
                  },
                  style: const TextStyle(color: AppColors.textMain),
                  decoration: InputDecoration(
                    prefixIcon: const Icon(Icons.search, color: AppColors.textSecondary),
                    hintText: 'Search by Name, Mobile or Email...',
                    hintStyle: const TextStyle(color: AppColors.textSecondary),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: AppColors.green200),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: AppColors.green200),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: AppColors.primary),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              Expanded(
                child: Obx(() {
                  final query = searchQuery.value.trim();
                  if (query.length < 2) {
                    return Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24.0),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.person_search_outlined,
                              size: 64,
                              color: AppColors.textSecondary.withOpacity(0.4),
                            ),
                            const SizedBox(height: 16),
                            const Text(
                              'Search Clients',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.bold,
                                color: AppColors.textMain,
                              ),
                            ),
                            const SizedBox(height: 8),
                            const Text(
                              'Search by Name, Mobile Number or Email\n(Type at least 2 characters to start)',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 13,
                                color: AppColors.textSecondary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  }

                  if (_bookingController.isFetchingClients.value) {
                    return const Center(
                      child: CircularProgressIndicator(color: AppColors.primary),
                    );
                  }

                  if (_bookingController.clientsList.isEmpty) {
                    return Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24.0),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(
                              Icons.search_off_outlined,
                              size: 48,
                              color: Colors.redAccent,
                            ),
                            const SizedBox(height: 12),
                            const Text(
                              'No clients found matching the search criteria.',
                              textAlign: TextAlign.center,
                              style: TextStyle(color: AppColors.textSecondary),
                            ),
                            const SizedBox(height: 20),
                            ElevatedButton.icon(
                              onPressed: () {
                                showAddForm.value = true;
                              },
                              icon: const Icon(Icons.person_add),
                              label: const Text('Add New Client'),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.primary,
                                foregroundColor: Colors.white,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  }

                  return ListView.builder(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    itemCount: _bookingController.clientsList.length,
                    itemBuilder: (context, index) {
                      final client = _bookingController.clientsList[index];
                      return Card(
                        color: AppColors.cardBg,
                        elevation: 0,
                        margin: const EdgeInsets.only(bottom: 8),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                          side: const BorderSide(color: AppColors.green100),
                        ),
                        child: ListTile(
                          leading: const CircleAvatar(
                            backgroundColor: AppColors.green100,
                            child: Icon(Icons.person, color: AppColors.primary),
                          ),
                          title: Text(
                            client['name'] ?? '',
                            style: const TextStyle(fontWeight: FontWeight.bold, color: AppColors.textMain),
                          ),
                          subtitle: Text(
                            '${client['mobile_number'] ?? ''} • ${client['email'] ?? ''}',
                            style: const TextStyle(color: AppColors.textSecondary, fontSize: 12),
                          ),
                          onTap: () {
                            _bookingController.selectedClient.value = client;
                            Get.back();
                          },
                        ),
                      );
                    },
                  );
                }),
              ),
            ],
          );
        }),
      ),
      isScrollControlled: true,
    );
  }

  Widget _buildAddClientForm(
    String queryName,
    RxBool showAddForm,
    TextEditingController nameController,
    TextEditingController mobileController,
    TextEditingController emailController,
  ) {
    if (nameController.text.isEmpty && queryName.isNotEmpty) {
      nameController.text = queryName;
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              IconButton(
                icon: const Icon(Icons.arrow_back, color: AppColors.textMain),
                onPressed: () {
                  showAddForm.value = false;
                },
              ),
              const Text(
                'Add New Client',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                  color: AppColors.textMain,
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          TextField(
            controller: nameController,
            style: const TextStyle(color: AppColors.textMain),
            decoration: InputDecoration(
              labelText: 'Full Name',
              labelStyle: const TextStyle(color: AppColors.textSecondary),
              prefixIcon: const Icon(Icons.person, color: AppColors.textSecondary),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: AppColors.green200),
              ),
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: mobileController,
            keyboardType: TextInputType.phone,
            style: const TextStyle(color: AppColors.textMain),
            decoration: InputDecoration(
              labelText: 'Mobile Number',
              labelStyle: const TextStyle(color: AppColors.textSecondary),
              prefixIcon: const Icon(Icons.phone, color: AppColors.textSecondary),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: AppColors.green200),
              ),
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: emailController,
            keyboardType: TextInputType.emailAddress,
            style: const TextStyle(color: AppColors.textMain),
            decoration: InputDecoration(
              labelText: 'Email Address (Optional)',
              labelStyle: const TextStyle(color: AppColors.textSecondary),
              prefixIcon: const Icon(Icons.email, color: AppColors.textSecondary),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: const BorderSide(color: AppColors.green200),
              ),
            ),
          ),
          const SizedBox(height: 30),
          Obx(() {
            return SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _bookingController.isCreatingClient.value
                    ? null
                    : () async {
                        if (nameController.text.trim().isEmpty) {
                          Get.snackbar('Error', 'Name is required');
                          return;
                        }
                        if (mobileController.text.trim().isEmpty) {
                          Get.snackbar('Error', 'Mobile number is required');
                          return;
                        }
                        final newUser = await _bookingController.registerClient(
                          name: nameController.text.trim(),
                          mobileNumber: mobileController.text.trim(),
                          email: emailController.text.trim().isNotEmpty ? emailController.text.trim() : null,
                        );
                        if (newUser != null) {
                          _bookingController.selectedClient.value = newUser;
                          if (mounted) {
                            Navigator.pop(context);
                          }
                        }
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: _bookingController.isCreatingClient.value
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                      )
                    : const Text(
                        'Create and Select Client',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
              ),
            );
          }),
        ],
      ),
    );
  }

  @override
  void dispose() {
    if (!GetPlatform.isWeb) {
      _razorpay.clear();
    }
    _pageController.dispose();
    _amountPaidController.dispose();
    _couponController.dispose();
    _additionalDiscountController.dispose();
    super.dispose();
  }

  Widget _buildTypeTab(String label, bool isSelected, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(
          horizontal: 12,
          vertical: 6,
        ),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primary : Colors.transparent,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: isSelected ? Colors.white : AppColors.textSecondary,
            fontSize: 12,
            fontWeight: FontWeight.bold,
          ),
        ),
      ),
    );
  }

  Widget _buildCalendarGrid() {
    DateTime firstDayOfMonth = DateTime(_calendarMonth.year, _calendarMonth.month, 1);
    int daysInMonth = DateTime(_calendarMonth.year, _calendarMonth.month + 1, 0).day;
    int firstWeekday = firstDayOfMonth.weekday; // 1 = Mon, 7 = Sun
    
    int offset = firstWeekday == 7 ? 0 : firstWeekday; // Number of empty slots before day 1

    List<Widget> dayWidgets = [];

    const weekdays = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    for (var day in weekdays) {
      dayWidgets.add(
        Center(
          child: Text(
            day,
            style: const TextStyle(
              color: AppColors.textSecondary,
              fontWeight: FontWeight.bold,
              fontSize: 12,
            ),
          ),
        ),
      );
    }

    for (int i = 0; i < offset; i++) {
      dayWidgets.add(const SizedBox.shrink());
    }

    DateTime today = DateTime.now();
    DateTime maxDate = today.add(const Duration(days: 60));

    for (int day = 1; day <= daysInMonth; day++) {
      DateTime date = DateTime(_calendarMonth.year, _calendarMonth.month, day);
      
      bool isPast = DateTime(date.year, date.month, date.day).isBefore(DateTime(today.year, today.month, today.day));
      bool isAfterMax = DateTime(date.year, date.month, date.day).isAfter(DateTime(maxDate.year, maxDate.month, maxDate.day));
      bool isSelectable = !isPast && !isAfterMax;

      bool isSelected = _scatteredDates.any((d) =>
          d.year == date.year && d.month == date.month && d.day == date.day);

      dayWidgets.add(
        GestureDetector(
          onTap: isSelectable
              ? () {
                  setState(() {
                    if (isSelected) {
                      _scatteredDates.removeWhere((d) =>
                          d.year == date.year &&
                          d.month == date.month &&
                          d.day == date.day);
                    } else {
                      _scatteredDates.add(date);
                    }
                    _selectedSlotIds.clear();
                    _totalAmount = 0;
                  });
                  _fetchSlots();
                }
              : null,
          child: Container(
            margin: const EdgeInsets.all(4),
            decoration: BoxDecoration(
              color: isSelected
                  ? AppColors.primary
                  : Colors.transparent,
              shape: BoxShape.circle,
              border: isSelected
                  ? null
                  : (isSelectable
                      ? Border.all(color: AppColors.green200.withOpacity(0.5))
                      : null),
            ),
            alignment: Alignment.center,
            child: Text(
              '$day',
              style: TextStyle(
                color: isSelected
                    ? Colors.white
                    : (isSelectable
                        ? AppColors.textMain
                        : AppColors.textSecondary.withOpacity(0.4)),
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                fontSize: 13,
              ),
            ),
          ),
        ),
      );
    }

    return Container(
      padding: const EdgeInsets.all(15),
      decoration: BoxDecoration(
        color: AppColors.cardBg,
        borderRadius: BorderRadius.circular(15),
        border: Border.all(color: AppColors.green200),
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              IconButton(
                icon: const Icon(Icons.chevron_left, color: AppColors.textMain),
                onPressed: () {
                  setState(() {
                    _calendarMonth = DateTime(_calendarMonth.year, _calendarMonth.month - 1);
                  });
                },
              ),
              Text(
                DateFormat('MMMM yyyy').format(_calendarMonth),
                style: const TextStyle(
                  color: AppColors.textMain,
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                ),
              ),
              IconButton(
                icon: const Icon(Icons.chevron_right, color: AppColors.textMain),
                onPressed: () {
                  setState(() {
                    _calendarMonth = DateTime(_calendarMonth.year, _calendarMonth.month + 1);
                  });
                },
              ),
            ],
          ),
          const SizedBox(height: 10),
          GridView.count(
            crossAxisCount: 7,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            children: dayWidgets,
          ),
          if (_scatteredDates.isNotEmpty) ...[
            const SizedBox(height: 15),
            const Divider(color: AppColors.green200),
            const SizedBox(height: 8),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(
                  Icons.check_circle,
                  color: AppColors.primary,
                  size: 16,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Selected (${_scatteredDates.length} days): ' +
                        _scatteredDates
                            .map((d) => DateFormat('dd MMM').format(d))
                            .join(', '),
                    style: const TextStyle(
                      color: AppColors.textMain,
                      fontSize: 12,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
