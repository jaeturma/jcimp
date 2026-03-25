<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->unsignedTinyInteger('quantity');
            $table->timestamp('expires_at');
            $table->timestamps();

            // ticket_id FK index created automatically by foreignId()->constrained()
            // Composite index for expiry cleanup: WHERE ticket_id = ? AND expires_at <= now()
            $table->index(['ticket_id', 'expires_at'], 'reservations_ticket_expires_idx');
            // Separate index for expiry-only scan: WHERE expires_at <= now()
            $table->index('expires_at', 'reservations_expires_idx');
            // Email index for student duplicate check and lookup
            $table->index('email', 'reservations_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
