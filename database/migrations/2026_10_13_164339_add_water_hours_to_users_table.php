<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The hours the day's water is paced across, as 24-hour "HH:MM".
            $table->string('water_starts_at', 5)->default('07:00')->after('daily_water_goal_ml');
            $table->string('water_ends_at', 5)->default('21:00')->after('water_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['water_starts_at', 'water_ends_at']);
        });
    }
};
