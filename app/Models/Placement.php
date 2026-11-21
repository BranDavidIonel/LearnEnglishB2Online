<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The result of the placement test: the index of the level the learner starts with
 * (the levels before it count as known) and the score of each level tested.
 *
 * @property int $id
 * @property int $user_id
 * @property int $start_index
 * @property array<string, float|int> $scores
 * @property CarbonImmutable|null $taken_on
 */
#[Fillable(['start_index', 'scores', 'taken_on'])]
class Placement extends Model
{
    public $timestamps = false;

    /** Dates only, so they compare as "Y-m-d" in SQL on SQLite and MySQL alike. */
    protected $dateFormat = 'Y-m-d';

    protected function casts(): array
    {
        return [
            'start_index' => 'integer',
            'scores' => 'array',
            'taken_on' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
