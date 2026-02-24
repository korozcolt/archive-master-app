<?php

namespace Tests\Browser;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class PortalAccessTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_portal_roles_can_access_portal_and_reports(): void
    {
        $roles = ['office_manager', 'archive_manager', 'receptionist', 'regular_user'];
        $company = Company::factory()->create();

        foreach ($roles as $roleName) {
            $user = User::factory()->create([
                'company_id' => $company->id,
                'email' => "{$roleName}@example.com",
            ]);
            $role = Role::firstOrCreate(['name' => $roleName]);
            $user->assignRole($role);

            $this->browse(function (Browser $browser) use ($user, $roleName) {
                $browser->loginAs($user)
                    ->visit('/portal')
                    ->waitForLocation('/portal')
                    ->pause(500);

                file_put_contents(
                    storage_path("app/portal-{$roleName}.html"),
                    $browser->driver->getPageSource()
                );

                $browser->waitForText('Portal', 5)
                    ->assertSee('Portal')
                    ->screenshot("portal-{$roleName}-dashboard")
                    ->visit('/portal/reports')
                    ->waitForLocation('/portal/reports')
                    ->waitForText('Reportes personales', 5)
                    ->assertSee('Reportes personales')
                    ->screenshot("portal-{$roleName}-reports");
            });
        }
    }

    public function test_admin_roles_are_redirected_from_portal(): void
    {
        $roles = ['super_admin', 'admin', 'branch_admin'];
        $company = Company::factory()->create();

        foreach ($roles as $roleName) {
            $user = User::factory()->create([
                'company_id' => $company->id,
                'email' => "{$roleName}@example.com",
            ]);
            $role = Role::firstOrCreate(['name' => $roleName]);
            $user->assignRole($role);

            $this->browse(function (Browser $browser) use ($user) {
                $browser->loginAs($user)
                    ->visit('/portal')
                    ->assertPathIs('/admin');
            });
        }
    }
}
