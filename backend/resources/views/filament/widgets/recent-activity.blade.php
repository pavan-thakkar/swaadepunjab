<x-filament-widgets::widget>
    <div class="sp-card" wire:poll.60s>
        <div class="sp-row"><h2>Recent activity</h2><a href="{{ $url }}" style="font-size: 13px; font-weight: 700; color: #8A5A00; text-decoration: none">Activity log</a></div>
        @forelse ($logs as $log)
            <div style="display: flex; gap: 10px; padding: 8px 0; border-top: 1px solid #F3EAD6; font-size: 13px; line-height: 1.4">
                <span class="sp-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($log->user_name ?? '?', 0, 1)) }}</span>
                <div>
                    <div><strong>{{ $log->user_name }}</strong> · {{ \Illuminate\Support\Str::limit($log->description, 90) }}</div>
                    <div class="sp-muted">{{ $log->created_at?->diffForHumans() }}</div>
                </div>
            </div>
        @empty
            <div class="sp-empty">No activity yet.</div>
        @endforelse
    </div>
</x-filament-widgets::widget>
