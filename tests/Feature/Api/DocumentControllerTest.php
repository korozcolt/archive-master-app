<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Company;
use App\Models\Document;
use App\Models\Category;
use App\Models\Status;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $company;
    protected $category;
    protected $status;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear empresa de prueba
        $this->company = Company::factory()->create();

        // Crear usuario de prueba
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // Crear categoría de prueba
        $this->category = Category::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // Crear estado de prueba
        $this->status = Status::factory()->create([
            'company_id' => $this->company->id,
        ]);
    }

    /** @test */
    public function authenticated_user_can_list_documents()
    {
        // Crear algunos documentos
        Document::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson('/api/documents');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'document_number',
                            'title',
                            'description',
                            'status',
                            'category',
                            'created_at',
                            'updated_at',
                        ]
                    ],
                    'meta',
                    'timestamp'
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function authenticated_user_can_create_document()
    {
        $documentData = [
            'title' => 'Documento de Prueba',
            'description' => 'Descripción del documento de prueba',
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'priority' => 'medium',
            'is_confidential' => false,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                        ->postJson('/api/documents', $documentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'document_number',
                        'barcode',
                        'qrcode',
                        'title',
                        'created_at',
                    ],
                    'timestamp'
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertDatabaseHas('documents', [
            'title' => 'Documento de Prueba',
            'company_id' => $this->company->id,
            'created_by' => $this->user->id,
        ]);
    }

    /** @test */
    public function document_creation_requires_required_fields()
    {
        $response = $this->actingAs($this->user, 'sanctum')
                        ->postJson('/api/documents', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['title', 'category_id', 'status_id']);
    }

    /** @test */
    public function authenticated_user_can_view_document()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson("/api/documents/{$document->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'document_number',
                        'title',
                        'description',
                        'content',
                        'status',
                        'category',
                        'creator',
                        'tags',
                        'versions',
                        'workflow_history',
                        'created_at',
                        'updated_at',
                    ],
                    'timestamp'
                ]);

        $this->assertEquals($document->id, $response->json('data.id'));
    }

    /** @test */
    public function authenticated_user_can_update_document()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $updateData = [
            'title' => 'Título Actualizado',
            'description' => 'Descripción actualizada',
            'priority' => 'high',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                        ->putJson("/api/documents/{$document->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Documento actualizado exitosamente',
                ]);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Título Actualizado',
            'description' => 'Descripción actualizada',
        ]);
    }

    /** @test */
    public function authenticated_user_can_delete_document()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->deleteJson("/api/documents/{$document->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Documento eliminado exitosamente',
                ]);

        $this->assertSoftDeleted('documents', ['id' => $document->id]);
    }

    /** @test */
    public function user_cannot_access_documents_from_other_companies()
    {
        // Crear otra empresa y documento
        $otherCompany = Company::factory()->create();
        $otherCategory = Category::factory()->create(['company_id' => $otherCompany->id]);
        $otherStatus = Status::factory()->create(['company_id' => $otherCompany->id]);

        $otherDocument = Document::factory()->create([
            'company_id' => $otherCompany->id,
            'category_id' => $otherCategory->id,
            'status_id' => $otherStatus->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson("/api/documents/{$otherDocument->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function user_can_search_documents()
    {
        // Crear documentos con diferentes títulos
        Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'title' => 'Contrato de Servicios',
            'created_by' => $this->user->id,
        ]);

        Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'title' => 'Factura de Compra',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson('/api/documents?search=contrato');

        $response->assertStatus(200);

        // Verificar que solo se devuelve el documento que coincide
        $documents = $response->json('data');
        $this->assertCount(1, $documents);
        $this->assertStringContainsString('Contrato', $documents[0]['title']);
    }
}
