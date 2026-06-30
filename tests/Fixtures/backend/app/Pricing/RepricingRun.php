<?php

namespace Shop\Pricing;

use JesseGall\CodeCommandments\Sins\Backend\ScratchStateRestore;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Toggles the pricing basis on `$this` for the duration of one revaluation, then
 * puts it back. The basis is this call's input smuggled through a field — pass it
 * down instead and the save/restore disappears.
 */
final class RepricingRun
{
    private string $basis = 'list';

    /** @var list<string> */
    private array $trail = [];

    #[Sinful(ScratchStateRestore::class)]
    public function revalue(string $basis): void
    {
        $previous = $this->basis;
        $this->basis = $basis;

        $this->trail[] = sprintf('priced on %s', $this->basis);

        $this->basis = $previous;
    }
}
