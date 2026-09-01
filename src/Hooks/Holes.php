<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Hooks;

/**
 * What a reminder's holes are filled with. A type rather than a bare map because every value in here is
 * MEASURED AT FIRE TIME: a nudge arrives wearing the voice of the system, so a stale number in one does
 * not read as missing, it reads as authoritative. Naming the thing that carries them is what gives that
 * rule somewhere to live.
 */
final readonly class Holes
{
    /**
     * @param  array<string, string>  $values
     */
    private function __construct(private array $values) {}

    public static function none(): self
    {
        return new self([]);
    }

    public function with(string $name, string|int $value): self
    {
        return new self([...$this->values, $name => (string) $value]);
    }

    /**
     * $body with every hole filled. A hole nothing was given for is LEFT AS IT IS rather than blanked —
     * `{count}` surviving into the output says plainly that something was not measured, where an empty
     * space says the measurement was zero.
     */
    public function fill(string $body): string
    {
        $holes = [];

        foreach ($this->values as $name => $value) {
            $holes['{' . $name . '}'] = $value;
        }

        return strtr($body, $holes);
    }
}
