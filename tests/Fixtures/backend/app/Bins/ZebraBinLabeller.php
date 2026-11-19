<?php

namespace Shop\Bins;

/**
 * Bin labels in the Zebra printer's own language.
 */
final class ZebraBinLabeller implements BinLabeller
{
    public function label(string $bin): string
    {
        return "^XA^FD{$bin}^FS^XZ";
    }

    public function reprint(string $bin, int $copies): string
    {
        return str_repeat($this->label($bin), $copies);
    }

    public function calibrate(float $darkness, int $speedInchesPerSecond): void
    {
        error_log("~SD{$darkness}^PR{$speedInchesPerSecond}");
    }
}
