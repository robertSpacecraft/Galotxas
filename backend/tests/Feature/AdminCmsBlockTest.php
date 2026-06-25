<?php

namespace Tests\Feature;

use App\Enums\CmsBlockType;
use App\Models\CmsBlock;
use App\Models\CmsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCmsBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_see_cms_page_blocks_ordered_by_sort_order(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->create(['title' => 'Federarse']);

        CmsBlock::factory()->for($page, 'page')->create([
            'type' => CmsBlockType::TEXT->value,
            'sort_order' => 20,
            'data' => ['text' => 'Segundo bloque'],
        ]);
        CmsBlock::factory()->for($page, 'page')->create([
            'type' => CmsBlockType::HEADING->value,
            'sort_order' => 10,
            'data' => ['text' => 'Primer bloque', 'level' => 2],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.cms-pages.show', $page));

        $response->assertOk();
        $response->assertSee('Bloques');
        $response->assertSee(route('admin.cms-pages.blocks.create', $page));
        $response->assertSeeInOrder(['Primer bloque', 'Segundo bloque']);
    }

    public function test_admin_can_create_heading_block(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.cms-pages.blocks.store', $page), [
                'type' => CmsBlockType::HEADING->value,
                'sort_order' => 10,
                'text' => 'Federarse',
                'level' => 2,
            ]);

        $response->assertRedirect(route('admin.cms-pages.show', $page));
        $response->assertSessionHas('success', 'Bloque CMS creado correctamente.');

        $block = CmsBlock::query()->where('cms_page_id', $page->id)->first();

        $this->assertNotNull($block);
        $this->assertSame(CmsBlockType::HEADING, $block->type);
        $this->assertSame(10, $block->sort_order);
        $this->assertSame([
            'text' => 'Federarse',
            'level' => 2,
        ], $block->data);
    }

    public function test_admin_can_edit_cms_block(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->create();
        $block = CmsBlock::factory()->for($page, 'page')->create([
            'type' => CmsBlockType::TEXT->value,
            'sort_order' => 10,
            'data' => ['text' => 'Texto inicial'],
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.cms-pages.blocks.update', [$page, $block]), [
                'type' => CmsBlockType::BUTTON->value,
                'sort_order' => 30,
                'label' => 'Inscribirme',
                'url' => '/inscripciones',
            ]);

        $response->assertRedirect(route('admin.cms-pages.show', $page));
        $response->assertSessionHas('success', 'Bloque CMS actualizado correctamente.');

        $block->refresh();

        $this->assertSame(CmsBlockType::BUTTON, $block->type);
        $this->assertSame(30, $block->sort_order);
        $this->assertSame([
            'label' => 'Inscribirme',
            'url' => '/inscripciones',
        ], $block->data);
    }

    public function test_admin_can_delete_cms_block(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->create();
        $block = CmsBlock::factory()->for($page, 'page')->create();

        $response = $this->actingAs($admin)
            ->delete(route('admin.cms-pages.blocks.destroy', [$page, $block]));

        $response->assertRedirect(route('admin.cms-pages.show', $page));
        $response->assertSessionHas('success', 'Bloque CMS eliminado correctamente.');

        $this->assertDatabaseMissing('cms_blocks', [
            'id' => $block->id,
        ]);
        $this->assertDatabaseHas('cms_pages', [
            'id' => $page->id,
        ]);
    }

    public function test_cannot_create_block_with_invalid_type(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.cms-pages.blocks.create', $page))
            ->post(route('admin.cms-pages.blocks.store', $page), [
                'type' => 'html',
                'sort_order' => 10,
                'text' => '<strong>No permitido</strong>',
            ]);

        $response->assertRedirect(route('admin.cms-pages.blocks.create', $page));
        $response->assertSessionHasErrors('type');
        $this->assertDatabaseCount('cms_blocks', 0);
    }

    public function test_cannot_save_invalid_data_for_selected_type(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.cms-pages.blocks.create', $page))
            ->post(route('admin.cms-pages.blocks.store', $page), [
                'type' => CmsBlockType::TEXT->value,
                'sort_order' => 10,
                'text' => '',
            ]);

        $response->assertRedirect(route('admin.cms-pages.blocks.create', $page));
        $response->assertSessionHasErrors('text');
        $this->assertDatabaseCount('cms_blocks', 0);
    }

    public function test_protocol_relative_url_is_rejected_for_url_blocks(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.cms-pages.blocks.create', $page))
            ->post(route('admin.cms-pages.blocks.store', $page), [
                'type' => CmsBlockType::IMAGE->value,
                'sort_order' => 10,
                'url' => '//example.com/imagen.jpg',
                'alt' => 'Imagen externa',
            ]);

        $response->assertRedirect(route('admin.cms-pages.blocks.create', $page));
        $response->assertSessionHasErrors('url');
        $this->assertDatabaseCount('cms_blocks', 0);
    }

    public function test_internal_path_is_accepted_for_url_blocks(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.cms-pages.blocks.store', $page), [
                'type' => CmsBlockType::DOCUMENT_LINK->value,
                'sort_order' => 10,
                'label' => 'Normativa',
                'url' => '/documents/normativa.pdf',
            ]);

        $response->assertRedirect(route('admin.cms-pages.show', $page));

        $block = CmsBlock::query()->where('cms_page_id', $page->id)->first();

        $this->assertNotNull($block);
        $this->assertSame([
            'label' => 'Normativa',
            'url' => '/documents/normativa.pdf',
        ], $block->data);
    }

    public function test_non_admin_user_cannot_access_cms_blocks_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $page = CmsPage::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.cms-pages.blocks.create', $page));

        $response->assertForbidden();
    }

    public function test_cannot_edit_block_through_another_page_route(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->create();
        $otherPage = CmsPage::factory()->create();
        $block = CmsBlock::factory()->for($otherPage, 'page')->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.cms-pages.blocks.edit', [$page, $block]));

        $response->assertNotFound();
    }

    public function test_admin_created_block_is_returned_by_public_endpoint(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->published()->create([
            'slug' => 'academy',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.cms-pages.blocks.store', $page), [
                'type' => CmsBlockType::LIST->value,
                'sort_order' => 10,
                'items_text' => "Primer punto\nSegundo punto",
            ])
            ->assertRedirect(route('admin.cms-pages.show', $page));

        $this->getJson('/api/v1/cms/pages/academy')
            ->assertOk()
            ->assertJsonPath('data.blocks.0.type', 'list')
            ->assertJsonPath('data.blocks.0.order', 10)
            ->assertJsonPath('data.blocks.0.data.items.0', 'Primer punto')
            ->assertJsonPath('data.blocks.0.data.items.1', 'Segundo punto');
    }
}
