<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The XP earned on one (Romanian calendar) day.
 *
 * @property int $id
 * @property int $user_id
 * @property CarbonImmutable $day
 * @property int $xp
 */
#[Fillable(['day', 'xp'])]
class DailyXp extends Model
{
    protected $table = 'daily_xp';

    public $timestamps = false;

    /** Dates only, so they compare as "Y-m-d" in SQL on SQLite and MySQL alike. */
    protected $dateFormat = 'Y-m-d';

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'xp' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
