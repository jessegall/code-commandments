<?php

namespace Shop\Http\Controllers\Delegated;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\RouteDelegatesToController;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The kiosk's label endpoint forwards straight to the real LabelController — a duplicate entry point on
 * the same print operation.
 */
final class KioskLabelController
{
    private readonly LabelController $labels;

    public function __construct(LabelController $labels)
    {
        $this->labels = $labels;
    }

    #[Sinful(RouteDelegatesToController::class)]
    public function print(string $sku): string
    {
        return $this->labels->print($sku);
    }

    public function status(int $code): string
    {
        return match ($code) {
            0 => 'idle',
            1 => 'printing',
            default => 'error',
        };
    }
}
