<?php

namespace Shop\Tills;

use Illuminate\Contracts\Foundation\Application;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Closes every till of a shift, each in a fresh scope: what the previous till left in the scope is forgotten first,
 * so the till of this pass can only be read after the reset, never injected before it.
 */
final class ShiftClose
{
    public function __construct(private readonly Application $app) {}

    /**
     * @param  list<int>  $tills
     * @return list<int>
     */
    #[Righteous(ContainerReach::class)]
    public function close(array $tills): array
    {
        $closed = [];
        foreach ($tills as $till) {
            $this->app->forgetScopedInstances();
            $current = $this->app->make(CurrentTill::class);
            $current->open($till);
            $closed[] = $current->number();
        }

        return $closed;
    }
}
