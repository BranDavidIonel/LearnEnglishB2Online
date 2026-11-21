<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Day by day: the XP earned each day (progress.json "days") and one snapshot per day of
 * the numbers drawn on the progress chart (progress.json "history"). Days are the
 * learner's local (Romanian) calendar days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_xp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->integer('xp');
            $table->unique(['user_id', 'day']);
        });

        Schema::create('progress_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('xp');
            // Words and irregular verbs learned, % of the road to B1 and to B2. Snapshots
            // written by older versions of the original app may lack some of them.
            $table->unsignedInteger('words')->nullable();
            $table->unsignedInteger('verbs')->nullable();
            $table->double('b1')->nullable();
            $table->double('b2')->nullable();
            $table->unique(['user_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_snapshots');
        Schema::dropIfExists('daily_xp');
    }
};
