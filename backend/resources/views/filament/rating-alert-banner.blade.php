@php
    $user = auth()->user();
    $show = $user && method_exists($user, 'hasRole') && $user->hasRole(['owner', 'manager']);
    $count = $show ? \App\Models\Order::badRatingsToCall() : 0;
    $onList = request()->routeIs('filament.admin.resources.order-ratings.*');
@endphp
@if ($count > 0 && ! $onList)
    <div x-data="{ k: 'sp-badrate-{{ now()->toDateString() }}-{{ $count }}', hide: false }"
         x-init="try { hide = localStorage.getItem(k) === '1' } catch (e) {}"
         x-show="! hide" x-cloak
         style="margin-bottom: 14px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 14px; padding: 10px 14px; font-size: 14px">
        <span aria-hidden="true">😞</span>
        <span style="flex: 1; min-width: 200px"><strong>{{ $count }} low {{ $count === 1 ? 'rating' : 'ratings' }} (1–2 ★).</strong> Call the {{ $count === 1 ? 'customer' : 'customers' }} to fix it before they leave a bad review.</span>
        <a href="{{ \App\Filament\Resources\OrderFeedbackResource::getUrl('index') }}" style="font-weight: 800; color: #B91C1C; text-decoration: none">See ratings →</a>
        <button type="button" x-on:click="hide = true; try { localStorage.setItem(k, '1') } catch (e) {}" aria-label="Hide for today" style="background: none; border: 0; cursor: pointer; font-size: 16px; color: #7A6040; padding: 4px">✕</button>
    </div>
@endif
