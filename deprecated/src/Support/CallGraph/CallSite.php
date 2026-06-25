<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

/**
 * A single call expression within a method body, with primitive argument
 * fingerprints so the AST can be dropped after index build.
 *
 * `argExprs` entries take one of these shapes (every entry may also carry an
 * `argName` key when the call site used named-argument syntax):
 *   ['kind' => 'var',            'name'  => string]   → a plain $var reference
 *   ['kind' => 'prop',           'prop'  => string]   → $this->prop
 *   ['kind' => 'string_literal', 'value' => string]   → a 'literal' scalar
 *   ['kind' => 'complex']                             → anything else
 */
final readonly class CallSite
{
    /**
     * @param  'method'|'static'|'func'|'nullsafe'  $calleeKind
     * @param  list<array{kind: string, name?: string, prop?: string, value?: string, argName?: string}>  $argExprs
     */
    public function __construct(
        public string $calleeFqcn,
        public string $calleeMethod,
        public string $calleeKind,
        public array $argExprs,
        public string $callerClassFqcn,
        public string $callerMethod,
        public string $callerFile,
        public int $line,
        /**
         * Byte offset of the call node in its file, so a cross-file consumer can
         * re-locate the exact call (even when several share a line). -1 when the
         * position is unavailable.
         */
        public int $startFilePos = -1,
    ) {}
}
