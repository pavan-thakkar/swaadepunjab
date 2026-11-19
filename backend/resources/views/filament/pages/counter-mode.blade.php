<x-filament-panels::page>
    <style>
        .cm-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 18px; align-items: start; }
        @media (max-width: 1024px) { .cm-wrap { grid-template-columns: 1fr; } }
        .cm-strip { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; background: #2C1A00; color: #FFF8E7; border-radius: 16px; padding: 10px 12px; }
        .cm-pend { display: flex; align-items: center; gap: 10px; background: rgba(255,248,231,0.08); border: 1px solid #E6A817; border-radius: 12px; padding: 6px 8px 6px 12px; font-size: 13px; }
        .cm-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .cm-tab { height: 40px; padding: 0 16px; border-radius: 999px; border: 1px solid #EDE0C4; background: #fff; font: inherit; font-size: 14px; font-weight: 700; cursor: pointer; color: #1A0F00; }
        .cm-tab[aria-selected="true"] { background: #2C1A00; border-color: #2C1A00; color: #FFF8E7; }
        .cm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; }
        .cm-dish { min-height: 104px; border-radius: 16px; border: 1px solid #EDE0C4; background: #fff; padding: 12px; display: flex; flex-direction: column; justify-content: space-between; align-items: flex-start; text-align: left; font: inherit; cursor: pointer; color: #1A0F00; }
        .cm-dish:hover { border-color: #C98F0F; }
        .cm-dish.in { background: #FFF8E7; border-color: #C98F0F; }
        .cm-dish.out { background: #F3EFE6; cursor: not-allowed; color: #7A6A55; }
        .cm-qty { background: #2C1A00; color: #FFF8E7; font-size: 13px; font-weight: 800; min-width: 28px; height: 28px; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; }
        .cm-bill { background: #fff; border: 1px solid #EDE0C4; border-radius: 18px; display: flex; flex-direction: column; position: sticky; top: 80px; }
        .dark .cm-bill, .dark .cm-dish, .dark .cm-tab { background: rgb(24 24 27); color: #fff; border-color: rgb(63 63 70); }
        .cm-seg { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); background: #F3EAD6; border-radius: 12px; padding: 4px; gap: 4px; }
        .cm-seg button { height: 40px; border-radius: 9px; border: 0; background: transparent; font: inherit; font-weight: 600; color: #4A3518; cursor: pointer; }
        .cm-seg button[aria-pressed="true"] { background: #fff; font-weight: 800; color: #1A0F00; box-shadow: 0 1px 3px rgba(100,60,0,.12); }
        .cm-step { display: flex; align-items: center; border: 1px solid #C98F0F; border-radius: 10px; height: 36px; background: #FFF8E7; }
        .cm-step button { width: 36px; height: 34px; border: 0; background: none; font-size: 18px; font-weight: 800; color: #8A5A00; cursor: pointer; }
        .cm-pay { height: 48px; border-radius: 12px; border: 1px solid #EDE0C4; background: #fff; font: inherit; font-weight: 800; cursor: pointer; color: #1A0F00; }
        .cm-pay[aria-pressed="true"] { border: 2px solid #C98F0F; background: #FFF8E7; }
        .cm-go { height: 56px; border-radius: 14px; border: 0; background: #E6A817; color: #1A0F00; font: inherit; font-size: 17px; font-weight: 900; cursor: pointer; }
        .cm-go:disabled { opacity: .5; cursor: not-allowed; }
        .cm-tabsbar { display: flex; gap: 6px; padding: 10px 10px 0; overflow-x: auto; scrollbar-width: thin; border-bottom: 1px solid #EDE0C4; background: #FBF8F1; border-radius: 18px 18px 0 0; }
        .cm-billtab { display: flex; align-items: center; flex-shrink: 0; border: 1px solid #EDE0C4; border-bottom: 0; border-radius: 10px 10px 0 0; background: #F3EAD6; }
        .cm-billtab.on { background: #fff; border-color: #C98F0F; box-shadow: 0 -2px 0 #E6A817 inset; }
        .cm-billtab-main { display: flex; align-items: center; gap: 6px; height: 40px; padding: 0 6px 0 12px; border: 0; background: none; font: inherit; font-size: 13px; font-weight: 700; color: #1A0F00; cursor: pointer; white-space: nowrap; }
        .cm-billtab.on .cm-billtab-main { font-weight: 800; }
        .cm-billtab-count { background: #2C1A00; color: #FFF8E7; font-size: 11px; font-weight: 800; min-width: 20px; height: 20px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; padding: 0 5px; }
        .cm-billtab-x { width: 28px; height: 40px; border: 0; background: none; color: #9F1D1D; font-size: 18px; font-weight: 700; cursor: pointer; }
        .cm-newbill { flex-shrink: 0; height: 40px; padding: 0 12px; border: 1px dashed #C98F0F; border-bottom: 0; border-radius: 10px 10px 0 0; background: transparent; color: #8A5A00; font: inherit; font-size: 13px; font-weight: 800; cursor: pointer; white-space: nowrap; }
        .dark .cm-tabsbar { background: rgb(39 39 42); border-color: rgb(63 63 70); }
    </style>

    @if (count($pending))
        <div class="cm-strip" wire:poll.10s>
            <strong style="margin-right: 4px">New online orders</strong>
            @foreach ($pending as $o)
                <div class="cm-pend" wire:key="p-{{ $o->id }}">
                    <span><strong>#{{ substr($o->order_number, -6) }}</strong> · {{ ['delivery' => 'Delivery', 'takeaway' => 'Take Away', 'dine_in' => 'Dine In'][$o->order_type ?? 'delivery'] ?? $o->order_type }} · ₹{{ number_format($o->total, 0) }}</span>
                    <button type="button" class="sp-btn sp-btn-amber" style="height: 34px" wire:click="acceptOnline({{ $o->id }})">Accept</button>
                </div>
            @endforeach
        </div>
    @else
        <div wire:poll.15s></div>
    @endif

    <div class="cm-wrap">
        <section aria-label="Menu" style="display: flex; flex-direction: column; gap: 14px; min-width: 0">
            <input type="search" class="sp-input" style="height: 48px; font-size: 15px; background: #fff" placeholder="Type dish name or initials… (e.g. pt for Paneer Tikka)" aria-label="Search dishes" wire:model.live.debounce.250ms="search" autofocus autocomplete="off">
            <div class="cm-tabs" role="tablist" aria-label="Categories">
                <button type="button" role="tab" class="cm-tab" aria-selected="{{ $category === 'all' ? 'true' : 'false' }}" wire:click="$set('category', 'all')">All</button>
                <button type="button" role="tab" class="cm-tab" aria-selected="{{ $category === 'bestsellers' ? 'true' : 'false' }}" wire:click="$set('category', 'bestsellers')">Bestsellers</button>
                @foreach ($cats as $c)
                    <button type="button" role="tab" class="cm-tab" aria-selected="{{ $category === $c->slug ? 'true' : 'false' }}" wire:click="$set('category', '{{ $c->slug }}')" wire:key="cat-{{ $c->slug }}">{{ $c->name }}</button>
                @endforeach
            </div>
            <div class="sp-muted" style="margin-top: -4px">{{ $menu->count() }} {{ $menu->count() === 1 ? 'dish' : 'dishes' }}{{ $menu->where('is_available', false)->count() ? ' · ' . $menu->where('is_available', false)->count() . ' sold out' : '' }}</div>
            <div class="cm-grid">
                @forelse ($menu as $d)
                    @php $q = $bill[$d->id] ?? 0; @endphp
                    <button type="button" class="cm-dish {{ ! $d->is_available ? 'out' : ($q ? 'in' : '') }}" wire:click="add({{ $d->id }})" wire:key="d-{{ $d->id }}" @disabled(! $d->is_available) aria-label="Add {{ $d->name }}, ₹{{ number_format($d->price, 0) }}{{ $q ? ', ' . $q . ' in bill' : '' }}">
                        <span style="font-size: 14px; font-weight: 700; line-height: 1.25">{{ $d->name }}</span>
                        <span style="display: flex; justify-content: space-between; align-items: center; width: 100%">
                            <span style="font-size: 15px; font-weight: 800">₹{{ number_format($d->price, 0) }}</span>
                            @if (! $d->is_available)<span style="font-size: 11px; font-weight: 800; color: #9F1D1D">SOLD OUT</span>@elseif ($q)<span class="cm-qty">{{ $q }}</span>@endif
                        </span>
                    </button>
                @empty
                    <div class="sp-empty" style="grid-column: 1 / -1">No dishes match.</div>
                @endforelse
            </div>
        </section>

        <aside class="cm-bill" aria-label="Current bill">
            {{-- Open bills: one tab per table / customer --}}
            <div class="cm-tabsbar" role="tablist" aria-label="Open bills">
                @foreach ($tabs as $t)
                    <div class="cm-billtab {{ $t['active'] ? 'on' : '' }}" wire:key="tab-{{ $t['id'] }}">
                        <button type="button" role="tab" aria-selected="{{ $t['active'] ? 'true' : 'false' }}" class="cm-billtab-main" wire:click="switchBill('{{ $t['id'] }}')" title="Opened {{ $t['opened'] }}">
                            <span>{{ $t['label'] }}</span>
                            @if ($t['count'])<span class="cm-billtab-count">{{ $t['count'] }}</span>@endif
                        </button>
                        @if (count($tabs) > 1 || $t['count'])
                            <button type="button" class="cm-billtab-x" aria-label="Close {{ $t['label'] }}" wire:click="closeBill('{{ $t['id'] }}')" @if ($t['count']) wire:confirm="Close {{ $t['label'] }}? Its items will be removed." @endif>×</button>
                        @endif
                    </div>
                @endforeach
                <button type="button" class="cm-newbill" wire:click="newBill" aria-label="Open a new bill">+ New bill</button>
            </div>
            <div style="padding: 16px 18px; border-bottom: 1px solid #F3EAD6; display: flex; flex-direction: column; gap: 10px">
                <div class="sp-row"><h2 style="margin: 0; font-size: 18px; font-weight: 800">{{ collect($tabs)->firstWhere('active', true)['label'] ?? 'Current bill' }}</h2>
                    @if (count($lines))<button type="button" style="background: none; border: 0; color: #9F1D1D; font: inherit; font-weight: 700; cursor: pointer; min-height: 40px" wire:click="clearBill">Clear</button>@endif
                </div>
                <div class="cm-seg" role="group" aria-label="Order type">
                    <button type="button" aria-pressed="{{ $orderType === 'dine_in' ? 'true' : 'false' }}" wire:click="$set('orderType', 'dine_in')">Dine In</button>
                    <button type="button" aria-pressed="{{ $orderType === 'takeaway' ? 'true' : 'false' }}" wire:click="$set('orderType', 'takeaway')">Take Away</button>
                </div>
                <div style="display: grid; grid-template-columns: {{ $orderType === 'dine_in' ? '90px 1fr' : '1fr' }}; gap: 8px">
                    @if ($orderType === 'dine_in')<input class="sp-input" placeholder="Table" aria-label="Table number" wire:model.blur="table">@endif
                    <input class="sp-input" type="tel" placeholder="Phone (optional, for points)" aria-label="Customer phone (optional)" wire:model.blur="phone">
                </div>
                <input class="sp-input" placeholder="Note for kitchen (optional)" aria-label="Note for kitchen" wire:model.blur="note">
            </div>

            <div style="padding: 4px 18px; max-height: 340px; overflow-y: auto">
                @forelse ($lines as $l)
                    <div class="sp-row" style="padding: 10px 0; border-bottom: 1px solid #F3EAD6" wire:key="l-{{ $l['id'] }}">
                        <div style="flex: 1; min-width: 0"><div style="font-size: 14px; font-weight: 700">{{ $l['name'] }}</div><div class="sp-muted">₹{{ number_format($l['price'], 0) }} each</div></div>
                        <div class="cm-step">
                            <button type="button" aria-label="Remove one {{ $l['name'] }}" wire:click="minus({{ $l['id'] }})">−</button>
                            <span style="min-width: 18px; text-align: center; font-weight: 800">{{ $l['qty'] }}</span>
                            <button type="button" aria-label="Add one {{ $l['name'] }}" wire:click="add({{ $l['id'] }})">+</button>
                        </div>
                        <strong style="width: 60px; text-align: right; font-size: 14px">₹{{ number_format($l['total'], 0) }}</strong>
                    </div>
                @empty
                    <div class="sp-empty" style="padding: 28px 0">Tap dishes on the left to add them.</div>
                @endforelse
                @if ($freeLine)
                    <div class="sp-row" style="padding: 10px 0; color: #11622F; font-weight: 700; font-size: 14px"><span>{{ $freeLine['name'] }}</span><span>FREE</span></div>
                @endif
            </div>

            <div style="padding: 14px 18px 18px; border-top: 1px solid #EDE0C4; display: flex; flex-direction: column; gap: 10px; background: #FBF8F1; border-radius: 0 0 18px 18px">
                <div class="sp-row" style="font-size: 14px"><span>Items ({{ array_sum(array_column($lines, 'qty')) }})</span><span>₹{{ number_format($subtotal, 0) }}</span></div>
                @if ($discount > 0)<div class="sp-row" style="font-size: 14px; color: #11622F; font-weight: 700"><span>Offer: {{ $offer?->title }}</span><span>−₹{{ number_format($discount, 0) }}</span></div>@endif
                <div class="sp-row" style="font-size: 22px; font-weight: 900"><span>Total</span><span>₹{{ number_format($total, 0) }}</span></div>
                <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px">
                    <button type="button" class="cm-pay" aria-pressed="{{ $payment === 'cash' ? 'true' : 'false' }}" wire:click="$set('payment', 'cash')">Cash</button>
                    <button type="button" class="cm-pay" aria-pressed="{{ $payment === 'upi' ? 'true' : 'false' }}" wire:click="$set('payment', 'upi')">UPI</button>
                </div>
                <button type="button" class="cm-go" wire:click="placeOrder" wire:loading.attr="disabled" @disabled(! count($lines))>
                    <span wire:loading.remove wire:target="placeOrder">Place order &amp; print · ₹{{ number_format($total, 0) }}</span>
                    <span wire:loading wire:target="placeOrder">Placing…</span>
                </button>
                @if ($lastOrder)
                    <div style="font-size: 13px; background: #E8F6EC; color: #11622F; padding: 8px 10px; border-radius: 10px">
                        Order #{{ substr($lastOrder->order_number, -6) }} placed.
                        <a href="{{ route('admin.order.print', ['order' => $lastOrder->id, 'type' => 'both']) }}" target="_blank" rel="noopener" style="font-weight: 800; color: #11622F">Print again</a>
                    </div>
                @endif
            </div>
        </aside>
    </div>

    {{-- Open the print window when an order is placed (falls back to the "Print again" link if the browser blocks pop-ups) --}}
    <div x-data x-on:sp-print.window="window.open($event.detail.url, '_blank', 'width=420,height=700')"></div>
</x-filament-panels::page>
