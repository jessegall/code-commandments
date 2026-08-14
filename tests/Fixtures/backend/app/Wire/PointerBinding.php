<?php

namespace Shop\Wire;

use JesseGall\CodeCommandments\Sins\Backend\ConvertedArgument;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Binds the pointer — a different surface, the same conversion spelled again, which is what says the
 * parameter is declared in the wrong currency.
 */
final class PointerBinding
{
    /**
     * @var list<string>
     */
    private array $bound = [];

    #[Sinful(ConvertedArgument::class)]
    public function bind(string $node): WireMessage
    {
        $this->bound[] = $node;

        return WireMessage::raise(SignalAlias::of(PointerReleased::class), $node);
    }

    /**
     * @return list<string>
     */
    public function bound(): array
    {
        return $this->bound;
    }
}
