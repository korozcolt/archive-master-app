<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Document;
use App\Models\Company;
use App\Models\Status;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;
    protected $status;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->status = Status::factory()->create([
            'company_id' => $this->company->id,
        ]);
        $this->category = Category::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    /**
     * Test que un usuario puede ver la lista de documentos
     */
    public function test_user_can_view_documents_list()
    {
        Document::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/documents');

        $response->assertStatus(200);
        $response->assertViewIs('documents.index');
    }

    /**
     * Test que un usuario puede crear un documento
     */
    public function test_user_can_create_document()
    {
        $response = $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'Test Document',
                'document_number' => 'TEST-001',
                'description' => 'Test description',
                'status_id' => $this->status->id,
                'category_id' => $this->category->id,
                'priority' => 'medium',
            ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('documents', [
            'title' => 'Test Document',
            'document_number' => 'TEST-001',
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test que un usuario puede ver un documento
     */
    public function test_user_can_view_document()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/documents/{$document->id}");

        $response->assertStatus(200);
        $response->assertViewIs('documents.show');
    }

    /**
     * Test que un usuario puede actualizar un documento
     */
    public function test_user_can_update_document()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/documents/{$document->id}", [
                'title' => 'Updated Title',
                'document_number' => $document->document_number,
                'description' => 'Updated description',
                'status_id' => $this->status->id,
                'category_id' => $this->category->id,
                'priority' => 'high',
            ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);
    }

    /**
     * Test que un usuario puede eliminar un documento
     */
    public function test_user_can_delete_document()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/documents/{$document->id}");

        $response->assertRedirect();
        
        $this->assertSoftDeleted('documents', [
            'id' => $document->id,
        ]);
    }

    /**
     * Test que un usuario no puede ver documentos de otra empresa
     */
    public function test_user_cannot_view_other_company_documents()
    {
        $otherCompany = Company::factory()->create();
        $otherStatus = Status::factory()->create(['company_id' => $otherCompany->id]);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        
        $document = Document::factory()->create([
            'company_id' => $otherCompany->id,
            'status_id' => $otherStatus->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get("/documents/{$document->id}");

        $response->assertStatus(403);
    }

    /**
     * Test validación de campos requeridos al crear documento
     */
    public function test_document_creation_requires_fields()
    {
        $response = $this->actingAs($this->user)
            ->post('/documents', [
                'title' => '',
                'category_id' => '',
                'status_id' => '',
                'priority' => '',
            ]);

        $response->assertSessionHasErrors(['title', 'category_id', 'status_id', 'priority']);
    }

    /**
     * Test que el código de documento debe ser único por empresa
     */
    public function test_document_code_must_be_unique_per_company()
    {
        Document::factory()->create([
            'company_id' => $this->company->id,
            'status_id' => $this->status->id,
            'document_number' => 'UNIQUE-001',
        ]);

        $response = $this->actingAs($this->user)
            ->post('/documents', [
                'title' => 'Test Document',
                'document_number' => 'UNIQUE-001',
                'status_id' => $this->status->id,
                'category_id' => $this->category->id,
                'priority' => 'medium',
            ]);

        $response->assertSessionHasErrors('document_number');
    }
}
