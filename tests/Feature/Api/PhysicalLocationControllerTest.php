<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\PhysicalLocation;
use App\Models\PhysicalLocationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PhysicalLocationControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $company;
    protected $template;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear empresa de prueba
        $this->company = Company::factory()->create();

        // Crear usuario de prueba
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // Crear plantilla de ubicación de prueba
        $this->template = PhysicalLocationTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'Estructura de Prueba',
            'description' => 'Plantilla para pruebas',
            'is_active' => true,
            'levels' => [
                ['order' => 1, 'name' => 'Edificio', 'code' => 'ED', 'required' => true],
                ['order' => 2, 'name' => 'Piso', 'code' => 'P', 'required' => true],
                ['order' => 3, 'name' => 'Sala', 'code' => 'SALA', 'required' => true],
            ],
        ]);
    }

    /** @test */
    public function authenticated_user_can_list_physical_locations()
    {
        // Crear algunas ubicaciones
        PhysicalLocation::factory()->count(3)->create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson('/api/physical-locations');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'full_path',
                            'structured_data',
                            'capacity_total',
                            'capacity_used',
                            'is_active',
                            'created_at',
                            'updated_at',
                        ]
                    ],
                    'meta',
                    'links',
                    'timestamp'
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function authenticated_user_can_create_physical_location()
    {
        $locationData = [
            'template_id' => $this->template->id,
            'structured_data' => [
                'edificio' => 'A',
                'piso' => '3',
                'sala' => 'Archivo',
            ],
            'capacity_total' => 100,
            'notes' => 'Ubicación de prueba',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                        ->postJson('/api/physical-locations', $locationData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'code',
                        'full_path',
                        'structured_data',
                        'capacity_total',
                        'capacity_used',
                        'is_active',
                    ],
                    'timestamp'
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertDatabaseHas('physical_locations', [
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'capacity_total' => 100,
        ]);
    }

    /** @test */
    public function authenticated_user_can_view_physical_location()
    {
        $location = PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'B', 'piso' => '2', 'sala' => 'Sala 1'],
            'capacity_total' => 50,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson("/api/physical-locations/{$location->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'code',
                        'full_path',
                        'structured_data',
                        'capacity_total',
                        'capacity_used',
                        'capacity_percentage',
                        'is_full',
                        'document_count',
                        'template',
                        'company',
                    ],
                    'timestamp'
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($location->id, $response->json('data.id'));
    }

    /** @test */
    public function authenticated_user_can_update_physical_location()
    {
        $location = PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'C', 'piso' => '1', 'sala' => 'Sala 2'],
            'capacity_total' => 75,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $updateData = [
            'capacity_total' => 100,
            'notes' => 'Capacidad ampliada',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                        ->putJson("/api/physical-locations/{$location->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Ubicación física actualizada exitosamente',
                ]);

        $this->assertDatabaseHas('physical_locations', [
            'id' => $location->id,
            'capacity_total' => 100,
            'notes' => 'Capacidad ampliada',
        ]);
    }

    /** @test */
    public function cannot_reduce_capacity_below_used_capacity()
    {
        $location = PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'D', 'piso' => '1', 'sala' => 'Sala 3'],
            'capacity_total' => 100,
            'capacity_used' => 50,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $updateData = [
            'capacity_total' => 40, // Menor que capacity_used (50)
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                        ->putJson("/api/physical-locations/{$location->id}", $updateData);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'La capacidad total no puede ser menor que la capacidad usada actual',
                ]);
    }

    /** @test */
    public function authenticated_user_can_delete_physical_location_without_documents()
    {
        $location = PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'E', 'piso' => '1', 'sala' => 'Sala 4'],
            'capacity_total' => 100,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->deleteJson("/api/physical-locations/{$location->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Ubicación física eliminada exitosamente',
                ]);

        $this->assertSoftDeleted('physical_locations', [
            'id' => $location->id,
        ]);
    }

    /** @test */
    public function authenticated_user_can_search_physical_locations()
    {
        PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'Principal', 'piso' => '1', 'sala' => 'Archivo'],
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson('/api/physical-locations/search?query=Principal');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'full_path',
                            'structured_data',
                        ]
                    ],
                    'timestamp'
                ]);

        $this->assertTrue($response->json('success'));
    }

    /** @test */
    public function authenticated_user_can_check_capacity()
    {
        $location = PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'F', 'piso' => '2', 'sala' => 'Sala 5'],
            'capacity_total' => 100,
            'capacity_used' => 75,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson("/api/physical-locations/{$location->id}/capacity");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'location_id',
                        'code',
                        'full_path',
                        'capacity_total',
                        'capacity_used',
                        'capacity_available',
                        'capacity_percentage',
                        'is_full',
                        'has_capacity_limit',
                        'document_count',
                    ],
                    'timestamp'
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals(100, $response->json('data.capacity_total'));
        $this->assertEquals(75, $response->json('data.capacity_used'));
        $this->assertEquals(25, $response->json('data.capacity_available'));
        $this->assertEquals(75.0, $response->json('data.capacity_percentage'));
        $this->assertFalse($response->json('data.is_full'));
    }

    /** @test */
    public function authenticated_user_can_list_available_locations()
    {
        // Crear ubicación con capacidad disponible
        PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'G', 'piso' => '1', 'sala' => 'Sala 6'],
            'capacity_total' => 100,
            'capacity_used' => 50,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        // Crear ubicación llena
        PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'H', 'piso' => '1', 'sala' => 'Sala 7'],
            'capacity_total' => 100,
            'capacity_used' => 100,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson('/api/physical-locations/available');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'code',
                            'full_path',
                            'capacity_available',
                            'capacity_percentage',
                            'template',
                        ]
                    ],
                    'timestamp'
                ]);

        $this->assertTrue($response->json('success'));
        // Solo debe retornar la ubicación con capacidad disponible
        $this->assertEquals(1, count($response->json('data')));
    }

    /** @test */
    public function authenticated_user_can_find_location_by_code()
    {
        $location = PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'I', 'piso' => '1', 'sala' => 'Sala 8'],
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson("/api/physical-locations/{$location->code}/by-code");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'code',
                        'full_path',
                        'capacity_percentage',
                        'is_full',
                    ],
                    'timestamp'
                ]);

        $this->assertTrue($response->json('success'));
        $this->assertEquals($location->id, $response->json('data.id'));
        $this->assertEquals($location->code, $response->json('data.code'));
    }

    /** @test */
    public function unauthenticated_user_cannot_access_physical_locations()
    {
        $response = $this->getJson('/api/physical-locations');

        $response->assertStatus(401);
    }

    /** @test */
    public function user_cannot_access_other_company_locations()
    {
        // Crear otra empresa
        $otherCompany = Company::factory()->create();

        // Crear ubicación de otra empresa
        $otherLocation = PhysicalLocation::create([
            'company_id' => $otherCompany->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'J', 'piso' => '1', 'sala' => 'Sala 9'],
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson("/api/physical-locations/{$otherLocation->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function validation_fails_when_creating_location_without_required_fields()
    {
        $invalidData = [
            'notes' => 'Solo notas sin datos requeridos',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
                        ->postJson('/api/physical-locations', $invalidData);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['template_id', 'structured_data']);
    }

    /** @test */
    public function can_filter_locations_by_template()
    {
        // Crear otra plantilla
        $template2 = PhysicalLocationTemplate::create([
            'company_id' => $this->company->id,
            'name' => 'Otra Plantilla',
            'is_active' => true,
            'levels' => [
                ['order' => 1, 'name' => 'Almacen', 'code' => 'ALM', 'required' => true],
            ],
        ]);

        // Crear ubicaciones con diferentes plantillas
        PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'K', 'piso' => '1', 'sala' => 'Sala 10'],
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $template2->id,
            'structured_data' => ['almacen' => 'Central'],
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson("/api/physical-locations?template_id={$this->template->id}");

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }

    /** @test */
    public function can_filter_locations_by_active_status()
    {
        // Crear ubicación activa
        PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'L', 'piso' => '1', 'sala' => 'Sala 11'],
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        // Crear ubicación inactiva
        PhysicalLocation::create([
            'company_id' => $this->company->id,
            'template_id' => $this->template->id,
            'structured_data' => ['edificio' => 'M', 'piso' => '1', 'sala' => 'Sala 12'],
            'is_active' => false,
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
                        ->getJson('/api/physical-locations?is_active=true');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data')));
    }
}
