@php $back = \App\Filament\BackLink::for($scopes ?? []); @endphp
@if ($back)
    <div style="margin-bottom: -0.5rem">
        <a href="{{ $back['url'] }}"
           style="display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 0 14px; border-radius: 10px; border: 1px solid #EDE0C4; background: #fff; color: #1A0F00; font-weight: 700; font-size: 14px; text-decoration: none; box-shadow: 0 1px 2px rgba(100,60,0,.06)"
           onmouseover="this.style.borderColor='#C98F0F'" onmouseout="this.style.borderColor='#EDE0C4'">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>
            {{ $back['label'] }}
        </a>
    </div>
@endif
