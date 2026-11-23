<?php

namespace Shop\Tills;

use Closure;
use JesseGall\CodeCommandments\Sins\Backend\NullableCallback;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Writes a till's end-of-day report with the layout chosen: a named layout, one built inline, or the plain one.
 */
final class TillReport
{
    #[Righteous(NullableCallback::class)]
    public function written(string $till, string | Closure | null $layout): string
    {
        if ($layout instanceof Closure) {
            return $layout($till);
        }

        return $layout === null ? "report {$till}" : "report {$till} ({$layout})";
    }
}
