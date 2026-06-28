<?php

namespace App\Providers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Webkul\Security\Models\User;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Authenticatable::class, User::class);
    }

    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // Mexican date format (día/mes/año) for every Filament date input,
        // instead of Filament's US default ("may 27, 2026").
        DatePicker::configureUsing(
            fn (DatePicker $component) => $component->displayFormat('d/m/Y')
        );
        DateTimePicker::configureUsing(
            fn (DateTimePicker $component) => $component->displayFormat('d/m/Y H:i')
        );
    }
}
