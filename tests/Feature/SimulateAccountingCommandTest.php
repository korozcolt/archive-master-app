<?php

use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\OCRService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

function makeTestDocument(Company $company, Category $category, array $overrides = []): Document
{
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $department = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]);
    $status = Status::factory()->create(['company_id' => $company->id]);
    $user = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
    ]);

    return Document::factory()->create(array_merge([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'status_id' => $status->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'metadata' => [],
    ], $overrides));
}

it('runs simulate accounting command and generates json export', function () {
    Storage::fake('local');

    $company = Company::factory()->create(['id' => 1, 'name' => 'Aguas de Sucre S.A.']);

    // Create AI Settings for Company
    CompanyAiSetting::create([
        'company_id' => $company->id,
        'provider' => 'gemini',
        'api_key_encrypted' => 'fake-key',
        'is_enabled' => true,
    ]);

    // ID 31 is Financiera y Contable
    $category = Category::factory()->create(['id' => 31, 'company_id' => $company->id]);

    $doc = makeTestDocument($company, $category, [
        'title' => 'EGRESO 01 TEST',
        'file_path' => 'documents/egreso-01.pdf',
        'created_at' => '2026-05-15 10:00:00',
    ]);

    mock(OCRService::class, function (MockInterface $mock) use ($doc): void {
        $mock->shouldReceive('processFile')->once()->with($doc->file_path, 'spa')->andReturn([
            'success' => true,
            'extracted_text' => 'CONCEPTO: Pago de servicios CARLOS GARRIDO NIT: 92641675 VALOR: $5.337.000,00',
        ]);
    });

    mock(AiGateway::class, function (MockInterface $mock): void {
        $mock->shouldReceive('extractAccounting')->once()->andReturn([
            'provider' => 'gemini',
            'fecha' => '2026-05-15',
            'numero_documento' => '01',
            'beneficiario' => 'CARLOS GARRIDO',
            'nit' => '92641675',
            'concepto' => 'Pago de servicios',
            'cuentas_contables' => [
                [
                    'codigo' => '510506',
                    'descripcion' => 'Sueldos',
                    'debito' => 5337000,
                    'credito' => 0,
                ],
                [
                    'codigo' => '111005',
                    'descripcion' => 'Bancos',
                    'debito' => 0,
                    'credito' => 5337000,
                ],
            ],
            'total' => 5337000,
        ]);
    });

    $exitCode = Artisan::call('app:simulate-accounting', [
        '--year' => 2026,
        '--limit' => 10,
        '--company' => $company->id,
    ]);

    expect($exitCode)->toBe(0);

    // Verify that a file with format accounting_simulation_1_2026_*.json is in the fake storage
    $files = Storage::disk('local')->files();
    $matchedFile = null;
    foreach ($files as $file) {
        if (preg_match('/^accounting_simulation_1_2026_.*\.json$/', $file)) {
            $matchedFile = $file;
            break;
        }
    }

    expect($matchedFile)->not->toBeNull();

    $json = json_decode(Storage::disk('local')->get($matchedFile), true);
    expect($json['meta']['total_processed'])->toBe(1)
        ->and($json['transactions'][0]['beneficiario'])->toBe('CARLOS GARRIDO')
        ->and($json['transactions'][0]['total'])->toBe(5337000);
});
