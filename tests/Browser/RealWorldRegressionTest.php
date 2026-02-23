<?php

namespace Tests\Browser;

use App\Models\Receipt;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class RealWorldRegressionTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('app:setup-qa-regression-data');
    }

    public function test_admin_and_portal_role_routing_rules_hold(): void
    {
        $adminRoles = [
            'qa.superadmin@archivemaster.test',
            'qa.admin@archivemaster.test',
            'qa.branch@archivemaster.test',
        ];

        $portalRoles = [
            'qa.office@archivemaster.test',
            'qa.archive@archivemaster.test',
            'qa.reception@archivemaster.test',
            'qa.user@archivemaster.test',
        ];

        foreach ($adminRoles as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->browse(function (Browser $browser) use ($user) {
                $browser->loginAs($user)
                    ->visit('/admin')
                    ->assertPathBeginsWith('/admin');
            });
        }

        foreach ($portalRoles as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->browse(function (Browser $browser) use ($user) {
                $browser->loginAs($user)
                    ->visit('/admin');

                $currentPath = parse_url($browser->driver->getCurrentURL(), PHP_URL_PATH);

                if ($currentPath === '/portal') {
                    $browser->assertPathIs('/portal');
                } else {
                    $browser->assertSee('PROHIBIDO');
                }

                $browser->visit('/portal')
                    ->assertPathIs('/portal');
            });
        }
    }

    public function test_approval_flow_is_visible_for_operational_user(): void
    {
        $officeManager = User::query()->where('email', 'qa.office@archivemaster.test')->firstOrFail();

        $this->browse(function (Browser $browser) use ($officeManager) {
            $browser->loginAs($officeManager)
                ->visit('/approvals')
                ->assertSee('Aprobaciones Pendientes')
                ->assertSee('QA-APR-0001')
                ->visit('/approvals/document/'.\App\Models\Document::query()->where('document_number', 'QA-APR-0001')->firstOrFail()->id)
                ->assertSee('QA-APR-0001');
        });
    }

    public function test_regular_user_can_view_receipt_from_seeded_dataset(): void
    {
        $regularUser = User::query()->where('email', 'qa.user@archivemaster.test')->firstOrFail();
        $receipt = Receipt::query()->where('receipt_number', 'REC-QA-0001')->firstOrFail();

        $this->browse(function (Browser $browser) use ($regularUser, $receipt) {
            $browser->loginAs($regularUser)
                ->visit('/receipts/'.$receipt->id)
                ->assertSee($receipt->receipt_number)
                ->assertSee($regularUser->email);
        });
    }
}
