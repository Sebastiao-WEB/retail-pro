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
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('referencia')->unique();
            $table->uuid('register_id')->nullable();
            $table->uuid('source_location_id')->nullable();
            $table->uuid('customer_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('cliente')->default('Cliente Geral');
            $table->string('caixa')->nullable();
            $table->string('operador')->nullable();
            $table->string('metodo_pagamento')->default('Dinheiro');
            $table->string('estado')->default('Concluida');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('desconto_aplicado', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('valor_pago', 14, 2)->default(0);
            $table->decimal('troco', 14, 2)->default(0);
            $table->timestamp('data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
