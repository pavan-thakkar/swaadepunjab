import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../providers/cart_provider.dart';
import 'package:geolocator/geolocator.dart';

class ProfileDetailsScreen extends StatefulWidget {
  const ProfileDetailsScreen({super.key});

  @override
  State<ProfileDetailsScreen> createState() => _ProfileDetailsScreenState();
}

class _ProfileDetailsScreenState extends State<ProfileDetailsScreen> {
  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _addressController = TextEditingController();
  final _cityController = TextEditingController();
  bool _isFetchingGPS = false;

  @override
  void initState() {
    super.initState();
    final cart = context.read<CartProvider>();
    _nameController.text = cart.userName ?? '';
    _phoneController.text = cart.userPhone ?? '';
    _emailController.text = cart.userEmail ?? '';
    _addressController.text = cart.userAddress ?? '';
    _cityController.text = cart.userCity ?? 'Amritsar';
  }

  @override
  void dispose() {
    _nameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _addressController.dispose();
    _cityController.dispose();
    super.dispose();
  }

  Future<void> _fetchGPSLocation() async {
    setState(() => _isFetchingGPS = true);
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        throw 'Location services are disabled.';
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          throw 'Location permissions are denied';
        }
      }

      if (permission == LocationPermission.deniedForever) {
        throw 'Location permissions are permanently denied.';
      }

      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      final cart = context.read<CartProvider>();
      await cart.updateGPSLocation(position.latitude, position.longitude);
      setState(() {
        _addressController.text = cart.userAddress ?? '';
        _cityController.text = cart.userCity ?? 'Amritsar';
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('GPS coordinates and address loaded successfully!')),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e')),
      );
    } finally {
      setState(() => _isFetchingGPS = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFFFFFDF5),
      appBar: AppBar(
        backgroundColor: const Color(0xFFFFF8E7),
        elevation: 0.5,
        title: Text(
          'Profile Details',
          style: GoogleFonts.outfit(
            color: const Color(0xFFE6A817),
            fontWeight: FontWeight.bold,
          ),
        ),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Color(0xFFE6A817)),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          children: [
            Card(
              color: const Color(0xFFFFFFFF),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: const BorderSide(color: Color(0xFFEDE0C4)),
              ),
              child: Padding(
                padding: const EdgeInsets.all(16.0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    TextField(
                      controller: _nameController,
                      style: const TextStyle(color: Color(0xFF1A0F00)),
                      decoration: const InputDecoration(
                        labelText: 'Full Name',
                        labelStyle: TextStyle(color: Color(0xFF7A6040)),
                        enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFEDE0C4))),
                        focusedBorder: UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFE6A817))),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _phoneController,
                      keyboardType: TextInputType.phone,
                      style: const TextStyle(color: Color(0xFF1A0F00)),
                      decoration: const InputDecoration(
                        labelText: 'Phone Number',
                        labelStyle: TextStyle(color: Color(0xFF7A6040)),
                        enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFEDE0C4))),
                        focusedBorder: UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFE6A817))),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      style: const TextStyle(color: Color(0xFF1A0F00)),
                      decoration: const InputDecoration(
                        labelText: 'Email Address',
                        labelStyle: TextStyle(color: Color(0xFF7A6040)),
                        enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFEDE0C4))),
                        focusedBorder: UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFE6A817))),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _addressController,
                      style: const TextStyle(color: Color(0xFF1A0F00)),
                      decoration: const InputDecoration(
                        labelText: 'Delivery Address',
                        labelStyle: TextStyle(color: Color(0xFF7A6040)),
                        enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFEDE0C4))),
                        focusedBorder: UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFE6A817))),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _cityController,
                      style: const TextStyle(color: Color(0xFF1A0F00)),
                      decoration: const InputDecoration(
                        labelText: 'City',
                        labelStyle: TextStyle(color: Color(0xFF7A6040)),
                        enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFEDE0C4))),
                        focusedBorder: UnderlineInputBorder(borderSide: BorderSide(color: Color(0xFFE6A817))),
                      ),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        _isFetchingGPS
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFE6A817)),
                              )
                            : TextButton.icon(
                                onPressed: _fetchGPSLocation,
                                icon: const Icon(Icons.my_location, size: 14, color: Color(0xFFE6A817)),
                                label: Text(
                                  'Locate Me',
                                  style: GoogleFonts.outfit(
                                    color: const Color(0xFFE6A817),
                                    fontWeight: FontWeight.bold,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () async {
                  final name = _nameController.text.trim();
                  final phone = _phoneController.text.trim();
                  final email = _emailController.text.trim();
                  final address = _addressController.text.trim();
                  final city = _cityController.text.trim();

                  if (name.isEmpty || phone.isEmpty) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Name and phone are required')),
                    );
                    return;
                  }

                  await cart.saveProfile(
                    name: name,
                    phone: phone,
                    email: email,
                    address: address,
                    city: city,
                  );

                  if (cart.userLatitude != null && address.isNotEmpty) {
                    await cart.calculateDeliveryFee(
                      address,
                      city,
                      cart.userLatitude,
                      cart.userLongitude,
                    );
                  }

                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Profile updated successfully!')),
                  );
                  Navigator.pop(context);
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFE6A817),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: Text(
                  'Save & Update',
                  style: GoogleFonts.outfit(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                ),
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                onPressed: () async {
                  await cart.logoutUser();
                  Navigator.pop(context);
                },
                style: OutlinedButton.styleFrom(
                  side: const BorderSide(color: Colors.redAccent),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: const Text('Logout', style: TextStyle(color: Colors.redAccent, fontWeight: FontWeight.bold)),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
