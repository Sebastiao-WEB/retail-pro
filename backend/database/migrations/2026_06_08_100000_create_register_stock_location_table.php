<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_stock_location', function (Blueprint $table) {
            $table->foreignUuid('register_id')->constrained('registers')->cascadeOnDelete();
            $table->foreignUuid('stock_location_id')->constrained('stock_locations')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['register_id', 'stock_location_id']);
        });

        if (Schema::hasColumn('stock_locations', 'register_id')) {
            DB::table('stock_locations')
                ->whereNotNull('register_id')
                ->orderBy('id')
                ->each(function ($location) {
                    DB::table('register_stock_location')->insertOrIgnore([
                        'register_id' => $location->register_id,
                        'stock_location_id' => $location->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

            Schema::table('stock_locations', function (Blueprint $table) {
                $table->dropColumn('register_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('stock_locations', 'register_id')) {
            Schema::table('stock_locations', function (Blueprint $table) {
                $table->uuid('register_id')->nullable()->after('id');
            });

            DB::table('register_stock_location')
                ->orderBy('stock_location_id')
                ->orderBy('register_id')
                ->each(function ($pivot) {
                    DB::table('stock_locations')
                        ->where('id', $pivot->stock_location_id)
                        ->whereNull('register_id')
                        ->update(['register_id' => $pivot->register_id]);
                });
        }

        Schema::dropIfExists('register_stock_location');
    }
};
