class Order {
  final int id;
  final String orderNumber;
  final String customerName;
  final String customerPhone;
  final String deliveryAddress;
  final String city;
  final double subtotal;
  final double deliveryFee;
  final double grandTotal;
  final String paymentMethod;
  final String paymentStatus;
  final String status;
  final double? distanceKm;
  final String createdAt;
  final List<dynamic> items;

  Order({
    required this.id,
    required this.orderNumber,
    required this.customerName,
    required this.customerPhone,
    required this.deliveryAddress,
    required this.city,
    required this.subtotal,
    required this.deliveryFee,
    required this.grandTotal,
    required this.paymentMethod,
    required this.paymentStatus,
    required this.status,
    this.distanceKm,
    required this.createdAt,
    required this.items,
  });

  factory Order.fromJson(Map<String, dynamic> json) {
    return Order(
      id: json['id'] as int,
      orderNumber: json['order_number'] as String? ?? '',
      customerName: json['customer_name'] as String? ?? '',
      customerPhone: json['customer_phone'] as String? ?? '',
      deliveryAddress: json['delivery_address'] as String? ?? '',
      city: json['city'] as String? ?? 'Amritsar',
      subtotal: double.tryParse(json['subtotal']?.toString() ?? '') ?? 0.0,
      deliveryFee: double.tryParse(json['delivery_fee']?.toString() ?? '') ?? 0.0,
      grandTotal: double.tryParse(json['grand_total']?.toString() ?? '') ?? 0.0,
      paymentMethod: json['payment_method'] as String? ?? 'cash_on_delivery',
      paymentStatus: json['payment_status'] as String? ?? 'pending',
      status: json['status'] as String? ?? 'pending',
      distanceKm: double.tryParse(json['distance_km']?.toString() ?? ''),
      createdAt: json['created_at'] as String? ?? '',
      items: json['items'] as List<dynamic>? ?? [],
    );
  }
}
