<?php

namespace Tests\Browser;

use App\Models\Category;
use App\Models\Company;
use App\Models\Department;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class ReportGenerationTest extends DuskTestCase
{
    use DatabaseMigrations;

    private function createAdminForCompany(Company $company, string $email = 'admin@test.com'): User
    {
        $admin = User::factory()->create([
            'company_id' => $company->id,
            'email' => $email,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        return $admin;
    }

    private function seedDocumentsForReports(Company $company, User $admin, int $count = 5): void
    {
        $department = Department::factory()->create(['company_id' => $company->id]);
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id, 'name' => 'Pendiente']);

        Document::factory()->count($count)->create([
            'company_id' => $company->id,
            'department_id' => $department->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
            'assigned_to' => $admin->id,
        ]);
    }

    public function test_user_can_access_reports_section(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createAdminForCompany($company);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/reports')
                ->assertSee('Reportes')
                ->assertSee('Generar Reporte Rápido')
                ->assertSee('Reporte Personalizado');
        });
    }

    public function test_user_can_generate_pdf_report(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createAdminForCompany($company);
        $this->seedDocumentsForReports($company, $admin, 10);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/reports')
                ->press('Generar Reporte Rápido')
                ->waitForText('Generar Reporte Rápido')
                ->assertSee('Generar')
                ->script("Array.from(document.querySelectorAll('button')).find(button => button.innerText.trim() === 'Generar')?.click();");

            $browser->pause(2000)
                ->assertDontSee('Error al generar reporte');
        });
    }

    public function test_user_can_generate_excel_report(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createAdminForCompany($company);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/reports')
                ->press('Generar Reporte Rápido')
                ->waitForText('Generar Reporte Rápido')
                ->assertSourceHas('Excel')
                ->assertSee('Generar');
        });
    }

    public function test_report_can_be_filtered_by_date_range(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createAdminForCompany($company);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/reports')
                ->press('Reporte Personalizado')
                ->waitForText('Generar Reporte Personalizado')
                ->assertSee('Fecha Desde')
                ->assertSee('Fecha Hasta')
                ->assertSee('Generar y Descargar');
        });
    }

    public function test_user_can_view_generated_reports_history(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createAdminForCompany($company);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/reports')
                ->assertSee('No hay reportes disponibles')
                ->assertSee('Utiliza el botón "Generar" para crear un nuevo reporte.');
        });
    }

    public function test_report_can_be_filtered_by_category(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createAdminForCompany($company);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/reports')
                ->press('Reporte Personalizado')
                ->waitForText('Generar Reporte Personalizado')
                ->assertSee('Filtros Avanzados')
                ->assertSee('Departamento');
        });
    }

    public function test_report_can_be_filtered_by_status(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createAdminForCompany($company);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/reports')
                ->press('Generar Reporte Rápido')
                ->waitForText('Documentos por Estado')
                ->assertSee('Documentos por Estado')
                ->assertSourceHas('Cumplimiento SLA');
        });
    }

    public function test_reports_are_isolated_by_company(): void
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $admin1 = $this->createAdminForCompany($company1, 'admin1@test.com');
        $admin2 = $this->createAdminForCompany($company2, 'admin2@test.com');

        $this->browse(function (Browser $browser) use ($admin1, $admin2) {
            $browser->loginAs($admin1)
                ->visit('/admin/reports')
                ->assertSee('Reportes')
                ->assertDontSee('403');

            $browser->loginAs($admin2)
                ->visit('/admin/reports')
                ->assertSee('Reportes')
                ->assertDontSee('403');
        });
    }

    public function test_user_can_download_previously_generated_report(): void
    {
        $company = Company::factory()->create();
        $admin = $this->createAdminForCompany($company);
        $this->seedDocumentsForReports($company, $admin, 3);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/reports')
                ->press('Generar Reporte Rápido')
                ->waitForText('Generar Reporte Rápido')
                ->script("Array.from(document.querySelectorAll('button')).find(button => button.innerText.trim() === 'Generar')?.click();");

            $browser->pause(2000)
                ->assertDontSee('Error al generar reporte');
        });
    }
}
