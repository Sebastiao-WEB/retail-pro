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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->uuid('produto_id')->nullable();
            $table->string('nome');
            $table->decimal('quantidade', 14, 2);
            $table->decimal('preco_venda', 14, 2)->default(0);
            $table->decimal('preco_sem_iva', 14, 2)->default(0);
            $table->decimal('iva_percentual', 5, 2)->default(0);
            $table->decimal('valor_iva_unitario', 14, 2)->default(0);
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
