<?php

namespace Shop\Integrations\Wire;

use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Backend\RawDecodedArrayReturn;
use JesseGall\CodeCommandments\Testing\Righteous;
use JsonSerializable;

/**
 * A webhook payload kept as the JSON it arrived in. Serialising it hands back what it holds, under the signature
 * JsonSerializable fixes, so there is no shape of its own to give the decoded value.
 */
final readonly class StoredPayload implements JsonSerializable
{
    public function __construct(private string $json) {}

    #[Righteous(RawDecodedArrayReturn::class)]
    public function jsonSerialize(): mixed
    {
        return json_decode($this->json, true, 512, JSON_THROW_ON_ERROR);
    }
}

/**
 * How long a rate-limited caller must wait, read from the response headers: a map keyed by header names, not a
 * record with fields.
 */
final class RetryAfter
{
    /**
     * @param  array<string, string>  $headers
     */
    #[Righteous(ArrayBag::class)]
    public static function seconds(array $headers): int
    {
        return (int) $headers['Retry-After'];
    }
}
