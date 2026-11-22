<?php

namespace Shop\Domain\Factories;

final class Window
{
    private function __construct(private readonly int $opens, private readonly int $closes)
    {
    }

    public static function between(int $opens, int $closes): self
    {
        return $opens <= $closes ? new self($opens, $closes) : new self($closes, $opens);
    }

    public function covers(int $hour): bool
    {
        return $hour >= $this->opens && $hour < $this->closes;
    }

    public function hours(): int
    {
        return $this->closes - $this->opens;
    }
}
