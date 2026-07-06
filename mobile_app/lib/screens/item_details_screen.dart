import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:google_fonts/google_fonts.dart';
import '../models/menu_item.dart';
import '../providers/cart_provider.dart';

class ItemDetailsScreen extends StatelessWidget {
  final MenuItem menuItem;

  const ItemDetailsScreen({super.key, required this.menuItem});

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartProvider>();
    final cartItemIndex = cart.items.indexWhere((i) => i.menuItem.id == menuItem.id);
    final inCartQty = cartItemIndex >= 0 ? cart.items[cartItemIndex].quantity : 0;

    return Scaffold(
      backgroundColor: const Color(0xFFFFFDF5),
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Color(0xFF1A0F00)),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Hero Image representation
            Container(
              height: 250,
              width: double.infinity,
              color: const Color(0xFFFFF8E7),
              child: const Center(
                child: Text(
                  '🍲',
                  style: TextStyle(fontSize: 100),
                ),
              ),
            ),
            
            Padding(
              padding: const EdgeInsets.all(20.0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Title and Price
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Expanded(
                        child: Text(
                          menuItem.name,
                          style: GoogleFonts.outfit(
                            color: const Color(0xFF1A0F00),
                            fontSize: 24,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                      Text(
                        '₹${menuItem.price.toStringAsFixed(0)}',
                        style: GoogleFonts.outfit(
                          color: const Color(0xFFFF6B00),
                          fontSize: 24,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                  
                  const SizedBox(height: 12),

                  // Rating and Prep time
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE6A817).withOpacity(0.15),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.star, color: Color(0xFFE6A817), size: 16),
                            const SizedBox(width: 4),
                            Text(
                              menuItem.rating.toStringAsFixed(1),
                              style: GoogleFonts.outfit(
                                color: const Color(0xFFE6A817),
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 12),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                        decoration: BoxDecoration(
                          color: const Color(0xFFEDE0C4),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.timer_outlined, color: Color(0xFF2C1A00), size: 16),
                            const SizedBox(width: 4),
                            Text(
                              '${menuItem.prepTime} min prep',
                              style: GoogleFonts.outfit(color: const Color(0xFF2C1A00)),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 24),

                  // Description
                  Text(
                    'Details',
                    style: GoogleFonts.outfit(
                      color: const Color(0xFF1A0F00),
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    menuItem.description.isNotEmpty 
                        ? menuItem.description 
                        : 'Authentic flavors prepared fresh daily with the finest Punjabi ingredients. Served piping hot to order!',
                    style: GoogleFonts.outfit(
                      color: const Color(0xFF7A6040),
                      fontSize: 14,
                      height: 1.5,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: Container(
        decoration: const BoxDecoration(
          color: Color(0xFFFFF8E7),
          border: Border(top: BorderSide(color: Color(0xFFEDE0C4))),
        ),
        padding: const EdgeInsets.all(20),
        child: Row(
          children: [
            if (inCartQty > 0) ...[
              // Quantity controls
              Row(
                children: [
                  IconButton(
                    onPressed: () => cart.decrementQuantity(menuItem.id),
                    icon: const Icon(Icons.remove_circle_outline, color: Color(0xFFE6A817), size: 30),
                  ),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16.0),
                    child: Text(
                      '$inCartQty',
                      style: GoogleFonts.outfit(
                        color: const Color(0xFF1A0F00),
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: () => cart.incrementQuantity(menuItem.id),
                    icon: const Icon(Icons.add_circle_outline, color: Color(0xFFE6A817), size: 30),
                  ),
                ],
              ),
              const SizedBox(width: 16),
            ],
            Expanded(
              child: ElevatedButton(
                onPressed: () {
                  cart.addItem(menuItem);
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('${menuItem.name} added to cart!')),
                  );
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFE6A817),
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: Text(
                  'Add to Cart',
                  style: GoogleFonts.outfit(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
