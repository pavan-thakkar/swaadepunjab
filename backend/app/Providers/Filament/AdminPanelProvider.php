<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Support\Assets\Js;
use Filament\View\PanelsRenderHook;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(\App\Filament\Pages\Auth\EditProfile::class, isSimple: false)
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('My Profile & Password')
                    ->icon('heroicon-o-user-circle'),
            ])
            ->navigationItems([
                NavigationItem::make('My Profile & Password')
                    ->url(fn (): string => \App\Filament\Pages\Auth\EditProfile::getUrl())
                    ->icon('heroicon-o-user-circle')
                    ->group('Settings')
                    ->sort(100)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.auth.profile')),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->font('Outfit')
            ->brandName('Swaad E Punjab')
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups(['Live', 'Menu', 'Grow', 'Insights', 'Settings'])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.admin-theme'))
            // "Back" button at the top of create / edit / view pages
            ->renderHook(PanelsRenderHook::PAGE_START, fn (array $scopes) => view('filament.back-button', ['scopes' => $scopes]))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->assets([
                Js::make('canvas-confetti', 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js'),
                Js::make('admin-custom', asset('js/admin-custom.js?v=' . filemtime(public_path('js/admin-custom.js')))),
            ]);
    }
}
