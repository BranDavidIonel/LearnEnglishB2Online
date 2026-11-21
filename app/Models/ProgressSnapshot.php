<?php

namespace App\Models;

use App\Casts\ExactFloat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The numbers of the progress chart on one day: total XP, words and irregular verbs
 * learned, and how far the learner is on the road to B1 and to B2 (in %, one decimal).
 * Today's row is refreshed after every change.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $day
 * @property int $xp
 * @property int|null $words
 * @property int|null $verbs
 * @property float|null $b1
 * @property float|null $b2
 */
#[Fillable(['day', 'xp', 'words', 'verbs', 'b1', 'b2'])]
class ProgressSnapshot extends Model
{
    public $timestamps = false;

    /** Dates only, so they compare as "Y-m-d" in SQL on SQLite and MySQL alike. */
    protected $dateFormat = 'Y-m-d';

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'xp' => 'integer',
            'words' => 'integer',
            'verbs' => 'integer',
            'b1' => ExactFloat::class,
            'b2' => ExactFloat::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
