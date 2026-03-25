<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            // Add OCR-related fields
            $table->text('ocr_text')->nullable()->after('transaction_amount')->comment('Full OCR extracted text from payment proof');
            $table->float('ocr_confidence')->nullable()->after('ocr_text')->comment('OCR extraction confidence score (0-100)');
            $table->boolean('ocr_extracted')->default(false)->after('ocr_confidence')->comment('Whether transaction details were auto-extracted via OCR');
        });
    }

    public function down(): void
    {
        Schema::table('manual_payments', function (Blueprint $table) {
            $table->dropColumn(['ocr_text', 'ocr_confidence', 'ocr_extracted']);
        });
    }
};
