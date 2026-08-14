<?php

namespace Shop\Wire;

use JesseGall\CodeCommandments\Sins\Backend\ConvertedArgument;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Binds the keyboard. It holds a signal CLASS and hands over an alias, because that is the currency
 * `raise()` asks for.
 */
final class HotkeyBinding
{
    #[Sinful(ConvertedArgument::class)]
    public function bind(string $node): WireMessage
    {
        return WireMessage::raise(SignalAlias::of(HotkeyPressed::class), $node);
    }

    /**
     * The FIX: the parameter is declared in the currency the caller holds, and the conversion lives on
     * the far side of the call.
     */
    #[Fixed(ConvertedArgument::class)]
    public function bindDirect(string $node): WireMessage
    {
        return WireMessage::raiseFor(HotkeyPressed::class, $node);
    }
}
