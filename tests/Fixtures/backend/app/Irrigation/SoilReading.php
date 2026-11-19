<?php

namespace Shop\Irrigation;

use DateTimeImmutable;

final readonly class SoilReading
{
    public function __construct(
        public string $bed,
        public int $moisturePercent,
        public DateTimeImmutable $takenAt,
    ) {}

    public static function takenNow(string $bed, int $moisturePercent): self
    {
        return new self($bed, $moisturePercent, new DateTimeImmutable());
    }

    public function isDry(): bool
    {
        return $this->moisturePercent < 25;
    }
}
