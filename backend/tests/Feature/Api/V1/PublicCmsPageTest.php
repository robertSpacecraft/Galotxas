<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CmsBlockType;
use App\Enums\CmsPageStatus;
use App\Models\CmsBlock;
use App\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicCmsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_index_returns_only_current_published_pages_without_blocks(): void
    {
        $publishedPage = CmsPage::factory()->published()->create([
            'slug' => 'federarse',
            'title' => 'Federarse',
            'seo_description' => 'Información para federarse.',
        ]);

        CmsBlock::factory()->for($publishedPage, 'page')->create([
            'type' => CmsBlockType::TEXT->value,
            'data' => ['text' => 'Contenido interno del detalle.'],
        ]);

        CmsPage::factory()->draft()->create([
            'slug' => 'borrador',
            'title' => 'Borrador',
        ]);

        CmsPage::factory()->published()->create([
            'slug' => 'programada',
            'title' => 'Programada',
            'published_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/cms/pages')
            ->assertOk()
            ->assertJsonPath('message', null)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'federarse')
            ->assertJsonPath('data.0.title', 'Federarse')
            ->assertJsonPath('data.0.seo_description', 'Información para federarse.')
            ->assertJsonPath('data.0.url', '/contenidos/federarse')
            ->assertJsonMissingPath('data.0.blocks')
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.status')
            ->assertJsonMissingPath('data.0.created_at')
            ->assertJsonMissingPath('data.0.updated_at');
    }

    public function test_public_index_orders_pages_by_published_at_desc_then_id_desc(): void
    {
        $olderPage = CmsPage::factory()->published()->create([
            'slug' => 'primera',
            'published_at' => now()->subDays(3),
        ]);

        $sameDateFirstPage = CmsPage::factory()->published()->create([
            'slug' => 'segunda',
            'published_at' => now()->subDay(),
        ]);

        $sameDateSecondPage = CmsPage::factory()->published()->create([
            'slug' => 'tercera',
            'published_at' => $sameDateFirstPage->published_at,
        ]);

        $this->getJson('/api/v1/cms/pages')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $sameDateSecondPage->slug)
            ->assertJsonPath('data.1.slug', $sameDateFirstPage->slug)
            ->assertJsonPath('data.2.slug', $olderPage->slug);
    }

    public function test_public_list_and_detail_share_immediate_past_future_and_draft_criteria(): void
    {
        $now = Carbon::parse('2026-08-01 12:00:00', config('app.timezone'));
        Carbon::setTestNow($now);

        try {
            $immediatePage = CmsPage::factory()->create([
                'slug' => 'publicacion-inmediata',
                'status' => CmsPageStatus::PUBLISHED->value,
                'published_at' => null,
            ]);
            CmsBlock::factory()->for($immediatePage, 'page')->create();

            $pastPage = CmsPage::factory()->published()->create([
                'slug' => 'publicacion-pasada',
                'published_at' => $now->copy()->subMinute(),
            ]);
            CmsBlock::factory()->for($pastPage, 'page')->create();

            $futurePage = CmsPage::factory()->published()->create([
                'slug' => 'publicacion-futura',
                'published_at' => $now->copy()->addMinute(),
            ]);
            CmsBlock::factory()->for($futurePage, 'page')->create();

            $draftPage = CmsPage::factory()->draft()->create([
                'slug' => 'borrador-no-publico',
            ]);
            CmsBlock::factory()->for($draftPage, 'page')->create();

            $this->getJson('/api/v1/cms/pages')
                ->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonFragment(['slug' => 'publicacion-inmediata'])
                ->assertJsonFragment(['slug' => 'publicacion-pasada'])
                ->assertJsonMissing(['slug' => 'publicacion-futura'])
                ->assertJsonMissing(['slug' => 'borrador-no-publico']);

            $this->getJson('/api/v1/cms/pages/publicacion-inmediata')->assertOk();
            $this->getJson('/api/v1/cms/pages/publicacion-pasada')->assertOk();
            $this->getJson('/api/v1/cms/pages/publicacion-futura')->assertNotFound();
            $this->getJson('/api/v1/cms/pages/borrador-no-publico')->assertNotFound();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_public_endpoint_returns_a_published_page_by_slug(): void
    {
        $page = CmsPage::factory()->published()->create([
            'slug' => 'federarse',
            'title' => 'Federarse',
            'seo_title' => 'Federarse en Galotxas',
            'seo_description' => 'Información pública para federarse.',
        ]);

        CmsBlock::factory()->for($page, 'page')->create([
            'type' => CmsBlockType::HEADING->value,
            'sort_order' => 1,
            'data' => ['text' => 'Federarse'],
        ]);

        $this->getJson('/api/v1/cms/pages/federarse')
            ->assertOk()
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.slug', 'federarse')
            ->assertJsonPath('data.title', 'Federarse')
            ->assertJsonPath('data.seo_title', 'Federarse en Galotxas')
            ->assertJsonPath('data.seo_description', 'Información pública para federarse.')
            ->assertJsonPath('data.blocks.0.type', 'heading')
            ->assertJsonPath('data.blocks.0.data.text', 'Federarse');
    }

    public function test_public_endpoint_returns_not_found_for_missing_page(): void
    {
        $this->getJson('/api/v1/cms/pages/no-existe')
            ->assertNotFound();
    }

    public function test_public_endpoint_returns_not_found_for_draft_page(): void
    {
        CmsPage::factory()->draft()->create([
            'slug' => 'borrador',
        ]);

        $this->getJson('/api/v1/cms/pages/borrador')
            ->assertNotFound();
    }

    public function test_public_endpoint_returns_not_found_for_future_published_page(): void
    {
        CmsPage::factory()->published()->create([
            'slug' => 'programada',
            'published_at' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/cms/pages/programada')
            ->assertNotFound();
    }

    public function test_public_endpoint_returns_blocks_ordered_by_sort_order(): void
    {
        $page = CmsPage::factory()->published()->create([
            'slug' => 'academy',
        ]);

        CmsBlock::factory()->for($page, 'page')->create([
            'type' => CmsBlockType::TEXT->value,
            'sort_order' => 20,
            'data' => ['text' => 'Segundo bloque'],
        ]);

        CmsBlock::factory()->for($page, 'page')->create([
            'type' => CmsBlockType::HEADING->value,
            'sort_order' => 10,
            'data' => ['text' => 'Primer bloque'],
        ]);

        $this->getJson('/api/v1/cms/pages/academy')
            ->assertOk()
            ->assertJsonPath('data.blocks.0.order', 10)
            ->assertJsonPath('data.blocks.0.data.text', 'Primer bloque')
            ->assertJsonPath('data.blocks.1.order', 20)
            ->assertJsonPath('data.blocks.1.data.text', 'Segundo bloque');
    }

    public function test_public_endpoint_does_not_expose_internal_fields(): void
    {
        $page = CmsPage::factory()->published()->create([
            'slug' => 'publica',
        ]);

        CmsBlock::factory()->for($page, 'page')->create([
            'type' => CmsBlockType::DOCUMENT_LINK->value,
            'sort_order' => 1,
            'data' => [
                'label' => 'Normativa',
                'url' => '/documents/normativa.pdf',
            ],
        ]);

        $this->getJson('/api/v1/cms/pages/publica')
            ->assertOk()
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.status')
            ->assertJsonMissingPath('data.created_at')
            ->assertJsonMissingPath('data.updated_at')
            ->assertJsonMissingPath('data.blocks.0.id')
            ->assertJsonMissingPath('data.blocks.0.cms_page_id')
            ->assertJsonMissingPath('data.blocks.0.sort_order')
            ->assertJsonMissingPath('data.blocks.0.created_at')
            ->assertJsonMissingPath('data.blocks.0.updated_at');
    }
}
