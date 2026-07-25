<x-filament-panels::page>
    <div class="max-w-2xl mx-auto space-y-6">
        <form wire:submit="import" class="space-y-6">
            {{ $this->form }}

            <div class="flex gap-3 pt-2">
                <x-filament::button
                    type="submit"
                    class="flex-1"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="import">Import Menu Items</span>
                    <span wire:loading wire:target="import">Processing & Parsing Menu...</span>
                </x-filament::button>

                <x-filament::button
                    color="gray"
                    wire:click="cancel"
                    type="button"
                >
                    Cancel
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
