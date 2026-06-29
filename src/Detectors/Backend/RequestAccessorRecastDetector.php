<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Re-coercing a typed request accessor at a CALL SITE — `$request->string('id')->toString()`
 * (or `(string) $request->string('id')`) in a handler/tool/service. The typed accessor
 * is right, but flattening it back to a bare string by key, inline, is the same mistake
 * as `->input()`: every call site re-reads the request. Expose the field as a NAMED
 * getter on a request class (`$request->workflowId()`) — the key, the cast, and the
 * rule live in one place. Points at laravel-idioms.
 *
 * Reads from INSIDE the request class — the named accessor itself, `$this->string('id')
 * ->toString()` — are fine and exempt ({@see \JesseGall\CodeCommandments\Ast\Query::isUsedOn}
 * drops calls whose enclosing class IS the request).
 */
final class RequestAccessorRecastDetector implements Detector
{
    /**
     * Laravel request flavours whose typed reads belong behind a named getter.
     */
    private const array REQUEST_TYPES = [
        'Illuminate\\Http\\Request',
        'Illuminate\\Foundation\\Http\\FormRequest',
        'Laravel\\Mcp\\Request',
    ];

    public function skill(): string
    {
        return 'laravel-idioms';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethod('string')
            ->isUsedOn(...self::REQUEST_TYPES)
            ->where(static fn (AstNode $node): bool => $node->isReCoercedToString())
            ->get();
    }
}
