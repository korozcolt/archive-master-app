<?php

namespace Tests\Browser;

use App\Models\Branch;
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

class UserDocumentFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    private function loginRegularPortal(Browser $browser, string $email = 'user@test.com'): Browser
    {
        $user = User::where('email', $email)->firstOrFail();

        return $browser->loginAs($user)
            ->visit('/portal')
            ->waitForLocation('/portal', 10);
    }

    /**
     * Test que usuario regular puede hacer login y ve el dashboard
     */
    public function test_regular_user_can_login_and_see_dashboard(): void
    {
        // Crear datos necesarios
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['branch_id' => $branch->id]);

        // Crear rol de usuario regular
        $role = Role::firstOrCreate(['name' => 'regular_user', 'guard_name' => 'web']);

        // Crear usuario regular
        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $user->assignRole('regular_user');

        $this->browse(function (Browser $browser) {
            $this->loginRegularPortal($browser)
                ->assertPathIs('/portal')
                ->assertSee('Portal')
                ->assertSee('Resumen personal de documentos')
                ->assertSee('Total')
                ->assertSee('Pendientes');
        });
    }

    /**
     * Test que usuario admin es redirigido a /admin
     */
    public function test_admin_user_is_redirected_to_filament(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['branch_id' => $branch->id]);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $admin->assignRole('admin');

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/portal')
                ->waitForLocation('/admin', 10)
                ->assertPathIs('/admin');
        });
    }

    /**
     * Test que dashboard muestra estadísticas correctas
     */
    public function test_dashboard_shows_correct_statistics(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['branch_id' => $branch->id]);

        $role = Role::firstOrCreate(['name' => 'regular_user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $user->assignRole('regular_user');

        // Crear categoría y estados
        $category = Category::factory()->create(['company_id' => $company->id]);
        $statusPending = Status::factory()->create([
            'company_id' => $company->id,
            'name' => 'Pendiente',
        ]);
        $statusCompleted = Status::factory()->create([
            'company_id' => $company->id,
            'name' => 'Completado',
        ]);

        // Crear documentos de prueba
        Document::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'category_id' => $category->id,
            'status_id' => $statusPending->id,
            'priority' => 'high',
        ]);

        Document::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'category_id' => $category->id,
            'status_id' => $statusCompleted->id,
            'priority' => 'low',
        ]);

        $this->browse(function (Browser $browser) {
            $this->loginRegularPortal($browser)
                ->assertSee('2') // Total documentos
                ->assertSee('Documentos recientes')
                ->assertSee('Nuevo Documento');
        });
    }

    /**
     * Test que usuario puede navegar a la lista de documentos
     */
    public function test_user_can_navigate_to_documents_list(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['branch_id' => $branch->id]);

        $role = Role::firstOrCreate(['name' => 'regular_user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $user->assignRole('regular_user');

        $this->browse(function (Browser $browser) {
            $this->loginRegularPortal($browser)
                ->clickLink('Mis Documentos')
                ->waitForLocation('/documents', 5)
                ->assertPathIs('/documents')
                ->assertSee('Mis Documentos')
                ->assertSee('NUEVO DOCUMENTO');
        });
    }

    /**
     * Test que usuario puede crear un documento
     */
    public function test_user_can_create_document(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['branch_id' => $branch->id]);

        $role = Role::firstOrCreate(['name' => 'regular_user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $user->assignRole('regular_user');

        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($category, $status) {
            $this->loginRegularPortal($browser)
                ->visit('/documents/create')
                ->assertSee('Crear Nuevo Documento')
                ->type('title', 'Documento de Prueba Dusk')
                ->type('description', 'Esta es una descripción de prueba')
                ->select('category_id', $category->id)
                ->select('status_id', $status->id)
                ->select('priority', 'medium')
                ->script("document.querySelector('form[action$=\"/documents\"]')?.submit();");

            $browser->pause(1000)
                ->assertSee('Documento creado exitosamente')
                ->assertSee('Documento de Prueba Dusk');
        });
    }

    /**
     * Test que usuario puede ver detalles de un documento
     */
    public function test_user_can_view_document_details(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['branch_id' => $branch->id]);

        $role = Role::firstOrCreate(['name' => 'regular_user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $user->assignRole('regular_user');

        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $document = Document::factory()->create([
            'title' => 'Documento de Prueba',
            'description' => 'Descripción del documento',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'priority' => 'high',
        ]);

        $this->browse(function (Browser $browser) use ($document) {
            $this->loginRegularPortal($browser)
                ->visit("/documents/{$document->id}")
                ->assertSee('Documento de Prueba')
                ->assertSee('Descripción del documento')
                ->assertSee('Información del Documento')
                ->assertSee('Categoría')
                ->assertSee('Estado')
                ->assertSee('Prioridad')
                ->assertSee('Alta');
        });
    }

    /**
     * Test que usuario puede editar su documento
     */
    public function test_user_can_edit_their_document(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['branch_id' => $branch->id]);

        $role = Role::firstOrCreate(['name' => 'regular_user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $user->assignRole('regular_user');

        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $document = Document::factory()->create([
            'title' => 'Documento Original',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
        ]);

        $this->browse(function (Browser $browser) use ($document) {
            $this->loginRegularPortal($browser)
                ->visit("/documents/{$document->id}/edit")
                ->assertSee('Editar Documento')
                ->assertInputValue('title', 'Documento Original')
                ->type('title', 'Documento Editado')
                ->script("document.querySelector('form[action$=\"/documents/{$document->id}\"]')?.submit();");

            // Wait for redirect after form submission instead of fragile assertSee
            $browser->pause(2000);

            // Allow redirect to document show or documents list
            $currentPath = $browser->driver->getCurrentURL();
            $this->assertTrue(
                str_contains($currentPath, '/documents'),
                "Expected redirect to documents path, got: {$currentPath}"
            );
        });

        // Verify persistence in database (deterministic check)
        $document->refresh();
        $this->assertSame('Documento Editado', $document->title);
    }

    /**
     * Test que usuario puede eliminar su documento
     */
    public function test_user_can_delete_their_document(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create(['branch_id' => $branch->id]);

        $role = Role::firstOrCreate(['name' => 'regular_user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $user->assignRole('regular_user');

        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $document = Document::factory()->create([
            'title' => 'Documento a Eliminar',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
        ]);

        $this->browse(function (Browser $browser) use ($document) {
            $this->loginRegularPortal($browser)
                ->visit("/documents/{$document->id}")
                ->assertSee('Documento a Eliminar')
                ->script("document.querySelector('form[action$=\"/documents/{$document->id}\"] button[type=\"submit\"]')?.click();");

            $browser->acceptDialog()
                ->waitForLocation('/documents', 10)
                ->assertSee('Documento eliminado exitosamente');
        });
    }

    /**
     * Test que usuario NO puede ver documentos de otra compañía
     */
    public function test_user_cannot_access_other_company_documents(): void
    {
        // Compañía 1 con usuario
        $company1 = Company::factory()->create();
        $branch1 = Branch::factory()->create(['company_id' => $company1->id]);
        $department1 = Department::factory()->create(['branch_id' => $branch1->id]);

        $role = Role::firstOrCreate(['name' => 'regular_user', 'guard_name' => 'web']);

        $user1 = User::factory()->create([
            'email' => 'user1@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company1->id,
            'branch_id' => $branch1->id,
            'department_id' => $department1->id,
        ]);
        $user1->assignRole('regular_user');

        // Compañía 2 con documento
        $company2 = Company::factory()->create();
        $branch2 = Branch::factory()->create(['company_id' => $company2->id]);
        $department2 = Department::factory()->create(['branch_id' => $branch2->id]);

        $user2 = User::factory()->create([
            'company_id' => $company2->id,
            'branch_id' => $branch2->id,
            'department_id' => $department2->id,
        ]);

        $category = Category::factory()->create(['company_id' => $company2->id]);
        $status = Status::factory()->create(['company_id' => $company2->id]);

        $document = Document::factory()->create([
            'company_id' => $company2->id,
            'branch_id' => $branch2->id,
            'department_id' => $department2->id,
            'created_by' => $user2->id,
            'assigned_to' => $user2->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
        ]);

        $this->browse(function (Browser $browser) use ($document) {
            $this->loginRegularPortal($browser, 'user1@test.com')
                ->visit("/documents/{$document->id}")
                ->assertSee('403');
        });
    }
}
