<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        // Set application locale for translatable fields
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
    public function can_view_categories_list_page()
    {
        $this->actingAs($this->admin);

        Livewire::test(CategoryResource\Pages\ListCategories::class)
            ->assertSuccessful();
    }

    /** @test */
    public function categories_list_shows_all_categories()
    {
        $this->actingAs($this->admin);

        // Create categories
        $categories = Category::factory()->count(5)->create([
            'company_id' => $this->company->id,
        ]);

        Livewire::test(CategoryResource\Pages\ListCategories::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($categories);
    }

    /** @test */
    public function can_search_categories_by_name()
    {
        $this->actingAs($this->admin);

        Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Categoría Búsqueda XYZ',
        ]);

        Category::factory()->count(5)->create([
            'company_id' => $this->company->id,
        ]);

        $component = Livewire::test(CategoryResource\Pages\ListCategories::class)
            ->searchTable('Búsqueda');

        // Verify search functionality works
        $this->assertEquals('Búsqueda', $component->instance()->getTableSearch());
    }

    /** @test */
    public function can_access_create_category_page()
    {
        $this->actingAs($this->admin);

        Livewire::test(CategoryResource\Pages\CreateCategory::class)
            ->assertSuccessful();
    }

    /** @test */
    public function can_create_category()
    {
        $this->actingAs($this->admin);

        $newData = [
            'name' => 'Nueva Categoría Test',
            'slug' => 'nueva-categoria-test',
            'company_id' => $this->company->id,
            'active' => true,
            'order' => 0,
        ];

        // Use fill() instead of fillForm() due to Filament bug #15557
        Livewire::test(CategoryResource\Pages\CreateCategory::class)
            ->fill(['data' => $newData])
            ->call('create')
            ->assertHasNoFormErrors();

        // Verify using model accessor (translatable field stored in 'en' locale by default)
        $category = Category::where('slug', 'nueva-categoria-test')->first();
        $this->assertNotNull($category);
        $this->assertEquals('Nueva Categoría Test', $category->getTranslation('name', 'en'));
    }

    /** @test */
    public function category_name_is_required()
    {
        $this->actingAs($this->admin);

        Livewire::test(CategoryResource\Pages\CreateCategory::class)
            ->set('data.name', null)
            ->call('create')
            ->assertHasFormErrors(['name']);
    }

    /** @test */
    public function category_slug_is_required()
    {
        $this->actingAs($this->admin);

        Livewire::test(CategoryResource\Pages\CreateCategory::class)
            ->fill(['data' => [
                'name' => 'Test Category',
                'slug' => null,
            ]])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    /** @test */
    public function slug_must_be_unique()
    {
        $this->actingAs($this->admin);

        Category::factory()->create([
            'company_id' => $this->company->id,
            'slug' => 'existing-slug',
        ]);

        Livewire::test(CategoryResource\Pages\CreateCategory::class)
            ->fill(['data' => [
                'name' => 'New Category',
                'slug' => 'existing-slug',
                'company_id' => $this->company->id,
            ]])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    /** @test */
    public function company_id_is_required()
    {
        $this->actingAs($this->admin);

        Livewire::test(CategoryResource\Pages\CreateCategory::class)
            ->fill(['data' => [
                'name' => 'Test Category',
                'slug' => 'test-category',
                'company_id' => null,
            ]])
            ->call('create')
            ->assertHasFormErrors(['company_id']);
    }

    /** @test */
    public function can_edit_category()
    {
        $this->actingAs($this->admin);

        $category = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Categoría Original',
        ]);

        Livewire::test(CategoryResource\Pages\EditCategory::class, [
            'record' => $category->id,
        ])
            ->assertSet('data.name', 'Categoría Original')
            ->set('data.name', 'Categoría Modificada')
            ->call('save')
            ->assertHasNoFormErrors();

        $category->refresh();
        $this->assertEquals('Categoría Modificada', $category->name);
    }

    /** @test */
    public function can_deactivate_category()
    {
        $this->actingAs($this->admin);

        $category = Category::factory()->create([
            'company_id' => $this->company->id,
            'active' => true,
        ]);

        Livewire::test(CategoryResource\Pages\EditCategory::class, [
            'record' => $category->id,
        ])
            ->assertSet('data.active', true)
            ->set('data.active', false)
            ->call('save')
            ->assertHasNoFormErrors();

        $category->refresh();
        $this->assertFalse($category->active);
    }

    /** @test */
    public function can_delete_category()
    {
        $this->actingAs($this->admin);

        $category = Category::factory()->create([
            'company_id' => $this->company->id,
        ]);

        Livewire::test(CategoryResource\Pages\EditCategory::class, [
            'record' => $category->id,
        ])
            ->callAction('delete');

        $this->assertSoftDeleted('categories', [
            'id' => $category->id,
        ]);
    }

    /** @test */
    public function can_create_child_category()
    {
        $this->actingAs($this->admin);

        $parentCategory = Category::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Categoría Padre',
        ]);

        $newData = [
            'name' => 'Categoría Hija',
            'slug' => 'categoria-hija',
            'company_id' => $this->company->id,
            'parent_id' => $parentCategory->id,
            'active' => true,
            'order' => 0,
        ];

        Livewire::test(CategoryResource\Pages\CreateCategory::class)
            ->fill(['data' => $newData])
            ->call('create')
            ->assertHasNoFormErrors();

        // Verify using model accessor (translatable field stored in 'en' locale by default)
        $childCategory = Category::where('slug', 'categoria-hija')->first();
        $this->assertNotNull($childCategory);
        $this->assertEquals('Categoría Hija', $childCategory->getTranslation('name', 'en'));
        $this->assertEquals($parentCategory->id, $childCategory->parent_id);
    }

    /** @test */
    public function can_filter_categories_by_active_status()
    {
        $this->actingAs($this->admin);

        $activeCategory = Category::factory()->create([
            'company_id' => $this->company->id,
            'active' => true,
        ]);
        $inactiveCategory = Category::factory()->create([
            'company_id' => $this->company->id,
            'active' => false,
        ]);

        Livewire::test(CategoryResource\Pages\ListCategories::class)
            ->filterTable('active', true)
            ->assertCanSeeTableRecords([$activeCategory])
            ->assertCanNotSeeTableRecords([$inactiveCategory]);
    }

    /** @test */
    public function can_filter_root_categories()
    {
        $this->actingAs($this->admin);

        $rootCategory = Category::factory()->create([
            'company_id' => $this->company->id,
            'parent_id' => null,
        ]);

        $parentCategory = Category::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $childCategory = Category::factory()->create([
            'company_id' => $this->company->id,
            'parent_id' => $parentCategory->id,
        ]);

        Livewire::test(CategoryResource\Pages\ListCategories::class)
            ->filterTable('root', true)
            ->assertCanSeeTableRecords([$rootCategory, $parentCategory])
            ->assertCanNotSeeTableRecords([$childCategory]);
    }

    /** @test */
    public function categories_are_isolated_by_company()
    {
        $company1 = Company::factory()->create(['name' => 'Company 1']);
        $company2 = Company::factory()->create(['name' => 'Company 2']);

        $category1 = Category::factory()->create([
            'company_id' => $company1->id,
            'name' => 'Category Company 1',
        ]);
        $category2 = Category::factory()->create([
            'company_id' => $company2->id,
            'name' => 'Category Company 2',
        ]);

        // Super admin sees all categories
        $this->actingAs($this->admin);

        Livewire::test(CategoryResource\Pages\ListCategories::class)
            ->assertCanSeeTableRecords([$category1, $category2]);
    }
}
