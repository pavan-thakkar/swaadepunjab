import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import '../models/menu_item.dart';
import '../models/cart_item.dart';
import '../services/api_service.dart';

class CartProvider with ChangeNotifier {
  final List<CartItem> _items = [];
  bool _isCartOpen = false;

  String? _userPhone;
  String? _userName;

  double _customDeliveryFee = 0.0;
  double? _distanceKm;
  String _distanceError = '';

  CartProvider() {
    _loadAuthAndCartFromPrefs();
  }

  List<CartItem> get items => _items;
  bool get isCartOpen => _isCartOpen;
  String? get userPhone => _userPhone;
  String? get userName => _userName;

  double get customDeliveryFee => _customDeliveryFee;
  double? get distanceKm => _distanceKm;
  String get distanceError => _distanceError;

  int get itemCount => _items.fold(0, (sum, item) => sum + item.quantity);
  double get totalSubtotal => _items.fold(0.0, (sum, item) => sum + item.subtotal);

  void toggleCart() {
    _isCartOpen = !_isCartOpen;
    notifyListeners();
  }

  void closeCart() {
    _isCartOpen = false;
    notifyListeners();
  }

  void addItem(MenuItem menuItem) {
    final existingIndex = _items.indexWhere((i) => i.menuItem.id == menuItem.id);
    if (existingIndex >= 0) {
      _items[existingIndex].quantity += 1;
    } else {
      _items.add(CartItem(menuItem: menuItem));
    }
    _saveCartToPrefs();
    notifyListeners();
  }

  void incrementQuantity(int itemId) {
    final index = _items.indexWhere((i) => i.menuItem.id == itemId);
    if (index >= 0) {
      _items[index].quantity += 1;
      _saveCartToPrefs();
      notifyListeners();
    }
  }

  void decrementQuantity(int itemId) {
    final index = _items.indexWhere((i) => i.menuItem.id == itemId);
    if (index >= 0) {
      if (_items[index].quantity <= 1) {
        _items.removeAt(index);
      } else {
        _items[index].quantity -= 1;
      }
      _saveCartToPrefs();
      notifyListeners();
    }
  }

  void removeItem(int itemId) {
    _items.removeWhere((i) => i.menuItem.id == itemId);
    _saveCartToPrefs();
    notifyListeners();
  }

  void clearCart() {
    _items.clear();
    _saveCartToPrefs();
    notifyListeners();
  }

  Future<void> calculateDeliveryFee(String address, String city, double? latitude, double? longitude) async {
    if (address.isEmpty) return;
    
    final result = await ApiService.calculateDeliveryFee(
      address: address,
      city: city,
      subtotal: totalSubtotal,
      latitude: latitude,
      longitude: longitude,
    );

    if (result['success'] == true) {
      _customDeliveryFee = (result['fee'] as num).toDouble();
      _distanceKm = result['distance_km'] != null ? (result['distance_km'] as num).toDouble() : null;
      _distanceError = result['error'] ?? '';
    } else {
      _customDeliveryFee = 0.0;
      _distanceKm = null;
      _distanceError = result['error'] ?? 'Distance check failed';
    }
    notifyListeners();
  }

  void resetDistanceFee() {
    _customDeliveryFee = 0.0;
    _distanceKm = null;
    _distanceError = '';
    notifyListeners();
  }

  String? _userEmail;
  String? _userAddress;
  String? _userCity;
  double? _userLatitude;
  double? _userLongitude;

  String? get userEmail => _userEmail;
  String? get userAddress => _userAddress;
  String? get userCity => _userCity;
  double? get userLatitude => _userLatitude;
  double? get userLongitude => _userLongitude;

  Future<void> saveProfile({
    required String name,
    required String phone,
    required String email,
    required String address,
    required String city,
  }) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('user_name', name);
    await prefs.setString('user_phone', phone);
    await prefs.setString('user_email', email);
    await prefs.setString('user_address', address);
    await prefs.setString('user_city', city);
    
    _userName = name;
    _userPhone = phone;
    _userEmail = email;
    _userAddress = address;
    _userCity = city;
    notifyListeners();
  }

  Future<void> updateGPSLocation(double lat, double lng) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setDouble('user_latitude', lat);
    await prefs.setDouble('user_longitude', lng);
    _userLatitude = lat;
    _userLongitude = lng;

    // Check for default simulator mock locations to override with a realistic Amritsar address
    bool isSimulatorMock = false;
    if ((lat - 37.7858).abs() < 0.01 && (lng - -122.4064).abs() < 0.01) {
      isSimulatorMock = true; // Apple Simulator SF
    } else if ((lat - 37.4219).abs() < 0.01 && (lng - -122.0840).abs() < 0.01) {
      isSimulatorMock = true; // Google Emulator MV
    } else if (lat == 0.0 && lng == 0.0) {
      isSimulatorMock = true;
    }

    if (isSimulatorMock) {
      final overrideAddress = "Ranjit Avenue, Block B";
      final overrideCity = "Amritsar";
      _userAddress = overrideAddress;
      _userCity = overrideCity;
      await prefs.setString('user_address', overrideAddress);
      await prefs.setString('user_city', overrideCity);
      await calculateDeliveryFee(overrideAddress, overrideCity, lat, lng);
      notifyListeners();
      return;
    }

    try {
      final url = Uri.parse('https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lng&addressdetails=1');
      final response = await http.get(url, headers: {'User-Agent': 'swaad_e_punjab_app'});
      if (response.statusCode == 200) {
        final decoded = json.decode(response.body);
        if (decoded != null && decoded['address'] != null) {
          final addr = decoded['address'];
          
          final road = addr['road'] ?? addr['suburb'] ?? addr['neighbourhood'] ?? addr['village'] ?? addr['subdistrict'];
          final residential = addr['residential'] ?? addr['apartment'] ?? addr['hotel'] ?? addr['amenity'];
          final city = addr['city'] ?? addr['town'] ?? addr['county'] ?? 'Amritsar';
          
          String areaName = '';
          if (residential != null) {
            areaName += '$residential, ';
          }
          if (road != null) {
            areaName += '$road';
          }
          
          if (areaName.isEmpty) {
            areaName = decoded['display_name'] ?? 'GPS Location';
            if (areaName.contains(',')) {
              areaName = areaName.split(',').take(2).join(',').trim();
            }
          }
          
          if (areaName.endsWith(', ')) {
            areaName = areaName.substring(0, areaName.length - 2);
          }
          
          _userAddress = areaName;
          _userCity = city;
          await prefs.setString('user_address', areaName);
          await prefs.setString('user_city', city);
          
          await calculateDeliveryFee(areaName, city, lat, lng);
        }
      }
    } catch (e) {
      debugPrint("Reverse geocoding error: $e");
    }

    notifyListeners();
  }

  Future<void> loginUser(String phone, String name) async {
    await saveProfile(
      name: name,
      phone: phone,
      email: _userEmail ?? '',
      address: _userAddress ?? '',
      city: _userCity ?? 'Amritsar',
    );
  }

  Future<void> logoutUser() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('user_phone');
    await prefs.remove('user_name');
    await prefs.remove('user_email');
    await prefs.remove('user_address');
    await prefs.remove('user_city');
    await prefs.remove('user_latitude');
    await prefs.remove('user_longitude');
    
    _userPhone = null;
    _userName = null;
    _userEmail = null;
    _userAddress = null;
    _userCity = null;
    _userLatitude = null;
    _userLongitude = null;
    notifyListeners();
  }

  Future<void> _loadAuthAndCartFromPrefs() async {
    final prefs = await SharedPreferences.getInstance();
    _userPhone = prefs.getString('user_phone');
    _userName = prefs.getString('user_name');
    _userEmail = prefs.getString('user_email');
    _userAddress = prefs.getString('user_address');
    _userCity = prefs.getString('user_city');
    _userLatitude = prefs.getDouble('user_latitude');
    _userLongitude = prefs.getDouble('user_longitude');

    final savedCart = prefs.getString('swaad_cart_items');
    if (savedCart != null) {
      try {
        final List<dynamic> decoded = jsonDecode(savedCart);
        final menuItems = await ApiService.fetchMenuItems();
        
        _items.clear();
        for (var itemJson in decoded) {
          _items.add(CartItem.fromJson(itemJson as Map<String, dynamic>, menuItems));
        }
      } catch (e) {
        debugPrint("Failed to load cart items from preferences: $e");
      }
    }
    notifyListeners();
  }

  Future<void> _saveCartToPrefs() async {
    final prefs = await SharedPreferences.getInstance();
    final jsonList = _items.map((item) => item.toJson()).toList();
    await prefs.setString('swaad_cart_items', jsonEncode(jsonList));
  }
}
