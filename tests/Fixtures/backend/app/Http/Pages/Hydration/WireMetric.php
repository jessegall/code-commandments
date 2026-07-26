<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NestedTypeMissingTypeScript;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullableWireObject;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * Scenario 4 — a frontend-bound legend for a chart series. Its optional highlighted point is a nested Data
 * typed `| null`; a label/format shape distinct from the trail, gauge, and panel scenarios.
 */
#[Sinful(NullableWireObject::class)]
#[Sinful(NestedTypeMissingTypeScript::class)]
#[TypeScript]
final class WireMetric extends Data
{
    /**
     * @param list<string> $labels
     */
    public function __construct(
        public readonly string $series,
        public readonly array $labels = [],
        public readonly Highlight|null $highlight = null,
    ) {}

    public function legend(): string
    {
        $suffix = $this->highlight === null ? '' : " ({$this->highlight->label})";

        return $this->series . $suffix;
    }

    public function shout(): array
    {
        return array_map(strtoupper(...), $this->labels);
    }
}

final class Highlight extends Data
{
    public function __construct(public readonly string $label = '') {}
}
