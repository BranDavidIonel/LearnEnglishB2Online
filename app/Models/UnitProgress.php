<?php

namespace App\Models;

use App\Casts\ExactFloat;
use App\Enums\UnitStep;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The learner's state in one unit: the lesson steps done, the best score of the unit
 * test, whether it is passed, whether a failed level exam sent it back to review, and
 * how many times the test was taken.
 *
 * @property int $id
 * @property int $user_id
 * @property string $unit
 * @property list<string> $steps
 * @property float $best
 * @property bool $passed
 * @property bool $review
 * @property int $tries
 */
#[Fillable(['unit', 'steps', 'best', 'passed', 'review', 'tries'])]
class UnitProgress extends Model
{
    protected $table = 'unit_progress';

    public $timestamps = false;

    protected $attributes = [
        'steps' => '[]',
        'best' => 0,
        'passed' => false,
        'review' => false,
        'tries' => 0,
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'best' => ExactFloat::class,
            'passed' => 'boolean',
            'review' => 'boolean',
            'tries' => 'integer',
        ];
    }

    public function hasStep(UnitStep|string $step): bool
    {
        $value = $step instanceof UnitStep ? $step->value : $step;

        return in_array($value, $this->steps, true);
    }

    public function addStep(UnitStep $step): void
    {
        if (! $this->hasStep($step)) {
            $this->steps = [...$this->steps, $step->value];
        }
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
