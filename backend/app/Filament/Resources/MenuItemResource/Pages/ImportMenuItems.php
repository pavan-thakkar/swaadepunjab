<?php

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Filament\Resources\MenuItemResource;
use App\Services\MenuImportService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\WithFileUploads;

class ImportMenuItems extends Page
{
    use WithFileUploads;

    protected static string $resource = MenuItemResource::class;
    protected static ?string $title = 'Import Menu Items';
    protected static string $view = 'filament.resources.menu-item-resource.pages.import-menu-items';

    public $file;
    public bool $isProcessing = false;

    public function import()
    {
        $this->validate([
            'file' => 'required|file|max:102400',
        ]);

        $this->isProcessing = true;

        try {
            $originalName = $this->file->getClientOriginalName();
            $tempPath = $this->file->getRealPath();

            $importService = app(MenuImportService::class);
            $count = $importService->import($tempPath, $originalName);

            Notification::make()
                ->title('Import Successful! ✅')
                ->body("Successfully imported {$count} menu items from \"{$originalName}\".")
                ->success()
                ->send();

            $this->file = null;
            $this->isProcessing = false;

            return redirect(MenuItemResource::getUrl('index'));
        } catch (\Exception $e) {
            $this->isProcessing = false;

            Notification::make()
                ->title('Import Failed ❌')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function cancel()
    {
        return redirect(MenuItemResource::getUrl('index'));
    }
}
