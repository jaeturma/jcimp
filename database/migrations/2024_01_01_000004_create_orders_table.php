<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('email');
            $table->enum('status', ['pending', 'pending_verification', 'paid', 'failed'])->default('pending');
            $table->enum('payment_method', ['qrph', 'manual'])->nullable();
            $table->decimal('total_amount', 10, 2);
            $table->string('gateway_reference')->nullable();
            $table->string('student_id_path')->nullable();
            $table->timestamps();

            // Composite index for student-rule validation: WHERE email = ? AND status = 'paid'
            $table->index(['email', 'status'], 'orders_email_status_idx');
            // Individual status index for admin dashboard / order listing
            $table->index('status', 'orders_status_idx');
            // Gateway reference for webhook idempotency lookup
            $table->index('gateway_reference', 'orders_gateway_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
