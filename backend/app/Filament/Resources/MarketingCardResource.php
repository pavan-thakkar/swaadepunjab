<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MarketingCardResource\Pages;
use App\Filament\Resources\MarketingCardResource\RelationManagers;
use App\Models\MarketingCard;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MarketingCardResource extends Resource
{
    use \App\Filament\Concerns\HasRoleAccess;

    protected static function roleAccess(): array
    {
        return ['view' => ['owner', 'manager'], 'edit' => ['owner', 'manager'], 'delete' => ['owner', 'manager']];
    }

    protected static ?string $model = MarketingCard::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Promo Banners';
    protected static ?string $navigationGroup = 'Grow';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('📢 Promotional Banner Details')->schema([
                    Forms\Components\Placeholder::make('video_only_hint')
                        ->hiddenLabel()
                        ->content('🎬 With a video, tag, title and text are optional. Leave them empty to show only the video.')
                        ->visible(fn (Forms\Get $get) => filled($get('video_path')) || filled($get('video_url')))
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('tag')
                        ->label('Promo Tag (e.g. 🔥 BEST SELLER)')
                        ->required(fn (Forms\Get $get) => blank($get('video_path')) && blank($get('video_url')))
                        ->placeholder('🔥 BEST SELLER')
                        ->maxLength(60),
                    Forms\Components\TextInput::make('title')
                        ->label('Banner Title')
                        ->required(fn (Forms\Get $get) => blank($get('video_path')) && blank($get('video_url')))
                        ->placeholder('e.g. Tandoori Paneer Butter Masala')
                        ->maxLength(150),
                    Forms\Components\Textarea::make('description')
                        ->label('Banner Description')
                        ->placeholder('Write a mouth-watering description...')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('price')
                        ->label('Discount Price / Text (e.g. ₹260 or 30% OFF)'),
                    Forms\Components\Select::make('menu_item_id')
                        ->label('Link to Menu Item (adds directly to cart)')
                        ->relationship('menuItem', 'name')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Forms\Components\FileUpload::make('video_path')
                        ->label('Upload video (MP4)')
                        ->helperText('MP4 up to 50 MB (keep it short, 10–30 seconds, for fast loading on phones). Plays on loop in the banner. If both an upload and a link are set, the upload is used.')
                        ->disk('public')
                        ->directory('marketing/videos')
                        ->visibility('public')
                        ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                        ->maxSize(51200)
                        ->openable()
                        ->downloadable()
                        ->live()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('video_url')
                        ->label('…or video link (MP4 URL)')
                        ->placeholder('e.g. https://assets.mixkit.co/...mp4')
                        ->url()
                        ->live(onBlur: true),
                    Forms\Components\Toggle::make('video_sound')
                        ->label('Video has sound — show 🔊 button')
                        ->helperText('Phones and browsers always start videos muted. Customers tap 🔊 on the banner to hear it.')
                        ->default(true)
                        ->inline(false)
                        ->visible(fn (Forms\Get $get) => filled($get('video_path')) || filled($get('video_url'))),
                    Forms\Components\FileUpload::make('image_path')
                        ->label('Cover image (shown while the video loads, or when there is no video)')
                        ->disk('public')
                        ->directory('marketing')
                        ->visibility('public')
                        ->image()
                        ->maxSize(10240)
                        ->columnSpanFull(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Visible on Home Page')
                        ->default(true)
                        ->required(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Display Order')
                        ->required()
                        ->numeric()
                        ->default(0),
                ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Remember filters, search and sorting when the admin leaves and comes back
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->grow(false),
                Tables\Columns\IconColumn::make('video_path')
                    ->label('Video')
                    ->state(fn ($record) => filled($record->video_path) || filled($record->video_url))
                    ->boolean()
                    ->trueIcon(fn ($record) => $record->video_sound ? 'heroicon-o-speaker-wave' : 'heroicon-o-film')
                    ->falseIcon('heroicon-o-minus')
                    ->tooltip(fn ($record) => (filled($record->video_path) || filled($record->video_url)) ? ($record->video_sound ? 'Video with sound' : 'Video (muted)') : 'No video'),

                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Image')
                    ->grow(false),
                Tables\Columns\TextColumn::make('tag')
                    ->searchable()
                    ->badge()
                    ->color('warning')
                    ->grow(false),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->placeholder('🎬 Video only')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Discount/Text')
                    ->searchable()
                    ->grow(false),
                Tables\Columns\TextColumn::make('menuItem.name')
                    ->label('Linked Item')
                    ->sortable()
                    ->placeholder('None'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Visible')
                    ->boolean()
                    ->grow(false),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketingCards::route('/'),
            'create' => Pages\CreateMarketingCard::route('/create'),
            'edit' => Pages\EditMarketingCard::route('/{record}/edit'),
        ];
    }
}
