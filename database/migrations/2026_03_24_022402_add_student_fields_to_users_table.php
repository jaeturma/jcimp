<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_student_verified')->default(false)->after('is_admin');
            $table->enum('student_type', ['college', 'highschool'])->nullable()->after('is_student_verified');
            $table->string('school_email')->nullable()->after('student_type');
            $table->string('lrn_number', 12)->nullable()->after('school_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_student_verified', 'student_type', 'school_email', 'lrn_number']);
        });
    }
};
