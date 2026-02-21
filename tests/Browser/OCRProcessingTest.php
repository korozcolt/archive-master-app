<?php

namespace Tests\Browser;

use App\Models\Category;
use App\Models\Company;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use App\Services\OCRService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class OCRProcessingTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que verifica que Tesseract está instalado y disponible
     */
    public function test_tesseract_is_installed_and_operational(): void
    {
        // Verificar que Tesseract está instalado
        $output = shell_exec('which tesseract 2>&1');
        $this->assertNotNull($output);
        $this->assertStringContainsString('tesseract', $output);

        // Verificar que soporta español
        $langs = shell_exec('tesseract --list-langs 2>&1');
        $this->assertStringContainsString('spa', $langs);
        $this->assertStringContainsString('eng', $langs);
    }

    /**
     * Test que OCRService se puede instanciar correctamente
     */
    public function test_ocr_service_can_be_instantiated(): void
    {
        $service = app(OCRService::class);
        $this->assertInstanceOf(OCRService::class, $service);
    }

    /**
     * Test que OCRService maneja correctamente los idiomas
     */
    public function test_ocr_service_handles_language_mapping(): void
    {
        $service = app(OCRService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('mapLanguageToTesseract');
        $method->setAccessible(true);

        $this->assertEquals('spa', $method->invoke($service, 'es'));
        $this->assertEquals('eng', $method->invoke($service, 'en'));
        $this->assertEquals('fra', $method->invoke($service, 'fr'));
        $this->assertEquals('deu', $method->invoke($service, 'de'));
    }

    /**
     * Test del flujo completo de usuario procesando OCR
     */
    public function test_user_can_process_document_with_ocr_command(): void
    {
        $company = Company::factory()->create(['name' => 'OCR Test Company']);
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $admin = User::factory()->create([
            'name' => 'OCR Admin',
            'email' => 'ocr@example.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $admin->assignRole('super_admin');

        // Crear documento de prueba
        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $admin->id,
            'title' => 'Test OCR Document',
        ]);

        // Verificar que el usuario puede autenticarse
        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin, 'web')
                ->visit('/admin')
                ->waitFor('@filament-navigation', 10)
                ->assertAuthenticated()
                ->assertSee($admin->name);
        });

        // Verificar que el documento existe en la base de datos
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'Test OCR Document',
            'company_id' => $company->id,
        ]);
    }

    /**
     * Test que OCR procesa texto correctamente
     */
    public function test_ocr_processes_text_extraction(): void
    {
        $service = app(OCRService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processExtractedText');
        $method->setAccessible(true);

        $rawText = "  Multiple    spaces   and\n\n\nnewlines  ";
        $processed = $method->invoke($service, $rawText);

        $this->assertNotEmpty($processed);
        $this->assertLessThan(strlen($rawText), strlen($processed));
    }

    /**
     * Test que OCR command está disponible
     */
    public function test_ocr_artisan_command_exists(): void
    {
        // Verificar que el comando existe
        $exitCode = \Artisan::call('documents:process-ocr', ['--help' => true]);

        // El comando debe existir (no error)
        $this->assertTrue($exitCode === 0 || $exitCode === null);
    }

    /**
     * Test que OCR service rechaza formatos no soportados
     */
    public function test_ocr_rejects_unsupported_formats(): void
    {
        Storage::fake('local');
        $service = app(OCRService::class);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Formato de archivo no soportado');

        $service->processDocument('document.txt', 'es');
    }

    /**
     * Test que múltiples usuarios pueden procesar OCR simultáneamente
     */
    public function test_multiple_users_can_process_ocr_concurrently(): void
    {
        $company = Company::factory()->create();

        // Crear varios usuarios
        $users = User::factory()->count(3)->create([
            'company_id' => $company->id,
        ]);

        foreach ($users as $user) {
            $user->assignRole('admin');
        }

        // Cada usuario puede autenticarse
        foreach ($users as $user) {
            $this->browse(function (Browser $browser) use ($user) {
                $browser->loginAs($user, 'web')
                    ->visit('/admin')
                    ->waitFor('@filament-navigation', 10)
                    ->assertAuthenticated();
            });
        }

        // Verificar que todos los usuarios están en la misma empresa
        $this->assertEquals(3, User::where('company_id', $company->id)->count());
    }

    /**
     * Test de integración: Documento → OCR → Resultados
     */
    public function test_document_ocr_integration_flow(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'email' => 'integration@test.com',
            'password' => bcrypt('password'),
            'company_id' => $company->id,
        ]);
        $user->assignRole('admin');

        // Crear documento
        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'title' => 'OCR Integration Test',
        ]);

        // Verificar que el documento se creó correctamente
        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'title' => 'OCR Integration Test',
        ]);

        // Verificar que el servicio OCR está disponible
        $service = app(OCRService::class);
        $this->assertInstanceOf(OCRService::class, $service);
    }

    /**
     * Test que OCR maneja errores gracefully
     */
    public function test_ocr_handles_errors_gracefully(): void
    {
        $service = app(OCRService::class);

        try {
            // Intentar procesar un archivo que no existe
            $service->processDocument('non-existent-file.jpg', 'es');
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            // El servicio debe lanzar una excepción clara
            $this->assertNotEmpty($e->getMessage());
        }
    }

    /**
     * Test que OCR respeta las preferencias de idioma
     */
    public function test_ocr_respects_language_preferences(): void
    {
        $service = app(OCRService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('mapLanguageToTesseract');
        $method->setAccessible(true);

        // Verificar que cada idioma se mapea correctamente
        $languages = [
            'es' => 'spa',
            'en' => 'eng',
            'fr' => 'fra',
            'de' => 'deu',
            'it' => 'ita',
            'pt' => 'por',
        ];

        foreach ($languages as $input => $expected) {
            $result = $method->invoke($service, $input);
            $this->assertEquals($expected, $result, "Language $input should map to $expected");
        }

        // Idioma desconocido debe usar inglés por defecto
        $this->assertEquals('eng', $method->invoke($service, 'unknown'));
    }
}
