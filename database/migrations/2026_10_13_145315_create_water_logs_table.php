<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('amount_ml');
            // Which container was tapped (small cup, bottle…), kept so the list can show it.
            $table->string('container', 20);
            $table->date('consumed_on');
            $table->timestamps();

            $table->index(['user_id', 'consumed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_logs');
    }
};
