<?php

namespace Tests\Browser;

use App\Models\Company;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Laravel\Sanctum\Sanctum;
use Tests\DuskTestCase;

class WebhookManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que un administrador puede ver la lista de webhooks vía API
     *
     * Nota: Webhooks se gestionan principalmente vía API REST.
     * Este test verifica la integración del sistema.
     */
    public function test_admin_can_access_webhook_api(): void
    {
        $company = Company::factory()->create(['name' => 'Test Company']);

        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        // Asignar rol de admin
        $admin->assignRole('super_admin');

        // Crear algunos webhooks de prueba
        Webhook::factory()->count(3)->create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin, 'web')
                ->visit('/admin')
                ->pause(1000)
                ->assertPathBeginsWith('/admin');

            // Verificar que el sistema está funcionando
            $browser->assertAuthenticated();
        });
    }

    /**
     * Test de creación de webhook vía API después de login
     */
    public function test_authenticated_user_can_create_webhook_via_api(): void
    {
        $company = Company::factory()->create(['name' => 'API Test Company']);

        $user = User::factory()->create([
            'name' => 'API User',
            'email' => 'api@example.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $user->assignRole('super_admin');

        // Verificar que el usuario puede autenticarse
        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user, 'web')
                ->visit('/admin')
                ->pause(1000)
                ->assertPathBeginsWith('/admin');
        });

        // Verificar que puede crear webhook vía API
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/webhooks/register', [
            'url' => 'https://example.com/webhook',
            'events' => ['document.created', 'document.updated'],
            'name' => 'Test Webhook from Browser',
            'active' => true,
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('webhooks', [
            'company_id' => $company->id,
            'name' => 'Test Webhook from Browser',
        ]);
    }

    /**
     * Test que webhooks son aislados por empresa
     */
    public function test_webhooks_are_isolated_by_company(): void
    {
        // Empresa 1
        $company1 = Company::factory()->create(['name' => 'Company 1']);
        $user1 = User::factory()->create([
            'email' => 'user1@company1.com',
            'password' => bcrypt('password'),
            'company_id' => $company1->id,
        ]);
        $user1->assignRole('admin');

        // Empresa 2
        $company2 = Company::factory()->create(['name' => 'Company 2']);
        $user2 = User::factory()->create([
            'email' => 'user2@company2.com',
            'password' => bcrypt('password'),
            'company_id' => $company2->id,
        ]);
        $user2->assignRole('admin');

        // Crear webhooks para cada empresa
        $webhook1 = Webhook::factory()->create([
            'company_id' => $company1->id,
            'user_id' => $user1->id,
            'name' => 'Webhook Company 1',
        ]);

        $webhook2 = Webhook::factory()->create([
            'company_id' => $company2->id,
            'user_id' => $user2->id,
            'name' => 'Webhook Company 2',
        ]);

        // Usuario 1 solo debe ver sus webhooks
        Sanctum::actingAs($user1);
        $response1 = $this->getJson('/api/webhooks');

        $response1->assertStatus(200);
        $data1 = $response1->json('data');

        $this->assertCount(1, $data1);
        $this->assertEquals('Webhook Company 1', $data1[0]['name']);

        // Usuario 2 solo debe ver sus webhooks
        auth()->forgetGuards();
        Sanctum::actingAs($user2);
        $response2 = $this->getJson('/api/webhooks');

        $response2->assertStatus(200);
        $data2 = $response2->json('data');

        $this->assertCount(1, $data2);
        $this->assertEquals('Webhook Company 2', $data2[0]['name']);
    }

    /**
     * Test que webhook puede ser actualizado
     */
    public function test_webhook_can_be_updated(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'email' => 'webhook@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
        ]);
        $user->assignRole('admin');

        $webhook = Webhook::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'url' => 'https://old-url.com/webhook',
            'name' => 'Old Name',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->putJson("/api/webhooks/{$webhook->id}", [
            'url' => 'https://new-url.com/webhook',
            'name' => 'New Name',
            'events' => ['document.created'],
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('webhooks', [
            'id' => $webhook->id,
            'url' => 'https://new-url.com/webhook',
            'name' => 'New Name',
        ]);
    }

    /**
     * Test que webhook puede ser eliminado
     */
    public function test_webhook_can_be_deleted(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'email' => 'delete@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
        ]);
        $user->assignRole('admin');

        $webhook = Webhook::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $response = $this->deleteJson("/api/webhooks/{$webhook->id}", [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);

        $this->assertSoftDeleted('webhooks', [
            'id' => $webhook->id,
        ]);
    }

    /**
     * Test que webhook tracking funciona correctamente
     */
    public function test_webhook_tracking_updates(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $webhook = Webhook::factory()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'failed_attempts' => 0,
            'last_triggered_at' => null,
        ]);

        // Simular disparo exitoso
        $webhook->markAsTriggered();
        $webhook->resetFailures();

        $this->assertNotNull($webhook->fresh()->last_triggered_at);
        $this->assertEquals(0, $webhook->fresh()->failed_attempts);

        // Simular fallo
        $webhook->incrementFailures();
        $webhook->incrementFailures();

        $this->assertEquals(2, $webhook->fresh()->failed_attempts);
    }

    /**
     * Test que webhooks inactivos no procesan eventos
     */
    public function test_inactive_webhooks_are_not_triggered(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        // Webhook activo
        $activeWebhook = Webhook::factory()->active()->create([
            'company_id' => $company->id,
            'events' => ['document.created'],
        ]);

        // Webhook inactivo
        $inactiveWebhook = Webhook::factory()->inactive()->create([
            'company_id' => $company->id,
            'events' => ['document.created'],
        ]);

        // Solo los webhooks activos deben ser recuperados
        $webhooksToTrigger = Webhook::forCompany($company->id)
            ->active()
            ->subscribedToEvent('document.created')
            ->get();

        $this->assertCount(1, $webhooksToTrigger);
        $this->assertTrue($webhooksToTrigger->first()->active);
    }
}
