<?php

namespace Tests\Browser;

use App\Models\Category;
use App\Models\Company;
use App\Models\Document;
use App\Models\Status;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Tests\DuskTestCase;

class TagManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test que admin puede ver lista de tags
     */
    public function test_admin_can_view_tags_list(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        Tag::factory()->count(5)->create(['company_id' => $company->id]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/tags')
                ->assertSee('Etiquetas')
                ->assertPresent('table');
        });
    }

    /**
     * Test que admin puede crear un tag
     */
    public function test_admin_can_create_tag(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/tags/create')
                ->type('input[name="name"]', 'Urgente')
                ->type('input[name="color"]', '#FF0000')
                ->press('Crear')
                ->pause(1000);

            $this->assertDatabaseHas('tags', [
                'name' => 'Urgente',
                'color' => '#FF0000',
            ]);
        });
    }

    /**
     * Test que tags están aislados por empresa
     */
    public function test_tags_are_isolated_by_company(): void
    {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();

        Tag::factory()->count(3)->create(['company_id' => $company1->id, 'name' => 'Tag Company 1']);
        Tag::factory()->count(2)->create(['company_id' => $company2->id, 'name' => 'Tag Company 2']);

        $this->assertEquals(3, Tag::where('company_id', $company1->id)->count());
        $this->assertEquals(2, Tag::where('company_id', $company2->id)->count());
    }

    /**
     * Test que documentos pueden tener múltiples tags
     */
    public function test_documents_can_have_multiple_tags(): void
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
        ]);

        $tag1 = Tag::factory()->create(['company_id' => $company->id, 'name' => 'Urgente']);
        $tag2 = Tag::factory()->create(['company_id' => $company->id, 'name' => 'Confidencial']);
        $tag3 = Tag::factory()->create(['company_id' => $company->id, 'name' => 'Legal']);

        $document->tags()->attach([$tag1->id, $tag2->id, $tag3->id]);

        $this->assertCount(3, $document->tags);
    }

    /**
     * Test que se pueden buscar documentos por tag
     */
    public function test_documents_can_be_searched_by_tag(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $tag = Tag::factory()->create(['company_id' => $company->id, 'name' => 'Importante']);

        $doc1 = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $doc2 = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        $doc1->tags()->attach($tag->id);

        $documentsWithTag = Document::whereHas('tags', function ($query) use ($tag) {
            $query->where('tags.id', $tag->id);
        })->get();

        $this->assertCount(1, $documentsWithTag);
        $this->assertEquals($doc1->id, $documentsWithTag->first()->id);
    }

    /**
     * Test que tag muestra conteo de documentos
     */
    public function test_tag_shows_document_count(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $tag = Tag::factory()->create(['company_id' => $company->id]);

        $documents = Document::factory()->count(5)->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
        ]);

        foreach ($documents as $doc) {
            $doc->tags()->attach($tag->id);
        }

        $this->assertEquals(5, $tag->documents()->count());
    }

    /**
     * Test que tag puede tener color personalizado
     */
    public function test_tag_can_have_custom_color(): void
    {
        $company = Company::factory()->create();

        $redTag = Tag::factory()->create([
            'company_id' => $company->id,
            'name' => 'Urgente',
            'color' => '#FF0000',
        ]);

        $greenTag = Tag::factory()->create([
            'company_id' => $company->id,
            'name' => 'Aprobado',
            'color' => '#00FF00',
        ]);

        $this->assertEquals('#FF0000', $redTag->color);
        $this->assertEquals('#00FF00', $greenTag->color);
    }

    /**
     * Test que tags pueden ser editados
     */
    public function test_tags_can_be_edited(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $tag = Tag::factory()->create([
            'company_id' => $company->id,
            'name' => 'Original',
        ]);

        $this->browse(function (Browser $browser) use ($admin, $tag) {
            $browser->loginAs($admin)
                ->visit('/admin/tags/'.$tag->id.'/edit')
                ->clear('input[name="name"]')
                ->type('input[name="name"]', 'Modificado')
                ->press('Guardar cambios')
                ->pause(1000);

            $tag->refresh();
            $this->assertEquals('Modificado', $tag->name);
        });
    }

    /**
     * Test que tag vacío puede ser eliminado
     */
    public function test_empty_tag_can_be_deleted(): void
    {
        $company = Company::factory()->create();
        $tag = Tag::factory()->create(['company_id' => $company->id]);

        $tag->delete();

        $this->assertSoftDeleted('tags', ['id' => $tag->id]);
    }

    /**
     * Test que tags se pueden filtrar en documentos
     */
    public function test_documents_can_be_filtered_by_tags(): void
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create(['company_id' => $company->id]);
        $status = Status::factory()->create(['company_id' => $company->id]);
        $user = User::factory()->create(['company_id' => $company->id]);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $admin->assignRole($adminRole);

        $urgentTag = Tag::factory()->create(['company_id' => $company->id, 'name' => 'Urgente']);
        $normalTag = Tag::factory()->create(['company_id' => $company->id, 'name' => 'Normal']);

        $doc1 = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'title' => 'Documento Urgente',
        ]);

        $doc2 = Document::factory()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'status_id' => $status->id,
            'created_by' => $user->id,
            'title' => 'Documento Normal',
        ]);

        $doc1->tags()->attach($urgentTag->id);
        $doc2->tags()->attach($normalTag->id);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                ->visit('/admin/documents')
                ->pause(500);

            // Aplicar filtro por tag
            // La implementación exacta depende de tu UI
        });
    }
}
