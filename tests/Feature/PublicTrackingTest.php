<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Company;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use App\Models\WorkflowHistory;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected $company;

    protected $user;

    protected $category;

    protected $status;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->category = Category::factory()->create(['company_id' => $this->company->id]);
        $this->status = Status::factory()->create(['company_id' => $this->company->id]);
    }

    /** @test */
    public function tracking_index_page_is_publicly_accessible()
    {
        $response = $this->get(route('tracking.index'));

        $response->assertStatus(200);
        $response->assertViewIs('tracking.index');
    }

    /** @test */
    public function can_track_document_with_valid_code()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
            'tracking_enabled' => true,
            'public_tracking_code' => str_repeat('E', 32),
        ]);

        $response = $this->post(route('tracking.track'), [
            'tracking_code' => str_repeat('E', 32),
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('tracking.show');
        $response->assertViewHas('document');
    }

    /** @test */
    public function cannot_track_document_with_invalid_code()
    {
        $invalidCode = str_repeat('F', 32);
        $response = $this->post(route('tracking.track'), [
            'tracking_code' => $invalidCode,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');
    }

    /** @test */
    public function cannot_track_document_when_tracking_is_disabled()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
            'tracking_enabled' => false,
            'public_tracking_code' => str_repeat('G', 32),
        ]);

        $response = $this->post(route('tracking.track'), [
            'tracking_code' => str_repeat('G', 32),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error');
    }

    /** @test */
    public function cannot_track_document_when_tracking_is_expired()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
            'tracking_enabled' => true,
            'public_tracking_code' => str_repeat('H', 32),
            'tracking_expires_at' => Carbon::now()->subDay(), // Expiró ayer
        ]);

        $response = $this->post(route('tracking.track'), [
            'tracking_code' => str_repeat('H', 32),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error', 'El código de tracking ha expirado');
    }

    /** @test */
    public function can_track_document_when_expiration_is_in_future()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
            'tracking_enabled' => true,
            'public_tracking_code' => str_repeat('I', 32),
            'tracking_expires_at' => Carbon::now()->addWeek(), // Expira en una semana
        ]);

        $response = $this->post(route('tracking.track'), [
            'tracking_code' => str_repeat('I', 32),
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('tracking.show');
    }

    /** @test */
    public function tracking_code_validation_requires_32_characters()
    {
        $response = $this->post(route('tracking.track'), [
            'tracking_code' => 'SHORT',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('tracking_code');
    }

    /** @test */
    public function tracking_code_is_required()
    {
        $response = $this->post(route('tracking.track'), []);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('tracking_code');
    }

    /** @test */
    public function tracking_code_is_case_insensitive()
    {
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
            'tracking_enabled' => true,
            'public_tracking_code' => 'ABC123DEF456GHI789JKL012MNO345PQ',
        ]);

        // Enviar en minúsculas
        $response = $this->post(route('tracking.track'), [
            'tracking_code' => 'abc123def456ghi789jkl012mno345pq',
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('tracking.show');
    }

    /** @test */
    public function tracking_shows_workflow_history()
    {
        $status1 = Status::factory()->create(['company_id' => $this->company->id, 'name' => 'Recibido']);
        $status2 = Status::factory()->create(['company_id' => $this->company->id, 'name' => 'En proceso']);

        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $status2->id,
            'created_by' => $this->user->id,
            'tracking_enabled' => true,
            'public_tracking_code' => str_repeat('J', 32),
        ]);

        // Crear historial de workflow
        WorkflowHistory::create([
            'document_id' => $document->id,
            'from_status_id' => $status1->id,
            'to_status_id' => $status2->id,
            'user_id' => $this->user->id,
            'comment' => 'Documento en proceso de revisión',
        ]);

        $response = $this->post(route('tracking.track'), [
            'tracking_code' => str_repeat('J', 32),
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('tracking.show');
        $response->assertViewHas('document');

        $documentData = $response->viewData('document');
        $this->assertArrayHasKey('workflow_history', $documentData);
        $this->assertCount(1, $documentData['workflow_history']);
    }

    /** @test */
    public function api_endpoint_returns_json_with_valid_code()
    {
        $trackingCode = str_repeat('A', 32);
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
            'tracking_enabled' => true,
            'public_tracking_code' => $trackingCode,
        ]);

        $response = $this->getJson(route('tracking.api', [
            'tracking_code' => $trackingCode,
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'tracking_code',
                'title',
                'description',
                'status',
                'category',
                'created_at',
                'updated_at',
                'tracking_expires_at',
                'timeline',
            ],
            'timestamp',
        ]);
        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function api_endpoint_returns_error_with_invalid_code()
    {
        $invalidCode = str_repeat('B', 32);
        $response = $this->getJson(route('tracking.api', [
            'tracking_code' => $invalidCode,
        ]));

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
        ]);
    }

    /** @test */
    public function api_endpoint_returns_410_for_expired_code()
    {
        $trackingCode = str_repeat('C', 32);
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
            'tracking_enabled' => true,
            'public_tracking_code' => $trackingCode,
            'tracking_expires_at' => Carbon::now()->subDay(),
        ]);

        $response = $this->getJson(route('tracking.api', [
            'tracking_code' => $trackingCode,
        ]));

        $response->assertStatus(410); // 410 Gone
        $response->assertJson([
            'success' => false,
            'message' => 'El código de tracking ha expirado',
        ]);
    }

    /** @test */
    public function tracking_does_not_expose_sensitive_information()
    {
        $trackingCode = str_repeat('D', 32);
        $document = Document::factory()->create([
            'company_id' => $this->company->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'created_by' => $this->user->id,
            'tracking_enabled' => true,
            'public_tracking_code' => $trackingCode,
            'document_number' => 'DOC-2025-0001',
        ]);

        $response = $this->getJson(route('tracking.api', [
            'tracking_code' => $trackingCode,
        ]));

        $response->assertStatus(200);

        // Verificar que NO se exponen datos sensibles
        $data = $response->json('data');
        $this->assertArrayNotHasKey('document_number', $data);
        $this->assertArrayNotHasKey('file', $data);
        $this->assertArrayNotHasKey('assigned_to', $data);
        $this->assertArrayNotHasKey('created_by', $data);
        $this->assertArrayNotHasKey('company_id', $data);

        // Verificar que SÍ se exponen datos públicos
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timeline', $data);
    }
}
