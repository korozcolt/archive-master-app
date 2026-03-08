<?php

namespace Database\Factories;

use App\Enums\ArchivePhase;
use App\Enums\FinalDisposition;
use App\Models\Company;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
use App\Models\RetentionSchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RetentionSchedule>
 */
class RetentionScheduleFactory extends Factory
{
    protected $model = RetentionSchedule::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'documentary_subseries_id' => DocumentarySubseries::factory(),
            'documentary_type_id' => DocumentaryType::factory(),
            'archive_phase' => ArchivePhase::Gestion,
            'management_years' => 2,
            'central_years' => 8,
            'historical_action' => 'Transferencia secundaria',
            'final_disposition' => FinalDisposition::ConservacionTotal,
            'legal_basis' => 'TRD corporativa',
            'is_active' => true,
        ];
    }
}
