<?php

namespace Shop\Pricing;

use JesseGall\CodeCommandments\Sins\Backend\ScratchStateRestore;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Toggles the pricing basis on `$this` for the duration of one revaluation, then
 * puts it back. The basis is this call's input smuggled through a field — pass it
 * down instead and the save/restore disappears.
 */
final class RepricingRun
{
    private string $basis = 'list';

    /**
     * @var list<string>
     */
    private array $trail = [];

    #[Sinful(ScratchStateRestore::class)]
    public function revalue(string $basis): void
    {
        $previous = $this->basis;
        $this->basis = $basis;

        $this->trail[] = sprintf('priced on %s', $this->basis);

        $this->basis = $previous;
    }

    /**
     * The FIX: the basis is this call's input, so it stays a PARAMETER and is read from there —
     * `$this->basis` is never written, and the save/restore pair disappears with it.
     */
    #[Fixed(ScratchStateRestore::class)]
    public function revalueOn(string $basis, int $lots): void
    {
        for ($lot = 0; $lot < $lots; $lot++) {
            $this->trail[] = sprintf('lot %d priced on %s', $lot, $basis);
        }
    }
}
