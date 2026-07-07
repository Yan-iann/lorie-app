<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_entries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('quantity')->nullable();
            $table->unsignedInteger('calories');
            $table->decimal('protein_g', 6, 1)->default(0);
            $table->decimal('carbs_g', 6, 1)->default(0);
            $table->decimal('fat_g', 6, 1)->default(0);
            $table->string('notes')->nullable();
            $table->date('consumed_on');
            $table->timestamps();

            $table->index('consumed_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_entries');
    }
};
