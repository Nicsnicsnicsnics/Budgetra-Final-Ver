<?php

if (! function_exists('currency_symbol')) {
    // The signed-in user's chosen display currency symbol (Settings →
    // Preferences), falling back to the Peso for guests/unauthenticated
    // contexts where there's no user record to read a preference from.
    function currency_symbol(): string
    {
        return auth()->check() ? (auth()->user()->currency_symbol ?: '₱') : '₱';
    }
}

if (! function_exists('currency_code')) {
    function currency_code(): string
    {
        return auth()->check() ? (auth()->user()->currency_code ?: 'PHP') : 'PHP';
    }
}
