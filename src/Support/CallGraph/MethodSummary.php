<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

/**
 * Distilled per-method info retained by the CodebaseIndex.
 *
 * `assignments` maps a local variable name to the expression-kind it was
 * assigned from — letting the OriginTracer check "was $x introduced via a
 * known external source, or is it something opaque we should bail on?".
 *
 * Shapes:
 *   ['kind' => 'array_literal']
 *   ['kind' => 'external_origin', 'reason' => 'json_decode']
 *   ['kind' => 'complex']
 */
final readonly class MethodSummary
{
    /**
     * @param  list<array{name: string, type: ?string}>  $params
     * @param  list<CallSite>  $callSites
     * @param  array<string, array{kind: string, reason?: string}>  $assignments
     */
    public function __construct(
        public string $classFqcn,
        public string $name,
        public array $params,
        public array $callSites,
        public array $assignments,
        public string $filePath,
        public int $line,
    ) {}

    public function hasParam(string $name): bool
    {
        foreach ($this->params as $p) {
            if ($p['name'] === $name) {
                return true;
            }
        }

        return false;
    }

    public function paramIndex(string $name): ?int
    {
        foreach ($this->params as $i => $p) {
            if ($p['name'] === $name) {
                return $i;
            }
        }

        return null;
    }
}
