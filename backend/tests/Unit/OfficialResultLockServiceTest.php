<?php

namespace Tests\Unit;

use App\Services\OfficialResultLockService;
use LogicException;
use Tests\TestCase;

class OfficialResultLockServiceTest extends TestCase
{
    public function test_common_lock_rejects_use_without_an_active_transaction(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Los locks de resultados oficiales requieren una transacción activa.');

        app(OfficialResultLockService::class)
            ->lockCategoriesAndCurrentOfficialResults([]);
    }
}
