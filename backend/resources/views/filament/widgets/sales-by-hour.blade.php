<x-filament-widgets::widget>
    <div class="sp-card" wire:poll.120s>
        <div class="sp-row"><h2>Sales by hour</h2><span class="sp-muted">Today · ₹{{ number_format($total, 0) }}</span></div>
        <div class="sp-bars" role="img" aria-label="Sales by hour today{{ $peakLabel ? ', busiest at ' . $peakLabel : '' }}">
            @foreach ($bars as $b)
                <div class="sp-bar {{ $b['peak'] ? 'peak' : '' }}" style="height: {{ $b['pct'] }}%" title="{{ $b['title'] }}"></div>
            @endforeach
        </div>
        <div class="sp-row sp-muted" style="margin-top: 6px"><span>11 AM</span><span>3 PM</span><span>7 PM</span><span>11 PM</span></div>
        @if ($peakLabel)<div class="sp-muted" style="margin-top: 6px">Busiest hour: <strong>{{ $peakLabel }}</strong></div>@endif
    </div>
</x-filament-widgets::widget>
