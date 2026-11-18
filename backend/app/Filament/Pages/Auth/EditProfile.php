<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

class EditProfile extends BaseEditProfile
{
    // Show inside the admin layout (with sidebar), not as a bare page
    public static function isSimple(): bool
    {
        return false;
    }

    public function getTitle(): string
    {
        return 'My Profile';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Profile Information')
                ->description('Update your name and login email.')
                ->schema([
                    $this->getNameFormComponent(),
                    $this->getEmailFormComponent(),
                ])->columns(2),

            Section::make('Change Password')
                ->description('Leave blank to keep your current password.')
                ->schema([
                    $this->getPasswordFormComponent()->label('New Password'),
                    $this->getPasswordConfirmationFormComponent()->label('Confirm New Password'),
                    TextInput::make('currentPassword')
                        ->label('Current Password')
                        ->password()
                        ->revealable()
                        ->required()
                        ->currentPassword()
                        ->visible(fn (Get $get): bool => filled($get('password')))
                        ->dehydrated(false),
                ]),
        ]);
    }
}
