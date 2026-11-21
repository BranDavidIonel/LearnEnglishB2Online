<?php

namespace App\Models;

use App\Casts\ExactFloat;
use App\Enums\Theme;
use Carbon\CarbonImmutable;
use Database\Factories\LearnerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The learner's totals and preferences: total XP, daily goal, day streak, speech and
 * sound settings, the colour theme, the number of sentences said in the speaking
 * practice and a few on/off flags of the pages. One row per user, made on the first change.
 *
 * @property int $id
 * @property int $user_id
 * @property int $xp
 * @property int $goal
 * @property int $streak_count
 * @property string|null $streak_last the last day XP was earned (Y-m-d)
 * @property Theme $theme
 * @property float $speech_rate
 * @property string|null $voice
 * @property bool $sound
 * @property int $spoken
 * @property array<string, mixed>|null $flags
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['xp', 'goal', 'streak_count', 'streak_last', 'theme', 'speech_rate', 'voice', 'sound', 'spoken', 'flags'])]
class LearnerProfile extends Model
{
    /** @use HasFactory<LearnerProfileFactory> */
    use HasFactory;

    /** The values of a new learner (config/learning.php "rules": goal 50, speech rate 0.9). */
    protected $attributes = [
        'xp' => 0,
        'goal' => 50,
        'streak_count' => 0,
        'theme' => 'dark',
        'speech_rate' => 0.9,
        'sound' => true,
        'spoken' => 0,
    ];

    protected function casts(): array
    {
        return [
            'xp' => 'integer',
            'goal' => 'integer',
            'streak_count' => 'integer',
            'theme' => Theme::class,
            'speech_rate' => ExactFloat::class,
            'sound' => 'boolean',
            'spoken' => 'integer',
            'flags' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
