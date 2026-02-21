<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Company;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Category;
use App\Models\Status;
use App\Models\Document;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Spatie\Permission\Models\Role;

class UserDocumentFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

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

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/admin/login')
                    ->type('input[type="email"]', 'user@test.com')
                    ->type('input[type="password"]', 'password')
                    ->press('button[type="submit"]')
                    ->waitForLocation('/dashboard', 10)
                    ->assertPathIs('/dashboard')
                    ->assertSee('Bienvenido')
                    ->assertSee($user->name)
                    ->assertSee('Total Documentos')
                    ->assertSee('En Proceso')
                    ->assertSee('Completados')
                    ->assertSee('Alta Prioridad');
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

        $this->browse(function (Browser $browser) {
            $browser->visit('/admin/login')
                    ->type('input[type="email"]', 'admin@test.com')
                    ->type('input[type="password"]', 'password')
                    ->press('button[type="submit"]')
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
            'name' => 'Pendiente'
        ]);
        $statusCompleted = Status::factory()->create([
            'company_id' => $company->id,
            'name' => 'Completado'
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
            $browser->visit('/admin/login')
                    ->type('input[type="email"]', 'user@test.com')
                    ->type('input[type="password"]', 'password')
                    ->press('button[type="submit"]')
                    ->waitForLocation('/dashboard', 10)
                    ->assertSee('2') // Total documentos
                    ->assertSee('Documentos Recientes')
                    ->assertSee('Acciones Rápidas');
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
            $browser->visit('/admin/login')
                    ->type('input[type="email"]', 'user@test.com')
                    ->type('input[type="password"]', 'password')
                    ->press('button[type="submit"]')
                    ->waitForLocation('/dashboard', 10)
                    ->clickLink('Mis Documentos')
                    ->waitForLocation('/documents', 5)
                    ->assertPathIs('/documents')
                    ->assertSee('Mis Documentos')
                    ->assertSee('Nuevo Documento');
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
            $browser->visit('/admin/login')
                    ->type('input[type="email"]', 'user@test.com')
                    ->type('input[type="password"]', 'password')
                    ->press('button[type="submit"]')
                    ->waitForLocation('/dashboard', 10)
                    ->visit('/documents/create')
                    ->assertSee('Crear Nuevo Documento')
                    ->type('title', 'Documento de Prueba Dusk')
                    ->type('description', 'Esta es una descripción de prueba')
                    ->select('category_id', $category->id)
                    ->select('status_id', $status->id)
                    ->select('priority', 'medium')
                    ->press('Crear Documento')
                    ->waitForLocation('/documents/', 10)
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
            $browser->visit('/admin/login')
                    ->type('input[type="email"]', 'user@test.com')
                    ->type('input[type="password"]', 'password')
                    ->press('button[type="submit"]')
                    ->waitForLocation('/dashboard', 10)
                    ->visit("/documents/{$document->id}")
                    ->assertSee('Documento de Prueba')
                    ->assertSee('Descripción del documento')
                    ->assertSee('Información del Documento')
                    ->assertSee('Categoría')
                    ->assertSee('Estado')
                    ->assertSee('Prioridad')
                    ->assertSee('High');
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

        $this->browse(function (Browser $browser) use ($document, $category, $status) {
            $browser->visit('/admin/login')
                    ->type('input[type="email"]', 'user@test.com')
                    ->type('input[type="password"]', 'password')
                    ->press('button[type="submit"]')
                    ->waitForLocation('/dashboard', 10)
                    ->visit("/documents/{$document->id}/edit")
                    ->assertSee('Editar Documento')
                    ->assertInputValue('title', 'Documento Original')
                    ->type('title', 'Documento Editado')
                    ->press('Actualizar Documento')
                    ->waitForLocation("/documents/{$document->id}", 10)
                    ->assertSee('Documento actualizado exitosamente')
                    ->assertSee('Documento Editado');
        });
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
            $browser->visit('/admin/login')
                    ->type('input[type="email"]', 'user@test.com')
                    ->type('input[type="password"]', 'password')
                    ->press('button[type="submit"]')
                    ->waitForLocation('/dashboard', 10)
                    ->visit("/documents/{$document->id}")
                    ->assertSee('Documento a Eliminar')
                    ->press('Eliminar Documento')
                    ->acceptDialog()
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
            $browser->visit('/admin/login')
                    ->type('input[type="email"]', 'user1@test.com')
                    ->type('input[type="password"]', 'password')
                    ->press('button[type="submit"]')
                    ->waitForLocation('/dashboard', 10)
                    ->visit("/documents/{$document->id}")
                    ->assertSee('403');
        });
    }
}
