<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A question the learner got wrong, waiting in "my mistakes" until it is answered right.
 * The key points into the content: "a1-u01:3" (exercise 3), "a1-u01:t2" (test question 2)
 * or "a1-u01:r1" (reading question 1, kept but not shown: it needs its text).
 *
 * @property int $id
 * @property int $user_id
 * @property string $key
 */
#[Fillable(['key'])]
class Mistake extends Model
{
    public $timestamps = false;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
