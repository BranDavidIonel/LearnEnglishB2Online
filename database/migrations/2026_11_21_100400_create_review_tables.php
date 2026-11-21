<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spaced repetition of the words (by their English text) and of the irregular verbs (by
 * their base form): the box (0-6), the day of the next review, the right / wrong answers,
 * and whether the learner said "I already know it". A word without a row is "new".
 * Rows are kept in the order they were added (the id), like the original progress file.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('word', 191);
            $table->unsignedTinyInteger('box')->default(0);
            $table->date('due_on');
            $table->unsignedInteger('ok')->default(0);
            $table->unsignedInteger('bad')->default(0);
            $table->boolean('known')->default(false);
            $table->unique(['user_id', 'word']);
            $table->index(['user_id', 'due_on']);
        });

        Schema::create('verb_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('verb', 50);
            $table->unsignedTinyInteger('box')->default(0);
            $table->date('due_on');
            $table->unsignedInteger('ok')->default(0);
            $table->unsignedInteger('bad')->default(0);
            $table->boolean('known')->default(false);
            $table->unique(['user_id', 'verb']);
            $table->index(['user_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verb_reviews');
        Schema::dropIfExists('word_reviews');
    }
};
