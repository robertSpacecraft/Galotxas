<?php

namespace Tests\Feature;

use App\Enums\ContactNotificationStatus;
use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminContactRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_access_navigation_list_and_detail(): void
    {
        $admin = User::factory()->admin()->create();
        $contactRequest = ContactRequest::factory()->newRequest()->create([
            'name' => 'Persona interesada',
            'email' => 'persona@example.test',
            'subject' => 'Consulta administrativa',
            'message' => "Primera línea.\nSegunda línea.",
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.contact-requests.index'))
            ->assertSee('Contacto');

        $this->actingAs($admin)
            ->get(route('admin.contact-requests.index'))
            ->assertOk()
            ->assertSee('Persona interesada')
            ->assertSee('Consulta administrativa')
            ->assertSee(route('admin.contact-requests.show', $contactRequest));

        $this->actingAs($admin)
            ->get(route('admin.contact-requests.show', $contactRequest))
            ->assertOk()
            ->assertSee('persona@example.test')
            ->assertSee('Primera línea.')
            ->assertSee('Segunda línea.')
            ->assertSee('Marcar como leída')
            ->assertSee('Cerrar');
    }

    public function test_contact_routes_reject_anonymous_normal_and_inactive_admin_users(): void
    {
        $user = User::factory()->create();
        $inactiveAdmin = User::factory()->admin()->create(['active' => false]);
        $contactRequest = ContactRequest::factory()->create();
        $getUrls = [
            route('admin.contact-requests.index'),
            route('admin.contact-requests.show', $contactRequest),
        ];

        foreach ($getUrls as $url) {
            $this->get($url)->assertRedirect(route('admin.login'));
            $this->actingAs($user)->get($url)->assertForbidden();
            $this->actingAs($inactiveAdmin)
                ->get($url)
                ->assertRedirect(route('admin.login'));
        }

        $this->actingAs($user)
            ->post(route('admin.contact-requests.close', $contactRequest))
            ->assertForbidden();
    }

    public function test_index_filters_by_status_and_uses_stable_pagination(): void
    {
        $admin = User::factory()->admin()->create();
        ContactRequest::factory()->read()->create([
            'subject' => 'Debe aparecer',
        ]);
        ContactRequest::factory()->newRequest()->create([
            'subject' => 'No debe aparecer',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.contact-requests.index', [
                'status' => ContactRequestStatus::READ->value,
            ]))
            ->assertOk()
            ->assertSee('Debe aparecer')
            ->assertDontSee('No debe aparecer');

        ContactRequest::factory()->count(25)->newRequest()->create();

        $this->actingAs($admin)
            ->get(route('admin.contact-requests.index'))
            ->assertOk()
            ->assertSee('page=2');
    }

    public function test_invalid_status_filter_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.contact-requests.index', ['status' => 'deleted']))
            ->assertSessionHasErrors('status');
    }

    public function test_index_filters_by_notification_status(): void
    {
        $admin = User::factory()->admin()->create();
        ContactRequest::factory()->notificationFailed()->create([
            'subject' => 'Fallo visible',
        ]);
        ContactRequest::factory()->create([
            'subject' => 'Sin intento oculto',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.contact-requests.index', [
                'notification_status' => ContactNotificationStatus::FAILED->value,
            ]))
            ->assertOk()
            ->assertSee('Fallo visible')
            ->assertDontSee('Sin intento oculto');
    }

    public function test_admin_marks_new_request_as_read_without_changing_original_content(): void
    {
        $admin = User::factory()->admin()->create();
        $contactRequest = ContactRequest::factory()->newRequest()->create();
        $original = $contactRequest->only(['name', 'email', 'subject', 'message']);

        $this->actingAs($admin)
            ->post(route('admin.contact-requests.mark-as-read', $contactRequest))
            ->assertRedirect(route('admin.contact-requests.show', $contactRequest))
            ->assertSessionHas('success');

        $contactRequest->refresh();
        $this->assertSame(ContactRequestStatus::READ, $contactRequest->status);
        $this->assertSame($original, $contactRequest->only(array_keys($original)));
    }

    public function test_admin_closes_new_or_read_request_without_editing_or_deleting_it(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            ContactRequest::factory()->newRequest()->create(),
            ContactRequest::factory()->read()->create(),
        ] as $contactRequest) {
            $message = $contactRequest->message;

            $this->actingAs($admin)
                ->post(route('admin.contact-requests.close', $contactRequest))
                ->assertRedirect(route('admin.contact-requests.show', $contactRequest));

            $contactRequest->refresh();
            $this->assertSame(ContactRequestStatus::CLOSED, $contactRequest->status);
            $this->assertSame($message, $contactRequest->message);
            $this->assertDatabaseHas('contact_requests', ['id' => $contactRequest->id]);
        }
    }

    public function test_closed_request_has_no_reopen_edit_or_destroy_flow(): void
    {
        $admin = User::factory()->admin()->create();
        $contactRequest = ContactRequest::factory()->closed()->create();

        $this->actingAs($admin)
            ->get(route('admin.contact-requests.show', $contactRequest))
            ->assertOk()
            ->assertDontSee('Marcar como leída')
            ->assertDontSee('Cerrar solicitud');

        $this->assertFalse(Route::has('admin.contact-requests.edit'));
        $this->assertFalse(Route::has('admin.contact-requests.update'));
        $this->assertFalse(Route::has('admin.contact-requests.destroy'));
    }
}
