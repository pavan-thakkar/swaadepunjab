<x-filament-panels::page>
    <form wire:submit="sendBroadcast" class="space-y-6">
        {{ $this->form }}

        <div class="flex items-center gap-3">
            <x-filament::button type="submit" color="success" size="lg">
                🚀 Send WhatsApp Broadcast
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
