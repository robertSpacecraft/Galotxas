<?php

namespace Tests\Feature;

use App\Enums\CmsNavigationSlot;
use App\Models\CmsNavigationItem;
use App\Models\CmsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCmsNavigationItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_view_index_and_structural_contract(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.cms-navigation.index'))
            ->assertOk()
            ->assertSeeInOrder(['Quiénes somos', 'Contacto', 'Federarse', 'Documentos'])
            ->assertSee(route('admin.cms-navigation.create'));
    }

    public function test_guest_normal_user_and_inactive_admin_cannot_manage_navigation(): void
    {
        $this->get(route('admin.cms-navigation.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.cms-navigation.index'))
            ->assertForbidden();

        $inactiveAdmin = User::factory()->admin()->create(['active' => false]);

        $this->actingAs($inactiveAdmin)
            ->get(route('admin.cms-navigation.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_prepare_inactive_placement_for_draft_page(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->draft()->create([
            'title' => 'Historia',
            'slug' => 'historia',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.cms-navigation.store'), $this->payload($page, [
                'label' => '  Historia del club  ',
                'is_active' => '0',
            ]));

        $response
            ->assertRedirect(route('admin.cms-navigation.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cms_navigation_items', [
            'cms_page_id' => $page->id,
            'slot' => CmsNavigationSlot::CLUB->value,
            'label' => 'Historia del club',
            'sort_order' => 10,
            'is_active' => 0,
        ]);
    }

    public function test_admin_can_create_active_edit_and_delete_without_deleting_page(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->published()->create();

        $this->actingAs($admin)
            ->post(route('admin.cms-navigation.store'), $this->payload($page, ['is_active' => '1']))
            ->assertRedirect(route('admin.cms-navigation.index'));

        $item = CmsNavigationItem::query()->firstOrFail();
        $this->assertTrue($item->is_active);

        $this->actingAs($admin)
            ->put(route('admin.cms-navigation.update', $item), $this->payload($page, [
                'label' => 'Nueva etiqueta',
                'sort_order' => 25,
                'is_active' => '0',
            ]))
            ->assertRedirect(route('admin.cms-navigation.index'));

        $this->assertDatabaseHas('cms_navigation_items', [
            'id' => $item->id,
            'label' => 'Nueva etiqueta',
            'sort_order' => 25,
            'is_active' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.cms-navigation.destroy', $item))
            ->assertRedirect(route('admin.cms-navigation.index'));

        $this->assertDatabaseMissing('cms_navigation_items', ['id' => $item->id]);
        $this->assertDatabaseHas('cms_pages', ['id' => $page->id]);
    }

    public function test_validation_rejects_invalid_payload_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.cms-navigation.create'))
            ->post(route('admin.cms-navigation.store'), [
                'cms_page_id' => $page->id,
                'slot' => 'footer',
                'label' => '<script>alert(1)</script>',
                'sort_order' => -1,
                'is_active' => '1',
                'url' => 'https://example.test',
                'route' => 'admin.dashboard',
            ]);

        $response
            ->assertRedirect(route('admin.cms-navigation.create'))
            ->assertSessionHasErrors(['slot', 'label', 'sort_order']);

        $this->assertDatabaseCount('cms_navigation_items', 0);
    }

    public function test_validation_fails_closed_for_manipulated_non_scalar_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.cms-navigation.store'), [
                'cms_page_id' => ['unexpected'],
                'label' => ['unexpected'],
                'sort_order' => 10,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors(['cms_page_id', 'label']);

        $this->assertDatabaseCount('cms_navigation_items', 0);
    }

    public function test_label_is_required_limited_and_cannot_be_a_url_or_contain_controls(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->create();

        foreach (['', str_repeat('a', 81), 'https://example.test', '/contenidos/historia', "Historia\nclub"] as $label) {
            $this->actingAs($admin)
                ->post(route('admin.cms-navigation.store'), $this->payload($page, ['label' => $label]))
                ->assertSessionHasErrors('label');
        }

        $this->assertDatabaseCount('cms_navigation_items', 0);
    }

    public function test_reserved_page_and_duplicate_assignment_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $reserved = CmsPage::factory()->create(['slug' => 'contacto']);
        $assigned = CmsPage::factory()->create(['slug' => 'historia']);
        CmsNavigationItem::factory()->for($assigned, 'cmsPage')->create();

        $this->actingAs($admin)
            ->post(route('admin.cms-navigation.store'), $this->payload($reserved))
            ->assertSessionHasErrors('cms_page_id');

        $this->actingAs($admin)
            ->post(route('admin.cms-navigation.store'), $this->payload($assigned))
            ->assertSessionHasErrors('cms_page_id');

        $this->assertDatabaseCount('cms_navigation_items', 1);
    }

    public function test_selector_excludes_reserved_and_assigned_pages_but_allows_current_page_on_edit(): void
    {
        $admin = User::factory()->admin()->create();
        $eligible = CmsPage::factory()->draft()->create(['title' => 'Historia eligible', 'slug' => 'historia']);
        $reserved = CmsPage::factory()->create(['title' => 'Contacto reservado', 'slug' => 'contacto']);
        $assigned = CmsPage::factory()->create(['title' => 'Prensa asignada', 'slug' => 'prensa']);
        $item = CmsNavigationItem::factory()->for($assigned, 'cmsPage')->create();

        $this->actingAs($admin)
            ->get(route('admin.cms-navigation.create'))
            ->assertOk()
            ->assertSee($eligible->title)
            ->assertDontSee($reserved->title)
            ->assertDontSee($assigned->title);

        $this->actingAs($admin)
            ->get(route('admin.cms-navigation.edit', $item))
            ->assertOk()
            ->assertSee($assigned->title)
            ->assertSee($eligible->title)
            ->assertDontSee($reserved->title);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(CmsPage $page, array $overrides = []): array
    {
        return array_merge([
            'cms_page_id' => $page->id,
            'label' => 'Historia del club',
            'sort_order' => 10,
            'is_active' => '0',
        ], $overrides);
    }
}
