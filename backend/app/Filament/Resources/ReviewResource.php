<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Review;
use App\Models\MenuItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Customer Reviews';
    protected static ?string $navigationGroup = 'Catalog';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Review Information')->schema([
                Forms\Components\Select::make('menu_item_id')
                    ->label('Dish')
                    ->relationship('menuItem', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('customer_name')
                    ->label('Customer Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('customer_phone')
                    ->label('Customer Phone')
                    ->maxLength(20),

                Forms\Components\TextInput::make('rating')
                    ->label('Rating (1-5)')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->maxValue(5),

                Forms\Components\Textarea::make('comment')
                    ->label('Comment')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('admin_reply')
                    ->label('Admin Reply')
                    ->placeholder('Enter response to this customer review...')
                    ->rows(2)
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_approved')
                    ->label('Approved (Visible on website)')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('menuItem.name')
                    ->label('Dish Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Phone')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Rating')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => "★ {$state}")
                    ->sortable(),

                Tables\Columns\TextColumn::make('comment')
                    ->label('Comment')
                    ->limit(60)
                    ->placeholder('No comment'),

                Tables\Columns\TextColumn::make('admin_reply')
                    ->label('Admin Reply')
                    ->limit(50)
                    ->placeholder('Pending Reply')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->weight(fn ($state) => $state ? 'normal' : 'bold'),

                Tables\Columns\ToggleColumn::make('is_approved')
                    ->label('Approved'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted On')
                    ->dateTime('d M Y, h:i A')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_approved')
                    ->label('Approved Status'),
                Tables\Filters\SelectFilter::make('menu_item_id')
                    ->label('Filter by Dish')
                    ->relationship('menuItem', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Review')
                    ->modalDescription('Are you sure you want to delete this customer review? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, Delete'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'edit' => Pages\EditReview::route('/{record}/edit'),
        ];
    }
}
