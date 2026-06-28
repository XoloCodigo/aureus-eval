<?php

namespace App\Providers\Filament;

use App\Http\Middleware\SetLocale;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Webkul\Manufacturing\ManufacturingPlugin;
use Webkul\Support\Filament\Pages\Profile;
use Webkul\Support\GlobalSearchProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        set_time_limit(300);

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->favicon(asset('images/favicon.ico'))
            ->brandLogo(asset('images/logo.svg'))
            ->brandLogoHeight('2rem')
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->unsavedChangesAlerts()
            ->topNavigation()
            ->maxContentWidth(Width::Full)
            ->databaseNotifications()
            ->userMenuItems([
                'profile' => Action::make('profile')
                    ->label(fn () => Auth::user()?->name)
                    ->url(fn (): string => Profile::getUrl()),
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.dashboard'))
                    ->icon('icon-dashboard'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.contact'))
                    ->icon('petfood-crm'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.sale'))
                    ->icon('petfood-ventas'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.purchase'))
                    ->icon('petfood-compras'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.manufacturing'))
                    ->icon('petfood-fabricacion'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.inventory'))
                    ->icon('petfood-inventario'),
                NavigationGroup::make()
                    ->label('Calidad e Inocuidad')
                    ->icon('petfood-calidad'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.invoice'))
                    ->icon('petfood-facturas'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.accounting'))
                    ->icon('icon-accounting'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.project'))
                    ->icon('icon-projects'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.employee'))
                    ->icon('petfood-empleados'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.time-off'))
                    ->icon('petfood-ausencias'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.recruitment'))
                    ->icon('petfood-rh'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.website'))
                    ->icon('icon-website'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.plugin'))
                    ->icon('petfood-complementos'),
                NavigationGroup::make()
                    ->label(fn (): string => __('admin.navigation.setting'))
                    ->icon('petfood-configuracion'),
            ])
            ->plugins([
                ManufacturingPlugin::make(),
                FilamentShieldPlugin::make()
                    ->gridColumns([
                        'default' => 1,
                        'sm'      => 1,
                        'lg'      => 2,
                        'xl'      => 3,
                    ])
                    ->sectionColumnSpan(1)
                    ->checkboxListColumns([
                        'default' => 1,
                        'sm'      => 1,
                        'lg'      => 2,
                        'xl'      => 3,
                    ])
                    ->resourceCheckboxListColumns([
                        'default' => 1,
                        'sm'      => 2,
                    ]),
            ])
            ->globalSearch(provider: GlobalSearchProvider::class)
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable(),
            ]);
    }
}
