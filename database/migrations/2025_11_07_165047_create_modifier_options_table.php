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
        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modifier_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('price_delta', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->index(['modifier_id', 'is_active', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modifier_options');
    }
};
