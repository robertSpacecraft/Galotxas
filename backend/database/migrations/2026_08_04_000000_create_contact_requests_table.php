<?php

use App\Enums\ContactRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 254);
            $table->string('subject', 200);
            $table->text('message');
            $table->string('status', 20)->default(ContactRequestStatus::NEW->value);
            $table->dateTime('consent_at');
            $table->char('ip_hash', 64);
            $table->timestamps();

            $table->index(
                ['status', 'created_at'],
                'contact_requests_status_created_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_requests');
    }
};
