<x-filament-widgets::widget>
    <div class="sp-card" style="display: flex; flex-direction: column; gap: 4px">
        <h2>Quick switches</h2>

        <div class="sp-row" style="padding: 10px 0; border-top: 1px solid #F3EAD6">
            <div><div style="font-weight: 700; font-size: 14px">Home delivery</div><div class="sp-muted">{{ $deliveryHint }}</div></div>
            <button type="button" class="sp-switch" role="switch" aria-checked="{{ $deliveryOn ? 'true' : 'false' }}" aria-label="Home delivery" wire:click="toggleDelivery"></button>
        </div>

        @if ($isOwner)
            <div class="sp-row" style="padding: 10px 0; border-top: 1px solid #F3EAD6">
                <div><div style="font-weight: 700; font-size: 14px">Loyalty points</div><div class="sp-muted">Customers earn and use points</div></div>
                <button type="button" class="sp-switch" role="switch" aria-checked="{{ $loyaltyOn ? 'true' : 'false' }}" aria-label="Loyalty points" wire:click="toggleLoyalty"></button>
            </div>
        @endif

        <div style="padding-top: 10px; border-top: 1px solid #F3EAD6; display: flex; flex-direction: column; gap: 8px">
            <label for="sp-dish-search" style="font-weight: 700; font-size: 14px">Sold out today</label>
            <input id="sp-dish-search" type="search" class="sp-input" placeholder="Type a dish to mark sold out…" wire:model.live.debounce.300ms="dishSearch" autocomplete="off">
            @if (count($results))
                <div style="display: flex; flex-direction: column; border: 1px solid #EDE0C4; border-radius: 12px; overflow: hidden">
                    @foreach ($results as $d)
                        <div class="sp-row" style="padding: 8px 10px; border-top: 1px solid #F3EAD6; font-size: 13px" wire:key="res-{{ $d->id }}">
                            <span>{{ $d->name }}</span>
                            @if ($d->is_available)
                                <button type="button" class="sp-btn sp-btn-light" style="height: 32px; color: #9F1D1D" wire:click="setAvailable({{ $d->id }}, false)">Sold out</button>
                            @else
                                <button type="button" class="sp-btn sp-btn-green" style="height: 32px" wire:click="setAvailable({{ $d->id }}, true)">Available</button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @elseif (mb_strlen(trim($dishSearch)) >= 2)
                <div class="sp-muted">No dish matches “{{ $dishSearch }}”.</div>
            @endif
            @if (count($soldOut))
                <div style="display: flex; flex-wrap: wrap; gap: 6px">
                    @foreach ($soldOut as $d)
                        <button type="button" class="sp-chip sp-chip-red" wire:click="setAvailable({{ $d->id }}, true)" title="Tap to make available again" wire:key="so-{{ $d->id }}">
                            {{ $d->name }} <span aria-hidden="true">×</span><span style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">make available</span>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="sp-muted">All dishes are available.</div>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
