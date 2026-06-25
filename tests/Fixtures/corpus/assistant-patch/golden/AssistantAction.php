<?php

namespace App\AssistantPatch;

use App\Support\FromArrayOnly;
use Spatie\LaravelData\Data;

/**
 * Base for every decoded assistant action — carries the discriminator and id.
 */
abstract class AssistantAction extends Data
{
    use FromArrayOnly;

    public function __construct(
        public readonly AssistantActionType $type,
        public readonly string $id,
    ) {}
}
