<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The course: the state of each unit (lesson steps done, best test score, passed, sent
 * back to review), the level exams, and the questions answered wrong ("my mistakes").
 * Units and levels are identified by the ids of the content files (a1-u01, a1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('unit', 20);
            // The lesson steps done, e.g. ["vocab", "grammar"].
            $table->json('steps');
            $table->double('best')->default(0);
            $table->boolean('passed')->default(false);
            $table->boolean('review')->default(false);
            $table->unsignedInteger('tries')->default(0);
            $table->unique(['user_id', 'unit']);
        });

        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('level', 2);
            $table->double('best')->default(0);
            $table->boolean('passed')->default(false);
            $table->unsignedInteger('tries')->default(0);
            $table->date('last_taken_on')->nullable();
            $table->unique(['user_id', 'level']);
        });

        // Kept in the order they were added (the id): the oldest one goes when the list is full.
        Schema::create('mistakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // "<unit>:<exercise index>", "<unit>:r<reading question>" or "<unit>:t<test question>".
            $table->string('key', 40);
            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mistakes');
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('unit_progress');
    }
};
