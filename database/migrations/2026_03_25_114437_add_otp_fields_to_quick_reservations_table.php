<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quick_reservations', function (Blueprint $table) {
            $table->string('otp_hash')->nullable()->after('used_at');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_hash');
            $table->timestamp('otp_verified_at')->nullable()->after('otp_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('quick_reservations', function (Blueprint $table) {
            $table->dropColumn(['otp_hash', 'otp_expires_at', 'otp_verified_at']);
        });
    }
};
