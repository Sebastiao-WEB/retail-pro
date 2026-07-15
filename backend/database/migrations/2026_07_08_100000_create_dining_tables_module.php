<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64)->unique();
            $table->string('name', 255)->nullable();
            $table->text('description')->nullable();
            $table->uuid('register_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('register_id')->references('id')->on('registers')->nullOnDelete();
            $table->index(['register_id', 'is_active']);
        });

        Schema::create('table_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dining_table_id');
            $table->uuid('register_id');
            $table->uuid('cash_session_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->uuid('sale_id')->nullable();
            $table->string('description', 500)->nullable();
            $table->string('status', 20)->default('OPEN');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('dining_table_id')->references('id')->on('dining_tables')->cascadeOnDelete();
            $table->foreign('register_id')->references('id')->on('registers')->cascadeOnDelete();
            $table->foreign('cash_session_id')->references('id')->on('cash_sessions')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
            $table->index(['register_id', 'status']);
            $table->index(['dining_table_id', 'status']);
        });

        Schema::create('table_order_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('table_order_id');
            $table->uuid('product_id')->nullable();
            $table->string('nome', 255);
            $table->decimal('quantidade', 12, 3);
            $table->decimal('preco_venda', 12, 2);
            $table->decimal('preco_sem_iva', 12, 2)->nullable();
            $table->decimal('iva_percentual', 8, 2)->nullable();
            $table->decimal('valor_iva_unitario', 12, 2)->nullable();
            $table->string('iva_tipo', 20)->nullable();
            $table->decimal('subtotal', 12, 2);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('table_order_id')->references('id')->on('table_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index('table_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_order_items');
        Schema::dropIfExists('table_orders');
        Schema::dropIfExists('dining_tables');
    }
};
