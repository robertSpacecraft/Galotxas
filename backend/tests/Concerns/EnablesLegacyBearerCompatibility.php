<?php

namespace Tests\Concerns;

/**
 * El Bearer legacy está retirado por defecto (false/false). Los tests que
 * ejercitan explícitamente el contrato Bearer lo reactivan (true/true,
 * compatibilidad explícita) para toda la clase.
 */
trait EnablesLegacyBearerCompatibility
{
    protected function setUpEnablesLegacyBearerCompatibility(): void
    {
        $this->enableLegacyBearerCompatibility();
    }

    protected function enableLegacyBearerCompatibility(): void
    {
        config()->set('legacy_bearer.issuance_enabled', true);
        config()->set('legacy_bearer.acceptance_enabled', true);
    }
}
