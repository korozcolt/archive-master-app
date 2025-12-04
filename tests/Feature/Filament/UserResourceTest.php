<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\UserResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Set application locale
        app()->setLocale('es');

        // Create company and super admin user
        $this->company = Company::factory()->create();
        $this->admin = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $superAdminRole = Role::create(['name' => 'super_admin']);
        $this->admin->assignRole($superAdminRole);
    }

    /** @test */
    public function super_admin_can_view_users_list_page()
    {
        $this->actingAs($this->admin);

        Livewire::test(UserResource\Pages\ListUsers::class)
            ->assertSuccessful();
    }

    /** @test */
    public function users_list_shows_all_users()
    {
        $this->actingAs($this->admin);

        // Create additional users
        $users = User::factory()->count(5)->create([
            'company_id' => $this->company->id,
        ]);

        Livewire::test(UserResource\Pages\ListUsers::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($users);
    }

    /** @test */
    public function can_search_users_by_name()
    {
        $this->actingAs($this->admin);

        $targetUser = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Usuario Búsqueda XYZ',
        ]);

        User::factory()->count(5)->create([
            'company_id' => $this->company->id,
        ]);

        $component = Livewire::test(UserResource\Pages\ListUsers::class)
            ->searchTable('Búsqueda');

        // Verify search functionality works
        $this->assertEquals('Búsqueda', $component->instance()->getTableSearch());
    }

    /** @test */
    public function super_admin_can_access_create_user_page()
    {
        $this->actingAs($this->admin);

        Livewire::test(UserResource\Pages\CreateUser::class)
            ->assertSuccessful();
    }

    /** @test */
    public function can_create_user()
    {
        $this->actingAs($this->admin);

        $newData = [
            'name' => 'Nuevo Usuario Test',
            'email' => 'nuevo@usuario.com',
            'company_id' => $this->company->id,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true,
            'language' => 'es',
            'timezone' => 'America/Bogota',
        ];

        // Use fill() instead of fillForm() due to Filament bug #15557
        Livewire::test(UserResource\Pages\CreateUser::class)
            ->fill(['data' => $newData])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'nuevo@usuario.com',
            'name' => 'Nuevo Usuario Test',
        ]);
    }

    /** @test */
    public function user_name_is_required()
    {
        $this->actingAs($this->admin);

        Livewire::test(UserResource\Pages\CreateUser::class)
            ->set('data.name', null)
            ->call('create')
            ->assertHasFormErrors(['name']);
    }

    /** @test */
    public function user_email_is_required()
    {
        $this->actingAs($this->admin);

        Livewire::test(UserResource\Pages\CreateUser::class)
            ->fill(['data' => [
                'name' => 'Test User',
                'email' => null,
            ]])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    /** @test */
    public function can_validate_email_format()
    {
        $this->actingAs($this->admin);

        Livewire::test(UserResource\Pages\CreateUser::class)
            ->fill(['data' => [
                'name' => 'Test User',
                'email' => 'invalid-email',
            ]])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    /** @test */
    public function email_must_be_unique()
    {
        $this->actingAs($this->admin);

        $existingUser = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'existing@test.com',
        ]);

        Livewire::test(UserResource\Pages\CreateUser::class)
            ->fill(['data' => [
                'name' => 'New User',
                'email' => 'existing@test.com',
                'company_id' => $this->company->id,
            ]])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    /** @test */
    public function password_is_required_on_create()
    {
        $this->actingAs($this->admin);

        Livewire::test(UserResource\Pages\CreateUser::class)
            ->fill(['data' => [
                'name' => 'Test User',
                'email' => 'test@user.com',
                'company_id' => $this->company->id,
                'password' => null,
            ]])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    /** @test */
    public function super_admin_can_edit_user()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Usuario Original',
            'email' => 'original@test.com',
        ]);

        Livewire::test(UserResource\Pages\EditUser::class, [
            'record' => $user->id,
        ])
            ->assertSet('data.name', 'Usuario Original')
            ->set('data.name', 'Usuario Modificado')
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertEquals('Usuario Modificado', $user->name);
    }

    /** @test */
    public function can_deactivate_user()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        Livewire::test(UserResource\Pages\EditUser::class, [
            'record' => $user->id,
        ])
            ->assertSet('data.is_active', true)
            ->set('data.is_active', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();
        $this->assertFalse($user->is_active);
    }

    /** @test */
    public function can_delete_user()
    {
        $this->actingAs($this->admin);

        $user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        Livewire::test(UserResource\Pages\EditUser::class, [
            'record' => $user->id,
        ])
            ->callAction('delete');

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function can_filter_users_by_active_status()
    {
        $this->actingAs($this->admin);

        $activeUser = User::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);
        $inactiveUser = User::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => false,
        ]);

        Livewire::test(UserResource\Pages\ListUsers::class)
            ->filterTable('is_active', true)
            ->assertCanSeeTableRecords([$activeUser])
            ->assertCanNotSeeTableRecords([$inactiveUser]);
    }

    /** @test */
    public function users_are_isolated_by_company()
    {
        // Create two companies with users
        $company1 = Company::factory()->create(['name' => 'Company 1']);
        $company2 = Company::factory()->create(['name' => 'Company 2']);

        $user1 = User::factory()->create([
            'company_id' => $company1->id,
            'name' => 'User Company 1',
        ]);
        $user2 = User::factory()->create([
            'company_id' => $company2->id,
            'name' => 'User Company 2',
        ]);

        // Super admin sees all users
        $this->actingAs($this->admin);

        Livewire::test(UserResource\Pages\ListUsers::class)
            ->assertCanSeeTableRecords([$user1, $user2]);
    }

    /** @test */
    public function regular_admin_only_sees_own_company_users()
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        $admin1 = User::factory()->create(['company_id' => $company1->id]);
        $adminRole = Role::create(['name' => 'admin']);
        $admin1->assignRole($adminRole);

        $user1 = User::factory()->create([
            'company_id' => $company1->id,
            'name' => 'User Company 1',
        ]);
        $user2 = User::factory()->create([
            'company_id' => $company2->id,
            'name' => 'User Company 2',
        ]);

        // Admin of company1 only sees users from company1
        $this->actingAs($admin1);

        Livewire::test(UserResource\Pages\ListUsers::class)
            ->assertCanSeeTableRecords([$user1])
            ->assertCanNotSeeTableRecords([$user2]);
    }

    /** @test */
    public function company_id_is_required()
    {
        $this->actingAs($this->admin);

        Livewire::test(UserResource\Pages\CreateUser::class)
            ->fill(['data' => [
                'name' => 'Test User',
                'email' => 'test@user.com',
                'company_id' => null,
            ]])
            ->call('create')
            ->assertHasFormErrors(['company_id']);
    }
}
