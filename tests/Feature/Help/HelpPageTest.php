<?php

use App\Models\User;
use App\Services\Content\ContentRepository;
use App\Services\Help\HelpPage;
use App\Services\Progress\WordReviewService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('sends guests to the login page', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
})->with(['/help', '/help/plan', '/help/tips']);

it('opens on the pronunciation signs, each with its example words from the course', function () {
    $this->actingAs($this->user)->get(route('help'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Help')
        ->where('tab', 'sounds')
        ->has('sounds.introRo')
        ->has('sounds.groups', 6)
        ->where('sounds.groups.0.id', 'marks')
        ->where('sounds.groups.1.id', 'short')
        ->where('sounds.groups.1.sounds.0.symbol', 'ɪ')
        ->has('sounds.groups.1.sounds.0.likeRo')
        ->where('sounds.groups.1.sounds.0.examples.0.en', 'sister')
        ->where('sounds.groups.1.sounds.0.examples.0.ipa', '/ˈsɪstə/')
        ->has('sounds.groups.1.sounds.0.examples.0.ro')
        ->has('plan.daily')
        ->has('tips'));
});

it('shows the daily plan with the vocabulary still to meet for B1 and B2', function () {
    $vocab = app(WordReviewService::class)->vocabTo($this->user, 'b1');

    $this->actingAs($this->user)->get(route('help', 'plan'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Help')
        ->where('tab', 'plan')
        ->has('plan.introRo')
        ->has('plan.daily', 5)
        ->where('plan.daily.0.link', 'flashcards')
        ->where('plan.daily.0.minutes', 10)
        ->has('plan.weekly', 4)
        ->has('plan.milestones', 3)
        ->where('plan.batch', 20)
        ->has('plan.goals', 2)
        ->where('plan.goals.0.level.id', 'b1')
        ->where('plan.goals.0.vocab.learned', 0)
        ->where('plan.goals.0.vocab.total', $vocab['total'])
        ->where('plan.goals.0.days', (int) ceil($vocab['new'] / 20))
        ->where('plan.goals.1.level.id', 'b2'));
});

it('counts fewer days once words are in the deck', function () {
    $words = app(WordReviewService::class);
    $before = app(HelpPage::class)->plan($this->user)['goals'][0]['days'];

    foreach (array_slice(array_keys(app(ContentRepository::class)->words()), 0, 20) as $en) {
        $words->learn($this->user, $en);
    }

    expect(app(HelpPage::class)->plan($this->user)['goals'][0]['days'])->toBe($before - 1);
});

it('shows the advice', function () {
    $this->actingAs($this->user)->get(route('help', 'tips'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Help')
        ->where('tab', 'tips')
        ->has('tips', 10)
        ->has('tips.0.emoji')
        ->has('tips.0.titleRo')
        ->has('tips.0.textRo'));
});

it('has no page for a tab that does not exist', function () {
    $this->actingAs($this->user)->get('/help/grammar')->assertNotFound();
});

it('names only course words as examples, each with its sign in the transcription', function () {
    $content = app(ContentRepository::class);
    $problems = [];

    foreach ($content->help()['sounds']['groups'] as $group) {
        foreach ($group['sounds'] as $sound) {
            foreach ($sound['examples'] as $en) {
                $word = $content->word($en) ?? $content->extraWord($en);

                if ($word === null) {
                    $problems[] = "{$sound['symbol']}: \"{$en}\" is not a word of the course";
                } elseif (! preg_match('/[\s…]/u', $sound['symbol']) && ! str_contains($word['ipa'], $sound['symbol'])) {
                    $problems[] = "{$sound['symbol']}: not in {$en} {$word['ipa']}";
                }
            }
        }
    }

    expect($problems)->toBe([]);
});

it('explains every sign the transcriptions of the course use', function () {
    $content = app(ContentRepository::class);
    $explained = [];

    foreach ($content->help()['sounds']['groups'] as $group) {
        foreach ($group['sounds'] as $sound) {
            $explained = [...$explained, ...mb_str_split($sound['symbol'])];
        }
    }

    $entries = [...array_values($content->words()), ...$content->verbs(), ...$content->glossary()['words'], ...$content->glossary()['names']];
    $used = [];

    foreach ($entries as $entry) {
        foreach (mb_str_split($entry['ipa'] ?? '') as $sign) {
            $used[$sign] = $entry['en'] ?? $entry['v1'];
        }
    }

    expect(array_diff_key($used, array_flip($explained)))->toBe([]);
});

it('sends every line of the plan to a part of the app the page knows', function () {
    $plan = app(ContentRepository::class)->help()['plan'];
    $links = array_column([...$plan['daily'], ...$plan['weekly'], ...$plan['milestones']], 'link');

    expect($links)->toHaveCount(12)
        ->and(array_diff($links, ['flashcards', 'words', 'learn', 'speaking', 'mistakes', 'cambridge', 'grammar', 'progress']))->toBe([]);
});
