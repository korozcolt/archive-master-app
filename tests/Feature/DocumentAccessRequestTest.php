<?php

use App\Enums\Role as RoleEnum;
use App\Models\Category;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentAccessRequest;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeAccessRequestCompany(): Company
{
    return Company::factory()->create();
}

function makeAccessRequestDocument(Company $company, User $creator): Document
{
    $category = Category::factory()->create(['company_id' => $company->id]);
    $status = Status::factory()->create(['company_id' => $company->id]);

    return Document::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
        'status_id' => $status->id,
        'created_by' => $creator->id,
        'assigned_to' => $creator->id,
        'file_path' => 'documents/test-file.pdf',
    ]);
}

function assignAccessRequestRole(User $user, string $roleName): void
{
    $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
    $user->assignRole($role);
}

it('shows the restricted view and blocks download for a user without implicit access', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/test-file.pdf', 'contenido');

    $company = makeAccessRequestCompany();
    $creator = User::factory()->create(['company_id' => $company->id]);
    $document = makeAccessRequestDocument($company, $creator);

    $regularUser = User::factory()->create(['company_id' => $company->id]);
    assignAccessRequestRole($regularUser, RoleEnum::RegularUser->value);

    $response = $this->actingAs($regularUser)->get(route('documents.show', $document));
    $response->assertOk();
    $response->assertViewIs('documents.show-restricted');
    $response->assertSee($document->title);

    $this->actingAs($regularUser)
        ->get(route('documents.download', $document))
        ->assertForbidden();
});

it('lets a user request access and blocks a duplicate pending request', function () {
    $company = makeAccessRequestCompany();
    $creator = User::factory()->create(['company_id' => $company->id]);
    $document = makeAccessRequestDocument($company, $creator);

    $regularUser = User::factory()->create(['company_id' => $company->id]);
    assignAccessRequestRole($regularUser, RoleEnum::RegularUser->value);

    $this->actingAs($regularUser)
        ->post(route('documents.access-requests.store', $document), ['reason' => 'Necesito revisar el expediente'])
        ->assertRedirect(route('documents.show', $document));

    $this->assertDatabaseHas('document_access_requests', [
        'document_id' => $document->id,
        'requested_by' => $regularUser->id,
        'status' => 'pending',
    ]);

    $this->actingAs($regularUser)
        ->post(route('documents.access-requests.store', $document), ['reason' => 'Otra vez'])
        ->assertSessionHasErrors();

    expect(DocumentAccessRequest::where('document_id', $document->id)->count())->toBe(1);
});

it('lets an eligible approver approve a request with the configured expiration hours', function () {
    config(['documents.access_requests.default_hours' => 24]);

    $company = makeAccessRequestCompany();
    $creator = User::factory()->create(['company_id' => $company->id]);
    $document = makeAccessRequestDocument($company, $creator);

    $regularUser = User::factory()->create(['company_id' => $company->id]);
    assignAccessRequestRole($regularUser, RoleEnum::RegularUser->value);

    $archiveManager = User::factory()->create(['company_id' => $company->id]);
    assignAccessRequestRole($archiveManager, RoleEnum::ArchiveManager->value);

    $accessRequest = DocumentAccessRequest::create([
        'document_id' => $document->id,
        'company_id' => $company->id,
        'requested_by' => $regularUser->id,
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $this->actingAs($archiveManager)
        ->post(route('access-requests.approve', $accessRequest))
        ->assertRedirect(route('access-requests.index'));

    $accessRequest->refresh();

    expect($accessRequest->status)->toBe('approved');
    expect($accessRequest->expires_at)->not->toBeNull();
    $hoursUntilExpiration = now()->diffInHours($accessRequest->expires_at, absolute: true);
    expect($hoursUntilExpiration)->toBeLessThanOrEqual(24)
        ->and($hoursUntilExpiration)->toBeGreaterThan(23);
});

it('lets the requester view and download the document once the request is approved', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/test-file.pdf', 'contenido');

    $company = makeAccessRequestCompany();
    $creator = User::factory()->create(['company_id' => $company->id]);
    $document = makeAccessRequestDocument($company, $creator);

    $regularUser = User::factory()->create(['company_id' => $company->id]);
    assignAccessRequestRole($regularUser, RoleEnum::RegularUser->value);

    DocumentAccessRequest::create([
        'document_id' => $document->id,
        'company_id' => $company->id,
        'requested_by' => $regularUser->id,
        'status' => 'approved',
        'requested_at' => now(),
        'responded_at' => now(),
        'expires_at' => now()->addHours(24),
    ]);

    $response = $this->actingAs($regularUser)->get(route('documents.show', $document));
    $response->assertOk();
    $response->assertViewIs('documents.show');

    $this->actingAs($regularUser)
        ->get(route('documents.download', $document))
        ->assertOk();
});

it('blocks access again once the granted access has expired', function () {
    Storage::fake('local');
    Storage::disk('local')->put('documents/test-file.pdf', 'contenido');

    $company = makeAccessRequestCompany();
    $creator = User::factory()->create(['company_id' => $company->id]);
    $document = makeAccessRequestDocument($company, $creator);

    $regularUser = User::factory()->create(['company_id' => $company->id]);
    assignAccessRequestRole($regularUser, RoleEnum::RegularUser->value);

    DocumentAccessRequest::create([
        'document_id' => $document->id,
        'company_id' => $company->id,
        'requested_by' => $regularUser->id,
        'status' => 'approved',
        'requested_at' => now()->subDays(2),
        'responded_at' => now()->subDays(2),
        'expires_at' => now()->subHour(),
    ]);

    $response = $this->actingAs($regularUser)->get(route('documents.show', $document));
    $response->assertOk();
    $response->assertViewIs('documents.show-restricted');

    $this->actingAs($regularUser)
        ->get(route('documents.download', $document))
        ->assertForbidden();

    $this->artisan('access-requests:expire')->assertSuccessful();

    expect(DocumentAccessRequest::where('document_id', $document->id)->first()->status)->toBe('expired');
});

it('never shows the restricted view to a user with implicit access', function () {
    $company = makeAccessRequestCompany();
    $creator = User::factory()->create(['company_id' => $company->id]);
    $document = makeAccessRequestDocument($company, $creator);

    $response = $this->actingAs($creator)->get(route('documents.show', $document));
    $response->assertOk();
    $response->assertViewIs('documents.show');
});

it('returns a plain forbidden response for users of a different company', function () {
    $company = makeAccessRequestCompany();
    $creator = User::factory()->create(['company_id' => $company->id]);
    $document = makeAccessRequestDocument($company, $creator);

    $otherCompany = Company::factory()->create();
    $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
    assignAccessRequestRole($otherUser, RoleEnum::RegularUser->value);

    $this->actingAs($otherUser)
        ->get(route('documents.show', $document))
        ->assertForbidden();
});

it('only allows an eligible approver or the document creator to approve or reject', function () {
    $company = makeAccessRequestCompany();
    $creator = User::factory()->create(['company_id' => $company->id]);
    $document = makeAccessRequestDocument($company, $creator);

    $regularUser = User::factory()->create(['company_id' => $company->id]);
    assignAccessRequestRole($regularUser, RoleEnum::RegularUser->value);

    $otherRegularUser = User::factory()->create(['company_id' => $company->id]);
    assignAccessRequestRole($otherRegularUser, RoleEnum::RegularUser->value);

    $accessRequest = DocumentAccessRequest::create([
        'document_id' => $document->id,
        'company_id' => $company->id,
        'requested_by' => $regularUser->id,
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $this->actingAs($otherRegularUser)
        ->post(route('access-requests.approve', $accessRequest))
        ->assertForbidden();

    $accessRequest->refresh();
    expect($accessRequest->status)->toBe('pending');

    $this->actingAs($creator)
        ->post(route('access-requests.approve', $accessRequest))
        ->assertRedirect(route('access-requests.index'));
});
