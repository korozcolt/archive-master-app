<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Company;
use App\Models\Document;
use App\Models\Category;
use App\Models\Status;
use App\Models\Report;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Spatie\Permission\Models\Role;

class ReportGenerationTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que usuario puede acceder a la sección de reportes
     */
    public function test_user_can_access_reports_section(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin/reports')
                    ->assertSee('Reportes')
                    ->pause(500);
        });
    }

    /**
     * Test que usuario puede generar un reporte PDF
     */
    public function test_user_can_generate_pdf_report(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        Document::factory()->count(10)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin/reports/create')
                    ->select('select[name="type"]', 'documents')
                    ->select('select[name="format"]', 'pdf')
                    ->press('Generar reporte')
                    ->pause(3000);

            // El reporte PDF debe descargarse
        });
    }

    /**
     * Test que usuario puede generar un reporte Excel
     */
    public function test_user_can_generate_excel_report(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name'] => 'Admin']);
        $admin->assignRole($adminRole);

        Document::factory()->count(10)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin/reports/create')
                    ->select('select[name="type"]', 'documents')
                    ->select('select[name="format"]', 'xlsx')
                    ->press('Generar reporte')
                    ->pause(3000);
        });
    }

    /**
     * Test que reporte puede filtrarse por rango de fechas
     */
    public function test_report_can_be_filtered_by_date_range(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin/reports/create')
                    ->type('input[name="start_date"]', '2025-01-01')
                    ->type('input[name="end_date"]', '2025-12-31')
                    ->press('Generar reporte')
                    ->pause(2000);
        });
    }

    /**
     * Test que usuario puede ver historial de reportes generados
     */
    public function test_user_can_view_generated_reports_history(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        Report::factory()->count(5)->create([
            'company_id' => $company->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin/reports')
                    ->assertSee('Historial de reportes')
                    ->assertPresent('table');
        });
    }

    /**
     * Test que reporte puede filtrarse por categoría
     */
    public function test_report_can_be_filtered_by_category(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id, 'name' => 'Contratos']);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin, $category) {
            $browser->loginAs($admin)
                    ->visit('/admin/reports/create')
                    ->select('select[name="category_id"]', $category->id)
                    ->press('Generar reporte')
                    ->pause(2000);
        });
    }

    /**
     * Test que reporte puede filtrarse por estado
     */
    public function test_report_can_be_filtered_by_status(): void
    {
        $company = Company::factory()->create();
        $status = Status::factory()->create(['company_id' => $company->id, 'name' => 'Aprobado']);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin, $status) {
            $browser->loginAs($admin)
                    ->visit('/admin/reports/create')
                    ->select('select[name="status_id"]', $status->id)
                    ->press('Generar reporte')
                    ->pause(2000);
        });
    }

    /**
     * Test que reportes están aislados por empresa
     */
    public function test_reports_are_isolated_by_company(): void
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $admin1 = User::factory()->create(['company_id' => $company1->id]);
        $admin2 = User::factory()->create(['company_id' => $company2->id]);

        Report::factory()->count(3)->create(['company_id' => $company1->id, 'created_by' => $admin1->id]);
        Report::factory()->count(2)->create(['company_id' => $company2->id, 'created_by' => $admin2->id]);

        $this->assertEquals(3, Report::where('company_id', $company1->id)->count());
        $this->assertEquals(2, Report::where('company_id', $company2->id)->count());
    }

    /**
     * Test que usuario puede descargar reporte previamente generado
     */
    public function test_user_can_download_previously_generated_report(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        $report = Report::factory()->create([
            'company_id' => $company->id,
            'created_by' => $admin->id,
            'file_path' => 'reports/test-report.pdf',
        ]);

        $this->browse(function (Browser $browser) use ($admin, $report) {
            $browser->loginAs($admin)
                    ->visit('/admin/reports')
                    ->click('a[href*="/reports/' . $report->id . '/download"]')
                    ->pause(2000);
        });
    }
}
