{{-- Kitchen ticket (KOT): items and notes only, no prices --}}
<div style="background: white; padding: 12px; margin: 0 auto; font-family: monospace; font-size: 13px; color: black; max-width: 80mm;">
    <div style="text-align: center; font-weight: bold; font-size: 16px;">KITCHEN TICKET</div>
    <div style="text-align: center; font-size: 12px; margin-top: 2px;">#{{ $order->order_number }}</div>
    <div style="border-top: 1px dashed black; margin: 8px 0;"></div>
    <div style="display: flex; justify-content: space-between; font-weight: bold;">
        <span>{{ ['delivery' => 'DELIVERY', 'takeaway' => 'TAKE AWAY', 'dine_in' => 'DINE IN'][$order->order_type ?? 'delivery'] ?? strtoupper((string) $order->order_type) }}</span>
        <span>{{ $order->table_number ? 'TABLE ' . $order->table_number : '' }}</span>
    </div>
    <div style="font-size: 11px;">{{ $order->created_at->format('d/m h:i A') }}</div>
    <div style="border-top: 1px dashed black; margin: 8px 0;"></div>
    @foreach ($order->items as $item)
        <div style="display: flex; gap: 8px; font-size: 15px; font-weight: bold; margin: 4px 0;">
            <span style="min-width: 28px;">{{ $item->quantity }}×</span><span>{{ $item->name }}</span>
        </div>
    @endforeach
    @if ($order->special_instructions)
        <div style="border-top: 1px dashed black; margin: 8px 0;"></div>
        <div style="font-size: 14px; font-weight: bold;">NOTE: {{ $order->special_instructions }}</div>
    @endif
</div>
