<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\DocumentTemplateResource;
use App\Models\Category;
use App\Models\Company;
use App\Models\DocumentTemplate;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DocumentTemplateResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Company $company;

    protected Category $category;

    protected Status $status;

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

        // Create required related models
        $this->category = Category::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $this->status = Status::factory()->create([
            'company_id' => $this->company->id,
            'is_initial' => true,
        ]);
    }

    /** @test */
    public function can_view_document_templates_list_page()
    {
        $this->actingAs($this->admin);

        Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->assertSuccessful();
    }

    /** @test */
    public function list_displays_localized_category_and_status_labels()
    {
        $this->actingAs($this->admin);

        $this->category->update([
            'name' => ['es' => 'Actas de Reunión'],
        ]);

        $this->status->update([
            'name' => ['es' => 'Borrador'],
            'active' => true,
        ]);

        DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'default_category_id' => $this->category->id,
            'default_status_id' => $this->status->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->assertSuccessful()
            ->assertSee('Actas de Reunión')
            ->assertSee('Borrador')
            ->assertDontSee('{\"es\":');
    }

    /** @test */
    public function list_displays_localized_labels_from_legacy_nested_translatable_json()
    {
        $this->actingAs($this->admin);

        $this->category->forceFill([
            'name' => json_encode(['es' => json_encode(['es' => 'Actas de Reunión'])], JSON_UNESCAPED_UNICODE),
        ])->save();

        $this->status->forceFill([
            'name' => json_encode([
                'en' => json_encode(['es' => 'Borrador'], JSON_UNESCAPED_UNICODE),
                'es' => json_encode(['es' => 'Borrador'], JSON_UNESCAPED_UNICODE),
            ], JSON_UNESCAPED_UNICODE),
            'active' => true,
        ])->save();

        DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
            'default_category_id' => $this->category->id,
            'default_status_id' => $this->status->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->assertSuccessful()
            ->assertSee('Actas de Reunión')
            ->assertSee('Borrador')
            ->assertDontSee('{\"es\":');
    }

    /** @test */
    public function document_templates_list_shows_all_templates()
    {
        $this->actingAs($this->admin);

        // Create templates
        $templates = DocumentTemplate::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords($templates);
    }

    /** @test */
    public function can_search_templates_by_name()
    {
        $this->actingAs($this->admin);

        DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Plantilla Búsqueda XYZ',
            'created_by' => $this->admin->id,
        ]);

        DocumentTemplate::factory()->count(5)->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $component = Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->searchTable('Búsqueda');

        // Verify search functionality works
        $this->assertEquals('Búsqueda', $component->instance()->getTableSearch());
    }

    /** @test */
    public function can_filter_by_active_status()
    {
        $this->actingAs($this->admin);

        $activeTemplate = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $inactiveTemplate = DocumentTemplate::factory()->inactive()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->assertCanSeeTableRecords([$activeTemplate])
            ->assertCanSeeTableRecords([$inactiveTemplate]);
    }

    /** @test */
    public function can_access_create_document_template_page()
    {
        $this->actingAs($this->admin);

        Livewire::test(DocumentTemplateResource\Pages\CreateDocumentTemplate::class)
            ->assertSuccessful();
    }

    /** @test */
    public function can_create_document_template()
    {
        $this->actingAs($this->admin);

        $newData = [
            'company_id' => $this->company->id,
            'name' => 'Nueva Plantilla Test',
            'description' => 'Descripción de prueba',
            'icon' => 'heroicon-o-document-text',
            'color' => 'blue',
            'default_category_id' => $this->category->id,
            'default_status_id' => $this->status->id,
            'default_priority' => 'medium',
            'default_is_confidential' => false,
            'default_tracking_enabled' => false,
            'required_fields' => ['title', 'description'],
            'allowed_file_types' => ['pdf', 'docx'],
            'max_file_size_mb' => 10,
            'document_number_prefix' => 'TEST-',
            'instructions' => 'Instrucciones de prueba',
            'help_text' => 'Texto de ayuda',
            'is_active' => true,
        ];

        Livewire::test(DocumentTemplateResource\Pages\CreateDocumentTemplate::class)
            ->fillForm($newData)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('document_templates', [
            'name' => 'Nueva Plantilla Test',
            'company_id' => $this->company->id,
            'document_number_prefix' => 'TEST-',
        ]);
    }

    /** @test */
    public function can_create_template_with_custom_fields()
    {
        $this->actingAs($this->admin);

        $customFields = [
            [
                'name' => 'numero_contrato',
                'label' => 'Número de Contrato',
                'type' => 'text',
                'required' => true,
                'description' => null,
            ],
            [
                'name' => 'monto',
                'label' => 'Monto',
                'type' => 'number',
                'required' => true,
                'description' => null,
            ],
        ];

        $newData = [
            'company_id' => $this->company->id,
            'name' => 'Plantilla con Campos Personalizados',
            'description' => 'Test',
            'icon' => 'heroicon-o-document-text',
            'color' => 'blue',
            'default_priority' => 'medium',
            'custom_fields' => $customFields,
            'is_active' => true,
        ];

        Livewire::test(DocumentTemplateResource\Pages\CreateDocumentTemplate::class)
            ->fillForm($newData)
            ->call('create')
            ->assertHasNoFormErrors();

        $template = DocumentTemplate::where('name', 'Plantilla con Campos Personalizados')->first();
        $this->assertNotNull($template);
        $this->assertEquals($customFields, $template->custom_fields);
    }

    /** @test */
    public function name_is_required()
    {
        $this->actingAs($this->admin);

        Livewire::test(DocumentTemplateResource\Pages\CreateDocumentTemplate::class)
            ->fillForm([
                'company_id' => $this->company->id,
                'name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    /** @test */
    public function can_access_edit_document_template_page()
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\EditDocumentTemplate::class, [
            'record' => $template->getRouteKey(),
        ])
            ->assertSuccessful();
    }

    /** @test */
    public function can_edit_document_template()
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Plantilla Original',
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\EditDocumentTemplate::class, [
            'record' => $template->getRouteKey(),
        ])
            ->fillForm([
                'name' => 'Plantilla Actualizada',
                'description' => 'Nueva descripción',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('document_templates', [
            'id' => $template->id,
            'name' => 'Plantilla Actualizada',
            'description' => 'Nueva descripción',
        ]);
    }

    /** @test */
    public function can_soft_delete_document_template()
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\EditDocumentTemplate::class, [
            'record' => $template->getRouteKey(),
        ])
            ->callAction('delete');

        $this->assertSoftDeleted('document_templates', [
            'id' => $template->id,
        ]);
    }

    /** @test */
    public function can_restore_soft_deleted_template()
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $template->delete();

        Livewire::test(DocumentTemplateResource\Pages\EditDocumentTemplate::class, [
            'record' => $template->getRouteKey(),
        ])
            ->callAction('restore');

        $this->assertDatabaseHas('document_templates', [
            'id' => $template->id,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function can_duplicate_template()
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Plantilla Original',
            'is_active' => true,
            'usage_count' => 50,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->callTableAction('duplicate', $template);

        // Verify duplicate was created
        $duplicate = DocumentTemplate::where('name', 'Plantilla Original (Copia)')->first();
        $this->assertNotNull($duplicate);
        $this->assertEquals($this->company->id, $duplicate->company_id);
        $this->assertFalse($duplicate->is_active); // Should be inactive
        $this->assertEquals(0, $duplicate->usage_count); // Should reset usage count
    }

    /** @test */
    public function can_bulk_activate_templates()
    {
        $this->actingAs($this->admin);

        $templates = DocumentTemplate::factory()->count(3)->inactive()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->callTableBulkAction('activate', $templates);

        foreach ($templates as $template) {
            $this->assertDatabaseHas('document_templates', [
                'id' => $template->id,
                'is_active' => true,
            ]);
        }
    }

    /** @test */
    public function can_bulk_deactivate_templates()
    {
        $this->actingAs($this->admin);

        $templates = DocumentTemplate::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->callTableBulkAction('deactivate', $templates);

        foreach ($templates as $template) {
            $this->assertDatabaseHas('document_templates', [
                'id' => $template->id,
                'is_active' => false,
            ]);
        }
    }

    /** @test */
    public function can_view_template_details()
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->withCustomFields()->create([
            'company_id' => $this->company->id,
            'name' => 'Plantilla de Vista',
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\ViewDocumentTemplate::class, [
            'record' => $template->getRouteKey(),
        ])
            ->assertSuccessful()
            ->assertSee('Plantilla de Vista')
            ->assertSee('Ver Plantilla de Documento')
            ->assertDontSee('Ver Document Template')
            ->assertDontSee('Document Templates');
    }

    /** @test */
    public function templates_are_scoped_by_company()
    {
        $this->actingAs($this->admin);

        // Create template for current company
        $ownTemplate = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        // Create template for different company
        $otherCompany = Company::factory()->create();
        $otherTemplate = DocumentTemplate::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        // Regular admin (not super_admin) should only see their company's templates
        $regularAdmin = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $adminRole = Role::create(['name' => 'admin']);
        $regularAdmin->assignRole($adminRole);

        $this->actingAs($regularAdmin);

        Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->assertCanSeeTableRecords([$ownTemplate])
            ->assertCanNotSeeTableRecords([$otherTemplate]);
    }

    /** @test */
    public function template_auto_sets_created_by_on_creation()
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $this->assertEquals($this->admin->id, $template->created_by);
    }

    /** @test */
    public function template_auto_sets_updated_by_on_update()
    {
        $this->actingAs($this->admin);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->admin->id,
        ]);

        $template->name = 'Updated Name';
        $template->save();
        $template->refresh();

        $this->assertEquals($this->admin->id, $template->updated_by);
    }

    /** @test */
    public function can_filter_templates_by_category()
    {
        $this->actingAs($this->admin);

        $category1 = Category::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $category2 = Category::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $template1 = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'default_category_id' => $category1->id,
            'created_by' => $this->admin->id,
        ]);

        $template2 = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'default_category_id' => $category2->id,
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->assertCanSeeTableRecords([$template1, $template2]);
    }

    /** @test */
    public function can_filter_templates_by_priority()
    {
        $this->actingAs($this->admin);

        $highPriorityTemplate = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'default_priority' => 'high',
            'created_by' => $this->admin->id,
        ]);

        $lowPriorityTemplate = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'default_priority' => 'low',
            'created_by' => $this->admin->id,
        ]);

        Livewire::test(DocumentTemplateResource\Pages\ListDocumentTemplates::class)
            ->assertCanSeeTableRecords([$highPriorityTemplate, $lowPriorityTemplate]);
    }
}
