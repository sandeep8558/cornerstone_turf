import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:get/get.dart';
import '../controllers/booking_controller.dart';
import '../utils/app_colors.dart';
import 'manager_booking_detail_screen.dart';

class ManagerQRScannerScreen extends StatefulWidget {
  const ManagerQRScannerScreen({super.key});

  @override
  State<ManagerQRScannerScreen> createState() => _ManagerQRScannerScreenState();
}

class _ManagerQRScannerScreenState extends State<ManagerQRScannerScreen> {
  final MobileScannerController cameraController = MobileScannerController();
  final BookingController _bookingController = Get.find<BookingController>();
  bool _hasScanned = false;
  bool _isLoading = false;

  @override
  void dispose() {
    cameraController.dispose();
    super.dispose();
  }

  void _handleQRCode(String rawValue) async {
    // Expected format: "cornerstone_booking:ID"
    if (!rawValue.startsWith('cornerstone_booking:')) {
      setState(() {
        _hasScanned = false;
      });
      Get.snackbar(
        'Invalid QR Code',
        'This QR code is not recognized by Cornerstone Turf.',
        backgroundColor: Colors.red,
        colorText: Colors.white,
      );
      return;
    }

    final String idStr = rawValue.substring('cornerstone_booking:'.length);
    final int? bookingId = int.tryParse(idStr);

    if (bookingId == null) {
      setState(() {
        _hasScanned = false;
      });
      Get.snackbar(
        'Invalid Booking ID',
        'Could not parse booking reference.',
        backgroundColor: Colors.red,
        colorText: Colors.white,
      );
      return;
    }

    setState(() {
      _isLoading = true;
    });

    final booking = await _bookingController.fetchSingleManagerBooking(bookingId);

    if (booking != null) {
      // Navigate to detail screen and remove scanner screen from stack
      Get.off(() => ManagerBookingDetailScreen(initialBooking: booking));
    } else {
      setState(() {
        _isLoading = false;
        _hasScanned = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final scanArea = (MediaQuery.of(context).size.width < 400 ||
            MediaQuery.of(context).size.height < 400)
        ? 240.0
        : 320.0;

    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        title: const Text(
          'Scan QR Code',
          style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
        ),
        backgroundColor: Colors.black,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.white),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: Stack(
        children: [
          // 1. Scanner View
          MobileScanner(
            controller: cameraController,
            onDetect: (capture) {
              if (_hasScanned || _isLoading) return;
              final List<Barcode> barcodes = capture.barcodes;
              for (final barcode in barcodes) {
                final String? rawValue = barcode.rawValue;
                if (rawValue != null) {
                  setState(() {
                    _hasScanned = true;
                  });
                  _handleQRCode(rawValue);
                  break;
                }
              }
            },
          ),

          // 2. Viewfinder Overlay
          ColorFiltered(
            colorFilter: ColorFilter.mode(
              Colors.black.withOpacity(0.5),
              BlendMode.srcOut,
            ),
            child: Stack(
              children: [
                Container(
                  color: Colors.transparent,
                ),
                Center(
                  child: Container(
                    height: scanArea,
                    width: scanArea,
                    decoration: BoxDecoration(
                      color: Colors.black,
                      borderRadius: BorderRadius.circular(20),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // 3. Scan Border Box
          Center(
            child: Container(
              height: scanArea,
              width: scanArea,
              decoration: BoxDecoration(
                border: Border.all(color: AppColors.primary, width: 3),
                borderRadius: BorderRadius.circular(20),
              ),
            ),
          ),

          // 4. Instructions helper text
          Positioned(
            bottom: 80,
            left: 20,
            right: 20,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              decoration: BoxDecoration(
                color: Colors.black.withOpacity(0.8),
                borderRadius: BorderRadius.circular(12),
              ),
              child: const Text(
                'Align the booking QR code inside the green box to scan',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ),

          // 5. Loading overlay when fetching details
          if (_isLoading)
            Container(
              color: Colors.black.withOpacity(0.7),
              child: Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const CircularProgressIndicator(color: AppColors.primary),
                    const SizedBox(height: 16),
                    const Text(
                      'Retrieving Booking Details...',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 15,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
