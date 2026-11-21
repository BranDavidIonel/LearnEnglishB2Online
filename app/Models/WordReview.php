<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\WordReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A word in the learner's flashcard deck (spaced repetition): its box (0-6), the day of
 * the next review, the right / wrong answers, and "known" when the learner said they
 * already knew it. The word is its English text, as in the content (it may hold spaces,
 * apostrophes or question marks).
 *
 * @property int $id
 * @property int $user_id
 * @property string $word
 * @property int $box
 * @property CarbonImmutable $due_on
 * @property int $ok
 * @property int $bad
 * @property bool $known
 */
#[Fillable(['word', 'box', 'due_on', 'ok', 'bad', 'known'])]
class WordReview extends Model
{
    /** @use HasFactory<WordReviewFactory> */
    use HasFactory;

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
