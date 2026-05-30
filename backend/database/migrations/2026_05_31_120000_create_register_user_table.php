<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_user', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('register_id')->constrained('registers')->cascadeOnDelete();
            $table->primary(['user_id', 'register_id']);
        });

        $existentes = DB::table('users')
            ->whereNotNull('register_id')
            ->select('id as user_id', 'register_id')
            ->get();

        foreach ($existentes as $linha) {
            DB::table('register_user')->insertOrIgnore([
                'user_id' => $linha->user_id,
                'register_id' => $linha->register_id,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('register_user');
    }
};
