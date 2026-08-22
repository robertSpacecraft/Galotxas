<?php

namespace Tests\Feature;

use App\Enums\CmsNavigationSlot;
use App\Models\CmsNavigationItem;
use App\Models\CmsPage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsNavigationItemModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_casts_relates_and_uses_fail_closed_database_defaults(): void
    {
        $page = CmsPage::factory()->create();
        $item = CmsNavigationItem::query()->create([
            'cms_page_id' => $page->id,
            'slot' => CmsNavigationSlot::CLUB->value,
            'label' => 'Historia del club',
        ]);

        $item->refresh();

        $this->assertSame(CmsNavigationSlot::CLUB, $item->slot);
        $this->assertFalse($item->is_active);
        $this->assertIsBool($item->is_active);
        $this->assertSame(0, $item->sort_order);
        $this->assertTrue($item->cmsPage->is($page));
        $this->assertTrue($page->navigationItems()->first()->is($item));
    }

    public function test_factory_can_create_active_and_inactive_items(): void
    {
        $active = CmsNavigationItem::factory()->active()->create();
        $inactive = CmsNavigationItem::factory()->inactive()->create();

        $this->assertTrue($active->is_active);
        $this->assertFalse($inactive->is_active);
    }

    public function test_ordered_scope_is_stable_by_slot_order_and_id(): void
    {
        $last = CmsNavigationItem::factory()->create(['label' => 'Último', 'sort_order' => 20]);
        $firstTie = CmsNavigationItem::factory()->create(['label' => 'Primero A', 'sort_order' => 10]);
        $secondTie = CmsNavigationItem::factory()->create(['label' => 'Primero B', 'sort_order' => 10]);

        $this->assertSame(
            [$firstTie->id, $secondTie->id, $last->id],
            CmsNavigationItem::query()->ordered()->pluck('id')->all()
        );
    }

    public function test_database_rejects_duplicate_page_and_slot(): void
    {
        $page = CmsPage::factory()->create();
        CmsNavigationItem::factory()->for($page, 'cmsPage')->create();

        $this->expectException(QueryException::class);

        CmsNavigationItem::factory()->for($page, 'cmsPage')->create();
    }

    public function test_deleting_page_cascades_placement(): void
    {
        $item = CmsNavigationItem::factory()->create();
        $pageId = $item->cms_page_id;

        CmsPage::query()->findOrFail($pageId)->delete();

        $this->assertDatabaseMissing('cms_navigation_items', ['id' => $item->id]);
    }
}
