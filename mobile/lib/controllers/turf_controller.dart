import 'dart:convert';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import 'package:geolocator/geolocator.dart';
import 'package:geocoding/geocoding.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter/foundation.dart' show kIsWeb;
import '../utils/api_constants.dart';
import 'auth_controller.dart';

class TurfController extends GetxController {
  var locations = [].obs;
  var turfs = [].obs;
  var offers = [].obs;
  var sliders = [].obs;
  var isLoading = false.obs;
  var isSlidersLoading = false.obs;
  var selectedLocationId = 0.obs;
  var userLocationName = 'All Locations'.obs;
  var isLocationLoading = false.obs;
  var isLocationSuccess = false.obs;
  
  final _storage = const FlutterSecureStorage();

  @override
  void onInit() {
    super.onInit();
    _initializeApp();
  }

  Future<void> _initializeApp() async {
    await fetchLocations();
    await fetchOffers();
    await fetchSliders();
    bool hasSavedLocation = await _loadSavedLocation();
    
    if (!hasSavedLocation) {
      await fetchUserLocation(isInitial: true);
    }
  }

  Future<bool> _loadSavedLocation() async {
    String? savedName = await _storage.read(key: 'user_geo_location');
    String? savedLat = await _storage.read(key: 'user_lat');
    String? savedLng = await _storage.read(key: 'user_lng');
    
    if (savedName != null && savedLat != null && savedLng != null) {
      userLocationName.value = savedName;
      double lat = double.tryParse(savedLat) ?? 0;
      double lng = double.tryParse(savedLng) ?? 0;
      
      if (lat != 0 && lng != 0) {
        _filterTurfsByClosestLocation(lat, lng);
        return true;
      }
    }
    return false;
  }

  Future<void> fetchUserLocation({bool isInitial = false}) async {
    isLocationLoading.value = true;
    bool serviceEnabled;
    LocationPermission permission;

    serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      isLocationLoading.value = false;
      if (isInitial) fetchTurfs();
      return;
    }

    permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        isLocationLoading.value = false;
        if (isInitial) fetchTurfs();
        return;
      }
    }
    
    if (permission == LocationPermission.deniedForever) {
      isLocationLoading.value = false;
      if (isInitial) fetchTurfs();
      return;
    } 

    try {
      Position position = await Geolocator.getCurrentPosition(desiredAccuracy: LocationAccuracy.high);
      String locName = 'Current Location';
      
      if (!kIsWeb) {
        try {
          List<Placemark> placemarks = await placemarkFromCoordinates(position.latitude, position.longitude);
          if (placemarks.isNotEmpty) {
            Placemark place = placemarks[0];
            if (place.locality != null && place.locality!.isNotEmpty) {
              locName = place.locality!;
            } else if (place.subAdministrativeArea != null && place.subAdministrativeArea!.isNotEmpty) {
              locName = place.subAdministrativeArea!;
            }
          }
        } catch (e) {
          print('Geocoding error: $e');
        }
      }
      
      userLocationName.value = locName;
      await _storage.write(key: 'user_geo_location', value: locName);
      await _storage.write(key: 'user_lat', value: position.latitude.toString());
      await _storage.write(key: 'user_lng', value: position.longitude.toString());
      
      _filterTurfsByClosestLocation(position.latitude, position.longitude);
      _showLocationSuccess();
    } catch (e) {
      print('Location error: $e');
      if (isInitial) fetchTurfs();
    } finally {
      isLocationLoading.value = false;
    }
  }

  void _showLocationSuccess() {
    isLocationSuccess.value = true;
    Future.delayed(const Duration(seconds: 3), () {
      isLocationSuccess.value = false;
    });
  }

  void _filterTurfsByClosestLocation(double userLat, double userLng) {
    if (locations.isEmpty) return;

    int closestId = 0;
    double minDistance = double.infinity;

    for (var loc in locations) {
      double locLat = double.tryParse(loc['latitude']?.toString() ?? loc['lat']?.toString() ?? '0') ?? 0;
      double locLng = double.tryParse(loc['longitude']?.toString() ?? loc['lng']?.toString() ?? loc['lon']?.toString() ?? '0') ?? 0;

      if (locLat != 0 && locLng != 0) {
        double distance = Geolocator.distanceBetween(userLat, userLng, locLat, locLng);
        if (distance < minDistance) {
          minDistance = distance;
          closestId = loc['id'];
        }
      }
    }

    if (closestId != 0 && minDistance <= 20000) {
      filterByLocation(closestId);
    } else {
      turfs.value = [];
      selectedLocationId.value = 0;
    }
  }

  Future<void> searchLocationByCity(String cityName) async {
    isLocationLoading.value = true;
    try {
      List<Location> geocodedLocations = await locationFromAddress(cityName);
      if (geocodedLocations.isNotEmpty) {
        Location loc = geocodedLocations.first;
        List<Placemark> placemarks = await placemarkFromCoordinates(loc.latitude, loc.longitude);
        
        if (placemarks.isNotEmpty) {
          Placemark place = placemarks[0];
          String locName = '';
          if (place.locality != null && place.locality!.isNotEmpty) {
            locName = place.locality!;
          } else if (place.subAdministrativeArea != null && place.subAdministrativeArea!.isNotEmpty) {
            locName = place.subAdministrativeArea!;
          } else {
            locName = cityName; // fallback to what user typed
          }
          
          userLocationName.value = locName;
          await _storage.write(key: 'user_geo_location', value: locName);
          await _storage.write(key: 'user_lat', value: loc.latitude.toString());
          await _storage.write(key: 'user_lng', value: loc.longitude.toString());
          
          _filterTurfsByClosestLocation(loc.latitude, loc.longitude);
          _showLocationSuccess();
        }
      }
    } catch (e) {
      print('Manual location error: $e');
      Get.snackbar('Error', 'Could not find location "$cityName". Please try another city.');
    } finally {
      isLocationLoading.value = false;
    }
  }

  Future<void> fetchOffers() async {
    isLoading.value = true;
    try {
      final response = await http.get(Uri.parse(ApiConstants.offers));
      if (response.statusCode == 200) {
        offers.value = json.decode(response.body);
      }
    } catch (e) {
      print('Error fetching offers: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> fetchSliders() async {
    isSlidersLoading.value = true;
    try {
      final response = await http.get(Uri.parse(ApiConstants.sliders));
      if (response.statusCode == 200) {
        sliders.value = json.decode(response.body);
      }
    } catch (e) {
      print('Error fetching sliders: $e');
    } finally {
      isSlidersLoading.value = false;
    }
  }

  Future<void> fetchLocations() async {
    try {
      final response = await http.get(Uri.parse('${ApiConstants.baseUrl}/locations'));
      if (response.statusCode == 200) {
        locations.value = json.decode(response.body);
      }
    } catch (e) {
      print('Error fetching locations: $e');
    }
  }

  Future<void> fetchTurfs({int? locationId}) async {
    isLoading.value = true;
    try {
      String url = '${ApiConstants.baseUrl}/turfs';
      if (locationId != null && locationId != 0) {
        url += '?location_id=$locationId';
      }
      
      final response = await http.get(Uri.parse(url));
      if (response.statusCode == 200) {
        turfs.value = json.decode(response.body);
      }
    } catch (e) {
      print('Error fetching turfs: $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<Map<String, dynamic>?> fetchTurfById(int id) async {
    try {
      final response = await http.get(Uri.parse('${ApiConstants.baseUrl}/turf/$id'));
      if (response.statusCode == 200) {
        return json.decode(response.body);
      }
    } catch (e) {
      print('Error fetching turf by id: $e');
    }
    return null;
  }

  void filterByLocation(int locationId) {
    selectedLocationId.value = locationId;
    fetchTurfs(locationId: locationId);
  }
}
