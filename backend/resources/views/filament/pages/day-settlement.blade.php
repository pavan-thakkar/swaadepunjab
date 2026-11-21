@php
    $m = fn ($v) => '₹' . number_format((float) $v, 2);
    $modes = ['cash' => ['💵', 'Cash', '#16A34A'], 'upi' => ['📱', 'UPI', '#2563EB'], 'card' => ['💳', 'Card', '#D97706'], 'online' => ['🌐', 'Online (website)', '#7C3AED']];
@endphp
<x-filament-panels::page>
    <style>
        .nh-bar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
        .nh-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:10px; border:1px solid #E5D9C3; background:#fff; font-weight:700; font-size:14px; cursor:pointer; color:#3B2A12; text-decoration:none; }
        .nh-btn:hover { background:#FFF8E7; }
        .nh-btn.primary { background:#E6A817; border-color:#C98F0F; color:#1A0F00; }
        .nh-btn.sm { padding:5px 10px; font-size:12.5px; border-radius:8px; }
        .nh-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:12px; }
        .nh-card { background:#fff; border:1px solid #EEE3CF; border-radius:16px; padding:14px 16px; }
        .nh-card .k { font-size:12px; font-weight:800; color:#8A7A5C; text-transform:uppercase; letter-spacing:.05em; }
        .nh-card .v { font-size:26px; font-weight:900; margin-top:2px; }
        .nh-card .s { font-size:12px; color:#8A7A5C; font-weight:600; }
        .nh-total { background:linear-gradient(135deg,#2C1A00,#5A3500); color:#FFF6E0; border:0; }
        .nh-total .k, .nh-total .s { color:#F3D68A; }
        .nh-sec { background:#fff; border:1px solid #EEE3CF; border-radius:16px; padding:16px; }
        .nh-sec h3 { font-size:16px; font-weight:900; margin:0 0 10px; display:flex; align-items:center; gap:8px; }
        .nh-tbl { width:100%; border-collapse:collapse; font-size:14px; }
        .nh-tbl th { text-align:left; font-size:12px; color:#8A7A5C; text-transform:uppercase; letter-spacing:.04em; padding:8px; border-bottom:1px solid #EEE3CF; }
        .nh-tbl td { padding:9px 8px; border-bottom:1px solid #F5EEDF; vertical-align:middle; }
        .nh-tbl td.r, .nh-tbl th.r { text-align:right; white-space:nowrap; }
        .nh-in { width:100%; padding:10px 12px; border:1px solid #E5D9C3; border-radius:10px; font-weight:800; font-size:18px; }
        .nh-in:disabled { background:#F9F6EF; color:#6B5A3E; }
        .nh-lbl { font-size:13px; font-weight:800; color:#3B2A12; margin-bottom:4px; display:block; }
        .nh-help { font-size:12px; color:#8A7A5C; margin-top:3px; }
        .nh-match { border-radius:14px; padding:14px 16px; font-weight:900; font-size:18px; }
        .nh-ok { background:#F0FDF4; color:#15803D; border:1px solid #BBF7D0; }
        .nh-bad { background:#FEF2F2; color:#B91C1C; border:1px solid #FECACA; }
        .nh-warn { background:#FFFBEB; color:#92400E; border:1px solid #FDE68A; }
        .nh-two { display:grid; grid-template-columns: 1.2fr 1fr; gap:16px; }
        @media (max-width: 1000px) { .nh-two { grid-template-columns: 1fr; } }
        .dark .nh-card:not(.nh-total), .dark .nh-sec, .dark .nh-btn, .dark .nh-in { background:#1f2937 !important; border-color:#374151 !important; color:#f3f4f6; }
        .dark .nh-lbl { color:#e5e7eb; }
        .dark .nh-help, .dark .nh-card .k, .dark .nh-card .s, .dark .nh-tbl th { color:#9ca3af; }
        .dark .nh-tbl td { border-color:#374151; color:#e5e7eb; }
        .dark .nh-exp { background:#3a2a0a !important; color:#fde68a !important; }
        .dark .nh-exp .nh-help { color:#d6c79a; }
        .dark .nh-in:disabled { background:#111827 !important; color:#d1d5db; }
        .dark .nh-ok { background:#052e16; color:#86efac; border-color:#166534; }
        .dark .nh-bad { background:#450a0a; color:#fca5a5; border-color:#7f1d1d; }
        .dark .nh-warn { background:#422006; color:#fcd34d; border-color:#78350f; }
    </style>

    {{-- Day picker --}}
    <div class="nh-bar">
        <button type="button" class="nh-btn" wire:click="shift(-1)">‹ Previous day</button>
        <input type="date" wire:model.live="date" class="nh-btn" style="font-weight:800">
        <button type="button" class="nh-btn" wire:click="shift(1)">Next day ›</button>
        @unless ($isToday)<button type="button" class="nh-btn" wire:click="goToday">Today</button>@endunless
        <span style="font-size:12.5px;color:#8A7A5C;font-weight:600">{{ $start->format('d M, h:i A') }} → {{ $end->copy()->subMinute()->format('d M, h:i A') }}</span>
        <span style="flex:1"></span>
        @if ($closing)
            <span class="nh-btn" style="cursor:default;background:#F0FDF4;border-color:#BBF7D0;color:#15803D">🔒 Closed by {{ $closing->closed_by_name }} · {{ $closing->updated_at->timezone(config('app.timezone'))->format('d M, h:i A') }}</span>
        @endif
        <a href="{{ $printUrl }}" target="_blank" rel="noopener" class="nh-btn primary">🖨️ Print hisab</a>
    </div>

    {{-- Totals by mode --}}
    <div class="nh-grid">
        <div class="nh-card nh-total">
            <div class="k">Total collected</div>
            <div class="v">{{ $m($collected) }}</div>
            <div class="s">{{ $bills }} bill{{ $bills == 1 ? '' : 's' }} settled</div>
        </div>
        @foreach ($modes as $k => [$ic, $label, $col])
            <div class="nh-card" style="border-left:5px solid {{ $col }}">
                <div class="k">{{ $ic }} {{ $label }}</div>
                <div class="v" style="color:{{ $col }}">{{ $m($totals[$k]) }}</div>
                <div class="s">{{ $collected > 0 ? round($totals[$k] * 100 / $collected) : 0 }}% of today</div>
            </div>
        @endforeach
    </div>
    @if ($refunds > 0 || $cancelled > 0)
        <div style="font-size:13px;color:#6B5A3E;font-weight:600;margin-top:-8px">
            @if ($refunds > 0)↩️ Refunded online today: <b>{{ $m($refunds) }}</b> (goes back from Razorpay, not from the drawer). @endif
            @if ($cancelled > 0) ❌ {{ $cancelled }} cancelled order{{ $cancelled == 1 ? '' : 's' }} (not counted). @endif
        </div>
    @endif

    {{-- Unsettled bills --}}
    @if ($unsettled->count() || $older_unsettled)
        <div class="nh-sec" style="border-color:#FECACA;background:#FFFBFB">
            @if ($unsettled->count())
                <h3 style="color:#B91C1C">⚠️ {{ $unsettled->count() }} bill{{ $unsettled->count() == 1 ? '' : 's' }} of this day not settled yet · {{ $m($unsettled_amount) }}</h3>
                <div class="nh-help" style="margin:-4px 0 10px">Settle these before closing the day, otherwise the hisab will not match. Tap how the customer paid.</div>
            @else
                <h3 style="color:#15803D">✅ Every bill of this day is settled</h3>
            @endif
            @if ($unsettled->count())
                <table class="nh-tbl">
                    <thead><tr><th>Bill</th><th>Customer</th><th>Type</th><th class="r">Amount</th><th class="r">Settle as</th></tr></thead>
                    <tbody>
                        @foreach ($unsettled as $o)
                            <tr wire:key="u{{ $o->id }}">
                                <td><a href="{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $o]) }}" style="font-weight:800;color:#8A5A00">{{ $o->order_number }}</a>
                                    <div class="nh-help">{{ $o->created_at->timezone(config('app.timezone'))->format('h:i A') }} · {{ $o->status_label }}</div></td>
                                <td>{{ $o->customer_name }}</td>
                                <td>{{ ['dine_in' => '🍽️ Dine in', 'takeaway' => '🛍️ Takeaway', 'delivery' => '🛵 Delivery'][$o->order_type] ?? $o->order_type }}</td>
                                <td class="r" style="font-weight:900">{{ $m($o->total) }}</td>
                                <td class="r">
                                    <button type="button" class="nh-btn sm" wire:click="quickSettle({{ $o->id }}, 'cash')">💵 Cash</button>
                                    <button type="button" class="nh-btn sm" wire:click="quickSettle({{ $o->id }}, 'upi')">📱 UPI</button>
                                    <button type="button" class="nh-btn sm" wire:click="quickSettle({{ $o->id }}, 'card')">💳 Card</button>
                                    <a class="nh-btn sm" href="{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $o]) }}" title="Split payment / note">➗ Split…</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            @if ($older_unsettled)
                <div class="nh-help" style="margin-top:10px;font-weight:700;color:#B91C1C">
                    + {{ $older_unsettled }} older unsettled bill{{ $older_unsettled == 1 ? '' : 's' }} from previous days —
                    <a href="{{ \App\Filament\Resources\OrderResource::getUrl('index', ['activeTab' => 'unsettled']) }}" style="text-decoration:underline">open Unsettled bills</a>
                </div>
            @endif
        </div>
    @else
        <div class="nh-match nh-ok">✅ Every bill of this day is settled</div>
    @endif

    <div class="nh-two">
        {{-- Cash count --}}
        <div class="nh-sec">
            <h3>🧮 Match the cash drawer</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label class="nh-lbl">Opening cash (morning float)</label>
                    <input type="number" step="0.01" min="0" class="nh-in" wire:model.live.debounce.400ms="opening_cash" @disabled($locked)>
                    <div class="nh-help">Filled from last night's counted cash</div>
                </div>
                <div>
                    <label class="nh-lbl">Cash paid out today</label>
                    <input type="number" step="0.01" min="0" class="nh-in" wire:model.live.debounce.400ms="expenses" @disabled($locked)>
                    <div class="nh-help">Vegetables, gas, staff advance… from the drawer</div>
                </div>
            </div>
            <div class="nh-exp" style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin:14px 0;padding:12px 14px;background:#FFF8E7;border-radius:12px;font-weight:800;color:#1A0F00">
                <span>Cash that should be in the drawer<div class="nh-help" style="font-weight:600">{{ $m($opening_cash ?: 0) }} + cash sales {{ $m($totals['cash']) }} − paid out {{ $m($expenses ?: 0) }}</div></span>
                <span style="font-size:24px;font-weight:900">{{ $m($expected) }}</span>
            </div>
            <label class="nh-lbl">Cash counted in drawer *</label>
            <input type="number" step="0.01" min="0" class="nh-in" style="font-size:22px" wire:model.live.debounce.400ms="counted_cash" placeholder="Count the notes and type the total" @disabled($locked)>
            @error('counted_cash')<div style="color:#DC2626;font-weight:700;font-size:13px;margin-top:4px">{{ $message }}</div>@enderror
            @if ($diff !== null)
                <div class="nh-match {{ $diff == 0 ? 'nh-ok' : ($diff > 0 ? 'nh-warn' : 'nh-bad') }}" style="margin-top:12px">
                    {{ $diff == 0 ? '✅ Cash matched' : ($diff > 0 ? '⬆️ Cash excess ' . $m($diff) : '⬇️ Cash short ' . $m(-$diff)) }}
                </div>
            @endif

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:16px">
                <div>
                    <label class="nh-lbl">UPI in bank / QR app (optional)</label>
                    <input type="number" step="0.01" min="0" class="nh-in" wire:model.live.debounce.400ms="upi_counted" placeholder="{{ number_format($totals['upi'], 2) }}" @disabled($locked)>
                    @if ($upiDiff !== null)<div class="nh-help" style="font-weight:800;color:{{ $upiDiff == 0 ? '#15803D' : '#B91C1C' }}">{{ $upiDiff == 0 ? '✅ UPI matched' : ($upiDiff > 0 ? 'UPI app shows ' . $m($upiDiff) . ' more' : 'UPI app shows ' . $m(-$upiDiff) . ' less') }}</div>@endif
                </div>
                <div>
                    <label class="nh-lbl">Card machine total (optional)</label>
                    <input type="number" step="0.01" min="0" class="nh-in" wire:model.live.debounce.400ms="card_counted" placeholder="{{ number_format($totals['card'], 2) }}" @disabled($locked)>
                    @if ($cardDiff !== null)<div class="nh-help" style="font-weight:800;color:{{ $cardDiff == 0 ? '#15803D' : '#B91C1C' }}">{{ $cardDiff == 0 ? '✅ Card matched' : ($cardDiff > 0 ? 'Machine shows ' . $m($cardDiff) . ' more' : 'Machine shows ' . $m(-$cardDiff) . ' less') }}</div>@endif
                </div>
            </div>
            <label class="nh-lbl" style="margin-top:14px">Note (optional)</label>
            <input type="text" class="nh-in" style="font-size:14px;font-weight:600" wire:model="note" maxlength="500" placeholder="e.g. ₹50 short — change given extra on table 4" @disabled($locked)>

            <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
                @if ($locked)
                    @if ($this->canReclose())
                        <button type="button" class="nh-btn" wire:click="startEdit">✏️ Change closed hisab</button>
                    @else
                        <span class="nh-help">This day is closed. Only the owner or manager can change it.</span>
                    @endif
                @else
                    <button type="button" class="nh-btn primary" style="font-size:16px;padding:12px 18px" wire:click="closeDay"
                        @if ($unsettled->count()) wire:confirm="{{ $unsettled->count() }} bill(s) are still unsettled. Close the day anyway?" @endif>
                        🔒 {{ $closing ? 'Save changes' : 'Close day & save hisab' }}
                    </button>
                @endif
            </div>
        </div>

        {{-- By staff --}}
        <div class="nh-sec">
            <h3>👥 Who collected what</h3>
            @if (empty($staff))
                <div class="nh-help">No settled bills on this day yet.</div>
            @else
                <table class="nh-tbl">
                    <thead><tr><th>Settled by</th><th class="r">Bills</th><th class="r">Cash</th><th class="r">UPI</th><th class="r">Card</th><th class="r">Online</th></tr></thead>
                    <tbody>
                        @foreach ($staff as $who => $r)
                            <tr><td style="font-weight:800">{{ $who }}</td><td class="r">{{ $r['bills'] }}</td>
                                <td class="r">{{ $r['cash'] ? $m($r['cash']) : '—' }}</td><td class="r">{{ $r['upi'] ? $m($r['upi']) : '—' }}</td>
                                <td class="r">{{ $r['card'] ? $m($r['card']) : '—' }}</td><td class="r">{{ $r['online'] ? $m($r['online']) : '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Every settled bill --}}
    <details class="nh-sec">
        <summary style="cursor:pointer;font-weight:900;font-size:16px">📜 All settled bills of this day ({{ $bills }})</summary>
        <table class="nh-tbl" style="margin-top:10px">
            <thead><tr><th>Time</th><th>Bill</th><th>Customer</th><th>Mode</th><th>By</th><th class="r">Amount</th></tr></thead>
            <tbody>
                @foreach ($settled->concat($online)->sortBy(fn ($o) => $o->settled_at ?? $o->paid_at ?? $o->created_at) as $o)
                    <tr wire:key="s{{ $o->id }}">
                        <td>{{ ($o->settled_at ?? $o->paid_at ?? $o->created_at)->timezone(config('app.timezone'))->format('h:i A') }}</td>
                        <td><a href="{{ \App\Filament\Resources\OrderResource::getUrl('view', ['record' => $o]) }}" style="font-weight:800;color:#8A5A00">{{ $o->order_number }}</a></td>
                        <td>{{ $o->customer_name }}</td>
                        <td>{{ $o->settleLabel() }}@if ($o->settle_mode === 'split')<div class="nh-help">{{ \App\Filament\Support\SettleBill::byLine($o) }}</div>@endif</td>
                        <td>{{ $o->settle_mode ? $o->settled_by_name : 'Website' }}</td>
                        <td class="r" style="font-weight:800">{{ $m($o->total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </details>
</x-filament-panels::page>
