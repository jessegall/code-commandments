<?php

namespace Shop\ValueObjects;

/**
 * Righteous twin for ArrayReturnBagDetector: `toValues()` is a SELF-SERIALIZER —
 * every value is a `$this->field` read — turning this value object into its
 * persistence shape. The value-objects skill exempts a to-shape mapper, so it must
 * NOT be flagged.
 */
final readonly class ApiToken
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresAt,
    ) {}

    public function toValues(): array
    {
        return [
            'accessToken' => $this->accessToken,
            'refreshToken' => $this->refreshToken,
            'expiresAt' => $this->expiresAt,
        ];
    }
}
