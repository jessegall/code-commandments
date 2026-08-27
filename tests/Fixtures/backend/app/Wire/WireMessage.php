<?php

namespace Shop\Wire;

use JesseGall\CodeCommandments\Sins\Backend\ConvertedArgument;
use JesseGall\CodeCommandments\Testing\Fixed;

/**
 * `raise()` asks for the alias, so every caller converts; `raiseFor()` asks for the signal class and
 * converts here — one rule about how a name crosses the wire, in one place.
 */
final class WireMessage
{
    private function __construct(
        public readonly string $signal,
        public readonly string $target,
    ) {}

    public static function raise(string $signal, string $target): self
    {
        return new self($signal, $target);
    }

    /**
     * @param  class-string  $signal
     */
    #[Fixed(ConvertedArgument::class)]
    public static function raiseFor(string $signal, string $target): self
    {
        return new self(SignalAlias::of($signal), $target);
    }
}
