<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An irregular verb in the learner's review queue (spaced repetition, like the words),
 * identified by its base form ("be", "feel").
 *
 * @property int $id
 * @property int $user_id
 * @property string $verb
 * @property int $box
 * @property CarbonImmutable $due_on
 * @property int $ok
 * @property int $bad
 * @property bool $known
 */
#[Fillable(['verb', 'box', 'due_on', 'ok', 'bad', 'known'])]
class VerbReview extends Model
{
    public $timestamps = false;

    /** Dates only, so they compare as "Y-m-d" in SQL on SQLite and MySQL alike. */
    protected $dateFormat = 'Y-m-d';

    protected $attributes = [
        'box' => 0,
        'ok' => 0,
        'bad' => 0,
        'known' => false,
    ];

    protected function casts(): array
    {
        return [
            'box' => 'integer',
            'due_on' => 'date',
            'ok' => 'integer',
            'bad' => 'integer',
            'known' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
