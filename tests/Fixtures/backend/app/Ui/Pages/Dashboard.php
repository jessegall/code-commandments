<?php

namespace Shop\Ui\Pages;

use Shop\Ui\Shared\Panel;

/**
 * The dashboard page — the top of the UI stack, free to use everything below it.
 */
final class Dashboard
{
    public static function heading(): string
    {
        return 'Today';
    }

    public function summary(): Panel
    {
        return new Panel(self::heading());
    }
}
