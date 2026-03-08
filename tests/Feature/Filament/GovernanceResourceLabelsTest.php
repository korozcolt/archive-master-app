<?php

use App\Filament\Resources\BusinessCalendarResource;
use App\Filament\Resources\DocumentarySeriesResource;
use App\Filament\Resources\DocumentarySubseriesResource;
use App\Filament\Resources\DocumentaryTypeResource;
use App\Filament\Resources\RetentionScheduleResource;
use App\Filament\Resources\SlaPolicyResource;
use Illuminate\Support\Facades\File;

it('uses spanish labels for governance resources', function (): void {
    expect(SlaPolicyResource::getPluralModelLabel())->toBe('políticas SLA')
        ->and(BusinessCalendarResource::getPluralModelLabel())->toBe('calendarios hábiles')
        ->and(DocumentarySeriesResource::getPluralModelLabel())->toBe('series TRD')
        ->and(DocumentarySubseriesResource::getPluralModelLabel())->toBe('subseries TRD')
        ->and(DocumentaryTypeResource::getPluralModelLabel())->toBe('tipos documentales')
        ->and(RetentionScheduleResource::getPluralModelLabel())->toBe('tablas de retención');
});

it('does not reload alpine from the portal layout', function (): void {
    $layout = File::get(resource_path('views/layouts/app.blade.php'));

    expect($layout)->not->toContain('cdn.jsdelivr.net/npm/alpinejs');
});
