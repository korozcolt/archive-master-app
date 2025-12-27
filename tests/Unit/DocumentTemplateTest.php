<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Company;
use App\Models\DocumentTemplate;
use App\Models\PhysicalLocation;
use App\Models\PhysicalLocationTemplate;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    /** @test */
    public function it_can_increment_usage_count()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'usage_count' => 5,
            'last_used_at' => null,
        ]);

        $template->incrementUsage();

        $this->assertEquals(6, $template->fresh()->usage_count);
        $this->assertNotNull($template->fresh()->last_used_at);
    }

    /** @test */
    public function it_can_apply_defaults_to_document()
    {
        $category = Category::factory()->create(['company_id' => $this->company->id]);
        $status = Status::factory()->create(['company_id' => $this->company->id]);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'default_category_id' => $category->id,
            'default_status_id' => $status->id,
            'default_priority' => 'high',
            'default_is_confidential' => true,
            'default_tracking_enabled' => true,
            'document_number_prefix' => 'TEST-',
            'default_tags' => ['tag1', 'tag2'],
        ]);

        $applied = $template->applyToDocument();

        $this->assertEquals($category->id, $applied['category_id']);
        $this->assertEquals($status->id, $applied['status_id']);
        $this->assertEquals('high', $applied['priority']);
        $this->assertTrue($applied['is_confidential']);
        $this->assertTrue($applied['tracking_enabled']);
        $this->assertEquals(['tag1', 'tag2'], $applied['tags']);
        $this->assertEquals('TEST-', $applied['_template_prefix']);
    }

    /** @test */
    public function it_can_override_defaults_when_applying_to_document()
    {
        $category = Category::factory()->create(['company_id' => $this->company->id]);
        $status = Status::factory()->create(['company_id' => $this->company->id]);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'default_category_id' => $category->id,
            'default_status_id' => $status->id,
            'default_priority' => 'high',
        ]);

        $applied = $template->applyToDocument([
            'priority' => 'low',
            'custom_field' => 'custom_value',
        ]);

        $this->assertEquals('low', $applied['priority']); // Override worked
        $this->assertEquals('custom_value', $applied['custom_field']); // Custom field added
        $this->assertEquals($category->id, $applied['category_id']); // Default preserved
    }

    /** @test */
    public function it_can_validate_required_fields()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'required_fields' => ['title', 'description', 'file'],
        ]);

        $validData = [
            'title' => 'Test',
            'description' => 'Test description',
            'file' => 'test.pdf',
        ];

        $invalidData = [
            'title' => 'Test',
        ];

        $this->assertEmpty($template->validateData($validData));
        $this->assertNotEmpty($template->validateData($invalidData));
        $this->assertArrayHasKey('description', $template->validateData($invalidData));
        $this->assertArrayHasKey('file', $template->validateData($invalidData));
    }

    /** @test */
    public function it_can_check_if_file_type_is_allowed()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'allowed_file_types' => ['pdf', 'docx', 'xlsx'],
        ]);

        $this->assertTrue($template->isFileTypeAllowed('pdf'));
        $this->assertTrue($template->isFileTypeAllowed('DOCX')); // Case insensitive
        $this->assertFalse($template->isFileTypeAllowed('jpg'));
        $this->assertFalse($template->isFileTypeAllowed('exe'));
    }

    /** @test */
    public function it_allows_all_file_types_when_no_restriction_is_set()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'allowed_file_types' => null,
        ]);

        $this->assertTrue($template->isFileTypeAllowed('pdf'));
        $this->assertTrue($template->isFileTypeAllowed('jpg'));
        $this->assertTrue($template->isFileTypeAllowed('anything'));
    }

    /** @test */
    public function it_can_check_if_file_size_is_allowed()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'max_file_size_mb' => 10, // 10 MB
        ]);

        $sizeInBytes5MB = 5 * 1024 * 1024; // 5 MB
        $sizeInBytes15MB = 15 * 1024 * 1024; // 15 MB

        $this->assertTrue($template->isFileSizeAllowed($sizeInBytes5MB));
        $this->assertFalse($template->isFileSizeAllowed($sizeInBytes15MB));
    }

    /** @test */
    public function it_allows_any_file_size_when_no_limit_is_set()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'max_file_size_mb' => null,
        ]);

        $sizeInBytes100MB = 100 * 1024 * 1024; // 100 MB

        $this->assertTrue($template->isFileSizeAllowed($sizeInBytes100MB));
    }

    /** @test */
    public function it_can_get_custom_fields_with_values()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'custom_fields' => [
                ['name' => 'field1', 'label' => 'Field 1', 'type' => 'text'],
                ['name' => 'field2', 'label' => 'Field 2', 'type' => 'number'],
            ],
        ]);

        $values = [
            'field1' => 'Value 1',
            'field2' => 123,
        ];

        $fieldsWithValues = $template->getCustomFieldsWithValues($values);

        $this->assertCount(2, $fieldsWithValues);
        $this->assertEquals('Value 1', $fieldsWithValues[0]['value']);
        $this->assertEquals(123, $fieldsWithValues[1]['value']);
    }

    /** @test */
    public function scope_active_returns_only_active_templates()
    {
        $activeTemplate = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'is_active' => true,
        ]);

        $inactiveTemplate = DocumentTemplate::factory()->inactive()->create([
            'company_id' => $this->company->id,
        ]);

        $activeTemplates = DocumentTemplate::active()->get();

        $this->assertTrue($activeTemplates->contains($activeTemplate));
        $this->assertFalse($activeTemplates->contains($inactiveTemplate));
    }

    /** @test */
    public function scope_for_company_returns_only_company_templates()
    {
        $template1 = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $otherCompany = Company::factory()->create();
        $template2 = DocumentTemplate::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $companyTemplates = DocumentTemplate::forCompany($this->company->id)->get();

        $this->assertTrue($companyTemplates->contains($template1));
        $this->assertFalse($companyTemplates->contains($template2));
    }

    /** @test */
    public function scope_most_used_returns_templates_ordered_by_usage()
    {
        $template1 = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'usage_count' => 10,
        ]);

        $template2 = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'usage_count' => 50,
        ]);

        $template3 = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'usage_count' => 25,
        ]);

        $mostUsed = DocumentTemplate::mostUsed(10)->get();

        $this->assertEquals($template2->id, $mostUsed->first()->id);
        $this->assertEquals($template3->id, $mostUsed->get(1)->id);
        $this->assertEquals($template1->id, $mostUsed->get(2)->id);
    }

    /** @test */
    public function scope_recently_used_returns_templates_ordered_by_last_used()
    {
        $template1 = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'last_used_at' => now()->subDays(10),
        ]);

        $template2 = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'last_used_at' => now()->subDay(),
        ]);

        $template3 = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'last_used_at' => now()->subDays(5),
        ]);

        $recentlyUsed = DocumentTemplate::recentlyUsed(10)->get();

        $this->assertEquals($template2->id, $recentlyUsed->first()->id);
        $this->assertEquals($template3->id, $recentlyUsed->get(1)->id);
        $this->assertEquals($template1->id, $recentlyUsed->get(2)->id);
    }

    /** @test */
    public function it_auto_sets_created_by_on_creation()
    {
        $this->actingAs($this->user);

        $template = DocumentTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'Test Template',
            'default_priority' => 'medium',
        ]);

        $this->assertEquals($this->user->id, $template->created_by);
        $this->assertEquals($this->company->id, $template->company_id);
    }

    /** @test */
    public function it_auto_sets_updated_by_on_update()
    {
        $this->actingAs($this->user);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $template->name = 'Updated Name';
        $template->save();
        $template->refresh();

        $this->assertEquals($this->user->id, $template->updated_by);
    }

    /** @test */
    public function it_has_company_relationship()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $this->assertInstanceOf(Company::class, $template->company);
        $this->assertEquals($this->company->id, $template->company->id);
    }

    /** @test */
    public function it_has_default_category_relationship()
    {
        $category = Category::factory()->create(['company_id' => $this->company->id]);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'default_category_id' => $category->id,
        ]);

        $this->assertInstanceOf(Category::class, $template->defaultCategory);
        $this->assertEquals($category->id, $template->defaultCategory->id);
    }

    /** @test */
    public function it_has_default_status_relationship()
    {
        $status = Status::factory()->create(['company_id' => $this->company->id]);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'default_status_id' => $status->id,
        ]);

        $this->assertInstanceOf(Status::class, $template->defaultStatus);
        $this->assertEquals($status->id, $template->defaultStatus->id);
    }

    /** @test */
    public function it_has_created_by_relationship()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $template->createdBy);
        $this->assertEquals($this->user->id, $template->createdBy->id);
    }

    /** @test */
    public function it_has_updated_by_relationship()
    {
        $updater = User::factory()->create(['company_id' => $this->company->id]);

        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'updated_by' => $updater->id,
        ]);

        $this->assertInstanceOf(User::class, $template->updatedBy);
        $this->assertEquals($updater->id, $template->updatedBy->id);
    }

    /** @test */
    public function it_casts_json_fields_correctly()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'custom_fields' => [['name' => 'test', 'label' => 'Test']],
            'required_fields' => ['title', 'description'],
            'allowed_file_types' => ['pdf', 'docx'],
            'default_tags' => ['tag1', 'tag2'],
            'suggested_tags' => ['tag3', 'tag4'],
        ]);

        $this->assertIsArray($template->custom_fields);
        $this->assertIsArray($template->required_fields);
        $this->assertIsArray($template->allowed_file_types);
        $this->assertIsArray($template->default_tags);
        $this->assertIsArray($template->suggested_tags);
    }

    /** @test */
    public function it_casts_boolean_fields_correctly()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
            'default_is_confidential' => true,
            'default_tracking_enabled' => false,
            'is_active' => true,
        ]);

        $this->assertIsBool($template->default_is_confidential);
        $this->assertIsBool($template->default_tracking_enabled);
        $this->assertIsBool($template->is_active);
    }

    /** @test */
    public function it_soft_deletes()
    {
        $template = DocumentTemplate::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $template->delete();

        $this->assertSoftDeleted('document_templates', [
            'id' => $template->id,
        ]);

        $this->assertNotNull($template->fresh()->deleted_at);
    }
}
