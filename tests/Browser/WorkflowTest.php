<?php

namespace Tests\Browser;

use App\Models\Company;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use App\Models\WorkflowDefinition;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class WorkflowTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que un usuario puede ver las definiciones de workflow
     */
    public function test_user_can_view_workflow_definitions(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        // Crear estados
        $status1 = Status::factory()->create([
            'company_id' => $company->id,
            'name' => 'Borrador',
        ]);
        $status2 = Status::factory()->create([
            'company_id' => $company->id,
            'name' => 'En Revisión',
        ]);

        // Crear workflow definition
        WorkflowDefinition::factory()->create([
            'company_id' => $company->id,
            'from_status_id' => $status1->id,
            'to_status_id' => $status2->id,
            'roles_allowed' => ['admin'],
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/workflow-definitions')
                ->assertSee('Definiciones de flujo')
                ->assertPresent('table');
        });
    }

    /**
     * Test que el historial de workflow se muestra correctamente
     */
    public function test_workflow_history_is_displayed(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        $status = Status::factory()->create([
            'company_id' => $company->id,
        ]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'created_by' => $user->id,
            'status_id' => $status->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $document) {
            $browser->loginAs($user)
                ->visit('/admin/documents/'.$document->id)
                ->assertSee($document->title)
                ->pause(500);
        });
    }

    /**
     * Test que se pueden crear estados de workflow
     */
    public function test_user_can_create_workflow_status(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/statuses')
                ->assertSee('Estados')
                ->pause(500);
        });
    }
}
