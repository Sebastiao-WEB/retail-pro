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
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nome');
            $table->string('codigo_barras')->nullable()->unique();
            $table->string('categoria')->nullable();
            $table->decimal('preco_compra', 14, 2)->default(0);
            $table->decimal('preco_venda', 14, 2)->default(0);
            $table->string('iva_tipo')->default('ISENTO');
            $table->decimal('iva_valor', 14, 2)->default(0);
            $table->decimal('iva_percentual', 5, 2)->default(0);
            $table->decimal('stock', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
