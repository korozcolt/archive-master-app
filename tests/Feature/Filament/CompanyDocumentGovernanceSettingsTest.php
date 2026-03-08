<?php

use App\Filament\Resources\CompanyResource\Pages\EditCompany;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeGovernanceAdmin(Company $company): User
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

it('saves governance settings from the company edit form', function () {
    $company = Company::factory()->create();
    $user = makeGovernanceAdmin($company);

    $this->actingAs($user);

    Livewire::test(EditCompany::class, ['record' => $company->id])
        ->set('data.settings.document_governance.jurisdiction', 'CO')
        ->set('data.settings.document_governance.timezone', 'America/Bogota')
        ->set('data.settings.document_governance.warning_days', ['5', '2', '1'])
        ->set('data.settings.document_governance.escalation_days', 2)
        ->set('data.settings.document_governance.allow_extension', true)
        ->set('data.settings.document_governance.requires_subsanation', true)
        ->set('data.settings.document_governance.archive_requires_trd', true)
        ->set('data.settings.document_governance.archive_requires_access_level', true)
        ->set('data.settings.document_governance.send_due_soon_alerts', true)
        ->set('data.settings.document_governance.send_overdue_alerts', true)
        ->set('data.settings.document_governance.notify_supervisors_on_overdue', true)
        ->set('data.settings.document_governance.send_archive_ready_alerts', true)
        ->set('data.settings.document_governance.send_archive_incomplete_alerts', true)
        ->call('save')
        ->assertHasNoFormErrors();

    $company->refresh();

    expect(data_get($company->settings, 'document_governance.warning_days'))->toBe(['5', '2', '1']);
    expect(data_get($company->settings, 'document_governance.escalation_days'))->toBe(2);
    expect(data_get($company->settings, 'document_governance.archive_requires_trd'))->toBeTrue();
    expect(data_get($company->settings, 'document_governance.send_due_soon_alerts'))->toBeTrue();
    expect(data_get($company->settings, 'document_governance.send_overdue_alerts'))->toBeTrue();
    expect(data_get($company->settings, 'document_governance.notify_supervisors_on_overdue'))->toBeTrue();
    expect(data_get($company->settings, 'document_governance.send_archive_ready_alerts'))->toBeTrue();
    expect(data_get($company->settings, 'document_governance.send_archive_incomplete_alerts'))->toBeTrue();
});
