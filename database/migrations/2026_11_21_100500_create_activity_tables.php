<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything outside the course: the best results of the word games and of the speaking
 * games, the best score of each conversation / role-play, and the Cambridge mock exams
 * (one row per test opened, one per paper result, with the writing drafts on the test).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('best_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // "games" (memory, sprint, spell) or "speaking" (pairs, sprint).
            $table->string('area', 10);
            $table->string('game', 20);
            $table->integer('value');
            $table->unique(['user_id', 'area', 'game']);
        });

        Schema::create('speaking_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // A conversation (a1-s01) or the role-play of a unit (role-a1-u01).
            $table->string('scenario', 40);
            $table->double('score');
            $table->unique(['user_id', 'scenario']);
        });

        Schema::create('cambridge_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('exam', 10);
            $table->string('test', 20);
            // The writing drafts, by task number: {"0": "Dear Sam, …"}.
            $table->json('drafts')->nullable();
            $table->unique(['user_id', 'exam', 'test']);
        });

        Schema::create('cambridge_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cambridge_test_id')->constrained()->cascadeOnDelete();
            $table->string('paper', 20);
            // The result as the page stored it, without its date: {score, correct, total, parts}.
            $table->json('result');
            $table->date('taken_on')->nullable();
            $table->unique(['cambridge_test_id', 'paper']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cambridge_results');
        Schema::dropIfExists('cambridge_tests');
        Schema::dropIfExists('speaking_scores');
        Schema::dropIfExists('best_scores');
    }
};
