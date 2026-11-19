<?php

namespace Shop\Courier\Printing;

use JesseGall\CodeCommandments\Sins\Backend\ArrayBagParameter;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Which printer each carrier's labels go to, and which parcels are held back. Both arrays are data, not
 * records: a list of tracking codes, and a map keyed by carrier service codes rather than field names.
 */
final class PrinterRouting
{
    /**
     * @var array<string, string>
     */
    private array $printers = [];

    /**
     * @var list<string>
     */
    private array $held = [];

    public function configure(): void
    {
        $this->route(['dhl.express' => 'zebra-1', 'ups.ground' => 'zebra-2']);
        $this->hold(['3S-1001', '3S-1002']);
    }

    /**
     * @param  array<string, string>  $printers
     */
    #[Righteous(ArrayBagParameter::class)]
    public function route(array $printers): void
    {
        $this->printers = [...$this->printers, ...$printers];
    }

    /**
     * @param  list<string>  $trackings
     */
    #[Righteous(ArrayBagParameter::class)]
    public function hold(array $trackings): void
    {
        $this->held = [...$this->held, ...$trackings];
    }
}
