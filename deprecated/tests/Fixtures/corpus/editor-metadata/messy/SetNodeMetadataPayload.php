<?php

namespace App\EditorMetadata;

use Spatie\LaravelData\Data;

/**
 * The inbound "set node metadata" editor action.
 *
 * ROOT SMELL: one untyped boundary. `$value` is a public readonly `mixed` that
 * carries whatever the editor sent for whatever `$aspect` it named — a label
 * string, a branches array, a specs map, sometimes a single string shorthand,
 * sometimes null. The type decision was punted here, so EVERY downstream reader
 * has to re-coerce the same `mixed` by hand. Type this once (one payload per
 * aspect, each holding a typed list of value objects) and the cascade below
 * evaporates.
 */
class SetNodeMetadataPayload extends Data
{
    public function __construct(
        public readonly string $nodeId,
        public readonly string $aspect,
        public readonly mixed $value,
    ) {}
}
