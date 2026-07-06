import 'dart:convert';
import 'package:http/http.dart' as http;
import '../models/menu_item.dart';
import '../models/category.dart';
import '../models/order.dart';

class ApiService {
  static String get baseUrl {
    return 'https://orange-eyes-trade.loca.lt/api';
  }

  static Map<String, String> get _headers => {
    'Bypass-Tunnel-Reminder': 'true',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  };

  static Future<List<MenuItem>> fetchMenuItems() async {
    try {
      final response = await http.get(Uri.parse('$baseUrl/menu'), headers: _headers);
      if (response.statusCode == 200) {
        final Map<String, dynamic> body = jsonDecode(response.body);
        final List<dynamic> data = body['data'] ?? [];
        return data.map((json) => MenuItem.fromJson(json)).toList();
      } else {
        throw Exception('Failed to load menu items');
      }
    } catch (e) {
      throw Exception('Network error: $e');
    }
  }

  static Future<List<Category>> fetchCategories() async {
    try {
      final response = await http.get(Uri.parse('$baseUrl/categories'), headers: _headers);
      if (response.statusCode == 200) {
        final Map<String, dynamic> body = jsonDecode(response.body);
        final List<dynamic> data = body['data'] ?? [];
        return data.map((json) => Category.fromJson(json)).toList();
      } else {
        throw Exception('Failed to load categories');
      }
    } catch (e) {
      throw Exception('Network error: $e');
    }
  }

  static Future<Map<String, dynamic>> calculateDeliveryFee({
    required String address,
    required String city,
    required double subtotal,
    double? latitude,
    double? longitude,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/calculate-delivery-fee'),
        headers: _headers,
        body: jsonEncode({
          'delivery_address': address,
          'city': city,
          'subtotal': subtotal,
          'latitude': latitude,
          'longitude': longitude,
        }),
      );

      if (response.statusCode == 200) {
        return jsonDecode(response.body) as Map<String, dynamic>;
      } else {
        final errorBody = jsonDecode(response.body);
        return {
          'success': false,
          'fee': 0.0,
          'distance_km': null,
          'error': errorBody['message'] ?? 'Could not calculate fee'
        };
      }
    } catch (e) {
      return {
        'success': false,
        'fee': 0.0,
        'distance_km': null,
        'error': 'Network connection failed'
      };
    }
  }

  static Future<Map<String, dynamic>> placeOrder(Map<String, dynamic> orderPayload) async {
    try {
      final response = await http.post(
        Uri.parse('$baseUrl/orders'),
        headers: _headers,
        body: jsonEncode(orderPayload),
      );

      final decoded = jsonDecode(response.body);
      if (response.statusCode == 200 || response.statusCode == 201) {
        return {
          'success': true,
          'message': decoded['message'] ?? 'Order placed successfully',
          'order': Order.fromJson(decoded['data'])
        };
      } else {
        return {
          'success': false,
          'message': decoded['message'] ?? 'Failed to place order'
        };
      }
    } catch (e) {
      return {
        'success': false,
        'message': 'API Request Failed: $e'
      };
    }
  }

  static Future<List<Order>> fetchOrderHistory(String phone) async {
    try {
      final response = await http.get(Uri.parse('$baseUrl/orders/history?phone=$phone'), headers: _headers);
      if (response.statusCode == 200) {
        final Map<String, dynamic> body = jsonDecode(response.body);
        final List<dynamic> data = body['data'] ?? [];
        return data.map((json) => Order.fromJson(json)).toList();
      } else {
        throw Exception('Failed to load order history');
      }
    } catch (e) {
      throw Exception('Network error: $e');
    }
  }

  static Future<Order> fetchOrderDetails(String orderNumber) async {
    try {
      final response = await http.get(Uri.parse('$baseUrl/orders/$orderNumber'), headers: _headers);
      if (response.statusCode == 200) {
        final Map<String, dynamic> body = jsonDecode(response.body);
        return Order.fromJson(body['data']);
      } else {
        throw Exception('Failed to load order details');
      }
    } catch (e) {
      throw Exception('Network error: $e');
    }
  }
}
