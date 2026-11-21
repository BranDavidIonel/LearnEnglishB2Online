<?php

namespace App\Models;

use App\Casts\ExactFloat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The best score (0..1) of a conversation ("a1-s01") or of the role-play of a unit
 * ("role-a1-u01") in the speaking practice.
 *
 * @property int $id
 * @property int $user_id
 * @property string $scenario
 * @property float $score
 */
#[Fillable(['scenario', 'score'])]
class SpeakingScore extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'score' => ExactFloat::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
