<?php

namespace Shop\Ui\Pages;

/**
 * The dashboard page — the top of the UI stack. Everything below may be built without it.
 */
final class Dashboard
{
    public static function heading(): string
    {
        return 'Today';
    }
}
