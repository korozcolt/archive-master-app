<?php

namespace App\Providers;

use App\Events\DocumentUpdated;
use App\Listeners\SendDocumentUpdateNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
        // Registrar listeners de eventos
        Event::listen(
            DocumentUpdated::class,
            SendDocumentUpdateNotification::class
        );
    }
}
