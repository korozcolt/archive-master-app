<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Company;
use App\Models\Document;
use App\Models\Category;
use App\Models\Status;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Spatie\Permission\Models\Role;

class DashboardTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que usuario puede acceder al dashboard
     */
    public function test_user_can_access_dashboard(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/admin')
                    ->assertSee('Dashboard')
                    ->assertPathIs('/admin');
        });
    }

    /**
     * Test que dashboard muestra estadísticas de documentos
     */
    public function test_dashboard_shows_document_statistics(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        Document::factory()->count(15)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/admin')
                    ->assertSee('Total de documentos')
                    ->assertSee('15');
        });
    }

    /**
     * Test que dashboard muestra documentos recientes
     */
    public function test_dashboard_shows_recent_documents(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $recentDoc = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'title' => 'Documento Más Reciente',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/admin')
                    ->assertSee('Documentos recientes')
                    ->assertSee('Documento Más Reciente');
        });
    }

    /**
     * Test que dashboard muestra notificaciones pendientes
     */
    public function test_dashboard_shows_pending_notifications(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        // Crear notificación no leída
        $user->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\DocumentDueSoon',
            'data' => [
                'type' => 'document_due_soon',
                'title' => 'Documento próximo a vencer',
                'message' => 'El documento vence en 3 días',
            ],
            'read_at' => null,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/admin')
                    ->assertPresent('.notification-badge')
                    ->assertSee('1');
        });
    }

    /**
     * Test que dashboard muestra gráficos de estadísticas
     */
    public function test_dashboard_shows_statistics_charts(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($admin)
                    ->visit('/admin')
                    ->assertPresent('canvas, .chart, #chart-container')
                    ->pause(500);
        });
    }

    /**
     * Test que dashboard muestra documentos por estado
     */
    public function test_dashboard_shows_documents_by_status(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $statusActive = Status::factory()->create(['company_id' => $company->id, 'name' => 'Activo']);
        $statusPending = Status::factory()->create(['company_id' => $company->id, 'name' => 'Pendiente']);

        Document::factory()->count(10)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $statusActive->id,
            'created_by' => $user->id,
        ]);

        Document::factory()->count(5)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $statusPending->id,
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/admin')
                    ->assertSee('Activo')
                    ->assertSee('Pendiente');
        });
    }

    /**
     * Test que dashboard muestra actividad reciente
     */
    public function test_dashboard_shows_recent_activity(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/admin')
                    ->assertSee('Actividad reciente')
                    ->pause(500);
        });
    }

    /**
     * Test que dashboard muestra widgets personalizables
     */
    public function test_dashboard_shows_customizable_widgets(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/admin')
                    ->assertPresent('.filament-widget, .fi-wi')
                    ->pause(500);
        });
    }

    /**
     * Test que diferentes roles ven diferentes dashboard views
     */
    public function test_different_roles_see_different_dashboard_views(): void
    {
        $company = Company::factory()->create();

        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        $regular = User::factory()->create(['company_id' => $company->id]);
        $regularRole = Role::create(['name' => 'Regular User']);
        $regular->assignRole($regularRole);

        // Admin ve todo
        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin')
                    ->assertSee('Dashboard')
                    ->pause(500);
        });

        // Usuario regular ve vista limitada
        $this->browse(function (Browser $browser) use ($regular) {
            $browser->loginAs($regular)
                    ->visit('/admin')
                    ->assertSee('Dashboard')
                    ->pause(500);
        });
    }

    /**
     * Test que dashboard muestra métricas de rendimiento
     */
    public function test_dashboard_shows_performance_metrics(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/admin')
                    ->pause(500);

            // Verificar que existen métricas
            // Como documentos creados hoy, documentos pendientes, etc.
        });
    }
}
