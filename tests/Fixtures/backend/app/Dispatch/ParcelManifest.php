<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * Writes the carrier manifest, in the shape the chosen carrier reads. The two forms share almost
 * everything and differ where the carriers differ — a pallet manifest carries a padded checksum the
 * parcel one has no field for. They are ALTERNATIVES this method chooses between, not one job written
 * twice: neither is missing what the other has, because neither is ever asked for the other's answer.
 */
final class ParcelManifest
{
    public function write(array $lines, bool $palletised): string
    {
        return $palletised ? $this->asPallet($lines) : $this->asParcel($lines);
    }

    private function asPallet(array $lines): string
    {
        $scratch = tempnam(sys_get_temp_dir(), 'pallet');

        if (is_file($scratch)) {
            unlink($scratch);
        }

        return str_pad(md5(implode("\n", $lines)), 40, '0');
    }

    #[Righteous(DivergentTwin::class)]
    private function asParcel(array $lines): string
    {
        $scratch = tempnam(sys_get_temp_dir(), 'parcel');

        if (is_file($scratch)) {
            unlink($scratch);
        }

        return md5(implode("\n", $lines));
    }
}
