<?php

namespace Tests\Feature;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContactRequestModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_contains_only_the_required_contact_fields(): void
    {
        $this->assertTrue(Schema::hasColumns('contact_requests', [
            'id',
            'name',
            'email',
            'subject',
            'message',
            'status',
            'consent_at',
            'ip_hash',
            'created_at',
            'updated_at',
        ]));

        foreach (['phone', 'ip', 'user_agent', 'attachment', 'dni'] as $column) {
            $this->assertFalse(Schema::hasColumn('contact_requests', $column));
        }
    }

    public function test_model_casts_status_and_consent_timestamp(): void
    {
        $contactRequest = ContactRequest::factory()->create([
            'status' => ContactRequestStatus::READ->value,
            'consent_at' => '2026-08-04 10:30:00',
        ]);

        $this->assertSame(ContactRequestStatus::READ, $contactRequest->status);
        $this->assertSame(
            '2026-08-04T10:30:00+00:00',
            $contactRequest->consent_at->toIso8601String()
        );
    }

    public function test_factory_exposes_all_statuses_and_scopes_order_stably(): void
    {
        $old = ContactRequest::factory()->newRequest()->create([
            'created_at' => '2026-08-01 09:00:00',
        ]);
        $read = ContactRequest::factory()->read()->create([
            'created_at' => '2026-08-02 09:00:00',
        ]);
        $closed = ContactRequest::factory()->closed()->create([
            'created_at' => '2026-08-03 09:00:00',
        ]);

        $this->assertSame(
            [$closed->id, $read->id, $old->id],
            ContactRequest::query()->ordered()->pluck('id')->all()
        );
        $this->assertSame(
            [$read->id],
            ContactRequest::query()
                ->withStatus(ContactRequestStatus::READ)
                ->pluck('id')
                ->all()
        );
    }
}
