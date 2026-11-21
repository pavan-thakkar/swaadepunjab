<?php

namespace App\Filament\Support;

use Filament\Forms;

/** "Sell only at certain times" fields (used on dishes and on categories). */
class SellingHours
{
    public static function section(string $forWhat = 'dish'): Forms\Components\Section
    {
        $times = [];
        for ($m = 0; $m < 24 * 60; $m += 30) {
            $hm = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
            $times[$hm] = \App\Support\TimeWindow::format($hm);
        }

        return Forms\Components\Section::make('🕐 Selling hours')
            ->description($forWhat === 'category'
                ? 'Sell every dish in this category only at certain times, e.g. Breakfast 4 PM – 7 PM. A dish with its own hours uses those instead.'
                : 'Sell this dish only at certain times, e.g. Maggi 4 PM – 7 PM. Leave off to sell all day (or to use its category\'s hours).')
            ->collapsible()
            ->collapsed(fn ($record) => ! $record?->time_limited)
            ->schema([
                Forms\Components\Toggle::make('time_limited')
                    ->label($forWhat === 'category' ? 'Sell this category only at certain times' : 'Sell only at certain times')
                    ->live()
                    ->columnSpanFull(),
                Forms\Components\Select::make('available_from')
                    ->label('From')
                    ->options($times)
                    ->native(false)
                    ->searchable()
                    ->required(fn (Forms\Get $get) => (bool) $get('time_limited'))
                    ->visible(fn (Forms\Get $get) => (bool) $get('time_limited')),
                Forms\Components\Select::make('available_until')
                    ->label('Until')
                    ->options($times)
                    ->native(false)
                    ->searchable()
                    ->required(fn (Forms\Get $get) => (bool) $get('time_limited'))
                    ->different('available_from')
                    ->helperText('Late-night works too, e.g. 10 PM → 2 AM.')
                    ->visible(fn (Forms\Get $get) => (bool) $get('time_limited')),
                Forms\Components\Radio::make('outside_hours')
                    ->label('Outside these hours')
                    ->options([
                        'show' => 'Show on the website as "Available 4 PM – 7 PM" (customers can\'t add it)',
                        'hide' => 'Hide it from the website',
                    ])
                    ->default('show')
                    ->dehydrateStateUsing(fn ($state) => in_array($state, ['show', 'hide'], true) ? $state : 'show')
                    ->visible(fn (Forms\Get $get) => (bool) $get('time_limited'))
                    ->columnSpanFull(),
            ])->columns(2);
    }

    /** Short text for admin lists, e.g. "🕐 4 PM – 7 PM". */
    public static function badge($record): ?string
    {
        if (! $record || ! $record->time_limited || ! $record->available_from || ! $record->available_until) {
            return null;
        }
        return '🕐 ' . \App\Support\TimeWindow::label($record->available_from, $record->available_until)
            . ($record->outside_hours === 'hide' ? ' (hidden other times)' : '');
    }
}
