<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_sheets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('referencia')->unique();
            $table->string('titulo');
            $table->date('data_referencia');
            $table->date('periodo_inicio')->nullable();
            $table->date('periodo_fim')->nullable();
            $table->string('status')->default('DRAFT');
            $table->text('notas')->nullable();
            $table->foreignUuid('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->decimal('total_activo', 14, 2)->default(0);
            $table->decimal('total_passivo', 14, 2)->default(0);
            $table->decimal('total_capital_proprio', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('balance_sheet_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('balance_sheet_id')->constrained('balance_sheets')->cascadeOnDelete();
            $table->string('secao');
            $table->string('grupo')->nullable();
            $table->string('rubrika');
            $table->decimal('valor', 14, 2)->default(0);
            $table->boolean('automatico')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_sheet_lines');
        Schema::dropIfExists('balance_sheets');
    }
};
