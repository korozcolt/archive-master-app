<?php

use App\Models\User;
use App\Models\Company;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create([
        'company_id' => $this->company->id,
    ]);
    $this->actingAs($this->user, 'sanctum');
});

describe('Webhook CRUD Operations', function () {
    test('can create webhook via API', function () {
        $response = $this->postJson('/api/webhooks/register', [
            'url' => 'https://example.com/webhook',
            'events' => ['document.created', 'document.updated'],
            'name' => 'Test Webhook',
            'secret' => 'test_secret_123',
            'active' => true,
            'retry_attempts' => 3,
            'timeout' => 30,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'url',
                    'events',
                    'name',
                    'active',
                    'test_result',
                    'created_at',
                ],
            ]);

        $this->assertDatabaseHas('webhooks', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'url' => 'https://example.com/webhook',
            'name' => 'Test Webhook',
        ]);
    });

    test('validates required fields when creating webhook', function () {
        $response = $this->postJson('/api/webhooks/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url', 'events']);
    });

    test('validates webhook URL format', function () {
        $response = $this->postJson('/api/webhooks/register', [
            'url' => 'invalid-url',
            'events' => ['document.created'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    });

    test('validates webhook events', function () {
        $response = $this->postJson('/api/webhooks/register', [
            'url' => 'https://example.com/webhook',
            'events' => ['invalid.event'],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['events.0']);
    });

    test('can list company webhooks', function () {
        Webhook::factory()->count(3)->create([
            'company_id' => $this->company->id,
        ]);

        // Create webhook for different company (shouldn't be visible)
        $otherCompany = Company::factory()->create();
        Webhook::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->getJson('/api/webhooks');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    });

    test('can update webhook', function () {
        $webhook = Webhook::factory()->create([
            'company_id' => $this->company->id,
            'url' => 'https://old-url.com/webhook',
        ]);

        $response = $this->putJson("/api/webhooks/{$webhook->id}", [
            'url' => 'https://new-url.com/webhook',
            'events' => ['document.deleted'],
            'name' => 'Updated Webhook',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('webhooks', [
            'id' => $webhook->id,
            'url' => 'https://new-url.com/webhook',
            'name' => 'Updated Webhook',
        ]);
    });

    test('cannot update webhook from another company', function () {
        $otherCompany = Company::factory()->create();
        $webhook = Webhook::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->putJson("/api/webhooks/{$webhook->id}", [
            'url' => 'https://malicious.com/webhook',
        ]);

        $response->assertStatus(404);
    });

    test('can delete webhook', function () {
        $webhook = Webhook::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $response = $this->deleteJson("/api/webhooks/{$webhook->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('webhooks', [
            'id' => $webhook->id,
        ]);
    });

    test('cannot delete webhook from another company', function () {
        $otherCompany = Company::factory()->create();
        $webhook = Webhook::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $response = $this->deleteJson("/api/webhooks/{$webhook->id}");

        $response->assertStatus(404);

        $this->assertDatabaseHas('webhooks', [
            'id' => $webhook->id,
            'deleted_at' => null,
        ]);
    });
});

describe('Webhook Model', function () {
    test('webhook belongs to company', function () {
        $webhook = Webhook::factory()->create([
            'company_id' => $this->company->id,
        ]);

        expect($webhook->company)->toBeInstanceOf(Company::class)
            ->and($webhook->company->id)->toBe($this->company->id);
    });

    test('webhook belongs to user', function () {
        $webhook = Webhook::factory()->create([
            'user_id' => $this->user->id,
        ]);

        expect($webhook->user)->toBeInstanceOf(User::class)
            ->and($webhook->user->id)->toBe($this->user->id);
    });

    test('active scope filters active webhooks', function () {
        Webhook::factory()->create([
            'company_id' => $this->company->id,
            'active' => true,
        ]);
        Webhook::factory()->create([
            'company_id' => $this->company->id,
            'active' => false,
        ]);

        $activeWebhooks = Webhook::active()->get();

        expect($activeWebhooks)->toHaveCount(1)
            ->and($activeWebhooks->first()->active)->toBeTrue();
    });

    test('forCompany scope filters by company', function () {
        Webhook::factory()->count(2)->create([
            'company_id' => $this->company->id,
        ]);

        $otherCompany = Company::factory()->create();
        Webhook::factory()->create([
            'company_id' => $otherCompany->id,
        ]);

        $companyWebhooks = Webhook::forCompany($this->company->id)->get();

        expect($companyWebhooks)->toHaveCount(2);
    });

    test('subscribedToEvent scope filters by event', function () {
        Webhook::factory()->create([
            'company_id' => $this->company->id,
            'events' => ['document.created', 'document.updated'],
        ]);
        Webhook::factory()->create([
            'company_id' => $this->company->id,
            'events' => ['user.created'],
        ]);

        $webhooks = Webhook::subscribedToEvent('document.created')->get();

        expect($webhooks)->toHaveCount(1);
    });

    test('can mark webhook as triggered', function () {
        $webhook = Webhook::factory()->create([
            'company_id' => $this->company->id,
            'last_triggered_at' => null,
        ]);

        $webhook->markAsTriggered();

        expect($webhook->fresh()->last_triggered_at)->not->toBeNull();
    });

    test('can increment failures', function () {
        $webhook = Webhook::factory()->create([
            'company_id' => $this->company->id,
            'failed_attempts' => 0,
        ]);

        $webhook->incrementFailures();

        expect($webhook->fresh()->failed_attempts)->toBe(1);
    });

    test('can reset failures', function () {
        $webhook = Webhook::factory()->create([
            'company_id' => $this->company->id,
            'failed_attempts' => 5,
        ]);

        $webhook->resetFailures();

        expect($webhook->fresh()->failed_attempts)->toBe(0);
    });

    test('hasEvent checks if webhook is subscribed to event', function () {
        $webhook = Webhook::factory()->create([
            'events' => ['document.created', 'document.updated'],
        ]);

        expect($webhook->hasEvent('document.created'))->toBeTrue()
            ->and($webhook->hasEvent('user.created'))->toBeFalse();
    });
});

describe('Webhook Casts', function () {
    test('events cast to array', function () {
        $webhook = Webhook::factory()->create([
            'events' => ['document.created', 'document.updated'],
        ]);

        expect($webhook->events)->toBeArray()
            ->and($webhook->events)->toContain('document.created');
    });

    test('active cast to boolean', function () {
        $webhook = Webhook::factory()->create(['active' => 1]);

        expect($webhook->active)->toBeTrue();
    });

    test('last_triggered_at cast to datetime', function () {
        $webhook = Webhook::factory()->create([
            'last_triggered_at' => now(),
        ]);

        expect($webhook->last_triggered_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    });
});
