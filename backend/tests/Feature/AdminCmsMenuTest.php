<?php

namespace Tests\Feature;

use App\Models\CmsBlock;
use App\Models\CmsNavigationItem;
use App\Models\CmsPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdminCmsMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_index_marks_cms_group_and_pages_child_as_active(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.cms-pages.index'))
            ->assertOk();

        $this->assertCmsMenuLabels($response);
        $this->assertCmsMenuState($response, pagesActive: true, navigationActive: false);
        $response
            ->assertDontSee('CMS/Páginas')
            ->assertDontSee('Navegación CMS');
    }

    public function test_navigation_index_marks_cms_group_and_navigation_child_as_active(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.cms-navigation.index'))
            ->assertOk();

        $this->assertCmsMenuLabels($response);
        $this->assertCmsMenuState($response, pagesActive: false, navigationActive: true);
    }

    public function test_pages_descendants_keep_cms_group_and_pages_child_active(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->draft()->create();
        $block = CmsBlock::factory()->for($page, 'page')->create();

        foreach ([
            route('admin.cms-pages.create'),
            route('admin.cms-pages.show', $page),
            route('admin.cms-pages.edit', $page),
            route('admin.cms-pages.blocks.create', $page),
            route('admin.cms-pages.blocks.edit', [$page, $block]),
        ] as $url) {
            $response = $this->actingAs($admin)->get($url)->assertOk();

            $this->assertCmsMenuState($response, pagesActive: true, navigationActive: false);
        }
    }

    public function test_navigation_descendants_keep_cms_group_and_navigation_child_active(): void
    {
        $admin = User::factory()->admin()->create();
        $page = CmsPage::factory()->draft()->create();
        $item = CmsNavigationItem::factory()->for($page, 'cmsPage')->create();

        foreach ([
            route('admin.cms-navigation.create'),
            route('admin.cms-navigation.edit', $item),
        ] as $url) {
            $response = $this->actingAs($admin)->get($url)->assertOk();

            $this->assertCmsMenuState($response, pagesActive: false, navigationActive: true);
        }
    }

    public function test_other_admin_section_keeps_cms_group_inactive(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.dashboard'))
            ->assertOk();

        $this->assertCmsMenuLabels($response);
        $this->assertCmsMenuState($response, pagesActive: false, navigationActive: false);
        $response
            ->assertSee('Escuela de Galotxas')
            ->assertDontSee('CMS/Páginas')
            ->assertDontSee('Navegación CMS');
    }

    private function assertCmsMenuLabels(TestResponse $response): void
    {
        $content = $response->getContent();

        $this->assertMatchesRegularExpression(
            '/<button\b[^>]*\bid="cmsAdminDropdown"[^>]*>\s*CMS\s*<\/button>/s',
            $content
        );
        $this->assertMatchesRegularExpression(
            '/<a\b[^>]*\bhref="'.preg_quote(route('admin.cms-pages.index'), '/').'"[^>]*>\s*Páginas\s*<\/a>/s',
            $content
        );
        $this->assertMatchesRegularExpression(
            '/<a\b[^>]*\bhref="'.preg_quote(route('admin.cms-navigation.index'), '/').'"[^>]*>\s*Navegación\s*<\/a>/s',
            $content
        );
    }

    private function assertCmsMenuState(
        TestResponse $response,
        bool $pagesActive,
        bool $navigationActive
    ): void {
        $content = $response->getContent();
        $cmsActive = $pagesActive || $navigationActive;
        $toggle = $this->tagWithAttribute($content, 'button', 'id', 'cmsAdminDropdown');
        $menu = $this->tagWithAttribute($content, 'ul', 'id', 'cmsAdminMenu');
        $pagesLink = $this->tagWithAttribute(
            $content,
            'a',
            'href',
            route('admin.cms-pages.index')
        );
        $navigationLink = $this->tagWithAttribute(
            $content,
            'a',
            'href',
            route('admin.cms-navigation.index')
        );

        $this->assertSame($cmsActive, $this->hasClass($toggle, 'active'));
        $this->assertStringContainsString(
            'aria-expanded="'.($cmsActive ? 'true' : 'false').'"',
            $toggle
        );
        $this->assertSame($cmsActive, $this->hasClass($menu, 'show'));
        $this->assertLinkState($pagesLink, $pagesActive);
        $this->assertLinkState($navigationLink, $navigationActive);
    }

    private function assertLinkState(string $tag, bool $active): void
    {
        $this->assertSame($active, $this->hasClass($tag, 'active'));

        if ($active) {
            $this->assertStringContainsString('aria-current="page"', $tag);

            return;
        }

        $this->assertStringNotContainsString('aria-current=', $tag);
    }

    private function tagWithAttribute(
        string $content,
        string $element,
        string $attribute,
        string $value
    ): string {
        $matched = preg_match(
            '/<'.preg_quote($element, '/').'\b[^>]*\b'.preg_quote($attribute, '/').'="'
                .preg_quote($value, '/').'"[^>]*>/',
            $content,
            $matches
        );

        $this->assertSame(1, $matched, "No se encontró {$element}[{$attribute}=\"{$value}\"].");

        return $matches[0];
    }

    private function hasClass(string $tag, string $class): bool
    {
        $matched = preg_match('/\bclass="([^"]*)"/', $tag, $matches);

        $this->assertSame(1, $matched, 'El elemento no contiene un atributo class.');

        return in_array($class, preg_split('/\s+/', trim($matches[1])), true);
    }
}
