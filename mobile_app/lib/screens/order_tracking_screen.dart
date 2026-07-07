import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../models/order.dart';
import '../services/api_service.dart';
import 'home_screen.dart';

class OrderTrackingScreen extends StatefulWidget {
  final String orderNumber;

  const OrderTrackingScreen({super.key, required this.orderNumber});

  @override
  State<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends State<OrderTrackingScreen> {
  Order? _order;
  bool _isLoading = true;
  String _errorMessage = '';
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _fetchOrder();
    // Poll order status every 10 seconds
    _timer = Timer.periodic(const Duration(seconds: 10), (_) => _fetchOrder());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  Future<void> _fetchOrder() async {
    try {
      final order = await ApiService.fetchOrderDetails(widget.orderNumber);
      if (mounted) {
        setState(() {
          _order = order;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = 'Failed to load status updates: $e';
          _isLoading = false;
        });
      }
    }
  }

  int _getStatusStep(String status) {
    switch (status.toLowerCase()) {
      case 'pending':
        return 0;
      case 'accepted':
      case 'preparing':
        return 1;
      case 'out_for_delivery':
        return 2;
      case 'delivered':
        return 3;
      default:
        return 0;
    }
  }

  @override
  Widget build(BuildContext context) {
    final currentStep = _order != null ? _getStatusStep(_order!.status) : 0;

    return Scaffold(
      backgroundColor: const Color(0xFFFFFDF5),
      appBar: AppBar(
        backgroundColor: const Color(0xFFFFF8E7),
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFF1A0F00)),
        title: Text(
          'Track Order #${widget.orderNumber}',
          style: GoogleFonts.outfit(color: const Color(0xFF1A0F00), fontWeight: FontWeight.bold, fontSize: 16),
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pushAndRemoveUntil(
                context,
                MaterialPageRoute(builder: (context) => const HomeScreen()),
                (route) => false,
              );
            },
            child: const Text('Home', style: TextStyle(color: Color(0xFFE6A817), fontWeight: FontWeight.bold)),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFFE6A817)))
          : _order == null
              ? Center(child: Text(_errorMessage, style: const TextStyle(color: Color(0xFF7A6040))))
              : Padding(
                  padding: const EdgeInsets.all(24.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Center(
                        child: Column(
                          children: [
                            const Text('🍔', style: TextStyle(fontSize: 60)),
                            const SizedBox(height: 12),
                            Text(
                              _order!.status.toUpperCase().replaceAll('_', ' '),
                              style: GoogleFonts.outfit(
                                color: const Color(0xFFE6A817),
                                fontSize: 20,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 1.1,
                              ),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Updates automatically every 10s',
                              style: TextStyle(color: Color(0xFFB89A70), fontSize: 11),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 40),

                      // Visual Lifecycle Tracker Steps
                      _buildStepTile(
                        stepIndex: 0,
                        currentStep: currentStep,
                        title: 'Order Placed',
                        subtitle: 'Waiting for restaurant confirmation',
                        icon: Icons.assignment_outlined,
                      ),
                      _buildStepDivider(0, currentStep),
                      _buildStepTile(
                        stepIndex: 1,
                        currentStep: currentStep,
                        title: 'Preparing Food',
                        subtitle: 'Our chef is preparing your meal',
                        icon: Icons.restaurant_menu_outlined,
                      ),
                      _buildStepDivider(1, currentStep),
                      _buildStepTile(
                        stepIndex: 2,
                        currentStep: currentStep,
                        title: 'Out for Delivery',
                        subtitle: 'Rider is on the way to your location',
                        icon: Icons.delivery_dining_outlined,
                      ),
                      _buildStepDivider(2, currentStep),
                      _buildStepTile(
                        stepIndex: 3,
                        currentStep: currentStep,
                        title: 'Delivered',
                        subtitle: 'Enjoy your tasty Swaad E Punjab meal!',
                        icon: Icons.check_circle_outline,
                      ),
                      const SizedBox(height: 32),
                      Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF8E7),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFEDE0C4)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Bill Details',
                              style: GoogleFonts.outfit(color: const Color(0xFF1A0F00), fontSize: 16, fontWeight: FontWeight.bold),
                            ),
                            const SizedBox(height: 12),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Subtotal', style: TextStyle(color: Color(0xFF7A6040))),
                                Text('₹${_order!.subtotal.toStringAsFixed(0)}', style: const TextStyle(color: Color(0xFF1A0F00), fontWeight: FontWeight.w600)),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                const Text('Delivery Charge', style: TextStyle(color: Color(0xFF7A6040))),
                                Text(
                                  _order!.deliveryFee > 0 ? '₹${_order!.deliveryFee.toStringAsFixed(0)}' : 'FREE',
                                  style: TextStyle(color: _order!.deliveryFee > 0 ? const Color(0xFF1A0F00) : Colors.green, fontWeight: FontWeight.w600),
                                ),
                              ],
                            ),
                            const Divider(color: Color(0xFFEDE0C4), height: 20),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text('Total Amount', style: GoogleFonts.outfit(color: const Color(0xFF1A0F00), fontSize: 16, fontWeight: FontWeight.bold)),
                                Text('₹${_order!.grandTotal.toStringAsFixed(0)}', style: GoogleFonts.outfit(color: const Color(0xFFFF6B00), fontSize: 18, fontWeight: FontWeight.bold)),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }

  Widget _buildStepTile({
    required int stepIndex,
    required int currentStep,
    required String title,
    required String subtitle,
    required IconData icon,
  }) {
    final isDone = currentStep >= stepIndex;
    final isCurrent = currentStep == stepIndex;
    final color = isDone
        ? const Color(0xFFE6A817)
        : (isCurrent ? const Color(0xFF1A0F00) : const Color(0xFFB89A70));

    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: isDone ? const Color(0xFFE6A817).withOpacity(0.15) : const Color(0xFFFFF8E7),
            shape: BoxShape.circle,
            border: Border.all(
              color: isCurrent ? const Color(0xFFE6A817) : Colors.transparent,
              width: 2,
            ),
          ),
          child: Icon(icon, color: color, size: 24),
        ),
        const SizedBox(width: 16),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: GoogleFonts.outfit(
                  color: isDone ? const Color(0xFF1A0F00) : const Color(0xFFB89A70),
                  fontWeight: FontWeight.bold,
                  fontSize: 15,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: GoogleFonts.outfit(
                  color: isDone ? const Color(0xFF7A6040) : const Color(0xFFB89A70),
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildStepDivider(int stepIndex, int currentStep) {
    final isDone = currentStep > stepIndex;
    return Container(
      margin: const EdgeInsets.only(left: 22),
      width: 2,
      height: 30,
      color: isDone ? const Color(0xFFE6A817) : const Color(0xFFEDE0C4),
    );
  }
}
