<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Modify the status enum to include 'otp_verified'
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'otp_verified', 'pending_verification', 'paid', 'failed'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'pending_verification', 'paid', 'failed'])->change();
        });
    }
};
