<?php

namespace Tests\Feature\Api\V1;

use App\Enums\CmsPageStatus;
use App\Models\CmsNavigationItem;
use App\Models\CmsPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicCmsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_effectively_published_placement_has_closed_contract_and_derived_url(): void
    {
        $page = CmsPage::factory()->published()->create([
            'slug' => 'historia-del-club',
            'title' => 'Private title marker',
        ]);
        CmsNavigationItem::factory()->active()->for($page, 'cmsPage')->create([
            'label' => 'Historia',
            'sort_order' => 15,
        ]);

        $response = $this->getJson('/api/v1/cms-navigation');

        $response
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson([
                'message' => null,
                'data' => [[
                    'slot' => 'club',
                    'label' => 'Historia',
                    'url' => '/contenidos/historia-del-club',
                    'sort_order' => 15,
                ]],
            ]);

        $this->assertStringNotContainsString('Private title marker', $response->getContent());

        $page->update(['slug' => 'memoria-del-club']);

        $this->getJson('/api/v1/cms-navigation')
            ->assertJsonPath('data.0.url', '/contenidos/memoria-del-club');
    }

    public function test_inactive_draft_future_reserved_and_invalid_label_items_are_hidden(): void
    {
        Carbon::setTestNow('2026-08-22 12:00:00');

        try {
            $inactivePage = CmsPage::factory()->published()->create(['slug' => 'inactiva']);
            CmsNavigationItem::factory()->inactive()->for($inactivePage, 'cmsPage')->create();

            $draftPage = CmsPage::factory()->draft()->create(['slug' => 'borrador']);
            CmsNavigationItem::factory()->active()->for($draftPage, 'cmsPage')->create();

            $futurePage = CmsPage::factory()->published()->create([
                'slug' => 'futura',
                'published_at' => now()->addMinute(),
            ]);
            CmsNavigationItem::factory()->active()->for($futurePage, 'cmsPage')->create();

            $reservedPage = CmsPage::factory()->published()->create(['slug' => 'documentos']);
            CmsNavigationItem::factory()->active()->for($reservedPage, 'cmsPage')->create();

            $invalidLabelPage = CmsPage::factory()->published()->create(['slug' => 'etiqueta-invalida']);
            CmsNavigationItem::factory()->active()->for($invalidLabelPage, 'cmsPage')->create([
                'label' => '<strong>Menú</strong>',
            ]);

            $this->getJson('/api/v1/cms-navigation')
                ->assertOk()
                ->assertExactJson(['message' => null, 'data' => []]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_republishing_page_restores_active_placement(): void
    {
        $page = CmsPage::factory()->draft()->create(['slug' => 'historia']);
        CmsNavigationItem::factory()->active()->for($page, 'cmsPage')->create(['label' => 'Historia']);

        $this->getJson('/api/v1/cms-navigation')
            ->assertJsonCount(0, 'data');

        $page->update([
            'status' => CmsPageStatus::PUBLISHED->value,
            'published_at' => null,
        ]);

        $this->getJson('/api/v1/cms-navigation')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.url', '/contenidos/historia');
    }

    public function test_items_have_stable_order_and_empty_response_is_an_array(): void
    {
        $this->getJson('/api/v1/cms-navigation')
            ->assertExactJson(['message' => null, 'data' => []]);

        $last = CmsNavigationItem::factory()->active()->for(
            CmsPage::factory()->published()->state(['slug' => 'ultima']),
            'cmsPage'
        )->create(['label' => 'Última', 'sort_order' => 20]);
        $firstTie = CmsNavigationItem::factory()->active()->for(
            CmsPage::factory()->published()->state(['slug' => 'primera-a']),
            'cmsPage'
        )->create(['label' => 'Primera A', 'sort_order' => 10]);
        $secondTie = CmsNavigationItem::factory()->active()->for(
            CmsPage::factory()->published()->state(['slug' => 'primera-b']),
            'cmsPage'
        )->create(['label' => 'Primera B', 'sort_order' => 10]);

        $this->getJson('/api/v1/cms-navigation')
            ->assertJsonPath('data.0.label', $firstTie->label)
            ->assertJsonPath('data.1.label', $secondTie->label)
            ->assertJsonPath('data.2.label', $last->label);
    }

    public function test_endpoint_eager_loads_pages_in_constant_query_count(): void
    {
        CmsNavigationItem::factory()->active()->count(5)->create();

        CmsPage::query()->update([
            'status' => CmsPageStatus::PUBLISHED->value,
            'published_at' => now()->subDay(),
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/v1/cms-navigation')->assertOk();

        $this->assertCount(2, DB::getQueryLog());
    }
}
