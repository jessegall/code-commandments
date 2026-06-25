<?php
namespace Acme\Notify\NullRight;
final class RateMemo {
    /** @var array<string, float> */
    private array $hits = [];
    public function lookup(string $pair): ?float { return $this->hits[$pair] ?? null; }
}
