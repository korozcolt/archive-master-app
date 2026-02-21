<?php

use App\Enums\Role as AppRole;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyAiSetting;
use App\Models\Department;
use App\Models\Document;
use App\Models\DocumentAiOutput;
use App\Models\DocumentAiRun;
use App\Models\DocumentVersion;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeContextByRole(AppRole $roleEnum = AppRole::OfficeManager): array
{
    $company = Company::factory()->create();
    $branch = Branch::factory()->create(['company_id' => $company->id]);
    $department = Department::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
    ]);
    $category = Category::factory()->create(['company_id' => $company->id]);
    $status = Status::factory()->create(['company_id' => $company->id]);

    $user = User::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
    ]);

    $role = Role::firstOrCreate([
        'name' => $roleEnum->value,
        'guard_name' => 'web',
    ]);
    $user->assignRole($role);

    $document = Document::factory()->create([
        'company_id' => $company->id,
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'status_id' => $status->id,
        'created_by' => $user->id,
        'assigned_to' => $user->id,
        'title' => 'Documento IA Portal',
        'description' => 'Pruebas de acciones IA en portal',
        'content' => 'Contenido de documento para IA.',
    ]);

    /** @var DocumentVersion $version */
    $version = $document->versions()->latest('version_number')->firstOrFail();
    $version->update([
        'created_by' => $user->id,
        'content' => 'Contenido de versión para resumen IA.',
        'metadata' => ['page_count' => 2],
    ]);

    return compact('company', 'department', 'category', 'status', 'user', 'document', 'version');
}

it('queues ai regenerate action from portal document view', function () {
    $ctx = makeContextByRole();
    CompanyAiSetting::factory()->create([
        'company_id' => $ctx['company']->id,
        'provider' => 'openai',
        'api_key_encrypted' => 'sk-openai-portal',
        'is_enabled' => true,
        'daily_doc_limit' => 100,
        'max_pages_per_doc' => 100,
    ]);

    $this->actingAs($ctx['user']);
    $beforeRuns = DocumentAiRun::query()->count();

    $response = $this->post(route('documents.ai.regenerate', $ctx['document']));
    $response->assertRedirect(route('documents.show', $ctx['document']));
    expect(DocumentAiRun::query()->count())->toBeGreaterThan($beforeRuns);

    $this->assertDatabaseHas('document_ai_runs', [
        'company_id' => $ctx['company']->id,
        'document_id' => $ctx['document']->id,
        'document_version_id' => $ctx['version']->id,
        'task' => 'summarize',
    ]);
});

it('applies ai suggestions to category and tags from portal', function () {
    $ctx = makeContextByRole();
    $suggestedCategory = Category::factory()->create(['company_id' => $ctx['company']->id]);

    $run = DocumentAiRun::factory()->create([
        'company_id' => $ctx['company']->id,
        'document_id' => $ctx['document']->id,
        'document_version_id' => $ctx['version']->id,
        'triggered_by' => $ctx['user']->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'success',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'portal-apply'),
        'prompt_version' => 'v1.0.0',
    ]);

    DocumentAiOutput::factory()->create([
        'document_ai_run_id' => $run->id,
        'summary_md' => 'Resumen IA',
        'suggested_tags' => ['archivo', 'urgente'],
        'suggested_category_id' => $suggestedCategory->id,
    ]);

    $this->actingAs($ctx['user']);

    $response = $this->post(route('documents.ai.apply', $ctx['document']));
    $response->assertRedirect(route('documents.show', $ctx['document']));

    $ctx['document']->refresh();
    expect((int) $ctx['document']->category_id)->toBe((int) $suggestedCategory->id);

    $tagSlugs = Tag::query()
        ->where('company_id', $ctx['company']->id)
        ->whereIn('slug', ['archivo', 'urgente'])
        ->pluck('slug')
        ->all();

    expect($tagSlugs)->toContain('archivo');
    expect($tagSlugs)->toContain('urgente');
});

it('stores incorrect feedback for latest ai summary', function () {
    $ctx = makeContextByRole();

    $run = DocumentAiRun::factory()->create([
        'company_id' => $ctx['company']->id,
        'document_id' => $ctx['document']->id,
        'document_version_id' => $ctx['version']->id,
        'triggered_by' => $ctx['user']->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'success',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'portal-feedback'),
        'prompt_version' => 'v1.0.0',
    ]);

    $output = DocumentAiOutput::factory()->create([
        'document_ai_run_id' => $run->id,
        'summary_md' => 'Resumen IA a reportar',
        'confidence' => ['classification' => 0.82],
    ]);

    $this->actingAs($ctx['user']);

    $response = $this->post(route('documents.ai.mark-incorrect', $ctx['document']), [
        'feedback_note' => 'Clasificación no corresponde al contenido.',
    ]);
    $response->assertRedirect(route('documents.show', $ctx['document']));

    $output->refresh();
    expect($output->confidence)->toHaveKey('feedback');
    expect($output->confidence['feedback'])->toHaveCount(1);
    expect($output->confidence['feedback'][0]['type'])->toBe('incorrect');
    expect((int) $output->confidence['feedback'][0]['user_id'])->toBe((int) $ctx['user']->id);
});

it('shows entities block for office manager and hides it for regular user', function () {
    $officeContext = makeContextByRole(AppRole::OfficeManager);
    $run = DocumentAiRun::factory()->create([
        'company_id' => $officeContext['company']->id,
        'document_id' => $officeContext['document']->id,
        'document_version_id' => $officeContext['version']->id,
        'triggered_by' => $officeContext['user']->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'success',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'entities-office'),
        'prompt_version' => 'v1.0.0',
    ]);
    DocumentAiOutput::factory()->create([
        'document_ai_run_id' => $run->id,
        'entities' => ['emails' => ['test@example.com']],
        'confidence' => ['classification' => 0.71],
    ]);

    $this->actingAs($officeContext['user'])
        ->get(route('documents.show', $officeContext['document']))
        ->assertOk()
        ->assertSee('Entidades detectadas y confianza');

    $regularContext = makeContextByRole(AppRole::RegularUser);
    $regularRun = DocumentAiRun::factory()->create([
        'company_id' => $regularContext['company']->id,
        'document_id' => $regularContext['document']->id,
        'document_version_id' => $regularContext['version']->id,
        'triggered_by' => $regularContext['user']->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'success',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'entities-regular'),
        'prompt_version' => 'v1.0.0',
    ]);
    DocumentAiOutput::factory()->create([
        'document_ai_run_id' => $regularRun->id,
        'entities' => ['emails' => ['regular@example.com']],
        'confidence' => ['classification' => 0.7],
    ]);

    $this->actingAs($regularContext['user'])
        ->get(route('documents.show', $regularContext['document']))
        ->assertOk()
        ->assertDontSee('Entidades detectadas y confianza');
});

it('throttles ai actions per user after configured limit', function () {
    $ctx = makeContextByRole();

    $run = DocumentAiRun::factory()->create([
        'company_id' => $ctx['company']->id,
        'document_id' => $ctx['document']->id,
        'document_version_id' => $ctx['version']->id,
        'triggered_by' => $ctx['user']->id,
        'provider' => 'openai',
        'model' => 'gpt-4.1-mini',
        'status' => 'success',
        'task' => 'summarize',
        'input_hash' => hash('sha256', 'throttle-test'),
        'prompt_version' => 'v1.0.0',
    ]);

    DocumentAiOutput::factory()->create([
        'document_ai_run_id' => $run->id,
        'summary_md' => 'Resumen base para throttle',
    ]);

    $this->actingAs($ctx['user']);

    $limiterKey = 'ai-actions:'.$ctx['company']->id.':'.$ctx['user']->id;
    RateLimiter::clear($limiterKey);

    for ($i = 0; $i < 30; $i++) {
        $this->post(route('documents.ai.mark-incorrect', $ctx['document']), [
            'feedback_note' => 'throttle-note-'.$i,
        ])->assertRedirect(route('documents.show', $ctx['document']));
    }

    $this->post(route('documents.ai.mark-incorrect', $ctx['document']), [
        'feedback_note' => 'throttle-hit',
    ])->assertStatus(429);
});
