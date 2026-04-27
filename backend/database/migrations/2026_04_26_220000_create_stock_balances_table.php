<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('location_id');
            $table->uuid('product_id');
            $table->decimal('quantity', 14, 2)->default(0);
            $table->decimal('min_stock', 14, 2)->nullable();
            $table->decimal('max_stock', 14, 2)->nullable();
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('stock_locations');
            $table->foreign('product_id')->references('id')->on('products');
            $table->unique(['location_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
