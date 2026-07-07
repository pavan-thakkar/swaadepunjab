import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:geolocator/geolocator.dart';
import '../models/menu_item.dart';
import '../models/category.dart';
import '../models/order.dart';
import '../providers/cart_provider.dart';
import '../services/api_service.dart';
import 'item_details_screen.dart';
import 'checkout_screen.dart';
import 'profile_details_screen.dart';
import 'order_history_screen.dart';
import 'location_picker_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _currentTab = 0;

  // Profile controllers
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _addressController = TextEditingController();
  final TextEditingController _cityController = TextEditingController();
  bool _isEditing = false;

  // Menu Tab State
  List<MenuItem> _menuItems = [];
  List<Category> _categories = [];
  bool _isLoading = true;
  String _errorMessage = '';
  String _searchQuery = '';
  String _selectedCategory = 'all';

  // Profile Tab State
  List<Order> _historyOrders = [];
  bool _isHistoryLoading = false;
  String _historyError = '';

  @override
  void initState() {
    super.initState();
    _fetchData();
    _fetchGPSLocationOnStart();
  }

  Future<void> _fetchData() async {
    try {
      final items = await ApiService.fetchMenuItems();
      final cats = await ApiService.fetchCategories();
      setState(() {
        _menuItems = items;
        _categories = cats;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _errorMessage = e.toString();
        _isLoading = false;
      });
    }
  }

  Future<void> _fetchHistoryOrders(String phone) async {
    setState(() {
      _isHistoryLoading = true;
      _historyError = '';
    });
    try {
      final orders = await ApiService.fetchOrderHistory(phone);
      setState(() {
        _historyOrders = orders;
        _isHistoryLoading = false;
      });
    } catch (e) {
      setState(() {
        _historyError = e.toString();
        _isHistoryLoading = false;
      });
    }
  }

  Future<void> _fetchGPSLocationOnStart() async {
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) return;

      LocationPermission permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
        if (permission == LocationPermission.denied) return;
      }
      if (permission == LocationPermission.deniedForever) return;

      Position position = await Geolocator.getCurrentPosition(
        desiredAccuracy: LocationAccuracy.high,
      );
      final cart = context.read<CartProvider>();
      await cart.updateGPSLocation(position.latitude, position.longitude);

      if (cart.userAddress != null && cart.userAddress!.isNotEmpty) {
        await cart.calculateDeliveryFee(
          cart.userAddress!,
          cart.userCity ?? 'Amritsar',
          position.latitude,
          position.longitude,
        );
      }
      debugPrint("Successfully fetched coordinates on boot: ${position.latitude}, ${position.longitude}");
    } catch (e) {
      debugPrint("Geolocator startup error: $e");
    }
  }

  void _onTabSelected(int index, CartProvider cart) {
    setState(() {
      _currentTab = index;
    });
    if (index == 2) {
      if (cart.userPhone != null) {
        _nameController.text = cart.userName ?? '';
        _phoneController.text = cart.userPhone ?? '';
        _emailController.text = cart.userEmail ?? '';
        _addressController.text = cart.userAddress ?? '';
        _cityController.text = cart.userCity ?? 'Amritsar';
        if (_historyOrders.isEmpty) {
          _fetchHistoryOrders(cart.userPhone!);
        }
      }
    }
  }

  List<MenuItem> get _filteredItems {
    return _menuItems.where((item) {
      final matchesSearch = item.name.toLowerCase().contains(_searchQuery.toLowerCase()) ||
          item.description.toLowerCase().contains(_searchQuery.toLowerCase());
      final matchesCategory = _selectedCategory == 'all' || item.category == _selectedCategory;
      return matchesSearch && matchesCategory && item.isAvailable;
    }).toList();
  }

  String _getAppBarTitle() {
    switch (_currentTab) {
      case 0:
        return 'SWAAD E PUNJAB';
      case 1:
        return 'My Basket';
      case 2:
        return 'My Profile';
      default:
        return 'SWAAD E PUNJAB';
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
        title: _currentTab == 0
            ? GestureDetector(
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => const LocationPickerScreen(),
                    ),
                  );
                },
                child: Row(
                  children: [
                    const Icon(Icons.location_on, color: Color(0xFFE6A817), size: 20),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Flexible(
                                child: Text(
                                  cart.isForSomeoneElse
                                      ? (cart.deliveryAddress != null && cart.deliveryAddress!.isNotEmpty
                                          ? (cart.deliveryAddress!.length > 18
                                              ? '${cart.deliveryAddress!.substring(0, 18)}...'
                                              : cart.deliveryAddress!)
                                          : 'Someone Else\'s Addr')
                                      : (cart.userAddress?.isNotEmpty == true
                                          ? (cart.userAddress!.length > 20
                                              ? '${cart.userAddress!.substring(0, 20)}...'
                                              : cart.userAddress!)
                                          : (cart.userLatitude != null
                                              ? 'Current Location'
                                              : 'Set Location')),
                                  style: GoogleFonts.outfit(
                                    color: const Color(0xFF1A0F00),
                                    fontSize: 14,
                                    fontWeight: FontWeight.bold,
                                  ),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                              const Icon(Icons.keyboard_arrow_down, color: Color(0xFF1A0F00), size: 16),
                            ],
                          ),
                          Text(
                            cart.isForSomeoneElse
                                ? '👥 For someone else • ${cart.deliveryCity ?? ''}'
                                : (cart.userCity ?? 'Tap to set location'),
                            style: GoogleFonts.outfit(
                              color: cart.isForSomeoneElse
                                  ? const Color(0xFF4CAF50)
                                  : const Color(0xFF7A6040),
                              fontSize: 10,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              )
            : Text(
                _getAppBarTitle(),
                style: GoogleFonts.outfit(
                  color: const Color(0xFFE6A817),
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.2,
                ),
              ),
      ),
      body: _buildTabBody(context, cart),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentTab,
        onTap: (index) => _onTabSelected(index, cart),
        backgroundColor: const Color(0xFFFFF8E7),
        selectedItemColor: const Color(0xFFE6A817),
        unselectedItemColor: const Color(0xFF7A6040),
        selectedLabelStyle: GoogleFonts.outfit(fontWeight: FontWeight.bold),
        unselectedLabelStyle: GoogleFonts.outfit(),
        items: [
          const BottomNavigationBarItem(
            icon: Icon(Icons.restaurant_menu),
            label: 'Menu',
          ),
          BottomNavigationBarItem(
            icon: Badge(
              label: Text('${cart.itemCount}'),
              isLabelVisible: cart.itemCount > 0,
              backgroundColor: const Color(0xFFFF6B00),
              child: const Icon(Icons.shopping_basket),
            ),
            label: 'Basket',
          ),
          const BottomNavigationBarItem(
            icon: Icon(Icons.person),
            label: 'Profile',
          ),
        ],
      ),
    );
  }

  Widget _buildTabBody(BuildContext context, CartProvider cart) {
    switch (_currentTab) {
      case 0:
        return _buildMenuTab(context, cart);
      case 1:
        return _buildCartTab(context, cart);
      case 2:
        return _buildProfileTab(context, cart);
      default:
        return _buildMenuTab(context, cart);
    }
  }

  Widget _buildMenuTab(BuildContext context, CartProvider cart) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator(color: Color(0xFFE6A817)));
    }
    if (_errorMessage.isNotEmpty) {
      return RefreshIndicator(
        color: const Color(0xFFE6A817),
        onRefresh: _fetchData,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            SizedBox(height: MediaQuery.of(context).size.height * 0.3),
            Center(
              child: Text(
                'Error: $_errorMessage\nPull down to retry',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Color(0xFF7A6040), fontSize: 16),
              ),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      color: const Color(0xFFE6A817),
      onRefresh: _fetchData,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          // 1. Search Bar
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.only(left: 16, right: 16, top: 12, bottom: 8),
              child: TextField(
                onChanged: (val) => setState(() => _searchQuery = val),
                style: const TextStyle(color: Color(0xFF1A0F00)),
                decoration: InputDecoration(
                  hintText: 'Search tasty dishes, cuisines...',
                  hintStyle: const TextStyle(color: Color(0xFFB89A70), fontSize: 13),
                  prefixIcon: const Icon(Icons.search, color: Color(0xFF7A6040), size: 20),
                  filled: true,
                  fillColor: const Color(0xFFFFF8E7),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(28),
                    borderSide: const BorderSide(color: Color(0xFFEDE0C4)),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(28),
                    borderSide: const BorderSide(color: Color(0xFFEDE0C4)),
                  ),
                ),
              ),
            ),
          ),

          // 2. Sliding Promo Banners
          SliverToBoxAdapter(
            child: SizedBox(
              height: 140,
              child: PageView(
                controller: PageController(viewportFraction: 0.88),
                children: [
                  _buildPromoCard(
                    '🔥 FLAT 50% OFF',
                    'On your first food order at Swaad E Punjab',
                    'Code: SWAAD50',
                    const Color(0xFFE6A817),
                  ),
                  _buildPromoCard(
                    '🛵 FREE DELIVERY',
                    'Enjoy free delivery on all orders above ₹199',
                    'No code required',
                    const Color(0xFFFF6B00),
                  ),
                  _buildPromoCard(
                    '🍛 PUNJABI SPECIALS',
                    'Taste the authenticity of Amritsari Thali',
                    'Order now',
                    const Color(0xFF7A6040),
                  ),
                ],
              ),
            ),
          ),

          const SliverToBoxAdapter(child: SizedBox(height: 16)),

          // 3. Swiggy style Mind Bubble Categories (Sticky!)
          SliverPersistentHeader(
            pinned: true,
            delegate: _StickyCategoryHeaderDelegate(
              height: 142.0,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16.0),
                    child: Text(
                      "What's on your mind?",
                      style: GoogleFonts.outfit(
                        color: const Color(0xFF1A0F00),
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  SizedBox(
                    height: 90,
                    child: ListView.builder(
                      scrollDirection: Axis.horizontal,
                      itemCount: _categories.length + 1,
                      padding: const EdgeInsets.symmetric(horizontal: 16),
                      itemBuilder: (context, index) {
                        final isAll = index == 0;
                        final categorySlug = isAll ? 'all' : _categories[index - 1].slug;
                        final categoryName = isAll ? 'All' : _categories[index - 1].name;
                        final categoryEmoji = isAll ? '🍽️' : (_categories[index - 1].emoji ?? '🍛');

                        final isSelected = _selectedCategory == categorySlug;

                        return Padding(
                          padding: const EdgeInsets.only(right: 14.0),
                          child: GestureDetector(
                            onTap: () {
                              setState(() => _selectedCategory = categorySlug);
                            },
                            child: Column(
                              children: [
                                CircleAvatar(
                                  radius: 30,
                                  backgroundColor: isSelected ? const Color(0xFFE6A817) : const Color(0xFFFFF8E7),
                                  child: CircleAvatar(
                                    radius: 28,
                                    backgroundColor: Colors.white,
                                    child: Text(
                                      categoryEmoji,
                                      style: const TextStyle(fontSize: 24),
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  categoryName,
                                  style: GoogleFonts.outfit(
                                    color: isSelected ? const Color(0xFFE6A817) : const Color(0xFF1A0F00),
                                    fontSize: 11,
                                    fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                ],
              ),
            ),
          ),

          const SliverToBoxAdapter(
            child: Divider(height: 16, color: Color(0xFFEDE0C4), thickness: 0.5),
          ),

          // 4. Menu Grid Header
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8),
              child: Text(
                "Popular Dishes",
                style: GoogleFonts.outfit(
                  color: const Color(0xFF1A0F00),
                  fontSize: 16,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),

          // 5. Menu Grid
          _filteredItems.isEmpty
              ? const SliverToBoxAdapter(
                  child: SizedBox(
                    height: 200,
                    child: Center(
                      child: Text(
                        'No items found.',
                        style: TextStyle(color: Color(0xFFB89A70)),
                      ),
                    ),
                  ),
                )
              : SliverPadding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                  sliver: SliverGrid(
                    gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                      crossAxisCount: 2,
                      crossAxisSpacing: 16,
                      mainAxisSpacing: 20,
                      childAspectRatio: 0.68,
                    ),
                    delegate: SliverChildBuilderDelegate(
                      (context, index) {
                        final item = _filteredItems[index];
                        return _buildMenuItemCard(context, item, cart);
                      },
                      childCount: _filteredItems.length,
                    ),
                  ),
                ),
        ],
      ),
    );
  }

  Widget _buildPromoCard(String title, String subtitle, String code, Color color) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [color, color.withOpacity(0.8)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            title,
            style: GoogleFonts.outfit(
              color: Colors.white,
              fontSize: 13,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            subtitle,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: GoogleFonts.outfit(
              color: Colors.white.withOpacity(0.9),
              fontSize: 9,
            ),
          ),
          const SizedBox(height: 4),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.25),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(
              code,
              style: GoogleFonts.outfit(
                color: Colors.white,
                fontSize: 8,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildCartTab(BuildContext context, CartProvider cart) {
    if (cart.items.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Text('🛒', style: TextStyle(fontSize: 60)),
            const SizedBox(height: 16),
            Text(
              'Your basket is empty',
              style: GoogleFonts.outfit(
                color: const Color(0xFF1A0F00),
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Add some delicious dishes from the menu!',
              style: TextStyle(color: Color(0xFF7A6040)),
            ),
            const SizedBox(height: 24),
            ElevatedButton(
              onPressed: () => setState(() => _currentTab = 0),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFE6A817),
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(30)),
              ),
              child: const Text('Browse Menu', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
            ),
          ],
        ),
      );
    }

    return Column(
      children: [
        Expanded(
          child: ListView.builder(
            itemCount: cart.items.length,
            padding: const EdgeInsets.all(16),
            itemBuilder: (context, index) {
              final item = cart.items[index];
              return Card(
                color: const Color(0xFFFFFFFF),
                margin: const EdgeInsets.only(bottom: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                  side: const BorderSide(color: Color(0xFFEDE0C4)),
                ),
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Row(
                    children: [
                      Container(
                        width: 60,
                        height: 60,
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF8E7),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: const Center(child: Text('🍲', style: TextStyle(fontSize: 28))),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.menuItem.name,
                              style: GoogleFonts.outfit(
                                color: const Color(0xFF1A0F00),
                                fontWeight: FontWeight.bold,
                                fontSize: 15,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '₹${item.menuItem.price.toStringAsFixed(0)} each',
                              style: const TextStyle(color: Color(0xFF7A6040), fontSize: 13),
                            ),
                          ],
                        ),
                      ),
                      Row(
                        children: [
                          IconButton(
                            onPressed: () => cart.decrementQuantity(item.menuItem.id),
                            icon: const Icon(Icons.remove_circle_outline, color: Color(0xFFE6A817), size: 24),
                          ),
                          Text(
                            '${item.quantity}',
                            style: GoogleFonts.outfit(
                              color: const Color(0xFF1A0F00),
                              fontWeight: FontWeight.bold,
                              fontSize: 15,
                            ),
                          ),
                          IconButton(
                            onPressed: () => cart.incrementQuantity(item.menuItem.id),
                            icon: const Icon(Icons.add_circle_outline, color: Color(0xFFE6A817), size: 24),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),
        Container(
          padding: const EdgeInsets.all(20),
          decoration: const BoxDecoration(
            color: Color(0xFFFFF8E7),
            border: Border(top: BorderSide(color: Color(0xFFEDE0C4))),
          ),
          child: Column(
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Total Amount',
                        style: GoogleFonts.outfit(color: const Color(0xFF1A0F00), fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 2),
                      if (cart.customDeliveryFee > 0)
                        Text(
                          'Subtotal: ₹${cart.totalSubtotal.toStringAsFixed(0)} • Delivery: ₹${cart.customDeliveryFee.toStringAsFixed(0)}',
                          style: GoogleFonts.outfit(color: const Color(0xFF7A6040), fontSize: 12),
                        )
                      else if (cart.deliveryAddress != null && cart.deliveryAddress!.isNotEmpty)
                        Text(
                          'Subtotal: ₹${cart.totalSubtotal.toStringAsFixed(0)} • Delivery: FREE',
                          style: GoogleFonts.outfit(color: const Color(0xFF2E7D32), fontSize: 12, fontWeight: FontWeight.w600),
                        )
                      else
                        Text(
                          'Subtotal: ₹${cart.totalSubtotal.toStringAsFixed(0)} • + Delivery at checkout',
                          style: GoogleFonts.outfit(color: const Color(0xFF7A6040), fontSize: 12),
                        ),
                    ],
                  ),
                  Text(
                    '₹${(cart.totalSubtotal + cart.customDeliveryFee).toStringAsFixed(0)}',
                    style: GoogleFonts.outfit(
                      color: const Color(0xFFFF6B00),
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (context) => const CheckoutScreen()),
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFFE6A817),
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: const Text(
                    'Proceed to Checkout',
                    style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildProfileTab(BuildContext context, CartProvider cart) {
    if (cart.userPhone == null) {
      return SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SizedBox(height: 20),
            Text(
              'Welcome to Swaad E Punjab',
              style: GoogleFonts.outfit(
                color: const Color(0xFF1A0F00),
                fontSize: 24,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            const Text(
              'Login with your name and phone number to begin.',
              style: TextStyle(color: Color(0xFF7A6040)),
            ),
            const SizedBox(height: 32),
            TextField(
              controller: _nameController,
              decoration: InputDecoration(
                labelText: 'Your Name',
                labelStyle: const TextStyle(color: Color(0xFF7A6040)),
                filled: true,
                fillColor: const Color(0xFFFFF8E7),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: Color(0xFFEDE0C4)),
                ),
              ),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                labelText: 'Phone Number',
                labelStyle: const TextStyle(color: Color(0xFF7A6040)),
                filled: true,
                fillColor: const Color(0xFFFFF8E7),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: Color(0xFFEDE0C4)),
                ),
              ),
            ),
            const SizedBox(height: 24),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () async {
                  final phone = _phoneController.text.trim();
                  final name = _nameController.text.trim();
                  if (phone.isEmpty || name.isEmpty) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Please enter name and phone number')),
                    );
                    return;
                  }
                  await cart.saveProfile(
                    name: name,
                    phone: phone,
                    email: '',
                    address: '',
                    city: 'Amritsar',
                  );
                  _nameController.text = name;
                  _phoneController.text = phone;
                  _emailController.text = '';
                  _addressController.text = '';
                  _cityController.text = 'Amritsar';
                  _fetchHistoryOrders(phone);
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFE6A817),
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Login', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
              ),
            ),
          ],
        ),
      );
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // 1. Profile Summary Card (Clickable to open details screen)
          Card(
            color: const Color(0xFFFFFFFF),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: const BorderSide(color: Color(0xFFEDE0C4)),
            ),
            child: InkWell(
              onTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => const ProfileDetailsScreen(),
                  ),
                );
              },
              borderRadius: BorderRadius.circular(16),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    const CircleAvatar(
                      radius: 30,
                      backgroundColor: Color(0xFFE6A817),
                      child: Icon(Icons.person, color: Colors.white, size: 30),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            cart.userName ?? 'Customer',
                            style: GoogleFonts.outfit(
                              color: const Color(0xFF1A0F00),
                              fontSize: 20,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            cart.userPhone ?? '',
                            style: const TextStyle(color: Color(0xFF7A6040)),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.edit, color: Color(0xFFE6A817), size: 22),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 16),

          // 2. Orders Card (Clickable to open past orders list)
          Card(
            color: const Color(0xFFFFFFFF),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
              side: const BorderSide(color: Color(0xFFEDE0C4)),
            ),
            child: InkWell(
              onTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => const OrderHistoryScreen(),
                  ),
                );
              },
              borderRadius: BorderRadius.circular(16),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    const Icon(Icons.receipt_long, color: Color(0xFFE6A817), size: 28),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'My Orders',
                            style: GoogleFonts.outfit(
                              color: const Color(0xFF1A0F00),
                              fontSize: 16,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'View and track past orders',
                            style: GoogleFonts.outfit(
                              color: const Color(0xFF7A6040),
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.arrow_forward_ios, color: Color(0xFFB89A70), size: 16),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMenuItemCard(BuildContext context, MenuItem item, CartProvider cart) {
    final originalPrice = ((item.price * 1.2) / 5).round() * 5.0;
    final reviewsCount = (item.id * 23 + 17) % 500 + 15;

    return Card(
      color: const Color(0xFFFFFFFF),
      elevation: 0.5,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Color(0xFFEDE0C4)),
      ),
      child: InkWell(
        onTap: () {
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => ItemDetailsScreen(menuItem: item),
            ),
          );
        },
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(8.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Food Image
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(12),
                  child: Container(
                    color: const Color(0xFFFFF8E7),
                    child: Stack(
                      children: [
                        const Positioned.fill(
                          child: Center(
                            child: Text(
                              '🍲',
                              style: TextStyle(fontSize: 48),
                            ),
                          ),
                        ),
                        
                        // Prep time pill top-left
                        Positioned(
                          top: 6,
                          left: 6,
                          child: Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.92),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                const Icon(Icons.access_time, color: Color(0xFF7A6040), size: 10),
                                const SizedBox(width: 2),
                                Text(
                                  '${item.prepTime}m',
                                  style: GoogleFonts.outfit(
                                    color: const Color(0xFF1A0F00),
                                    fontSize: 9,
                                    fontWeight: FontWeight.bold,
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
              ),
              const SizedBox(height: 8),

              // 2. Tags Row: Veg Icon, Bestseller, Rating
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(1.5),
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.green, width: 1.2),
                        borderRadius: BorderRadius.circular(3),
                      ),
                      child: const Icon(Icons.circle, color: Colors.green, size: 5),
                    ),
                    const SizedBox(width: 4),
                    if (item.isFeatured || item.id % 2 == 0) ...[
                      const Icon(Icons.star, color: Colors.orange, size: 10),
                      Text(
                        'Bestseller',
                        style: GoogleFonts.outfit(
                          color: Colors.orange,
                          fontSize: 9,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(width: 4),
                    ],
                    Text(
                      '★ ${item.rating.toStringAsFixed(1)} ($reviewsCount)',
                      style: GoogleFonts.outfit(
                        color: const Color(0xFF2E7D32),
                        fontSize: 9,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 4),

              // 3. Dish Name
              Text(
                item.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.outfit(
                  color: const Color(0xFF1A0F00),
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 4),

              // 4. Pricing block & ADD Button Row
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (originalPrice > item.price)
                        Text(
                          '₹${originalPrice.toStringAsFixed(0)}',
                          style: const TextStyle(
                            color: Color(0xFFB89A70),
                            fontSize: 9,
                            decoration: TextDecoration.lineThrough,
                          ),
                        ),
                      Text(
                        '₹${item.price.toStringAsFixed(0)}',
                        style: GoogleFonts.outfit(
                          color: const Color(0xFF1A0F00),
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                  Container(
                    height: 28,
                    width: 68,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(6),
                      border: Border.all(color: const Color(0xFFEDE0C4)),
                    ),
                    child: InkWell(
                      onTap: () {
                        cart.addItem(item);
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text('${item.name} added to cart!'),
                            duration: const Duration(seconds: 1),
                          ),
                        );
                      },
                      borderRadius: BorderRadius.circular(6),
                      child: Center(
                        child: Text(
                          'ADD',
                          style: GoogleFonts.outfit(
                            color: const Color(0xFFE6A817),
                            fontWeight: FontWeight.w900,
                            fontSize: 10,
                            letterSpacing: 0.5,
                          ),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StickyCategoryHeaderDelegate extends SliverPersistentHeaderDelegate {
  final Widget child;
  final double height;

  _StickyCategoryHeaderDelegate({required this.child, this.height = 145.0});

  @override
  double get minExtent => height;

  @override
  double get maxExtent => height;

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) {
    return Container(
      color: const Color(0xFFFFFDF5), // Match Scaffold background to cover scroll contents behind
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: child,
    );
  }

  @override
  bool shouldRebuild(covariant _StickyCategoryHeaderDelegate oldDelegate) {
    return true;
  }
}
