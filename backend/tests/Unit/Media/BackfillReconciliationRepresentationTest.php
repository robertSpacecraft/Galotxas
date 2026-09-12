<?php

namespace Tests\Unit\Media;

use App\Services\Media\Backfill\Reconciliation\ItemReconciliationResult;
use App\Services\Media\Backfill\Reconciliation\ObjectReconciliationResolution;
use App\Services\Media\Backfill\Reconciliation\ReconciliationEventPointer;
use App\Services\Media\Backfill\Reconciliation\ReconciliationEventType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BackfillReconciliationRepresentationTest extends TestCase
{
    #[DataProvider('validEnumValues')]
    public function test_projection_and_event_enums_are_closed(string $enum, string $value): void
    {
        $this->assertSame($value, $enum::tryFrom($value)?->value);
        $this->assertNull($enum::tryFrom('future_unknown'));
    }

    public static function validEnumValues(): array
    {
        return [
            'object forward retained' => [ObjectReconciliationResolution::class, 'forward_retained'],
            'item forward accepted' => [ItemReconciliationResult::class, 'forward_accepted'],
            'item closed no effect' => [ItemReconciliationResult::class, 'closed_no_effect'],
            'attempt started' => [ReconciliationEventType::class, 'attempt_started'],
            'attempt blocked' => [ReconciliationEventType::class, 'attempt_blocked'],
            'item forward accepted event' => [ReconciliationEventType::class, 'item_forward_accepted'],
            'item no effect event' => [ReconciliationEventType::class, 'item_no_effect_closed'],
            'run closed event' => [ReconciliationEventType::class, 'run_closed_after_reconciliation'],
        ];
    }

    public function test_event_pointer_exposes_only_presence_validity_and_a_fingerprint(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440001';
        $pointer = ReconciliationEventPointer::from($uuid);

        $this->assertTrue($pointer->present);
        $this->assertTrue($pointer->valid);
        $this->assertSame(substr(hash('sha256', $uuid), 0, 16), $pointer->fingerprint);
        $this->assertObjectNotHasProperty('eventId', $pointer);
        $this->assertObjectNotHasProperty('raw', $pointer);

        $missing = ReconciliationEventPointer::from(null);
        $this->assertFalse($missing->present);
        $this->assertTrue($missing->valid);
        $this->assertNull($missing->fingerprint);

        $invalid = ReconciliationEventPointer::from('not-a-uuid');
        $this->assertTrue($invalid->present);
        $this->assertFalse($invalid->valid);
    }
}
