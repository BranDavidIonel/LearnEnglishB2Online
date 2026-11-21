<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * The signed-in account, changed from the settings page: name and email, and the password
 * (the only places that set it are here and the database seeder, from .env).
 */
class AccountController extends Controller
{
    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $this->user($request)->update($request->validated());

        return back();
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->user($request)->update(['password' => $request->string('password')->value()]);

        return back();
    }

    private function user(UpdateProfileRequest|UpdatePasswordRequest $request): User
    {
        /** @var User */
        return $request->user();
    }
}
