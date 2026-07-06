<x-filament-panels::page>
    <div class="max-w-2xl mx-auto">
        <form wire:submit="import" class="space-y-6">
            {{-- File Upload Area --}}
            <div
                x-data="{ isDragging: false }"
                x-on:dragover.prevent="isDragging = true"
                x-on:dragleave.prevent="isDragging = false"
                x-on:drop.prevent="isDragging = false"
                class="relative"
            >
                <label
                    for="file-upload"
                    :class="isDragging ? 'border-primary-500 bg-primary-500/10' : 'border-gray-600 hover:border-primary-400'"
                    class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed rounded-xl cursor-pointer transition-all duration-200"
                >
                    @if ($file)
                        <div class="flex flex-col items-center gap-3 text-center">
                            <div class="p-3 rounded-full bg-success-500/20">
                                <x-heroicon-o-document-check class="w-8 h-8 text-success-500" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-white">{{ $file->getClientOriginalName() }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ number_format($file->getSize() / 1024 / 1024, 2) }} MB</p>
                            </div>
                            <p class="text-xs text-gray-500">Click or drag to replace</p>
                        </div>
                    @else
                        <div class="flex flex-col items-center gap-3">
                            <div class="p-3 rounded-full bg-gray-700">
                                <x-heroicon-o-arrow-up-tray class="w-8 h-8 text-gray-400" />
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-medium text-gray-300">
                                    <span class="text-primary-400">Click to upload</span> or drag and drop
                                </p>
                                <p class="text-xs text-gray-500 mt-1">Excel (.xlsx, .xls), CSV, or PDF — up to 100 MB</p>
                            </div>
                        </div>
                    @endif

                    <input
                        id="file-upload"
                        type="file"
                        wire:model="file"
                        accept=".xlsx,.xls,.csv,.pdf"
                        class="sr-only"
                    />
                </label>
            </div>

            {{-- Loading indicator --}}
            <div wire:loading wire:target="file" class="flex items-center gap-2 text-sm text-primary-400">
                <x-filament::loading-indicator class="h-5 w-5" />
                <span>Uploading file…</span>
            </div>

            @error('file')
                <p class="text-sm text-danger-500">{{ $message }}</p>
            @enderror

            {{-- Action Buttons --}}
            <div class="flex gap-3">
                <x-filament::button
                    type="submit"
                    :disabled="!$file || $isProcessing"
                    class="flex-1"
                >
                    @if ($isProcessing)
                        <x-filament::loading-indicator class="h-5 w-5 mr-2" />
                        Processing…
                    @else
                        Import Menu Items
                    @endif
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
