<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('gender')->nullable()->after('remember_token');
            $table->decimal('height_cm', 5, 1)->nullable()->after('gender');
            $table->decimal('weight_kg', 5, 1)->nullable()->after('height_cm');
            $table->string('activity_level')->default('moderate')->after('weight_kg');
            $table->unsignedInteger('daily_goal')->default(2000)->after('activity_level');
            $table->timestamp('profile_completed_at')->nullable()->after('daily_goal');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn([
                'google_id',
                'gender',
                'height_cm',
                'weight_kg',
                'activity_level',
                'daily_goal',
                'profile_completed_at',
            ]);
        });
    }
};
