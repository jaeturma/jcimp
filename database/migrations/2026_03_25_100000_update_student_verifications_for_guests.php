<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Expand the status enum to include 'pending_otp'
        DB::statement("ALTER TABLE student_verifications MODIFY COLUMN status ENUM('pending_otp','pending','approved','rejected') DEFAULT 'pending_otp'");

        Schema::table('student_verifications', function (Blueprint $table) {
            // Make user_id nullable for guest submissions
            $table->foreignId('user_id')->nullable()->change();
            // Guest email (used when user_id is null)
            $table->string('guest_email')->nullable()->after('user_id');
            // OTP fields
            $table->string('otp_hash')->nullable()->after('guest_email');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_hash');
            $table->timestamp('otp_verified_at')->nullable()->after('otp_expires_at');
            // Access token issued after full verification
            $table->uuid('access_token')->nullable()->unique()->after('otp_verified_at');
            $table->timestamp('token_expires_at')->nullable()->after('access_token');
        });
    }

    public function down(): void {
        Schema::table('student_verifications', function (Blueprint $table) {
            $table->dropColumn(['guest_email','otp_hash','otp_expires_at','otp_verified_at','access_token','token_expires_at']);
            $table->foreignId('user_id')->nullable(false)->change();
        });
        DB::statement("ALTER TABLE student_verifications MODIFY COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending'");
    }
};
