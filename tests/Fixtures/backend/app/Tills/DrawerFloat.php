<?php

namespace Shop\Tills;

use JesseGall\CodeCommandments\Sins\Backend\CancelledCoalesce;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The cash a drawer starts the day with, counted in when the till opens.
 */
final class DrawerFloat
{
    public ?int $countedCents = null;

    /**
     * @var list<string>
     */
    private array $warnings = [];

    #[Sinful(CancelledCoalesce::class)]
    public function open(string $till): string
    {
        if (($this->countedCents ?? 0) === 0) {
            $this->warnings[] = "{$till} opened without a float";

            return 'warn';
        }

        return 'ok';
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }
}
