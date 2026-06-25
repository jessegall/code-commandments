<?php

namespace App\AssistantPatch;

use App\Support\FromArrayOnly;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Raw view of one inbound assistant action — every field nullable, because the
 * payload is type-discriminated and decoded per action type downstream.
 */
#[MapInputName(SnakeCaseMapper::class)]
final class RawAssistantAction extends Data
{
    use FromArrayOnly;

    public function __construct(
        public readonly string $type,
        public readonly ?string $id = null,
        public readonly ?string $summary = null,
        public readonly ?string $nodeId = null,
        public readonly ?NodePatch $node = null,
    ) {}
}
