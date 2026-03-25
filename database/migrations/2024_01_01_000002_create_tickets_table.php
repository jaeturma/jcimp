<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('total_quantity');
            $table->unsignedInteger('reserved_quantity')->default(0);
            $table->unsignedInteger('sold_quantity')->default(0);
            $table->enum('type', ['regular', 'student'])->default('regular');
            $table->unsignedTinyInteger('max_per_user')->default(4);
            $table->boolean('requires_verification')->default(false);
            $table->timestamps();
            // event_id index is created automatically by foreignId()->constrained()
            $table->index(['event_id', 'type']); // composite: list by event + filter by type
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
