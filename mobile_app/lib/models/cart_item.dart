import 'menu_item.dart';

class CartItem {
  final MenuItem menuItem;
  int quantity;

  CartItem({
    required this.menuItem,
    this.quantity = 1,
  });

  double get subtotal => menuItem.price * quantity;

  Map<String, dynamic> toJson() {
    return {
      'menu_item_id': menuItem.id,
      'name': menuItem.name,
      'price': menuItem.price,
      'quantity': quantity,
      'category': menuItem.category,
    };
  }

  factory CartItem.fromJson(Map<String, dynamic> json, List<MenuItem> menuItems) {
    final itemId = json['menu_item_id'] as int;
    final item = menuItems.firstWhere(
      (m) => m.id == itemId,
      orElse: () => MenuItem(
        id: itemId,
        name: json['name'] as String? ?? 'Deleted Item',
        description: '',
        category: json['category'] as String? ?? '',
        price: double.tryParse(json['price']?.toString() ?? '') ?? 0.0,
        prepTime: 15,
        rating: 4.5,
        isAvailable: true,
        isFeatured: false,
      ),
    );

    return CartItem(
      menuItem: item,
      quantity: json['quantity'] as int? ?? 1,
    );
  }
}
