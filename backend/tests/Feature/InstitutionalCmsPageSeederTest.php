<?php

namespace Tests\Feature;

use App\Enums\CmsPageStatus;
use App\Models\CmsPage;
use Database\Seeders\InstitutionalCmsPageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstitutionalCmsPageSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_published_institutional_cms_pages_with_basic_blocks(): void
    {
        $this->seed(InstitutionalCmsPageSeeder::class);

        foreach ($this->expectedSlugs() as $slug) {
            $page = CmsPage::query()
                ->where('slug', $slug)
                ->with('blocks')
                ->first();

            $this->assertNotNull($page);
            $this->assertSame(CmsPageStatus::PUBLISHED, $page->status);
            $this->assertNotNull($page->published_at);
            $this->assertCount(2, $page->blocks);
            $this->assertSame('heading', $page->blocks[0]->type->value);
            $this->assertSame('text', $page->blocks[1]->type->value);
        }
    }

    public function test_it_does_not_overwrite_existing_cms_pages(): void
    {
        $existingPage = CmsPage::factory()->draft()->create([
            'slug' => 'nosotros',
            'title' => 'Contenido propio',
            'seo_description' => 'Descripción propia.',
        ]);

        $this->seed(InstitutionalCmsPageSeeder::class);

        $existingPage->refresh();

        $this->assertSame('Contenido propio', $existingPage->title);
        $this->assertSame('Descripción propia.', $existingPage->seo_description);
        $this->assertSame(CmsPageStatus::DRAFT, $existingPage->status);
        $this->assertCount(0, $existingPage->blocks);
        $this->assertSame(6, CmsPage::query()->count());
    }

    /**
     * @return array<int, string>
     */
    private function expectedSlugs(): array
    {
        return [
            'prensa-media',
            'nosotros',
            'federaciones',
            'academy',
            'documentos',
            'federarse',
        ];
    }
}
