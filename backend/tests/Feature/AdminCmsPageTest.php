<?php

namespace Tests\Feature;

use App\Enums\CmsBlockType;
use App\Enums\CmsPageStatus;
use App\Models\CmsBlock;
use App\Models\CmsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_admin_can_create_cms_page(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.cms-pages.store'), [
                'title' => 'Academy',
                'slug' => 'academy',
                'status' => CmsPageStatus::PUBLISHED->value,
                'published_at' => '',
                'seo_title' => 'Academy Galotxas',
                'seo_description' => 'Información de escuela.',
            ]);

        $response->assertRedirect(route('admin.cms-pages.index'));
        $response->assertSessionHas('success', 'Página CMS creada correctamente.');

        $page = CmsPage::query()->where('slug', 'academy')->first();

        $this->assertNotNull($page);
        $this->assertSame('Academy', $page->title);
        $this->assertSame(CmsPageStatus::PUBLISHED, $page->status);
        $this->assertNotNull($page->published_at);
        $this->assertSame('Academy Galotxas', $page->seo_title);
        $this->assertSame('Información de escuela.', $page->seo_description);
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
