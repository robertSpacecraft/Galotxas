<?php

namespace Tests\Feature;

use App\Enums\ChampionshipRegistrationPaymentStatus;
use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChampionshipRegistrationRequestAdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('lastname')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role');
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('championships', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('championship_registration_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('championship_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('player_id')->nullable();
            $table->unsignedBigInteger('suggested_category_id')->nullable();
            $table->string('status');
            $table->string('payment_status');
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function test_admin_can_return_a_rejected_request_to_pending(): void
    {
        $admin = $this->createAdmin();
        $championship = $this->createChampionship();
        $registrationRequest = $this->createRejectedRequest($championship, $admin);

        $response = $this->actingAs($admin)->post(route(
            'admin.championships.registration-requests.mark-as-pending',
            [$championship, $registrationRequest]
        ));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Solicitud devuelta a pendiente correctamente.');
        $this->assertDatabaseHas('championship_registration_requests', [
            'id' => $registrationRequest->id,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PAID->value,
        ]);
    }

    public function test_admin_cannot_return_a_request_from_another_championship_to_pending(): void
    {
        $admin = $this->createAdmin();
        $championship = $this->createChampionship();
        $otherChampionship = $this->createChampionship();
        $registrationRequest = $this->createRejectedRequest($otherChampionship, $admin);

        $response = $this->actingAs($admin)->post(route(
            'admin.championships.registration-requests.mark-as-pending',
            [$championship, $registrationRequest]
        ));

        $response->assertNotFound();
        $this->assertDatabaseHas('championship_registration_requests', [
            'id' => $registrationRequest->id,
            'status' => ChampionshipRegistrationRequestStatus::REJECTED->value,
        ]);
    }

    private function createAdmin(): User
    {
        return User::query()->create([
            'name' => 'Admin',
            'email' => uniqid('admin-') . '@example.com',
            'password' => 'password',
            'role' => 'admin',
            'active' => true,
        ]);
    }

    private function createChampionship(): Championship
    {
        return Championship::query()->create([
            'name' => uniqid('Campeonato '),
            'type' => 'singles',
            'status' => 'active',
        ]);
    }

    private function createRejectedRequest(
        Championship $championship,
        User $user
    ): ChampionshipRegistrationRequest {
        return ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $user->id,
            'player_id' => null,
            'status' => ChampionshipRegistrationRequestStatus::REJECTED->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PAID->value,
        ]);
    }
}
