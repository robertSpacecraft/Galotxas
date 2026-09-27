<?php

namespace Tests\Unit;

use App\Enums\PublicIdentityAuthorizationMode;
use PHPUnit\Framework\TestCase;

class PublicIdentityAuthorizationModeTest extends TestCase
{
    public function test_alias_mode_authorizes_only_alias(): void
    {
        $mode = PublicIdentityAuthorizationMode::ALIAS;

        $this->assertTrue($mode->allowsAlias());
        $this->assertFalse($mode->allowsNameInitial());
    }

    public function test_name_initial_mode_authorizes_both_name_initial_and_alias(): void
    {
        $mode = PublicIdentityAuthorizationMode::NAME_INITIAL;

        $this->assertTrue($mode->allowsNameInitial());
        $this->assertTrue($mode->allowsAlias());
    }

    public function test_anonymous_mode_authorizes_neither(): void
    {
        $mode = PublicIdentityAuthorizationMode::ANONYMOUS;

        $this->assertFalse($mode->allowsAlias());
        $this->assertFalse($mode->allowsNameInitial());
    }

    public function test_enum_preserves_values_and_labels(): void
    {
        $this->assertSame(
            ['alias', 'name_initial', 'anonymous'],
            PublicIdentityAuthorizationMode::values()
        );

        $this->assertSame('Alias deportivo', PublicIdentityAuthorizationMode::ALIAS->label());
        $this->assertSame('Nombre e inicial', PublicIdentityAuthorizationMode::NAME_INITIAL->label());
        $this->assertSame('Identidad anónima', PublicIdentityAuthorizationMode::ANONYMOUS->label());
    }
}
