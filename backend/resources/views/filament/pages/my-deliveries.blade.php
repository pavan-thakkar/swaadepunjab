@php
    $m = fn ($v) => '₹' . number_format((float) $v, fmod((float) $v, 1) ? 2 : 0);
    $digits = fn ($p) => substr(preg_replace('/\D/', '', (string) $p), -10);
@endphp
<x-filament-panels::page>
    <script src="{{ asset('js/sp-qrcode.js') }}?v={{ @filemtime(public_path('js/sp-qrcode.js')) }}"></script>
    <style>
        .md-wrap { max-width: 640px; margin: 0 auto; display: flex; flex-direction: column; gap: 14px; }
        .md-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; background: #F3EAD6; border-radius: 14px; padding: 5px; }
        .md-tabs button { height: 44px; border: 0; border-radius: 10px; background: transparent; font: inherit; font-weight: 700; font-size: 15px; color: #4A3518; cursor: pointer; }
        .md-tabs button[aria-pressed="true"] { background: #fff; font-weight: 900; color: #1A0F00; box-shadow: 0 1px 4px rgba(100,60,0,.15); }
        .md-card { background: #fff; border: 1px solid #EEE3CF; border-radius: 18px; padding: 16px; box-shadow: 0 2px 10px rgba(80,50,0,.06); }
        .md-card.otw { border: 2px solid #3B82F6; }
        .md-top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
        .md-no { font-weight: 900; font-size: 15px; }
        .md-chip { font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 999px; white-space: nowrap; }
        .md-name { font-size: 20px; font-weight: 900; margin-top: 10px; }
        .md-addr { font-size: 15px; line-height: 1.4; margin-top: 4px; color: #3B2A12; }
        .md-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 12px; }
        .md-btn { display: flex; align-items: center; justify-content: center; gap: 6px; min-height: 50px; border-radius: 14px; border: 1px solid #E5D9C3; background: #fff; font: inherit; font-weight: 800; font-size: 15px; color: #1A0F00; text-decoration: none; cursor: pointer; text-align: center; padding: 6px 8px; }
        .md-btn.call { background: #ECFDF5; border-color: #A7F3D0; color: #065F46; }
        .md-btn.wa { background: #F0FDF4; border-color: #BBF7D0; color: #15803D; }
        .md-btn.map { background: #EFF6FF; border-color: #BFDBFE; color: #1D4ED8; }
        .md-btn.big { min-height: 58px; font-size: 17px; }
        .md-btn.go { background: #3B82F6; border-color: #2563EB; color: #fff; }
        .md-btn.done { background: #16A34A; border-color: #15803D; color: #fff; }
        .md-btn.cash { background: #FEF9C3; border-color: #FDE047; }
        .md-btn.online { background: #EDE9FE; border-color: #C4B5FD; color: #5B21B6; }
        .md-collect { margin-top: 12px; border-radius: 14px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center; font-weight: 900; }
        .md-collect.due { background: #FEF2F2; color: #B91C1C; border: 2px dashed #FCA5A5; }
        .md-collect.paid { background: #F0FDF4; color: #15803D; border: 1px solid #BBF7D0; }
        .md-collect .amt { font-size: 26px; }
        .md-items { margin-top: 10px; font-size: 14px; color: #3B2A12; background: #FBF8F1; border-radius: 12px; padding: 8px 12px; }
        .md-items summary { cursor: pointer; font-weight: 800; }
        .md-note { margin-top: 8px; background: #FEF3C7; color: #92400E; border-radius: 10px; padding: 6px 10px; font-size: 13.5px; font-weight: 700; }
        .md-empty { text-align: center; padding: 40px 10px; color: #8A7A5C; font-weight: 700; }
        .md-sheet-bg { position: fixed; inset: 0; background: rgba(0,0,0,.55); z-index: 60; display: flex; align-items: flex-end; justify-content: center; }
        .md-sheet { background: #fff; width: 100%; max-width: 480px; border-radius: 22px 22px 0 0; padding: 18px 18px 24px; max-height: 94vh; overflow-y: auto; text-align: center; }
        .md-sheet canvas { width: 260px; height: 260px; image-rendering: pixelated; border-radius: 12px; border: 1px solid #eee; margin: 10px auto; display: block; }
        .md-cashbar { background: linear-gradient(135deg,#2C1A00,#5A3500); color: #FFF6E0; border-radius: 16px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; font-weight: 800; }
        .dark .md-card, .dark .md-btn:not(.go):not(.done), .dark .md-sheet { background: #1f2937; border-color: #374151; color: #f3f4f6; }
        .dark .md-addr, .dark .md-items { color: #e5e7eb; } .dark .md-items { background: #111827; }
    </style>

    <div class="md-wrap" wire:poll.20s>
        @if ($isRider && $cashInHand > 0)
            <div class="md-cashbar"><span>💵 Cash with you today<br><small style="font-weight:600;opacity:.8">Give this to the counter</small></span><span style="font-size:24px">{{ $m($cashInHand) }}</span></div>
        @endif

        <div class="md-tabs" role="group">
            <button type="button" aria-pressed="{{ $tab === 'todo' ? 'true' : 'false' }}" wire:click="$set('tab', 'todo')">To deliver ({{ $todo->count() }})</button>
            <button type="button" aria-pressed="{{ $tab === 'done' ? 'true' : 'false' }}" wire:click="$set('tab', 'done')">Delivered today ({{ $done->count() }})</button>
        </div>

        @php $list = $tab === 'todo' ? $todo : $done; @endphp
        @forelse ($list as $o)
            @php
                $due = $o->amountToCollect();
                $ph = $digits($o->customer_phone);
                $otw = $o->status === 'out_for_delivery';
                [$chipTxt, $chipCss] = match ($o->status) {
                    'out_for_delivery' => ['🛵 On the way', 'background:#DBEAFE;color:#1D4ED8'],
                    'preparing'        => ['✅ Ready to pick up', 'background:#DCFCE7;color:#15803D'],
                    'delivered'        => ['🎉 Delivered ' . $o->delivered_at?->timezone(config('app.timezone'))->format('h:i A'), 'background:#F0FDF4;color:#15803D'],
                    default            => ['🍳 Being cooked', 'background:#FEF3C7;color:#92400E'],
                };
            @endphp
            <div class="md-card {{ $otw ? 'otw' : '' }}" wire:key="o{{ $o->id }}">
                <div class="md-top">
                    <span class="md-no">#{{ substr($o->order_number, -6) }} <small style="font-weight:600;color:#8A7A5C">· {{ $o->created_at->timezone(config('app.timezone'))->format('h:i A') }}</small></span>
                    <span class="md-chip" style="{{ $chipCss }}">{{ $chipTxt }}</span>
                </div>
                @unless ($isRider)<div style="font-size:12.5px;font-weight:800;color:#1D4ED8;margin-top:4px">🛵 {{ $o->rider_name }}</div>@endunless
                <div class="md-name">{{ $o->customer_name }}</div>
                <div class="md-addr">📍 {{ $o->delivery_address }}{{ $o->city && $o->city !== 'Counter' ? ', ' . $o->city : '' }}</div>

                <div class="md-row">
                    @if (strlen($ph) === 10)
                        <a class="md-btn call" href="tel:+91{{ $ph }}">📞 Call</a>
                        <a class="md-btn wa" href="https://wa.me/91{{ $ph }}" target="_blank" rel="noopener">💬 WhatsApp</a>
                    @else
                        <span class="md-btn" style="opacity:.5">📞 No phone</span><span></span>
                    @endif
                    <a class="md-btn map" href="{{ $o->mapsUrl() }}" target="_blank" rel="noopener">🗺️ Map</a>
                </div>

                <details class="md-items">
                    <summary>🧾 {{ $o->items->sum('quantity') }} items — tap to check</summary>
                    @foreach ($o->items as $it)
                        @php [$dish, $extra] = \App\Filament\Resources\OrderResource::splitItemName((string) $it->name); @endphp
                        <div style="padding:3px 0"><b>{{ $it->quantity }}×</b> {{ $dish }}@if ($extra)<span style="opacity:.7"> · {{ $extra }}</span>@endif</div>
                    @endforeach
                </details>
                @if ($o->special_instructions)<div class="md-note">📝 {{ $o->special_instructions }}</div>@endif

                @if ($o->status !== 'delivered')
                    @if ($due > 0)
                        <div class="md-collect due"><span>COLLECT<br><small style="font-weight:700">Cash on delivery</small></span><span class="amt">{{ $m($due) }}</span></div>
                    @else
                        <div class="md-collect paid"><span>✅ PAID — collect nothing<br><small style="font-weight:700">{{ $o->settleLabel() }}</small></span><span class="amt">{{ $m($o->total) }}</span></div>
                    @endif

                    @if (! $otw)
                        <button type="button" class="md-btn big go" style="width:100%;margin-top:12px" wire:click="pickedUp({{ $o->id }})">🛵 Picked up — start delivery</button>
                    @endif
                    @if ($due > 0)
                        <div class="md-row">
                            <button type="button" class="md-btn cash" wire:click="collected({{ $o->id }}, 'cash')" wire:confirm="Took {{ $m($due) }} cash from the customer?">💵 Cash</button>
                            <button type="button" class="md-btn" wire:click="showUpi({{ $o->id }})">📱 Shop UPI</button>
                            <button type="button" class="md-btn online" wire:click="payOnline({{ $o->id }})">🌐 Pay online</button>
                        </div>
                    @endif
                    <button type="button" class="md-btn big done" style="width:100%;margin-top:10px{{ $due > 0 ? ';opacity:.45' : '' }}" wire:click="delivered({{ $o->id }})">✅ Delivered</button>
                @else
                    <div class="md-collect paid"><span>{{ $o->settleLabel() }}</span><span class="amt">{{ $m($o->total) }}</span></div>
                @endif
            </div>
        @empty
            <div class="md-empty">{{ $tab === 'todo' ? '🎉 No deliveries right now. New orders given to you will appear here.' : 'Nothing delivered yet today.' }}</div>
        @endforelse
        @if ($shopPhone && $isRider)<a class="md-btn" href="tel:{{ preg_replace('/[^\d+]/', '', $shopPhone) }}">☎️ Call the shop</a>@endif
    </div>

    {{-- Payment QR sheet --}}
    @if ($qr)
        <div class="md-sheet-bg" wire:key="qr-{{ $qr->id }}-{{ $qrKind }}" @if ($qrKind === 'online') wire:poll.5s="checkPayment" @endif>
            <div class="md-sheet">
                @php $payText = $qrKind === 'online' ? $qr->pay_link_url : $upiUri; @endphp
                <div style="font-size:13px;font-weight:800;color:#8A7A5C;text-transform:uppercase;letter-spacing:.05em">{{ $qrKind === 'online' ? '🌐 Pay online — UPI / card / netbanking' : '📱 Pay to shop UPI' }}</div>
                <div style="font-size:34px;font-weight:900;margin-top:4px">{{ $m($qr->total) }}</div>
                <div style="font-size:13px;color:#6B5A3E">{{ $qr->customer_name }} · #{{ substr($qr->order_number, -6) }}</div>
                <canvas x-data x-init="window.SPQR && SPQR.draw($el, @js($payText), 520)" aria-label="Payment QR code"></canvas>
                <div style="font-size:14px;font-weight:700;color:#3B2A12">Ask the customer to scan with any UPI app<br>(GPay, PhonePe, Paytm…)</div>

                @if ($qrKind === 'online')
                    <div style="margin:12px 0;padding:10px;border-radius:12px;background:#EDE9FE;color:#5B21B6;font-weight:800;font-size:14px">
                        ⏳ Waiting for payment… it turns green by itself when paid.
                    </div>
                    @php $ph = $digits($qr->customer_phone); @endphp
                    <div class="md-row" style="grid-template-columns:1fr 1fr">
                        @if (strlen($ph) === 10)
                            <a class="md-btn wa" target="_blank" rel="noopener" href="https://wa.me/91{{ $ph }}?text={{ rawurlencode('Pay ' . $m($qr->total) . ' for your Swaad E Punjab order ' . $qr->order_number . ': ' . $qr->pay_link_url) }}">💬 Send link</a>
                        @else
                            <span></span>
                        @endif
                        <button type="button" class="md-btn" wire:click="checkPayment(true)">🔄 Check payment</button>
                    </div>
                @else
                    <div style="margin:12px 0;padding:10px;border-radius:12px;background:#FEF3C7;color:#92400E;font-weight:700;font-size:13.5px">
                        Check the customer's phone shows <b>Payment successful</b> to {{ \App\Models\ReceiptSetting::query()->value('shop_upi_name') ?: 'the shop' }}, then tap below.
                    </div>
                    <button type="button" class="md-btn big done" style="width:100%" wire:click="collected({{ $qr->id }}, 'upi')">✅ Customer paid {{ $m($qr->total) }}</button>
                @endif
                <button type="button" class="md-btn" style="width:100%;margin-top:10px" wire:click="closeQr">Close</button>
            </div>
        </div>
    @endif
</x-filament-panels::page>
