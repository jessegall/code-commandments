<?php

namespace Shop\Absences;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Prints the return label of a shipment, with the carrier's own barcode when the carrier gives one.
 */
final class ReturnLabelPrinter
{
    public function __construct(private readonly LabelSheet $sheet) {}

    #[Sinful(MadeNullThenChecked::class)]
    public function print(Shipment $shipment): void
    {
        $barcode = $shipment->carrier instanceof BarcodeCarrier ? $shipment->carrier->barcodeFor($shipment->number) : null;

        $this->sheet->write($shipment->number);

        if ($barcode !== null) {
            $this->sheet->drawBarcode($barcode);
        } else {
            $this->sheet->write('no barcode');
        }
    }
}
