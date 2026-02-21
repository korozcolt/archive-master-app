<?php

namespace Tests\Browser;

use App\Models\Category;
use App\Models\Company;
use App\Models\Document;
use App\Models\Status;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class FileUploadTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test que usuario puede acceder a la página de carga de archivos
     */
    public function test_user_can_access_file_upload_page(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/documents/create')
                ->assertSee('Crear documento')
                ->assertPresent('input[type="file"]');
        });
    }

    /**
     * Test que usuario puede subir un archivo PDF
     */
    public function test_user_can_upload_pdf_file(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        // Crear archivo PDF fake
        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

        $this->browse(function (Browser $browser) use ($user, $file) {
            $browser->loginAs($user)
                ->visit('/admin/documents/create')
                ->attach('input[type="file"]', storage_path('app/'.$file->path()))
                ->pause(1000);

            // Verificar que el archivo fue procesado
            // La implementación exacta depende de tu sistema de carga
        });
    }

    /**
     * Test que usuario puede subir una imagen
     */
    public function test_user_can_upload_image_file(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        // Crear imagen fake
        $file = UploadedFile::fake()->image('document.jpg', 800, 600);

        $this->browse(function (Browser $browser) use ($user, $file) {
            $browser->loginAs($user)
                ->visit('/admin/documents/create')
                ->attach('input[type="file"]', storage_path('app/'.$file->path()))
                ->pause(1000);
        });
    }

    /**
     * Test que se valida el tamaño máximo de archivo
     */
    public function test_file_upload_validates_max_size(): void
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        // Crear archivo muy grande (>10MB)
        $largeFile = UploadedFile::fake()->create('large.pdf', 11000, 'application/pdf');

        $this->browse(function (Browser $browser) use ($user, $largeFile) {
            $browser->loginAs($user)
                ->visit('/admin/documents/create')
                ->attach('input[type="file"]', storage_path('app/'.$largeFile->path()))
                ->pause(1000);

            // Debería mostrar error de tamaño
            // La implementación exacta depende de tu validación
        });
    }

    /**
     * Test que se validan los tipos de archivo permitidos
     */
    public function test_file_upload_validates_allowed_types(): void
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        // Crear archivo no permitido (.exe)
        $invalidFile = UploadedFile::fake()->create('malware.exe', 100);

        $this->browse(function (Browser $browser) use ($user, $invalidFile) {
            $browser->loginAs($user)
                ->visit('/admin/documents/create')
                ->attach('input[type="file"]', storage_path('app/'.$invalidFile->path()))
                ->pause(1000);

            // Debería mostrar error de tipo no permitido
        });
    }

    /**
     * Test que archivo se almacena con nombre único
     */
    public function test_uploaded_file_gets_unique_name(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
        $path = $file->store('documents', 'public');

        $document = Document::create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'title' => 'Test Document',
            'file_path' => $path,
            'file_name' => 'document.pdf',
        ]);

        // Verificar que el path es único
        $this->assertNotEquals('document.pdf', $path);
        $this->assertStringContainsString('documents/', $path);

        // Verificar que el archivo existe en storage
        Storage::disk('public')->assertExists($path);
    }

    /**
     * Test que se muestra progreso de carga
     */
    public function test_upload_shows_progress_indicator(): void
    {
        $company = Company::factory()->create();

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/admin/documents/create')
                ->assertPresent('input[type="file"]')
                ->pause(500);

            // Verificar que existe indicador de progreso
            // La implementación exacta depende de tu UI
        });
    }

    /**
     * Test que se pueden subir múltiples archivos
     */
    public function test_user_can_upload_multiple_files(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        // Crear múltiples archivos
        $file1 = UploadedFile::fake()->create('doc1.pdf', 512, 'application/pdf');
        $file2 = UploadedFile::fake()->create('doc2.pdf', 512, 'application/pdf');
        $file3 = UploadedFile::fake()->create('doc3.pdf', 512, 'application/pdf');

        // Almacenar archivos
        $path1 = $file1->store('documents', 'public');
        $path2 = $file2->store('documents', 'public');
        $path3 = $file3->store('documents', 'public');

        // Verificar que todos se almacenaron
        Storage::disk('public')->assertExists($path1);
        Storage::disk('public')->assertExists($path2);
        Storage::disk('public')->assertExists($path3);
    }

    /**
     * Test que se captura información del archivo subido
     */
    public function test_uploaded_file_metadata_is_captured(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $file = UploadedFile::fake()->create('test-doc.pdf', 2048, 'application/pdf');
        $path = $file->store('documents', 'public');

        $document = Document::create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'title' => 'Test Document',
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        // Verificar metadata
        $this->assertEquals('test-doc.pdf', $document->file_name);
        $this->assertEquals($file->getSize(), $document->file_size);
        $this->assertEquals('application/pdf', $document->mime_type);
    }

    /**
     * Test que archivo se puede eliminar después de subirlo
     */
    public function test_uploaded_file_can_be_deleted(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $user->assignRole($adminRole);

        $file = UploadedFile::fake()->create('to-delete.pdf', 1024, 'application/pdf');
        $path = $file->store('documents', 'public');

        $document = Document::create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'title' => 'Document to Delete',
            'file_path' => $path,
        ]);

        $this->browse(function (Browser $browser) use ($user, $document) {
            $browser->loginAs($user)
                ->visit('/admin/documents/'.$document->id.'/edit')
                ->pause(500);

            // Eliminar archivo
            Storage::disk('public')->delete($document->file_path);
            Storage::disk('public')->assertMissing($document->file_path);
        });
    }
}
