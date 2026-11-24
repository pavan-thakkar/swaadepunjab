<?php

namespace App\Filament;

use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ManageRecords;
use Filament\Resources\Pages\Page as ResourcePage;

/**
 * Works out where the "Back" button at the top of an admin page should go.
 * - Create / Edit / View pages → that section's list (keeps its remembered filters)
 * - Other pages (Counter, Reports, WhatsApp, My Profile) → Dashboard
 * - Lists and the Dashboard itself → no button
 */
class BackLink
{
    public static function for(array $scopes): ?array
    {
        foreach ($scopes as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }
            // Full-screen work screens use all the height: no back button
            if (in_array($class, [\App\Filament\Pages\ManageOrders::class, \App\Filament\Pages\KitchenBoard::class, \App\Filament\Pages\MyDeliveries::class], true)) {
                return null;
            }
            if (is_subclass_of($class, ListRecords::class) || is_subclass_of($class, ManageRecords::class)
                || $class === Dashboard::class || is_subclass_of($class, Dashboard::class)) {
                return null;
            }
            if (is_subclass_of($class, ResourcePage::class)) {
                try {
                    $resource = $class::getResource();
                    if ($resource::hasPage('index')) {
                        return ['url' => $resource::getUrl('index'), 'label' => 'Back to ' . $resource::getPluralModelLabel()];
                    }
                } catch (\Throwable $e) {
                    return null;
                }
                return null;
            }
            if (is_subclass_of($class, Page::class)) {
                return ['url' => Dashboard::getUrl(), 'label' => 'Back to Dashboard'];
            }
        }
        return null;
    }
}
