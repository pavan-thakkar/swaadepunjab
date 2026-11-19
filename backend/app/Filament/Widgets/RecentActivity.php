<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Filament\Widgets\Widget;

class RecentActivity extends Widget
{
    protected static string $view = 'filament.widgets.recent-activity';
    protected static ?int $sort = 6;
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isOwner();
    }

    protected function getViewData(): array
    {
        return [
            'logs' => ActivityLog::latest('created_at')->limit(6)->get(),
            'url' => ActivityLogResource::getUrl(),
        ];
    }
}
