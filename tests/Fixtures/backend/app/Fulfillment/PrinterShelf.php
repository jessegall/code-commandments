<?php

namespace Shop\Fulfillment;

use Illuminate\Contracts\Container\Container;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ContainerReach;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * A shelf of printers registered by class, each built when first asked for: the class is data, so the container
 * is the factory here, not a hidden dependency.
 */
final class PrinterShelf
{
    private array $printers = [];

    public function __construct(private readonly Container $container) {}

    public function place(string $key, string $printer): void
    {
        $this->printers[$key] = $printer;
    }

    #[Righteous(ContainerReach::class)]
    public function build(string $key): object
    {
        return $this->container->make($this->printers[$key]);
    }
}
