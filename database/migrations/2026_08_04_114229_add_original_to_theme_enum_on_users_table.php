<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_theme_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_theme_check CHECK (theme IN ('daylight', 'nightflight', 'terracotta', 'retro-wanderlust', 'sakura-bloom', 'original', 'auto'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_theme_check');
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_theme_check CHECK (theme IN ('daylight', 'nightflight', 'terracotta', 'retro-wanderlust', 'sakura-bloom', 'auto'))");
    }
};
