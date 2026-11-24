<x-filament-panels::page>
    <style>
        .kb-top { display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
        .kb-chip { height:40px; padding:0 16px; border-radius:999px; border:1px solid #E5D9C3; background:#fff; font:inherit; font-weight:700; font-size:14px; cursor:pointer; color:#3B2A12; }
        .kb-chip[aria-pressed="true"] { background:#2C1A00; color:#FFF6E0; border-color:#2C1A00; }
        .kb-grid { display:grid; grid-template-columns:repeat(4, minmax(250px, 1fr)); gap:14px; align-items:start; }
        @media (max-width: 1100px) { .kb-grid { grid-template-columns:repeat(2, minmax(250px, 1fr)); } }
        @media (max-width: 640px) { .kb-grid { grid-template-columns:1fr; } }
        .kb-col { border-radius:18px; padding:10px; min-height:200px; }
        .kb-colhead { display:flex; justify-content:space-between; align-items:center; font-weight:900; font-size:15px; letter-spacing:.04em; padding:4px 6px 10px; }
        .kb-count { min-width:28px; height:28px; border-radius:14px; display:inline-flex; align-items:center; justify-content:center; color:#fff; font-size:14px; padding:0 8px; }
        .kb-card { background:#fff; border-radius:16px; padding:12px; margin-bottom:10px; box-shadow:0 2px 8px rgba(60,40,0,.08); border:2px solid transparent; }
        .kb-card.late { border-color:#DC2626; animation: kbPulse 1.6s ease-in-out infinite; }
        .kb-card.warn { border-color:#F59E0B; }
        @keyframes kbPulse { 0%,100% { box-shadow:0 0 0 0 rgba(220,38,38,.35); } 50% { box-shadow:0 0 0 6px rgba(220,38,38,0); } }
        .kb-head { display:flex; justify-content:space-between; align-items:center; gap:6px; }
        .kb-no { font-weight:900; font-size:18px; color:#1A0F00; text-decoration:none; }
        .kb-type { font-size:12px; font-weight:800; padding:3px 9px; border-radius:999px; white-space:nowrap; }
        .kb-who { font-size:13px; color:#6B5A3E; font-weight:600; margin-top:2px; }
        .kb-timer { margin-top:8px; font-weight:900; font-size:16px; border-radius:10px; padding:6px 10px; display:flex; justify-content:space-between; align-items:center; }
        .kb-timer.ok { background:#F1F5F9; color:#334155; } .kb-timer.warn { background:#FEF3C7; color:#92400E; } .kb-timer.late { background:#FEE2E2; color:#B91C1C; }
        .kb-items { margin-top:8px; font-size:15px; line-height:1.35; }
        .kb-items div { display:flex; gap:8px; padding:3px 0; border-bottom:1px dashed #F1E7D3; }
        .kb-items b { min-width:30px; color:#8A5A00; }
        .kb-items small { display:block; color:#8A7A5C; font-size:12.5px; }
        .kb-note { margin-top:6px; background:#FEF3C7; color:#92400E; border-radius:8px; padding:5px 8px; font-weight:800; font-size:13.5px; }
        .kb-btn { width:100%; min-height:50px; margin-top:10px; border:0; border-radius:12px; font:inherit; font-weight:900; font-size:16px; cursor:pointer; }
        .kb-prep { display:grid; grid-template-columns:repeat(4, 1fr); gap:6px; margin-top:10px; }
        .kb-prep button { min-height:48px; border-radius:10px; border:0; background:#16A34A; color:#fff; font:inherit; font-weight:900; font-size:15px; cursor:pointer; }
        .kb-rej { margin-top:6px; width:100%; min-height:38px; border-radius:10px; border:1px solid #FECACA; background:#fff; color:#DC2626; font:inherit; font-weight:800; cursor:pointer; }
        .kb-empty { text-align:center; color:#A8987A; font-weight:700; padding:24px 6px; }
        .kb-paid { font-size:11.5px; font-weight:900; color:#15803D; background:#DCFCE7; border-radius:6px; padding:2px 6px; }
        .dark .kb-card { background:#1f2937; color:#f3f4f6; } .dark .kb-no { color:#fff; } .dark .kb-col { background:#111827 !important; }
        .dark .kb-items div { border-color:#374151; } .dark .kb-chip { background:#1f2937; color:#f3f4f6; border-color:#374151; }
    </style>

    <div wire:poll.8s
         x-data="{ now: Math.floor(Date.now() / 1000) }"
         x-init="setInterval(() => now = Math.floor(Date.now() / 1000), 1000)">
        <div class="kb-top" style="margin-bottom:14px">
            @foreach (['all' => 'All', 'delivery' => '🛵 Delivery', 'takeaway' => '🛍️ Take Away', 'dine_in' => '🍽️ Dine In'] as $k => $l)
                @php $n = $k === 'all' ? array_sum($counts) : ($counts[$k] ?? 0); @endphp
                <button type="button" class="kb-chip" aria-pressed="{{ $type === $k ? 'true' : 'false' }}" wire:click="$set('type', '{{ $k }}')">{{ $l }}{{ $n ? " ($n)" : '' }}</button>
            @endforeach
            <span style="flex:1"></span>
            <span style="font-size:12.5px;color:#15803D;font-weight:700">● Live · updates every few seconds</span>
            <button type="button" class="kb-chip" x-on:click="document.fullscreenElement ? document.exitFullscreen() : document.documentElement.requestFullscreen()">⛶ Full screen</button>
        </div>

        <div class="kb-grid">
            @foreach ($columns as $col)
                <section class="kb-col" style="background:{{ $col['bg'] }}" aria-label="{{ $col['title'] }}">
                    <div class="kb-colhead" style="color:{{ $col['color'] }}">
                        <span>{{ $col['title'] }}</span>
                        <span class="kb-count" style="background:{{ $col['color'] }}">{{ $col['orders']->count() }}</span>
                    </div>
                    @forelse ($col['orders'] as $o)
                        @php
                            $t = $o->timer();
                            $step = $o->nextStep();
                            [$typeTxt, $typeCss] = match ($o->type()) {
                                'takeaway' => ['🛍️ Take Away', 'background:#FEF3C7;color:#92400E'],
                                'dine_in'  => ['🍽️ Dine In' . ($o->table_number ? ' · T' . $o->table_number : ''), 'background:#DCFCE7;color:#166534'],
                                default    => ['🛵 Delivery', 'background:#DBEAFE;color:#1E40AF'],
                            };
                        @endphp
                        <article class="kb-card {{ $t['state'] ?? '' }}" wire:key="kb{{ $o->id }}" @if ($t && $t['due']) x-data="{ due: {{ $t['due'] }} }" x-bind:class="(due - now) <= 180 ? 'late' : ''" @endif>
                            <div class="kb-head">
                                <a class="kb-no" href="{{ $viewUrl($o) }}">#{{ substr($o->order_number, -6) }}</a>
                                <span class="kb-type" style="{{ $typeCss }}">{{ $typeTxt }}</span>
                            </div>
                            <div class="kb-who">{{ $o->customer_name }}
                                @if ($o->isPaidViaRazorpay()) <span class="kb-paid">PAID</span> @endif
                                @if ($o->type() === 'delivery' && $o->rider_name) · 🛵 {{ $o->rider_name }} @endif
                            </div>

                            @if ($t)
                                <div class="kb-timer {{ $t['state'] }}" @if ($t['due']) x-bind:class="(due - now) <= 180 ? 'late' : 'ok'" @endif
                                     @if ($t['due']) x-data="{ due: {{ $t['due'] }} }" @else x-data="{ since: {{ (int) $t['since'] }} }" @endif>
                                    @if ($t['due'])
                                        <span x-text="(due - now) >= 0 ? '⏱ ' + Math.floor((due - now) / 60) + ':' + String((due - now) % 60).padStart(2, '0') + ' left' : '🔴 ' + Math.ceil((now - due) / 60) + ' min late'">{{ $t['text'] }}</span>
                                        <small style="font-weight:700">of {{ $o->prep_time_minutes ?: 20 }} min</small>
                                    @else
                                        <span>{{ $t['text'] }}</span>
                                        <small style="font-weight:700" x-text="Math.floor((now - since) / 60) + ':' + String((now - since) % 60).padStart(2, '0')"></small>
                                    @endif
                                </div>
                            @endif

                            <div class="kb-items">
                                @foreach ($o->items as $it)
                                    @php [$dish, $extra] = \App\Filament\Resources\OrderResource::splitItemName((string) $it->name); @endphp
                                    <div><b>{{ $it->quantity }}×</b><span>{{ $dish }}@if ($extra)<small>{{ $extra }}</small>@endif</span></div>
                                @endforeach
                            </div>
                            @if ($o->special_instructions)<div class="kb-note">📝 {{ $o->special_instructions }}</div>@endif

                            @if ($o->status === 'pending')
                                <div style="font-size:12.5px;font-weight:800;color:#6B5A3E;margin-top:10px">Accept — ready in:</div>
                                <div class="kb-prep">
                                    @foreach ([10, 15, 20, 30] as $m)
                                        <button type="button" wire:click="accept({{ $o->id }}, {{ $m }})">{{ $m }}′</button>
                                    @endforeach
                                </div>
                                <div x-data="{ rj: false }" style="position:relative">
                                    <button type="button" class="kb-rej" x-on:click="rj = !rj">Reject ▾</button>
                                    <div x-show="rj" x-cloak x-on:click.outside="rj = false" style="position:absolute;left:0;right:0;z-index:20;background:#fff;border:1px solid #FECACA;border-radius:12px;box-shadow:0 10px 24px rgba(0,0,0,.15);padding:6px;margin-top:4px">
                                        @foreach (\App\Models\Order::REJECT_REASONS as $rk => $rl)
                                            <button type="button" wire:click="reject({{ $o->id }}, '{{ $rk }}')" x-on:click="rj = false" style="display:block;width:100%;text-align:left;padding:10px;border:0;background:none;border-radius:8px;font:inherit;font-weight:700;cursor:pointer;color:#1e293b">{{ $rl }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            @elseif ($step)
                                <button type="button" class="kb-btn" wire:click="next({{ $o->id }})" wire:loading.attr="disabled"
                                    style="{{ match ($step['color']) { 'warning' => 'background:#F59E0B;color:#1A0F00', 'info' => 'background:#2563EB;color:#fff', default => 'background:#16A34A;color:#fff' } }}">
                                    {{ $step['label'] }} →
                                </button>
                            @endif
                        </article>
                    @empty
                        <div class="kb-empty">Nothing here</div>
                    @endforelse
                </section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
