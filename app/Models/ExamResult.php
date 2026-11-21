<?php

namespace App\Models;

use App\Casts\ExactFloat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The learner's level exam of one level: best score, passed, attempts, last attempt.
 *
 * @property int $id
 * @property int $user_id
 * @property string $level
 * @property float $best
 * @property bool $passed
 * @property int $tries
 * @property CarbonImmutable|null $last_taken_on
 */
#[Fillable(['level', 'best', 'passed', 'tries', 'last_taken_on'])]
class ExamResult extends Model
{
    public $timestamps = false;

    /** Dates only, so they compare as "Y-m-d" in SQL on SQLite and MySQL alike. */
    protected $dateFormat = 'Y-m-d';

    protected $attributes = [
        'best' => 0,
        'passed' => false,
        'tries' => 0,
    ];

    protected function casts(): array
    {
        return [
            'best' => ExactFloat::class,
            'passed' => 'boolean',
            'tries' => 'integer',
            'last_taken_on' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
