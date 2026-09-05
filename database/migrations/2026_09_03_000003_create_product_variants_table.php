<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            // String, not an enum -- sizes are shaped totally differently
            // per category (belts: "85"-"105", shoes: EU sizes, caps/bags:
            // "One size", clothing: "S"-"XXL").
            $table->string('size');
            $table->unsignedInteger('stock')->default(0);
            $table->timestamps();

            // A used item only has ONE of each size in stock in practice,
            // but this still guards against an admin fat-fingering the
            // same size twice for one product.
            $table->unique(['product_id', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
