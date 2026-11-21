<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Cambridge mock test the learner worked on ("pet" / "pet-test1"): its paper results
 * and the drafts of the writing tasks.
 *
 * @property int $id
 * @property int $user_id
 * @property string $exam
 * @property string $test
 * @property array<int|string, string>|null $drafts by task number
 */
#[Fillable(['exam', 'test', 'drafts'])]
class CambridgeTest extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'drafts' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<CambridgeResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(CambridgeResult::class);
    }
}
