<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support\CallGraph;

/**
 * A single call expression within a method body, with primitive argument
 * fingerprints so the AST can be dropped after index build.
 *
 * `argExprs` entries take one of these shapes:
 *   ['kind' => 'var',      'name' => string]        → a plain $var reference
 *   ['kind' => 'prop',     'prop' => string]        → $this->prop
 *   ['kind' => 'complex']                           → anything else
 */
final readonly class CallSite
{
    /**
     * @param  'method'|'static'|'func'|'nullsafe'  $calleeKind
     * @param  list<array{kind: string, name?: string, prop?: string}>  $argExprs
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
    ) {}
}
