<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

/**
 * Walks upward through same-scroll callers to find the origin of an array
 * value passed as a parameter. Returns null when the chain is ambiguous,
 * contains complex expressions, or runs past the configured depth — the
 * prophet falls back to its local hint in that case.
 */
final class OriginTracer
{
    public function __construct(
        private readonly CodebaseIndex $index,
        private readonly int $maxDepth = 10,
    ) {}

    public function trace(string $classFqcn, string $methodName, string $paramName): ?OriginTrace
    {
        return $this->walk($classFqcn, $methodName, $paramName, 0, []);
    }

    /**
     * @param  array<string, true>  $visited  cycle guard keyed by "fqcn::method::param"
     */
    private function walk(
        string $classFqcn,
        string $methodName,
        string $paramName,
        int $depth,
        array $visited,
    ): ?OriginTrace {
        if ($depth >= $this->maxDepth) {
            return null;
        }

        $guardKey = $classFqcn . '::' . $methodName . '::' . $paramName;

        if (isset($visited[$guardKey])) {
            return null;
        }

        $visited[$guardKey] = true;

        $class = $this->index->classByFqcn($classFqcn);

        if ($class === null) {
            return null;
        }

        $method = $class->methods[$methodName] ?? null;

        if ($method === null) {
            return null;
        }

        $paramIdx = $method->paramIndex($paramName);

        if ($paramIdx === null) {
            return null;
        }

        $callers = $this->index->callersOf($classFqcn, $methodName);

        if (empty($callers)) {
            return null;
        }

        /** @var list<OriginTrace> $results */
        $results = [];

        foreach ($callers as $cs) {
            $result = $this->resolveCaller($cs, $paramIdx, $depth, $visited);

            if ($result === null) {
                return null;
            }

            $results[] = $result;
        }

        if (empty($results)) {
            return null;
        }

        // All callers must converge on the same origin; otherwise the trace
        // is ambiguous and we fall back.
        $first = $results[0];

        foreach ($results as $other) {
            if ($other->originClassFqcn !== $first->originClassFqcn
                || $other->originMethod !== $first->originMethod
            ) {
                return null;
            }
        }

        return $first;
    }

    /**
     * @param  array<string, true>  $visited
     */
    private function resolveCaller(
        CallSite $cs,
        int $paramIdx,
        int $depth,
        array $visited,
    ): ?OriginTrace {
        $arg = $cs->argExprs[$paramIdx] ?? null;

        if ($arg === null || $arg['kind'] === 'complex') {
            return null;
        }

        if ($arg['kind'] === 'prop') {
            return new OriginTrace(
                originClassFqcn: $cs->callerClassFqcn,
                originMethod: $cs->callerMethod,
                file: $cs->callerFile,
                line: $cs->line,
                hops: $depth + 1,
                reason: '$this->' . ($arg['prop'] ?? '?'),
            );
        }

        if ($arg['kind'] !== 'var') {
            return null;
        }

        $varName = $arg['name'] ?? null;

        if ($varName === null) {
            return null;
        }

        $callerClass = $this->index->classByFqcn($cs->callerClassFqcn);

        if ($callerClass === null) {
            return null;
        }

        $callerMethod = $callerClass->methods[$cs->callerMethod] ?? null;

        if ($callerMethod === null) {
            return null;
        }

        // If the caller itself received this variable as a param, recurse.
        if ($callerMethod->hasParam($varName)) {
            return $this->walk(
                $cs->callerClassFqcn,
                $cs->callerMethod,
                $varName,
                $depth + 1,
                $visited,
            );
        }

        // Otherwise inspect the caller's local assignment for an external origin.
        $assignment = $callerMethod->assignments[$varName] ?? null;

        if ($assignment === null) {
            return null;
        }

        if ($assignment['kind'] === 'array_literal') {
            return new OriginTrace(
                originClassFqcn: $cs->callerClassFqcn,
                originMethod: $cs->callerMethod,
                file: $callerMethod->filePath,
                line: $callerMethod->line,
                hops: $depth + 1,
                reason: 'array literal',
            );
        }

        if ($assignment['kind'] === 'external_origin') {
            return new OriginTrace(
                originClassFqcn: $cs->callerClassFqcn,
                originMethod: $cs->callerMethod,
                file: $callerMethod->filePath,
                line: $callerMethod->line,
                hops: $depth + 1,
                reason: $assignment['reason'] ?? 'external source',
            );
        }

        return null;
    }
}
