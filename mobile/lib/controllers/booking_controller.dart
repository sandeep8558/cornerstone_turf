import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../utils/api_constants.dart';
import 'auth_controller.dart';

class BookingController extends GetxController {
  final _storage = const FlutterSecureStorage();

  var myBookings = [].obs;
  var managerBookings = [].obs;

  void clearBookings() {
    myBookings.clear();
    managerBookings.clear();
  }
  var isLoading = false.obs;
  var isSlotsLoading = false.obs;
  var availableSlots = [].obs;
  var systemSettings = {}.obs;

  // Coupon state
  var appliedCoupon = {}.obs;
  var discountAmount = 0.0.obs;
  var isCouponLoading = false.obs;
  var appliedCoupons = <String, Map<String, dynamic>>{}.obs; // dateStr -> coupon data

  void resetCoupon() {
    appliedCoupon.value = {};
    discountAmount.value = 0.0;
    appliedCoupons.clear();
  }

  Future<bool> validateAndApplyCouponForDate({
    required String code,
    required String dateStr,
    required double dayAmount,
    required int slotsCount,
  }) async {
    if (code.isEmpty) return false;

    isCouponLoading.value = true;
    try {
      final authController = Get.find<AuthController>();
      
      Map<String, String> body = {
        'code': code,
        'amount': dayAmount.toString(),
        'slots_count': slotsCount.toString(),
        'date': dateStr,
      };

      final response = await http.post(
        Uri.parse('${ApiConstants.baseUrl}/coupons/validate'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer ${authController.token.value}',
        },
        body: body,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        appliedCoupons[dateStr] = Map<String, dynamic>.from(data['coupon']);
        Get.snackbar(
          'Success',
          'Coupon applied for $dateStr successfully!',
          backgroundColor: const Color(0xFF22C55E),
          colorText: Colors.white,
        );
        return true;
      } else {
        final data = json.decode(response.body);
        Get.snackbar(
          'Error',
          'For $dateStr: ${data['message'] ?? 'Invalid coupon'}',
          backgroundColor: Colors.redAccent,
          colorText: Colors.white,
        );
        appliedCoupons.remove(dateStr);
        return false;
      }
    } catch (e) {
      Get.snackbar('Error', 'Failed to validate coupon for $dateStr');
      appliedCoupons.remove(dateStr);
      return false;
    } finally {
      isCouponLoading.value = false;
    }
  }

  void removeCouponForDate(String dateStr) {
    appliedCoupons.remove(dateStr);
  }

  Future<List<String>> validateAndApplyCouponForMultipleDates({
    required String code,
    required Map<String, dynamic> reviewData,
  }) async {
    if (code.isEmpty) return [];

    Map<String, dynamic> availableSlots = {};
    if (reviewData['available_slots'] is Map) {
      availableSlots = Map<String, dynamic>.from(reviewData['available_slots']);
    }

    final authController = Get.find<AuthController>();
    List<String> appliedDates = [];
    List<String> errors = [];
    isCouponLoading.value = true;

    try {
      // Find candidate dates that do not have any coupon applied yet
      List<String> candidateDates = [];
      availableSlots.forEach((dateStr, slotList) {
        if (appliedCoupons[dateStr] == null && slotList is List) {
          candidateDates.add(dateStr);
        }
      });

      if (candidateDates.isEmpty) {
        Get.snackbar('Alert', 'All dates already have coupons applied');
        return [];
      }

      // Prepare validation tasks to run in parallel
      List<Future<void>> validationTasks = candidateDates.map((dateStr) async {
        final slotList = availableSlots[dateStr] as List;
        double dayAmount = 0;
        for (var s in slotList) {
          dayAmount += double.tryParse(s['price']?.toString() ?? '0') ?? 0;
        }

        try {
          final response = await http.post(
            Uri.parse('${ApiConstants.baseUrl}/coupons/validate'),
            headers: {
              'Accept': 'application/json',
              'Authorization': 'Bearer ${authController.token.value}',
            },
            body: {
              'code': code,
              'amount': dayAmount.toString(),
              'slots_count': slotList.length.toString(),
              'date': dateStr,
            },
          );

          if (response.statusCode == 200) {
            final data = json.decode(response.body);
            appliedCoupons[dateStr] = Map<String, dynamic>.from(data['coupon']);
            appliedDates.add(dateStr);
          } else {
            final data = json.decode(response.body);
            errors.add('$dateStr: ${data['message'] ?? 'Invalid'}');
          }
        } catch (e) {
          errors.add('$dateStr: Error validating');
        }
      }).toList();

      // Wait for all requests to finish
      await Future.wait(validationTasks);

      if (appliedDates.isNotEmpty) {
        appliedCoupons.refresh(); // Trigger GetX reactive update
        Get.snackbar(
          'Success',
          'Coupon "$code" applied to ${appliedDates.length} date(s).',
          backgroundColor: const Color(0xFF22C55E),
          colorText: Colors.white,
        );
      } else {
        // Show the most relevant error message
        String errMsg = errors.isNotEmpty ? errors.first : 'Invalid coupon';
        Get.snackbar(
          'Error',
          errMsg,
          backgroundColor: Colors.redAccent,
          colorText: Colors.white,
        );
      }
      return appliedDates;
    } catch (e) {
      Get.snackbar('Error', 'An error occurred: $e');
      return [];
    } finally {
      isCouponLoading.value = false;
    }
  }

  // Client selection state
  var selectedClient = Rxn<Map<String, dynamic>>();
  var clientsList = <Map<String, dynamic>>[].obs;
  var isFetchingClients = false.obs;

  void resetClientSelection() {
    selectedClient.value = null;
  }

  var isCreatingClient = false.obs;

  Future<Map<String, dynamic>?> registerClient({
    required String name,
    required String mobileNumber,
    String? email,
  }) async {
    isCreatingClient.value = true;
    try {
      final authController = Get.find<AuthController>();
      
      Map<String, String> body = {
        'name': name,
        'mobile_number': mobileNumber,
      };
      if (email != null && email.trim().isNotEmpty) {
        body['email'] = email;
      }

      final response = await http.post(
        Uri.parse(ApiConstants.managerClients),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer ${authController.token.value}',
        },
        body: body,
      );

      if (response.statusCode == 201) {
        final data = json.decode(response.body);
        Get.snackbar(
          'Success',
          'Client created successfully',
          backgroundColor: const Color(0xFF22C55E),
          colorText: Colors.white,
        );
        return Map<String, dynamic>.from(data['user']);
      } else {
        final data = json.decode(response.body);
        Get.snackbar(
          'Error',
          data['message'] ?? 'Failed to create client',
          backgroundColor: Colors.redAccent,
          colorText: Colors.white,
        );
        return null;
      }
    } catch (e) {
      Get.snackbar('Error', 'An error occurred: $e');
      return null;
    } finally {
      isCreatingClient.value = false;
    }
  }

  Future<void> fetchClients({String search = ''}) async {
    if (search.trim().length < 2) {
      clientsList.clear();
      return;
    }
    isFetchingClients.value = true;
    try {
      final authController = Get.find<AuthController>();
      final response = await http.get(
        Uri.parse('${ApiConstants.managerClients}?search=$search'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer ${authController.token.value}',
        },
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        if (data['users'] != null) {
          clientsList.value = List<Map<String, dynamic>>.from(data['users']);
        } else {
          clientsList.clear();
        }
      } else {
        Get.snackbar('Error', 'Failed to load client list');
      }
    } catch (e) {
      print('Error fetching clients: $e');
    } finally {
      isFetchingClients.value = false;
    }
  }

  void updateDiscount(double amount, {int? slotsCount}) {
    if (appliedCoupon.isEmpty) return;

    final coupon = appliedCoupon;

    // Check minimum order value
    double minOrder =
        double.tryParse(coupon['minimum_order_value']?.toString() ?? '0') ?? 0;
    if (amount < minOrder) {
      resetCoupon();
      Get.snackbar(
        'Coupon Removed',
        'Minimum booking amount for this coupon is ₹$minOrder',
        backgroundColor: Colors.orangeAccent,
        colorText: Colors.white,
        snackPosition: SnackPosition.BOTTOM,
      );
      return;
    }

    // Check minimum slots to be ordered
    int minSlots =
        int.tryParse(coupon['minimum_slots_to_be_ordered']?.toString() ?? '0') ?? 0;
    if (slotsCount != null && minSlots > 0 && slotsCount < minSlots) {
      resetCoupon();
      Get.snackbar(
        'Coupon Removed',
        'You must book at least $minSlots slots to use this coupon.',
        backgroundColor: Colors.orangeAccent,
        colorText: Colors.white,
        snackPosition: SnackPosition.BOTTOM,
      );
      return;
    }

    double discount = 0;
    double discountValue =
        double.tryParse(coupon['discount_value']?.toString() ?? '0') ?? 0;

    if (coupon['discount_type'].toString().toLowerCase() == 'percentage') {
      discount = amount * (discountValue / 100);
      double maxDiscount =
          double.tryParse(
            coupon['max_discount_amount']?.toString() ?? '999999',
          ) ??
          999999;
      if (discount > maxDiscount) discount = maxDiscount;
    } else {
      discount = discountValue;
    }

    if (discount > amount) discount = amount;
    discountAmount.value = double.parse(discount.toStringAsFixed(2));
  }

  Map<String, dynamic> calculateLongBookingDiscount(Map<String, dynamic> reviewData) {
    if (appliedCoupon.isEmpty && appliedCoupons.isEmpty) {
      return {'discount': 0.0, 'eligible_days': 0};
    }

    Map<String, dynamic> availableSlots = {};
    if (reviewData['available_slots'] is Map) {
      availableSlots = Map<String, dynamic>.from(reviewData['available_slots']);
    }

    double totalDiscount = 0.0;
    int qualifyingDaysCount = 0;
    double totalOriginalAmount = double.tryParse(reviewData['total_amount']?.toString() ?? '0') ?? 0;

    availableSlots.forEach((dateStr, slotList) {
      if (slotList is List) {
        // Resolve coupon for this date: date-specific coupon takes precedence
        Map<String, dynamic> coupon = {};
        if (appliedCoupons[dateStr] != null) {
          coupon = appliedCoupons[dateStr]!;
        } else if (appliedCoupon.isNotEmpty) {
          coupon = Map<String, dynamic>.from(appliedCoupon);
        }

        if (coupon.isEmpty) return;

        int minSlots = int.tryParse(coupon['minimum_slots_to_be_ordered']?.toString() ?? '0') ?? 0;
        double minOrder = double.tryParse(coupon['minimum_order_value']?.toString() ?? '0') ?? 0;
        double maxDiscountAmount = double.tryParse(coupon['max_discount_amount']?.toString() ?? '0') ?? 0;

        // Check day-specific validity
        final date = DateTime.parse(dateStr);
        final weekdays = {
          1: 'mon',
          2: 'tue',
          3: 'wed',
          4: 'thu',
          5: 'fri',
          6: 'sat',
          7: 'sun',
        };
        final dayField = weekdays[date.weekday];
        bool isDayValid = true;
        if (coupon[dayField] != null) {
          final val = coupon[dayField];
          isDayValid = val == true || val == 1 || val == '1';
        }

        double dayAmount = 0;
        for (var s in slotList) {
          dayAmount += double.tryParse(s['price']?.toString() ?? '0') ?? 0;
        }

        bool meetsMinSlots = minSlots == 0 || slotList.length >= minSlots;
        bool meetsMinOrder = minOrder == 0 || dayAmount >= minOrder;

        if (isDayValid && meetsMinSlots && meetsMinOrder) {
          qualifyingDaysCount++;
          double discountValue = double.tryParse(coupon['discount_value']?.toString() ?? '0') ?? 0;
          double dayDiscount = 0;

          if (coupon['discount_type'].toString().toLowerCase() == 'percentage') {
            dayDiscount = dayAmount * (discountValue / 100);
          } else {
            dayDiscount = discountValue;
          }

          if (maxDiscountAmount > 0 && dayDiscount > maxDiscountAmount) {
            dayDiscount = maxDiscountAmount;
          }

          totalDiscount += dayDiscount;
        }
      }
    });

    if (totalDiscount > totalOriginalAmount) {
      totalDiscount = totalOriginalAmount;
    }

    return {
      'discount': double.parse(totalDiscount.toStringAsFixed(2)),
      'eligible_days': qualifyingDaysCount,
    };
  }

  Future<bool> validateCoupon(String code, double amount, {int? slotsCount, String? date}) async {
    if (code.isEmpty) return false;

    isCouponLoading.value = true;
    try {
      final authController = Get.find<AuthController>();
      
      Map<String, String> body = {
        'code': code,
        'amount': amount.toString(),
      };
      if (slotsCount != null) {
        body['slots_count'] = slotsCount.toString();
      }
      if (date != null) {
        body['date'] = date;
      }

      final response = await http.post(
        Uri.parse('${ApiConstants.baseUrl}/coupons/validate'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer ${authController.token.value}',
        },
        body: body,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        appliedCoupon.value = data['coupon'];
        discountAmount.value = double.parse(data['discount_amount'].toString());
        Get.snackbar(
          'Success',
          data['message'],
          backgroundColor: const Color(0xFF22C55E),
          colorText: Colors.white,
        );
        return true;
      } else {
        final data = json.decode(response.body);
        Get.snackbar(
          'Error',
          data['message'] ?? 'Invalid coupon',
          backgroundColor: Colors.redAccent,
          colorText: Colors.white,
        );
        resetCoupon();
        return false;
      }
    } catch (e) {
      Get.snackbar('Error', 'Failed to validate coupon');
      resetCoupon();
      return false;
    } finally {
      isCouponLoading.value = false;
    }
  }

  @override
  void onInit() {
    super.onInit();
    fetchSystemSettings();
    fetchMyBookings();
  }

  Future<void> fetchSystemSettings() async {
    try {
      final response = await http.get(
        Uri.parse('${ApiConstants.baseUrl}/settings'),
      );
      if (response.statusCode == 200) {
        systemSettings.value = json.decode(response.body);
      }
    } catch (e) {
      print('Error fetching settings: $e');
    }
  }

  Future<void> fetchMyBookings() async {
    isLoading.value = true;
    try {
      String? token = await _storage.read(key: 'access_token');
      final response = await http.get(
        Uri.parse('${ApiConstants.baseUrl}/bookings'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      if (response.statusCode == 200) {
        myBookings.value = json.decode(response.body);
      }
    } catch (e) {
      print('Error fetching bookings: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> fetchAvailableSlots(int turfId, String date) async {
    isSlotsLoading.value = true;
    try {
      final response = await http.get(
        Uri.parse('${ApiConstants.baseUrl}/slots?turf_id=$turfId&date=$date'),
      );
      if (response.statusCode == 200) {
        availableSlots.value = json.decode(response.body);
      } else {
        print('Slot API error: ${response.statusCode} - ${response.body}');
        availableSlots.clear();
      }
    } catch (e) {
      print('Error fetching slots: $e');
      availableSlots.clear();
    } finally {
      isSlotsLoading.value = false;
    }
  }

  Future<bool> createBooking({
    required int turfId,
    required String date,
    required List<int> slotIds,
    required String paymentType,
    String? transactionId,
    String? couponCode,
    double? amountPaid,
    double? additionalDiscount,
  }) async {
    try {
      String? token = await _storage.read(key: 'access_token');

      Map<String, dynamic> body = {
        'turf_id': turfId,
        'date': date,
        'slot_ids': slotIds,
        'payment_type': paymentType,
      };

      if (transactionId != null) body['transaction_id'] = transactionId;
      if (couponCode != null) body['coupon_code'] = couponCode;
      if (selectedClient.value != null) body['client_id'] = selectedClient.value!['id'];
      if (amountPaid != null) body['amount_paid'] = amountPaid;
      if (additionalDiscount != null) body['additional_discount'] = additionalDiscount;

      final response = await http.post(
        Uri.parse('${ApiConstants.baseUrl}/bookings'),
        headers: {
          'Authorization': 'Bearer $token',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: json.encode(body),
      );

      if (response.statusCode == 200) {
        fetchMyBookings();
        resetClientSelection();
        return true;
      } else {
        final error = json.decode(response.body);
        Get.snackbar('Error', error['message'] ?? 'Failed to create booking');
        return false;
      }
    } catch (e) {
      Get.snackbar('Error', 'An error occurred: $e');
      return false;
    }
  }

  Future<Map<String, dynamic>?> calculateLongBooking({
    required int turfId,
    String? fromDate,
    String? toDate,
    List<String>? dates,
    required List<int> slotIds,
  }) async {
    isSlotsLoading.value = true;
    try {
      String? token = await _storage.read(key: 'access_token');
      final Map<String, dynamic> requestBody = {
        'turf_id': turfId,
        'slot_ids': slotIds,
      };
      if (dates != null) {
        requestBody['dates'] = dates;
      } else {
        requestBody['from_date'] = fromDate;
        requestBody['to_date'] = toDate;
      }

      final response = await http.post(
        Uri.parse('${ApiConstants.baseUrl}/bookings/calculate-long-booking'),
        headers: {
          'Authorization': 'Bearer $token',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: json.encode(requestBody),
      );

      if (response.statusCode == 200) {
        return json.decode(response.body);
      } else {
        final error = json.decode(response.body);
        Get.snackbar(
          'Error',
          error['message'] ?? 'Failed to calculate long booking',
        );
        return null;
      }
    } catch (e) {
      Get.snackbar('Error', 'An error occurred: $e');
      return null;
    } finally {
      isSlotsLoading.value = false;
    }
  }

  Future<bool> createLongBooking({
    required int turfId,
    String? fromDate,
    String? toDate,
    List<String>? dates,
    required List<int> slotIds,
    required String paymentType,
    String? transactionId,
    String? couponCode,
    Map<String, String>? couponCodes,
    double? amountPaid,
    double? additionalDiscount,
  }) async {
    try {
      String? token = await _storage.read(key: 'access_token');

      Map<String, dynamic> body = {
        'turf_id': turfId,
        'slot_ids': slotIds,
        'payment_type': paymentType,
      };
      if (dates != null) {
        body['dates'] = dates;
      } else {
        body['from_date'] = fromDate;
        body['to_date'] = toDate;
      }

      if (transactionId != null) body['transaction_id'] = transactionId;
      if (couponCode != null) body['coupon_code'] = couponCode;
      if (couponCodes != null) body['coupon_codes'] = couponCodes;
      if (selectedClient.value != null) body['client_id'] = selectedClient.value!['id'];
      if (amountPaid != null) body['amount_paid'] = amountPaid;
      if (additionalDiscount != null) body['additional_discount'] = additionalDiscount;

      final response = await http.post(
        Uri.parse('${ApiConstants.baseUrl}/bookings/long'),
        headers: {
          'Authorization': 'Bearer $token',
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: json.encode(body),
      );

      if (response.statusCode == 200) {
        fetchMyBookings();
        resetClientSelection();
        return true;
      } else {
        final error = json.decode(response.body);
        Get.snackbar(
          'Error',
          error['message'] ?? 'Failed to create long booking',
        );
        return false;
      }
    } catch (e) {
      Get.snackbar('Error', 'An error occurred: $e');
      return false;
    }
  }

  Future<void> fetchManagerBookings(String date) async {
    isLoading.value = true;
    try {
      String? token = await _storage.read(key: 'access_token');
      final response = await http.get(
        Uri.parse('${ApiConstants.baseUrl}/manager/bookings?date=$date'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      if (response.statusCode == 200) {
        managerBookings.value = json.decode(response.body);
      } else {
        managerBookings.clear();
      }
    } catch (e) {
      print('Error fetching manager bookings: $e');
      managerBookings.clear();
    } finally {
      isLoading.value = false;
    }
  }

  Future<bool> collectPayment(int bookingId, double amount, String type) async {
    try {
      String? token = await _storage.read(key: 'access_token');
      final response = await http.post(
        Uri.parse(
          '${ApiConstants.baseUrl}/manager/bookings/$bookingId/collect',
        ),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: {'amount': amount.toString(), 'type': type},
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        // Update the booking in the list
        int index = managerBookings.indexWhere((b) => b['id'] == bookingId);
        if (index != -1) {
          managerBookings[index] = data['booking'];
        }
        Get.snackbar(
          'Success',
          'Payment collected successfully',
          backgroundColor: const Color(0xFF22C55E),
          colorText: Colors.white,
        );
        return true;
      } else {
        Get.snackbar('Error', 'Failed to collect payment');
        return false;
      }
    } catch (e) {
      Get.snackbar('Error', 'Connection error');
      return false;
    }
  }

  Future<bool> updateManagerFields(
    int bookingId, {
    String? status,
    int? players,
    String? came,
  }) async {
    try {
      String? token = await _storage.read(key: 'access_token');
      Map<String, String> body = {};

      if (status != null) body['status'] = status;
      if (players != null) body['players'] = players.toString();
      if (came != null) body['came'] = came;

      final response = await http.post(
        Uri.parse('${ApiConstants.baseUrl}/manager/bookings/$bookingId/status'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: body,
      );

      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        int index = managerBookings.indexWhere((b) => b['id'] == bookingId);
        if (index != -1) {
          managerBookings[index] = data['booking'];
        }
        Get.snackbar(
          'Success',
          'Updated successfully',
          backgroundColor: const Color(0xFF22C55E),
          colorText: Colors.white,
        );
        return true;
      } else {
        Get.snackbar('Error', 'Update failed');
        return false;
      }
    } catch (e) {
      Get.snackbar('Error', 'Connection error');
      return false;
    }
  }

  Future<Map<String, dynamic>?> fetchSingleManagerBooking(int bookingId) async {
    try {
      String? token = await _storage.read(key: 'access_token');
      final response = await http.get(
        Uri.parse('${ApiConstants.baseUrl}/manager/bookings/$bookingId'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );

      if (response.statusCode == 200) {
        return json.decode(response.body) as Map<String, dynamic>;
      } else {
        Get.snackbar('Error', 'Booking not found');
        return null;
      }
    } catch (e) {
      Get.snackbar('Error', 'Connection error');
      return null;
    }
  }
}
