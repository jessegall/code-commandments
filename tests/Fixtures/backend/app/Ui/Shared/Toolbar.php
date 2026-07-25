<?php

namespace Shop\Ui\Shared;

use JesseGall\CodeCommandments\Sins\Backend\NamespaceDependency;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Ui\Pages\Dashboard;

/**
 * A shared toolbar, reusable across pages.
 */
final class Toolbar
{
    /**
     * Reaching two layers up for a page's heading. The toolbar is only "shared" until the first
     * page it knows by name — after this it can never appear on any other one.
     */
    #[Sinful(NamespaceDependency::class)]
    public function caption(): string
    {
        return Dashboard::heading();
    }
}
