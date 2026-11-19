<x-filament-widgets::widget>
    @if ($rows->isNotEmpty())
        <div class="sp-card sp-renew" role="region" aria-label="Monthly plans ending soon">
            <div class="sp-row" style="margin-bottom: 10px; align-items: flex-start">
                <div>
                    <h2 style="margin: 0; display: flex; align-items: center; gap: 8px">
                        <span aria-hidden="true">🔔</span> Monthly plans ending soon
                        @if ($notCalled)<span class="sp-chip sp-chip-red" style="cursor: default">{{ $notCalled }} to call</span>@endif
                    </h2>
                    <div class="sp-muted" style="margin-top: 2px">Ending in the next {{ $days }} days or just ended. Call them to continue the plan.</div>
                </div>
                <a href="{{ $url }}" class="sp-btn sp-btn-light" style="display: inline-flex; align-items: center; text-decoration: none; white-space: nowrap">All plan customers →</a>
            </div>

            <div style="display: flex; flex-direction: column">
                @foreach ($rows as $r)
                    @php $d = $r->daysLeft(); @endphp
                    <div class="sp-row sp-renew-row" wire:key="pr-{{ $r->id }}">
                        <div style="min-width: 0; flex: 1">
                            <div style="font-weight: 800; font-size: 14px">{{ $r->name }}@if ($r->company)<span class="sp-muted" style="font-weight: 600"> · {{ $r->company }}</span>@endif</div>
                            <div class="sp-muted">{{ $r->plan_name ?: 'Monthly plan' }} · ends {{ $r->active_until?->format('D, d M') }} · {{ $r->phone }}</div>
                        </div>
                        <span class="sp-renew-badge {{ $d <= 0 ? 'over' : ($d === 1 ? 'today' : '') }}">{{ $r->endsLabel() }}</span>
                        <div style="display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end">
                            <a class="sp-btn sp-btn-light" style="display: inline-flex; align-items: center; text-decoration: none" href="tel:{{ $r->phone }}">📞 Call</a>
                            <a class="sp-btn sp-btn-green" style="display: inline-flex; align-items: center; text-decoration: none" href="{{ $r->whatsappUrl(true) }}" target="_blank" rel="noopener">WhatsApp</a>
                            @if ($r->renewal_called_at)
                                <span class="sp-chip" style="background: #E8F6EC; color: #11622F; cursor: default">✓ Called {{ $r->renewal_called_at->format('d M') }}</span>
                            @else
                                <button type="button" class="sp-btn sp-btn-amber" wire:click="markCalled({{ $r->id }})">Mark called</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <style>
            .sp-renew { border-color: #F5C26B; background: linear-gradient(180deg, #FFF8E7, #fff 60%); }
            .sp-renew-row { padding: 10px 0; border-top: 1px solid #F3EAD6; gap: 12px; flex-wrap: wrap; }
            .sp-renew-badge { font-size: 12px; font-weight: 800; padding: 4px 10px; border-radius: 999px; background: #FFF1CC; color: #8A5A00; white-space: nowrap; }
            .sp-renew-badge.today { background: #FFEDD5; color: #C2410C; }
            .sp-renew-badge.over { background: #FDECEC; color: #9F1D1D; }
            .dark .sp-renew { background: rgb(24 24 27); }
        </style>
    @endif
</x-filament-widgets::widget>
