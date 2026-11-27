<?php

namespace Shop\Constants;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

final readonly class Stamp
{
    public function __construct(private \DateTimeImmutable $at) {}

    #[Sinful(ConstantArgument::class)]
    public function label(string $format): string
    {
        return $this->at->format($format);
    }

    #[Fixed(ConstantArgument::class)]
    public function dateLabel(): string
    {
        return $this->label('Y-m-d');
    }
}
