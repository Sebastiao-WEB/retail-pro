<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('from_location_id');
            $table->uuid('to_location_id');
            $table->uuid('requested_by')->nullable();
            $table->string('status')->default('COMPLETED');
            $table->text('note')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('from_location_id')->references('id')->on('stock_locations');
            $table->foreign('to_location_id')->references('id')->on('stock_locations');
            $table->foreign('requested_by')->references('id')->on('users');
            $table->index(['from_location_id', 'to_location_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
