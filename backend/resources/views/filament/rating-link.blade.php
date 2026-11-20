@php
    $phone10 = substr(preg_replace('/\D/', '', (string) $order->customer_phone), -10);
    $msg = $order->ratingMessage();
    $url = $order->ratingUrl();
@endphp
<div style="display:flex;flex-direction:column;gap:14px;font-size:14px">
    @if ($order->order_rating)
        <div style="font-size:26px;color:{{ $order->order_rating <= 2 ? '#B91C1C' : '#B45309' }}">
            {{ \App\Filament\Resources\OrderFeedbackResource::stars($order->order_rating) }}
            <span style="font-size:15px;color:#555">{{ $order->order_rating }}/5 · {{ $order->rated_at?->format('d M, h:i A') }}</span>
        </div>
        <div style="background:#FFF8E7;border-radius:12px;padding:10px 12px">{{ $order->order_feedback ?: 'No comment.' }}</div>
        @if ($order->feedback_called_at)
            <div>✅ Called by {{ $order->feedback_called_by ?: 'staff' }} on {{ $order->feedback_called_at->format('d M, h:i A') }}{{ $order->feedback_note ? ' — ' . $order->feedback_note : '' }}</div>
        @endif
    @else
        <div>
            Send this link to <strong>{{ $order->customer_name }}</strong>. They tap it, pick 1–5 stars and can write a comment — no login needed.
            @if ($order->rating_link_sent_at)
                <div style="margin-top:6px;color:#11622F">Sent automatically on WhatsApp {{ $order->rating_link_sent_at->diffForHumans() }}.</div>
            @endif
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="https://wa.me/91{{ $phone10 }}?text={{ rawurlencode($msg) }}" target="_blank" rel="noopener"
               style="padding:9px 16px;border-radius:10px;background:#16A34A;color:#fff;font-weight:800;text-decoration:none">💬 Send on WhatsApp</a>
            <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js($url)); $el.innerText = '✓ Link copied'"
               style="padding:9px 16px;border-radius:10px;background:#F3F4F6;border:1px solid #D1D5DB;font-weight:700;cursor:pointer">📋 Copy link</button>
        </div>
        <div style="font-size:12px;color:#777;word-break:break-all">{{ $url }}</div>
    @endif
</div>
