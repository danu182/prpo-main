<?php

namespace App\Providers;

// use Illuminate\Auth\Access\Gate as AccessGate;
// use Illuminate\Support\Facades\Gate;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator; // 1. TAMBAHKAN BARIS INI

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 2. TAMBAHKAN BARIS INI UNTUK MEMAKAI BOOTSTRAP 5
        Paginator::useBootstrapFive();

        Gate::before(function ($user, $ability) {
        return $user->hasRole('Super Admin') ? true : null;

        if (env('APP_ENV') !== 'local') {
            URL::forceScheme('https');
        }
        
        // Atau jika ingin selalu HTTPS saat pakai ngrok:
        if (str_contains(request()->url(), 'ngrok-free.app')) {
            URL::forceScheme('https');
        }

    });
    }
}
