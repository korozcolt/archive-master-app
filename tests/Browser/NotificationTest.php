<?php

namespace Tests\Browser;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
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
        $this->user->assignRole(Role::firstOrCreate(['name' => 'regular_user']));

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
    public function test_user_can_view_notifications()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/notifications')
                ->assertPathIs('/notifications')
                ->assertPresent('form[action$="/notifications/read-all"]')
                ->assertPresent('form[action$="/notifications/clear/read"]')
                ->assertPresent('form[action*="/notifications/"][action$="/read"]')
                ->assertPresent('form[action*="/notifications/"][action$="/read"] button');
        });
    }

    /**
     * Test que el contador de notificaciones funciona
     */
    public function test_notification_counter_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/portal')
                ->waitForText('Resumen personal de documentos')
                ->assertSee('1')
                ->assertVisible('nav .relative > button span[x-show]');
        });
    }

    /**
     * Test que el usuario puede marcar una notificación como leída
     */
    public function test_user_can_mark_notification_as_read()
    {
        $notification = $this->user->unreadNotifications()->first();

        $this->browse(function (Browser $browser) use ($notification) {
            $browser->loginAs($this->user)
                ->visit('/notifications');

            $browser->script(
                "Array.from(document.querySelectorAll('form[action*=\"/notifications/\"][action$=\"/read\"]')).find((form) => !form.action.endsWith('/clear/read'))?.submit();"
            );

            $browser->pause(1000);

            // Verificar en base de datos
            $notification->refresh();
            $this->assertNotNull($notification->read_at);
        });
    }

    /**
     * Test que el usuario puede marcar todas como leídas
     */
    public function test_user_can_mark_all_as_read()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/notifications');

            $browser->script(
                "document.querySelector('form[action$=\"/notifications/read-all\"]')?.submit();"
            );

            $browser->pause(1000);

            // Verificar en base de datos
            $this->assertEquals(0, $this->user->unreadNotifications()->count());
        });
    }

    /**
     * Test que el usuario puede eliminar una notificación
     */
    public function test_user_can_delete_notification()
    {
        $notification = $this->user->notifications()->first();

        $this->browse(function (Browser $browser) use ($notification) {
            $browser->loginAs($this->user)
                ->visit('/notifications');

            $browser->script(
                "document.querySelector('form[action$=\"/notifications/{$notification->id}\"]')?.submit();"
            );

            $browser->waitForText('Notificación eliminada');

            // Verificar en base de datos
            $this->assertNull(DatabaseNotification::find($notification->id));
        });
    }

    /**
     * Test que la pantalla resume el total y sin leer
     */
    public function test_notifications_summary_is_visible()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/notifications')
                ->assertPresent('div[class*="bg-white"]')
                ->assertPresent('form[action$="/notifications/read-all"]')
                ->assertPresent('form[action$="/notifications/clear/read"]');
        });
    }

    /**
     * Test que las notificaciones se cargan con paginación
     */
    public function test_notifications_pagination()
    {
        // Crear 25 notificaciones adicionales
        for ($i = 0; $i < 25; $i++) {
            $this->user->notifications()->create([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'App\Notifications\DocumentDueSoon',
                'data' => [
                    'type' => 'document_due_soon',
                    'title' => 'Notificación '.$i,
                    'message' => 'Mensaje de prueba '.$i,
                ],
            ]);
        }

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/notifications')
                ->assertPresent('a[href*="page=2"]');

            $browser->visit('/notifications?page=2')
                ->assertPresent('a[href*="page=1"]');
        });
    }

    /**
     * Test que el dropdown de notificaciones funciona
     */
    public function test_notification_dropdown_works()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/portal')
                ->waitForText('Resumen personal de documentos')
                ->click('nav .relative > button')
                ->waitFor('.am-dropdown-panel')
                ->assertPresent('.am-dropdown-panel a[href$="/notifications"]')
                ->assertPresent('.am-dropdown-panel form[action$="/notifications/read-all"]');
        });
    }

    /**
     * Test que se pueden limpiar notificaciones leídas
     */
    public function test_user_can_clear_read_notifications()
    {
        // Marcar todas como leídas primero
        $this->user->unreadNotifications->markAsRead();

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/notifications');

            $browser->script(
                "document.querySelector('form[action$=\"/notifications/clear/read\"]')?.submit();"
            );

            $browser->waitForText('Notificaciones leídas eliminadas');

            // Verificar que solo quedan las no leídas
            $this->assertEquals(0, $this->user->notifications()->whereNotNull('read_at')->count());
        });
    }
}
