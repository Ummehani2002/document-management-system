<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Azure\Provider as AzureProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

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
        // Default Laravel pagination uses Tailwind classes; this app uses plain CSS in layouts.app.
        Paginator::useBootstrapFive();

        Route::pattern('project', '[0-9]+');
        Route::pattern('entity', '[0-9]+');
        Route::pattern('folder', '[0-9]+');
        Route::pattern('subfolder', '[0-9]+');

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('azure', AzureProvider::class);
        });
    }
}
