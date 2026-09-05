<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Whole kronor, no ören -- matches how the shop always prices
            // (1499 kr, never 1499.50) and avoids float rounding entirely.
            $table->unsignedInteger('price');
            $table->unsignedInteger('sale_price')->nullable();

            // Free text on purpose (not an enum column) -- lets the admin
            // phrase condition naturally ("Nyskick", "Bra skick, lätt
            // nagg i sulan") without a migration every time a new nuance
            // shows up. ProductResource offers common presets as a
            // creatable select so it stays consistent day to day.
            $table->string('condition')->nullable();

            $table->string('image')->nullable();
            $table->boolean('is_new')->default(false);
            $table->boolean('is_active')->default(true);

            // Free-form specs (material, brand, size guide notes, ...) --
            // varies enough by category (belts vs. shoes vs. bags) that a
            // fixed set of columns would mean lots of unused nullable
            // columns. Key/value pairs edited via a Repeater in the admin.
            $table->json('specs')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
