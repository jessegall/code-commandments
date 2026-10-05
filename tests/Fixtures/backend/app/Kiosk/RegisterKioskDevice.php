<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\ArrayReturnBag;
use JesseGall\CodeCommandments\Testing\Righteous;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Registers a kiosk's device. The package runs it as a controller and validates the request with its rules(),
 * whose array shape the package dictates as Laravel dictates a FormRequest's.
 */
final class RegisterKioskDevice
{
    use AsAction;

    #[Righteous(ArrayReturnBag::class)]
    public function rules(): array
    {
        return [
            'device_name' => 'required|string',
            'os_version' => 'required|string',
        ];
    }

    public function handle(string $deviceName, string $osVersion): string
    {
        return $deviceName . ' on ' . $osVersion;
    }
}
