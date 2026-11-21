<?php

use App\Enums\Game;
use App\Enums\SpeakingGame;
use App\Services\Progress\GameService;
use App\Services\Progress\SpeakingService;
use App\Services\Progress\XpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

require_once __DIR__.'/helpers.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->speaking = app(SpeakingService::class);
    $this->games = app(GameService::class);
    $this->user = freshLearner();
});

it('counts the sentences said: 2 XP each, or the XP asked for', function () {
    expect($this->speaking->countSpoken($this->user, 1, 2))->toBe(2)
        ->and($this->speaking->countSpoken($this->user, 3, 3))->toBe(9)
        ->and($this->speaking->state($this->user)['said'])->toBe(4)
        ->and(app(XpService::class)->total($this->user))->toBe(11);
});

it('keeps the best score of a conversation: 15 XP the first time, 5 after', function () {
    expect($this->speaking->recordConversation($this->user, 'a1-s01', 0.6))->toBe(['first' => true, 'xp' => 15, 'best' => 0.6])
        ->and($this->speaking->recordConversation($this->user, 'a1-s01', 0.4))->toBe(['first' => false, 'xp' => 5, 'best' => 0.6])
        ->and($this->speaking->recordConversation($this->user, 'a1-s01', 0.95))->toBe(['first' => false, 'xp' => 5, 'best' => 0.95])
        ->and($this->speaking->recordConversation($this->user, 'role-a1-u01', 0))->toMatchArray(['first' => true, 'best' => 0.0])
        ->and($this->speaking->state($this->user)['scen'])->toBe(['a1-s01' => 0.95, 'role-a1-u01' => 0.0]);
});

it('keeps the records of the speaking games (the higher, the better)', function () {
    expect($this->speaking->record($this->user, SpeakingGame::Sprint, 7))->toBeTrue()
        ->and($this->speaking->record($this->user, SpeakingGame::Sprint, 7))->toBeFalse()
        ->and($this->speaking->record($this->user, SpeakingGame::Sprint, 9))->toBeTrue()
        ->and($this->speaking->record($this->user, SpeakingGame::Pairs, 0))->toBeTrue()
        ->and($this->speaking->record($this->user, SpeakingGame::Pairs, 0))->toBeTrue() // an empty record counts as none
        ->and($this->speaking->state($this->user)['best'])->toBe(['sprint' => 9, 'pairs' => 0])
        ->and($this->games->records($this->user))->toBe([]);
});

it('keeps the records of the word games: the fewest moves at Pairs, the most points elsewhere', function () {
    expect($this->games->record($this->user, Game::Memory, 36))->toBeTrue()
        ->and($this->games->record($this->user, Game::Memory, 40))->toBeFalse()
        ->and($this->games->record($this->user, Game::Memory, 20))->toBeTrue()
        ->and($this->games->record($this->user, Game::Sprint, 16))->toBeTrue()
        ->and($this->games->record($this->user, Game::Sprint, 12))->toBeFalse()
        ->and($this->games->record($this->user, Game::Spell, 8))->toBeTrue()
        ->and($this->games->record($this->user, Game::Spell, 10))->toBeTrue()
        ->and($this->games->records($this->user))->toBe(['memory' => 20, 'sprint' => 16, 'spell' => 10])
        ->and($this->speaking->state($this->user)['best'])->toBe([]);
});
