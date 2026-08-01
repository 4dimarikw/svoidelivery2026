<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),

            // One-time consent gate (design system §08). Two separate
            // checkboxes, not one combined "age + terms" checkbox — some
            // jurisdictions treat bundled consents as improper. Validated
            // only; never persisted — see the create() call below and
            // User::$fillable, neither of which references these keys.
            'age_confirmed' => ['accepted'],
            'terms_accepted' => ['accepted'],
        ], [
            // Custom messages live in the app-owned lang/{ru,en}/account.php,
            // not validation.php — that file is managed by laravel-lang and
            // gets overwritten by `composer post-update-cmd` → `artisan lang:update`.
            'age_confirmed.accepted' => __('account.register.age_gate_required'),
            'terms_accepted.accepted' => __('account.register.terms_gate_required'),
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);
    }
}
