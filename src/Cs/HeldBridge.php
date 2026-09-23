<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cs;

use JesseGall\PhpTypes\Option;

/**
 * A bridge sought the first time a read needs one and kept after — none when `dotnet` is missing. A
 * scan holds its own for one read; the journal's hook service holds one for its whole life, so every
 * per-edit C# check reaches a bridge that is already running.
 */
final class HeldBridge
{
    /**
     * @var Option<Bridge>
     */
    private Option $bridge;

    private bool $sought = false;

    public function __construct()
    {
        $this->bridge = Option::none();
    }

    /**
     * @return Option<Bridge>
     */
    public function bridge(): Option
    {
        if (! $this->sought) {
            $this->bridge = Bridge::located();
            $this->sought = true;
        }

        return $this->bridge;
    }
}
