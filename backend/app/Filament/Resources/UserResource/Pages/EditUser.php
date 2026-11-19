<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Never let the Owner lock themselves out
        if ($this->record->id === auth()->id()) {
            unset($data['role'], $data['is_active']);
        }
        // Keep at least one active Owner
        $demoting = $this->record->role === 'owner' && (($data['role'] ?? 'owner') !== 'owner' || (isset($data['is_active']) && ! $data['is_active']));
        if ($demoting && User::where('role', 'owner')->where('is_active', true)->count() <= 1) {
            unset($data['role'], $data['is_active']);
            \Filament\Notifications\Notification::make()->title('You need at least one active Owner, so the role was not changed.')->warning()->send();
        }
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
