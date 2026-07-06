class MenuItem {
  final int id;
  final String name;
  final String description;
  final String category;
  final double price;
  final int prepTime;
  final double rating;
  final bool isAvailable;
  final bool isFeatured;
  final String? image;

  MenuItem({
    required this.id,
    required this.name,
    required this.description,
    required this.category,
    required this.price,
    required this.prepTime,
    required this.rating,
    required this.isAvailable,
    required this.isFeatured,
    this.image,
  });

  factory MenuItem.fromJson(Map<String, dynamic> json) {
    return MenuItem(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      description: json['description'] as String? ?? '',
      category: json['category'] as String? ?? '',
      price: double.tryParse(json['price']?.toString() ?? '') ?? 0.0,
      prepTime: int.tryParse(json['prep_time']?.toString() ?? '') ?? 15,
      rating: double.tryParse(json['rating']?.toString() ?? '') ?? 4.0,
      isAvailable: json['is_available'] == true ||
          json['is_available'] == 1 ||
          json['is_available'] == '1' ||
          json['is_available'] == 'true',
      isFeatured: json['is_featured'] == true ||
          json['is_featured'] == 1 ||
          json['is_featured'] == '1' ||
          json['is_featured'] == 'true',
      image: json['image'] as String?,
    );
  }
}
