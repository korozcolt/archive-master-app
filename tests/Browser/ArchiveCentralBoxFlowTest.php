<?php

use App\Enums\ArchivePhase;
use App\Enums\DocumentAccessLevel;
use App\Enums\Role as AppRole;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentarySeries;
use App\Models\DocumentarySubseries;
use App\Models\DocumentaryType;
use App\Models\PhysicalLocation;
use App\Models\PhysicalLocationTemplate;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role as SpatieRole;

beforeEach(function (): void {
    $this->archiveDuskPrefix = 'AM-DUSK-'.Str::upper(Str::random(8));
    $this->archiveDuskUploadPath = storage_path("app/{$this->archiveDuskPrefix}.pdf");

    file_put_contents($this->archiveDuskUploadPath, "%PDF-1.4\n% {$this->archiveDuskPrefix}\n");
});

afterEach(function (): void {
    if (isset($this->archiveDuskUploadPath) && file_exists($this->archiveDuskUploadPath)) {
        unlink($this->archiveDuskUploadPath);
    }

    if (! isset($this->archiveDuskCompanyId)) {
        return;
    }

    DB::transaction(function (): void {
        $documents = Document::query()
            ->withTrashed()
            ->where('company_id', $this->archiveDuskCompanyId)
            ->get();

        foreach ($documents as $document) {
            $document->forceDelete();
        }

        PhysicalLocation::query()
            ->withTrashed()
            ->where('company_id', $this->archiveDuskCompanyId)
            ->forceDelete();

        DocumentaryType::query()->where('company_id', $this->archiveDuskCompanyId)->delete();
        DocumentarySubseries::query()->where('company_id', $this->archiveDuskCompanyId)->delete();
        DocumentarySeries::query()->where('company_id', $this->archiveDuskCompanyId)->delete();
        Category::query()->withTrashed()->where('company_id', $this->archiveDuskCompanyId)->forceDelete();
        Status::query()->withTrashed()->where('company_id', $this->archiveDuskCompanyId)->forceDelete();
        Department::query()->where('company_id', $this->archiveDuskCompanyId)->delete();
        Branch::query()->where('company_id', $this->archiveDuskCompanyId)->delete();
        PhysicalLocationTemplate::query()->where('company_id', $this->archiveDuskCompanyId)->delete();
        User::query()->where('company_id', $this->archiveDuskCompanyId)->delete();
        Company::query()->whereKey($this->archiveDuskCompanyId)->delete();
    });
});

function createArchiveCentralBrowserScenario(object $testCase, string $prefix): array
{
    $company = Company::factory()->create(['name' => "{$prefix} Empresa"]);
    $testCase->archiveDuskCompanyId = $company->id;

    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $archiveDepartment = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => ['es' => "{$prefix} Archivo Central"],
        'code' => 'AC170',
    ]);
    $producerDepartment = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'name' => ['es' => "{$prefix} Gerencia"],
        'code' => 'G100',
    ]);

    $archiveManager = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $archiveDepartment->id,
        'email' => Str::lower("{$prefix}-manager@example.test"),
        'is_active' => true,
    ]);
    $archiveOperator = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $archiveDepartment->id,
        'email' => Str::lower("{$prefix}-operator@example.test"),
        'is_active' => true,
    ]);

    $archiveManager->assignRole(SpatieRole::firstOrCreate([
        'name' => AppRole::ArchiveManager->value,
        'guard_name' => 'web',
    ]));
    $archiveOperator->assignRole(SpatieRole::firstOrCreate([
        'name' => AppRole::ArchiveOperator->value,
        'guard_name' => 'web',
    ]));

    $category = Category::factory()->create([
        'company_id' => $company->id,
        'name' => ['es' => "{$prefix} Contratos"],
        'slug' => Str::slug("{$prefix} contratos"),
    ]);
    Status::factory()->create([
        'company_id' => $company->id,
        'name' => ['es' => 'Archivado'],
        'slug' => 'archivado',
        'active' => true,
    ]);

    $template = PhysicalLocationTemplate::query()->create([
        'company_id' => $company->id,
        'name' => "{$prefix} Plantilla Caja",
        'description' => 'Plantilla Dusk de archivo central por caja.',
        'is_active' => true,
        'levels' => [
            ['order' => 1, 'name' => 'Nivel', 'code' => 'NIV', 'required' => true],
            ['order' => 2, 'name' => 'Archivo', 'code' => 'ARC', 'required' => true],
            ['order' => 3, 'name' => 'Estante', 'code' => 'EST', 'required' => true],
            ['order' => 4, 'name' => 'Entrepano', 'code' => 'ENT', 'required' => true],
            ['order' => 5, 'name' => 'Caja', 'code' => 'CJ', 'required' => true],
        ],
    ]);

    $boxOne = PhysicalLocation::query()->create([
        'company_id' => $company->id,
        'template_id' => $template->id,
        'structured_data' => [
            'nivel' => 'Sotano',
            'archivo' => 'Principal',
            'estante' => '01',
            'entrepano' => '01',
            'caja' => '001',
        ],
        'capacity_total' => 10,
        'capacity_used' => 0,
        'is_active' => true,
    ]);
    $boxTwo = PhysicalLocation::query()->create([
        'company_id' => $company->id,
        'template_id' => $template->id,
        'structured_data' => [
            'nivel' => 'Sotano',
            'archivo' => 'Principal',
            'estante' => '01',
            'entrepano' => '01',
            'caja' => '002',
        ],
        'capacity_total' => 10,
        'capacity_used' => 0,
        'is_active' => true,
    ]);

    $series = DocumentarySeries::factory()->create([
        'company_id' => $company->id,
        'department_id' => $producerDepartment->id,
        'code' => 'G100',
        'name' => "{$prefix} Contratos",
    ]);
    $subseries = DocumentarySubseries::factory()->create([
        'company_id' => $company->id,
        'department_id' => $producerDepartment->id,
        'documentary_series_id' => $series->id,
        'code' => 'G100-01',
        'name' => "{$prefix} Contratos de obra",
    ]);
    $documentaryType = DocumentaryType::factory()->create([
        'company_id' => $company->id,
        'department_id' => $producerDepartment->id,
        'documentary_subseries_id' => $subseries->id,
        'code' => 'G100-01-01',
        'name' => "{$prefix} Contrato",
        'access_level_default' => DocumentAccessLevel::Interno,
    ]);

    return compact(
        'archiveManager',
        'archiveOperator',
        'category',
        'producerDepartment',
        'boxOne',
        'boxTwo',
        'series',
        'subseries',
        'documentaryType',
    );
}

function submitHistoricalBoxUpload(Browser $browser, array $scenario, string $reference, string $uploadPath): void
{
    $browser
        ->visit('/documents/historical/create')
        ->waitFor('@historical-shelf-select')
        ->assertSee('Estante')
        ->assertSee('Entrepano')
        ->assertSee('Caja')
        ->select('@historical-shelf-select', '01')
        ->pause(250)
        ->select('@historical-bay-select', '01')
        ->pause(250)
        ->select('@historical-box-select', (string) $scenario['boxOne']->id)
        ->select('category_id', (string) $scenario['category']->id)
        ->select('original_department_id', (string) $scenario['producerDepartment']->id)
        ->select('@historical-row-type-select', (string) $scenario['documentaryType']->id)
        ->attach('@historical-row-file', $uploadPath)
        ->type('@historical-row-folder', 'Carpeta Dusk')
        ->type('@historical-row-volume', 'Tomo 1')
        ->type('@historical-row-year', '2024')
        ->type('@historical-row-reference', $reference)
        ->type('@historical-row-description', 'Carga de navegador por caja')
        ->press('@historical-submit')
        ->waitForText('archivo central', 10);
}

test('archive manager completes the browser box intake and moves the stored document', function (): void {
    $scenario = createArchiveCentralBrowserScenario($this, $this->archiveDuskPrefix);
    $reference = "{$this->archiveDuskPrefix}-MANAGER";

    $this->browse(function (Browser $browser) use ($scenario, $reference): void {
        $browser->loginAs($scenario['archiveManager']);

        submitHistoricalBoxUpload($browser, $scenario, $reference, $this->archiveDuskUploadPath);

        $document = Document::query()->where('title', $reference)->firstOrFail();

        expect($document->archive_phase)->toBe(ArchivePhase::Central)
            ->and($document->physical_location_id)->toBe($scenario['boxOne']->id)
            ->and($document->documentary_type_id)->toBe($scenario['documentaryType']->id)
            ->and($document->trd_series_id)->toBe($scenario['series']->id)
            ->and($document->trd_subseries_id)->toBe($scenario['subseries']->id);

        $browser
            ->visit("/documents/{$document->id}?edit_location=1")
            ->waitFor('@archive-location-select')
            ->select('@archive-location-select', (string) $scenario['boxTwo']->id)
            ->type('archive_note', 'Movimiento Dusk de administrador de archivo')
            ->press('@archive-location-submit')
            ->pause(1000);

        $document->refresh();

        expect($document->physical_location_id)->toBe($scenario['boxTwo']->id)
            ->and($document->locationHistory()->where('movement_type', 'moved')->exists())->toBeTrue();
    });
});

test('archive operator completes browser intake but cannot access location correction controls', function (): void {
    $scenario = createArchiveCentralBrowserScenario($this, $this->archiveDuskPrefix);
    $reference = "{$this->archiveDuskPrefix}-OPERATOR";

    $this->browse(function (Browser $browser) use ($scenario, $reference): void {
        $browser->loginAs($scenario['archiveOperator']);

        submitHistoricalBoxUpload($browser, $scenario, $reference, $this->archiveDuskUploadPath);

        $document = Document::query()->where('title', $reference)->firstOrFail();

        expect($document->created_by)->toBe($scenario['archiveOperator']->id)
            ->and($document->archive_phase)->toBe(ArchivePhase::Central)
            ->and($document->physical_location_id)->toBe($scenario['boxOne']->id);

        $browser
            ->visit("/documents/{$document->id}?edit_location=1")
            ->waitForText($reference)
            ->assertMissing('@archive-location-select')
            ->assertDontSee('Mover / actualizar');
    });
});
