<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets_issued', function (Blueprint $table) {
            $table->string('holder_name')->nullable()->after('status');
            $table->string('holder_email')->nullable()->after('holder_name');
            $table->string('transfer_token')->nullable()->unique()->after('holder_email');
            $table->boolean('is_for_resale')->default(false)->after('transfer_token');
            $table->decimal('resale_price', 10, 2)->nullable()->after('is_for_resale');
            $table->foreignId('transferred_to_user_id')->nullable()->constrained('users')->nullOnDelete()->after('resale_price');
        });
    }

    public function down(): void
    {
        Schema::table('tickets_issued', function (Blueprint $table) {
            $table->dropColumn([
                'holder_name',
                'holder_email',
                'transfer_token',
                'is_for_resale',
                'resale_price',
                'transferred_to_user_id',
            ]);
        });
    }
};
