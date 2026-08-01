<?php

use Illuminate\Support\Str;

if (! function_exists('ui_id')) {
    /**
     * Deterministic DOM id for a form field name, shared between <x-ui.field>
     * and its control so both sides compute the same id without passing it
     * back and forth. Deliberately NOT Str::uuid() — a random id would change
     * on every render, breaking Alpine/morph-based DOM diffing and making
     * tests that assert on ids flaky.
     */
    function ui_id(string $name): string
    {
        return 'f-'.Str::of($name)->slug('-');
    }
}
