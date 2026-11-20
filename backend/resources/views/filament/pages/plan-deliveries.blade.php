<x-filament-panels::page>
    <style>
        .pd-bar { display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
        .pd-btn { display:inline-flex; align-items:center; gap:6px; padding:8px 14px; border-radius:10px; border:1px solid #E5D9C3; background:#fff; font-weight:700; font-size:14px; cursor:pointer; color:#3B2A12; text-decoration:none; }
        .pd-btn:hover { background:#FFF8E7; }
        .pd-btn.primary { background:#E6A817; border-color:#C98F0F; color:#1A0F00; }
        .pd-btn.green { background:#16A34A; border-color:#15803D; color:#fff; }
        .pd-btn.sm { padding:6px 10px; font-size:13px; }
        .pd-stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; }
        .pd-stat { background:#fff; border:1px solid #EEE3CF; border-radius:14px; padding:12px 14px; }
        .pd-stat b { display:block; font-size:24px; font-weight:900; }
        .pd-stat span { font-size:12px; font-weight:700; color:#8A7A5C; text-transform:uppercase; letter-spacing:.04em; }
        .pd-group { margin-top:6px; }
        .pd-group h3 { font-size:15px; font-weight:900; color:#8A5A00; margin:14px 0 8px; display:flex; gap:8px; align-items:center; }
        .pd-row { display:grid; grid-template-columns: minmax(200px,2fr) 110px minmax(180px,2fr) auto; gap:12px; align-items:center; background:#fff; border:1px solid #EEE3CF; border-radius:14px; padding:12px 14px; margin-bottom:8px; }
        .pd-row.done { background:#F0FDF4; border-color:#BBF7D0; }
        .pd-row.skip { background:#F9FAFB; opacity:.75; }
        .pd-name { font-weight:800; font-size:15px; }
        .pd-sub { font-size:12.5px; color:#6B5A3E; margin-top:2px; }
        .pd-meals input { width:70px; padding:6px 8px; border:1px solid #E5D9C3; border-radius:8px; font-weight:800; font-size:16px; text-align:center; }
        .pd-tag { display:inline-block; padding:2px 8px; border-radius:99px; font-size:11px; font-weight:800; }
        .pd-actions { display:flex; gap:6px; flex-wrap:wrap; justify-content:flex-end; }
        @media (max-width: 900px) { .pd-row { grid-template-columns: 1fr 1fr; } .pd-actions { grid-column: 1 / -1; justify-content:flex-start; } }
        .dark .pd-row, .dark .pd-stat, .dark .pd-btn { background:#1f2937; border-color:#374151; color:#f3f4f6; }
    </style>

    <div class="pd-bar">
        <button type="button" class="pd-btn" wire:click="shift(-1)">‹ Previous day</button>
        <input type="date" wire:model.live="date" class="pd-btn" style="font-weight:800">
        <button type="button" class="pd-btn" wire:click="shift(1)">Next day ›</button>
        @unless ($isToday)
            <button type="button" class="pd-btn" wire:click="goToday">Today</button>
        @endunless
        <span style="flex:1"></span>
        <a href="{{ $printUrl }}" target="_blank" rel="noopener" class="pd-btn primary">🖨️ Print list</a>
        @if ($customers)
            <button type="button" class="pd-btn green" wire:click="markAllDelivered" wire:confirm="Mark every plan on this day as delivered?">✅ Mark all delivered</button>
        @endif
    </div>

    <div style="font-size:20px;font-weight:900;margin-top:4px">
        {{ $day->format('l, d M Y') }} @if ($isToday)<span class="pd-tag" style="background:#FEF3C7;color:#92400E;vertical-align:middle">TODAY</span>@endif
    </div>

    @if ($isSunday && ! $sundayOn)
        <div style="background:#FFF8E7;border:1px solid #F5C26B;border-radius:14px;padding:14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap">
            <span style="flex:1">☀️ <strong>Sunday — no plan deliveries.</strong> Plans are set to run Monday to Saturday.</span>
            <button type="button" class="pd-btn sm" wire:click="toggleSunday">Plans run on Sundays too</button>
        </div>
    @else
        <div class="pd-stats">
            <div class="pd-stat"><span>Customers</span><b>{{ $customers }}</b></div>
            <div class="pd-stat"><span>Meals to send</span><b>{{ $meals }}</b></div>
            <div class="pd-stat"><span>Delivered</span><b style="color:#15803D">{{ $delivered }} / {{ $customers }}</b></div>
            <div class="pd-stat"><span>Skipped</span><b style="color:#6B7280">{{ $skipped }}</b></div>
        </div>

        @forelse ($groups as $time => $rows)
            <div class="pd-group">
                <h3>🕐 {{ $time }} <span class="pd-tag" style="background:#FEF3C7;color:#92400E">{{ $rows->where('mark.status', '!=', 'skipped')->sum('meals') }} meals</span></h3>
                @foreach ($rows as $r)
                    @php $p = $r['p']; $st = $r['mark']?->status; @endphp
                    <div class="pd-row {{ $st === 'delivered' ? 'done' : ($st === 'skipped' ? 'skip' : '') }}" wire:key="pd-{{ $p->id }}-{{ $day->toDateString() }}">
                        <div>
                            <div class="pd-name">{{ $p->company ?: $p->name }}</div>
                            <div class="pd-sub">
                                {{ $p->company ? $p->name . ' · ' : '' }}<a href="tel:+91{{ substr(preg_replace('/\D/', '', (string) $p->phone), -10) }}" style="color:#1D4ED8;font-weight:700">{{ $p->phone }}</a>
                            </div>
                            <div class="pd-sub">
                                {{ $p->plan_name ?: $p->plan?->name }}{{ $p->plan?->meal_type ? ' · ' . $p->plan->meal_type : '' }}
                                · day {{ $r['days'] + ($st === 'delivered' ? 0 : 1) }}{{ $r['planDays'] ? ' of ' . $r['planDays'] : '' }}
                                @if ($r['endsSoon'])<span class="pd-tag" style="background:#FEE2E2;color:#B91C1C">ends {{ $p->active_until->format('d M') }}</span>@endif
                            </div>
                        </div>
                        <div class="pd-meals">
                            @if ($st)
                                <div style="font-size:22px;font-weight:900">{{ $st === 'skipped' ? '—' : $r['meals'] }}</div>
                                <div class="pd-sub">{{ $st === 'skipped' ? 'skipped' : 'meals sent' }}</div>
                            @else
                                <input type="number" min="0" wire:model="people.{{ $p->id }}" placeholder="{{ $r['default'] }}" aria-label="Meals for {{ $p->company ?: $p->name }}">
                                <div class="pd-sub">meals</div>
                            @endif
                        </div>
                        <div class="pd-sub" style="font-size:13px">
                            📍 {{ $p->address ?: 'Address not set' }}
                            @if ($p->customer_note)<div style="margin-top:3px">📝 {{ $p->customer_note }}</div>@endif
                        </div>
                        <div class="pd-actions">
                            @if ($st)
                                <span class="pd-tag" style="{{ $st === 'delivered' ? 'background:#DCFCE7;color:#15803D' : 'background:#F3F4F6;color:#4B5563' }};font-size:12px;padding:5px 10px">
                                    {{ $st === 'delivered' ? '✅ Delivered' : '⏭ Skipped' }}{{ $r['mark']->done_by ? ' · ' . $r['mark']->done_by : '' }}
                                </span>
                                <button type="button" class="pd-btn sm" wire:click="undo({{ $p->id }})">Undo</button>
                            @else
                                <button type="button" class="pd-btn sm green" wire:click="markDelivered({{ $p->id }})">✅ Delivered</button>
                                <button type="button" class="pd-btn sm" wire:click="markSkipped({{ $p->id }})">⏭ Skip today</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <div style="background:#fff;border:1px dashed #E5D9C3;border-radius:14px;padding:28px;text-align:center;color:#6B5A3E">
                No active monthly plans on this day.
                <div style="margin-top:6px;font-size:13px">Plans appear here once a request is marked <strong>Active</strong> with start and end dates (Grow → Plan Enquiries).</div>
            </div>
        @endforelse

        @if (! $isSunday)
            <div style="font-size:12px;color:#8A7A5C;margin-top:8px">
                Sundays: {{ $sundayOn ? 'plans run on Sundays' : 'off' }} ·
                <button type="button" wire:click="toggleSunday" style="text-decoration:underline;color:#8A5A00;background:none;border:0;cursor:pointer;font-size:12px">change</button>
            </div>
        @endif
    @endif
</x-filament-panels::page>
