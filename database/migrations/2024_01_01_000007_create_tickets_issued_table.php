<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets_issued', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('qr_code')->unique();
            $table->enum('status', ['valid', 'used'])->default('valid');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            // order_id and ticket_id FK indexes created automatically by foreignId()->constrained()
            // Composite for "list tickets for an order by status" query
            $table->index(['order_id', 'status'], 'tickets_issued_order_status_idx');
            // qr_code already indexed by ->unique() above — no duplicate needed
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets_issued');
    }
};
