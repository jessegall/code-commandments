<?php

namespace Shop\Tills;

use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Container\Container;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * One label printer for the whole run, labelling each bag with the till of the scope it is printed in.
 */
#[Singleton]
final class TillLabels
{
    private int $printed = 0;

    public function __construct(private readonly Container $container) {}

    #[Righteous(ContainerReach::class)]
    public function label(string $bag): string
    {
        $this->printed++;

        return sprintf('%s at till %d (#%d)', $bag, $this->container->make(CurrentTill::class)->number(), $this->printed);
    }
}
