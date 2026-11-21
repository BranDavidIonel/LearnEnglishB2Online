<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The account that logs in. The app has a single one (the learner), made by the
 * database seeder from .env (config/learning.php "admin"). Everything the learner does
 * is stored in the tables below, one set per user.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $locale
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasOne<LearnerProfile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(LearnerProfile::class);
    }

    /** @return HasOne<Placement, $this> */
    public function placement(): HasOne
    {
        return $this->hasOne(Placement::class);
    }

    /** @return HasMany<DailyXp, $this> */
    public function dailyXp(): HasMany
    {
        return $this->hasMany(DailyXp::class);
    }

    /** @return HasMany<ProgressSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(ProgressSnapshot::class);
    }

    /** @return HasMany<UnitProgress, $this> */
    public function unitProgress(): HasMany
    {
        return $this->hasMany(UnitProgress::class);
    }

    /** @return HasMany<ExamResult, $this> */
    public function examResults(): HasMany
    {
        return $this->hasMany(ExamResult::class);
    }

    /** @return HasMany<WordReview, $this> */
    public function wordReviews(): HasMany
    {
        return $this->hasMany(WordReview::class);
    }

    /** @return HasMany<VerbReview, $this> */
    public function verbReviews(): HasMany
    {
        return $this->hasMany(VerbReview::class);
    }

    /** @return HasMany<Mistake, $this> */
    public function mistakes(): HasMany
    {
        return $this->hasMany(Mistake::class);
    }

    /** @return HasMany<BestScore, $this> */
    public function bestScores(): HasMany
    {
        return $this->hasMany(BestScore::class);
    }

    /** @return HasMany<SpeakingScore, $this> */
    public function speakingScores(): HasMany
    {
        return $this->hasMany(SpeakingScore::class);
    }

    /** @return HasMany<CambridgeTest, $this> */
    public function cambridgeTests(): HasMany
    {
        return $this->hasMany(CambridgeTest::class);
    }

    /** @return HasMany<CambridgeResult, $this> */
    public function cambridgeResults(): HasMany
    {
        return $this->hasMany(CambridgeResult::class);
    }
}
