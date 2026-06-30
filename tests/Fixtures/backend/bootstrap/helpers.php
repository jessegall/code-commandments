<?php

// Global helpers — file scope, no class. config() here is the framework idiom
// (a helper, not a service reaching into config), so ConfigRead must NOT flag it.

if (! function_exists('shop_currency')) {
    function shop_currency(): string
    {
        return config('shop.currency', 'EUR');
    }
}
