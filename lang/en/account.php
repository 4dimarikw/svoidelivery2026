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
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'patronymic' => 'Patronymic',
        'phone' => 'Phone',
        'phone_help' => 'Russian number with +7 or 8: +7 (999) 123-45-67, 8 999 123 45 67',
        'vk_url' => 'VK link',
        'telegram_url' => 'Telegram link',
        'default_order_comment' => 'Default order comment',
        'current_password' => 'Current password',
        'new_password_confirmation' => 'Confirm new password',
    ],

    'login' => [
        'title' => 'Welcome back',
        'subtitle' => 'Log in to order like one of our own',
        'remember' => 'Remember me',
        'forgot' => 'Forgot your password?',
        'submit' => 'Log in',
        'no_account' => 'First time here?',
        'create' => 'Create an account',
        'or' => 'or',
    ],

    'telegram' => [
        'section_title' => 'Telegram',
        'linked_as' => 'Account linked to Telegram ID :id.',
        'not_linked' => 'Open the bot in Telegram and press "Start" to link your account.',
        'open_in_telegram' => 'Open in Telegram',
        'unlink' => 'Unlink Telegram',
        'unlink_blocked' => 'Telegram cannot be unlinked: it is the only way into this account. Add an email and a password first.',
        'email_optional' => 'You signed in with Telegram — an email is optional.',
        'failed' => 'Could not sign in with Telegram. Please try again.',
        'linked' => 'Telegram linked.',
        'unlinked' => 'Telegram unlinked.',
        'webapp_login' => 'Sign in with Telegram',
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

    'verify' => [
        'title' => 'Verify your email',
        'subtitle' => 'We sent a verification link to your email address',
        'sent' => 'A new verification link has been sent.',
        'resend' => 'Resend verification email',
    ],

    'nav' => [
        'profile' => 'Profile',
        'orders' => 'My orders',
    ],

    'profile' => [
        'basic_title' => 'Basic information',
        'details_title' => 'Personal details',
        'password_title' => 'Password',
        'basic_updated' => 'Saved.',
        'details_updated' => 'Saved.',
        'password_updated' => 'Password changed.',
        'submit' => 'Save',
        'saving' => 'Saving…',
    ],

    'address' => [
        'label' => 'Label',
        'city' => 'City',
        'address' => 'Address',
        'comment' => 'Note',
        'is_default' => 'Make this the default address',
        'add' => 'Add address',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'delete_confirm' => 'Delete this address?',
        'empty' => 'No addresses yet.',
        'default_marker' => 'Default',
        'index_title' => 'Delivery addresses',
        'created' => 'Address added.',
        'updated' => 'Address saved.',
        'deleted' => 'Address deleted.',
    ],

    'favorites' => [
        'title' => 'Favorites',
        'empty' => 'No favorites yet.',
        'clear' => 'Clear all',
        'clear_confirm' => 'Remove all products from favorites?',
        'added' => 'Product added to favorites.',
        'removed' => 'Product removed from favorites.',
        'cleared' => 'Favorites cleared.',
    ],

    'cart' => [
        'title' => 'Cart',
        'empty' => 'Your cart is empty.',
        'items' => 'Items',
        'total_label' => 'Total',
        'checkout' => 'Checkout',
        'continue' => 'Continue shopping',
        'clear' => 'Clear cart',
        'clear_confirm' => 'Clear the cart?',
        'remove' => 'Remove from cart',
        'added' => 'Product added to cart.',
        'removed' => 'Product removed from cart.',
        'cleared' => 'Cart cleared.',
    ],
];
