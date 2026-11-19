@php
    $user = auth()->user();
    $show = $user && method_exists($user, 'hasRole') && $user->hasRole(['owner', 'manager']);
    $count = $show ? \App\Models\PlanEnquiry::needsCallCount() : 0;
    $onDashboard = request()->routeIs('filament.admin.pages.dashboard');
    $onList = request()->routeIs('filament.admin.resources.plan-customers.*');
@endphp
@if ($count > 0 && ! $onDashboard && ! $onList)
    {{-- Dismissed for the rest of the day (per browser); comes back tomorrow or when the number changes --}}
    <div x-data="{ k: 'sp-renew-{{ now()->toDateString() }}-{{ $count }}', hide: false }"
         x-init="try { hide = localStorage.getItem(k) === '1' } catch (e) {}"
         x-show="! hide" x-cloak
         style="margin-bottom: 14px; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; background: #FFF8E7; border: 1px solid #F5C26B; border-radius: 14px; padding: 10px 14px; font-size: 14px">
        <span aria-hidden="true">🔔</span>
        <span style="flex: 1; min-width: 200px"><strong>{{ $count }} monthly {{ $count === 1 ? 'plan is' : 'plans are' }} ending soon.</strong> Call to continue the plan.</span>
        <a href="{{ \App\Filament\Resources\PlanCustomerResource::getUrl('index') }}" style="font-weight: 800; color: #8A5A00; text-decoration: none">See customers →</a>
        <button type="button" x-on:click="hide = true; try { localStorage.setItem(k, '1') } catch (e) {}" aria-label="Hide for today" style="background: none; border: 0; cursor: pointer; font-size: 16px; color: #7A6040; padding: 4px">✕</button>
    </div>
@endif
