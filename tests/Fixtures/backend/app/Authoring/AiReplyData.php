<?php

namespace Shop\Authoring;

use Spatie\LaravelData\Data;

/**
 * An assistant's reply. `message` is promised on every reply, including the failures.
 */
final class AiReplyData extends Data
{
    public function __construct(
        public readonly string $message,
        public readonly bool $success,
        public readonly ?string $error,
    ) {}
}
