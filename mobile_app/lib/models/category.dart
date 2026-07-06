class Category {
  final int id;
  final String name;
  final String slug;
  final String? emoji;
  final int sortOrder;
  final bool isActive;

  Category({
    required this.id,
    required this.name,
    required this.slug,
    this.emoji,
    required this.sortOrder,
    required this.isActive,
  });

  factory Category.fromJson(Map<String, dynamic> json) {
    return Category(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      emoji: json['emoji'] as String?,
      sortOrder: json['sort_order'] as int? ?? 0,
      isActive: json['is_active'] == true ||
          json['is_active'] == 1 ||
          json['is_active'] == '1' ||
          json['is_active'] == 'true',
    );
  }
}
