<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConfigRead;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The dispatch lanes the config names, read the first time they are asked for and kept for the rest of the request.
 */
#[Righteous(ConfigRead::class)]
final class DispatchLanes
{
    /**
     * @var list<string>|null
     */
    private ?array $names;

    public function __construct()
    {
        $this->names = null;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        if ($this->names !== null) {
            return $this->names;
        }

        return $this->names = $this->configured();
    }

    /**
     * @return list<string>
     */
    private function configured(): array
    {
        return array_keys((array) config('dispatch.lanes'));
    }
}
