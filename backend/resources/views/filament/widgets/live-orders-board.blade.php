<x-filament-widgets::widget>
    <div class="sp-card" wire:poll.10s>
        <div class="sp-row" style="margin-bottom: 12px">
            <h2 style="margin: 0">Live orders</h2>
            <span class="sp-muted" style="display: inline-flex; align-items: center; gap: 6px; color: #11622F; font-weight: 700">
                <span style="width: 8px; height: 8px; border-radius: 4px; background: #16A34A"></span> Updates every 10 seconds
            </span>
        </div>
        <div class="sp-board">
            @foreach ($columns as $col)
                <section class="sp-col" aria-label="{{ $col['title'] }}">
                    <div class="sp-col-head" style="color: {{ $col['color'] }}">
                        <span>{{ $col['title'] }}</span><span class="sp-count">{{ count($col['cards']) }}</span>
                    </div>
                    @forelse ($col['cards'] as $o)
                        <article class="sp-order {{ $col['key'] === 'pending' ? 'new' : '' }}" wire:key="order-{{ $o['id'] }}">
                            <div class="sp-row">
                                <a class="sp-no" href="{{ $o['url'] }}">#{{ $o['no'] }}</a>
                                <span style="font-size: 12px; font-weight: 700; color: {{ $o['late'] ? '#B91C1C' : '#6B5232' }}">{{ $o['ago'] }}</span>
                            </div>
                            <div style="font-size: 13px; line-height: 1.35">{{ $o['items'] }}</div>
                            @if ($o['note'])
                                <div style="font-size: 12px; background: #FFF8E7; color: #7A4A00; padding: 4px 8px; border-radius: 8px">Note: {{ \Illuminate\Support\Str::limit($o['note'], 60) }}</div>
                            @endif
                            <div class="sp-row">
                                <span class="sp-type sp-type-{{ $o['type'] }}">{{ $o['typeLabel'] }}</span>
                                <strong style="font-size: 14px">{{ $o['total'] }}</strong>
                            </div>
                            <button type="button" class="sp-btn {{ $o['btn'] }}" style="margin-top: 4px" wire:click="advance({{ $o['id'] }})" wire:loading.attr="disabled">
                                {{ $o['action'] }}
                            </button>
                        </article>
                    @empty
                        <div class="sp-empty">Nothing here</div>
                    @endforelse
                </section>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
