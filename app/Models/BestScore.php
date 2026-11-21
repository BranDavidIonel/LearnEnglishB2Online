<?php

namespace App\Models;

use App\Enums\ScoreArea;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The best result of a game: a word game (memory moves, sprint points, dictation score)
 * or a speaking game (sound-alike pairs, voice sprint).
 *
 * @property int $id
 * @property int $user_id
 * @property ScoreArea $area
 * @property string $game
 * @property int $value
 */
#[Fillable(['area', 'game', 'value'])]
class BestScore extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'area' => ScoreArea::class,
            'value' => 'integer',
        ];
    }

    /**
     * A first result, or a result better than the record, becomes the record (and is
     * saved). An empty record (0) counts as none, like the original.
     *
     * @return bool true for a new record
     */
    public function keepIfBetter(int $value, bool $lowerIsBetter): bool
    {
        $current = $this->exists ? $this->value : 0;
        $better = $lowerIsBetter ? $value < $current : $value > $current;

        if ($current !== 0 && ! $better) {
            return false;
        }

        $this->value = $value;
        $this->save();

        return true;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
