<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('balance_sheets', function (Blueprint $table) {
            $table->decimal('total_recargas_qtd', 14, 2)->default(0)->after('finalized_at');
            $table->decimal('total_recargas_valor', 14, 2)->default(0)->after('total_recargas_qtd');
            $table->decimal('total_vendas_qtd', 14, 2)->default(0)->after('total_recargas_valor');
            $table->decimal('total_vendas_valor', 14, 2)->default(0)->after('total_vendas_qtd');
            $table->decimal('total_custo_vendas', 14, 2)->default(0)->after('total_vendas_valor');
            $table->decimal('total_lucro', 14, 2)->default(0)->after('total_custo_vendas');
            $table->decimal('total_stock_qtd', 14, 2)->default(0)->after('total_lucro');
            $table->decimal('total_stock_valor_compra', 14, 2)->default(0)->after('total_stock_qtd');
            $table->decimal('total_stock_valor_venda', 14, 2)->default(0)->after('total_stock_valor_compra');
        });

        Schema::table('balance_sheet_lines', function (Blueprint $table) {
            $table->foreignUuid('product_id')->nullable()->after('balance_sheet_id')->constrained('products')->nullOnDelete();
            $table->decimal('qtd_recarregada', 14, 2)->default(0)->after('ordem');
            $table->decimal('valor_recarga', 14, 2)->default(0)->after('qtd_recarregada');
            $table->decimal('qtd_vendida', 14, 2)->default(0)->after('valor_recarga');
            $table->decimal('valor_vendas', 14, 2)->default(0)->after('qtd_vendida');
            $table->decimal('custo_vendas', 14, 2)->default(0)->after('valor_vendas');
            $table->decimal('lucro', 14, 2)->default(0)->after('custo_vendas');
            $table->decimal('qtd_stock', 14, 2)->default(0)->after('lucro');
            $table->decimal('valor_stock_compra', 14, 2)->default(0)->after('qtd_stock');
            $table->decimal('valor_stock_venda', 14, 2)->default(0)->after('valor_stock_compra');
        });
    }

    public function down(): void
    {
        Schema::table('balance_sheet_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn([
                'qtd_recarregada',
                'valor_recarga',
                'qtd_vendida',
                'valor_vendas',
                'custo_vendas',
                'lucro',
                'qtd_stock',
                'valor_stock_compra',
                'valor_stock_venda',
            ]);
        });

        Schema::table('balance_sheets', function (Blueprint $table) {
            $table->dropColumn([
                'total_recargas_qtd',
                'total_recargas_valor',
                'total_vendas_qtd',
                'total_vendas_valor',
                'total_custo_vendas',
                'total_lucro',
                'total_stock_qtd',
                'total_stock_valor_compra',
                'total_stock_valor_venda',
            ]);
        });
    }
};
