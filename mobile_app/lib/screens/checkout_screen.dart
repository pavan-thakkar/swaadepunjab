import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:geolocator/geolocator.dart';
import '../providers/cart_provider.dart';
import '../services/api_service.dart';
import 'order_tracking_screen.dart';
import '../models/order.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  final _formKey = GlobalKey<FormState>();

  String _customerName = '';
  String _customerEmail = '';
  String _customerPhone = '';
  String _deliveryAddress = '';
  String _city = 'Amritsar';
  String _apartmentNo = '';
  String _apartmentName = '';
  String _specialInstructions = '';
  String _pickupTime = '';

  String _orderType = 'delivery'; // delivery, dine_in, takeaway
  String _paymentMethod = 'cash_on_delivery'; // cash_on_delivery, card, razorpay
  bool _isPlacing = false;
  String _errorMessage = '';

  double? _latitude;
  double? _longitude;
  bool _fetchingLocation = false;

  @override
  void initState() {
    super.initState();
    final cart = context.read<CartProvider>();
    _customerPhone = cart.userPhone ?? '';
    _customerName = cart.userName ?? '';
    _customerEmail = cart.userEmail ?? '';
    _deliveryAddress = cart.userAddress ?? '';
    _city = cart.userCity ?? 'Amritsar';
    _latitude = cart.userLatitude;
    _longitude = cart.userLongitude;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_deliveryAddress.isNotEmpty) {
        _triggerFeeCalculation();
      }
    });
  }

  Future<void> _fetchGPSCoordinates() async {
    setState(() => _fetchingLocation = true);
    try {
      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }

      if (permission == LocationPermission.whileInUse || permission == LocationPermission.always) {
        Position position = await Geolocator.getCurrentPosition(
          desiredAccuracy: LocationAccuracy.high,
        );
        setState(() {
          _latitude = position.latitude;
          _longitude = position.longitude;
          _fetchingLocation = false;
        });

        // Trigger dynamic distance and fee lookup
        _triggerFeeCalculation();
      } else {
        setState(() {
          _errorMessage = 'Location permission denied. Using address matching.';
          _fetchingLocation = false;
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Failed to fetch GPS coordinates. Using address matching.';
        _fetchingLocation = false;
      });
    }
  }

  void _triggerFeeCalculation() {
    if (_orderType != 'delivery' || _deliveryAddress.isEmpty) return;

    final fullAddress = [
      if (_apartmentNo.isNotEmpty) 'Apt/Flat: $_apartmentNo',
      if (_apartmentName.isNotEmpty) 'Building/Landmark: $_apartmentName',
      _deliveryAddress
    ].join(', ');

    // Call Provider to fetch calculated distance and fee dynamically in background
    context.read<CartProvider>().calculateDeliveryFee(
          fullAddress,
          _city,
          _latitude,
          _longitude,
        );
  }

  Future<void> _submitOrder(CartProvider cart) async {
    if (!_formKey.currentState!.validate()) return;
    _formKey.currentState!.save();

    setState(() {
      _isPlacing = true;
      _errorMessage = '';
    });

    final fullAddress = _orderType == 'delivery'
        ? [
            if (_apartmentNo.isNotEmpty) 'Apt/Flat: $_apartmentNo',
            if (_apartmentName.isNotEmpty) 'Building/Landmark: $_apartmentName',
            _deliveryAddress
          ].join(', ')
        : (_orderType == 'dine_in' ? 'Dine In' : 'Take Away');

    final payload = {
      'customer_name': _customerName,
      'customer_email': _customerEmail,
      'customer_phone': _customerPhone,
      'delivery_address': fullAddress,
      'city': _orderType == 'delivery' ? _city : 'Amritsar',
      'payment_method': _paymentMethod,
      'order_type': _orderType,
      'latitude': _orderType == 'delivery' ? _latitude : null,
      'longitude': _orderType == 'delivery' ? _longitude : null,
      'special_instructions': _specialInstructions,
      'pickup_time': _orderType != 'delivery' ? _pickupTime : null,
      'table_number': null,
      'items': cart.items.map((i) => {
            'menu_item_id': i.menuItem.id,
            'quantity': i.quantity,
          }).toList(),
    };

    final result = await ApiService.placeOrder(payload);

    setState(() => _isPlacing = false);

    if (result['success'] == true) {
      final order = result['order'] as Order;
      
      // Save auth detail to prefs
      await cart.loginUser(_customerPhone, _customerName);
      
      // Clear cart
      cart.clearCart();
      cart.resetDistanceFee();

      if (mounted) {
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(
            builder: (context) => OrderTrackingScreen(orderNumber: order.orderNumber),
          ),
        );
      }
    } else {
      setState(() {
        _errorMessage = result['message'] ?? 'Failed to place order. Try again.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final activeDeliveryFee = _orderType == 'delivery' ? cart.customDeliveryFee : 0.0;
    final grandTotal = cart.totalSubtotal + activeDeliveryFee;

    return Scaffold(
      backgroundColor: const Color(0xFFFFFDF5),
      appBar: AppBar(
        backgroundColor: const Color(0xFFFFF8E7),
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFF1A0F00)),
        title: Text(
          'Checkout',
          style: GoogleFonts.outfit(color: const Color(0xFF1A0F00), fontWeight: FontWeight.bold),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Order Type Toggle
              Text(
                'How would you like your order?',
                style: GoogleFonts.outfit(color: const Color(0xFF2C1A00), fontSize: 14, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  _buildOrderTypeButton('delivery', '🛵 Delivery'),
                  const SizedBox(width: 8),
                  _buildOrderTypeButton('dine_in', '🍽️ Dine In'),
                  const SizedBox(width: 8),
                  _buildOrderTypeButton('takeaway', '🛍️ Takeaway'),
                ],
              ),
              const SizedBox(height: 24),

              // Contact Details Section
              _buildSectionHeader('👤 Contact Information'),
              const SizedBox(height: 12),
              _buildTextField(
                label: 'Your Name *',
                initialValue: _customerName,
                validator: (val) => val == null || val.isEmpty ? 'Required' : null,
                onSaved: (val) => _customerName = val ?? '',
              ),
              const SizedBox(height: 12),
              _buildTextField(
                label: 'Phone Number *',
                initialValue: _customerPhone,
                keyboardType: TextInputType.phone,
                validator: (val) => val == null || val.isEmpty ? 'Required' : null,
                onSaved: (val) => _customerPhone = val ?? '',
              ),
              const SizedBox(height: 12),
              _buildTextField(
                label: 'Email (Optional)',
                initialValue: _customerEmail,
                keyboardType: TextInputType.emailAddress,
                onSaved: (val) => _customerEmail = val ?? '',
              ),
              const SizedBox(height: 24),

              // Delivery Address section
              if (_orderType == 'delivery') ...[
                _buildSectionHeader('📍 Delivery Address'),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        _latitude != null 
                            ? 'GPS Coordinates captured successfully!'
                            : 'Set coordinate matching for precise distance calculation.',
                        style: TextStyle(
                          color: _latitude != null ? Colors.green : const Color(0xFF7A6040),
                          fontSize: 12,
                        ),
                      ),
                    ),
                    TextButton.icon(
                      onPressed: _fetchingLocation ? null : _fetchGPSCoordinates,
                      icon: _fetchingLocation 
                          ? const SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFE6A817)))
                          : const Icon(Icons.my_location, size: 16, color: Color(0xFFE6A817)),
                      label: Text(
                        _latitude != null ? 'Recapture' : 'Locate Me',
                        style: const TextStyle(color: Color(0xFFE6A817), fontSize: 12),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                _buildTextField(
                  label: 'Street Address / Location *',
                  validator: (val) => val == null || val.isEmpty ? 'Required' : null,
                  onChanged: (val) {
                    _deliveryAddress = val;
                    _triggerFeeCalculation();
                  },
                  onSaved: (val) => _deliveryAddress = val ?? '',
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _buildTextField(
                        label: 'Flat / Apt No',
                        onChanged: (val) {
                          _apartmentNo = val;
                          _triggerFeeCalculation();
                        },
                        onSaved: (val) => _apartmentNo = val ?? '',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _buildTextField(
                        label: 'Building / Landmark',
                        onChanged: (val) {
                          _apartmentName = val;
                          _triggerFeeCalculation();
                        },
                        onSaved: (val) => _apartmentName = val ?? '',
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _buildTextField(
                  label: 'City',
                  initialValue: _city,
                  onChanged: (val) {
                    _city = val;
                    _triggerFeeCalculation();
                  },
                  onSaved: (val) => _city = val ?? 'Amritsar',
                ),
                const SizedBox(height: 24),
              ],

              // Takeaway / Dine In Details
              if (_orderType == 'dine_in') ...[
                _buildSectionHeader('🍽️ Dine In Details'),
                const SizedBox(height: 12),
                _buildTextField(
                  label: 'How many minutes will you take to arrive?',
                  onSaved: (val) => _pickupTime = val ?? '',
                ),
                const SizedBox(height: 24),
              ] else if (_orderType == 'takeaway') ...[
                _buildSectionHeader('🛍️ Take Away Details'),
                const SizedBox(height: 12),
                _buildTextField(
                  label: 'Estimated Collection Time',
                  onSaved: (val) => _pickupTime = val ?? '',
                ),
                const SizedBox(height: 24),
              ],

              // Payment Selection
              _buildSectionHeader('💳 Payment Method'),
              const SizedBox(height: 12),
              _buildPaymentTile('cash_on_delivery', '💵 Cash on Delivery / Pay at Counter'),
              _buildPaymentTile('razorpay', '💳 Pay Online (Razorpay Cards/UPI)'),
              const SizedBox(height: 24),

              // Order Summary display
              _buildSectionHeader('🧾 Order Summary'),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFFFFF),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFEDE0C4)),
                ),
                child: Column(
                  children: [
                    _buildSummaryRow('Subtotal', '₹${cart.totalSubtotal.toStringAsFixed(0)}'),
                    if (_orderType == 'delivery' && cart.distanceKm != null) ...[
                      const Divider(color: Color(0xFFEDE0C4)),
                      _buildSummaryRow(
                        'Calculated Distance', 
                        '${cart.distanceKm!.toStringAsFixed(1)} KM',
                        valueColor: const Color(0xFFFF6B00)
                      ),
                    ],
                    if (_orderType == 'delivery') ...[
                      const Divider(color: Color(0xFFEDE0C4)),
                      _buildSummaryRow('Delivery Charge', '₹${activeDeliveryFee.toStringAsFixed(0)}'),
                    ],
                    const Divider(color: Color(0xFFEDE0C4), thickness: 1.2),
                    _buildSummaryRow(
                      'Total Amount', 
                      '₹${grandTotal.toStringAsFixed(0)}',
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      valueColor: const Color(0xFFFF6B00)
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Error messages
              if (_errorMessage.isNotEmpty || cart.distanceError.isNotEmpty) ...[
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.withOpacity(0.1),
                    border: Border.all(color: Colors.red.withOpacity(0.3)),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '⚠️ ${_errorMessage.isNotEmpty ? _errorMessage : cart.distanceError}',
                    style: const TextStyle(color: Colors.redAccent, fontSize: 13),
                  ),
                ),
                const SizedBox(height: 24),
              ],

              // Submit Button
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isPlacing || cart.distanceError.isNotEmpty ? null : () => _submitOrder(cart),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFE6A817),
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: _isPlacing
                      ? const CircularProgressIndicator(color: Colors.white)
                      : Text(
                          'Place Order • ₹${grandTotal.toStringAsFixed(0)}',
                          style: GoogleFonts.outfit(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            fontSize: 16,
                          ),
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildOrderTypeButton(String type, String label) {
    final isSelected = _orderType == type;
    return Expanded(
      child: OutlinedButton(
        onPressed: () {
          setState(() {
            _orderType = type;
            _errorMessage = '';
          });
          _triggerFeeCalculation();
        },
        style: OutlinedButton.styleFrom(
          backgroundColor: isSelected ? const Color(0xFFE6A817) : const Color(0xFFFFF8E7),
          side: BorderSide(color: isSelected ? const Color(0xFFE6A817) : const Color(0xFFEDE0C4)),
          padding: const EdgeInsets.symmetric(vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
        child: Text(
          label,
          style: GoogleFonts.outfit(
            color: isSelected ? Colors.white : const Color(0xFF2C1A00),
            fontWeight: FontWeight.bold,
            fontSize: 12,
          ),
        ),
      ),
    );
  }

  Widget _buildPaymentTile(String method, String label) {
    final isSelected = _paymentMethod == method;
    return Card(
      color: const Color(0xFFFFFFFF),
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: BorderSide(color: isSelected ? const Color(0xFFE6A817) : const Color(0xFFEDE0C4)),
      ),
      child: RadioListTile<String>(
        value: method,
        groupValue: _paymentMethod,
        title: Text(label, style: GoogleFonts.outfit(color: const Color(0xFF2C1A00), fontSize: 14)),
        activeColor: const Color(0xFFE6A817),
        onChanged: (val) {
          setState(() => _paymentMethod = val!);
        },
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Text(
      title,
      style: GoogleFonts.outfit(
        color: const Color(0xFF1A0F00),
        fontSize: 16,
        fontWeight: FontWeight.bold,
      ),
    );
  }

  Widget _buildTextField({
    required String label,
    String? initialValue,
    TextInputType? keyboardType,
    FormFieldValidator<String>? validator,
    FormFieldSetter<String>? onSaved,
    ValueChanged<String>? onChanged,
  }) {
    return TextFormField(
      initialValue: initialValue,
      keyboardType: keyboardType,
      validator: validator,
      onSaved: onSaved,
      onChanged: onChanged,
      style: const TextStyle(color: Color(0xFF1A0F00)),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(color: Color(0xFF7A6040), fontSize: 13),
        filled: true,
        fillColor: const Color(0xFFFFF8E7),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: Color(0xFFEDE0C4)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: Color(0xFFE6A817)),
        ),
      ),
    );
  }

  Widget _buildSummaryRow(String label, String value, {
    double fontSize = 14,
    FontWeight fontWeight = FontWeight.normal,
    Color valueColor = const Color(0xFF2C1A00),
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4.0),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: GoogleFonts.outfit(color: const Color(0xFF7A6040), fontSize: fontSize)),
          Text(
            value,
            style: GoogleFonts.outfit(
              color: valueColor,
              fontSize: fontSize,
              fontWeight: fontWeight,
            ),
          ),
        ],
      ),
    );
  }
}
