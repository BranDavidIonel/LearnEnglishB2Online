<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The latest result of one paper of a Cambridge mock test, as the page stored it
 * ({score, correct, total, parts} for Reading / Listening, {score} for the others),
 * and the day it was taken.
 *
 * @property int $id
 * @property int $user_id
 * @property int $cambridge_test_id
 * @property string $paper
 * @property array<string, mixed> $result
 * @property CarbonImmutable|null $taken_on
 */
#[Fillable(['user_id', 'paper', 'result', 'taken_on'])]
class CambridgeResult extends Model
{
    public $timestamps = false;

    /** Dates only, so they compare as "Y-m-d" in SQL on SQLite and MySQL alike. */
    protected $dateFormat = 'Y-m-d';

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'taken_on' => 'date',
        ];
    }

    /** @return BelongsTo<CambridgeTest, $this> */
    public function cambridgeTest(): BelongsTo
    {
        return $this->belongsTo(CambridgeTest::class);
    }
}
