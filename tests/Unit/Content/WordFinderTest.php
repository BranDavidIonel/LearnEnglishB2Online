<?php

use App\Services\Content\Validation\LessonWordsValidator;
use App\Services\Content\WordFinder;

/*
| WordFinder on the shipped content: real forms of the lessons and what they must resolve to,
| the traps the rules must not fall into, the word rule, sentence starts and expressions.
*/

beforeEach(function () {
    $this->finder = app(WordFinder::class);
});

/** [headword, source, relation] of a form, or null. */
function tapped(string $form, bool $sentenceStart = false): ?array
{
    $match = test()->finder->find($form, $sentenceStart);

    return $match === null ? null : [$match->headword, $match->source->value, $match->relation?->value];
}

it('resolves the real forms of the lessons', function (string $form, string $headword, string $source, ?string $relation) {
    expect(tapped($form))->toBe([$headword, $source, $relation]);
})->with([
    ['names', 'name', 'course', 'plural'],
    ['children', 'child', 'course', 'plural'],
    ['owners', 'owner', 'course', 'plural'],
    ['things', 'thing', 'course', 'plural'],
    ['sprints', 'sprint', 'course', 'plural'],
    ['charts', 'chart', 'glossary', 'plural'],
    ['guys', 'guy', 'glossary', 'plural'],
    ['accounts', 'account', 'course', 'plural'],
    ['taxes', 'tax', 'course', 'plural'],
    ['days', 'day', 'course', 'plural'],
    ['boys', 'boy', 'course', 'plural'],
    ['browsers', 'browser', 'glossary', 'plural'],
    ['revisions', 'revision', 'glossary', 'plural'],
    ['weaknesses', 'weakness', 'course', 'plural'],
    ['directions', 'direction', 'glossary', 'plural'],
    ['brothers', 'brother', 'course', 'plural'],
    ['orders', 'order', 'glossary', 'plural'],
    ['jams', 'jam', 'course', 'plural'],
    ['weekends', 'weekend', 'course', 'plural'],
    ['materials', 'material', 'glossary', 'plural'],
    ['backups', 'backup', 'course', 'plural'],
    ['salaries', 'salary', 'course', 'plural'],
    ['testimonials', 'testimonial', 'course', 'plural'],
    ['seconds', 'second', 'glossary', 'plural'],
    ['offices', 'office', 'course', 'plural'],
    ['planes', 'plane', 'course', 'plural'],
    ['places', 'place', 'course', 'plural'],
    ['shareholders', 'shareholder', 'course', 'plural'],
    ['benefits', 'benefit', 'course', 'plural'],
    ['hackers', 'hacker', 'glossary', 'plural'],
    ['chargers', 'charger', 'course', 'plural'],
    ['millions', 'million', 'course', 'plural'],
    ['streets', 'street', 'course', 'plural'],
    ['colours', 'colour', 'course', 'plural'],
    ['languages', 'language', 'course', 'plural'],
    ['errors', 'error', 'glossary', 'plural'],
    ['volunteers', 'volunteer', 'course', 'plural'],
    ['flowers', 'flower', 'glossary', 'plural'],
    ['recommendations', 'recommendation', 'course', 'plural'],
    ['classes', 'class', 'course', 'plural'],
    ['speeds', 'speed', 'course', 'form'],
    ['what\'s', 'what', 'course', 'form'],
    ['leads', 'lead', 'course', 'form'],
    ['where\'s', 'where', 'course', 'form'],
    ['lights', 'light', 'course', 'form'],
    ['worse', 'bad', 'course', 'form'],
    ['deals', 'deal', 'course', 'form'],
    ['there\'s', 'there', 'course', 'form'],
    ['let\'s', 'let', 'course', 'form'],
    ['here\'s', 'here', 'course', 'form'],
    ['simplified', 'simplify', 'glossary', 'past'],
    ['disappeared', 'disappear', 'course', 'past'],
    ['burned', 'burn', 'course', 'past'],
    ['saved', 'save', 'course', 'past'],
    ['gave', 'give', 'course', 'past'],
    ['vanished', 'vanish', 'course', 'past'],
    ['checked', 'check', 'glossary', 'past'],
    ['calculated', 'calculate', 'course', 'past'],
    ['argued', 'argue', 'course', 'past'],
    ['submitted', 'submit', 'course', 'past'],
    ['ran', 'run', 'course', 'past'],
    ['requested', 'request', 'glossary', 'past'],
    ['supposed', 'suppose', 'glossary', 'past'],
    ['objected', 'object', 'course', 'past'],
    ['rolled', 'roll', 'glossary', 'past'],
    ['uploaded', 'upload', 'course', 'past'],
    ['claimed', 'claim', 'course', 'past'],
    ['grew', 'grow', 'course', 'past'],
    ['affected', 'affect', 'course', 'past'],
    ['countered', 'counter', 'course', 'past'],
    ['wondered', 'wonder', 'course', 'past'],
    ['led', 'lead', 'course', 'past'],
    ['taught', 'teach', 'course', 'past'],
    ['attached', 'attach', 'course', 'past'],
    ['installed', 'install', 'glossary', 'past'],
    ['printed', 'print', 'course', 'past'],
    ['rejected', 'reject', 'course', 'past'],
    ['lent', 'lend', 'course', 'past'],
    ['transformed', 'transform', 'course', 'past'],
    ['validated', 'validate', 'course', 'past'],
    ['children\'s', 'child', 'course', 'possessive'],
    ['project\'s', 'project', 'course', 'possessive'],
    ['friend\'s', 'friend', 'course', 'possessive'],
    ['year\'s', 'year', 'course', 'possessive'],
    ['Andrei\'s', 'Andrei', 'name', 'possessive'],
    ['boss\'s', 'boss', 'course', 'possessive'],
    ['brother\'s', 'brother', 'course', 'possessive'],
    ['people\'s', 'people', 'course', 'possessive'],
    ['devil\'s', 'devil', 'glossary', 'possessive'],
    ['tomorrow\'s', 'tomorrow', 'course', 'possessive'],
    ['cat\'s', 'cat', 'course', 'possessive'],
    ['grandfather\'s', 'grandfather', 'course', 'possessive'],
    ['person\'s', 'person', 'glossary', 'possessive'],
    ['teammate\'s', 'teammate', 'course', 'possessive'],
    ['company\'s', 'company', 'course', 'possessive'],
    ['sister\'s', 'sister', 'course', 'possessive'],
    ['singer\'s', 'singer', 'glossary', 'possessive'],
    ['Matei\'s', 'Matei', 'name', 'possessive'],
    ['cousin\'s', 'cousin', 'course', 'possessive'],
    ['developer\'s', 'developer', 'course', 'possessive'],
    ['Mum\'s', 'mum', 'course', 'possessive'],
    ['ocean\'s', 'ocean', 'glossary', 'possessive'],
    ['dad\'s', 'dad', 'course', 'possessive'],
    ['driver\'s', 'driver', 'course', 'possessive'],
    ['government\'s', 'government', 'glossary', 'possessive'],
    ['someone\'s', 'someone', 'glossary', 'possessive'],
    ['else\'s', 'else', 'course', 'possessive'],
    ['owner\'s', 'owner', 'course', 'possessive'],
    ['today\'s', 'today', 'course', 'possessive'],
    ['son\'s', 'son', 'course', 'possessive'],
    ['Mihai\'s', 'Mihai', 'name', 'possessive'],
    ['competitor\'s', 'competitor', 'course', 'possessive'],
    ['client\'s', 'client', 'course', 'possessive'],
    ['blog\'s', 'blog', 'course', 'possessive'],
    ['Ana\'s', 'Ana', 'name', 'possessive'],
    ['mum\'s', 'mum', 'course', 'possessive'],
    ['night\'s', 'night', 'course', 'possessive'],
    ['user\'s', 'user', 'course', 'possessive'],
    ['team\'s', 'team', 'course', 'possessive'],
    ['audience\'s', 'audience', 'course', 'possessive'],
    ['hotel\'s', 'hotel', 'course', 'possessive'],
    ['daughter\'s', 'daughter', 'course', 'possessive'],
    ['aunt\'s', 'aunt', 'course', 'possessive'],
    ['month\'s', 'month', 'course', 'possessive'],
    ['Jake\'s', 'Jake', 'name', 'possessive'],
    ['puts', 'put', 'course', 'third'],
    ['plays', 'play', 'course', 'third'],
    ['suits', 'suit', 'course', 'third'],
    ['supports', 'support', 'course', 'third'],
    ['uses', 'use', 'course', 'third'],
    ['goes', 'go', 'course', 'third'],
    ['costs', 'cost', 'course', 'third'],
    ['drinks', 'drink', 'course', 'third'],
    ['starts', 'start', 'glossary', 'third'],
    ['approves', 'approve', 'course', 'third'],
    ['wears', 'wear', 'course', 'third'],
    ['checks', 'check', 'glossary', 'third'],
    ['handles', 'handle', 'glossary', 'third'],
    ['exports', 'export', 'course', 'third'],
    ['contradicts', 'contradict', 'course', 'third'],
    ['exists', 'exist', 'glossary', 'third'],
    ['improves', 'improve', 'course', 'third'],
    ['walks', 'walk', 'course', 'third'],
    ['sings', 'sing', 'course', 'third'],
    ['generates', 'generate', 'course', 'third'],
    ['calls', 'call', 'course', 'third'],
    ['notifies', 'notify', 'course', 'third'],
    ['showing', 'show', 'course', 'ing'],
    ['investigating', 'investigate', 'course', 'ing'],
    ['crashing', 'crash', 'course', 'ing'],
    ['growing', 'grow', 'course', 'ing'],
    ['focusing', 'focus', 'glossary', 'ing'],
    ['shouting', 'shout', 'course', 'ing'],
    ['shopping', 'shop', 'course', 'ing'],
    ['Having', 'have', 'course', 'ing'],
    ['rising', 'rise', 'course', 'ing'],
    ['riding', 'ride', 'course', 'ing'],
    ['implying', 'imply', 'course', 'ing'],
    ['banking', 'bank', 'course', 'ing'],
    ['sitting', 'sit', 'course', 'ing'],
    ['flying', 'fly', 'course', 'ing'],
    ['getting', 'get', 'course', 'ing'],
    ['keeping', 'keep', 'course', 'ing'],
    ['seeing', 'see', 'course', 'ing'],
    ['looking', 'look', 'course', 'ing'],
    ['thinking', 'think', 'course', 'ing'],
    ['turning', 'turn', 'glossary', 'ing'],
    ['cooking', 'cook', 'course', 'ing'],
    ['signing', 'sign', 'course', 'ing'],
    ['easier', 'easy', 'course', 'comparative'],
    ['wider', 'wide', 'course', 'comparative'],
    ['older', 'old', 'course', 'comparative'],
    ['smaller', 'small', 'course', 'comparative'],
    ['fewer', 'few', 'glossary', 'comparative'],
    ['cheaper', 'cheap', 'course', 'comparative'],
    ['bigger', 'big', 'course', 'comparative'],
    ['longer', 'long', 'course', 'comparative'],
    ['higher', 'high', 'glossary', 'comparative'],
    ['noisier', 'noisy', 'course', 'comparative'],
    ['deeper', 'deep', 'course', 'comparative'],
    ['earlier', 'early', 'course', 'comparative'],
    ['slower', 'slow', 'course', 'comparative'],
    ['drier', 'dry', 'course', 'comparative'],
    ['warmer', 'warm', 'course', 'comparative'],
    ['simpler', 'simple', 'course', 'comparative'],
    ['safer', 'safe', 'course', 'comparative'],
    ['softer', 'soft', 'course', 'comparative'],
    ['clearer', 'clear', 'glossary', 'comparative'],
    ['thinner', 'thin', 'course', 'comparative'],
    ['thicker', 'thick', 'course', 'comparative'],
    ['harder', 'hard', 'glossary', 'comparative'],
    ['younger', 'young', 'course', 'comparative'],
    ['sooner', 'soon', 'course', 'comparative'],
    ['brighter', 'bright', 'course', 'comparative'],
    ['quieter', 'quiet', 'course', 'comparative'],
    ['healthier', 'healthy', 'glossary', 'comparative'],
    ['faster', 'fast', 'course', 'comparative'],
    ['hotter', 'hot', 'course', 'comparative'],
    ['greener', 'green', 'course', 'comparative'],
    ['greatest', 'great', 'course', 'superlative'],
    ['simplest', 'simple', 'course', 'superlative'],
    ['tallest', 'tall', 'course', 'superlative'],
    ['oldest', 'old', 'course', 'superlative'],
    ['brightest', 'bright', 'course', 'superlative'],
    ['worst', 'bad', 'course', 'superlative'],
    ['slowest', 'slow', 'course', 'superlative'],
    ['youngest', 'young', 'course', 'superlative'],
    ['cheapest', 'cheap', 'course', 'superlative'],
    ['fastest', 'fast', 'course', 'superlative'],
    ['earliest', 'early', 'course', 'superlative'],
    ['smallest', 'small', 'course', 'superlative'],
    ['biggest', 'big', 'course', 'superlative'],
    ['latest', 'late', 'course', 'superlative'],
    ['healthiest', 'healthy', 'glossary', 'superlative'],
    ['ugliest', 'ugly', 'course', 'superlative'],
    ['richest', 'rich', 'course', 'superlative'],
    ['highest', 'high', 'glossary', 'superlative'],
    ['quickest', 'quick', 'glossary', 'superlative'],
    ['worn', 'wear', 'course', 'participle'],
    ['done', 'do', 'course', 'participle'],
    ['stolen', 'steal', 'course', 'participle'],
    ['been', 'be', 'course', 'participle'],
    ['given', 'give', 'course', 'participle'],
    ['shown', 'show', 'course', 'participle'],
    ['fallen', 'fall', 'course', 'participle'],
    ['drunk', 'drink', 'course', 'participle'],
    ['known', 'know', 'course', 'participle'],
    ['eaten', 'eat', 'course', 'participle'],
    ['grown', 'grow', 'course', 'participle'],
    ['written', 'write', 'course', 'participle'],
    ['gone', 'go', 'course', 'participle'],
    ['seen', 'see', 'course', 'participle'],
    ['spoken', 'speak', 'course', 'participle'],
    ['taken', 'take', 'course', 'participle'],
    ['risen', 'rise', 'course', 'participle'],
    ['forgotten', 'forget', 'course', 'participle'],
    ['chosen', 'choose', 'course', 'participle'],
    ['hidden', 'hide', 'course', 'participle'],
    ['thrown', 'throw', 'course', 'participle'],
]);

it('takes a form as it is before any rule', function () {
    expect(tapped('alerts'))->toBe(['alert', 'course', 'plural'])
        ->and(tapped('Iași'))->toBe(['Iași', 'name', null])
        ->and(tapped("I'm"))->toBe(["I'm", 'glossary', null])
        ->and(tapped('start-up'))->toBe(['start-up', 'glossary', null])
        ->and(tapped('four-five-six'))->toBe(['four-five-six', 'glossary', null])
        ->and(tapped('p'))->toBe(['p', 'name', null]);
});

it('is forgiving with the tapped form', function () {
    expect(tapped('Hello!'))->toBe(['hello', 'course', null])
        ->and(tapped('children’s'))->toBe(['child', 'course', 'possessive'])
        ->and(tapped('  went, '))->toBe(['go', 'course', 'past'])
        ->and(tapped(''))->toBeNull()
        ->and(tapped('12'))->toBeNull();
});

it('gives names their possessive and plural only', function () {
    expect(tapped("Andrei's"))->toBe(['Andrei', 'name', 'possessive'])
        ->and(tapped("tom's"))->toBe(['Tom', 'name', 'possessive'])
        ->and(tapped('CRMs'))->toBe(['CRM', 'name', 'plural'])
        ->and(tapped('Andreied'))->toBeNull();
});

it('does not fall into the traps of the rules', function (string $form, ?string $not) {
    expect(tapped($form)[0] ?? null)->not->toBe($not);
})->with([
    'her is not he + r' => ['her', 'he'],
    'as is not the plural of a' => ['as', 'a'],
    'his is not the plural of hi' => ['his', 'hi'],
    'thing is not th + ing' => ['thing', 'th'],
    'seed is not the past of see' => ['seed', 'see'],
    'feed is not fee + d' => ['feed', 'fee'],
    'riding is ride, not rid' => ['riding', 'rid'],
    'upper is not up + er' => ['upper', 'up'],
    'outer is not out + er' => ['outer', 'out'],
    'earring is not ear + ing' => ['earring', 'ear'],
    'better is not bet + er' => ['better', 'bet'],
    'may is not the month' => ['may', 'May'],
]);

it('looks a word that starts a sentence up in lower case first', function () {
    expect(WordFinder::startsSentence('May I come in?', 'May'))->toBeTrue()
        ->and(WordFinder::startsSentence('We start in May.', 'May'))->toBeFalse()
        ->and(WordFinder::startsSentence('Yes. "Thank you," she said.', 'Thank'))->toBeTrue()
        ->and(tapped('May'))->toBe(['May', 'course', null])
        ->and(tapped('Thank', true)[0])->toBe('thank')
        ->and(tapped('Andrei', true))->toBe(['Andrei', 'name', null])
        ->and(tapped('May', true))->toBe(['may', 'glossary', null])
        ->and(tapped('His', true))->toBe(['his', 'glossary', null])
        ->and(tapped('Thank', true))->toBe(['thank', 'glossary', null]);
});

it('shows the course word for a form the glossary only maps', function (string $form, string $headword, string $relation) {
    $match = test()->finder->find($form);

    expect([$match?->headword, $match?->source->value, $match?->relation?->value])->toBe([$headword, 'course', $relation])
        ->and($match?->learnable)->toBeTrue()
        ->and($match?->entry['unit'] ?? null)->not->toBeNull();
})->with([
    ['worse', 'bad', 'form'],
    ['worst', 'bad', 'superlative'],
    ['children', 'child', 'plural'],
    ["children's", 'child', 'possessive'],
    ['lying', 'lie', 'ing'],
    ['men', 'man', 'plural'],
    ['panicked', 'panic', 'past'],
    ['women', 'woman', 'plural'],
]);

it('splits words exactly as the browser does', function () {
    expect(WordFinder::words("Don’t stop: the start-up's 12th CRM in Iași, p.m. -- ___ B2 'quoted' wait___ed T1"))
        ->toBe(["Don't", 'stop', 'the', "start-up's", 'CRM', 'in', 'Iași', 'p', 'm', 'quoted'])
        ->and(WordFinder::occurrences('"Hi!" (Yes.) no'))->toBe([
            ['word' => 'Hi', 'start' => true],
            ['word' => 'Yes', 'start' => true],
            ['word' => 'no', 'start' => true],
        ])
        ->and(WordFinder::occurrences('I was, Paul'))->toBe([
            ['word' => 'I', 'start' => true],
            ['word' => 'was', 'start' => false],
            ['word' => 'Paul', 'start' => false],
        ]);
});

it('finds the expressions of a sentence that hold the tapped word', function () {
    $finder = $this->finder;

    expect(array_column($finder->expressions('I was on call. I was having dinner.', 'on'), 'en'))->toContain('on call')
        ->and(array_column($finder->expressions('I was on call.', 'was'), 'en'))->not->toContain('on call')
        ->and(array_column($finder->expressions("I'm looking forward to the weekend.", 'looking'), 'en'))->toContain('look forward to')
        ->and(array_column($finder->expressions("I can't stand meetings.", 'stand'), 'en'))->toContain("can't stand")
        ->and(array_column($finder->expressions('She made up her mind.', 'mind'), 'en'))->toContain('make up your mind')
        ->and(array_column($finder->expressions("I'm looking forward to the weekend.", 'weekend'), 'en'))->not->toContain('look forward to')
        ->and($finder->expressions('Nothing here.', 'here'))->toBe([]);
});

it('explains every word shown where the word window is on', function () {
    $missing = array_column((new LessonWordsValidator(resource_path('content')))->unexplained($this->finder), 'form');

    expect($missing)->toBe([]);
});
