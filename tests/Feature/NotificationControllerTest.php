<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_unread_endpoint_formats_distribution_notifications_with_context(): void
    {
        $user = User::factory()->create();

        DatabaseNotification::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'type' => 'App\\Notifications\\DocumentDistributedToOfficeNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => [
                'type' => 'document_distributed_to_office',
                'document_id' => 10,
                'document_title' => 'Oficio de traslado',
                'document_number' => 'DOC-001',
                'department_name' => 'Recursos Humanos',
                'sender_name' => 'Gloria Recepción',
                'routing_note' => 'Revisar prioridad alta',
                'url' => route('documents.show', 10),
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('notifications.unread'));

        $response->assertSuccessful()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('notifications.0.title', 'Documento recibido por oficina');

        $this->assertStringContainsString('Recursos Humanos', $response->json('notifications.0.message'));
        $this->assertSame('Gloria Recepción', $response->json('notifications.0.meta.Enviado por'));
    }
}
