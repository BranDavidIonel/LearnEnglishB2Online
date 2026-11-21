<?php

namespace App\Services\Verbs;

use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Progress\VerbReviewService;

/**
 * What the irregular verbs page shows: every verb with the learner's state, the shape of its
 * three forms and the answers accepted when they are typed, the counts and the verbs due today.
 * The page only filters and draws; the rules stay in VerbReviewService.
 */
class VerbsPage
{
    public function __construct(
        private readonly ContentRepository $content,
        private readonly VerbReviewService $verbs,
    ) {}

    /**
     * Which shape the three forms have, a big help for remembering them (only the first
     * alternative of a form counts): "aaa" cut – cut – cut, "abb" buy – bought – bought,
     * "aba" come – came – come, "abc" go – went – gone.
     *
     * @param  array<string, mixed>  $verb
     */
    public static function pattern(array $verb): string
    {
        $base = (string) $verb['v1'];
        $past = VerbReviewService::alternatives((string) $verb['v2'])[0] ?? '';
        $participle = VerbReviewService::alternatives((string) $verb['v3'])[0] ?? '';

        return match (true) {
            $base === $past && $past === $participle => 'aaa',
            $past === $participle => 'abb',
            $base === $participle => 'aba',
            default => 'abc',
        };
    }

    /**
     * Every verb, the most used first, with its state (VerbReviewService::withState()).
     *
     * @return list<array<string, mixed>>
     */
    public function verbs(User $user): array
    {
        return array_map(fn (array $verb): array => $this->verbs->withState($user, $verb), $this->content->verbs());
    }

    /**
     * The base forms of the verbs to review today, in review order.
     *
     * @return list<string>
     */
    public function due(User $user): array
    {
        return array_map(fn (array $verb): string => (string) $verb['v1'], $this->verbs->due($user));
    }
}
