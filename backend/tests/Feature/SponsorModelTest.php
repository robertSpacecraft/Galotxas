<?php

namespace Tests\Feature;

use App\Enums\SponsorEffectiveState;
use App\Models\Sponsor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SponsorModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_defaults_casts_and_expressive_states_are_coherent(): void
    {
        $inactive = Sponsor::factory()->create();
        $active = Sponsor::factory()->active()->create();
        $scheduled = Sponsor::factory()->scheduled()->create();
        $expired = Sponsor::factory()->expired()->create();

        $this->assertFalse($inactive->is_active);
        $this->assertIsInt($inactive->logo_width);
        $this->assertIsInt($inactive->logo_height);
        $this->assertIsInt($inactive->sort_order);
        $this->assertNull($inactive->starts_at);
        $this->assertSame(SponsorEffectiveState::INACTIVE, $inactive->effectiveState());
        $this->assertSame(SponsorEffectiveState::ACTIVE, $active->effectiveState());
        $this->assertSame(SponsorEffectiveState::SCHEDULED, $scheduled->effectiveState());
        $this->assertSame(SponsorEffectiveState::EXPIRED, $expired->effectiveState());
    }

    public function test_effective_scope_applies_inclusive_start_exclusive_end_and_stable_order(): void
    {
        $now = CarbonImmutable::parse('2026-08-16 12:00:00');
        CarbonImmutable::setTestNow($now);

        $laterId = Sponsor::factory()->active()->create([
            'name' => 'Mismo orden primero por ID',
            'sort_order' => 20,
            'starts_at' => $now,
            'ends_at' => $now->addHour(),
        ]);
        $earlierOrder = Sponsor::factory()->active()->create([
            'name' => 'Orden menor',
            'sort_order' => 10,
        ]);
        $sameOrderLaterId = Sponsor::factory()->active()->create([
            'name' => 'Mismo orden segundo por ID',
            'sort_order' => 20,
        ]);
        Sponsor::factory()->active()->create([
            'ends_at' => $now,
        ]);
        Sponsor::factory()->active()->create([
            'starts_at' => $now->addSecond(),
        ]);
        Sponsor::factory()->inactive()->create();

        $visible = Sponsor::query()->effectivelyVisible($now)->ordered()->get();

        $this->assertSame(
            [$earlierOrder->id, $laterId->id, $sameOrderLaterId->id],
            $visible->modelKeys()
        );
        $this->assertTrue($laterId->isEffectivelyVisible($now));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }
}
