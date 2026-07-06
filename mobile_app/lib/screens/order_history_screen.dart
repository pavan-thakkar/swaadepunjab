import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:google_fonts/google_fonts.dart';
import '../models/order.dart';
import '../providers/cart_provider.dart';
import '../services/api_service.dart';
import 'order_tracking_screen.dart';

class OrderHistoryScreen extends StatefulWidget {
  const OrderHistoryScreen({super.key});

  @override
  State<OrderHistoryScreen> createState() => _OrderHistoryScreenState();
}

class _OrderHistoryScreenState extends State<OrderHistoryScreen> {
  List<Order> _orders = [];
  bool _isLoading = true;
  String _errorMessage = '';

  @override
  void initState() {
    super.initState();
    _fetchHistory();
  }

  Future<void> _fetchHistory() async {
    final cart = context.read<CartProvider>();
    if (cart.userPhone == null) {
      setState(() {
        _errorMessage = 'Login to view your order history.';
        _isLoading = false;
      });
      return;
    }

    try {
      final orders = await ApiService.fetchOrderHistory(cart.userPhone!);
      setState(() {
        _orders = orders;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _errorMessage = e.toString();
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFFFDF5),
      appBar: AppBar(
        backgroundColor: const Color(0xFFFFF8E7),
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFF1A0F00)),
        title: Text(
          'My Orders',
          style: GoogleFonts.outfit(color: const Color(0xFF1A0F00), fontWeight: FontWeight.bold),
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFFE6A817)))
          : _errorMessage.isNotEmpty
              ? Center(child: Text(_errorMessage, style: const TextStyle(color: Color(0xFF7A6040))))
              : _orders.isEmpty
                  ? const Center(
                      child: Text(
                        'No orders placed yet.',
                        style: TextStyle(color: Color(0xFFB89A70)),
                      ),
                    )
                  : ListView.builder(
                      itemCount: _orders.length,
                      padding: const EdgeInsets.all(16),
                      itemBuilder: (context, index) {
                        final order = _orders[index];
                        return _buildOrderCard(context, order);
                      },
                    ),
    );
  }

  Widget _buildOrderCard(BuildContext context, Order order) {
    return Card(
      color: const Color(0xFFFFFFFF),
      margin: const EdgeInsets.only(bottom: 12),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
        side: const BorderSide(color: Color(0xFFEDE0C4)),
      ),
      child: ListTile(
        contentPadding: const EdgeInsets.all(16),
        title: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Expanded(
              child: Text(
                'Order #${order.orderNumber}',
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.outfit(color: const Color(0xFF1A0F00), fontWeight: FontWeight.bold),
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: order.status == 'delivered' 
                    ? Colors.green.withOpacity(0.15) 
                    : const Color(0xFFE6A817).withOpacity(0.15),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                order.status.toUpperCase().replaceAll('_', ' '),
                style: GoogleFonts.outfit(
                  color: order.status == 'delivered' ? Colors.green : const Color(0xFFE6A817),
                  fontSize: 10,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ],
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 8),
            Text(
              'Subtotal: ₹${order.subtotal.toStringAsFixed(0)} • Total: ₹${order.grandTotal.toStringAsFixed(0)}',
              style: const TextStyle(color: Color(0xFF7A6040), fontSize: 13),
            ),
            const SizedBox(height: 4),
            Text(
              'Placed on: ${order.createdAt.split('T')[0]}',
              style: const TextStyle(color: Color(0xFFB89A70), fontSize: 11),
            ),
          ],
        ),
        trailing: const Icon(Icons.arrow_forward_ios, color: Color(0xFFB89A70), size: 16),
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => OrderTrackingScreen(orderNumber: order.orderNumber),
            ),
          );
        },
      ),
    );
  }
}
