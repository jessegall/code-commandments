<?php

namespace Shop\Fulfillment;

use Illuminate\Contracts\Container\Container;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The desk that writes a delivery note line, once by asking an injected container and once by its injected printer.
 */
final class DeliveryNoteDesk
{
    public function __construct(
        private readonly Container $container,
        private readonly DeliveryNotePrinter $printer,
    ) {}

    #[Sinful(ContainerReach::class)]
    public function lineFromContainer(DeliveryInstruction $instruction): string
    {
        return $this->container->make(DeliveryNotePrinter::class)->line($instruction);
    }

    #[Fixed(ContainerReach::class)]
    public function line(DeliveryInstruction $instruction): string
    {
        return $this->printer->line($instruction);
    }
}
