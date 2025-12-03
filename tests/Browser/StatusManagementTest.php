<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Company;
use App\Models\Status;
use App\Models\Document;
use App\Models\Category;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Spatie\Permission\Models\Role;

class StatusManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que admin puede ver lista de estados
     */
    public function test_admin_can_view_statuses_list(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        Status::factory()->count(5)->create(['company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin/statuses')
                    ->assertSee('Estados')
                    ->assertPresent('table');
        });
    }

    /**
     * Test que admin puede crear un estado
     */
    public function test_admin_can_create_status(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/admin/statuses/create')
                    ->type('input[name="name"]', 'En Revisión')
                    ->type('input[name="color"]', '#FFA500')
                    ->press('Crear')
                    ->pause(1000);

            $this->assertDatabaseHas('statuses', [
                'name' => 'En Revisión',
                'color' => '#FFA500',
            ]);
        });
    }

    /**
     * Test que estados están aislados por empresa
     */
    public function test_statuses_are_isolated_by_company(): void
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        Status::factory()->count(3)->create(['company_id' => $company1->id]);
        Status::factory()->count(2)->create(['company_id' => $company2->id]);

        $this->assertEquals(3, Status::where('company_id', $company1->id)->count());
        $this->assertEquals(2, Status::where('company_id', $company2->id)->count());
    }

    /**
     * Test que estado tiene color personalizado
     */
    public function test_status_has_custom_color(): void
    {
        $company = Company::factory()->create();

        $status = Status::factory()->create([
            'company_id' => $company->id,
            'name' => 'Aprobado',
            'color' => '#00FF00',
        ]);

        $this->assertEquals('#00FF00', $status->color);
    }

    /**
     * Test que documentos pueden cambiar de estado
     */
    public function test_documents_can_change_status(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $status1 = Status::factory()->create(['company_id' => $company->id, 'name' => 'Borrador']);
        $status2 = Status::factory()->create(['company_id' => $company->id, 'name' => 'Publicado']);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status1->id,
            'created_by' => $user->id,
        ]);

        $document->update(['status_id' => $status2->id]);

        $this->assertEquals($status2->id, $document->fresh()->status_id);
    }

    /**
     * Test que estado muestra conteo de documentos
     */
    public function test_status_shows_document_count(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $status = Status::factory()->create(['company_id' => $company->id]);

        Document::factory()->count(7)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $this->assertEquals(7, Document::where('status_id', $status->id)->count());
    }

    /**
     * Test que estado puede ser editado
     */
    public function test_status_can_be_edited(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::create(['name' => 'Admin']);
        $admin->assignRole($adminRole);

        $status = Status::factory()->create([
            'company_id' => $company->id,
            'name' => 'Original',
        ]);

        $this->browse(function (Browser $browser) use ($admin, $status) {
            $browser->loginAs($admin)
                    ->visit('/admin/statuses/' . $status->id . '/edit')
                    ->clear('input[name="name"]')
                    ->type('input[name="name"]', 'Actualizado')
                    ->press('Guardar cambios')
                    ->pause(1000);

            $status->refresh();
            $this->assertEquals('Actualizado', $status->name);
        });
    }

    /**
     * Test que estado vacío puede ser eliminado
     */
    public function test_empty_status_can_be_deleted(): void
    {
        $company = Company::factory()->create();
        $status = Status::factory()->create(['company_id' => $company->id]);

        $status->delete();

        $this->assertSoftDeleted('statuses', ['id' => $status->id]);
    }

    /**
     * Test que documentos pueden filtrarse por estado
     */
    public function test_documents_can_be_filtered_by_status(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $statusDraft = Status::factory()->create(['company_id' => $company->id, 'name' => 'Borrador']);
        $statusPublished = Status::factory()->create(['company_id' => $company->id, 'name' => 'Publicado']);

        Document::factory()->count(3)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $statusDraft->id,
            'created_by' => $user->id,
        ]);

        Document::factory()->count(5)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $statusPublished->id,
            'created_by' => $user->id,
        ]);

        $this->assertEquals(3, Document::where('status_id', $statusDraft->id)->count());
        $this->assertEquals(5, Document::where('status_id', $statusPublished->id)->count());
    }
}
