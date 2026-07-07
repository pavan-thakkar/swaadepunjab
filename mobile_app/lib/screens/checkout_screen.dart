import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:google_fonts/google_fonts.dart';
import '../providers/cart_provider.dart';
import '../services/api_service.dart';
import 'order_tracking_screen.dart';
import 'location_picker_screen.dart';
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
  String _city = 'Gandhinagar';
  String _apartmentNo = '';
  String _apartmentName = '';
  String _specialInstructions = '';
  String _pickupTime = '';

  String _orderType = 'delivery';
  String _paymentMethod = 'cash_on_delivery';
  bool _isPlacing = false;
  String _errorMessage = '';

  // location mode: 'my_location' or 'someone_else'
  String _locationMode = 'my_location';

  // Controllers for address fields so we can programmatically set them
  final TextEditingController _addressController = TextEditingController();
  final TextEditingController _cityController = TextEditingController();

  @override
  void initState() {
    super.initState();
    final cart = context.read<CartProvider>();
    _customerPhone = cart.userPhone ?? '';
    _customerName = cart.userName ?? '';
    _customerEmail = cart.userEmail ?? '';

    // If someone else mode was set from location picker, honour it
    if (cart.isForSomeoneElse) {
      _locationMode = 'someone_else';
      _deliveryAddress = cart.deliveryAddress ?? '';
      _city = cart.deliveryCity ?? 'Gandhinagar';
    } else {
      _locationMode = 'my_location';
      _deliveryAddress = cart.userAddress ?? '';
      _city = cart.userCity ?? 'Gandhinagar';
    }

    _addressController.text = _deliveryAddress;
    _cityController.text = _city;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _triggerFeeCalculation();
    });
  }

  @override
  void dispose() {
    _addressController.dispose();
    _cityController.dispose();
    super.dispose();
  }

  void _switchLocationMode(String mode) {
    final cart = context.read<CartProvider>();
    setState(() {
      _locationMode = mode;
      if (mode == 'my_location') {
        _deliveryAddress = cart.userAddress ?? '';
        _city = cart.userCity ?? 'Gandhinagar';
        cart.clearSomeoneElseDelivery();
      } else {
        _deliveryAddress = cart.deliveryAddress ?? cart.userAddress ?? '';
        _city = cart.deliveryCity ?? cart.userCity ?? 'Gandhinagar';
      }
      _addressController.text = _deliveryAddress;
      _cityController.text = _city;
    });
    _triggerFeeCalculation();
  }

  void _triggerFeeCalculation() {
    if (_orderType != 'delivery' || _deliveryAddress.isEmpty) return;
    final cart = context.read<CartProvider>();

    double? lat;
    double? lng;
    if (_locationMode == 'my_location') {
      lat = cart.userLatitude;
      lng = cart.userLongitude;
    } else {
      lat = cart.deliveryLatitude;
      lng = cart.deliveryLongitude;
    }

    final fullAddress = [
      if (_apartmentNo.isNotEmpty) 'Apt/Flat: $_apartmentNo',
      if (_apartmentName.isNotEmpty) 'Building: $_apartmentName',
      _deliveryAddress,
    ].join(', ');

    cart.calculateDeliveryFee(fullAddress, _city, lat, lng);
  }

  Future<void> _submitOrder(CartProvider cart) async {
    if (!_formKey.currentState!.validate()) return;
    _formKey.currentState!.save();

    setState(() {
      _isPlacing = true;
      _errorMessage = '';
    });

    double? lat;
    double? lng;
    if (_locationMode == 'my_location') {
      lat = cart.userLatitude;
      lng = cart.userLongitude;
    } else {
      lat = cart.deliveryLatitude;
      lng = cart.deliveryLongitude;
    }

    final fullAddress = _orderType == 'delivery'
        ? [
            if (_apartmentNo.isNotEmpty) 'Apt/Flat: $_apartmentNo',
            if (_apartmentName.isNotEmpty) 'Building: $_apartmentName',
            _deliveryAddress,
          ].join(', ')
        : (_orderType == 'dine_in' ? 'Dine In' : 'Take Away');

    final payload = {
      'customer_name': _customerName,
      'customer_email': _customerEmail,
      'customer_phone': _customerPhone,
      'delivery_address': fullAddress,
      'city': _orderType == 'delivery' ? _city : 'Gandhinagar',
      'payment_method': _paymentMethod,
      'order_type': _orderType,
      'latitude': _orderType == 'delivery' ? lat : null,
      'longitude': _orderType == 'delivery' ? lng : null,
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
      await cart.loginUser(_customerPhone, _customerName);
      cart.clearCart();
      cart.resetDistanceFee();
      cart.clearSomeoneElseDelivery();

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

              // Delivery Address Section
              if (_orderType == 'delivery') ...[
                _buildSectionHeader('📍 Delivery Address'),
                const SizedBox(height: 12),

                // Location Mode Selector (My Location vs Someone Else)
                _buildLocationModeSelector(cart),
                const SizedBox(height: 12),

                // Change Location Button
                OutlinedButton.icon(
                  onPressed: () async {
                    await Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => LocationPickerScreen(
                          isForSomeoneElse: _locationMode == 'someone_else',
                        ),
                      ),
                    );
                    // Refresh address from provider after picker
                    if (mounted) {
                      final c = context.read<CartProvider>();
                      setState(() {
                        if (_locationMode == 'someone_else' && c.isForSomeoneElse) {
                          _deliveryAddress = c.deliveryAddress ?? '';
                          _city = c.deliveryCity ?? 'Gandhinagar';
                        } else {
                          _deliveryAddress = c.userAddress ?? '';
                          _city = c.userCity ?? 'Gandhinagar';
                        }
                        _addressController.text = _deliveryAddress;
                        _cityController.text = _city;
                      });
                      _triggerFeeCalculation();
                    }
                  },
                  icon: const Icon(Icons.edit_location_alt, color: Color(0xFFE6A817), size: 18),
                  label: Text(
                    'Change Location',
                    style: GoogleFonts.outfit(color: const Color(0xFFE6A817), fontWeight: FontWeight.w600),
                  ),
                  style: OutlinedButton.styleFrom(
                    side: const BorderSide(color: Color(0xFFE6A817)),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                ),
                const SizedBox(height: 12),

                // Address text field (editable)
                TextFormField(
                  controller: _addressController,
                  style: const TextStyle(color: Color(0xFF1A0F00)),
                  validator: (val) => val == null || val.isEmpty ? 'Required' : null,
                  onChanged: (val) {
                    _deliveryAddress = val;
                    _triggerFeeCalculation();
                  },
                  onSaved: (val) => _deliveryAddress = val ?? '',
                  decoration: _inputDecoration('Street Address / Location *'),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: _buildTextField(
                        label: 'Flat / Apt No',
                        onChanged: (val) { _apartmentNo = val; _triggerFeeCalculation(); },
                        onSaved: (val) => _apartmentNo = val ?? '',
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _buildTextField(
                        label: 'Building / Landmark',
                        onChanged: (val) { _apartmentName = val; _triggerFeeCalculation(); },
                        onSaved: (val) => _apartmentName = val ?? '',
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                TextFormField(
                  controller: _cityController,
                  style: const TextStyle(color: Color(0xFF1A0F00)),
                  onChanged: (val) { _city = val; _triggerFeeCalculation(); },
                  onSaved: (val) => _city = val ?? 'Gandhinagar',
                  decoration: _inputDecoration('City'),
                ),
                const SizedBox(height: 12),
                _buildTextField(
                  label: 'Special Instructions (Optional)',
                  onSaved: (val) => _specialInstructions = val ?? '',
                ),
                const SizedBox(height: 24),
              ],

              // Dine In / Takeaway time fields
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
              _buildPaymentTile('razorpay', '💳 Pay Online (Razorpay / UPI)'),
              const SizedBox(height: 24),

              // Order Summary
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
                        'Distance',
                        '${cart.distanceKm!.toStringAsFixed(1)} km',
                        valueColor: const Color(0xFFFF6B00),
                      ),
                    ],
                    if (_orderType == 'delivery') ...[
                      const Divider(color: Color(0xFFEDE0C4)),
                      _buildSummaryRow(
                        'Delivery Charge',
                        cart.isCalculatingFee
                            ? 'Calculating...'
                            : (activeDeliveryFee == 0 ? 'FREE' : '₹${activeDeliveryFee.toStringAsFixed(0)}'),
                        valueColor: activeDeliveryFee == 0 && !cart.isCalculatingFee
                            ? const Color(0xFF2E7D32)
                            : const Color(0xFF1A0F00),
                      ),
                    ],
                    const Divider(color: Color(0xFFEDE0C4), thickness: 1.2),
                    _buildSummaryRow(
                      'Total Amount',
                      '₹${grandTotal.toStringAsFixed(0)}',
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      valueColor: const Color(0xFFFF6B00),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),

              // Error message (only show actual errors, NOT distance errors)
              if (_errorMessage.isNotEmpty) ...[
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.withOpacity(0.1),
                    border: Border.all(color: Colors.red.withOpacity(0.3)),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '⚠️ $_errorMessage',
                    style: const TextStyle(color: Colors.redAccent, fontSize: 13),
                  ),
                ),
                const SizedBox(height: 24),
              ],

              // Place Order Button — NOT blocked by distance error
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _isPlacing ? null : () => _submitOrder(cart),
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
              const SizedBox(height: 32),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildLocationModeSelector(CartProvider cart) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFEDE0C4)),
      ),
      child: Column(
        children: [
          // My Location option
          _buildLocationOption(
            mode: 'my_location',
            icon: Icons.my_location,
            title: 'My Location',
            subtitle: cart.userAddress?.isNotEmpty == true
                ? '${cart.userAddress}, ${cart.userCity ?? ''}'
                : 'Tap "Change Location" to set your address',
            color: const Color(0xFFE6A817),
          ),
          const Divider(height: 1, color: Color(0xFFEDE0C4)),
          // Someone else option
          _buildLocationOption(
            mode: 'someone_else',
            icon: Icons.people_alt_outlined,
            title: 'Order for Someone Else',
            subtitle: cart.isForSomeoneElse && cart.deliveryAddress?.isNotEmpty == true
                ? '${cart.deliveryAddress}, ${cart.deliveryCity ?? ''}'
                : 'Tap "Change Location" to set their address',
            color: const Color(0xFF4CAF50),
          ),
        ],
      ),
    );
  }

  Widget _buildLocationOption({
    required String mode,
    required IconData icon,
    required String title,
    required String subtitle,
    required Color color,
  }) {
    final isSelected = _locationMode == mode;
    return InkWell(
      onTap: () => _switchLocationMode(mode),
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: isSelected ? color.withOpacity(0.12) : const Color(0xFFF5F5F5),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(icon, color: isSelected ? color : const Color(0xFF9A7A50), size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: GoogleFonts.outfit(
                      fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                      fontSize: 14,
                      color: isSelected ? const Color(0xFF1A0F00) : const Color(0xFF7A6040),
                    ),
                  ),
                  Text(
                    subtitle,
                    style: GoogleFonts.outfit(fontSize: 11, color: const Color(0xFF9A7A50)),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            Radio<String>(
              value: mode,
              groupValue: _locationMode,
              onChanged: (v) => _switchLocationMode(v!),
              activeColor: color,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOrderTypeButton(String type, String label) {
    final isSelected = _orderType == type;
    return Expanded(
      child: OutlinedButton(
        onPressed: () {
          setState(() { _orderType = type; _errorMessage = ''; });
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
        onChanged: (val) => setState(() => _paymentMethod = val!),
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Text(
      title,
      style: GoogleFonts.outfit(color: const Color(0xFF1A0F00), fontSize: 16, fontWeight: FontWeight.bold),
    );
  }

  InputDecoration _inputDecoration(String label) {
    return InputDecoration(
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
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Colors.red),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: Colors.red),
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
      decoration: _inputDecoration(label),
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
          Text(value, style: GoogleFonts.outfit(color: valueColor, fontSize: fontSize, fontWeight: fontWeight)),
        ],
      ),
    );
  }
}
