<?php

namespace Tests\Feature;

use App\Enums\CmsBlockType;
use App\Enums\CmsPageStatus;
use App\Models\CmsBlock;
use App\Models\CmsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminCmsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_cms_pages_index(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->published()->create([
            'title' => 'Federarse',
            'slug' => 'federarse',
        ]);
        CmsBlock::factory()->for($page, 'page')->create([
            'type' => CmsBlockType::TEXT->value,
            'sort_order' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.cms-pages.index'));

        $response->assertOk();
        $response->assertSee('Páginas CMS');
        $response->assertSee('Federarse');
        $response->assertSee('federarse');
        $response->assertSee('Publicada');
        $response->assertSee(route('admin.cms-pages.show', $page));
        $response->assertSee(route('admin.cms-pages.edit', $page));
    }

    public function test_create_form_explains_that_new_pages_start_as_drafts(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.cms-pages.create'));

        $response->assertOk();
        $response->assertSee('La página se creará como borrador.');
        $response->assertSee('name="status" value="draft"', false);
        $response->assertDontSee('<option value="published"', false);
    }

    public function test_admin_can_create_cms_page_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.cms-pages.store'), [
                'title' => 'Academy',
                'slug' => 'academy',
                'status' => CmsPageStatus::DRAFT->value,
                'published_at' => '',
                'seo_title' => 'Academy Galotxas',
                'seo_description' => 'Información de escuela.',
            ]);

        $response->assertRedirect(route('admin.cms-pages.index'));
        $response->assertSessionHas('success', 'Página CMS creada correctamente.');

        $page = CmsPage::query()->where('slug', 'academy')->first();

        $this->assertNotNull($page);
        $this->assertSame('Academy', $page->title);
        $this->assertSame(CmsPageStatus::DRAFT, $page->status);
        $this->assertNull($page->published_at);
        $this->assertSame('Academy Galotxas', $page->seo_title);
        $this->assertSame('Información de escuela.', $page->seo_description);
    }

    public function test_manipulated_request_cannot_create_published_page_without_blocks(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.cms-pages.create'))
            ->post(route('admin.cms-pages.store'), [
                'title' => 'Publicación manipulada',
                'slug' => 'publicacion-manipulada',
                'status' => CmsPageStatus::PUBLISHED->value,
                'published_at' => '',
                'seo_title' => '',
                'seo_description' => '',
            ]);

        $response->assertRedirect(route('admin.cms-pages.create'));
        $response->assertSessionHasErrors([
            'status' => 'Las páginas nuevas deben crearse como borrador. Añade al menos un bloque antes de publicarlas.',
        ]);
        $this->assertDatabaseMissing('cms_pages', [
            'slug' => 'publicacion-manipulada',
        ]);
    }

    public function test_admin_can_edit_cms_page(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->draft()->create([
            'title' => 'Página antigua',
            'slug' => 'pagina-antigua',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.cms-pages.update', $page), [
                'title' => 'Página actualizada',
                'slug' => 'pagina-actualizada',
                'status' => CmsPageStatus::DRAFT->value,
                'published_at' => '',
                'seo_title' => 'SEO actualizado',
                'seo_description' => 'Descripción actualizada.',
            ]);

        $response->assertRedirect(route('admin.cms-pages.index'));
        $response->assertSessionHas('success', 'Página CMS actualizada correctamente.');

        $this->assertDatabaseHas('cms_pages', [
            'id' => $page->id,
            'title' => 'Página actualizada',
            'slug' => 'pagina-actualizada',
            'status' => CmsPageStatus::DRAFT->value,
            'seo_title' => 'SEO actualizado',
            'seo_description' => 'Descripción actualizada.',
        ]);
    }

    public function test_cms_page_slug_must_be_unique(): void
    {
        $admin = User::factory()->admin()->create();
        CmsPage::factory()->create([
            'slug' => 'federarse',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.cms-pages.create'))
            ->post(route('admin.cms-pages.store'), [
                'title' => 'Otra federación',
                'slug' => 'federarse',
                'status' => CmsPageStatus::DRAFT->value,
                'published_at' => '',
                'seo_title' => '',
                'seo_description' => '',
            ]);

        $response->assertRedirect(route('admin.cms-pages.create'));
        $response->assertSessionHasErrors('slug');
    }

    public function test_page_without_blocks_cannot_be_published(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->draft()->create([
            'title' => 'Página vacía',
            'slug' => 'pagina-vacia',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.cms-pages.edit', $page))
            ->put(route('admin.cms-pages.update', $page), [
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => CmsPageStatus::PUBLISHED->value,
                'published_at' => '',
                'seo_title' => '',
                'seo_description' => '',
            ]);

        $response->assertRedirect(route('admin.cms-pages.edit', $page));
        $response->assertSessionHasErrors([
            'status' => 'No se puede publicar una página sin bloques. Añade al menos un bloque válido antes de publicarla.',
        ]);
        $this->assertSame(CmsPageStatus::DRAFT, $page->fresh()->status);
    }

    public function test_page_with_a_block_can_be_published_immediately_with_null_date(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->draft()->create([
            'title' => 'Página con contenido',
            'slug' => 'pagina-con-contenido',
        ]);
        CmsBlock::factory()->for($page, 'page')->create();

        $response = $this->actingAs($admin)
            ->put(route('admin.cms-pages.update', $page), [
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => CmsPageStatus::PUBLISHED->value,
                'published_at' => '',
                'seo_title' => '',
                'seo_description' => '',
            ]);

        $response->assertRedirect(route('admin.cms-pages.index'));
        $response->assertSessionHasNoErrors();

        $page->refresh();

        $this->assertSame(CmsPageStatus::PUBLISHED, $page->status);
        $this->assertNull($page->published_at);

        $this->getJson('/api/v1/cms/pages/'.$page->slug)->assertOk();
    }

    public function test_page_with_a_block_can_be_scheduled_for_a_future_date(): void
    {
        $now = Carbon::parse('2026-08-01 12:00:00', config('app.timezone'));
        Carbon::setTestNow($now);

        try {
            $admin = User::factory()->admin()->create();
            $page = CmsPage::factory()->draft()->create([
                'title' => 'Página programada',
                'slug' => 'pagina-programada',
            ]);
            CmsBlock::factory()->for($page, 'page')->create();

            $response = $this->actingAs($admin)
                ->put(route('admin.cms-pages.update', $page), [
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'status' => CmsPageStatus::PUBLISHED->value,
                    'published_at' => '2026-08-02T12:00',
                    'seo_title' => '',
                    'seo_description' => '',
                ]);

            $response->assertRedirect(route('admin.cms-pages.index'));
            $this->assertSame('2026-08-02 12:00:00', $page->fresh()->published_at->format('Y-m-d H:i:s'));

            $this->actingAs($admin)
                ->get(route('admin.cms-pages.index'))
                ->assertOk()
                ->assertSee('Programada');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_index_presents_draft_scheduled_and_published_states(): void
    {
        $now = Carbon::parse('2026-08-01 12:00:00', config('app.timezone'));
        Carbon::setTestNow($now);

        try {
            $admin = User::factory()->admin()->create();
            $pastPage = CmsPage::factory()->published()->create([
                'title' => 'Publicación pasada',
                'published_at' => $now->copy()->subHour(),
            ]);
            CmsBlock::factory()->for($pastPage, 'page')->create();

            $immediatePage = CmsPage::factory()->create([
                'title' => 'Publicación inmediata',
                'status' => CmsPageStatus::PUBLISHED->value,
                'published_at' => null,
            ]);
            CmsBlock::factory()->for($immediatePage, 'page')->create();

            $scheduledPage = CmsPage::factory()->published()->create([
                'title' => 'Publicación futura',
                'published_at' => $now->copy()->addHour(),
            ]);
            CmsBlock::factory()->for($scheduledPage, 'page')->create();

            CmsPage::factory()->draft()->create([
                'title' => 'Borrador editorial',
            ]);

            $this->actingAs($admin)
                ->get(route('admin.cms-pages.index'))
                ->assertOk()
                ->assertSeeInOrder([
                    'Borrador editorial',
                    'Borrador',
                    'Publicación futura',
                    'Programada',
                    'Publicación inmediata',
                    'Publicada',
                    'Inmediata',
                    'Publicación pasada',
                    'Publicada',
                ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_edit_form_explains_publication_rules_and_configured_timezone(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->draft()->create();

        $this->actingAs($admin)
            ->get(route('admin.cms-pages.edit', $page))
            ->assertOk()
            ->assertSee('Esta página no tiene bloques y no podrá publicarse')
            ->assertSee('Déjala vacía para publicar inmediatamente.')
            ->assertSee('Una fecha futura programa la publicación.')
            ->assertSee('zona horaria configurada por Laravel: '.config('app.timezone'));
    }

    public function test_cms_page_update_allows_keeping_own_slug(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->draft()->create([
            'slug' => 'federarse',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.cms-pages.update', $page), [
                'title' => 'Federarse actualizado',
                'slug' => 'federarse',
                'status' => CmsPageStatus::DRAFT->value,
                'published_at' => '',
                'seo_title' => '',
                'seo_description' => '',
            ]);

        $response->assertRedirect(route('admin.cms-pages.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cms_pages', [
            'id' => $page->id,
            'slug' => 'federarse',
            'title' => 'Federarse actualizado',
        ]);
    }

    public function test_non_admin_user_cannot_access_cms_pages_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get(route('admin.cms-pages.index'));

        $response->assertForbidden();
    }
}
