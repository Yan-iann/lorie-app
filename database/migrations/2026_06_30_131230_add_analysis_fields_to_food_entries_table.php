<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_entries', function (Blueprint $table) {
            $table->decimal('fiber_g', 6, 1)->default(0)->after('fat_g');
            $table->decimal('sugar_g', 6, 1)->default(0)->after('fiber_g');
            $table->decimal('sodium_mg', 8, 1)->default(0)->after('sugar_g');
            $table->string('source')->default('usda')->after('notes');
            $table->string('confidence')->nullable()->after('source');
            $table->text('verification_note')->nullable()->after('confidence');
            $table->json('breakdown')->nullable()->after('verification_note');
        });
    }

    public function down(): void
    {
        Schema::table('food_entries', function (Blueprint $table) {
            $table->dropColumn([
                'fiber_g',
                'sugar_g',
                'sodium_mg',
                'source',
                'confidence',
                'verification_note',
                'breakdown',
            ]);
        });
    }
};
