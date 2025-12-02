<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        // Crear notificaciones de prueba
        $this->user->notifications()->create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\DocumentDueSoon',
            'data' => [
                'type' => 'document_due_soon',
                'title' => 'Documento próximo a vencer',
                'message' => 'El documento vence pronto',
            ],
            'read_at' => null,
        ]);

        $this->user->notifications()->create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\DocumentAssigned',
            'data' => [
                'type' => 'document_assigned',
                'title' => 'Documento asignado',
                'message' => 'Se te ha asignado un documento',
            ],
            'read_at' => now(),
        ]);
    }

    /**
     * Test que un usuario puede ver sus notificaciones
     */
    public function test_user_can_view_notifications()
    {
        $response = $this->actingAs($this->user)
            ->get('/notifications');

        $response->assertStatus(200);
        $response->assertViewIs('notifications.index');
        $response->assertViewHas('notifications');
    }

    /**
     * Test que un usuario puede marcar una notificación como leída
     */
    public function test_user_can_mark_notification_as_read()
    {
        $notification = $this->user->unreadNotifications()->first();

        $response = $this->actingAs($this->user)
            ->post("/notifications/{$notification->id}/read");

        $response->assertRedirect();
        
        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /**
     * Test que un usuario puede marcar todas las notificaciones como leídas
     */
    public function test_user_can_mark_all_as_read()
    {
        $response = $this->actingAs($this->user)
            ->post('/notifications/read-all');

        $response->assertRedirect();
        
        $this->assertEquals(0, $this->user->unreadNotifications()->count());
    }

    /**
     * Test que un usuario puede eliminar una notificación
     */
    public function test_user_can_delete_notification()
    {
        $notification = $this->user->notifications()->first();

        $response = $this->actingAs($this->user)
            ->delete("/notifications/{$notification->id}");

        $response->assertRedirect();
        
        $this->assertNull(DatabaseNotification::find($notification->id));
    }

    /**
     * Test que un usuario puede filtrar notificaciones no leídas
     */
    public function test_user_can_filter_unread_notifications()
    {
        $response = $this->actingAs($this->user)
            ->get('/notifications?filter=unread');

        $response->assertStatus(200);
        $notifications = $response->viewData('notifications');
        
        $this->assertGreaterThanOrEqual(1, $notifications->count());
        $this->assertNull($notifications->first()->read_at);
    }

    /**
     * Test que un usuario puede filtrar notificaciones leídas
     */
    public function test_user_can_filter_read_notifications()
    {
        $response = $this->actingAs($this->user)
            ->get('/notifications?filter=read');

        $response->assertStatus(200);
        $notifications = $response->viewData('notifications');
        
        $this->assertGreaterThanOrEqual(1, $notifications->count());
        $this->assertNotNull($notifications->first()->read_at);
    }

    /**
     * Test que las notificaciones están paginadas
     */
    public function test_notifications_are_paginated()
    {
        // Crear 25 notificaciones adicionales
        for ($i = 0; $i < 25; $i++) {
            $this->user->notifications()->create([
                'id' => Str::uuid(),
                'type' => 'App\Notifications\DocumentDueSoon',
                'data' => [
                    'type' => 'test',
                    'title' => 'Notificación ' . $i,
                    'message' => 'Mensaje ' . $i,
                ],
            ]);
        }

        $response = $this->actingAs($this->user)
            ->get('/notifications');

        $response->assertStatus(200);
        $notifications = $response->viewData('notifications');
        
        $this->assertInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class, $notifications);
    }

    /**
     * Test que un usuario no puede ver notificaciones de otros usuarios
     */
    public function test_user_cannot_access_other_user_notifications()
    {
        $otherUser = User::factory()->create([
            'company_id' => $this->company->id,
        ]);
        
        $otherNotification = $otherUser->notifications()->create([
            'id' => Str::uuid(),
            'type' => 'App\Notifications\DocumentAssigned',
            'data' => [
                'type' => 'test',
                'title' => 'Privada',
                'message' => 'No debes ver esto',
            ],
        ]);

        $response = $this->actingAs($this->user)
            ->post("/notifications/{$otherNotification->id}/read");

        $response->assertStatus(404);
    }

    /**
     * Test que se pueden limpiar notificaciones leídas
     */
    public function test_user_can_clear_read_notifications()
    {
        // Marcar todas como leídas
        $this->user->unreadNotifications->markAsRead();

        $response = $this->actingAs($this->user)
            ->delete('/notifications/clear/read');

        $response->assertRedirect();
        
        $this->assertEquals(0, $this->user->notifications()->whereNotNull('read_at')->count());
    }

    /**
     * Test contador de notificaciones no leídas
     */
    public function test_unread_notifications_count()
    {
        $count = $this->user->unreadNotifications()->count();
        
        $this->assertEquals(1, $count);
    }
}
