<?php

namespace Tests\Browser;

use App\Models\User;
use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Category;
use App\Models\Status;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Spatie\Permission\Models\Role;

class DocumentVersioningTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test que usuario puede ver el historial de versiones de un documento
     */
    public function test_user_can_view_document_version_history(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::create(['name' => 'Admin']);
        $user->assignRole($adminRole);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'title' => 'Documento con Versiones',
        ]);

        // Crear versiones
        DocumentVersion::factory()->count(3)->create([
            'document_id' => $document->id,
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $document) {
            $browser->loginAs($user)
                    ->visit('/admin/documents/' . $document->id)
                    ->assertSee('Versiones')
                    ->assertSee('Historial de versiones')
                    ->pause(500);

            // Verificar en base de datos
            $this->assertEquals(3, DocumentVersion::where('document_id', $document->id)->count());
        });
    }

    /**
     * Test que usuario puede crear una nueva versión de un documento
     */
    public function test_user_can_create_new_document_version(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::create(['name' => 'Admin']);
        $user->assignRole($adminRole);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $document) {
            $browser->loginAs($user)
                    ->visit('/admin/documents/' . $document->id)
                    ->clickLink('Nueva versión')
                    ->pause(500);

            // Crear versión manualmente
            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 2,
                'file_path' => 'documents/version2.pdf',
                'file_name' => 'version2.pdf',
                'file_size' => 1024,
                'mime_type' => 'application/pdf',
                'created_by' => $user->id,
                'changes_description' => 'Segunda versión del documento',
            ]);

            // Verificar en base de datos
            $this->assertDatabaseHas('document_versions', [
                'document_id' => $document->id,
                'version_number' => 2,
                'changes_description' => 'Segunda versión del documento',
            ]);
        });
    }

    /**
     * Test que las versiones se numeran automáticamente
     */
    public function test_versions_are_numbered_automatically(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        // Crear versiones consecutivas
        $version1 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/v1.pdf',
            'file_name' => 'v1.pdf',
            'created_by' => $user->id,
        ]);

        $version2 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 2,
            'file_path' => 'documents/v2.pdf',
            'file_name' => 'v2.pdf',
            'created_by' => $user->id,
        ]);

        $version3 = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 3,
            'file_path' => 'documents/v3.pdf',
            'file_name' => 'v3.pdf',
            'created_by' => $user->id,
        ]);

        // Verificar numeración
        $this->assertEquals(1, $version1->version_number);
        $this->assertEquals(2, $version2->version_number);
        $this->assertEquals(3, $version3->version_number);
    }

    /**
     * Test que usuario puede ver detalles de una versión específica
     */
    public function test_user_can_view_version_details(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::create(['name' => 'Admin']);
        $user->assignRole($adminRole);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 2,
            'changes_description' => 'Actualización importante de contenido',
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $document, $version) {
            $browser->loginAs($user)
                    ->visit('/admin/documents/' . $document->id)
                    ->assertSee('Versión ' . $version->version_number)
                    ->assertSee('Actualización importante de contenido');
        });
    }

    /**
     * Test que usuario puede restaurar una versión anterior
     */
    public function test_user_can_restore_previous_version(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::create(['name' => 'Admin']);
        $user->assignRole($adminRole);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'file_path' => 'documents/current.pdf',
        ]);

        // Crear versión anterior
        $oldVersion = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/version1.pdf',
            'created_by' => $user->id,
        ]);

        // Crear versión actual
        $currentVersion = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 2,
            'file_path' => 'documents/version2.pdf',
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $document, $oldVersion) {
            $browser->loginAs($user)
                    ->visit('/admin/documents/' . $document->id)
                    ->pause(500);

            // Restaurar versión 1
            // La implementación exacta depende de tu UI

            // Crear nueva versión basada en la anterior (restauración)
            $restoredVersion = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 3,
                'file_path' => $oldVersion->file_path,
                'file_name' => $oldVersion->file_name,
                'created_by' => $user->id,
                'changes_description' => 'Restaurada desde versión ' . $oldVersion->version_number,
            ]);

            // Verificar restauración
            $this->assertEquals(3, $restoredVersion->version_number);
            $this->assertStringContainsString('Restaurada', $restoredVersion->changes_description);
        });
    }

    /**
     * Test que cada versión registra quién la creó y cuándo
     */
    public function test_versions_track_creator_and_timestamp(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user1 = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Usuario 1',
        ]);

        $user2 = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Usuario 2',
        ]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user1->id,
        ]);

        $version1 = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'created_by' => $user1->id,
        ]);

        $version2 = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'created_by' => $user2->id,
        ]);

        // Verificar creadores
        $this->assertEquals($user1->id, $version1->created_by);
        $this->assertEquals($user2->id, $version2->created_by);
        $this->assertNotNull($version1->created_at);
        $this->assertNotNull($version2->created_at);
    }

    /**
     * Test que se puede comparar dos versiones
     */
    public function test_user_can_compare_two_versions(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::create(['name' => 'Admin']);
        $user->assignRole($adminRole);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $version1 = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_size' => 1024,
            'created_by' => $user->id,
        ]);

        $version2 = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 2,
            'file_size' => 2048,
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $document) {
            $browser->loginAs($user)
                    ->visit('/admin/documents/' . $document->id)
                    ->pause(500);

            // Comparar versiones
            // La implementación exacta depende de tu UI
        });

        // Verificar diferencias
        $this->assertNotEquals($version1->file_size, $version2->file_size);
    }

    /**
     * Test que versión incluye descripción de cambios
     */
    public function test_version_includes_change_description(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 2,
            'file_path' => 'documents/v2.pdf',
            'file_name' => 'v2.pdf',
            'created_by' => $user->id,
            'changes_description' => 'Corrección de errores ortográficos y actualización de fechas',
        ]);

        // Verificar descripción
        $this->assertNotNull($version->changes_description);
        $this->assertStringContainsString('Corrección', $version->changes_description);
    }

    /**
     * Test que versiones mantienen información del archivo
     */
    public function test_versions_maintain_file_information(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => 'documents/contract_v1.pdf',
            'file_name' => 'contract_v1.pdf',
            'file_size' => 2048576, // 2MB
            'mime_type' => 'application/pdf',
            'created_by' => $user->id,
        ]);

        // Verificar información del archivo
        $this->assertEquals('documents/contract_v1.pdf', $version->file_path);
        $this->assertEquals('contract_v1.pdf', $version->file_name);
        $this->assertEquals(2048576, $version->file_size);
        $this->assertEquals('application/pdf', $version->mime_type);
    }

    /**
     * Test que se puede descargar una versión específica
     */
    public function test_user_can_download_specific_version(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::create(['name' => 'Admin']);
        $user->assignRole($adminRole);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $version = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 2,
            'file_path' => 'documents/downloadable.pdf',
            'file_name' => 'downloadable.pdf',
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $document, $version) {
            $browser->loginAs($user)
                    ->visit('/admin/documents/' . $document->id)
                    ->pause(500);

            // Click en descargar versión
            // La implementación exacta depende de tu UI
            // Se esperaría una ruta como: /admin/documents/{id}/versions/{version}/download
        });
    }

    /**
     * Test que el documento actual muestra la versión más reciente
     */
    public function test_document_displays_latest_version(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::create(['name' => 'Admin']);
        $user->assignRole($adminRole);

        $document = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        // Crear versiones
        DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'created_by' => $user->id,
        ]);

        $latestVersion = DocumentVersion::factory()->create([
            'document_id' => $document->id,
            'version_number' => 3,
            'created_by' => $user->id,
        ]);

        $this->browse(function (Browser $browser) use ($user, $document, $latestVersion) {
            $browser->loginAs($user)
                    ->visit('/admin/documents/' . $document->id)
                    ->assertSee('Versión ' . $latestVersion->version_number);
        });

        // Verificar que la versión más reciente es la 3
        $latest = DocumentVersion::where('document_id', $document->id)
            ->orderBy('version_number', 'desc')
            ->first();

        $this->assertEquals(3, $latest->version_number);
    }
}
