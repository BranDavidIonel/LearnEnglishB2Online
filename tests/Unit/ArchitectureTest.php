<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Resources\Json\JsonResource;

// Guards the project structure described in the README.

arch('no debugging leftovers')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('models are Eloquent models')
    ->expect('App\Models')
    ->toExtend(Model::class);

arch('controllers are named *Controller')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller');

arch('validation lives in form requests')
    ->expect('App\Http\Requests')
    ->toExtend(FormRequest::class)
    ->toHaveSuffix('Request');

arch('API resources shape the data sent to Vue')
    ->expect('App\Http\Resources')
    ->toExtend(JsonResource::class)
    ->toHaveSuffix('Resource');

arch('enums are backed enums')
    ->expect('App\Enums')
    ->toBeEnums()
    ->ignoring('App\Enums\Concerns');

arch('business logic does not depend on the HTTP layer')
    ->expect('App\Services')
    ->not->toUse(['Inertia\Inertia', 'Illuminate\Http\Request', 'App\Http']);
