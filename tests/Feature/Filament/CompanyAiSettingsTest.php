<?php

use App\Filament\Resources\CompanyResource\Pages\AiObservability;
use App\Filament\Resources\CompanyResource\Pages\EditCompany;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\DocumentAiRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeSuperAdminForCompany(Company $company): User
{
    $user = User::factory()->create([
        'company_id' => $company->id,
    ]);

    $role = Role::firstOrCreate([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);
    $user->assignRole($role);

    return $user;
}

it('saves ai settings from company edit form', function () {
    $company = Company::factory()->create();
    $user = makeSuperAdminForCompany($company);

    $this->actingAs($user);

    Livewire::test(EditCompany::class, ['record' => $company->id])
        ->set('data.ai_setting.provider', 'openai')
        ->set('data.ai_setting.is_enabled', true)
        ->set('data.ai_setting.api_key_encrypted', 'sk-test-company-ai')
        ->set('data.ai_setting.daily_doc_limit', 120)
        ->set('data.ai_setting.max_pages_per_doc', 80)
        ->set('data.ai_setting.monthly_budget_cents', 500000)
        ->set('data.ai_setting.store_outputs', true)
        ->set('data.ai_setting.redact_pii', true)
        ->call('save')
        ->assertHasNoFormErrors();

    $setting = CompanyAiSetting::query()->where('company_id', $company->id)->first();
    expect($setting)->not->toBeNull();
    expect($setting->provider)->toBe('openai');
    expect($setting->is_enabled)->toBeTrue();
    expect($setting->api_key_encrypted)->toBe('sk-test-company-ai');
    expect($setting->daily_doc_limit)->toBe(120);
    expect($setting->max_pages_per_doc)->toBe(80);
});

it('does not overwrite existing api key when edit form submits blank key', function () {
    $company = Company::factory()->create();
    $user = makeSuperAdminForCompany($company);

    CompanyAiSetting::query()->create([
        'company_id' => $company->id,
        'provider' => 'openai',
        'api_key_encrypted' => 'sk-existing-company-key',
        'is_enabled' => true,
        'daily_doc_limit' => 100,
        'max_pages_per_doc' => 100,
        'store_outputs' => true,
        'redact_pii' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(EditCompany::class, ['record' => $company->id])
        ->set('data.ai_setting.provider', 'openai')
        ->set('data.ai_setting.is_enabled', true)
        ->set('data.ai_setting.daily_doc_limit', 210)
        ->call('save')
        ->assertHasNoFormErrors();

    $setting = CompanyAiSetting::query()->where('company_id', $company->id)->firstOrFail();
    expect($setting->api_key_encrypted)->toBe('sk-existing-company-key');
    expect($setting->daily_doc_limit)->toBe(210);
});

it('can execute ai test and sample actions from company edit page', function () {
    $company = Company::factory()->create();
    $user = makeSuperAdminForCompany($company);

    CompanyAiSetting::query()->create([
        'company_id' => $company->id,
        'provider' => 'openai',
        'api_key_encrypted' => 'sk-openai-company-test',
        'is_enabled' => true,
        'daily_doc_limit' => 100,
        'max_pages_per_doc' => 100,
        'store_outputs' => true,
        'redact_pii' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(EditCompany::class, ['record' => $company->id])
        ->callAction('testAiProvider')
        ->callAction('runAiSample')
        ->assertHasNoErrors();
});

it('shows ai observability metrics in company edit form', function () {
    $company = Company::factory()->create();
    $user = makeSuperAdminForCompany($company);

    DocumentAiRun::factory()->create([
        'company_id' => $company->id,
        'status' => 'success',
        'provider' => 'openai',
        'cost_cents' => 250,
    ]);
    DocumentAiRun::factory()->create([
        'company_id' => $company->id,
        'status' => 'failed',
        'provider' => 'gemini',
        'error_message' => 'Provider timeout while summarizing.',
    ]);

    $this->actingAs($user);

    Livewire::test(EditCompany::class, ['record' => $company->id])
        ->assertSee('Observabilidad IA')
        ->assertSee('Costo mensual acumulado')
        ->assertSee('OpenAI: 0 | Gemini: 1')
        ->assertSee('Provider timeout while summarizing.');
});

it('renders dedicated ai observability page with provider and daily aggregates', function () {
    $company = Company::factory()->create();
    $user = makeSuperAdminForCompany($company);

    DocumentAiRun::factory()->create([
        'company_id' => $company->id,
        'status' => 'success',
        'provider' => 'openai',
        'cost_cents' => 300,
    ]);
    DocumentAiRun::factory()->create([
        'company_id' => $company->id,
        'status' => 'failed',
        'provider' => 'gemini',
        'error_message' => 'Provider unavailable.',
    ]);

    $this->actingAs($user);

    Livewire::test(AiObservability::class, ['record' => $company->id])
        ->assertSee('Por proveedor (mes actual)')
        ->assertSee('Últimos 7 días')
        ->assertSee('OPENAI')
        ->assertSee('GEMINI')
        ->assertSee('$3.00');
});

it('exports ai observability csv from dedicated page', function () {
    $company = Company::factory()->create();
    $user = makeSuperAdminForCompany($company);

    DocumentAiRun::factory()->create([
        'company_id' => $company->id,
        'status' => 'success',
        'provider' => 'openai',
        'cost_cents' => 150,
    ]);

    $this->actingAs($user);

    Livewire::test(AiObservability::class, ['record' => $company->id])
        ->call('exportCsv')
        ->assertFileDownloaded();
});
