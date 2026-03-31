<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets_issued', function (Blueprint $table) {
            $table->string('ticket_card_path')->nullable()->after('qr_code');
        });
    }

    public function down(): void
    {
        Schema::table('tickets_issued', function (Blueprint $table) {
            $table->dropColumn('ticket_card_path');
        });
    }
};
