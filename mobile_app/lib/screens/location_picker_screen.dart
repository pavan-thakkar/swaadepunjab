import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:geolocator/geolocator.dart';
import 'package:provider/provider.dart';
import 'package:http/http.dart' as http;
import '../providers/cart_provider.dart';

class LocationPickerScreen extends StatefulWidget {
  final bool isForSomeoneElse;
  const LocationPickerScreen({super.key, this.isForSomeoneElse = false});

  @override
  State<LocationPickerScreen> createState() => _LocationPickerScreenState();
}

class _LocationPickerScreenState extends State<LocationPickerScreen>
    with TickerProviderStateMixin {
  final TextEditingController _searchController = TextEditingController();
  List<Map<String, dynamic>> _searchResults = [];
  bool _isSearching = false;
  bool _isGpsLoading = false;
  bool _isSomeoneElse = false;
  String _gpsError = '';
  late AnimationController _animController;
  late Animation<double> _fadeAnim;

  // Restaurant location (Mapple 99 Food County, GIFT City, Gandhinagar)
  static const double _restaurantLat = 23.1629;
  static const double _restaurantLng = 72.6887;
  static const double _maxDeliveryKm = 15.0;

  // Use Nominatim (free, no API key needed)
  // Searches India-wide, prioritises Gujarat/Gandhinagar area
  static const String _nominatimBase = 'https://nominatim.openstreetmap.org';

  @override
  void initState() {
    super.initState();
    _isSomeoneElse = widget.isForSomeoneElse;
    _animController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _fadeAnim = CurvedAnimation(parent: _animController, curve: Curves.easeOut);
    _animController.forward();
  }

  @override
  void dispose() {
    _searchController.dispose();
    _animController.dispose();
    super.dispose();
  }

  double _calcDistance(double lat1, double lng1, double lat2, double lng2) {
    return Geolocator.distanceBetween(lat1, lng1, lat2, lng2) / 1000.0;
  }

  Future<void> _detectGpsLocation() async {
    setState(() {
      _isGpsLoading = true;
      _gpsError = '';
    });
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        setState(() {
          _gpsError = 'Location services are disabled. Please enable GPS.';
          _isGpsLoading = false;
        });
        return;
      }

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) {
          setState(() {
            _gpsError = 'Location permission denied.';
            _isGpsLoading = false;
          });
          return;
        }
      }
      if (permission == LocationPermission.deniedForever) {
        setState(() {
          _gpsError = 'Location permission permanently denied. Enable in Settings.';
          _isGpsLoading = false;
        });
        return;
      }

      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );

      // Reverse geocode using Nominatim
      String areaName = 'Current Location';
      String city = 'Gandhinagar';

      try {
        final url = Uri.parse(
            '$_nominatimBase/reverse?format=json&lat=${position.latitude}&lon=${position.longitude}&addressdetails=1');
        final response = await http.get(url, headers: {'User-Agent': 'swaad_e_punjab_app'});

        if (response.statusCode == 200) {
          final decoded = json.decode(response.body);
          if (decoded != null && decoded['address'] != null) {
            final addr = decoded['address'];
            final road = addr['road'] ?? addr['suburb'] ?? addr['neighbourhood'] ?? addr['village'] ?? '';
            final residential = addr['residential'] ?? addr['apartment'] ?? '';
            city = addr['city'] ?? addr['town'] ?? addr['county'] ?? 'Gandhinagar';
            areaName = '';
            if (residential.isNotEmpty) areaName += '$residential, ';
            if (road.isNotEmpty) areaName += road;
            if (areaName.trim().isEmpty) {
              final displayName = decoded['display_name'] ?? '';
              areaName = displayName.contains(',')
                  ? displayName.split(',').take(2).join(', ')
                  : displayName;
            }
            areaName = areaName.trim().replaceAll(RegExp(r',\s*$'), '');
          }
        }
      } catch (_) {}

      if (mounted) {
        final cart = context.read<CartProvider>();
        if (_isSomeoneElse) {
          await cart.setDeliveryForSomeoneElse(
            address: areaName,
            city: city,
            latitude: position.latitude,
            longitude: position.longitude,
          );
        } else {
          await cart.updateGPSLocation(position.latitude, position.longitude);
          await cart.calculateDeliveryFee(
            areaName, city, position.latitude, position.longitude,
          );
        }
        if (mounted) Navigator.pop(context, true);
      }
    } catch (e) {
      setState(() {
        _gpsError = 'Could not fetch location. Try again.';
        _isGpsLoading = false;
      });
    }
  }

  Future<void> _searchAddress(String query) async {
    if (query.trim().length < 3) {
      setState(() => _searchResults = []);
      return;
    }
    setState(() {
      _isSearching = true;
      _searchResults = [];
    });

    try {
      // Search India-wide, biased toward Gujarat (GIFT City area)
      final url = Uri.parse(
          '$_nominatimBase/search'
          '?q=${Uri.encodeComponent(query)}'
          '&format=json'
          '&addressdetails=1'
          '&limit=10'
          '&countrycodes=in'
          '&viewbox=72.0,22.5,73.5,23.8'
          '&bounded=0');

      final response = await http.get(url, headers: {
        'User-Agent': 'swaad_e_punjab_app',
        'Accept-Language': 'en',
      });

      if (response.statusCode == 200) {
        final List decoded = json.decode(response.body);

        // If no results from Gujarat-biased search, do a broader India search
        List finalResults = decoded;
        if (decoded.isEmpty) {
          final broadUrl = Uri.parse(
              '$_nominatimBase/search'
              '?q=${Uri.encodeComponent(query)}'
              '&format=json'
              '&addressdetails=1'
              '&limit=10'
              '&countrycodes=in');
          final broadResp = await http.get(broadUrl, headers: {
            'User-Agent': 'swaad_e_punjab_app',
            'Accept-Language': 'en',
          });
          if (broadResp.statusCode == 200) {
            finalResults = json.decode(broadResp.body);
          }
        }

        final results = finalResults.map<Map<String, dynamic>>((e) {
          final addr = e['address'] ?? {};
          final road = addr['road'] ?? addr['suburb'] ?? addr['neighbourhood'] ?? addr['village'] ?? '';
          final residential = addr['residential'] ?? addr['apartment'] ?? addr['amenity'] ?? '';
          final cityName = addr['city'] ?? addr['town'] ?? addr['county'] ?? addr['state_district'] ?? 'Gujarat';
          final state = addr['state'] ?? '';

          String displayName = '';
          if (residential.isNotEmpty) displayName += '$residential, ';
          if (road.isNotEmpty) displayName += '$road, ';
          displayName += cityName;
          if (state.isNotEmpty && state != cityName) displayName += ', $state';

          if (displayName.trim().isEmpty) {
            displayName = (e['display_name'] ?? '').split(',').take(3).join(', ');
          }

          return {
            'name': displayName.trim(),
            'lat': double.tryParse(e['lat'].toString()) ?? 0.0,
            'lng': double.tryParse(e['lon'].toString()) ?? 0.0,
            'city': cityName,
          };
        }).toList();

        setState(() {
          _searchResults = results;
          _isSearching = false;
        });
      } else {
        setState(() => _isSearching = false);
      }
    } catch (e) {
      setState(() => _isSearching = false);
    }
  }

  Future<void> _selectSearchResult(Map<String, dynamic> result) async {
    final double lat = result['lat'];
    final double lng = result['lng'];
    final String name = result['name'];
    final String city = result['city'];

    final cart = context.read<CartProvider>();

    if (_isSomeoneElse) {
      await cart.setDeliveryForSomeoneElse(
        address: name,
        city: city,
        latitude: lat,
        longitude: lng,
      );
    } else {
      await cart.updateGPSLocation(lat, lng);
      await cart.calculateDeliveryFee(name, city, lat, lng);
    }

    if (mounted) Navigator.pop(context, true);
  }

  void _useManualEntry() {
    // Allow user to just type an address manually without search
    final text = _searchController.text.trim();
    if (text.isEmpty) return;

    final cart = context.read<CartProvider>();

    if (_isSomeoneElse) {
      cart.setDeliveryForSomeoneElse(
        address: text,
        city: 'Gandhinagar',
        latitude: _restaurantLat,
        longitude: _restaurantLng,
      );
    } else {
      cart.setManualAddress(address: text, city: 'Gandhinagar');
    }

    if (mounted) Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFFFFDF5),
      appBar: AppBar(
        backgroundColor: const Color(0xFFFFF8E7),
        elevation: 0.5,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new, color: Color(0xFF1A0F00), size: 20),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Select Delivery Location',
          style: GoogleFonts.outfit(
            color: const Color(0xFF1A0F00),
            fontWeight: FontWeight.w800,
            fontSize: 17,
          ),
        ),
      ),
      body: FadeTransition(
        opacity: _fadeAnim,
        child: Column(
          children: [
            // Search Bar
            Container(
              color: const Color(0xFFFFF8E7),
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: TextField(
                controller: _searchController,
                autofocus: false,
                style: GoogleFonts.outfit(color: const Color(0xFF1A0F00)),
                textInputAction: TextInputAction.search,
                decoration: InputDecoration(
                  hintText: 'Search area, street, landmark...',
                  hintStyle: GoogleFonts.outfit(color: const Color(0xFF9A7A50), fontSize: 14),
                  prefixIcon: const Icon(Icons.search, color: Color(0xFFE6A817)),
                  suffixIcon: _searchController.text.isNotEmpty
                      ? Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            if (_searchResults.isEmpty && _searchController.text.length >= 3)
                              TextButton(
                                onPressed: _useManualEntry,
                                child: Text(
                                  'Use this',
                                  style: GoogleFonts.outfit(
                                    color: const Color(0xFFE6A817),
                                    fontSize: 12,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                            IconButton(
                              icon: const Icon(Icons.close, color: Color(0xFF9A7A50)),
                              onPressed: () {
                                _searchController.clear();
                                setState(() => _searchResults = []);
                              },
                            ),
                          ],
                        )
                      : null,
                  filled: true,
                  fillColor: Colors.white,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFE8D5B0)),
                  ),
                  focusedBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFE6A817), width: 2),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: Color(0xFFE8D5B0)),
                  ),
                  contentPadding: const EdgeInsets.symmetric(vertical: 14),
                ),
                onChanged: (val) {
                  setState(() {});
                  _searchAddress(val);
                },
                onSubmitted: (_) => _useManualEntry(),
              ),
            ),

            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.only(bottom: 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // GPS Button
                    _buildGpsButton(),

                    // "Order for someone else" toggle
                    _buildSomeoneElseToggle(),

                    // Delivery zone info
                    _buildDeliveryZoneInfo(),

                    // GPS error
                    if (_gpsError.isNotEmpty) _buildErrorBanner(),

                    // Search results or searching indicator
                    if (_isSearching)
                      const Padding(
                        padding: EdgeInsets.all(32),
                        child: Center(child: CircularProgressIndicator(color: Color(0xFFE6A817))),
                      )
                    else if (_searchResults.isNotEmpty)
                      _buildSearchResults()
                    else if (_searchController.text.length >= 3 && !_isSearching)
                      _buildManualEntryOption(),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildGpsButton() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
      child: InkWell(
        onTap: _isGpsLoading ? null : _detectGpsLocation,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE6A817), width: 1.5),
            boxShadow: [
              BoxShadow(
                color: const Color(0xFFE6A817).withOpacity(0.08),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFE6A817).withOpacity(0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: _isGpsLoading
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(color: Color(0xFFE6A817), strokeWidth: 2.5),
                      )
                    : const Icon(Icons.my_location, color: Color(0xFFE6A817), size: 22),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _isGpsLoading ? 'Detecting your location...' : 'Use Current Location',
                      style: GoogleFonts.outfit(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                        color: const Color(0xFF1A0F00),
                      ),
                    ),
                    Text(
                      'Using GPS • Most accurate',
                      style: GoogleFonts.outfit(fontSize: 12, color: const Color(0xFF9A7A50)),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: Color(0xFFE6A817)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSomeoneElseToggle() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: InkWell(
        onTap: () => setState(() => _isSomeoneElse = !_isSomeoneElse),
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: _isSomeoneElse ? const Color(0xFF4CAF50) : const Color(0xFFE8D5B0),
              width: _isSomeoneElse ? 2 : 1,
            ),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.04),
                blurRadius: 6,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: _isSomeoneElse
                      ? const Color(0xFF4CAF50).withOpacity(0.12)
                      : const Color(0xFFE8D5B0).withOpacity(0.5),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  Icons.people_alt_outlined,
                  color: _isSomeoneElse ? const Color(0xFF4CAF50) : const Color(0xFF9A7A50),
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Order for someone else',
                      style: GoogleFonts.outfit(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                        color: const Color(0xFF1A0F00),
                      ),
                    ),
                    Text(
                      _isSomeoneElse
                          ? 'Search or type their address below ✓'
                          : 'Deliver to a different address',
                      style: GoogleFonts.outfit(
                        fontSize: 12,
                        color: _isSomeoneElse ? const Color(0xFF4CAF50) : const Color(0xFF9A7A50),
                      ),
                    ),
                  ],
                ),
              ),
              Switch(
                value: _isSomeoneElse,
                onChanged: (v) => setState(() => _isSomeoneElse = v),
                activeColor: const Color(0xFF4CAF50),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildDeliveryZoneInfo() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF3CD),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFE6A817).withOpacity(0.4)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('🏍️', style: TextStyle(fontSize: 20)),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Delivery Zone — GIFT City, Gandhinagar',
                    style: GoogleFonts.outfit(
                      fontWeight: FontWeight.w800,
                      fontSize: 13,
                      color: const Color(0xFF5A3E1B),
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'We deliver within ${_maxDeliveryKm.toInt()} km of our outlet. You can also type any address manually.',
                    style: GoogleFonts.outfit(
                      fontSize: 12,
                      color: const Color(0xFF7A6040),
                      height: 1.4,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildErrorBanner() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFFFEBEB),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Colors.red.shade200),
        ),
        child: Row(
          children: [
            const Icon(Icons.error_outline, color: Colors.red, size: 20),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                _gpsError,
                style: GoogleFonts.outfit(color: Colors.red.shade700, fontSize: 13),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildManualEntryOption() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
      child: InkWell(
        onTap: _useManualEntry,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFE8D5B0)),
          ),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFE6A817).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.edit_location_alt, color: Color(0xFFE6A817), size: 22),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Use "${_searchController.text.trim()}"',
                      style: GoogleFonts.outfit(
                        fontWeight: FontWeight.w700,
                        fontSize: 14,
                        color: const Color(0xFF1A0F00),
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    Text(
                      'Enter address manually',
                      style: GoogleFonts.outfit(fontSize: 12, color: const Color(0xFF9A7A50)),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.arrow_forward_ios, size: 14, color: Color(0xFFE6A817)),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildSearchResults() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
          child: Text(
            _isSomeoneElse ? 'Deliver to their address' : 'Search Results',
            style: GoogleFonts.outfit(
              fontWeight: FontWeight.w700,
              fontSize: 13,
              color: const Color(0xFF9A7A50),
              letterSpacing: 0.5,
            ),
          ),
        ),
        ListView.separated(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: _searchResults.length,
          separatorBuilder: (_, __) => const Divider(height: 1, indent: 60),
          itemBuilder: (context, index) {
            final result = _searchResults[index];
            final dist = _calcDistance(
              result['lat'], result['lng'], _restaurantLat, _restaurantLng,
            );
            final inRange = dist <= _maxDeliveryKm;

            return ListTile(
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              leading: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFE6A817).withOpacity(0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.location_on, color: Color(0xFFE6A817), size: 20),
              ),
              title: Text(
                result['name'],
                style: GoogleFonts.outfit(
                  fontWeight: FontWeight.w600,
                  fontSize: 14,
                  color: const Color(0xFF1A0F00),
                ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              subtitle: Row(
                children: [
                  Container(
                    margin: const EdgeInsets.only(top: 4),
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: inRange
                          ? const Color(0xFF4CAF50).withOpacity(0.12)
                          : Colors.orange.withOpacity(0.12),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      inRange
                          ? '${dist.toStringAsFixed(1)} km • In range ✓'
                          : '${dist.toStringAsFixed(1)} km from outlet',
                      style: GoogleFonts.outfit(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: inRange ? const Color(0xFF4CAF50) : Colors.orange.shade700,
                      ),
                    ),
                  ),
                ],
              ),
              onTap: () => _selectSearchResult(result),
            );
          },
        ),
      ],
    );
  }
}
