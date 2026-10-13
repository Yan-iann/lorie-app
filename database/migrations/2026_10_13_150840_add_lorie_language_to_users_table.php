<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The language Lorie's messages are written in: en, tl, ceb or mix.
            $table->string('lorie_language', 8)->default('mix')->after('daily_water_goal_ml');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('lorie_language');
        });
    }
};
