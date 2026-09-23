import 'dart:async';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:animate_do/animate_do.dart';
import '../controllers/turf_controller.dart';
import '../utils/api_constants.dart';
import '../utils/app_colors.dart';
import 'turf_detail_screen.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final TurfController controller = Get.put(TurfController());

    return Scaffold(
      backgroundColor: Colors.transparent, // Background handled by main navigation or theme
      body: RefreshIndicator(
        onRefresh: () async {
          controller.fetchLocations();
          controller.fetchSliders();
          controller.fetchTurfs(
            locationId: controller.selectedLocationId.value,
          );
        },
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [

              // Location Filter
              FadeInLeft(
                child: Row(
                  children: [
                    Expanded(
                      child: GestureDetector(
                        onTap: () {
                          TextEditingController searchController = TextEditingController();
                          Get.dialog(
                            AlertDialog(
                              backgroundColor: AppColors.cardBg,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(20),
                                side: const BorderSide(color: AppColors.green200),
                              ),
                              title: const Text(
                                'Enter Location',
                                style: TextStyle(color: AppColors.textMain, fontWeight: FontWeight.bold),
                              ),
                              content: TextField(
                                controller: searchController,
                                style: const TextStyle(color: AppColors.textMain),
                                decoration: InputDecoration(
                                  hintText: 'e.g., Mumbai, London',
                                  hintStyle: TextStyle(color: AppColors.textMain.withOpacity(0.4)),
                                  enabledBorder: const UnderlineInputBorder(
                                    borderSide: BorderSide(color: AppColors.green200),
                                  ),
                                  focusedBorder: const UnderlineInputBorder(
                                    borderSide: BorderSide(color: AppColors.primary),
                                  ),
                                ),
                              ),
                              actions: [
                                TextButton(
                                  onPressed: () => Get.back(),
                                  child: const Text(
                                    'Cancel',
                                    style: TextStyle(color: AppColors.textSecondary, fontWeight: FontWeight.w600),
                                  ),
                                ),
                                ElevatedButton(
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppColors.primary,
                                    foregroundColor: Colors.white,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                  ),
                                  onPressed: () {
                                    Get.back();
                                    if (searchController.text.trim().isNotEmpty) {
                                      controller.searchLocationByCity(searchController.text.trim());
                                    }
                                  },
                                  child: const Text('Search', style: TextStyle(fontWeight: FontWeight.bold)),
                                ),
                              ],
                            ),
                          );
                        },
                        child: Container(
                          height: 50,
                          padding: const EdgeInsets.symmetric(horizontal: 15),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(15),
                            border: Border.all(color: AppColors.green200),
                            boxShadow: [
                              BoxShadow(
                                color: AppColors.green800.withOpacity(0.04),
                                blurRadius: 8,
                                offset: const Offset(0, 2),
                              ),
                            ],
                          ),
                          child: Row(
                            children: [
                              Obx(() {
                                  if (controller.isLocationLoading.value) {
                                    return const SizedBox(
                                      height: 20,
                                      width: 20,
                                      child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
                                    );
                                  } else if (controller.isLocationSuccess.value) {
                                    return const Icon(Icons.check_circle, color: AppColors.primary);
                                  } else {
                                    return const Icon(Icons.location_on_outlined, color: AppColors.primary);
                                  }
                              }),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Obx(() => Text(
                                  controller.userLocationName.value,
                                  style: const TextStyle(
                                    color: AppColors.textMain,
                                    fontSize: 16,
                                    fontWeight: FontWeight.bold,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                )),
                              ),
                              const Icon(Icons.search, color: AppColors.textSecondary, size: 20),
                            ],
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Container(
                      height: 50,
                      width: 50,
                      decoration: BoxDecoration(
                        color: AppColors.primary,
                        borderRadius: BorderRadius.circular(15),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primary.withOpacity(0.2),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: IconButton(
                        icon: const Icon(Icons.my_location, color: Colors.white),
                        onPressed: () {
                          controller.fetchUserLocation();
                        },
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 25),

              // Image Slider
              Obx(() {
                if (controller.isSlidersLoading.value && controller.sliders.isEmpty) {
                  return Container(
                    height: 160,
                    width: double.infinity,
                    margin: const EdgeInsets.only(bottom: 25),
                    decoration: BoxDecoration(
                      color: AppColors.green50,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Center(
                      child: CircularProgressIndicator(color: AppColors.primary),
                    ),
                  );
                }

                if (controller.sliders.isEmpty) {
                  return const SizedBox.shrink();
                }

                return FadeInUp(
                  duration: const Duration(milliseconds: 600),
                  child: Column(
                    children: [
                      HomeImageSlider(sliders: controller.sliders),
                      const SizedBox(height: 25),
                    ],
                  ),
                );
              }),

              // Turf Listing (Grouped by Location)
              Obx(() {
                if (controller.isLoading.value) {
                  return const Center(
                    child: CircularProgressIndicator(color: AppColors.primary),
                  );
                }

                if (controller.turfs.isEmpty) {
                  return const Center(
                    child: Padding(
                      padding: EdgeInsets.only(top: 50),
                      child: Text(
                        'No venues found in this location.',
                        style: TextStyle(color: AppColors.textSecondary, fontWeight: FontWeight.w500),
                      ),
                    ),
                  );
                }

                // Filter unique locations
                final Map<int, dynamic> uniqueLocationTurfs = {};
                for (var turf in controller.turfs) {
                  int locId = turf['location_id'];
                  if (!uniqueLocationTurfs.containsKey(locId)) {
                    uniqueLocationTurfs[locId] = turf;
                  }
                }

                final List<dynamic> displayTurfList = uniqueLocationTurfs.values
                    .toList();

                return ListView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: displayTurfList.length,
                  itemBuilder: (context, index) {
                    final turf = displayTurfList[index];
                    return FadeInUp(
                      delay: Duration(milliseconds: 100 * index),
                      child: _buildTurfCard(turf),
                    );
                  },
                );
              }),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildTurfCard(dynamic turf) {
    return GestureDetector(
      onTap: () => Get.to(() => TurfDetailScreen(turf: turf)),
      child: Container(
        margin: const EdgeInsets.only(bottom: 20),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: AppColors.green200.withOpacity(0.6)),
          boxShadow: [
            BoxShadow(
              color: AppColors.green800.withOpacity(0.04),
              blurRadius: 12,
              offset: const Offset(0, 6),
            ),
          ],
        ),
        clipBehavior: Clip.antiAlias,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(
                top: Radius.circular(20),
              ),
              child: AspectRatio(
                aspectRatio: 4 / 3,
                child: Stack(
                  children: [
                    _getTurfImages(turf).isNotEmpty
                        ? TurfImageCarousel(images: _getTurfImages(turf))
                        : Container(
                            height: double.infinity,
                            width: double.infinity,
                            color: AppColors.green100,
                            child: Icon(
                              Icons.sports_soccer,
                              color: AppColors.primary.withOpacity(0.2),
                              size: 80,
                            ),
                          ),
                    Positioned(
                      top: 15,
                      right: 15,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 5,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.black54,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Row(
                          children: [
                            Icon(Icons.star, color: Colors.amber, size: 16),
                            SizedBox(width: 4),
                            Text(
                              '4.8',
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
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(15.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          turf['location']?['name'] ?? 'No Location',
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                            color: AppColors.textMain,
                          ),
                        ),
                      ),
                      Text(
                        '₹${((turf['slots'] != null && (turf['slots'] as List).isNotEmpty) ? (turf['slots'][0]['mon_amount'] ?? '799') : '799')}',
                        style: const TextStyle(
                          color: AppColors.primary,
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.pin_drop_outlined, color: AppColors.primary, size: 14),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          turf['location']?['address'] ??
                              'No address available',
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 12,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 4,
                    children: (turf['sports'] as List)
                        .take(3)
                        .map(
                          (sport) => Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 4,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.green50,
                              border: Border.all(color: AppColors.green200),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Text(
                              sport['name'],
                              style: const TextStyle(
                                color: AppColors.textMain,
                                fontSize: 11,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ),
                        )
                        .toList(),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
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
}

class TurfImageCarousel extends StatefulWidget {
  final List<String> images;

  const TurfImageCarousel({super.key, required this.images});

  @override
  State<TurfImageCarousel> createState() => _TurfImageCarouselState();
}

class _TurfImageCarouselState extends State<TurfImageCarousel> {
  int _currentIndex = 0;

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        PageView.builder(
          onPageChanged: (index) {
            setState(() {
              _currentIndex = index;
            });
          },
          itemCount: widget.images.length,
          itemBuilder: (context, index) {
            return Image.network(
              widget.images[index],
              fit: BoxFit.cover,
              errorBuilder: (context, error, stackTrace) => Container(
                color: AppColors.green100,
                child: const Icon(
                  Icons.broken_image,
                  color: AppColors.primary,
                  size: 50,
                ),
              ),
            );
          },
        ),
        if (widget.images.length > 1)
          Positioned(
            bottom: 15,
            left: 0,
            right: 0,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: widget.images.asMap().entries.map((entry) {
                return AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  width: _currentIndex == entry.key ? 20 : 8,
                  height: 8,
                  margin: const EdgeInsets.symmetric(horizontal: 4),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(4),
                    color: _currentIndex == entry.key
                        ? AppColors.primary
                        : Colors.white.withOpacity(0.5),
                  ),
                );
              }).toList(),
            ),
          ),
      ],
    );
  }
}

class HomeImageSlider extends StatefulWidget {
  final List<dynamic> sliders;

  const HomeImageSlider({super.key, required this.sliders});

  @override
  State<HomeImageSlider> createState() => _HomeImageSliderState();
}

class _HomeImageSliderState extends State<HomeImageSlider> {
  late final PageController _pageController;
  int _currentIndex = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _pageController = PageController(initialPage: 0);
    _startAutoPlay();
  }

  void _startAutoPlay() {
    if (widget.sliders.length <= 1) return;
    _timer = Timer.periodic(const Duration(seconds: 4), (timer) {
      if (_pageController.hasClients) {
        int nextIndex = (_currentIndex + 1) % widget.sliders.length;
        _pageController.animateToPage(
          nextIndex,
          duration: const Duration(milliseconds: 800),
          curve: Curves.easeInOut,
        );
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.sliders.isEmpty) return const SizedBox.shrink();

    return AspectRatio(
      aspectRatio: 5 / 3,
      child: Container(
        width: double.infinity,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(20),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(20),
          child: Stack(
            children: [
              PageView.builder(
                controller: _pageController,
                onPageChanged: (index) {
                  setState(() {
                    _currentIndex = index;
                  });
                },
                itemCount: widget.sliders.length,
                itemBuilder: (context, index) {
                  final slider = widget.sliders[index];
                  final String imagePath = slider['image_url']?.toString() ?? slider['image']?.toString() ?? '';
                  final String imageUrl = imagePath.startsWith('http')
                      ? imagePath
                      : '${ApiConstants.imageBaseUrl}${imagePath.startsWith('/') ? '' : '/'}$imagePath';

                  return Stack(
                    fit: StackFit.expand,
                    children: [
                      Image.network(
                        imageUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (context, error, stackTrace) => Container(
                          color: AppColors.green50,
                          child: const Icon(
                            Icons.broken_image_outlined,
                            color: AppColors.primary,
                            size: 40,
                          ),
                        ),
                      ),
                      if (slider['title'] != null && slider['title'].toString().isNotEmpty) ...[
                        Positioned.fill(
                          child: DecoratedBox(
                            decoration: BoxDecoration(
                              gradient: LinearGradient(
                                begin: Alignment.topCenter,
                                end: Alignment.bottomCenter,
                                colors: [
                                  Colors.transparent,
                                  Colors.black.withOpacity(0.6),
                                ],
                                stops: const [0.6, 1.0],
                              ),
                            ),
                          ),
                        ),
                        Positioned(
                          bottom: 15,
                          left: 15,
                          right: 15,
                          child: Text(
                            slider['title'].toString(),
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                              letterSpacing: 0.5,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                        ),
                      ),
                    ],
                  ],
                );
              },
            ),
            if (widget.sliders.length > 1)
              Positioned(
                bottom: 15,
                right: 15,
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: widget.sliders.asMap().entries.map((entry) {
                    return AnimatedContainer(
                      duration: const Duration(milliseconds: 350),
                      width: _currentIndex == entry.key ? 16 : 6,
                      height: 6,
                      margin: const EdgeInsets.symmetric(horizontal: 3),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(3),
                        color: _currentIndex == entry.key
                            ? Colors.white
                            : Colors.white.withOpacity(0.4),
                      ),
                    );
                  }).toList(),
                ),
              ),
          ],
        ),
      ),
    ),
  );
}
}

