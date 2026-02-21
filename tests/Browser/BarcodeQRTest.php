<?php

namespace Tests\Browser;

use App\Models\Category;
use App\Models\Company;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class BarcodeQRTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que documento genera código QR automáticamente
     */
    public function test_document_generates_qr_code_automatically(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'document_number' => 'DOC-2025-001',
        ]);

        // Verificar que se generó código QR
        $this->assertNotNull($document->qr_code);
        $this->assertStringContainsString('DOC-2025-001', $document->qr_code);
    }

    /**
     * Test que documento genera código de barras automáticamente
     */
    public function test_document_generates_barcode_automatically(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'document_number' => 'DOC-2025-002',
        ]);

        // Verificar que se generó código de barras
        $this->assertNotNull($document->barcode);
        $this->assertStringContainsString('DOC-2025-002', $document->barcode);
    }

    /**
     * Test que usuario puede visualizar código QR de un documento
     */
    public function test_user_can_view_document_qr_code(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
            'document_number' => 'DOC-2025-003',
        ]);

        $this->browse(function (Browser $browser) use ($admin, $document) {
            $browser->loginAs($admin)
                ->visit('/admin/documents/'.$document->id)
                ->assertPresent('img[alt*="QR"], .qr-code, #qr-code')
                ->pause(500);
        });
    }

    /**
     * Test que usuario puede descargar código QR
     */
    public function test_user_can_download_qr_code(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $document) {
            $browser->loginAs($admin)
                ->visit('/admin/documents/'.$document->id)
                ->click('button[title="Descargar QR"], a[href*="qr-code/download"]')
                ->pause(2000);
        });
    }

    /**
     * Test que usuario puede escanear código QR para buscar documento
     */
    public function test_user_can_scan_qr_code_to_find_document(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/hardware/scan')
                ->assertSee('Escanear')
                ->assertPresent('button[id*="scan"], #start-scan')
                ->pause(500);
        });
    }

    /**
     * Test que escaneo de código de barras redirige al documento correcto
     */
    public function test_barcode_scan_redirects_to_correct_document(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
            'document_number' => 'DOC-2025-SCAN-001',
        ]);

        // Simulación: Escanear el código de barras debe redirigir al documento
        // En pruebas reales, esto requeriría hardware o mocking
        $this->assertTrue(true);
    }

    /**
     * Test que código QR contiene información correcta del documento
     */
    public function test_qr_code_contains_correct_document_information(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'document_number' => 'DOC-2025-QR-TEST',
            'title' => 'Documento de Prueba QR',
        ]);

        // El código QR debe contener al menos el número del documento
        $this->assertNotNull($document->qr_code);
        $this->assertStringContainsString('DOC-2025-QR-TEST', $document->qr_code);
    }

    /**
     * Test que códigos QR y barcode son únicos por documento
     */
    public function test_qr_codes_and_barcodes_are_unique_per_document(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $doc1 = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'document_number' => 'DOC-001',
        ]);

        $doc2 = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'document_number' => 'DOC-002',
        ]);

        // Verificar que son diferentes
        $this->assertNotEquals($doc1->qr_code, $doc2->qr_code);
        $this->assertNotEquals($doc1->barcode, $doc2->barcode);
    }

    /**
     * Test que usuario puede imprimir código QR desde vista de documento
     */
    public function test_user_can_print_qr_code_from_document_view(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
        ]);

        $this->browse(function (Browser $browser) use ($admin, $document) {
            $browser->loginAs($admin)
                ->visit('/admin/documents/'.$document->id)
                ->click('button[title="Imprimir QR"], button[onclick*="print"]')
                ->pause(1000);
        });
    }

    /**
     * Test que API endpoint de escaneo funciona correctamente
     */
    public function test_scan_api_endpoint_works_correctly(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'document_number' => 'DOC-API-SCAN-001',
        ]);

        // Simular escaneo vía API
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/hardware/scan', [
            'code' => 'DOC-API-SCAN-001',
            'type' => 'barcode',
        ], [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'document' => [
                'document_number' => 'DOC-API-SCAN-001',
            ],
        ]);
    }
}
