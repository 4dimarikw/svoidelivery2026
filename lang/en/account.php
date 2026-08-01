<?php

// App-owned auth/account copy (login, register, password reset). Not in
// auth.php/passwords.php/validation.php — those are managed by
// laravel-lang/common and get overwritten by `composer post-update-cmd` →
// `artisan lang:update`. Not in ui.php either — that's reserved for
// <x-ui.*> component-internal strings, not page copy.

return [
    'field' => [
        'name' => 'Name',
        'email' => 'E-mail',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'new_password' => 'New password',
    ],

    'login' => [
        'title' => 'Welcome back',
        'subtitle' => 'Log in to order like one of our own',
        'remember' => 'Remember me',
        'forgot' => 'Forgot your password?',
        'submit' => 'Log in',
        'no_account' => 'First time here?',
        'create' => 'Create an account',
    ],

    'register' => [
        'title' => 'Join us',
        'subtitle' => 'One minute and delivery is yours',
        'age_gate' => 'I am 18 years or older',
        'age_gate_required' => 'Please confirm you are 18 years or older.',
        'terms_gate' => 'I accept the terms of service',
        'terms_gate_required' => 'Please accept the terms of service.',
        'password_hint' => 'At least 8 characters',
        'submit' => 'Create account',
        'have_account' => 'Already with us?',
        'login' => 'Log in',
    ],

    'forgot' => [
        'title' => 'Password recovery',
        'subtitle' => "We'll email you a reset link",
        'submit' => 'Send reset link',
        'back' => 'Back to login',
    ],

    'reset' => [
        'title' => 'New password',
        'subtitle' => 'Choose a new password for your account',
        'submit' => 'Save password',
    ],

    'confirm' => [
        'title' => 'Confirm your password',
        'subtitle' => 'This is a secure area — please confirm your password before continuing',
        'submit' => 'Confirm',
    ],
];
