<?php

namespace App\Providers\Filament;

use App\Http\Middleware\RedirectBasedOnRole;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelTranslatablePlugin;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->plugin(
                SpatieLaravelTranslatablePlugin::make()
                    ->defaultLocales(['en', 'es']),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                \App\Filament\Widgets\ProductivityStatsWidget::class,
                \App\Filament\Widgets\QuickActionsWidget::class,
                \App\Filament\Widgets\NotificationsWidget::class,
                \App\Filament\Widgets\StatsOverview::class,
                \App\Filament\Widgets\CompanyStatsWidget::class,
                \App\Filament\Widgets\DocumentsByStatus::class,
                \App\Filament\Widgets\CategoryDepartmentWidget::class,
                \App\Filament\Widgets\RecentDocuments::class,
                \App\Filament\Widgets\UserActivityWidget::class,
                \App\Filament\Widgets\OverdueDocuments::class,
                \App\Filament\Widgets\WorkflowStatsWidget::class,
                \App\Filament\Widgets\RecentActivity::class,
                // Phase 3: Reports & Analytics Widgets
                \App\Filament\Widgets\ReportsAnalyticsWidget::class,
                \App\Filament\Widgets\DocumentsTrendChart::class,
                \App\Filament\Widgets\DepartmentDistributionChart::class,
                \App\Filament\Widgets\SlaComplianceChart::class,
                // Phase 3 Week 6: Advanced Reports & Performance Metrics
                \App\Filament\Widgets\PerformanceMetricsWidget::class,
                \App\Filament\Widgets\PerformanceTrendsChart::class,
            ])
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
                RedirectBasedOnRole::class,
            ])
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => Blade::render(<<<'BLADE'
                    <div class="mt-4 text-center">
                        <a href="{{ route('login') }}" class="text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400 dark:hover:text-primary-300">
                            Ir a Portal
                        </a>
                    </div>
                BLADE)
            )
            ->renderHook(
                'panels::body.end',
                fn (): string => Blade::render('<x-quick-search />')
            );
    }
}
