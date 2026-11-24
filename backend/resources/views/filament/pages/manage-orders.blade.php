@php
    $m = fn ($v) => '₹' . number_format((float) $v, fmod((float) $v, 1) ? 2 : 0);
    $tz = config('app.timezone');
    $short = fn ($o) => strlen($o->order_number) > 4 ? substr($o->order_number, -4) : $o->order_number;
    $typeTag = fn ($o) => match ($o->type()) {
        'takeaway' => ['🛍️ TAKE AWAY', 'background:#FEF3C7;color:#92400E'],
        'dine_in'  => ['🍽️ DINE IN' . ($o->table_number ? ' · T' . $o->table_number : ''), 'background:#DCFCE7;color:#166534'],
        default    => ['🛵 DELIVERY', 'background:#DBEAFE;color:#1E40AF'],
    };
    $statusTag = fn ($o) => match ($o->status) {
        'pending' => ['NEW', '#DC2626'], 'accepted' => ['PREPARING', '#D97706'], 'preparing' => ['READY', '#16A34A'],
        'out_for_delivery' => ['PICKED UP', '#2563EB'], 'delivered' => [match ($o->type()) { 'takeaway' => 'HANDED OVER', 'dine_in' => 'COMPLETED', default => 'DELIVERED' }, '#15803D'],
        'cancelled' => ['CANCELLED', '#6B7280'], default => [strtoupper($o->status), '#6B7280'],
    };
@endphp
<x-filament-panels::page>
    <style>
        .mo { display:grid; grid-template-columns: 370px minmax(0,1fr); gap:0; background:#fff; border:1px solid #EEE3CF; border-radius:18px; overflow:hidden; height: calc(100vh - 170px); min-height: 560px; }
        .mo-left { border-right:1px solid #EEE3CF; display:flex; flex-direction:column; min-height:0; background:#FBF8F1; }
        .mo-tabs { display:flex; flex-wrap:wrap; border-bottom:1px solid #EEE3CF; background:#fff; }
        .mo-tab { flex:1 1 auto; padding:12px 9px 10px; border:0; background:none; font:inherit; font-size:13.5px; font-weight:700; color:#5B4A30; cursor:pointer; border-bottom:3px solid transparent; white-space:nowrap; }
        .mo-tab.on { color:#EA580C; border-bottom-color:#EA580C; }
        .mo-tab .n { display:inline-block; min-width:20px; padding:0 6px; height:20px; line-height:20px; border-radius:10px; font-size:11.5px; margin-left:4px; background:#F3EAD6; color:#5B4A30; }
        .mo-tab.on .n, .mo-tab .n.hot { background:#EA580C; color:#fff; }
        .mo-search { padding:10px 12px; }
        .mo-search input { width:100%; height:42px; border-radius:10px; border:1px solid #E5D9C3; background:#fff; padding:0 12px 0 36px; font:inherit; font-size:14px; }
        .mo-list { flex:1; overflow-y:auto; padding:0 12px 12px; overscroll-behavior:contain; }
        .mo-card { display:block; width:100%; text-align:left; background:#fff; border:1px solid #EEE3CF; border-radius:14px; padding:12px 14px; margin-bottom:10px; cursor:pointer; font:inherit; color:inherit; }
        .mo-card:hover { border-color:#F3C77B; }
        .mo-card.on { border:2px solid #EA580C; box-shadow:0 4px 14px rgba(234,88,12,.12); }
        .mo-card .no { font-size:20px; font-weight:900; }
        .mo-chip { display:inline-block; font-size:10.5px; font-weight:900; padding:3px 7px; border-radius:5px; color:#fff; letter-spacing:.03em; }
        .mo-line { font-size:13px; color:#3B2A12; margin-top:6px; line-height:1.35; }
        .mo-more { color:#EA580C; font-weight:800; font-size:12px; }
        .mo-foot { display:flex; justify-content:space-between; align-items:center; border-top:1px dashed #E5D9C3; margin-top:10px; padding-top:8px; font-size:13px; font-weight:800; }
        .mo-right { overflow-y:auto; min-height:0; background:#F7F3EA; overscroll-behavior:contain; }
        .mo-head { display:flex; flex-wrap:wrap; gap:12px; align-items:center; justify-content:space-between; background:#fff; padding:16px 20px; border-bottom:1px solid #EEE3CF; position:sticky; top:0; z-index:5; }
        .mo-big { font-size:24px; font-weight:900; }
        .mo-big span { color:#EA580C; }
        .mo-sub { font-size:13px; color:#6B5A3E; font-weight:600; margin-top:2px; }
        .mo-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:42px; padding:0 16px; border-radius:10px; border:1px solid #E5D9C3; background:#fff; font:inherit; font-weight:800; font-size:14px; color:#1A0F00; cursor:pointer; text-decoration:none; }
        .mo-btn.orange { background:#EA580C; border-color:#EA580C; color:#fff; }
        .mo-btn.soft { background:#FFF1E7; border-color:#FED7AA; color:#C2410C; }
        .mo-btn.green { background:#16A34A; border-color:#15803D; color:#fff; }
        .mo-btn.blue { background:#2563EB; border-color:#1D4ED8; color:#fff; }
        .mo-btn.amber { background:#F59E0B; border-color:#D97706; color:#1A0F00; }
        .mo-btn.red { background:#fff; border-color:#FECACA; color:#DC2626; }
        .mo-body { display:grid; grid-template-columns: minmax(0,1fr) 300px; gap:16px; padding:16px 20px 24px; }
        @media (max-width: 1280px) { .mo-body { grid-template-columns: 1fr; } }
        .mo-box { background:#fff; border:1px solid #EEE3CF; border-radius:14px; padding:14px 16px; }
        .mo-box h4 { margin:0 0 10px; font-size:13px; font-weight:900; color:#8A7A5C; text-transform:uppercase; letter-spacing:.05em; }
        .mo-tl { display:flex; align-items:flex-start; gap:6px; }
        .mo-tl .st { flex:1; text-align:center; font-size:12.5px; font-weight:800; color:#A8987A; position:relative; }
        .mo-tl .st .dot { width:26px; height:26px; border-radius:13px; margin:0 auto 6px; display:flex; align-items:center; justify-content:center; background:#EEE3CF; color:#fff; font-size:13px; }
        .mo-tl .st.done { color:#1A0F00; } .mo-tl .st.done .dot { background:#16A34A; }
        .mo-tl .st.now .dot { background:#EA580C; box-shadow:0 0 0 4px #FED7AA; }
        .mo-tl .st small { display:block; font-weight:700; color:#6B5A3E; font-size:11.5px; margin-top:2px; }
        .mo-tl .bar { flex:0 0 22px; border-top:2px dashed #E5D9C3; margin-top:13px; }
        .mo-item { display:flex; justify-content:space-between; gap:10px; padding:10px 0; border-bottom:1px solid #F5EEDF; }
        .mo-item:last-child { border-bottom:0; }
        .mo-item .nm { font-weight:800; font-size:15px; }
        .mo-item .ex { font-size:12.5px; color:#8A7A5C; margin-top:2px; }
        .mo-item .q { font-weight:900; font-size:16px; white-space:nowrap; text-align:right; }
        .mo-item .q small { display:block; font-weight:700; color:#8A7A5C; font-size:12px; }
        .mo-row { display:flex; justify-content:space-between; font-size:14px; padding:4px 0; }
        .mo-row.total { font-size:18px; font-weight:900; border-top:1px solid #EEE3CF; margin-top:6px; padding-top:10px; }
        .mo-timer { font-weight:900; border-radius:10px; padding:8px 12px; font-size:15px; }
        .mo-timer.ok { background:#F1F5F9; color:#334155; } .mo-timer.warn { background:#FEF3C7; color:#92400E; } .mo-timer.late { background:#FEE2E2; color:#B91C1C; }
        .mo-empty { text-align:center; padding:40px 16px; color:#A8987A; font-weight:700; }
        .mo-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .mo-backbtn { display:none; }
        @media (max-width: 900px) {
            .mo { grid-template-columns: 1fr; height:auto; }
            .mo-left { border-right:0; max-height:none; }
            .mo-list { max-height:none; overflow:visible; }
            .mo.has-sel .mo-left { display:none; }
            .mo:not(.has-sel) .mo-right { display:none; }
            .mo-backbtn { display:inline-flex; }
            .mo-head { position:static; }
        }
        .dark .mo, .dark .mo-tabs, .dark .mo-card, .dark .mo-head, .dark .mo-box, .dark .mo-btn:not(.orange):not(.green):not(.blue):not(.amber), .dark .mo-search input { background:#1f2937; border-color:#374151; color:#f3f4f6; }
        .dark .mo-left, .dark .mo-right { background:#111827; } .dark .mo-line, .dark .mo-tab { color:#e5e7eb; }
    </style>

    <div class="mo {{ $this->selected ? 'has-sel' : '' }}" wire:poll.8s
         x-data="{ now: Math.floor(Date.now() / 1000) }" x-init="setInterval(() => now = Math.floor(Date.now() / 1000), 1000)">

        {{-- ================= LEFT: tabs + cards ================= --}}
        <div class="mo-left">
            <div class="mo-tabs" role="tablist">
                @foreach (\App\Filament\Pages\ManageOrders::TABS as $k => [$label])
                    <button type="button" role="tab" class="mo-tab {{ $tab === $k ? 'on' : '' }}" wire:click="setTab('{{ $k }}')">
                        {{ $label }}@if ($counts[$k])<span class="n {{ $k === 'new' && $counts[$k] ? 'hot' : '' }}">{{ $counts[$k] }}</span>@endif
                    </button>
                @endforeach
            </div>
            <div class="mo-search" style="position:relative">
                <span style="position:absolute;left:24px;top:21px;opacity:.5">🔍</span>
                <input type="search" placeholder="Search order no, name or phone" wire:model.live.debounce.300ms="search">
            </div>
            <div class="mo-list">
                @forelse ($list as $o)
                    @php [$tt, $tc] = $typeTag($o); [$sl, $sc] = $statusTag($o); $t = $o->timer(); $first = $o->items->first(); @endphp
                    <button type="button" class="mo-card {{ $sel && $sel->id === $o->id ? 'on' : '' }}" wire:click="select({{ $o->id }})" wire:key="c{{ $o->id }}">
                        <div style="display:flex;justify-content:space-between;align-items:center">
                            <span class="no">#{{ $short($o) }}</span>
                            <span style="font-size:12px;font-weight:700;color:#8A7A5C">{{ $o->created_at->timezone($tz)->format('h:i A') }}</span>
                        </div>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
                            <span class="mo-chip" style="background:{{ $sc }}">{{ $sl }}</span>
                            <span class="mo-chip" style="{{ $tc }}">{{ $tt }}</span>
                            @if ($o->isPaidViaRazorpay())<span class="mo-chip" style="background:#DCFCE7;color:#15803D">PAID</span>@endif
                        </div>
                        <div class="mo-line">
                            <b>{{ $m($o->total) }}</b> · {{ $o->customer_name }}<br>
                            @if ($first){{ \App\Filament\Resources\OrderResource::splitItemName((string) $first->name)[0] }} × {{ $first->quantity }}@endif
                            @if ($o->items->count() > 1) <span class="mo-more">+ {{ $o->items->count() - 1 }} MORE</span>@endif
                        </div>
                        @if ($t || $o->status === 'cancelled' || ($o->type() === 'delivery' && $o->rider_name))
                            <div class="mo-foot">
                                @if ($o->status === 'cancelled')
                                    <span style="color:#6B7280">{{ \Illuminate\Support\Str::limit($o->cancelledByLabel() ?: 'Cancelled', 40) }}</span>
                                @elseif ($t)
                                    <span style="color:{{ $t['state'] === 'late' ? '#DC2626' : ($t['state'] === 'warn' ? '#D97706' : '#15803D') }}">{{ $t['state'] === 'late' ? '👎' : '👍' }} {{ \Illuminate\Support\Str::limit(ltrim($t['text'], '⏱🔴 '), 34) }}</span>
                                @endif
                                @if ($o->type() === 'delivery' && $o->rider_name)<span style="color:#1D4ED8">🛵 {{ strtok($o->rider_name, ' ') }}</span>@endif
                            </div>
                        @endif
                    </button>
                @empty
                    <div class="mo-empty">{{ $search !== '' ? 'No order matches “' . $search . '”' : match ($tab) { 'new' => '🎉 No new orders right now', 'past' => 'No finished orders today', default => 'Nothing here' } }}</div>
                @endforelse
            </div>
        </div>

        {{-- ================= RIGHT: full details of the selected order ================= --}}
        <div class="mo-right">
            @if ($sel)
                @php
                    [$tt, $tc] = $typeTag($sel); [$sl, $sc] = $statusTag($sel); $t = $sel->timer(); $step = $sel->nextStep(); $type = $sel->type();
                    $at = fn ($d) => $d ? $d->timezone($tz)->format('h:i A') : null;
                    $steps = array_values(array_filter([
                        ['Placed', $at($sel->created_at), true],
                        ['Accepted', $at($sel->accepted_at), in_array($sel->status, ['accepted', 'preparing', 'out_for_delivery', 'delivered'], true)],
                        [match ($type) { 'takeaway' => 'Ready', 'dine_in' => 'Served', default => 'Food ready' }, $at($sel->ready_at), in_array($sel->status, ['preparing', 'out_for_delivery', 'delivered'], true)],
                        $type === 'delivery' ? ['Picked up', $at($sel->picked_up_at), in_array($sel->status, ['out_for_delivery', 'delivered'], true)] : null,
                        [match ($type) { 'takeaway' => 'Handed over', 'dine_in' => 'Completed', default => 'Delivered' }, $at($sel->delivered_at), $sel->status === 'delivered'],
                    ]));
                    $nowIdx = collect($steps)->search(fn ($s) => ! $s[2]);
                    $disc = (float) $sel->discount + (float) $sel->coupon_discount + (float) $sel->points_discount;
                @endphp
                <div class="mo-head" wire:key="h{{ $sel->id }}">
                    <div style="display:flex;align-items:center;gap:12px">
                        <button type="button" class="mo-btn mo-backbtn" wire:click="back">‹ Back</button>
                        <div>
                            <div class="mo-big">#{{ strlen($sel->order_number) > 4 ? substr($sel->order_number, 0, -4) : '' }}<span>{{ $short($sel) }}</span>
                                <span class="mo-chip" style="background:{{ $sc }};vertical-align:middle;font-size:12px">{{ $sl }}</span>
                                <span class="mo-chip" style="{{ $tc }};vertical-align:middle;font-size:12px">{{ $tt }}</span>
                            </div>
                            <div class="mo-sub">{{ $sel->created_at->timezone($tz)->format('d M, h:i A') }} · {{ (int) $sel->items->sum('quantity') }} items · {{ $m($sel->total) }} · {{ $sel->settleLabel() }}</div>
                        </div>
                    </div>
                    <div class="mo-actions">
                        <button type="button" class="mo-btn orange" onclick="window.open('{{ $printUrl($sel, 'kot') }}', '_blank', 'width=420,height=700')">🔥 Print KOT</button>
                        <button type="button" class="mo-btn soft" onclick="window.open('{{ $printUrl($sel, 'bill') }}', '_blank', 'width=420,height=700')">🧾 Print Bill</button>
                        <a class="mo-btn" href="{{ $viewUrl($sel) }}" title="Open the full order page (edit, refund, rating link…)">⋯ Full page</a>
                    </div>
                </div>

                <div class="mo-body">
                    <div style="display:flex;flex-direction:column;gap:16px;min-width:0">
                        {{-- what to do now --}}
                        @if ($sel->status === 'pending' || $step || ($t && $sel->status !== 'cancelled'))
                            <div class="mo-box" style="border:2px solid #FED7AA">
                                @if ($t)
                                    <div class="mo-timer {{ $t['state'] }}" style="margin-bottom:12px" @if ($t['due']) x-bind:class="(due - now) <= 180 ? 'late' : 'ok'" @endif
                                         @if ($t['due']) x-data="{ due: {{ $t['due'] }} }" @endif>
                                        @if ($t['due'])
                                            <span x-text="(due - now) >= 0 ? '⏱ ' + Math.floor((due - now) / 60) + ':' + String((due - now) % 60).padStart(2, '0') + ' left to get it ready' : '🔴 ' + Math.ceil((now - due) / 60) + ' min late'">{{ $t['text'] }}</span>
                                        @else
                                            {{ $t['text'] }}
                                        @endif
                                    </div>
                                @endif
                                @if ($sel->status === 'pending')
                                    <div style="font-weight:900;margin-bottom:8px">Accept — food ready in:</div>
                                    <div class="mo-actions">
                                        @foreach (\App\Models\Order::PREP_CHOICES as $min)
                                            <button type="button" class="mo-btn green" style="min-width:72px" wire:click="accept({{ $sel->id }}, {{ $min }})">{{ $min }} min</button>
                                        @endforeach
                                        <div x-data="{ rj: false }" style="position:relative">
                                            <button type="button" class="mo-btn red" x-on:click="rj = !rj">Reject ▾</button>
                                            <div x-show="rj" x-cloak x-on:click.outside="rj = false" style="position:absolute;right:0;top:calc(100% + 4px);z-index:30;min-width:260px;background:#fff;border:1px solid #FECACA;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.18);padding:6px">
                                                @foreach (\App\Models\Order::REJECT_REASONS as $rk => $rl)
                                                    <button type="button" wire:click="reject({{ $sel->id }}, '{{ $rk }}')" x-on:click="rj = false" style="display:block;width:100%;text-align:left;padding:10px;border:0;background:none;border-radius:8px;font:inherit;font-weight:700;cursor:pointer;color:#1e293b">{{ $rl }}</button>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @elseif ($step)
                                    <div class="mo-actions">
                                        <button type="button" class="mo-btn {{ match ($step['color']) { 'warning' => 'amber', 'info' => 'blue', default => 'green' } }}" style="min-height:52px;font-size:17px;padding:0 26px" wire:click="next({{ $sel->id }})" wire:loading.attr="disabled">{{ $step['label'] }} →</button>
                                        @if ($sel->status === 'accepted')
                                            <div x-data="{ rj: false }" style="position:relative">
                                                <button type="button" class="mo-btn red" x-on:click="rj = !rj">Cancel order ▾</button>
                                                <div x-show="rj" x-cloak x-on:click.outside="rj = false" style="position:absolute;left:0;top:calc(100% + 4px);z-index:30;min-width:260px;background:#fff;border:1px solid #FECACA;border-radius:12px;box-shadow:0 12px 30px rgba(0,0,0,.18);padding:6px">
                                                    @foreach (\App\Models\Order::REJECT_REASONS as $rk => $rl)
                                                        <button type="button" wire:click="reject({{ $sel->id }}, '{{ $rk }}')" x-on:click="rj = false" style="display:block;width:100%;text-align:left;padding:10px;border:0;background:none;border-radius:8px;font:inherit;font-weight:700;cursor:pointer;color:#1e293b">{{ $rl }}</button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- timeline --}}
                        @if ($sel->status !== 'cancelled')
                            <div class="mo-box">
                                <div class="mo-tl">
                                    @foreach ($steps as $i => [$label, $time, $done])
                                        @if ($i)<div class="bar"></div>@endif
                                        <div class="st {{ $done ? 'done' : ($nowIdx === $i ? 'now' : '') }}">
                                            <div class="dot">{{ $done ? '✓' : '' }}</div>{{ $label }}<small>{{ $done ? ($time ?: '✓') : '' }}</small>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="mo-box" style="border-color:#FECACA;background:#FEF2F2;color:#B91C1C;font-weight:800">❌ {{ $sel->cancelledByLabel() ?: 'Cancelled' }}</div>
                        @endif

                        {{-- items --}}
                        <div class="mo-box">
                            @if ($sel->special_instructions)
                                <div style="background:#FEF3C7;color:#92400E;border-radius:10px;padding:8px 12px;font-weight:800;margin-bottom:8px">📝 {{ $sel->special_instructions }}</div>
                            @endif
                            @foreach ($sel->items as $it)
                                @php [$dish, $extra] = \App\Filament\Resources\OrderResource::splitItemName((string) $it->name); @endphp
                                <div class="mo-item">
                                    <div><div class="nm">{{ $dish }}@if ($it->is_free) <span style="color:#15803D;font-size:12px">FREE</span>@endif</div>@if ($extra)<div class="ex">{{ $extra }}</div>@endif</div>
                                    <div class="q">× {{ $it->quantity }}<small>{{ $m($it->subtotal) }}</small></div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- side: customer, rider, bill --}}
                    <div style="display:flex;flex-direction:column;gap:16px">
                        <div class="mo-box">
                            <h4>👤 Customer</h4>
                            <div style="font-weight:900;font-size:16px">{{ $sel->customer_name }}</div>
                            @php $ph = substr(preg_replace('/\D/', '', (string) $sel->customer_phone), -10); @endphp
                            @if (strlen($ph) === 10)
                                <div style="margin-top:4px"><a href="tel:+91{{ $ph }}" style="font-weight:800;color:#15803D">📞 {{ $ph }}</a></div>
                                <div style="font-size:13px;color:#6B5A3E;margin-top:6px">🛍️ {{ $history }} order{{ $history == 1 ? '' : 's' }} in the last 30 days{{ $history <= 1 ? ' · new customer' : '' }}</div>
                            @endif
                            @if ($type === 'delivery')
                                <div style="font-size:13.5px;margin-top:8px;line-height:1.4">📍 {{ $sel->delivery_address }}{{ $sel->city && $sel->city !== 'Counter' ? ', ' . $sel->city : '' }}</div>
                                <a href="{{ $sel->mapsUrl() }}" target="_blank" rel="noopener" style="font-size:13px;font-weight:800;color:#2563EB">🗺️ Open map{{ $sel->distance_km ? ' · ' . $sel->distance_km . ' km' : '' }}</a>
                            @elseif ($type === 'dine_in' && $sel->table_number)
                                <div style="margin-top:6px;font-weight:800">🍽️ Table {{ $sel->table_number }}</div>
                            @endif
                        </div>

                        @if ($type === 'delivery' && ! in_array($sel->status, ['cancelled'], true))
                            <div class="mo-box">
                                <h4>🛵 Delivery boy</h4>
                                @if ($sel->status === 'delivered')
                                    <div style="font-weight:800">{{ $sel->rider_name ?: '—' }} delivered the order</div>
                                @elseif ($riders)
                                    <select class="mo-btn" style="width:100%;font-weight:700" wire:change="assignRider({{ $sel->id }}, $event.target.value)">
                                        <option value="">{{ $sel->rider_id ? '— remove —' : '— choose delivery boy —' }}</option>
                                        @foreach ($riders as $rid => $rname)
                                            <option value="{{ $rid }}" @selected($sel->rider_id == $rid)>{{ $rname }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    <div style="font-size:13px;color:#8A7A5C">No delivery boys yet — add one in Staff accounts (role “Delivery Boy”).</div>
                                @endif
                            </div>
                        @endif

                        <div class="mo-box">
                            <h4>🧾 Bill details</h4>
                            <div class="mo-row"><span>Item total ({{ (int) $sel->items->sum('quantity') }} items)</span><span>{{ $m($sel->subtotal) }}</span></div>
                            @if ($disc > 0)<div class="mo-row" style="color:#15803D"><span>Discount{{ $sel->offer_title ? ' · ' . $sel->offer_title : ($sel->coupon_code ? ' · ' . $sel->coupon_code : '') }}</span><span>−{{ $m($disc) }}</span></div>@endif
                            @if ((float) $sel->delivery_fee > 0)<div class="mo-row"><span>Delivery fee</span><span>{{ $m($sel->delivery_fee) }}</span></div>@endif
                            @if ((float) $sel->gst_amount > 0)<div class="mo-row"><span>GST {{ rtrim(rtrim(number_format((float) $sel->gst_rate, 2), '0'), '.') }}%{{ $sel->gst_mode === 'inclusive' ? ' (included)' : '' }}</span><span>{{ $sel->gst_mode === 'inclusive' ? '' : '+' }}{{ $m($sel->gst_amount) }}</span></div>@endif
                            <div class="mo-row total"><span>Bill total</span><span>{{ $m($sel->total) }}</span></div>
                            <div style="margin-top:10px;font-size:13.5px;font-weight:800;color:{{ $sel->isSettled() ? '#15803D' : '#B91C1C' }}">{{ $sel->settleLabel() }}</div>
                            @if ($sel->needsSettlement() && $sel->status !== 'pending')
                                <div class="mo-actions" style="margin-top:8px">
                                    <span style="font-size:12.5px;color:#6B5A3E;font-weight:700;width:100%">Settle bill as:</span>
                                    <button type="button" class="mo-btn" wire:click="settle({{ $sel->id }}, 'cash')" wire:confirm="Settle {{ $m($sel->total) }} as CASH?">💵 Cash</button>
                                    <button type="button" class="mo-btn" wire:click="settle({{ $sel->id }}, 'upi')" wire:confirm="Settle {{ $m($sel->total) }} as UPI?">📱 UPI</button>
                                    <button type="button" class="mo-btn" wire:click="settle({{ $sel->id }}, 'card')" wire:confirm="Settle {{ $m($sel->total) }} as CARD?">💳 Card</button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @else
                <div class="mo-empty" style="padding-top:120px">👈 Choose an order to see its details</div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
