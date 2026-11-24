@php
    $m = fn ($v) => '₹' . number_format((float) $v, fmod((float) $v, 1) ? 2 : 0);
    $tz = config('app.timezone');
    $last4 = fn ($o) => strlen($o->order_number) > 4 ? substr($o->order_number, -4) : $o->order_number;
    $head = fn ($o) => strlen($o->order_number) > 4 ? substr($o->order_number, 0, -4) : '';
    $typeTag = fn ($o) => match ($o->type()) {
        'takeaway' => ['TAKE AWAY', '#B45309'],
        'dine_in'  => ['DINE IN' . ($o->table_number ? ' · T' . $o->table_number : ''), '#047857'],
        default    => ['DELIVERY', '#1D4ED8'],
    };
    $statusTag = fn ($o) => match ($o->status) {
        'pending' => ['NEW', '#E23744'], 'accepted' => ['PREPARING', '#F59E0B'], 'preparing' => ['READY', '#0E9F6E'],
        'out_for_delivery' => ['PICKED UP', '#2563EB'],
        'delivered' => [match ($o->type()) { 'takeaway' => 'HANDED OVER', 'dine_in' => 'COMPLETED', default => 'DELIVERED' }, '#0E9F6E'],
        'cancelled' => ['CANCELLED', '#6B7280'], default => [strtoupper($o->status), '#6B7280'],
    };
    $initials = fn ($n) => collect(preg_split('/\s+/', trim((string) $n)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $mmss = fn ($secs) => sprintf('%02d:%02d', intdiv(max(0, (int) $secs), 60), max(0, (int) $secs) % 60);
    // Footer line on each card (Swiggy "Handover on time / delayed")
    $footer = function ($o) use ($mmss) {
        $now = now()->timestamp;
        switch ($o->status) {
            case 'pending':
                $s = $now - $o->created_at->timestamp;
                return [$s > 180 ? 'bad' : 'good', $s > 180 ? 'Waiting to accept' : 'New — accept now', $mmss($s) . ' MINS', null];
            case 'accepted':
                $due = $o->readyDueAt()?->timestamp ?? $now;
                return [$due - $now <= 180 ? 'bad' : 'good', $due - $now < 0 ? 'Food is late' : 'Food due in', $mmss(abs($due - $now)) . ' MINS', $due];
            case 'preparing':
                $s = $now - ($o->ready_at ?: $o->updated_at)->timestamp;
                $late = $o->type() !== 'dine_in' && $s > 600;
                return [$late ? 'bad' : 'good', $late ? 'Handover delayed' : ($o->type() === 'dine_in' ? 'Served' : 'Waiting for handover'), $mmss($s) . ' MINS', null];
            case 'out_for_delivery':
            case 'delivered':
                if ($o->accepted_at && $o->ready_at) {
                    $took = $o->ready_at->timestamp - $o->accepted_at->timestamp;
                    $ok = $took <= ((int) ($o->prep_time_minutes ?: 20)) * 60;
                    return [$ok ? 'good' : 'bad', $ok ? 'Handover on time' : 'Handover delayed', $mmss($took) . ' MINS', null];
                }
                return ['good', $o->status === 'delivered' ? 'Completed' : 'On the way', '', null];
        }
        return null;
    };
@endphp
<x-filament-panels::page>
    <style>
        .fi-header { display:none; } /* the dark bar below is the page header */
        /* use the whole screen: no empty band above, the order area fills the window height */
        .fi-page > section { padding-top:12px !important; padding-bottom:12px !important; row-gap:0 !important; }
        @media (min-width: 901px) {
            .sw { display:flex; flex-direction:column; height:calc(100vh - 4rem - 24px); min-height:560px; }
            .sw > .sw-main { flex:1 1 auto; height:auto !important; min-height:0 !important; }
        }
        .sw { --or:#FC8019; --or2:#F15700; font-size:14px; color:#282C3F; }
        .sw-top { display:flex; align-items:center; gap:18px; background:#282C3F; color:#fff; border-radius:16px 16px 0 0; padding:12px 18px; flex-wrap:wrap; }
        .sw-top h2 { margin:0; font-size:17px; font-weight:900; letter-spacing:.02em; }
        .sw-top .sep { width:1px; height:28px; background:rgba(255,255,255,.3); }
        .sw-switch { position:relative; width:52px; height:28px; border-radius:14px; border:0; cursor:pointer; background:#9CA3AF; transition:.2s; flex:0 0 auto; }
        .sw-switch.on { background:#22C55E; }
        .sw-switch::after { content:''; position:absolute; top:3px; left:3px; width:22px; height:22px; border-radius:11px; background:#fff; transition:.2s; box-shadow:0 1px 3px rgba(0,0,0,.3); }
        .sw-switch.on::after { left:27px; }
        .sw-outlet b { display:block; font-size:16px; font-weight:900; }
        .sw-outlet small { font-size:12px; opacity:.8; }
        .sw-tabs { display:flex; background:#fff; border-bottom:1px solid #E9E9EB; overflow-x:auto; scrollbar-width:none; }
        .sw-tab { padding:16px 22px 13px; border:0; background:none; font:inherit; font-size:15px; font-weight:600; color:#3D4152; cursor:pointer; border-bottom:3px solid transparent; white-space:nowrap; position:relative; }
        .sw-tab + .sw-tab::before { content:''; position:absolute; left:0; top:16px; bottom:16px; width:1px; background:#E9E9EB; }
        .sw-tab.on { color:var(--or2); border-bottom-color:var(--or2); font-weight:800; }
        .sw-tab .n { display:inline-flex; align-items:center; justify-content:center; min-width:22px; height:22px; padding:0 6px; border-radius:11px; background:var(--or2); color:#fff; font-size:12px; font-weight:800; margin-left:6px; }
        .sw-main { display:grid; grid-template-columns:300px minmax(0,1fr); height:calc(100vh - 230px); min-height:540px; background:#F1F1F6; border-radius:0 0 16px 16px; overflow:hidden; border:1px solid #E9E9EB; border-top:0; }
        .sw-left { background:#fff; border-right:1px solid #E9E9EB; display:flex; flex-direction:column; min-height:0; }
        .sw-online { display:flex; justify-content:space-between; align-items:center; padding:12px 14px; color:#fff; font-size:12px; font-weight:900; letter-spacing:.03em; }
        .sw-search { padding:12px; }
        .sw-search input { width:100%; box-sizing:border-box; height:40px; border-radius:8px; border:0; background:#F1F1F6; padding:0 12px 0 34px; font:inherit; font-size:13.5px; }
        .sw-list { flex:1; overflow-y:auto; padding:2px 12px 12px; background:#F1F1F6; overscroll-behavior:contain; }
        .sw-card { display:block; width:100%; text-align:left; background:#fff; border:1px solid #E9E9EB; border-radius:12px; padding:14px; margin-top:12px; cursor:pointer; font:inherit; color:inherit; }
        .sw-card.on { border:2px solid var(--or); box-shadow:0 6px 16px rgba(252,128,25,.15); }
        .sw-logo { width:30px; height:30px; border-radius:8px; background:var(--or); color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:15px; flex:0 0 auto; }
        .sw-no { font-size:20px; font-weight:900; }
        .sw-chip { display:inline-block; font-size:10.5px; font-weight:900; padding:3px 7px; border-radius:4px; color:#fff; letter-spacing:.04em; }
        .sw-meta { font-size:13px; margin-top:8px; line-height:1.4; color:#3D4152; }
        .sw-more { color:var(--or2); font-weight:900; font-size:12px; margin-top:4px; display:inline-block; }
        .sw-dash { border-top:1px dashed #D4D5D9; margin:12px 0; }
        .sw-person { display:flex; gap:10px; align-items:center; font-size:12.5px; color:#535665; text-transform:uppercase; font-weight:600; }
        .sw-avatar { width:34px; height:34px; border-radius:6px; background:#FFE8D6; color:var(--or2); display:inline-flex; align-items:center; justify-content:center; font-weight:900; font-size:13px; flex:0 0 auto; }
        .sw-foot { display:flex; justify-content:space-between; align-items:center; font-weight:800; font-size:14px; }
        .sw-foot .good { color:#1BA672; } .sw-foot .bad { color:#E23744; }
        .sw-foot small { color:#7E808C; font-weight:700; font-size:12px; }
        .sw-right { overflow-y:auto; min-height:0; overscroll-behavior:contain; }
        .sw-dhead { display:flex; flex-wrap:wrap; gap:12px; justify-content:space-between; align-items:center; background:#fff; padding:14px 20px; border-bottom:1px solid #E9E9EB; position:sticky; top:0; z-index:5; }
        .sw-dno { font-size:20px; font-weight:900; } .sw-dno span { color:#1BA672; }
        .sw-dsub { color:#7E808C; font-size:13.5px; margin-top:2px; }
        .sw-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:40px; padding:0 16px; border-radius:8px; border:0; font:inherit; font-weight:800; font-size:13.5px; cursor:pointer; text-decoration:none; background:#fff; color:#282C3F; border:1px solid #D4D5D9; }
        .sw-btn.kot { background:var(--or2); color:#fff; border-color:var(--or2); }
        .sw-btn.bill { background:#FFF0E6; color:var(--or2); border-color:#FFE0CC; }
        .sw-btn.go { min-height:50px; font-size:16px; padding:0 24px; color:#fff; }
        .sw-btn.green { background:#1BA672; border-color:#1BA672; color:#fff; }
        .sw-btn.red { color:#E23744; border-color:#F8C9CD; }
        .sw-grid { display:grid; grid-template-columns:minmax(0,1fr) 290px; gap:16px; padding:16px 20px 28px; }
        @media (max-width: 1380px) { .sw-grid { grid-template-columns:1fr; } }
        .sw-box { background:#fff; border:1px solid #E9E9EB; border-radius:12px; padding:16px 18px; }
        .sw-box h4 { margin:0 0 12px; font-size:15px; font-weight:900; }
        .sw-tl { display:flex; align-items:center; gap:8px; flex-wrap:wrap; row-gap:12px; }
        .sw-tl .st { display:flex; gap:8px; align-items:flex-start; font-size:13px; font-weight:700; color:#93959F; white-space:nowrap; }
        .sw-tl .st .ck { width:18px; height:18px; border-radius:9px; background:#D4D5D9; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:11px; margin-top:1px; }
        .sw-tl .st.done { color:#282C3F; } .sw-tl .st.done .ck { background:#282C3F; }
        .sw-tl .st.now .ck { background:var(--or); box-shadow:0 0 0 3px #FFE0CC; }
        .sw-tl .st b { display:block; font-weight:900; font-size:13px; color:#282C3F; }
        .sw-tl .arr { flex:1 1 16px; min-width:14px; max-width:90px; border-top:2px dotted #C1C2C7; position:relative; }
        .sw-tl .arr::after { content:'›'; position:absolute; right:-3px; top:-12px; color:#C1C2C7; font-weight:900; }
        .sw-pill { display:inline-flex; gap:6px; align-items:center; background:#F1F1F6; border:1px solid #E9E9EB; border-radius:6px; padding:6px 10px; font-size:13px; font-weight:700; color:#3D4152; margin-bottom:6px; }
        .sw-item { display:flex; justify-content:space-between; gap:12px; padding:12px 0; border-bottom:1px solid #F1F1F6; }
        .sw-item:last-child { border-bottom:0; }
        .sw-item .nm { font-size:16px; font-weight:700; display:flex; gap:8px; align-items:flex-start; }
        .sw-item .ct { font-size:12.5px; color:#7E808C; margin:2px 0 0 22px; }
        .sw-dotv { width:14px; height:14px; border:1.5px solid #1BA672; border-radius:3px; display:inline-flex; align-items:center; justify-content:center; margin-top:4px; flex:0 0 auto; }
        .sw-dotv::after { content:''; width:6px; height:6px; border-radius:3px; background:#1BA672; }
        .sw-item .q { text-align:right; white-space:nowrap; } .sw-item .q b { font-size:18px; } .sw-item .q small { display:block; color:#7E808C; font-size:12.5px; }
        .sw-row { display:flex; justify-content:space-between; padding:7px 0; font-size:13.5px; color:#3D4152; }
        .sw-row.total { border-top:1px solid #E9E9EB; margin-top:8px; padding-top:14px; font-size:15px; font-weight:900; color:#282C3F; }
        .sw-timer { border-radius:8px; padding:9px 12px; font-weight:900; font-size:15px; }
        .sw-timer.ok { background:#F1F1F6; color:#282C3F; } .sw-timer.late { background:#FDECEE; color:#E23744; }
        .sw-empty { text-align:center; padding:50px 16px; color:#93959F; font-weight:700; }
        .sw-back { display:none; }
        @media (max-width: 900px) {
            .sw-main { grid-template-columns:1fr; height:auto; }
            .sw-list { overflow:visible; }
            .sw.has-sel .sw-left { display:none; }
            .sw:not(.has-sel) .sw-right { display:none; }
            .sw-back { display:inline-flex; }
            .sw-dhead { position:static; }
        }
        .dark .sw { color:#f3f4f6; } .dark .sw-tabs, .dark .sw-left, .dark .sw-card, .dark .sw-box, .dark .sw-dhead { background:#1f2937; border-color:#374151; color:#f3f4f6; }
        .dark .sw-main, .dark .sw-list { background:#111827; } .dark .sw-tab, .dark .sw-meta, .dark .sw-row, .dark .sw-tl .st b { color:#e5e7eb; }
    </style>

    <div class="sw {{ $this->selected ? 'has-sel' : '' }}" wire:poll.8s
         x-data="{ now: Math.floor(Date.now() / 1000) }" x-init="setInterval(() => now = Math.floor(Date.now() / 1000), 1000)">

        <div class="sw-top">
            <h2>MANAGE ORDERS</h2>
            <span class="sep"></span>
            <button type="button" class="sw-switch {{ $online ? 'on' : '' }}" wire:click="toggleOnline"
                    wire:confirm="{{ $online ? 'Go OFFLINE? Customers will not be able to order home delivery on the website (Take Away / Dine In stay open).' : 'Go ONLINE and accept delivery orders again?' }}"
                    title="{{ $online ? 'Online — accepting delivery orders' : 'Offline — delivery closed' }}" aria-pressed="{{ $online ? 'true' : 'false' }}"></button>
            <div class="sw-outlet"><b>{{ $outletName }}</b>@if ($outletAddress)<small>{{ \Illuminate\Support\Str::limit($outletAddress, 90) }}</small>@endif</div>
        </div>

        <div class="sw-tabs" role="tablist">
            @foreach (\App\Filament\Pages\ManageOrders::TABS as $k => [$label])
                <button type="button" role="tab" class="sw-tab {{ $tab === $k ? 'on' : '' }}" wire:click="setTab('{{ $k }}')">{{ $label }}@if ($counts[$k])<span class="n">{{ $counts[$k] }}</span>@endif</button>
            @endforeach
        </div>

        <div class="sw-main">
            {{-- ===== left: cards ===== --}}
            <div class="sw-left">
                <div class="sw-online" style="background:{{ $online ? '#1BA672' : '#E23744' }}">
                    <span>{{ $online ? 'YOU ARE ONLINE' : 'YOU ARE OFFLINE — NO DELIVERY' }}</span>
                    <a href="{{ \App\Models\Order::websiteUrl() }}" target="_blank" rel="noopener" style="color:#fff;text-decoration:none">CHECK WEBSITE ↗</a>
                </div>
                <div class="sw-search" style="position:relative">
                    <span style="position:absolute;left:24px;top:22px;opacity:.5;font-size:13px">🔍</span>
                    <input type="search" placeholder="Search for orders" wire:model.live.debounce.300ms="search">
                </div>
                <div class="sw-list">
                    @forelse ($list as $o)
                        @php [$sl, $sc] = $statusTag($o); [$tl, $tc] = $typeTag($o); $f = $footer($o); $first = $o->items->first(); @endphp
                        <button type="button" class="sw-card {{ $sel && $sel->id === $o->id ? 'on' : '' }}" wire:click="select({{ $o->id }})" wire:key="c{{ $o->id }}">
                            <div style="display:flex;align-items:center;gap:10px">
                                <span class="sw-logo">🍽</span><span class="sw-no">#{{ $last4($o) }}</span>
                            </div>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
                                <span class="sw-chip" style="background:{{ $sc }}">{{ $sl }}</span>
                                <span class="sw-chip" style="background:{{ $tc }}">{{ $tl }}</span>
                                @if ($o->isPaidViaRazorpay())<span class="sw-chip" style="background:#1BA672">PAID</span>@endif
                            </div>
                            <div class="sw-meta">
                                {{ $o->created_at->timezone($tz)->format('h:i A') }} &nbsp;|&nbsp; {{ $m($o->total) }} &nbsp;|&nbsp;
                                @if ($first){{ \App\Filament\Resources\OrderResource::splitItemName((string) $first->name)[0] }} x {{ $first->quantity }}@endif
                                @if ($o->items->count() > 1)<br><span class="sw-more">+ MORE</span>@endif
                            </div>
                            @if ($o->type() === 'delivery' && $o->rider_name)
                                <div class="sw-dash"></div>
                                <div class="sw-person"><span class="sw-avatar">{{ $initials($o->rider_name) }}</span>
                                    <span>{{ $o->rider_name }} {{ match ($o->status) { 'delivered' => 'has delivered the order', 'out_for_delivery' => 'is on the way', default => 'will pick up the order' } }}</span></div>
                            @elseif ($o->status === 'cancelled')
                                <div class="sw-dash"></div>
                                <div class="sw-person" style="text-transform:none">{{ \Illuminate\Support\Str::limit($o->cancelledByLabel() ?: 'Cancelled', 60) }}</div>
                            @endif
                            @if ($f)
                                <div class="sw-dash"></div>
                                <div class="sw-foot">
                                    <span class="{{ $f[0] }}">{{ $f[0] === 'good' ? '👍' : '👎' }} {{ $f[1] }}</span>
                                    @if ($f[3])
                                        <small x-data="{ due: {{ $f[3] }} }" x-bind:style="(due - now) <= 180 ? 'color:#E23744' : ''" x-text="String(Math.floor(Math.abs(due - now) / 60)).padStart(2, '0') + ':' + String(Math.abs(due - now) % 60).padStart(2, '0') + ' MINS'">{{ $f[2] }}</small>
                                    @else
                                        <small>{{ $f[2] }}</small>
                                    @endif
                                </div>
                            @endif
                        </button>
                    @empty
                        <div class="sw-empty">{{ $search !== '' ? 'No order matches “' . $search . '”' : match ($tab) { 'new' => '🎉 No new orders right now', 'past' => 'No finished orders today', default => 'Nothing here' } }}</div>
                    @endforelse
                </div>
            </div>

            {{-- ===== right: selected order ===== --}}
            <div class="sw-right">
                @if ($sel)
                    @php
                        [$sl, $sc] = $statusTag($sel); [$tl, $tc] = $typeTag($sel); $t = $sel->timer(); $step = $sel->nextStep(); $type = $sel->type();
                        $at = fn ($d) => $d ? $d->timezone($tz)->format('h:i A') : null;
                        $steps = array_values(array_filter([
                            ['Placed', $at($sel->created_at), true],
                            ['Confirmed', $at($sel->accepted_at), in_array($sel->status, ['accepted', 'preparing', 'out_for_delivery', 'delivered'], true)],
                            [match ($type) { 'takeaway' => 'Ready', 'dine_in' => 'Served', default => 'Food ready' }, $at($sel->ready_at), in_array($sel->status, ['preparing', 'out_for_delivery', 'delivered'], true)],
                            $type === 'delivery' ? ['Picked up', $at($sel->picked_up_at), in_array($sel->status, ['out_for_delivery', 'delivered'], true)] : null,
                            [match ($type) { 'takeaway' => 'Handed over', 'dine_in' => 'Completed', default => 'Delivered' }, $at($sel->delivered_at), $sel->status === 'delivered'],
                        ]));
                        $nowIdx = collect($steps)->search(fn ($x) => ! $x[2]);
                        $disc = (float) $sel->discount + (float) $sel->coupon_discount + (float) $sel->points_discount;
                        $qty = (int) $sel->items->sum('quantity');
                        $ph = substr(preg_replace('/\D/', '', (string) $sel->customer_phone), -10);
                    @endphp
                    <div class="sw-dhead" wire:key="h{{ $sel->id }}">
                        <div style="display:flex;align-items:center;gap:12px">
                            <button type="button" class="sw-btn sw-back" wire:click="back">‹</button>
                            <span class="sw-logo" style="width:40px;height:40px;font-size:20px">🍽</span>
                            <div>
                                <div class="sw-dno">#{{ $head($sel) }}<span>{{ $last4($sel) }}</span>
                                    <span class="sw-chip" style="background:{{ $sc }};vertical-align:middle">{{ $sl }}</span>
                                    <span class="sw-chip" style="background:{{ $tc }};vertical-align:middle">{{ $tl }}</span>
                                </div>
                                <div class="sw-dsub">{{ $sel->created_at->timezone($tz)->format('h:i A') }} | {{ $qty }} item{{ $qty == 1 ? '' : 's' }}, {{ $m($sel->total) }}</div>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px">
                            <button type="button" class="sw-btn kot" onclick="window.open('{{ $printUrl($sel, 'kot') }}', '_blank', 'width=420,height=700')">Print KOT</button>
                            <button type="button" class="sw-btn bill" onclick="window.open('{{ $printUrl($sel, 'bill') }}', '_blank', 'width=420,height=700')">Print Bill</button>
                        </div>
                    </div>

                    <div class="sw-grid">
                        <div style="display:flex;flex-direction:column;gap:16px;min-width:0">
                            {{-- action strip --}}
                            @if ($sel->status === 'pending' || $step)
                                <div class="sw-box" style="border:2px solid #FFE0CC">
                                    @if ($t)
                                        <div class="sw-timer {{ $t['state'] === 'late' ? 'late' : 'ok' }}" style="margin-bottom:12px"
                                             @if ($t['due']) x-data="{ due: {{ $t['due'] }} }" x-bind:class="(due - now) <= 180 ? 'late' : 'ok'" @endif>
                                            @if ($t['due'])
                                                <span x-text="(due - now) >= 0 ? '⏱ ' + Math.floor((due - now) / 60) + ':' + String((due - now) % 60).padStart(2, '0') + ' left to get it ready' : '🔴 ' + Math.ceil((now - due) / 60) + ' min late'">{{ $t['text'] }}</span>
                                            @else
                                                {{ $t['text'] }}
                                            @endif
                                        </div>
                                    @endif
                                    @if ($sel->status === 'pending')
                                        <div style="font-weight:900;margin-bottom:8px">Accept order — food ready in:</div>
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                                            @foreach (\App\Models\Order::PREP_CHOICES as $min)
                                                <button type="button" class="sw-btn green" style="min-width:76px;min-height:46px" wire:click="accept({{ $sel->id }}, {{ $min }})">{{ $min }} min</button>
                                            @endforeach
                                            <div x-data="{ rj: false }" style="position:relative">
                                                <button type="button" class="sw-btn red" style="min-height:46px" x-on:click="rj = !rj">Reject ▾</button>
                                                <div x-show="rj" x-cloak x-on:click.outside="rj = false" style="position:absolute;right:0;top:calc(100% + 4px);z-index:30;min-width:260px;background:#fff;border:1px solid #F8C9CD;border-radius:10px;box-shadow:0 12px 30px rgba(0,0,0,.18);padding:6px">
                                                    @foreach (\App\Models\Order::REJECT_REASONS as $rk => $rl)
                                                        <button type="button" wire:click="reject({{ $sel->id }}, '{{ $rk }}')" x-on:click="rj = false" style="display:block;width:100%;text-align:left;padding:10px;border:0;background:none;border-radius:8px;font:inherit;font-weight:700;cursor:pointer;color:#282C3F">{{ $rl }}</button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @elseif ($step)
                                        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                                            <button type="button" class="sw-btn go" style="background:{{ match ($step['color']) { 'warning' => '#F59E0B', 'info' => '#2563EB', default => '#1BA672' } }}" wire:click="next({{ $sel->id }})" wire:loading.attr="disabled">{{ $step['label'] }} →</button>
                                            @if ($sel->status === 'accepted')
                                                <div x-data="{ rj: false }" style="position:relative">
                                                    <button type="button" class="sw-btn red" x-on:click="rj = !rj">Cancel order ▾</button>
                                                    <div x-show="rj" x-cloak x-on:click.outside="rj = false" style="position:absolute;left:0;top:calc(100% + 4px);z-index:30;min-width:260px;background:#fff;border:1px solid #F8C9CD;border-radius:10px;box-shadow:0 12px 30px rgba(0,0,0,.18);padding:6px">
                                                        @foreach (\App\Models\Order::REJECT_REASONS as $rk => $rl)
                                                            <button type="button" wire:click="reject({{ $sel->id }}, '{{ $rk }}')" x-on:click="rj = false" style="display:block;width:100%;text-align:left;padding:10px;border:0;background:none;border-radius:8px;font:inherit;font-weight:700;cursor:pointer;color:#282C3F">{{ $rl }}</button>
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
                                <div class="sw-box">
                                    <div class="sw-tl">
                                        @foreach ($steps as $i => [$label, $time, $done])
                                            @if ($i)<span class="arr"></span>@endif
                                            <div class="st {{ $done ? 'done' : ($nowIdx === $i ? 'now' : '') }}">
                                                <span class="ck">{{ $done ? '✓' : '' }}</span>
                                                <span>{{ $label }}<b>{{ $done ? ($time ?: '—') : '' }}</b></span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="sw-box" style="background:#FDECEE;border-color:#F8C9CD;color:#E23744;font-weight:800">❌ {{ $sel->cancelledByLabel() ?: 'Cancelled' }}</div>
                            @endif

                            {{-- items --}}
                            <div class="sw-box">
                                @if ($sel->special_instructions)<div class="sw-pill">✂️ {{ $sel->special_instructions }}</div>@endif
                                @foreach ($sel->items as $it)
                                    @php [$dish, $extra] = \App\Filament\Resources\OrderResource::splitItemName((string) $it->name); $cat = $catNames[$it->menuItem?->category ?? ''] ?? null; @endphp
                                    <div class="sw-item">
                                        <div>
                                            <div class="nm"><span class="sw-dotv"></span><span>{{ $dish }}@if ($it->is_free) <span style="color:#1BA672;font-size:12px;font-weight:900">FREE</span>@endif</span></div>
                                            <div class="ct">{{ collect([$extra ?: null, $cat])->filter()->implode(' · ') }}</div>
                                        </div>
                                        <div class="q"><b>x {{ $it->quantity }}</b><small>{{ $m($it->subtotal) }}</small></div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- side column --}}
                        <div style="display:flex;flex-direction:column;gap:16px">
                            <div class="sw-box">
                                <div style="display:flex;gap:10px;align-items:center;font-weight:900;font-size:15px"><span class="sw-avatar" style="border-radius:17px;background:#E9E9EB;color:#7E808C">👤</span>Order from {{ $sel->customer_name }}</div>
                                <div class="sw-dash" style="margin:12px 0"></div>
                                @if (strlen($ph) === 10)
                                    <div style="font-size:13.5px;color:#3D4152">🛍️ {{ $history }} order{{ $history == 1 ? '' : 's' }} in the last 30 days</div>
                                    <div style="margin-top:8px"><a href="tel:+91{{ $ph }}" style="font-weight:800;color:#1BA672;text-decoration:none">📞 {{ $ph }}</a></div>
                                @else
                                    <div style="font-size:13.5px;color:#7E808C">Counter order</div>
                                @endif
                                @if ($type === 'delivery')
                                    <div style="font-size:13px;margin-top:8px;line-height:1.45;color:#3D4152">📍 {{ $sel->delivery_address }}{{ $sel->city && $sel->city !== 'Counter' ? ', ' . $sel->city : '' }}</div>
                                    <a href="{{ $sel->mapsUrl() }}" target="_blank" rel="noopener" style="font-size:13px;font-weight:800;color:#2563EB;text-decoration:none">🗺️ Open map{{ $sel->distance_km ? ' · ' . $sel->distance_km . ' km' : '' }}</a>
                                @elseif ($type === 'dine_in' && $sel->table_number)
                                    <div style="margin-top:8px;font-weight:800">🍽️ Table {{ $sel->table_number }}</div>
                                @endif
                            </div>

                            @if ($type === 'delivery' && $sel->status !== 'cancelled')
                                <div class="sw-box">
                                    @if ($sel->rider_name)
                                        <div style="display:flex;gap:10px;align-items:center">
                                            <span class="sw-avatar">{{ $initials($sel->rider_name) }}</span>
                                            <div style="font-size:13.5px;color:#3D4152">{{ $sel->rider_name }} {{ match ($sel->status) { 'delivered' => 'has delivered the order', 'out_for_delivery' => 'is on the way', default => 'will deliver this order' } }}
                                                @if ($sel->rider?->phone)<br><a href="tel:{{ $sel->rider->phone }}" style="color:#3D4152">{{ $sel->rider->phone }}</a>@endif</div>
                                        </div>
                                    @endif
                                    @if ($sel->status !== 'delivered')
                                        @if ($riders)
                                            <select class="sw-btn" style="width:100%;margin-top:{{ $sel->rider_name ? '12px' : '0' }};font-weight:700" wire:change="assignRider({{ $sel->id }}, $event.target.value)">
                                                <option value="">{{ $sel->rider_id ? '— change / remove delivery boy —' : '— choose delivery boy —' }}</option>
                                                @foreach ($riders as $rid => $rname)
                                                    <option value="{{ $rid }}" @selected($sel->rider_id == $rid)>{{ $rname }}</option>
                                                @endforeach
                                            </select>
                                        @else
                                            <div style="font-size:13px;color:#7E808C">No delivery boys yet — add one in Staff accounts (role “Delivery Boy”).</div>
                                        @endif
                                    @endif
                                </div>
                            @endif

                            <div class="sw-box">
                                <h4>Bill Details</h4>
                                <div class="sw-row"><span>Item total ({{ $qty }} item{{ $qty == 1 ? '' : 's' }})</span><span>{{ $m($sel->subtotal) }}</span></div>
                                @if ((float) $sel->delivery_fee > 0)<div class="sw-row"><span>Delivery charges</span><span>{{ $m($sel->delivery_fee) }}</span></div>@endif
                                @if ((float) $sel->gst_amount > 0)<div class="sw-row"><span>GST {{ rtrim(rtrim(number_format((float) $sel->gst_rate, 2), '0'), '.') }}%{{ $sel->gst_mode === 'inclusive' ? ' (incl.)' : '' }}</span><span>{{ $sel->gst_mode === 'inclusive' ? '' : '' }}{{ $m($sel->gst_amount) }}</span></div>@endif
                                @if ($disc > 0)<div class="sw-row"><span>Discount<br><small style="color:#7E808C">{{ $sel->offer_title ?: ($sel->coupon_code ?: 'Offer') }}</small></span><span>-{{ $m($disc) }}</span></div>@endif
                                <div class="sw-row total"><span>Bill Total</span><span>{{ $m($sel->total) }}</span></div>
                                <div style="margin-top:10px;font-size:13px;font-weight:800;color:{{ $sel->isSettled() ? '#1BA672' : '#E23744' }}">{{ $sel->settleLabel() }}</div>
                                @if ($sel->needsSettlement() && $sel->status !== 'pending')
                                    <div style="display:flex;gap:6px;margin-top:8px">
                                        <button type="button" class="sw-btn" style="flex:1" wire:click="settle({{ $sel->id }}, 'cash')" wire:confirm="Settle {{ $m($sel->total) }} as CASH?">💵 Cash</button>
                                        <button type="button" class="sw-btn" style="flex:1" wire:click="settle({{ $sel->id }}, 'upi')" wire:confirm="Settle {{ $m($sel->total) }} as UPI?">📱 UPI</button>
                                        <button type="button" class="sw-btn" style="flex:1" wire:click="settle({{ $sel->id }}, 'card')" wire:confirm="Settle {{ $m($sel->total) }} as CARD?">💳 Card</button>
                                    </div>
                                @endif
                            </div>
                            <a href="{{ $viewUrl($sel) }}" style="text-align:center;font-size:12.5px;font-weight:800;color:#F15700;text-decoration:underline">OPEN FULL ORDER PAGE</a>
                        </div>
                    </div>
                @else
                    <div class="sw-empty" style="padding-top:140px">👈 Select an order to see its details</div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
