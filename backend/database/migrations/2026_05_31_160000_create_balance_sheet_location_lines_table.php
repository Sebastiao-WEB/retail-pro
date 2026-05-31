<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_sheet_location_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('balance_sheet_id')->constrained('balance_sheets')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->string('local_codigo');
            $table->string('local_nome');
            $table->string('produto_nome');
            $table->string('codigo_barras')->nullable();
            $table->decimal('quantity', 14, 2)->default(0);
            $table->decimal('valor_compra', 14, 2)->default(0);
            $table->decimal('valor_venda', 14, 2)->default(0);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['balance_sheet_id', 'product_id', 'location_id'], 'bs_location_lines_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_sheet_location_lines');
    }
};
