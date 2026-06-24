<?php

namespace App\Providers;

use App\Contracts\PdfRenderer;
use App\Events\DocumentUpdated;
use App\Events\DocumentVersionCreated;
use App\Listeners\QueueDocumentVersionAiPipeline;
use App\Listeners\SendDocumentUpdateNotification;
use App\Services\Pdf\DompdfPdfRenderer;
use App\Services\Pdf\PdfStudioPdfRenderer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('ai.php'), 'ai');

        $this->app->bind(PdfRenderer::class, function ($app): PdfRenderer {
            $wantsPdfStudio = config('reports.pdf.renderer') === 'pdf-studio';
            $pdfStudioEnabled = (bool) config('reports.pdf.pdf_studio.enabled', false);

            if ($wantsPdfStudio && $pdfStudioEnabled) {
                return $app->make(PdfStudioPdfRenderer::class);
            }

            return $app->make(DompdfPdfRenderer::class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production') && str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
        // Registrar listeners de eventos
        Event::listen(
            DocumentUpdated::class,
            SendDocumentUpdateNotification::class
        );

        Event::listen(
            DocumentVersionCreated::class,
            QueueDocumentVersionAiPipeline::class
        );

        RateLimiter::for('ai-actions', function (Request $request) {
            $user = $request->user();
            $userId = $user?->id ?? $request->ip();
            $companyId = $user?->company_id ?? 'guest';
            $limitPerHour = max(1, (int) config('ai.limits.actions_per_hour', 30));

            return Limit::perHour($limitPerHour)->by('ai-actions:'.$companyId.':'.$userId);
        });
    }
}
