<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CmsBlockType;
use App\Models\CmsBlock;
use App\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCmsPageTest extends TestCase
{
    use RefreshDatabase;

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
