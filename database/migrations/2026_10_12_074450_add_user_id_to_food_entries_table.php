<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_entries', function (Blueprint $table) {
            // Nullable: entries logged before accounts existed have no owner
            // until the first account claims them.
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index(['user_id', 'consumed_on']);
        });
    }

    public function down(): void
    {
        Schema::table('food_entries', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['user_id', 'consumed_on']);
            $table->dropColumn('user_id');
        });
    }
};
