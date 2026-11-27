<?php

namespace Shop\Records;

use JesseGall\CodeCommandments\Testing\Fixed;
use Spatie\LaravelData\Data;

#[Fixed(RepeatedRecordShape::class)]
final class ReplyData extends Data
{
    public function __construct(
        public readonly string $token,
        public readonly string $status,
        public readonly ?string $value,
    ) {}

    public static function failed(CallInput $call): self
    {
        return self::from(['token' => $call->token, 'status' => 'failed', 'value' => null]);
    }

    public static function unavailable(CallInput $call): self
    {
        return self::from(['token' => $call->token, 'status' => 'unavailable', 'value' => null]);
    }
}
