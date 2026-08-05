<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExternalResourcePrivacyTest extends TestCase
{
    public function test_public_welcome_does_not_load_remote_fonts_or_third_party_resources(): void
    {
        $response = $this->get('/')->assertOk();

        $response
            ->assertDontSee('fonts.bunny.net', false)
            ->assertDontSee('fonts.googleapis.com', false)
            ->assertDontSee('fonts.gstatic.com', false)
            ->assertDontSee('cdn.jsdelivr.net', false)
            ->assertDontSee('<iframe', false);
    }

    public function test_admin_layout_uses_only_local_styles_and_scripts(): void
    {
        $html = view('admin.layout')->render();

        $this->assertStringContainsString('/css/admin.css', $html);
        $this->assertStringContainsString('/js/admin.js', $html);
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('fonts.bunny.net', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertFileExists(public_path('css/admin.css'));
        $this->assertFileExists(public_path('js/admin.js'));
    }
}
