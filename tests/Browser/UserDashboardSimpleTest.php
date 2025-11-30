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

class UserDashboardSimpleTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar el entorno para tests
        config(['app.env' => 'testing']);
        // Don't override session driver - use 'file' from .env.dusk.local for persistence
        // config(['session.driver' => 'array']);

        // Limpiar cache de permisos de Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Test que dashboard muestra correctamente para usuario autenticado
     */
    public function test_authenticated_user_can_see_dashboard(): void
    {
        // Crear datos necesarios
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id
        ]);

        $role = Role::create(['name' => 'regular_user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);

        $user->assignRole('regular_user');

        // Limpiar cache de permisos después de asignar rol
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $user->refresh();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user, 'web')
                    ->visit('/dashboard')
                    ->assertPathIs('/dashboard')
                    ->assertSee('Total Documentos')
                    ->assertSee('En Proceso')
                    ->assertSee('Completados')
                    ->assertSee('Alta Prioridad')
                    ->assertSee('Acciones Rápidas');
        });
    }

    /**
     * Test que usuario puede ver la lista de documentos
     */
    public function test_user_can_view_documents_list(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id
        ]);

        $role = Role::create(['name' => 'regular_user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $user->assignRole('regular_user');

        // Limpiar cache de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear un documento
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $document = Document::factory()->create([
            'title' => 'Test Document',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
        ]);
        $document->refresh(); // Asegurar timestamps

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/documents')
                    ->screenshot('documents-list-test')
                    ->assertSee('Mis Documentos')
                    ->assertSee('Test Document')
                    ->assertSee('NUEVO DOCUMENTO'); // Button text is uppercase
        });
    }

    /**
     * Test que usuario puede ver formulario de creación
     */
    public function test_user_can_access_create_form(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id
        ]);

        $role = Role::create(['name' => 'regular_user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $user->assignRole('regular_user');

        // Limpiar cache de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Crear categoría y estado para el formulario
        Category::factory()->create(['company_id' => $company->id, 'name' => 'Test Category']);
        Status::factory()->create(['company_id' => $company->id, 'name' => 'Test Status']);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/documents/create')
                    ->assertSee('Crear Nuevo Documento')
                    ->assertSee('Título')
                    ->assertSee('Descripción')
                    ->assertSee('Categoría')
                    ->assertSee('Estado')
                    ->assertSee('Prioridad');
        });
    }

    /**
     * Test que usuario puede ver detalles de su documento
     */
    public function test_user_can_view_document_details(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id
        ]);

        $role = Role::create(['name' => 'regular_user', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $user->assignRole('regular_user');

        // Limpiar cache de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $category = Category::factory()->create([
            'company_id' => $company->id,
            'name' => 'Categoría de Prueba'
        ]);
        $status = Status::factory()->create([
            'company_id' => $company->id,
            'name' => 'Estado de Prueba'
        ]);

        $document = Document::factory()->create([
            'title' => 'Mi Documento de Prueba',
            'description' => 'Esta es una descripción de prueba',
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'priority' => 'high',
        ]);
        $document->refresh(); // Asegurar timestamps

        $this->browse(function (Browser $browser) use ($user, $document) {
            $browser->loginAs($user)
                    ->visit("/documents/{$document->id}")
                    ->screenshot('document-details-test')
                    ->assertSee('Mi Documento de Prueba')
                    ->assertSee('Esta es una descripción de prueba')
                    ->assertSee('Información del Documento')
                    ->assertSee('Categoría de Prueba')
                    ->assertSee('Estado de Prueba')
                    ->assertSee('Alta'); // 'high' priority shows as 'Alta' in Spanish
        });
    }

    /**
     * Test que admin es redirigido a /admin cuando visita /dashboard
     */
    public function test_admin_redirected_to_admin_panel(): void
    {
        $company = Company::factory()->create();
        $branch = Branch::factory()->create(['company_id' => $company->id]);
        $department = Department::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id
        ]);

        $role = Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'department_id' => $department->id,
        ]);
        $admin->assignRole('admin');

        // Limpiar cache de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                    ->visit('/dashboard')
                    ->assertPathIs('/admin');
        });
    }
}
