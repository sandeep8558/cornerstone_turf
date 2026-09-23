import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';
import '../controllers/booking_controller.dart';
import '../utils/app_colors.dart';

class SlotSelectionScreen extends StatefulWidget {
  final dynamic turf;
  const SlotSelectionScreen({super.key, required this.turf});

  @override
  State<SlotSelectionScreen> createState() => _SlotSelectionScreenState();
}

class _SlotSelectionScreenState extends State<SlotSelectionScreen> {
  final BookingController _bookingController = Get.find<BookingController>();
  late Razorpay _razorpay;
  DateTime _selectedDate = DateTime.now();
  List<int> _selectedSlotIds = [];
  double _totalAmount = 0;
  String _activeTab = 'Morning';

  @override
  void initState() {
    super.initState();
    _fetchSlots();
    if (!GetPlatform.isWeb) {
      _razorpay = Razorpay();
      _razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, _handlePaymentSuccess);
      _razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, _handlePaymentError);
    }
  }

  void _fetchSlots() {
    _bookingController.fetchAvailableSlots(widget.turf['id'], DateFormat('Y-MM-dd').format(_selectedDate));
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

  void _finalizeBooking(String? transactionId) {
    _bookingController.createBooking(
      turfId: widget.turf['id'],
      date: DateFormat('Y-MM-dd').format(_selectedDate),
      slotIds: _selectedSlotIds,
      paymentType: 'Full',
      transactionId: transactionId,
    ).then((success) {
      if (success) {
        Get.offAllNamed('/home');
        Get.snackbar('Success', 'Your booking is confirmed!');
      }
    });
  }
  bool _isSlotPast(dynamic slot) {
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
        _totalAmount -= (slot['amount'] as num).toDouble();

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
            _totalAmount -= (s['amount'] as num).toDouble();
          }
        }
      } else {
        _selectedSlotIds.add(slot['id']);
        _totalAmount += (slot['amount'] as num).toDouble();

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
            (element) => element['from'].toString() == currentTo && element['is_available'] == true,
            orElse: () => null,
          );

          if (nextSlot != null && !_selectedSlotIds.contains(nextSlot['id'])) {
            if (!_isSlotPast(nextSlot)) {
              _selectedSlotIds.add(nextSlot['id']);
              _totalAmount += (nextSlot['amount'] as num).toDouble();
            }
          } else {
            var prevSlot = _bookingController.availableSlots.firstWhere(
              (element) => element['to'].toString() == currentFrom && element['is_available'] == true,
              orElse: () => null,
            );
            if (prevSlot != null && !_selectedSlotIds.contains(prevSlot['id'])) {
              if (!_isSlotPast(prevSlot)) {
                _selectedSlotIds.add(prevSlot['id']);
                _totalAmount += (prevSlot['amount'] as num).toDouble();
              }
            }
          }
        }
      }
    });
  }

  void _startPayment() {
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

    var options = {
      'key': _bookingController.systemSettings['razorpay_key'] ?? 'rzp_test_placeholder',
      'amount': (_totalAmount * 100).toInt(),
      'name': 'Cornerstone Turf',
      'description': 'Turf Booking',
      'prefill': {'contact': '', 'email': ''},
    };

    if (!GetPlatform.isWeb) {
      try {
        _razorpay.open(options);
      } catch (e) {
        debugPrint('Error: $e');
      }
    } else {
      Get.snackbar('Notice', 'Web payments are currently being configured.');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Book Your Slot', style: TextStyle(color: AppColors.textMain, fontWeight: FontWeight.bold, fontSize: 18)),
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(icon: const Icon(Icons.arrow_back, color: AppColors.textMain), onPressed: () => Get.back()),
        centerTitle: true,
      ),
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Date Selection
          const SizedBox(height: 10),
          SizedBox(
            height: 90,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 15),
              itemCount: 14,
              itemBuilder: (context, index) {
                DateTime date = DateTime.now().add(Duration(days: index));
                bool isSelected = DateFormat('Y-MM-dd').format(date) == DateFormat('Y-MM-dd').format(_selectedDate);
                
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
                    width: 50,
                    margin: const EdgeInsets.only(right: 12),
                    child: Column(
                      children: [
                        Text(DateFormat('EEE').format(date), style: TextStyle(color: isSelected ? AppColors.textMain : AppColors.textSecondary, fontSize: 11)),
                        const SizedBox(height: 8),
                        Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: isSelected ? AppColors.primary : Colors.transparent,
                            shape: BoxShape.circle,
                            border: isSelected ? null : Border.all(color: AppColors.green200),
                          ),
                          child: Center(
                            child: Text(
                              DateFormat('dd').format(date),
                              style: TextStyle(color: isSelected ? Colors.white : AppColors.textMain, fontWeight: FontWeight.bold, fontSize: 15),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),

          Padding(
            padding: const EdgeInsets.all(20.0),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Pick a Slot', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: AppColors.textMain)),
                const SizedBox(height: 20),
                
                // Tabs
                Row(
                  children: [
                    _buildTab('Morning', Icons.wb_twilight),
                    const SizedBox(width: 10),
                    _buildTab('Afternoon', Icons.wb_sunny),
                    const SizedBox(width: 10),
                    _buildTab('Night', Icons.dark_mode),
                  ],
                ),
                const SizedBox(height: 25),

                // Slots Grid
                Obx(() {
                  if (_bookingController.isLoading.value) {
                    return const Center(child: CircularProgressIndicator(color: AppColors.primary));
                  }
                  
                  if (_bookingController.availableSlots.isEmpty) {
                    return const Center(child: Text('No slots available.', style: TextStyle(color: AppColors.textSecondary)));
                  }

                  return GridView.builder(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      childAspectRatio: 2.2,
                      crossAxisSpacing: 15,
                      mainAxisSpacing: 15,
                    ),
                    itemCount: _bookingController.availableSlots.length,
                    itemBuilder: (context, index) {
                      final slot = _bookingController.availableSlots[index];
                      bool isBooked = slot['is_available'] == false;
                      bool isPast = _isSlotPast(slot);
                      bool isUnavailable = isBooked || isPast;
                      bool isSelected = _selectedSlotIds.contains(slot['id']);

                      return GestureDetector(
                        onTap: isUnavailable ? null : () => _handleSlotSelection(slot, isSelected),
                        child: Container(
                          decoration: BoxDecoration(
                            color: isSelected ? AppColors.primary : Colors.white,
                            borderRadius: BorderRadius.circular(15),
                            border: Border.all(color: isSelected ? AppColors.primary : AppColors.green200),
                          ),
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                '${slot['from']} - ${slot['to']}',
                                style: TextStyle(
                                  color: isUnavailable ? Colors.black26 : (isSelected ? Colors.white : AppColors.textMain),
                                  fontWeight: FontWeight.bold,
                                  fontSize: 14,
                                ),
                              ),
                              if (isBooked)
                                const Text('Booked', style: TextStyle(color: Colors.redAccent, fontSize: 10, fontWeight: FontWeight.bold))
                              else if (isPast)
                                const Text('Past', style: TextStyle(color: Colors.grey, fontSize: 10, fontWeight: FontWeight.bold))
                            ],
                          ),
                        ),
                      );
                    },
                  );
                }),
              ],
            ),
          ),
          
          const Spacer(),
          // Bottom Continue Button
          Padding(
            padding: const EdgeInsets.all(20.0),
            child: ElevatedButton(
              onPressed: _selectedSlotIds.isEmpty ? null : _startPayment,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                disabledBackgroundColor: AppColors.green200.withOpacity(0.5),
                padding: const EdgeInsets.symmetric(vertical: 18),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                elevation: 0,
                minimumSize: const Size(double.infinity, 50),
              ),
              child: const Text('Continue', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
            ),
          ),
          const SizedBox(height: 10),
        ],
      ),
    );
  }

  Widget _buildTab(String label, IconData icon) {
    bool isActive = _activeTab == label;
    return GestureDetector(
      onTap: () => setState(() => _activeTab = label),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: isActive ? AppColors.accentSurface : Colors.white,
          borderRadius: BorderRadius.circular(15),
          border: Border.all(color: isActive ? AppColors.primary : AppColors.green200),
        ),
        child: Row(
          children: [
            Icon(icon, size: 16, color: isActive ? AppColors.primary : Colors.orange),
            const SizedBox(width: 6),
            Text(label, style: TextStyle(color: isActive ? AppColors.primary : AppColors.textMain, fontSize: 12, fontWeight: FontWeight.bold)),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    if (!GetPlatform.isWeb) {
      _razorpay.clear();
    }
    super.dispose();
  }
}
