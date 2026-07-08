<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullToOptionalMap;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Optional;

/*
 * A request wrapper that reads an optional key off its payload and hand-maps its absence onto `Optional`
 * with a null-first ternary. The `optionalOrMissing()` factory is the one named home for this map.
 */
final class MoveRequest
{
    /** @param array<string, mixed> $payload */
    public function __construct(private readonly array $payload = [])
    {
    }

    #[Sinful(NullToOptionalMap::class)]
    public function position(): OptCoords|Optional
    {
        $raw = $this->payload['position'] ?? null;

        return $raw === null ? new Optional : OptCoords::from($raw);
    }

    public function actor(): string
    {
        return (string) ($this->payload['actor'] ?? 'system');
    }
}
