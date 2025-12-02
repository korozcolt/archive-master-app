<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Document;
use App\Models\Company;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class NotificationTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $user;
    protected $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'email' => 'user@test.com',
        ]);

        // Crear notificaciones de prueba
        $this->user->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\DocumentDueSoon',
            'data' => [
                'type' => 'document_due_soon',
                'title' => 'Documento próximo a vencer',
                'message' => 'El documento "Test Document" vence en 3 días',
                'urgency' => 'warning',
            ],
            'read_at' => null,
        ]);

        $this->user->notifications()->create([
            'id' => \Illuminate\Support\Str::uuid(),
            'type' => 'App\Notifications\DocumentAssigned',
            'data' => [
                'type' => 'document_assigned',
                'title' => 'Documento asignado',
                'message' => 'Se te ha asignado un nuevo documento',
            ],
            'read_at' => now(),
        ]);
    }

    /**
     * Test que el usuario puede ver sus notificaciones
     */
    public function testUserCanViewNotifications()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/notifications')
                    ->assertSee('Notificaciones')
                    ->assertSee('Documento próximo a vencer')
                    ->assertSee('Documento asignado');
        });
    }

    /**
     * Test que el contador de notificaciones funciona
     */
    public function testNotificationCounterWorks()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->assertSee('1') // 1 notificación no leída
                    ->assertPresent('.notification-badge');
        });
    }

    /**
     * Test que el usuario puede marcar una notificación como leída
     */
    public function testUserCanMarkNotificationAsRead()
    {
        $notification = $this->user->unreadNotifications()->first();

        $this->browse(function (Browser $browser) use ($notification) {
            $browser->loginAs($this->user)
                    ->visit('/notifications')
                    ->click('[data-notification-id="' . $notification->id . '"] .mark-read-btn')
                    ->waitForText('Notificación marcada como leída');

            // Verificar en base de datos
            $notification->refresh();
            $this->assertNotNull($notification->read_at);
        });
    }

    /**
     * Test que el usuario puede marcar todas como leídas
     */
    public function testUserCanMarkAllAsRead()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/notifications')
                    ->press('Marcar todas como leídas')
                    ->waitForText('Todas las notificaciones marcadas como leídas');

            // Verificar en base de datos
            $this->assertEquals(0, $this->user->unreadNotifications()->count());
        });
    }

    /**
     * Test que el usuario puede eliminar una notificación
     */
    public function testUserCanDeleteNotification()
    {
        $notification = $this->user->notifications()->first();

        $this->browse(function (Browser $browser) use ($notification) {
            $browser->loginAs($this->user)
                    ->visit('/notifications')
                    ->click('[data-notification-id="' . $notification->id . '"] .delete-btn')
                    ->whenAvailable('.confirm-dialog', function ($modal) {
                        $modal->press('Confirmar');
                    })
                    ->waitForText('Notificación eliminada');

            // Verificar en base de datos
            $this->assertNull(DatabaseNotification::find($notification->id));
        });
    }

    /**
     * Test que el usuario puede filtrar notificaciones por leídas/no leídas
     */
    public function testUserCanFilterNotifications()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/notifications')
                    ->select('filter', 'unread')
                    ->waitForReload()
                    ->assertSee('Documento próximo a vencer')
                    ->assertDontSee('Se te ha asignado un nuevo documento');

            $browser->select('filter', 'read')
                    ->waitForReload()
                    ->assertDontSee('Documento próximo a vencer')
                    ->assertSee('Se te ha asignado un nuevo documento');
        });
    }

    /**
     * Test que las notificaciones se cargan con paginación
     */
    public function testNotificationsPagination()
    {
        // Crear 25 notificaciones adicionales
        for ($i = 0; $i < 25; $i++) {
            $this->user->notifications()->create([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\DocumentDueSoon',
                'data' => [
                    'type' => 'document_due_soon',
                    'title' => 'Notificación ' . $i,
                    'message' => 'Mensaje de prueba ' . $i,
                ],
            ]);
        }

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/notifications')
                    ->assertPresent('.pagination')
                    ->click('.pagination a[rel="next"]')
                    ->waitForReload()
                    ->assertPresent('.notification-item');
        });
    }

    /**
     * Test que el dropdown de notificaciones funciona
     */
    public function testNotificationDropdownWorks()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/dashboard')
                    ->click('.notification-bell')
                    ->waitFor('.notification-dropdown')
                    ->assertSee('Documento próximo a vencer')
                    ->assertPresent('.notification-dropdown .notification-item');
        });
    }

    /**
     * Test que se pueden limpiar notificaciones leídas
     */
    public function testUserCanClearReadNotifications()
    {
        // Marcar todas como leídas primero
        $this->user->unreadNotifications->markAsRead();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/notifications')
                    ->press('Limpiar leídas')
                    ->whenAvailable('.confirm-dialog', function ($modal) {
                        $modal->press('Confirmar');
                    })
                    ->waitForText('Notificaciones leídas eliminadas');

            // Verificar que solo quedan las no leídas
            $this->assertEquals(0, $this->user->notifications()->whereNotNull('read_at')->count());
        });
    }
}
