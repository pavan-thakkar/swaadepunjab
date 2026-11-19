<x-filament-widgets::widget>
    <div class="sp-card">
        <h2>Top dishes today</h2>
        @forelse ($top as $i => $t)
            <div class="sp-row" style="padding: 7px 0; font-size: 14px">
                <span style="display: flex; align-items: center; gap: 10px"><span class="sp-rank">{{ $i + 1 }}</span>{{ $t->name }}</span>
                <strong>{{ (int) $t->qty }}</strong>
            </div>
        @empty
            <div class="sp-empty">No orders yet today.</div>
        @endforelse
    </div>
</x-filament-widgets::widget>
