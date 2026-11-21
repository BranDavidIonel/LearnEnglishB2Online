<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The learner's totals and preferences (one row per user) and the result of the
 * placement test. In the original progress.json: xp, goal, streak, settings, flags,
 * speak.said and placement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedSmallInteger('goal')->default(50);
            $table->unsignedInteger('streak_count')->default(0);
            $table->date('streak_last')->nullable();
            $table->string('theme', 10)->default('dark');
            $table->double('speech_rate')->default(0.9);
            $table->string('voice')->nullable();
            $table->boolean('sound')->default(true);
            // Sentences said well enough in the speaking practice.
            $table->unsignedInteger('spoken')->default(0);
            $table->json('flags')->nullable();
            $table->timestamps();
        });

        Schema::create('placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Index of the level the learner starts with (the levels before it count as known).
            $table->unsignedTinyInteger('start_index');
            // Score of each level tested: {"a1": 0.83, "a2": 0.5}.
            $table->json('scores');
            $table->date('taken_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placements');
        Schema::dropIfExists('learner_profiles');
    }
};
