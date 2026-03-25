<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->string('transaction_number')->nullable()->after('proof_image');
            $table->decimal('transaction_amount', 10, 2)->nullable()->after('transaction_number');
        });
    }

    public function down(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->dropColumn(['transaction_number', 'transaction_amount']);
        });
    }
};
